<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Eleves';
$pageSection = 'students';
$admin       = currentUser();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Inscrire un nouvel eleve ───────────────────────────────
    if ($action === 'create') {
        $firstName  = sanitizeString($_POST['first_name'] ?? '');
        $lastName   = sanitizeString($_POST['last_name']  ?? '');
        $gender     = in_array($_POST['gender'] ?? '', ['M','F']) ? $_POST['gender'] : 'M';
        $dob        = $_POST['date_of_birth'] ?? null;
        $classId    = (int)($_POST['class_id'] ?? 0);
        $prevSchool = sanitizeString($_POST['previous_school'] ?? '');
        $medical    = sanitizeString($_POST['medical_notes'] ?? '');
        $scholarship= !empty($_POST['scholarship']) ? 1 : 0;
        $email      = strtolower(trim($_POST['email'] ?? ''));

        $class = $classId ? dbFetchOne("SELECT * FROM classes WHERE id=?", [$classId]) : null;

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif (!$class) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Veuillez selectionner une classe.');
        } else {
            $needsAccount = studentNeedsAccount((int)$class['level_id']);

            if ($needsAccount && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirectWith($_SERVER['PHP_SELF'], 'danger',
                    'A partir de la 7e annee, un email valide est requis pour creer le compte de l\'eleve.');
            } elseif ($needsAccount && dbFetchOne("SELECT id FROM users WHERE email=?", [$email])) {
                redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette adresse email est deja utilisee.');
            } else {
                $studentNumber = generateStudentNumber();
                $userId = null;

                if ($needsAccount) {
                    $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
                    dbExecute(
                        "INSERT INTO users (role_id, username, email, password, first_name, last_name, gender, date_of_birth, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
                        [ROLE_STUDENT, $username, $email, hashPassword(generateToken(24)), $firstName, $lastName, $gender, $dob ?: null]
                    );
                    $userId = dbLastId();
                }

                dbExecute(
                    "INSERT INTO students (user_id, class_id, academic_year_id, level_id, option_id, grade_year,
                                            student_number, enrollment_date, has_account, previous_school, medical_notes,
                                            scholarship, first_name, last_name, gender, date_of_birth)
                     VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId, $classId, $yearId, $class['level_id'], $class['option_id'], $class['grade_year'],
                        $studentNumber, $needsAccount ? 1 : 0, $prevSchool ?: null, $medical ?: null,
                        $scholarship,
                        $needsAccount ? null : $firstName, $needsAccount ? null : $lastName,
                        $needsAccount ? null : $gender, $needsAccount ? null : ($dob ?: null),
                    ]
                );

                logActivity('student_created', "Eleve inscrit : $firstName $lastName ($studentNumber)");

                if ($needsAccount) {
                    $token  = createInvitation($userId, ROLE_STUDENT, $admin['id']);
                    $mailOk = sendInvitationEmail($email, $firstName, ROLE_LABELS[ROLE_STUDENT], $token);
                    logActivity('invitation_sent', "Invitation envoyee a $email" . ($mailOk ? '' : ' (echec envoi email)'));
                    redirectWith($_SERVER['PHP_SELF'], 'success',
                        "Eleve inscrit ($studentNumber). Invitation envoyee a $email." .
                        (APP_ENV === 'development' ? " Lien : " . BASE_URL . "/auth/activate-account.php?token=$token" : ''));
                } else {
                    redirectWith($_SERVER['PHP_SELF'], 'success',
                        "Eleve inscrit ($studentNumber). Profil scolaire sans compte (primaire).");
                }
            }
        }
    }

    // ── Renvoyer une invitation (eleves avec compte) ──────────
    if ($action === 'resend') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $u = dbFetchOne("SELECT * FROM users WHERE id=? AND role_id=?", [$userId, ROLE_STUDENT]);
        if ($u && !$u['is_active']) {
            $token  = createInvitation($userId, ROLE_STUDENT, $admin['id']);
            $mailOk = sendInvitationEmail($u['email'], $u['first_name'], ROLE_LABELS[ROLE_STUDENT], $token);
            logActivity('invitation_resent', "Invitation renvoyee a " . $u['email'] . ($mailOk ? '' : ' (echec envoi email)'));
            redirectWith($_SERVER['PHP_SELF'], 'success',
                "Invitation renvoyee." . (APP_ENV === 'development' ? " Lien : " . BASE_URL . "/auth/activate-account.php?token=$token" : ''));
        } else {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Impossible de renvoyer une invitation pour ce compte.');
        }
    }

    // ── Changer le statut (actif / suspendu) ───────────────────
    if ($action === 'toggle_status') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = dbFetchOne("SELECT * FROM students WHERE id=?", [$studentId]);
        if ($student) {
            $newStatus = $student['status'] === 'actif' ? 'suspendu' : 'actif';
            dbExecute("UPDATE students SET status=? WHERE id=?", [$newStatus, $studentId]);
            logActivity('student_status_changed', "Eleve #$studentId -> $newStatus");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Statut mis a jour.');
        }
    }
}

