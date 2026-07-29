<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Administrateurs';
$pageSection = 'admins';
$superAdmin  = currentUser();
$tempPwdShow = null;

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Creer un Administrateur principal ─────────────────────
    if ($action === 'create') {
        $firstName = sanitizeString($_POST['first_name'] ?? '');
        $lastName  = sanitizeString($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email']     ?? ''));
        $phone     = sanitizeString($_POST['phone']      ?? '');

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Adresse email invalide.');
        } elseif (dbFetchOne("SELECT id FROM users WHERE email=?", [$email])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette adresse email est deja utilisee.');
        } else {
            $tempPwd  = generateTempPassword();
            $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
            dbExecute(
                "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, is_active, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)",
                [ROLE_ADMIN, $username, $email, hashPassword($tempPwd), $firstName, $lastName, $phone]
            );
            logActivity('admin_created', "Administrateur cree : $firstName $lastName ($email)");

            $_SESSION['flash_temp_pwd'] = [
                'name' => "$firstName $lastName", 'username' => $username, 'password' => $tempPwd,
            ];
            redirectWith($_SERVER['PHP_SELF'], 'success', "Administrateur cree avec succes.");
        }
    }

    // ── Activer / desactiver ──────────────────────────────────
    if ($action === 'toggle_active') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $target = dbFetchOne("SELECT * FROM users WHERE id=? AND role_id=?", [$userId, ROLE_ADMIN]);
        if ($target) {
            $newState = $target['is_active'] ? 0 : 1;
            dbExecute("UPDATE users SET is_active=? WHERE id=?", [$newState, $userId]);
            logActivity($newState ? 'admin_activated' : 'admin_deactivated',
                $target['first_name'] . ' ' . $target['last_name']);
            redirectWith($_SERVER['PHP_SELF'], 'success', $newState ? 'Compte reactive.' : 'Compte desactive.');
        }
    }

    // ── Reinitialiser le mot de passe ─────────────────────────
    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $target = dbFetchOne("SELECT * FROM users WHERE id=? AND role_id=?", [$userId, ROLE_ADMIN]);
        if ($target) {
            $tempPwd = generateTempPassword();
            dbExecute("UPDATE users SET password=?, must_change_password=1 WHERE id=?", [hashPassword($tempPwd), $userId]);
            logActivity('admin_password_reset', $target['first_name'] . ' ' . $target['last_name']);
            $_SESSION['flash_temp_pwd'] = [
                'name' => $target['first_name'] . ' ' . $target['last_name'],
                'username' => $target['username'], 'password' => $tempPwd,
            ];
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Mot de passe reinitialise.');
        }
    }
}

if (!empty($_SESSION['flash_temp_pwd'])) {
    $tempPwdShow = $_SESSION['flash_temp_pwd'];
    unset($_SESSION['flash_temp_pwd']);
}

$admins = dbFetchAll(
    "SELECT * FROM users WHERE role_id = ? ORDER BY created_at DESC",
    [ROLE_ADMIN]
);

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
        <strong>Mot de passe temporaire pour <?= clean($tempPwdShow['name']) ?></strong><br>
        Identifiant : <code style="font-family:var(--font-mono)"><?= clean($tempPwdShow['username']) ?></code> —
        Mot de passe : <code style="font-family:var(--font-mono);font-weight:700"><?= clean($tempPwdShow['password']) ?></code><br>
        <span class="text-xs">A communiquer de maniere securisee. Un changement de mot de passe sera exige a la premiere connexion.</span>
      </div>
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h1>Administrateurs</h1>
        <p>Comptes ayant la gestion quotidienne de l'etablissement</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="addAdminModal">
          <i class="bx bx-user-plus"></i> Ajouter un administrateur
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="bx bx-user-check"></i> Liste des administrateurs</h3></div>

      <?php if (empty($admins)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-user-check"></i></div>
          <h3>Aucun administrateur</h3>
          <p>Creez le compte de l'Administrateur principal pour lui transferer la gestion quotidienne.</p>
          <button class="btn btn-primary btn-sm" data-modal="addAdminModal">
            <i class="bx bx-user-plus"></i> Ajouter un administrateur
          </button>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead>
            <tr><th>Administrateur</th><th>Identifiant</th><th>Derniere connexion</th><th>Statut</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--primary)"><?= getInitials($a['first_name'], $a['last_name']) ?></div>
                <div>
                  <div class="td-name"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                  <div class="td-sub"><?= clean($a['email']) ?></div>
                </div>
              </td>
              <td class="text-sm"><?= clean($a['username']) ?></td>
              <td class="text-sm text-muted"><?= $a['last_login'] ? timeAgo($a['last_login']) : 'Jamais connecte' ?></td>
              <td>
                <?php if ($a['is_active']): ?>
                  <span class="badge badge-success">Actif</span>
                <?php else: ?>
                  <span class="badge badge-gray">Desactive</span>
                <?php endif; ?>
                <?php if ($a['must_change_password']): ?>
                  <div class="text-xs text-muted" style="margin-top:3px"><i class="bx bx-time-five"></i> Changement mdp requis</div>
                <?php endif; ?>
              </td>
              <td class="td-actions">
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-icon btn-sm"
                          title="<?= $a['is_active'] ? 'Desactiver' : 'Reactiver' ?>"
                          style="color:<?= $a['is_active'] ? 'var(--danger)' : 'var(--success)' ?>">
                    <i class="bx <?= $a['is_active'] ? 'bx-block' : 'bx-check-circle' ?>"></i>
                  </button>
                </form>
                <form method="POST" style="display:inline" data-confirm="Generer un nouveau mot de passe temporaire pour ce compte ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Reinitialiser le mot de passe">
                    <i class="bx bx-key"></i>
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

<!-- ══ Modal : Ajouter un administrateur ══ -->
<div class="modal-overlay" id="addAdminModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h3><i class="bx bx-user-plus"></i> Nouvel administrateur</h3>
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
        <div class="form-group">
          <label class="form-label">Email professionnel <span class="form-required">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="admin.jmartin@ecole.cd" required>
        </div>
        <div class="form-group">
          <label class="form-label">Telephone</label>
          <input type="text" name="phone" class="form-control" placeholder="+243 00 000 0000">
        </div>
        <div class="alert alert-info" style="margin-bottom:0">
          <i class="bx bx-info-circle"></i> Un mot de passe temporaire sera genere automatiquement. L'administrateur devra
          le changer obligatoirement a sa premiere connexion.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Creer le compte</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
