<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

if (isLoggedIn()) redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$reset = $token ? findPasswordResetByToken($token) : null;

if ($reset && $reset['status'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        dbExecute("UPDATE users SET password=?, must_change_password=0 WHERE id=?", [hashPassword($password), $reset['user_id']]);
        dbExecute("UPDATE password_resets SET status='used', used_at=NOW() WHERE id=?", [$reset['id']]);
        logActivity('password_reset_completed', $reset['username']);
        redirectWith(BASE_URL . '/auth/login.php', 'success', 'Mot de passe reinitialise avec succes. Tu peux te connecter.');
    }
}

$schoolName = getSetting('school_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Nouveau mot de passe — <?= clean($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);}
    .rp-wrap{width:100%;max-width:440px;padding:20px;}
    .rp-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:36px;box-shadow:var(--shadow-lg);}
    .rp-icon{width:60px;height:60px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff;margin:0 auto 14px;box-shadow:var(--shadow-primary);}
    .rp-icon.err{background:var(--grad-danger);}
    .btn-auth{width:100%;padding:13px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);margin-top:6px;}
    .btn-auth:hover{filter:brightness(1.07);}
    .pwd-bars{display:flex;gap:4px;margin-top:6px;}
    .pwd-bar{flex:1;height:4px;border-radius:2px;background:var(--border);transition:background .3s;}
  </style>
</head>
<body>
<div class="rp-wrap">

  <?php if (!$reset): ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="rp-icon err"><i class="bx bx-link-alt"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Lien invalide</h1>
    </div>
    <div class="rp-card">
      <div class="alert alert-danger"><i class="bx bx-x-circle"></i> Ce lien de reinitialisation est introuvable ou incorrect.</div>
      <a href="<?= BASE_URL ?>/auth/forgot-password.php" class="btn btn-secondary btn-block"><i class="bx bx-refresh"></i> Demander un nouveau lien</a>
    </div>

  <?php elseif ($reset['status'] === 'used'): ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="rp-icon"><i class="bx bx-check-circle"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Lien deja utilise</h1>
    </div>
    <div class="rp-card">
      <div class="alert alert-info"><i class="bx bx-info-circle"></i> Ce lien a deja servi a definir un mot de passe. Connecte-toi normalement.</div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-block"><i class="bx bx-log-in"></i> Se connecter</a>
    </div>

  <?php elseif ($reset['status'] === 'expired'): ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="rp-icon err"><i class="bx bx-time-five"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Lien expire</h1>
    </div>
    <div class="rp-card">
      <div class="alert alert-warning"><i class="bx bx-error"></i> Ce lien a expire (valable 1 heure). Demande-en un nouveau.</div>
      <a href="<?= BASE_URL ?>/auth/forgot-password.php" class="btn btn-secondary btn-block"><i class="bx bx-refresh"></i> Demander un nouveau lien</a>
    </div>

  <?php else: /* pending — formulaire de reinitialisation */ ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="rp-icon"><i class="bx bx-key"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Nouveau mot de passe</h1>
      <p style="font-size:13px;color:var(--text-muted)">Pour <?= clean($reset['first_name'] . ' ' . $reset['last_name']) ?></p>
    </div>

    <div class="rp-card">
      <?php if ($error): ?><div class="alert alert-danger"><i class="bx bx-x-circle"></i> <?= clean($error) ?></div><?php endif; ?>

      <form method="POST" data-loading>
        <input type="hidden" name="token" value="<?= clean($token) ?>">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label">Nouveau mot de passe <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-lock-alt input-icon"></i>
            <input type="password" name="password" id="npwd" class="form-control pr" placeholder="Min. 8 caracteres" required minlength="8">
            <span class="input-icon-right" data-toggle-pwd="npwd" style="cursor:pointer"><i class="bx bx-show"></i></span>
          </div>
          <div class="pwd-bars"><div class="pwd-bar" id="b1"></div><div class="pwd-bar" id="b2"></div><div class="pwd-bar" id="b3"></div><div class="pwd-bar" id="b4"></div></div>
          <span class="form-hint">Au moins 8 caracteres, une lettre et un chiffre.</span>
        </div>

        <div class="form-group">
          <label class="form-label">Confirmer le mot de passe <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-lock input-icon"></i>
            <input type="password" name="confirm" id="cfm" class="form-control pr" placeholder="Repetez le mot de passe" required>
            <span class="input-icon-right" data-toggle-pwd="cfm" style="cursor:pointer"><i class="bx bx-show"></i></span>
          </div>
        </div>

        <button type="submit" class="btn-auth"><i class="bx bx-check"></i> Definir le nouveau mot de passe</button>
      </form>
    </div>
  <?php endif; ?>

</div>

<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<?php if ($reset && $reset['status'] === 'pending'): ?>
<script>
const np = document.getElementById('npwd');
np?.addEventListener('input', function() {
  const v = this.value; let s = 0;
  if (v.length >= 8) s++; if (v.length >= 12) s++;
  if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
  if (/[0-9]/.test(v) && /\W/.test(v)) s++;
  const c = ['#ef4444','#f59e0b','#3b82f6','#10b981'];
  for (let i = 1; i <= 4; i++) { document.getElementById('b'+i).style.background = i <= s ? c[s-1] : 'var(--border)'; }
});
</script>
<?php endif; ?>
</body></html>
