<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Notifications';
$pageSection = 'notifications';
$user        = currentUser();

$allUsers = dbFetchAll(
    "SELECT u.id, u.first_name, u.last_name, r.label AS role_label
     FROM users u JOIN roles r ON u.role_id = r.id
     WHERE u.is_active = 1 ORDER BY r.id, u.last_name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $title   = trim($_POST['title']   ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = trim($_POST['type']    ?? 'info');
        $target  = trim($_POST['target']  ?? 'all');
        $userId  = (int)($_POST['user_id']?? 0);

        if (empty($title) || empty($message)) {
            redirectWith(BASE_URL . '/admin/notifications.php', 'danger', 'Titre et message obligatoires.');
        }

        $sent = 0;
        if ($target === 'all') {
            foreach ($allUsers as $u) { createNotification($u['id'], $title, $message, $type); $sent++; }
        } elseif ($target === 'user' && $userId) {
            createNotification($userId, $title, $message, $type); $sent = 1;
        } elseif ($target === 'role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $users  = dbFetchAll("SELECT id FROM users WHERE role_id = ? AND is_active = 1", [$roleId]);
            foreach ($users as $u) { createNotification($u['id'], $title, $message, $type); $sent++; }
        }

        logActivity('send_notification', "Notification envoyee a $sent utilisateur(s) : $title");
        redirectWith(BASE_URL . '/admin/notifications.php', 'success', "Notification envoyee a $sent utilisateur(s) !");
    }

    if ($action === 'mark_all_read') {
        dbExecute("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$user['id']]);
        redirectWith(BASE_URL . '/admin/notifications.php', 'success', 'Toutes marquees comme lues.');
    }

    if ($action === 'delete') {
        dbExecute("DELETE FROM notifications WHERE id = ? AND user_id = ?", [(int)$_POST['notif_id'], $user['id']]);
        redirectWith(BASE_URL . '/admin/notifications.php', 'success', 'Notification supprimee.');
    }

    if ($action === 'delete_all') {
        dbExecute("DELETE FROM notifications WHERE user_id = ?", [$user['id']]);
        redirectWith(BASE_URL . '/admin/notifications.php', 'success', 'Toutes les notifications supprimees.');
    }
}

$filter = trim($_GET['filter'] ?? 'all');
$page   = max(1, (int)($_GET['page'] ?? 1));
$where  = "user_id = {$user['id']}";
if ($filter === 'unread') $where .= " AND is_read = 0";
if ($filter === 'read')   $where .= " AND is_read = 1";

$total  = dbFetchOne("SELECT COUNT(*) c FROM notifications WHERE $where")['c'] ?? 0;
$pag    = paginate($total, $page, 20);
$notifs = dbFetchAll("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
$unreadCount = countUnreadNotifications($user['id']);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>
<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-bell" style="color:var(--primary)"></i> Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="badge badge-danger"><?= $unreadCount ?> non lues</span>
      <?php endif; ?>
    </h1>
    <p>Gerez et envoyez des notifications aux utilisateurs</p>
  </div>
  <div class="page-header-actions">
    <?php if ($unreadCount > 0): ?>
      <form method="POST" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-secondary"><i class="bx bx-check-double"></i> Tout lire</button>
      </form>
    <?php endif; ?>
    <form method="POST" style="display:inline">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete_all">
      <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Supprimer toutes vos notifications ?')">
        <i class="bx bx-trash"></i> Tout supprimer
      </button>
    </form>
    <button class="btn btn-primary" data-modal="modalSend">
      <i class="bx bx-send"></i> Envoyer
    </button>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">
  <div>
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <?php foreach (['all'=>'Toutes','unread'=>'Non lues','read'=>'Lues'] as $k => $l): ?>
        <a href="?filter=<?= $k ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <?php if (empty($notifs)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-bell-off"></i></div>
          <h3>Aucune notification</h3>
          <p>Aucune notification pour le moment.</p>
        </div>
      <?php else: ?>
        <?php
        $tc = ['success'=>'#10b981','danger'=>'#ef4444','warning'=>'#f59e0b','info'=>'#3b82f6'];
        $ti = ['success'=>'bx-check-circle','danger'=>'bx-x-circle','warning'=>'bx-error','info'=>'bx-info-circle'];
        foreach ($notifs as $n):
          $c = $tc[$n['type']] ?? '#6366f1';
          $i = $ti[$n['type']] ?? 'bx-bell';
        ?>
        <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border-light);background:<?= $n['is_read']?'transparent':'var(--primary-bg)' ?>">
          <div style="width:40px;height:40px;border-radius:var(--radius);background:<?= $c ?>18;color:<?= $c ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
            <i class="bx <?= $i ?>"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
              <div style="font-size:14px;font-weight:<?= $n['is_read']?'500':'700' ?>;color:var(--text-primary)"><?= clean($n['title']) ?></div>
              <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                <?php if (!$n['is_read']): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block"></span><?php endif; ?>
                <span style="font-size:11.5px;color:var(--text-muted)"><?= timeAgo($n['created_at']) ?></span>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm btn-icon"><i class="bx bx-x"></i></button>
                </form>
              </div>
            </div>
            <div style="font-size:13px;color:var(--text-muted);margin-top:4px"><?= clean($n['message']) ?></div>
            <div style="margin-top:6px">
              <span class="badge badge-<?= $n['type']==='success'?'success':($n['type']==='danger'?'danger':($n['type']==='warning'?'warning':'info')) ?>" style="font-size:10.5px">
                <?= ucfirst($n['type']) ?>
              </span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if ($pag['total_pages'] > 1): ?>
        <div style="padding:14px 20px;border-top:1px solid var(--border-light);display:flex;justify-content:center">
          <nav class="pagination">
            <?php if ($pag['has_prev']): ?><a href="?filter=<?= $filter ?>&page=<?= $pag['prev_page'] ?>"><i class="bx bx-chevron-left"></i></a><?php endif; ?>
            <?php for ($pg=max(1,$pag['current_page']-2);$pg<=min($pag['total_pages'],$pag['current_page']+2);$pg++): ?>
              <?php if ($pg===$pag['current_page']): ?><span class="active"><?= $pg ?></span><?php else: ?><a href="?filter=<?= $filter ?>&page=<?= $pg ?>"><?= $pg ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pag['has_next']): ?><a href="?filter=<?= $filter ?>&page=<?= $pag['next_page'] ?>"><i class="bx bx-chevron-right"></i></a><?php endif; ?>
          </nav>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="bx bx-bar-chart-alt"></i> Statistiques</h3></div>
    <div class="card-body">
      <?php
      $stats = dbFetchAll("SELECT type, COUNT(*) nb FROM notifications WHERE user_id = ? GROUP BY type", [$user['id']]);
      foreach ($stats as $st):
      ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-light)">
        <span class="badge badge-<?= $st['type']==='success'?'success':($st['type']==='danger'?'danger':($st['type']==='warning'?'warning':'info')) ?>"><?= ucfirst($st['type']) ?></span>
        <span style="font-weight:700;color:var(--text-primary)"><?= $st['nb'] ?></span>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:space-between;padding-top:10px">
        <span style="font-size:13px;color:var(--text-muted)">Total</span>
        <span style="font-weight:800;color:var(--text-primary)"><?= $total ?></span>
      </div>
    </div>
  </div>
