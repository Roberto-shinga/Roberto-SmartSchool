<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

http_response_code(403);
$schoolName = getSetting('school_name', APP_NAME);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Acces refuse — <?= clean($schoolName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);text-align:center;padding:20px;}
    .icon{width:76px;height:76px;border-radius:50%;background:var(--grad-danger);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;margin:0 auto 20px;box-shadow:0 8px 20px rgba(239,68,68,.3);}
  </style>
</head>
<body>
  <div style="max-width:440px">
    <div class="icon"><i class="bx bx-lock-alt"></i></div>
    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:10px">Acces refuse</h1>
    <p style="color:var(--text-muted);line-height:1.7;margin-bottom:24px">
      <?php if ($user): ?>
        Ton compte (<?= clean(ROLE_LABELS[$user['role_id']] ?? '') ?>) n'a pas les permissions necessaires
        pour acceder a cette page.
      <?php else: ?>
        Tu dois etre connecte avec un compte autorise pour acceder a cette page.
      <?php endif; ?>
    </p>
    <?php if ($user): ?>
      <a href="<?= ROLE_REDIRECTS[$user['role_id']] ?? BASE_URL ?>" class="btn btn-primary"><i class="bx bx-home-alt"></i> Retour a mon tableau de bord</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary"><i class="bx bx-log-in"></i> Se connecter</a>
    <?php endif; ?>
  </div>
</body></html>
