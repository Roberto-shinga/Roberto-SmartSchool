<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Sauvegardes';
$pageSection = 'backups';
$admin       = currentUser();

if (!is_dir(BACKUPS_PATH)) { @mkdir(BACKUPS_PATH, 0755, true); }

function backupMysqlArgs(): string
{
    $args = '--host=' . escapeshellarg(DB_HOST) . ' --user=' . escapeshellarg(DB_USER);
    if (DB_PASS !== '') $args .= ' --password=' . escapeshellarg(DB_PASS);
    return $args;
}

$shellAvailable = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))));

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Creer une sauvegarde ───────────────────────────────────
    if ($action === 'create') {
        if (!$shellAvailable) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'shell_exec() est desactive sur ce serveur. Impossible de lancer mysqldump.');
        } else {
            $filename = 'smartschool_' . date('Ymd_His') . '.sql';
            $filepath = BACKUPS_PATH . '/' . $filename;
            $cmd = escapeshellarg(MYSQLDUMP_BIN) . ' ' . backupMysqlArgs() . ' ' . escapeshellarg(DB_NAME)
                 . ' > ' . escapeshellarg($filepath) . ' 2>&1';
            @shell_exec($cmd);

            if (is_file($filepath) && filesize($filepath) > 0) {
                logActivity('backup_created', $filename);
                redirectWith($_SERVER['PHP_SELF'], 'success', "Sauvegarde creee : $filename");
            } else {
                @unlink($filepath);
                logActivity('backup_failed', 'Echec de la creation de sauvegarde');
                redirectWith($_SERVER['PHP_SELF'], 'danger',
                    'Echec de la sauvegarde. Verifie le chemin MYSQLDUMP_BIN dans config/constants.php.');
            }
        }
    }

    // ── Supprimer une sauvegarde ───────────────────────────────
    if ($action === 'delete') {
        $file = basename($_POST['file'] ?? '');
        $path = BACKUPS_PATH . '/' . $file;
        if ($file && str_ends_with($file, '.sql') && is_file($path)) {
            unlink($path);
            logActivity('backup_deleted', $file);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Sauvegarde supprimee.');
        } else {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Fichier introuvable.');
        }
    }

    // ── Restaurer une sauvegarde ────────────────────────────────
    if ($action === 'restore') {
        $file     = basename($_POST['file'] ?? '');
        $confirm  = trim($_POST['confirm_text'] ?? '');
        $path     = BACKUPS_PATH . '/' . $file;

        if ($confirm !== 'RESTAURER') {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Confirmation incorrecte. Restauration annulee.');
        } elseif (!$shellAvailable) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'shell_exec() est desactive sur ce serveur. Impossible de restaurer.');
        } elseif (!$file || !str_ends_with($file, '.sql') || !is_file($path)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Fichier de sauvegarde introuvable.');
        } else {
            $cmd = escapeshellarg(MYSQL_BIN) . ' ' . backupMysqlArgs() . ' ' . escapeshellarg(DB_NAME)
                 . ' < ' . escapeshellarg($path) . ' 2>&1';
            @shell_exec($cmd);
            logActivity('backup_restored', $file);
            redirectWith($_SERVER['PHP_SELF'], 'warning',
                "Restauration executee depuis $file. Verifie l'integrite des donnees.");
        }
    }
}

