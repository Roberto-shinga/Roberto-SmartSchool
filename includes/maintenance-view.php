<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Maintenance en cours — <?= clean(getSetting('school_name', APP_NAME)) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);text-align:center;padding:20px;}
    .maint-icon{width:76px;height:76px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;margin:0 auto 20px;box-shadow:var(--shadow-primary);}
  </style>
</head>
<body>
  <div style="max-width:440px">
    <div class="maint-icon"><i class="bx bx-wrench"></i></div>
    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:10px">Maintenance en cours</h1>
    <p style="color:var(--text-muted);line-height:1.7">
      <?= clean(getSetting('maintenance_message', 'La plateforme est temporairement indisponible pour maintenance. Merci de revenir dans quelques instants.')) ?>
    </p>
  </div>
</body></html>
