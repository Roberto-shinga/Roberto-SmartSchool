<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Eleves';
$pageSection = 'students';
$admin       = currentUser();
$tempPwdShow = null;

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Inscrire un nouvel eleve ───────────────────────────────
    if ($action === 'create') {
        $firstName   = sanitizeString($_POST['first_name'] ?? '');
        $lastName    = sanitizeString($_POST['last_name']  ?? '');
        $postnom     = sanitizeString($_POST['postnom']    ?? '');
        $gender      = in_array($_POST['gender'] ?? '', ['M','F']) ? $_POST['gender'] : 'M';
        $dob         = $_POST['date_of_birth'] ?? null;
        $birthPlace  = sanitizeString($_POST['birth_place'] ?? '');
        $nationality = sanitizeString($_POST['nationality']  ?? '') ?: 'Congolaise (RDC)';
        $address     = sanitizeString($_POST['address']     ?? '');
        $classId     = (int)($_POST['class_id'] ?? 0);
        $prevSchool  = sanitizeString($_POST['previous_school'] ?? '');
        $medical     = sanitizeString($_POST['medical_notes'] ?? '');
        $scholarship = !empty($_POST['scholarship']) ? 1 : 0;

        $class = $classId ? dbFetchOne("SELECT * FROM classes WHERE id=?", [$classId]) : null;

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif (!$class) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Veuillez selectionner une classe.');
        } else {
            $needsAccount  = studentNeedsAccount((int)$class['level_id']);
            $studentNumber = generateStudentNumber();
            $userId  = null;
            $tempPwd = null;

            if ($needsAccount) {
                // Compte par MATRICULE : pas d'email requis. Le matricule
                // sert d'identifiant de connexion (onglet "Eleve" du login),
                // avec un mot de passe temporaire a changer a la 1ere connexion.
                $tempPwd  = generateTempPassword();
                $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
                dbExecute(
                    "INSERT INTO users (role_id, username, password, first_name, last_name, postnom, gender, date_of_birth,
                                         birth_place, nationality, address, is_active, must_change_password)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)",
                    [ROLE_STUDENT, $username, hashPassword($tempPwd), $firstName, $lastName, $postnom ?: null,
                     $gender, $dob ?: null, $birthPlace ?: null, $nationality, $address ?: null]
                );
                $userId = dbLastId();
            }

            dbExecute(
                "INSERT INTO students (user_id, class_id, academic_year_id, level_id, option_id, grade_year,
                                        student_number, enrollment_date, has_account, first_login, previous_school, medical_notes,
                                        scholarship, first_name, last_name, postnom, gender, date_of_birth,
                                        birth_place, nationality, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId, $classId, $yearId, $class['level_id'], $class['option_id'], $class['grade_year'],
                    $studentNumber, $needsAccount ? 1 : 0, $prevSchool ?: null, $medical ?: null,
                    $scholarship,
                    $needsAccount ? null : $firstName, $needsAccount ? null : $lastName, $needsAccount ? null : ($postnom ?: null),
                    $needsAccount ? null : $gender, $needsAccount ? null : ($dob ?: null),
                    $needsAccount ? null : ($birthPlace ?: null), $needsAccount ? null : $nationality, $needsAccount ? null : ($address ?: null),
                ]
            );

            logActivity('student_created', "Eleve inscrit : $firstName $lastName ($studentNumber)");

            if ($needsAccount) {
                $_SESSION['flash_temp_pwd'] = [
                    'name' => "$firstName $lastName", 'username' => $studentNumber, 'password' => $tempPwd,
                ];
                redirectWith($_SERVER['PHP_SELF'], 'success', "Eleve inscrit avec un compte (matricule $studentNumber).");
            } else {
                redirectWith($_SERVER['PHP_SELF'], 'success',
                    "Eleve inscrit ($studentNumber). Profil scolaire sans compte (primaire).");
            }
        }
    }

    // ── Reinitialiser le mot de passe (eleves avec compte) ─────
    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $u = dbFetchOne("SELECT * FROM users WHERE id=? AND role_id=?", [$userId, ROLE_STUDENT]);
        if ($u) {
            $tempPwd = generateTempPassword();
            dbExecute("UPDATE users SET password=?, must_change_password=1 WHERE id=?", [hashPassword($tempPwd), $userId]);
            dbExecute("UPDATE students SET first_login=1 WHERE id=?", [$studentId]);
            logActivity('student_password_reset', "Mot de passe reinitialise : " . $u['first_name'] . ' ' . $u['last_name']);
            $_SESSION['flash_temp_pwd'] = [
                'name' => $u['first_name'] . ' ' . $u['last_name'],
                'username' => dbFetchOne("SELECT student_number FROM students WHERE id=?", [$studentId])['student_number'] ?? $u['username'],
                'password' => $tempPwd,
            ];
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Mot de passe reinitialise.');
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

if (!empty($_SESSION['flash_temp_pwd'])) {
    $tempPwdShow = $_SESSION['flash_temp_pwd'];
    unset($_SESSION['flash_temp_pwd']);
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
    "SELECT s.*, u.is_active AS u_active, u.must_change_password AS u_must_change,
            COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
            COALESCE(s.postnom, u.postnom) AS pn,
            c.name AS class_name
     FROM students s
     LEFT JOIN users u   ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     $whereSql
     ORDER BY s.created_at DESC",
    $params
);

$classesList = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <?php if ($tempPwdShow): ?>
    <div class="alert alert-warning" style="align-items:flex-start">
      <i class="bx bx-key" style="margin-top:2px"></i>
      <div>
        <strong>Identifiants de connexion pour <?= clean($tempPwdShow['name']) ?></strong><br>
        Matricule : <code style="font-family:var(--font-mono);font-weight:700"><?= clean($tempPwdShow['username']) ?></code> —
        Mot de passe : <code style="font-family:var(--font-mono);font-weight:700"><?= clean($tempPwdShow['password']) ?></code><br>
        <span class="text-xs">A communiquer a l'eleve (ou son parent). Connexion via l'onglet "Eleve (matricule)" sur la page de connexion — un changement de mot de passe sera exige a la premiere connexion.</span>
      </div>
    </div>
    <?php endif; ?>

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
                  <div class="td-name"><?= clean(implode(' ', array_filter([$s['fn'] ?? '', $s['pn'] ?? '', $s['ln'] ?? '']))) ?></div>
                  <?php if ($s['scholarship']): ?><div class="text-xs" style="color:var(--success)"><i class="bx bx-award"></i> Boursier</div><?php endif; ?>
                </div>
              </td>
              <td class="text-sm font-mono"><?= clean($s['student_number']) ?></td>
              <td class="text-sm"><?= clean($s['class_name'] ?: '—') ?></td>
              <td>
                <?php if (!$s['has_account']): ?>
                  <span class="badge badge-gray">Sans compte (primaire)</span>
                <?php elseif ($s['u_must_change']): ?>
                  <span class="badge badge-warning">1ere connexion en attente</span>
                <?php elseif ($s['u_active']): ?>
                  <span class="badge badge-success">Actif</span>
                <?php else: ?>
                  <span class="badge badge-gray">Desactive</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-<?= $s['status'] === 'actif' ? 'success' : 'danger' ?>"><?= ucfirst($s['status']) ?></span>
              </td>
              <td class="td-actions">
                <?php if ($s['has_account']): ?>
                  <form method="POST" style="display:inline" data-confirm="Generer un nouveau mot de passe temporaire pour cet eleve ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                    <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Reinitialiser le mot de passe">
                      <i class="bx bx-key"></i>
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
        <div class="grid-3">
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Postnom</label>
            <input type="text" name="postnom" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" required>
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
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Classe <span class="form-required">*</span></label>
          <select name="class_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($classesList as $c): ?>
              <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">A partir de la 7e annee, un compte est cree automatiquement : un matricule et un
          mot de passe temporaire seront generes et affiches apres l'inscription (aucun email requis).</span>
        </div>
      </div>
      <div class="modal-body" style="padding-top:0">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Lieu de naissance</label>
            <input type="text" name="birth_place" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Nationalite</label>
            <input type="text" name="nationality" class="form-control" value="Congolaise (RDC)">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <input type="text" name="address" class="form-control">
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