// ── Liste des sauvegardes existantes ──────────────────────────
$backups = [];
if (is_dir(BACKUPS_PATH)) {
    foreach (glob(BACKUPS_PATH . '/*.sql') as $f) {
        $backups[] = ['name' => basename($f), 'size' => filesize($f), 'date' => filemtime($f)];
    }
    usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' Ko';
    return $bytes . ' o';
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Sauvegardes</h1><p>Sauvegarde et restauration de la base de donnees</p></div>
      <div class="page-header-actions">
        <form method="POST" data-confirm="Lancer une sauvegarde complete de la base maintenant ?">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create">
          <button type="submit" class="btn btn-primary"><i class="bx bx-cloud-upload"></i> Nouvelle sauvegarde</button>
        </form>
      </div>
    </div>

    <?php if (!$shellAvailable): ?>
    <div class="alert alert-warning" style="margin-bottom:20px">
      <i class="bx bx-error-circle"></i>
      <div><strong>shell_exec() est desactive</strong> sur ce serveur PHP. Les sauvegardes et restaurations
      automatiques ne fonctionneront pas tant que cette fonction n'est pas autorisee dans <code>php.ini</code>
      (retirer <code>shell_exec</code> de <code>disable_functions</code>).</div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-bottom:20px">
      <i class="bx bx-info-circle"></i>
      Verifie que <code>MYSQLDUMP_BIN</code> et <code>MYSQL_BIN</code> dans <code>config/constants.php</code>
      correspondent bien a ton installation XAMPP (par defaut : <code>C:/xampp/mysql/bin/</code>).
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="bx bx-archive"></i> Sauvegardes disponibles</h3></div>

      <?php if (empty($backups)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-archive"></i></div>
          <h3>Aucune sauvegarde</h3>
          <p>Cree ta premiere sauvegarde de la base de donnees.</p>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Fichier</th><th>Taille</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($backups as $b): ?>
            <tr>
              <td class="text-sm font-mono"><i class="bx bx-file"></i> <?= clean($b['name']) ?></td>
              <td class="text-sm text-muted"><?= formatBytes($b['size']) ?></td>
              <td class="text-sm text-muted"><?= date('d/m/Y H:i', $b['date']) ?></td>
              <td class="td-actions">
                <a href="<?= BASE_URL ?>/superadmin/backup-download.php?file=<?= urlencode($b['name']) ?>"
                   class="btn btn-ghost btn-icon btn-sm" title="Telecharger"><i class="bx bx-download"></i></a>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Restaurer"
                        style="color:var(--warning)"
                        onclick="document.getElementById('restoreFile_<?= md5($b['name']) ?>').value='<?= clean($b['name']) ?>';
                                 document.getElementById('restoreName_<?= md5($b['name']) ?>').textContent='<?= clean($b['name']) ?>';
                                 SS.openModal('restoreModal_<?= md5($b['name']) ?>')">
                  <i class="bx bx-recycle"></i>
                </button>
                <form method="POST" style="display:inline" data-confirm="Supprimer definitivement <?= clean($b['name']) ?> ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="file" value="<?= clean($b['name']) ?>">
                  <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Supprimer" style="color:var(--danger)">
                    <i class="bx bx-trash"></i>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Modal de restauration (une par fichier, avec confirmation textuelle) -->
            <div class="modal-overlay" id="restoreModal_<?= md5($b['name']) ?>">
              <div class="modal">
                <form method="POST">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="file" id="restoreFile_<?= md5($b['name']) ?>" value="<?= clean($b['name']) ?>">
                  <div class="modal-header">
                    <h3><i class="bx bx-error-circle" style="color:var(--danger)"></i> Restaurer une sauvegarde</h3>
                    <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
                  </div>
                  <div class="modal-body">
                    <div class="alert alert-danger">
                      <i class="bx bx-error"></i>
                      <div>Cette action va <strong>ecraser toutes les donnees actuelles</strong> avec le contenu de
                      <span id="restoreName_<?= md5($b['name']) ?>" style="font-weight:700"><?= clean($b['name']) ?></span>.
                      Cette operation est irreversible.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                      <label class="form-label">Tape <strong>RESTAURER</strong> pour confirmer</label>
                      <input type="text" name="confirm_text" class="form-control" autocomplete="off" required
                             oninput="this.closest('.modal').querySelector('[data-restore-submit]').disabled = this.value !== 'RESTAURER'">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
                    <button type="submit" class="btn btn-danger" data-restore-submit disabled>
                      <i class="bx bx-recycle"></i> Restaurer definitivement
                    </button>
                  </div>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