</div>

<!-- MODAL ENVOYER -->
<div class="modal-overlay" id="modalSend">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-send" style="color:var(--primary)"></i> Envoyer une notification</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="send">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Destinataires</label>
          <select name="target" class="form-control" id="targetSelect" onchange="handleTarget(this.value)">
            <option value="all">Tous les utilisateurs</option>
            <option value="role">Par role</option>
            <option value="user">Utilisateur specifique</option>
          </select>
        </div>
        <div id="roleField" class="form-group" style="display:none">
          <label class="form-label">Role</label>
          <select name="role_id" class="form-control">
            <option value="2">Administrateurs</option>
            <option value="3">Enseignants</option>
            <option value="4">Eleves</option>
            <option value="5">Parents</option>
            <option value="6">Comptables</option>
          </select>
        </div>
        <div id="userField" class="form-group" style="display:none">
          <label class="form-label">Utilisateur</label>
          <select name="user_id" class="form-control">
            <option value="">Selectionner</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= $u['id'] ?>"><?= clean($u['first_name']) ?> <?= clean($u['last_name']) ?> (<?= clean($u['role_label']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
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
          <label class="form-label">Titre <span class="form-required">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="Titre de la notification" required>
        </div>
        <div class="form-group">
          <label class="form-label">Message <span class="form-required">*</span></label>
          <textarea name="message" class="form-control" rows="3" placeholder="Message..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-send"></i> Envoyer</button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>
<?php
$pageScript = "
function handleTarget(val) {
  document.getElementById('roleField').style.display = val==='role' ? '' : 'none';
  document.getElementById('userField').style.display = val==='user' ? '' : 'none';
}
";
require_once INCLUDES_PATH . '/footer.php';
?>