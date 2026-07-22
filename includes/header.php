<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= clean($pageTitle ?? 'SmartSchool') ?> — <?= clean(getSetting('school_name', APP_NAME)) ?></title>
  <meta name="description" content="SmartSchool RDC — Systeme de gestion scolaire">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">

  <?php if (isset($pageStyles)): ?>
    <style><?= $pageStyles ?></style>
  <?php endif; ?>
</head>
<body class="<?= themeClass() ?>">
<div class="toast-container" id="toastContainer"></div>
