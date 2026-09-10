<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

if (isLoggedIn()) redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';

if (empty($token)) {
    $invitation = null;
} else {
    $invitation = findInvitationByToken($token);
}

$schoolName = getSetting('school_name', APP_NAME);

// ── Traitement de l'activation ───────────────────────────────
if ($invitation && $invitation['status'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        try {
            getDB()->beginTransaction();

            dbExecute(
                "UPDATE users SET password=?, is_active=1, must_change_password=0 WHERE id=?",
                [hashPassword($password), $invitation['user_id']]
            );
            dbExecute(
                "UPDATE invitations SET status='used', used_at=NOW() WHERE id=?",
                [$invitation['id']]
            );

            getDB()->commit();

            $newUser = dbFetchOne(
                "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=?",
                [$invitation['user_id']]
            );
            logActivity('account_activated', $newUser['first_name'] . ' ' . $newUser['last_name'] . ' — ' . ROLE_LABELS[$newUser['role_id']]);

            loginUser($newUser);
            redirectWith(ROLE_REDIRECTS[$newUser['role_id']] ?? BASE_URL, 'success',
                'Compte active avec succes. Bienvenue ' . $newUser['first_name'] . ' !');
        } catch (Exception $e) {
            if (getDB()->inTransaction()) getDB()->rollBack();
            $error = 'Erreur lors de l\'activation du compte. Reessayez ou contactez l\'administration.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Activation de compte — <?= clean($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);}
    .act-wrap{width:100%;max-width:460px;padding:20px;}
    .act-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:36px;box-shadow:var(--shadow-lg);}
    .act-icon{width:60px;height:60px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff;margin:0 auto 14px;box-shadow:var(--shadow-primary);}
    .act-icon.err{background:var(--grad-danger);}
    .invite-box{background:var(--primary-bg);border:1px solid rgba(79,70,229,0.25);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;}
    .btn-auth{width:100%;padding:13px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);margin-top:6px;}
    .btn-auth:hover{filter:brightness(1.07);}
    .pwd-bars{display:flex;gap:4px;margin-top:6px;}
    .pwd-bar{flex:1;height:4px;border-radius:2px;background:var(--border);transition:background .3s;}
    @keyframes spin{to{transform:rotate(360deg);}}
  </style>
</head>
<body>
<div class="act-wrap">

  <?php if (!$invitation): ?>
    <!-- Token introuvable -->
    <div style="text-align:center;margin-bottom:22px">
      <div class="act-icon err"><i class="bx bx-link-alt"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Lien invalide</h1>
    </div>
    <div class="act-card">
      <div class="alert alert-danger"><i class="bx bx-x-circle"></i> Ce lien d'invitation est introuvable ou incorrect.</div>
      <p class="text-sm text-muted" style="margin-bottom:18px">Verifiez que vous avez copie le lien complet depuis votre email, ou demandez a l'administration de vous envoyer une nouvelle invitation.</p>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary btn-block"><i class="bx bx-arrow-back"></i> Retour a la connexion</a>
    </div>

  <?php elseif ($invitation['status'] === 'used'): ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="act-icon"><i class="bx bx-check-circle"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Compte deja active</h1>
    </div>
    <div class="act-card">
      <div class="alert alert-info"><i class="bx bx-info-circle"></i> Ce compte a deja ete active. Vous pouvez vous connecter normalement.</div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-block"><i class="bx bx-log-in"></i> Se connecter</a>
    </div>

  <?php elseif ($invitation['status'] === 'expired'): ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="act-icon err"><i class="bx bx-time-five"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Invitation expiree</h1>
    </div>
    <div class="act-card">
      <div class="alert alert-warning"><i class="bx bx-error"></i> Cette invitation a expire. Veuillez demander une nouvelle invitation a l'administrateur.</div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary btn-block"><i class="bx bx-arrow-back"></i> Retour a la connexion</a>
    </div>

  <?php elseif ($invitation['status'] === 'cancelled'): ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="act-icon err"><i class="bx bx-block"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Invitation annulee</h1>
    </div>
    <div class="act-card">
      <div class="alert alert-danger"><i class="bx bx-x-circle"></i> Cette invitation a ete annulee. Contactez l'administration si vous pensez qu'il s'agit d'une erreur.</div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary btn-block"><i class="bx bx-arrow-back"></i> Retour a la connexion</a>
    </div>

  <?php else: /* pending — formulaire d'activation */ ?>
    <div style="text-align:center;margin-bottom:22px">
      <div class="act-icon"><i class="bx bx-user-plus"></i></div>
      <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Activer mon compte</h1>
      <p style="font-size:13px;color:var(--text-muted)">Bienvenue sur <?= clean($schoolName) ?></p>
    </div>

    <div class="act-card">
      <div class="invite-box">
        <div class="avatar avatar-48" style="background:var(--primary)"><?= getInitials($invitation['first_name'], $invitation['last_name']) ?></div>
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--text-primary)"><?= clean($invitation['first_name'] . ' ' . $invitation['last_name']) ?></div>
          <div style="font-size:12.5px;color:var(--text-muted)">Role : <?= clean(ROLE_LABELS[$invitation['role_id']] ?? '') ?></div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bx bx-x-circle"></i> <?= clean($error) ?></div>
      <?php endif; ?>

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

        <button type="submit" class="btn-auth"><i class="bx bx-check"></i> Activer mon compte</button>
      </form>

      <p class="form-hint" style="text-align:center;margin-top:16px">
        Cette invitation expire le <?= formatDateTime($invitation['expires_at']) ?>.
      </p>
    </div>
  <?php endif; ?>

</div>

<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<?php if ($invitation && $invitation['status'] === 'pending'): ?>
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