// ── Filtres ────────────────────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$classFlt = (int)($_GET['class_id'] ?? 0);

$where  = ["s.academic_year_id = ?"];
$params = [$yearId];
if ($search !== '') {
    $where[]  = "(s.student_number LIKE ? OR COALESCE(s.first_name, u.first_name) LIKE ? OR COALESCE(s.last_name, u.last_name) LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($classFlt > 0) { $where[] = "s.class_id = ?"; $params[] = $classFlt; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$students = dbFetchAll(
    "SELECT s.*, u.email AS u_email, u.is_active AS u_active,
            COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
            c.name AS class_name,
            (SELECT status FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_status
     FROM students s
     LEFT JOIN users u   ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     $whereSql
     ORDER BY s.created_at DESC",
    $params
);

$classesList = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);

$invBadge = [
    'pending'   => ['warning', 'Invitation en attente'],
    'expired'   => ['danger',  'Invitation expiree'],
    'cancelled' => ['gray',    'Invitation annulee'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Eleves</h1>
        <p><?= count($students) ?> eleve(s) — annee <?= clean($currentYear['name'] ?? '—') ?></p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="addStudentModal">
          <i class="bx bx-user-plus"></i> Inscrire un eleve
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-graduation"></i> Liste des eleves</h3>
        <form method="GET" style="display:flex;gap:10px">
          <select name="class_id" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="0">Toutes les classes</option>
            <?php foreach ($classesList as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $classFlt === $c['id'] ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="search-wrap" style="max-width:220px">
            <i class="bx bx-search"></i>
            <input type="text" name="q" class="search-input" placeholder="Nom ou matricule..." value="<?= clean($search) ?>">
          </div>
        </form>
      </div>

      <?php if (empty($students)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-graduation"></i></div>
          <h3>Aucun eleve trouve</h3>
          <p>Commencez par inscrire votre premier eleve.</p>
          <button class="btn btn-primary btn-sm" data-modal="addStudentModal">
            <i class="bx bx-user-plus"></i> Inscrire un eleve
          </button>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Matricule</th><th>Classe</th><th>Compte</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($students as $s): ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--success)"><?= getInitials($s['fn'] ?: '?', $s['ln'] ?: '') ?></div>
                <div>
                  <div class="td-name"><?= clean(trim(($s['fn'] ?? '') . ' ' . ($s['ln'] ?? ''))) ?></div>
                  <?php if ($s['scholarship']): ?><div class="text-xs" style="color:var(--success)"><i class="bx bx-award"></i> Boursier</div><?php endif; ?>
                </div>
              </td>
              <td class="text-sm font-mono"><?= clean($s['student_number']) ?></td>
              <td class="text-sm"><?= clean($s['class_name'] ?: '—') ?></td>
              <td>
                <?php if (!$s['has_account']): ?>
                  <span class="badge badge-gray">Sans compte (primaire)</span>
                <?php elseif ($s['u_active']): ?>
                  <span class="badge badge-success">Actif</span>
                <?php else: ?>
                  <?php [$bc, $bl] = $invBadge[$s['inv_status']] ?? ['warning', 'Invitation en attente']; ?>
                  <span class="badge badge-<?= $bc ?>"><?= $bl ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-<?= $s['status'] === 'actif' ? 'success' : 'danger' ?>"><?= ucfirst($s['status']) ?></span>
              </td>
              <td class="td-actions">
                <?php if ($s['has_account'] && !$s['u_active']): ?>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend">
                    <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Renvoyer l'invitation">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <form method="POST" style="display:inline" data-confirm="Changer le statut de cet eleve ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-icon btn-sm"
                          title="<?= $s['status'] === 'actif' ? 'Suspendre' : 'Reactiver' ?>"
                          style="color:<?= $s['status'] === 'actif' ? 'var(--danger)' : 'var(--success)' ?>">
                    <i class="bx <?= $s['status'] === 'actif' ? 'bx-pause-circle' : 'bx-play-circle' ?>"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══ Modal : Inscrire un eleve ══ -->
<div class="modal-overlay" id="addStudentModal">
  <div class="modal modal-lg">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h3><i class="bx bx-user-plus"></i> Inscrire un eleve</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" required>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" class="form-control">
              <option value="M">Masculin</option>
              <option value="F">Feminin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date de naissance</label>
            <input type="date" name="date_of_birth" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Classe <span class="form-required">*</span></label>
          <select name="class_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($classesList as $c): ?>
              <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">A partir de la 7e annee, un compte personnel sera cree automatiquement (email requis).</span>
        </div>
        <div class="form-group">
          <label class="form-label">Email (si eleve du cycle terminal ou humanites)</label>
          <input type="email" name="email" class="form-control" placeholder="prenom.nom@email.com">
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Ecole precedente</label>
            <input type="text" name="previous_school" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-check" style="margin-top:32px">
              <input type="checkbox" name="scholarship" value="1">
              <span class="form-check-label">Beneficie d'une bourse</span>
            </label>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Notes medicales (optionnel)</label>
          <textarea name="medical_notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Inscrire l'eleve</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
