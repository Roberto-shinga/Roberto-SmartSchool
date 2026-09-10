<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(); // n'importe quel role connecte

$pageTitle   = 'Mon profil';
$pageSection = 'profile';
$user        = currentUser();
$roleId      = (int)$user['role_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Mettre a jour les informations personnelles ─────────────
    if ($action === 'update_info') {
        $firstName = sanitizeString($_POST['first_name'] ?? '');
        $lastName  = sanitizeString($_POST['last_name']  ?? '');
        $phone     = sanitizeString($_POST['phone']      ?? '');
        $email     = strtolower(trim($_POST['email']     ?? ''));

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Adresse email invalide.');
        } elseif ($email !== '' && dbFetchOne("SELECT id FROM users WHERE email=? AND id!=?", [$email, $user['id']])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette adresse email est deja utilisee par un autre compte.');
        } else {
            dbExecute("UPDATE users SET first_name=?, last_name=?, phone=?, email=? WHERE id=?",
                [$firstName, $lastName, $phone ?: null, $email ?: null, $user['id']]);
            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name']  = $lastName;
            logActivity('profile_updated', 'Informations personnelles mises a jour');
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Profil mis a jour.');
        }
    }

    // ── Changer la photo de profil ───────────────────────────────
    if ($action === 'update_avatar') {
        if (empty($_FILES['avatar']['name'])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Selectionne une image.');
        } else {
            $upload = uploadFile($_FILES['avatar'], 'avatars', ALLOWED_IMG);
            if (!$upload['success']) {
                redirectWith($_SERVER['PHP_SELF'], 'danger', $upload['error']);
            } else {
                dbExecute("UPDATE users SET avatar=? WHERE id=?", [$upload['filename'], $user['id']]);
                logActivity('profile_avatar_updated', 'Photo de profil mise a jour');
                redirectWith($_SERVER['PHP_SELF'], 'success', 'Photo de profil mise a jour.');
            }
        }
    }
}

// Recharge les donnees fraiches (apres une eventuelle mise a jour)
$user = dbFetchOne("SELECT * FROM users WHERE id=?", [$user['id']]);

// Infos complementaires selon le role (lecture seule)
$roleInfo = null;
if ($roleId === ROLE_TEACHER) {
    $roleInfo = dbFetchOne("SELECT * FROM teachers WHERE user_id=?", [$user['id']]);
} elseif ($roleId === ROLE_STUDENT) {
    $roleInfo = dbFetchOne("SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.user_id=?", [$user['id']]);
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mon profil</h1><p><?= clean(ROLE_LABELS[$roleId] ?? '') ?></p></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-user"></i> Informations personnelles</h3></div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_info">
            <div class="grid-2">
              <div class="form-group"><label class="form-label">Prenom <span class="form-required">*</span></label><input type="text" name="first_name" class="form-control" value="<?= clean($user['first_name']) ?>" required></div>
              <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="last_name" class="form-control" value="<?= clean($user['last_name']) ?>" required></div>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= clean($user['email'] ?? '') ?>" placeholder="<?= !empty($user['email']) ? '' : 'Aucun email (connexion par matricule)' ?>">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Telephone</label>
              <input type="text" name="phone" class="form-control" value="<?= clean($user['phone'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:18px"><i class="bx bx-save"></i> Enregistrer</button>
          </form>

          <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border-light)">
            <a href="<?= BASE_URL ?>/auth/change-password.php" class="btn btn-secondary"><i class="bx bx-lock-alt"></i> Changer mon mot de passe</a>
          </div>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
          <div class="card-header"><h3><i class="bx bx-image"></i> Photo de profil</h3></div>
          <div class="card-body" style="text-align:center">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= UPLOADS_URL ?>/avatars/<?= clean($user['avatar']) ?>" alt="Avatar" style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin-bottom:16px">
            <?php else: ?>
              <div class="avatar" style="width:96px;height:96px;font-size:28px;background:var(--primary);margin:0 auto 16px"><?= getInitials($user['first_name'], $user['last_name']) ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_avatar">
              <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp" style="margin-bottom:12px">
              <button type="submit" class="btn btn-secondary btn-sm" style="width:100%"><i class="bx bx-upload"></i> Changer la photo</button>
            </form>
          </div>
        </div>

        <?php if ($roleInfo): ?>
        <div class="card">
          <div class="card-header"><h3><i class="bx bx-id-card"></i> Informations <?= $roleId === ROLE_TEACHER ? 'professionnelles' : 'scolaires' ?></h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
            <?php if ($roleId === ROLE_TEACHER): ?>
              <div class="flex justify-between text-sm"><span class="text-muted">Matricule</span><span class="font-mono"><?= clean($roleInfo['employee_id']) ?></span></div>
              <div class="flex justify-between text-sm"><span class="text-muted">Qualification</span><span><?= clean($roleInfo['qualification'] ?: '—') ?></span></div>
              <div class="flex justify-between text-sm"><span class="text-muted">Specialite</span><span><?= clean($roleInfo['speciality'] ?: '—') ?></span></div>
              <div class="flex justify-between text-sm"><span class="text-muted">Date d'embauche</span><span><?= $roleInfo['hire_date'] ? formatDate($roleInfo['hire_date']) : '—' ?></span></div>
            <?php elseif ($roleId === ROLE_STUDENT): ?>
              <div class="flex justify-between text-sm"><span class="text-muted">Matricule</span><span class="font-mono"><?= clean($roleInfo['student_number']) ?></span></div>
              <div class="flex justify-between text-sm"><span class="text-muted">Classe</span><span><?= clean($roleInfo['class_name'] ?: '—') ?></span></div>
              <div class="flex justify-between text-sm"><span class="text-muted">Inscription</span><span><?= $roleInfo['enrollment_date'] ? formatDate($roleInfo['enrollment_date']) : '—' ?></span></div>
            <?php endif; ?>
            <p class="text-xs text-muted" style="margin-top:8px">Ces informations sont gerees par l'administration.</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>