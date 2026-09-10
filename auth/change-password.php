<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole();

$user    = currentUser();
$forced  = !empty($_SESSION['force_pwd_change']);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password']     ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (!$forced && empty($current)) {
        $error = 'Veuillez saisir votre mot de passe actuel.';
    } elseif (strlen($new) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caracteres.';
    } elseif ($new !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $dbUser = dbFetchOne("SELECT password FROM users WHERE id=?", [$user['id']]);
        if (!$forced && !verifyPassword($current, $dbUser['password'])) {
            $error = 'Mot de passe actuel incorrect.';
        } else {
            dbExecute("UPDATE users SET password=?, must_change_password=0 WHERE id=?",
                [hashPassword($new), $user['id']]);
            unset($_SESSION['force_pwd_change']);
            logActivity('change_password', 'Changement de mot de passe');
            $dest = ROLE_REDIRECTS[$user['role_id']] ?? BASE_URL;
            redirectWith($dest, 'success', 'Mot de passe modifie avec succes !');
        }
    }
}

$schoolName = getSetting('school_name', APP_NAME);
$flash      = getFlash();
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= $forced ? 'Définir mon mot de passe' : 'Changer le mot de passe' ?> — <?= clean($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);}
    .pwd-wrap{width:100%;max-width:460px;padding:20px;}
    .pwd-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:36px;box-shadow:var(--shadow-lg);}
    .btn-auth{width:100%;padding:13px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);margin-top:8px;}
    .btn-auth:hover{filter:brightness(1.07);}
    .pwd-bars{display:flex;gap:4px;margin-top:6px;}
    .pwd-bar{flex:1;height:4px;border-radius:2px;background:var(--border);transition:background .3s;}
    @keyframes spin{to{transform:rotate(360deg);}}
  </style>
</head>
<body>
<div class="pwd-wrap">
  <?php if ($forced): ?>
  <div style="text-align:center;margin-bottom:24px">
    <div style="width:60px;height:60px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.7rem;margin:0 auto 12px;box-shadow:var(--shadow-primary)">🔐</div>
    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Definir mon mot de passe</h1>
    <p style="font-size:13px;color:var(--text-muted)">Premiere connexion — choisissez un mot de passe personnel et securise</p>
  </div>
  <?php else: ?>
  <div style="text-align:center;margin-bottom:24px">
    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Changer le mot de passe</h1>
    <p style="font-size:13px;color:var(--text-muted)">Bonjour <?= clean($user['first_name']) ?></p>
  </div>
  <?php endif; ?>

  <div class="pwd-card">
    <?php if ($flash): ?><div class="alert alert-<?= clean($flash['type']) ?>"><i class="bx bx-info-circle"></i> <?= clean($flash['message']) ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-danger"><i class="bx bx-x-circle"></i> <?= clean($error) ?></div><?php endif; ?>

    <form method="POST" data-loading>
      <?= csrfField() ?>

      <?php if (!$forced): ?>
      <div class="form-group">
        <label class="form-label">Mot de passe actuel <span class="form-required">*</span></label>
        <div class="input-wrap"><i class="bx bx-lock-alt input-icon"></i>
          <input type="password" name="current_password" id="p0" class="form-control pr" placeholder="Mot de passe actuel" required>
          <span class="input-icon-right" data-toggle-pwd="p0" style="cursor:pointer"><i class="bx bx-show"></i></span>
        </div>
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Nouveau mot de passe <span class="form-required">*</span></label>
        <div class="input-wrap"><i class="bx bx-lock input-icon"></i>
          <input type="password" name="new_password" id="newPwd" class="form-control pr" placeholder="Min. 6 caracteres" required minlength="6">
          <span class="input-icon-right" data-toggle-pwd="newPwd" style="cursor:pointer"><i class="bx bx-show"></i></span>
        </div>
        <div class="pwd-bars"><div class="pwd-bar" id="b1"></div><div class="pwd-bar" id="b2"></div><div class="pwd-bar" id="b3"></div><div class="pwd-bar" id="b4"></div></div>
        <span id="pwdLbl" class="form-hint"></span>
      </div>

      <div class="form-group">
        <label class="form-label">Confirmer le mot de passe <span class="form-required">*</span></label>
        <div class="input-wrap"><i class="bx bx-lock input-icon"></i>
          <input type="password" name="confirm_password" id="cfm" class="form-control pr" placeholder="Repetez le mot de passe" required>
          <span class="input-icon-right" data-toggle-pwd="cfm" style="cursor:pointer"><i class="bx bx-show"></i></span>
        </div>
        <span id="matchMsg" class="form-hint"></span>
      </div>

      <button type="submit" class="btn-auth"><i class="bx bx-save"></i>
        <?= $forced ? 'Enregistrer mon mot de passe' : 'Modifier le mot de passe' ?>
      </button>
    </form>

    <?php if (!$forced): ?>
    <div style="text-align:center;margin-top:16px">
      <a href="<?= ROLE_REDIRECTS[$user['role_id']] ?? BASE_URL ?>" style="font-size:13px;color:var(--text-muted)">
        <i class="bx bx-arrow-back"></i> Retour
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<script>
const newPwd = document.getElementById('newPwd');
const cfm    = document.getElementById('cfm');
newPwd?.addEventListener('input', function() {
  const v=this.value; let s=0;
  if(v.length>=6)s++; if(v.length>=10)s++;
  if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;
  if(/[0-9]/.test(v)&&/[^a-zA-Z0-9]/.test(v))s++;
  const c=['#ef4444','#f59e0b','#3b82f6','#10b981'];
  const l=['Faible','Moyen','Bon','Excellent'];
  for(let i=1;i<=4;i++){document.getElementById('b'+i).style.background=i<=s?c[s-1]:'var(--border)';}
  const lbl=document.getElementById('pwdLbl');
  lbl.textContent=s>0?l[s-1]:''; lbl.style.color=s>0?c[s-1]:'';
});
cfm?.addEventListener('input', function() {
  const m=document.getElementById('matchMsg');
  if(!this.value){m.textContent='';return;}
  const ok=this.value===newPwd.value;
  m.textContent=ok?'✓ Les mots de passe correspondent':'✗ Les mots de passe ne correspondent pas';
  m.style.color=ok?'var(--success)':'var(--danger)';
});
</script>
</body></html>
