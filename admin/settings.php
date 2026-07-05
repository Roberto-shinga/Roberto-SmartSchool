<?php
// ============================================================
//  SmartSchool — Parametres
//  Emplacement : admin/settings.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Parametres';
$pageSection = 'settings';
$user        = currentUser();
$activeTab   = trim($_GET['tab'] ?? 'general');

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Parametres generaux ──
    if ($action === 'save_general') {
        $settings = [
            'school_name'    => trim($_POST['school_name']    ?? ''),
            'school_address' => trim($_POST['school_address'] ?? ''),
            'school_phone'   => trim($_POST['school_phone']   ?? ''),
            'school_email'   => trim($_POST['school_email']   ?? ''),
            'currency'       => trim($_POST['currency']       ?? 'FCFA'),
        ];

        foreach ($settings as $key => $value) {
            $exists = dbFetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                dbExecute("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
            } else {
                dbExecute("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            }
        }
        logActivity('update_settings', 'Parametres generaux modifies');
        redirectWith(BASE_URL . '/admin/settings.php?tab=general', 'success', 'Parametres mis a jour avec succes !');
    }

    // ── Profil utilisateur ──
    if ($action === 'save_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '');

        dbExecute(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?",
            [$firstName, $lastName, $email, $phone, $user['id']]
        );

        // Mettre a jour la session
        $_SESSION['user']['first_name'] = $firstName;
        $_SESSION['user']['last_name']  = $lastName;
        $_SESSION['user']['email']      = $email;
        $_SESSION['user']['phone']      = $phone;

        logActivity('update_profile', 'Profil modifie');
        redirectWith(BASE_URL . '/admin/settings.php?tab=profile', 'success', 'Profil mis a jour !');
    }

    // ── Changer mot de passe ──
    if ($action === 'change_password') {
        $current = trim($_POST['current_password'] ?? '');
        $new     = trim($_POST['new_password']     ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        $dbUser = dbFetchOne("SELECT password FROM users WHERE id = ?", [$user['id']]);

        if (!verifyPassword($current, $dbUser['password'])) {
            redirectWith(BASE_URL . '/admin/settings.php?tab=security', 'danger', 'Mot de passe actuel incorrect.');
        }
        if (strlen($new) < 6) {
            redirectWith(BASE_URL . '/admin/settings.php?tab=security', 'danger', 'Le nouveau mot de passe doit faire au moins 6 caracteres.');
        }
        if ($new !== $confirm) {
            redirectWith(BASE_URL . '/admin/settings.php?tab=security', 'danger', 'Les mots de passe ne correspondent pas.');
        }

        dbExecute("UPDATE users SET password = ? WHERE id = ?", [hashPassword($new), $user['id']]);
        logActivity('change_password', 'Mot de passe modifie');
        redirectWith(BASE_URL . '/admin/settings.php?tab=security', 'success', 'Mot de passe modifie avec succes !');
    }
}

// Recuperer tous les parametres
$allSettings = dbFetchAll("SELECT setting_key, setting_value FROM system_settings");
$settingsMap = [];
foreach ($allSettings as $s) {
    $settingsMap[$s['setting_key']] = $s['setting_value'];
}

// Statistiques systeme
$totalUsers = dbFetchOne("SELECT COUNT(*) c FROM users")['c'] ?? 0;
$dbSize     = dbFetchOne(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
     FROM information_schema.TABLES WHERE table_schema = ?",
    [DB_NAME]
)['size_mb'] ?? 0;

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-cog" style="color:var(--primary)"></i>
      Parametres
    </h1>
    <p>Gerez les parametres de l'application et de votre compte</p>
  </div>
</div>

<!-- ONGLETS -->
<div class="tabs">
  <button class="tab-btn <?= $activeTab === 'general'  ? 'active' : '' ?>" onclick="location.href='?tab=general'">
    <i class="bx bx-buildings"></i> General
  </button>
  <button class="tab-btn <?= $activeTab === 'profile'   ? 'active' : '' ?>" onclick="location.href='?tab=profile'">
    <i class="bx bx-user-circle"></i> Mon profil
  </button>
  <button class="tab-btn <?= $activeTab === 'security'  ? 'active' : '' ?>" onclick="location.href='?tab=security'">
    <i class="bx bx-lock-alt"></i> Securite
  </button>
  <button class="tab-btn <?= $activeTab === 'appearance'? 'active' : '' ?>" onclick="location.href='?tab=appearance'">
    <i class="bx bx-palette"></i> Apparence
  </button>
  <button class="tab-btn <?= $activeTab === 'system'    ? 'active' : '' ?>" onclick="location.href='?tab=system'">
    <i class="bx bx-server"></i> Systeme
  </button>
</div>

<!-- ══ ONGLET GENERAL ══ -->
<?php if ($activeTab === 'general'): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-buildings"></i> Informations de l'ecole</h3>
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_general">
    <div class="card-body">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Nom de l'ecole</label>
          <input type="text" name="school_name" class="form-control"
                 value="<?= clean($settingsMap['school_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Devise</label>
          <select name="currency" class="form-control">
            <?php foreach (['FCFA','EUR','USD','MAD','XOF'] as $cur): ?>
              <option value="<?= $cur ?>" <?= ($settingsMap['currency'] ?? '') === $cur ? 'selected' : '' ?>>
                <?= $cur ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Telephone</label>
          <input type="text" name="school_phone" class="form-control"
                 value="<?= clean($settingsMap['school_phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="school_email" class="form-control"
                 value="<?= clean($settingsMap['school_email'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Adresse</label>
        <textarea name="school_address" class="form-control" rows="2"><?= clean($settingsMap['school_address'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="modal-footer" style="justify-content:flex-start">
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-save"></i> Enregistrer les modifications
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ══ ONGLET PROFIL ══ -->
<?php if ($activeTab === 'profile'): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-user-circle"></i> Mon profil</h3>
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_profile">
    <div class="card-body">
      <!-- Avatar -->
      <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid var(--border-light)">
        <div class="avatar avatar-64" style="background:<?= ROLE_COLORS[currentRole()] ?? '#6366f1' ?>">
          <?= getInitials($user['first_name'], $user['last_name']) ?>
        </div>
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--text-primary)">
            <?= clean($user['first_name']) ?> <?= clean($user['last_name']) ?>
          </div>
          <div style="font-size:13px;color:var(--text-muted)">
            <?= ROLE_LABELS[currentRole()] ?? '' ?>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Prenom</label>
          <input type="text" name="first_name" class="form-control"
                 value="<?= clean($user['first_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nom</label>
          <input type="text" name="last_name" class="form-control"
                 value="<?= clean($user['last_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= clean($user['email']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Telephone</label>
          <input type="text" name="phone" class="form-control"
                 value="<?= clean($user['phone'] ?? '') ?>">
        </div>
      </div>
    </div>
    <div class="modal-footer" style="justify-content:flex-start">
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-save"></i> Mettre a jour le profil
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ══ ONGLET SECURITE ══ -->
<?php if ($activeTab === 'security'): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-lock-alt"></i> Changer le mot de passe</h3>
  </div>
  <form method="POST" id="pwdForm">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Mot de passe actuel <span class="form-required">*</span></label>
        <div class="input-wrap">
          <i class="bx bx-lock-alt input-icon"></i>
          <input type="password" name="current_password" class="form-control" required>
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Nouveau mot de passe <span class="form-required">*</span></label>
          <div class="input-wrap">
            <i class="bx bx-key input-icon"></i>
            <input type="password" name="new_password" id="newPwd" class="form-control" required minlength="6">
          </div>
          <span class="form-hint">Minimum 6 caracteres</span>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmer <span class="form-required">*</span></label>
          <div class="input-wrap">
            <i class="bx bx-key input-icon"></i>
            <input type="password" name="confirm_password" id="confirmPwd" class="form-control" required>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer" style="justify-content:flex-start">
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-lock-alt"></i> Changer le mot de passe
      </button>
    </div>
  </form>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-header">
    <h3><i class="bx bx-info-circle"></i> Informations de connexion</h3>
  </div>
  <div class="card-body">
    <div style="display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;justify-content:space-between;padding:12px;background:var(--bg-body);border-radius:var(--radius)">
        <span style="color:var(--text-muted);font-size:13px">Nom d'utilisateur</span>
        <span style="font-weight:600;font-size:13px"><?= clean($user['username']) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:12px;background:var(--bg-body);border-radius:var(--radius)">
        <span style="color:var(--text-muted);font-size:13px">Derniere connexion</span>
        <span style="font-weight:600;font-size:13px"><?= formatDateTime($user['last_login'] ?? '') ?></span>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ ONGLET APPARENCE ══ -->
<?php if ($activeTab === 'appearance'): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-palette"></i> Apparence</h3>
  </div>
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:var(--bg-body);border-radius:var(--radius)">
      <div style="display:flex;align-items:center;gap:14px">
        <div style="width:42px;height:42px;border-radius:var(--radius);background:var(--grad-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem">
          <i class="bx bx-moon"></i>
        </div>
        <div>
          <div style="font-weight:600;font-size:14px;color:var(--text-primary)">Mode sombre</div>
          <div style="font-size:12.5px;color:var(--text-muted)">Basculer entre theme clair et sombre</div>
        </div>
      </div>
      <div class="toggle-wrap">
        <input type="checkbox" class="toggle" id="appearanceDark" <?= isDarkMode() ? 'checked' : '' ?>>
        <label for="appearanceDark" class="toggle-slider"></label>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ ONGLET SYSTEME ══ -->
<?php if ($activeTab === 'system'): ?>
<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-server"></i> Informations systeme</h3>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php
        $infos = [
          ['label'=>'Version SmartSchool', 'val'=>APP_VERSION],
          ['label'=>'Version PHP',         'val'=>PHP_VERSION],
          ['label'=>'Base de donnees',     'val'=>DB_NAME],
          ['label'=>'Taille base donnees', 'val'=>$dbSize . ' MB'],
          ['label'=>'Utilisateurs totaux', 'val'=>$totalUsers],
        ];
        foreach ($infos as $info): ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-light)">
          <span style="font-size:13px;color:var(--text-muted)"><?= $info['label'] ?></span>
          <span style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= $info['val'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-shield-quarter"></i> Roles du systeme</h3>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach (ROLE_LABELS as $rid => $label): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg-body);border-radius:var(--radius)">
          <div style="width:32px;height:32px;border-radius:var(--radius-sm);background:<?= ROLE_COLORS[$rid] ?>20;color:<?= ROLE_COLORS[$rid] ?>;display:flex;align-items:center;justify-content:center">
            <i class="bx <?= ROLE_ICONS[$rid] ?>"></i>
          </div>
          <span style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

</div>
</div>
</div>

<?php
$pageScript = "
// Validation correspondance mots de passe
const pwdForm = document.getElementById('pwdForm');
if (pwdForm) {
  pwdForm.addEventListener('submit', function(e) {
    const newPwd = document.getElementById('newPwd').value;
    const confirmPwd = document.getElementById('confirmPwd').value;
    if (newPwd !== confirmPwd) {
      e.preventDefault();
      SS_Toast.error('Erreur', 'Les mots de passe ne correspondent pas.');
    }
  });
}

// Toggle dark mode depuis parametres
const appearanceToggle = document.getElementById('appearanceDark');
if (appearanceToggle) {
  appearanceToggle.addEventListener('change', function() {
    document.getElementById('darkModeBtn')?.click();
  });
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
