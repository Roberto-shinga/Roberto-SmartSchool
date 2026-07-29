<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Maintenance';
$pageSection = 'maintenance';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_maintenance') {
        $newState = getSetting('maintenance_mode', '0') === '1' ? '0' : '1';
        setSetting('maintenance_mode', $newState);
        setSetting('maintenance_message', sanitizeString($_POST['maintenance_message'] ?? ''));
        logActivity('maintenance_mode_toggled', $newState === '1' ? 'Active' : 'Desactive');
        redirectWith($_SERVER['PHP_SELF'], $newState === '1' ? 'warning' : 'success',
            $newState === '1' ? 'Mode maintenance active.' : 'Mode maintenance desactive.');
    }

    if ($action === 'clear_logs') {
        $days = max(30, (int)($_POST['days'] ?? 90));
        $deleted = dbExecute("DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL ? DAY", [$days]);
        logActivity('logs_cleared', "$deleted entree(s) supprimee(s) (plus de $days jours)");
        redirectWith($_SERVER['PHP_SELF'], 'success', "$deleted entree(s) de journal supprimee(s).");
    }
}

$maintenanceOn = getSetting('maintenance_mode', '0') === '1';
$maintMsg      = getSetting('maintenance_message', 'La plateforme est temporairement indisponible pour maintenance. Merci de revenir dans quelques instants.');

// ── Informations systeme ──────────────────────────────────────
$dbVersion = dbFetchOne("SELECT VERSION() v")['v'] ?? '—';
$dbSize    = dbFetchOne(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) mb
     FROM information_schema.TABLES WHERE table_schema = ?",
    [DB_NAME]
)['mb'] ?? 0;
$tableCount   = dbFetchOne("SELECT COUNT(*) c FROM information_schema.TABLES WHERE table_schema=?", [DB_NAME])['c'] ?? 0;
$logsCount    = dbCount('activity_logs');
$diskFree     = @disk_free_space(ROOT_PATH);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Maintenance</h1><p>Etat du systeme et outils techniques</p></div>
    </div>

    <?php if ($maintenanceOn): ?>
    <div class="alert alert-warning" style="margin-bottom:24px">
      <i class="bx bx-error-circle"></i>
      <div><strong>Mode maintenance actif.</strong> Seul le Super Administrateur peut acceder au site actuellement.</div>
    </div>
    <?php endif; ?>

    <div class="grid-2 mb-6">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-wrench"></i> Mode maintenance</h3></div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="toggle_maintenance">
            <div class="form-group">
              <label class="form-label">Message affiche aux visiteurs</label>
              <textarea name="maintenance_message" class="form-control" rows="3"><?= clean($maintMsg) ?></textarea>
            </div>
            <button type="submit" class="btn <?= $maintenanceOn ? 'btn-secondary' : 'btn-danger' ?>" style="width:100%">
              <i class="bx <?= $maintenanceOn ? 'bx-play-circle' : 'bx-power-off' ?>"></i>
              <?= $maintenanceOn ? 'Desactiver le mode maintenance' : 'Activer le mode maintenance' ?>
            </button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-trash"></i> Nettoyage des journaux</h3></div>
        <div class="card-body">
          <p class="text-sm text-muted" style="margin-bottom:14px"><?= $logsCount ?> entree(s) actuellement dans <code>activity_logs</code>.</p>
          <form method="POST" data-confirm="Supprimer definitivement les anciennes entrees de journal ?">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="clear_logs">
            <div class="form-group">
              <label class="form-label">Supprimer les journaux plus vieux que (jours)</label>
              <input type="number" name="days" class="form-control" value="90" min="30">
            </div>
            <button type="submit" class="btn btn-secondary" style="width:100%">
              <i class="bx bx-trash"></i> Nettoyer les vieux journaux
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="bx bx-server"></i> Informations systeme</h3></div>
      <div class="card-body">
        <div class="grid-4">
          <div><div class="text-xs text-muted">Version PHP</div><div class="font-semibold"><?= PHP_VERSION ?></div></div>
          <div><div class="text-xs text-muted">Version MySQL</div><div class="font-semibold"><?= clean($dbVersion) ?></div></div>
          <div><div class="text-xs text-muted">Tables en base</div><div class="font-semibold"><?= $tableCount ?></div></div>
          <div><div class="text-xs text-muted">Taille de la base</div><div class="font-semibold"><?= $dbSize ?> Mo</div></div>
          <div><div class="text-xs text-muted">Version app</div><div class="font-semibold"><?= APP_VERSION ?></div></div>
          <div><div class="text-xs text-muted">Environnement</div><div class="font-semibold"><?= APP_ENV ?></div></div>
          <div><div class="text-xs text-muted">Limite memoire PHP</div><div class="font-semibold"><?= ini_get('memory_limit') ?></div></div>
          <div><div class="text-xs text-muted">Espace disque libre</div><div class="font-semibold"><?= $diskFree ? round($diskFree / 1073741824, 1) . ' Go' : '—' ?></div></div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
