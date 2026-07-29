<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Enseignants';
$pageSection = 'teachers';
$admin       = currentUser();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Inviter un nouvel enseignant ──────────────────────────
    if ($action === 'invite') {
        $firstName   = sanitizeString($_POST['first_name'] ?? '');
        $lastName    = sanitizeString($_POST['last_name']  ?? '');
        $email       = strtolower(trim($_POST['email']     ?? ''));
        $phone       = sanitizeString($_POST['phone']      ?? '');
        $gender      = in_array($_POST['gender'] ?? '', ['M','F']) ? $_POST['gender'] : 'M';
        $qualif      = sanitizeString($_POST['qualification'] ?? '');
        $speciality  = sanitizeString($_POST['speciality']    ?? '');
        $subjectIds  = array_map('intval', $_POST['subjects'] ?? []);
        $classIds    = array_map('intval', $_POST['classes']  ?? []);

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Adresse email invalide.');
        } elseif (empty($subjectIds) || empty($classIds)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Selectionnez au moins une matiere et une classe.');
        } elseif (dbFetchOne("SELECT id FROM users WHERE email=?", [$email])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette adresse email est deja utilisee.');
        } else {
            try {
                getDB()->beginTransaction();

                $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
                dbExecute(
                    "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, gender, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
                    [ROLE_TEACHER, $username, $email, hashPassword(generateToken(24)), $firstName, $lastName, $phone, $gender]
                );
                $userId = dbLastId();

                dbExecute(
                    "INSERT INTO teachers (user_id, employee_id, hire_date, qualification, speciality, status)
                     VALUES (?, ?, CURDATE(), ?, ?, 'actif')",
                    [$userId, generateEmployeeId(), $qualif ?: null, $speciality ?: null]
                );
                $teacherId = dbLastId();

                foreach ($subjectIds as $sId) {
                    foreach ($classIds as $cId) {
                        dbExecute(
                            "INSERT INTO teacher_assignments (teacher_id, class_id, subject_id, academic_year_id, hours_per_week)
                             VALUES (?, ?, ?, ?, 2)",
                            [$teacherId, $cId, $sId, $yearId]
                        );
                    }
                }

                getDB()->commit();

                $token = createInvitation($userId, ROLE_TEACHER, $admin['id']);
                logActivity('invitation_created', "Profil enseignant cree : $firstName $lastName");
                $mailOk = sendInvitationEmail($email, $firstName, ROLE_LABELS[ROLE_TEACHER], $token);
                logActivity('invitation_sent', "Invitation envoyee a $email" . ($mailOk ? '' : ' (echec envoi email)'));

                redirectWith($_SERVER['PHP_SELF'], 'success',
                    "Enseignant cree. Invitation envoyee a $email." .
                    (APP_ENV === 'development' ? " Lien : " . BASE_URL . "/auth/activate-account.php?token=$token" : ''));
            } catch (Exception $e) {
                if (getDB()->inTransaction()) getDB()->rollBack();
                redirectWith($_SERVER['PHP_SELF'], 'danger', 'Erreur lors de la creation du profil enseignant.');
            }
        }
    }

    // ── Renvoyer une invitation ────────────────────────────────
    if ($action === 'resend') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $u = dbFetchOne("SELECT * FROM users WHERE id=? AND role_id=?", [$userId, ROLE_TEACHER]);
        if ($u && !$u['is_active']) {
            $token = createInvitation($userId, ROLE_TEACHER, $admin['id']);
            $mailOk = sendInvitationEmail($u['email'], $u['first_name'], ROLE_LABELS[ROLE_TEACHER], $token);
            logActivity('invitation_resent', "Invitation renvoyee a " . $u['email'] . ($mailOk ? '' : ' (echec envoi email)'));
            redirectWith($_SERVER['PHP_SELF'], 'success',
                "Invitation renvoyee." . (APP_ENV === 'development' ? " Lien : " . BASE_URL . "/auth/activate-account.php?token=$token" : ''));
        } else {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Impossible de renvoyer une invitation pour ce compte.');
        }
    }

    // ── Annuler une invitation ──────────────────────────────────
    if ($action === 'cancel') {
        $invId = (int)($_POST['invitation_id'] ?? 0);
        cancelInvitation($invId);
        logActivity('invitation_cancelled', "Invitation #$invId annulee");
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Invitation annulee.');
    }
}

// ── Donnees pour le formulaire ────────────────────────────────
$subjectsList = dbFetchAll("SELECT id, name, color FROM subjects WHERE is_active=1 ORDER BY name");
$classesList  = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);

