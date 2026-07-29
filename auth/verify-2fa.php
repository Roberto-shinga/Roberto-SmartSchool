<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

// Cette page n'existe que dans le cadre d'une connexion Super Admin
// en attente de verification. Pas de session pending -> retour au login.
if (empty($_SESSION['pending_2fa']['user_id'])) {
    redirectWith(BASE_URL . '/auth/login.php', 'warning', 'Veuillez vous reconnecter.');
}

$pending  = $_SESSION['pending_2fa'];
$userId   = (int)$pending['user_id'];
$error    = '';
$resent   = false;

$superAdmin = dbFetchOne(
    "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?",
    [$userId]
);

// Si l'utilisateur a disparu ou n'est plus super admin entre temps
if (!$superAdmin || (int)$superAdmin['role_id'] !== ROLE_SUPER_ADMIN) {
    unset($_SESSION['pending_2fa'], $_SESSION['dev_2fa_code']);
    redirectWith(BASE_URL . '/auth/login.php', 'danger', 'Session de verification invalide.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Renvoyer un nouveau code ──────────────────────────────
    if (isset($_POST['resend'])) {
        $lastSent = $pending['last_sent'] ?? 0;
        if (time() - $lastSent < SUPERADMIN_2FA_RESEND_DELAY) {
            $error = 'Veuillez patienter avant de demander un nouveau code.';
        } else {
            $code = generate2FACode($userId, 'login');
            send2FACodeEmail($superAdmin['email'], $superAdmin['first_name'], $code);
            $_SESSION['pending_2fa']['last_sent'] = time();
            if (APP_ENV === 'development') $_SESSION['dev_2fa_code'] = $code;
            logActivity('superadmin_2fa_sent', 'Code renvoye pour : ' . $superAdmin['username']);
            $resent = true;
        }
    } else {
        // ── Verifier le code saisi ────────────────────────────
        $codeInput = trim($_POST['code'] ?? '');
        $result    = verify2FACode($userId, $codeInput, 'login');

        if (!$result['ok']) {
            $error = $result['error'];
            logActivity('superadmin_2fa_failed', 'Echec verification 2FA : ' . $superAdmin['username']);
        } else {
            // Code valide : la session complete est ouverte ICI seulement
            loginUser($superAdmin);
            $_SESSION['superadmin_2fa_ok'] = true;

            if (!empty($pending['remember'])) {
                setcookie('ss_remember', generateToken(), time() + REMEMBER_DAYS * 86400, '/', '', false, true);
            }
            unset($_SESSION['pending_2fa'], $_SESSION['dev_2fa_code']);
            logActivity('superadmin_2fa_verified', 'Connexion Super Admin verifiee : ' . $superAdmin['username']);

            if ($superAdmin['must_change_password']) {
                $_SESSION['force_pwd_change'] = true;
                redirectWith(BASE_URL . '/auth/change-password.php', 'warning',
                    'Veuillez definir votre mot de passe personnel avant de continuer.');
            }
            redirectWith(ROLE_REDIRECTS[ROLE_SUPER_ADMIN], 'success',
                'Bienvenue ' . $superAdmin['first_name'] . ' !');
        }
    }
}

$schoolName = getSetting('school_name', APP_NAME);
$devCode    = APP_ENV === 'development' ? ($_SESSION['dev_2fa_code'] ?? null) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Verification de securite — <?= clean($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);}
    .v2fa-wrap{width:100%;max-width:440px;padding:20px;}
    .v2fa-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);padding:36px;box-shadow:var(--shadow-lg);}
    .v2fa-icon{width:60px;height:60px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff;margin:0 auto 14px;box-shadow:var(--shadow-primary);}
    .code-input{
      width:100%;padding:14px;text-align:center;letter-spacing:0.6em;font-size:24px;font-weight:700;
      border:1.5px solid var(--border);border-radius:var(--radius);background:var(--bg-card);color:var(--text-primary);
      font-family:var(--font-mono);
    }
    .code-input:focus{outline:none;border-color:var(--border-focus);box-shadow:0 0 0 3px rgba(79,70,229,0.10);}
    .btn-auth{width:100%;padding:13px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);margin-top:14px;}
    .btn-auth:hover{filter:brightness(1.07);}
    .dev-banner{background:var(--warning-bg);border:1px dashed var(--warning);border-radius:var(--radius);padding:12px 14px;margin-bottom:18px;font-size:12.5px;color:var(--warning-dark);}
    .dev-banner strong{font-family:var(--font-mono);font-size:15px;letter-spacing:0.1em;}
    @keyframes spin{to{transform:rotate(360deg);}}
  </style>
</head>
<body>
<div class="v2fa-wrap">
  <div style="text-align:center;margin-bottom:22px">
    <div class="v2fa-icon"><i class="bx bx-shield-quarter"></i></div>
    <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Verification de securite</h1>
    <p style="font-size:13px;color:var(--text-muted)">
      Un code a 6 chiffres a ete envoye a<br>
      <strong style="color:var(--text-secondary)"><?= clean($superAdmin['email'] ?? '') ?></strong>
    </p>
  </div>

  <div class="v2fa-card">
    <?php if ($devCode): ?>
      <div class="dev-banner">
        <i class="bx bx-code-alt"></i> <strong>Mode developpement</strong> — aucun serveur SMTP configure,
        voici le code directement : <strong><?= clean($devCode) ?></strong><br>
        <span style="opacity:.8">A retirer en production (voir <code>APP_ENV</code> dans config/constants.php).</span>
      </div>
    <?php endif; ?>

    <?php if ($resent): ?>
      <div class="alert alert-success"><i class="bx bx-check-circle"></i> Nouveau code envoye.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bx bx-x-circle"></i> <?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST" data-loading>
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" style="text-align:center;display:block">Code de verification</label>
        <input type="text" name="code" class="code-input" maxlength="6" inputmode="numeric"
               pattern="[0-9]{6}" placeholder="------" autofocus required autocomplete="one-time-code">
        <p class="form-hint" style="text-align:center;margin-top:8px">Valide 10 minutes.</p>
      </div>
      <button type="submit" class="btn-auth"><i class="bx bx-check-shield"></i> Verifier et se connecter</button>
    </form>

    <form method="POST" style="margin-top:10px">
      <?= csrfField() ?>
      <button type="submit" name="resend" value="1" class="btn btn-ghost btn-block btn-sm">
        <i class="bx bx-refresh"></i> Renvoyer le code
      </button>
    </form>

    <div style="text-align:center;margin-top:16px;padding-top:14px;border-top:1px solid var(--border-light)">
      <a href="<?= BASE_URL ?>/auth/logout.php" style="font-size:13px;color:var(--text-muted)">
        <i class="bx bx-arrow-back"></i> Annuler et revenir a la connexion
      </a>
    </div>
  </div>
</div>

<script src="<?= ASSETS_URL ?>/js/main.js"></script>
</body></html>
