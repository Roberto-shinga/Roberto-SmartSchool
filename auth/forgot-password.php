<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

if (isLoggedIn()) redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL);

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = dbFetchOne("SELECT * FROM users WHERE email=? AND is_active=1", [$email]);
        if ($user) {
            $token  = createPasswordReset($user['id']);
            $mailOk = sendPasswordResetEmail($user['email'], $user['first_name'], $token);
            logActivity('password_reset_requested', $user['username'] . ($mailOk ? '' : ' (echec envoi email)'));
        }
        // Meme reponse que l'email existe ou non : evite de reveler quels
        // comptes existent sur la plateforme (protection anti-enumeration).
    }
    $sent = true;
    if (isset($token) && APP_ENV === 'development' && isset($mailOk) && !$mailOk) {
        $_SESSION['dev_reset_link'] = BASE_URL . '/auth/reset-password.php?token=' . $token;
    }
}

$schoolName = getSetting('school_name', APP_NAME);
$devLink    = APP_ENV === 'development' ? ($_SESSION['dev_reset_link'] ?? null) : null;
unset($_SESSION['dev_reset_link']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Mot de passe oublie — <?= clean($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);}
    .fp-wrap{width:100%;max-width:440px;padding:20px;}
    .fp-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:36px;box-shadow:var(--shadow-lg);}
    .fp-icon{width:60px;height:60px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff;margin:0 auto 14px;box-shadow:var(--shadow-primary);}
    .btn-auth{width:100%;padding:13px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);margin-top:6px;}
    .btn-auth:hover{filter:brightness(1.07);}
    .dev-banner{background:var(--warning-bg);border:1px dashed var(--warning);border-radius:var(--radius);padding:12px 14px;margin-bottom:18px;font-size:12.5px;color:var(--warning-dark);word-break:break-all}
  </style>
</head>
<body>
<div class="fp-wrap">
  <div style="text-align:center;margin-bottom:22px">
    <div class="fp-icon"><i class="bx bx-key"></i></div>
    <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Mot de passe oublie</h1>
    <p style="font-size:13px;color:var(--text-muted)">Entre ton email pour recevoir un lien de reinitialisation</p>
  </div>

  <div class="fp-card">
    <?php if ($sent): ?>
      <?php if ($devLink): ?>
        <div class="dev-banner">
          <i class="bx bx-code-alt"></i> <strong>Mode developpement</strong> — l'email n'a pas pu etre envoye
          (SMTP non configure, ou cette adresse ne correspond a aucun compte actif). Si un compte existe, voici le
          lien directement : <a href="<?= clean($devLink) ?>"><?= clean($devLink) ?></a>
        </div>
      <?php endif; ?>
      <div class="alert alert-success">
        <i class="bx bx-check-circle"></i>
        <div>Si un compte actif existe avec cette adresse, un email de reinitialisation vient d'etre envoye.
        Verifie aussi tes spams.</div>
      </div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary btn-block"><i class="bx bx-arrow-back"></i> Retour a la connexion</a>
    <?php else: ?>
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Adresse email</label>
          <input type="email" name="email" class="form-control" placeholder="ton.email@exemple.com" required autofocus>
          <span class="form-hint">Fonctionne uniquement pour les comptes lies a une adresse email (les eleves du primaire/cycle utilisant un matricule doivent contacter l'administration).</span>
        </div>
        <button type="submit" class="btn-auth"><i class="bx bx-send"></i> Envoyer le lien</button>
      </form>
      <div style="text-align:center;margin-top:16px;padding-top:14px;border-top:1px solid var(--border-light)">
        <a href="<?= BASE_URL ?>/auth/login.php" style="font-size:13px;color:var(--text-muted)"><i class="bx bx-arrow-back"></i> Retour a la connexion</a>
      </div>
    <?php endif; ?>
  </div>
</div>
</body></html>