// ── Liste des enseignants (evite le N+1 : tout en une requete) ─
$teachers = dbFetchAll(
    "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.avatar, u.created_at,
            t.id AS teacher_id, t.employee_id, t.hire_date, t.qualification, t.speciality,
            (SELECT status     FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_status,
            (SELECT id         FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_id,
            (SELECT expires_at FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_expires,
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects_list,
            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes_list
     FROM users u
     JOIN teachers t ON t.user_id = u.id
     LEFT JOIN teacher_assignments ta ON ta.teacher_id = t.id AND ta.academic_year_id = ?
     LEFT JOIN subjects s ON s.id = ta.subject_id
     LEFT JOIN classes  c ON c.id = ta.class_id
     WHERE u.role_id = ?
     GROUP BY u.id, t.id
     ORDER BY u.created_at DESC",
    [$yearId, ROLE_TEACHER]
);

$invBadge = [
    'pending'   => ['warning', 'Invitation en attente'],
    'expired'   => ['danger',  'Invitation expiree'],
    'cancelled' => ['gray',    'Invitation annulee'],
    'used'      => ['success', 'Actif'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Enseignants</h1>
        <p><?= count($teachers) ?> enseignant(s) — annee <?= clean($currentYear['name'] ?? '—') ?></p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="addTeacherModal">
          <i class="bx bx-user-plus"></i> Ajouter un enseignant
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-chalkboard"></i> Liste des enseignants</h3>
        <div class="search-wrap" style="max-width:260px">
          <i class="bx bx-search"></i>
          <input type="text" class="search-input" placeholder="Rechercher..." data-table-search="teachersTable">
        </div>
      </div>

      <?php if (empty($teachers)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-chalkboard"></i></div>
          <h3>Aucun enseignant</h3>
          <p>Commencez par inviter votre premier enseignant.</p>
          <button class="btn btn-primary btn-sm" data-modal="addTeacherModal">
            <i class="bx bx-user-plus"></i> Ajouter un enseignant
          </button>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table" id="teachersTable">
          <thead>
            <tr>
              <th>Enseignant</th><th>Matricule</th><th>Matiere(s)</th><th>Classe(s)</th><th>Statut</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teachers as $t):
              $status = $t['is_active'] ? 'used' : ($t['inv_status'] ?: 'pending');
              [$badgeColor, $badgeLabel] = $invBadge[$status] ?? ['gray', 'Inconnu'];
            ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--cyan)"><?= getInitials($t['first_name'], $t['last_name']) ?></div>
                <div>
                  <div class="td-name"><?= clean($t['first_name'] . ' ' . $t['last_name']) ?></div>
                  <div class="td-sub"><?= clean($t['email']) ?></div>
                </div>
              </td>
              <td class="text-sm"><?= clean($t['employee_id']) ?></td>
              <td class="text-sm"><?= clean($t['subjects_list'] ?: '—') ?></td>
              <td class="text-sm"><?= clean($t['classes_list'] ?: '—') ?></td>
              <td>
                <span class="badge badge-<?= $badgeColor ?>"><?= $badgeLabel ?></span>
                <?php if (!$t['is_active'] && $status === 'pending' && $t['inv_expires']): ?>
                  <div class="text-xs text-muted" style="margin-top:3px">Expire le <?= formatDateTime($t['inv_expires']) ?></div>
                <?php endif; ?>
              </td>
              <td class="td-actions">
                <?php if (!$t['is_active']): ?>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend">
                    <input type="hidden" name="user_id" value="<?= $t['user_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Renvoyer l'invitation">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </form>
                  <?php if ($t['inv_id'] && $status === 'pending'): ?>
                  <form method="POST" style="display:inline" data-confirm="Annuler cette invitation ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="invitation_id" value="<?= $t['inv_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Annuler l'invitation" style="color:var(--danger)">
                      <i class="bx bx-x-circle"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-xs text-muted">—</span>
                <?php endif; ?>
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

<!-- ══ Modal : Ajouter un enseignant ══ -->
<div class="modal-overlay" id="addTeacherModal">
  <div class="modal modal-lg">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="invite">
      <div class="modal-header">
        <h3><i class="bx bx-user-plus"></i> Inviter un enseignant</h3>
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
            <label class="form-label">Email professionnel <span class="form-required">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="jean.kabila@email.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" class="form-control" placeholder="+243 00 000 0000">
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
            <label class="form-label">Specialite</label>
            <input type="text" name="speciality" class="form-control" placeholder="Ex : Mathematiques appliquees">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Qualification</label>
          <input type="text" name="qualification" class="form-control" placeholder="Ex : Licence en pedagogie">
        </div>

        <div class="form-group">
          <label class="form-label">Matiere(s) enseignee(s) <span class="form-required">*</span></label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius);max-height:150px;overflow-y:auto">
            <?php foreach ($subjectsList as $s): ?>
              <label class="form-check" style="background:var(--bg-hover);padding:6px 12px;border-radius:var(--radius-full)">
                <input type="checkbox" name="subjects[]" value="<?= $s['id'] ?>">
                <span class="form-check-label"><?= clean($s['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Classe(s) attribuee(s) <span class="form-required">*</span></label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius);max-height:150px;overflow-y:auto">
            <?php foreach ($classesList as $c): ?>
              <label class="form-check" style="background:var(--bg-hover);padding:6px 12px;border-radius:var(--radius-full)">
                <input type="checkbox" name="classes[]" value="<?= $c['id'] ?>">
                <span class="form-check-label"><?= clean($c['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <span class="form-hint">L'enseignant sera affecte a chaque matiere selectionnee, dans chaque classe selectionnee.</span>
        </div>

        <div class="alert alert-info" style="margin-bottom:0">
          <i class="bx bx-info-circle"></i> Un email d'invitation sera envoye pour que l'enseignant active son compte et definisse lui-meme son mot de passe.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-send"></i> Creer et envoyer l'invitation</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
