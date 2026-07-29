<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Paramètres';
$pageSection = 'settings';
$user        = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $keys = ['school_name','school_address','school_phone','school_email',
             'currency','currency_name','max_grade','passing_grade','school_motto'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
    }
    // Upload logo
    if (!empty($_FILES['logo']['name'])) {
        $up = uploadFile($_FILES['logo'], 'logos', ['jpg','jpeg','png','webp']);
        if ($up['success']) setSetting('school_logo', $up['filename']);
        else redirectWith(BASE_URL . '/admin/settings.php', 'danger', $up['error']);
    }
    logActivity('update_settings', 'Paramètres de l\'établissement mis à jour');
    redirectWith(BASE_URL . '/admin/settings.php', 'success', 'Paramètres enregistrés !');
}

// Charger tous les paramètres
$settings = [];
$rows = dbFetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
$s = fn($k, $d='') => $settings[$k] ?? $d;

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div><h1>Paramètres</h1><p>Configuration de l'établissement scolaire</p></div>
</div>

<form method="POST" enctype="multipart/form-data">
  <?= csrfField() ?>
  <div class="grid-2" style="align-items:start">

    <!-- Infos établissement -->
    <div class="card">
      <div class="card-header"><h3><i class="bx bx-buildings"></i> Établissement</h3></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Nom de l'établissement</label>
          <input type="text" name="school_name" class="form-control" value="<?= clean($s('school_name','SmartSchool')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Devise scolaire</label>
          <input type="text" name="school_motto" class="form-control" placeholder="Excellence et Savoir" value="<?= clean($s('school_motto')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <textarea name="school_address" class="form-control" rows="2"><?= clean($s('school_address')) ?></textarea>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="school_phone" class="form-control" value="<?= clean($s('school_phone')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="school_email" class="form-control" value="<?= clean($s('school_email')) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Logo de l'école</label>
          <?php $logo = $s('school_logo'); if ($logo && file_exists(UPLOADS_PATH . '/logos/' . $logo)): ?>
            <img src="<?= UPLOADS_URL ?>/logos/<?= clean($logo) ?>" style="height:60px;margin-bottom:8px;display:block;border-radius:var(--radius)">
          <?php endif; ?>
          <input type="file" name="logo" class="form-control" accept="image/*">
          <span class="form-hint">JPG, PNG ou WebP — max 10 Mo</span>
        </div>
      </div>
    </div>

    <!-- Paramètres académiques -->
    <div>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3><i class="bx bx-book-open"></i> Académique</h3></div>
        <div class="card-body">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Note maximale</label>
              <input type="number" name="max_grade" class="form-control" min="10" max="100" value="<?= clean($s('max_grade','20')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Note de passage</label>
              <input type="number" name="passing_grade" class="form-control" min="1" max="100" value="<?= clean($s('passing_grade','10')) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-money"></i> Finances</h3></div>
        <div class="card-body">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Devise (symbole)</label>
              <input type="text" name="currency" class="form-control" value="<?= clean($s('currency','FC')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Nom de la devise</label>
              <input type="text" name="currency_name" class="form-control" value="<?= clean($s('currency_name','Franc Congolais')) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div style="margin-top:20px;display:flex;justify-content:flex-end">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="bx bx-save"></i> Enregistrer les paramètres
    </button>
  </div>
</form>

</div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>