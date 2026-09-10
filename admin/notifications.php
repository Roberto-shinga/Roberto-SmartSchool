<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Notifications';
$pageSection = 'notifications';
$admin       = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $target  = $_POST['target'] ?? 'user';
    $title   = sanitizeString($_POST['title'] ?? '');
    $message = sanitizeString($_POST['message'] ?? '');
    $type    = in_array($_POST['type'] ?? '', ['info','success','warning','danger']) ? $_POST['type'] : 'info';
    $link    = sanitizeString($_POST['link'] ?? '');

    if (empty($title) || empty($message)) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', 'Titre et message obligatoires.');
    } else {
        $recipients = [];

        if ($target === 'user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId) $recipients = [$userId];
        } elseif ($target === 'role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            if ($roleId) {
                $rows = dbFetchAll("SELECT id FROM users WHERE role_id=? AND is_active=1", [$roleId]);
                $recipients = array_column($rows, 'id');
            }
        } elseif ($target === 'all') {
            $rows = dbFetchAll("SELECT id FROM users WHERE is_active=1");
            $recipients = array_column($rows, 'id');
        }

        if (empty($recipients)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Aucun destinataire trouve pour ce choix.');
        } else {
            foreach ($recipients as $rid) {
                createNotification((int)$rid, $title, $message, $type, $link);
            }
            logActivity('notification_broadcast', "\"$title\" envoyee a " . count($recipients) . " destinataire(s)");
            redirectWith($_SERVER['PHP_SELF'], 'success', count($recipients) . ' notification(s) envoyee(s).');
        }
    }
}

$recentNotifs = dbFetchAll(
    "SELECT n.*, u.first_name, u.last_name, u.role_id
     FROM notifications n JOIN users u ON n.user_id = u.id
     ORDER BY n.created_at DESC LIMIT 20"
);

$allUsers = dbFetchAll("SELECT id, first_name, last_name, role_id FROM users WHERE is_active=1 ORDER BY first_name");

$typeMeta = [
    'info'    => ['info',    'bx-info-circle'],
    'success' => ['success', 'bx-check-circle'],
    'warning' => ['warning', 'bx-error'],
    'danger'  => ['danger',  'bx-error-circle'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Notifications</h1><p>Envoyer une notification a un utilisateur, un role, ou tout l'etablissement</p></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-bell"></i> Composer une notification</h3></div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
              <label class="form-label">Destinataire</label>
              <div class="tabs" style="margin-bottom:12px">
                <button type="button" class="tab-btn active" data-target-tab="user" onclick="switchTarget('user')">Un utilisateur</button>
                <button type="button" class="tab-btn" data-target-tab="role" onclick="switchTarget('role')">Un role</button>
                <button type="button" class="tab-btn" data-target-tab="all" onclick="switchTarget('all')">Tout le monde</button>
              </div>
              <input type="hidden" name="target" id="notif_target" value="user">

              <div id="target_user_box">
                <select name="user_id" class="form-control">
                  <option value="">Selectionner un utilisateur...</option>
                  <?php foreach ($allUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= clean($u['first_name'] . ' ' . $u['last_name']) ?> — <?= clean(ROLE_LABELS[$u['role_id']] ?? '') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div id="target_role_box" style="display:none">
                <select name="role_id" class="form-control">
                  <option value="">Selectionner un role...</option>
                  <?php foreach (ROLE_LABELS as $rid => $lbl): ?><option value="<?= $rid ?>"><?= clean($lbl) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div id="target_all_box" style="display:none">
                <div class="alert alert-warning" style="margin-bottom:0"><i class="bx bx-error"></i> Cette notification sera envoyee a tous les comptes actifs de la plateforme.</div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Titre <span class="form-required">*</span></label>
              <input type="text" name="title" class="form-control" maxlength="200" required>
            </div>
            <div class="form-group">
              <label class="form-label">Message <span class="form-required">*</span></label>
              <textarea name="message" class="form-control" rows="3" required></textarea>
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Type</label>
                <select name="type" class="form-control">
                  <option value="info">Information</option>
                  <option value="success">Succes</option>
                  <option value="warning">Avertissement</option>
                  <option value="danger">Urgent</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Lien (optionnel)</label>
                <input type="text" name="link" class="form-control" placeholder="/admin/payments.php">
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%"><i class="bx bx-send"></i> Envoyer</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-history"></i> Notifications recentes</h3></div>
        <div class="card-body" style="padding:8px 0">
          <?php if (empty($recentNotifs)): ?>
            <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-bell-off"></i></div><p>Aucune notification envoyee.</p></div>
          <?php else: foreach ($recentNotifs as $n): [$color, $icon] = $typeMeta[$n['type']] ?? ['info','bx-info-circle']; ?>
            <div style="display:flex;gap:12px;padding:10px 20px;align-items:flex-start">
              <div class="stat-icon c-<?= $color ?>" style="width:34px;height:34px;font-size:1rem"><i class="bx <?= $icon ?>"></i></div>
              <div style="flex:1;min-width:0">
                <div class="text-sm font-semibold"><?= clean($n['title']) ?></div>
                <div class="text-xs text-muted truncate">A <?= clean($n['first_name'] . ' ' . $n['last_name']) ?> · <?= $n['is_read'] ? 'Lu' : 'Non lu' ?></div>
              </div>
              <div class="text-xs text-muted" style="white-space:nowrap"><?= timeAgo($n['created_at']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function switchTarget(t) {
  document.getElementById('notif_target').value = t;
  document.querySelectorAll('[data-target-tab]').forEach(b => b.classList.toggle('active', b.dataset.targetTab === t));
  document.getElementById('target_user_box').style.display = t === 'user' ? '' : 'none';
  document.getElementById('target_role_box').style.display = t === 'role' ? '' : 'none';
  document.getElementById('target_all_box').style.display  = t === 'all'  ? '' : 'none';
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
