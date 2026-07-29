<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Journaux d\'activité';
$pageSection = 'logs';
$user        = currentUser();

$search   = trim($_GET['q']      ?? '');
$filterAction = trim($_GET['action_filter'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR al.description LIKE ?)";
    $s = "%$search%";
    $params   = [$s,$s,$s];
}
if ($filterAction) { $where[] = "al.action=?"; $params[] = $filterAction; }
$whereStr = implode(' AND ', $where);

$total = dbFetchOne("SELECT COUNT(*) c FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id WHERE $whereStr", $params)['c'] ?? 0;
$pag   = paginate($total, $page, 30);

$logs = dbFetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.role_id
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id=u.id
     WHERE $whereStr
     ORDER BY al.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

$actions = dbFetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");

$actionMeta = [
    'login'           => ['bx-log-in',    'success', 'Connexion'],
    'login_failed'    => ['bx-error',     'danger',  'Échec connexion'],
    'logout'          => ['bx-log-out',   'gray',    'Déconnexion'],
    'register'        => ['bx-user-plus', 'info',    'Inscription'],
    'change_password' => ['bx-lock-alt',  'warning', 'Changement mot de passe'],
    'add_payment'     => ['bx-credit-card','success','Paiement ajouté'],
    'delete_payment'  => ['bx-trash',     'danger',  'Paiement supprimé'],
    'add_fee'         => ['bx-money',     'primary', 'Frais ajouté'],
    'update_settings' => ['bx-cog',       'cyan',    'Paramètres modifiés'],
    'validate_proof'  => ['bx-check-circle','success','Preuve validée'],
    'reject_proof'    => ['bx-x-circle',  'danger',  'Preuve rejetée'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div><h1>Journaux d'activité</h1><p><?= number_format($total) ?> événements enregistrés</p></div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="search-wrap" style="flex:1;min-width:200px">
        <i class="bx bx-search"></i>
        <input type="text" name="q" class="search-input" placeholder="Utilisateur, description..." value="<?= clean($search) ?>">
      </div>
      <select name="action_filter" class="form-control" style="width:200px">
        <option value="">Toutes les actions</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= clean($a['action']) ?>" <?= $filterAction===$a['action']?'selected':'' ?>>
            <?= $actionMeta[$a['action']][2] ?? clean($a['action']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt"></i> Filtrer</button>
      <?php if ($search || $filterAction): ?>
        <a href="?" class="btn btn-secondary">Réinitialiser</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Action</th><th>Utilisateur</th><th>Description</th><th>IP</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5">
          <div class="empty-state">
            <div class="empty-state-icon"><i class="bx bx-history"></i></div>
            <h3>Aucun journal trouvé</h3>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($logs as $log):
          [$icon, $color, $label] = $actionMeta[$log['action']] ?? ['bx-info-circle','primary', $log['action']];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="stat-icon c-<?= $color ?>" style="width:30px;height:30px;font-size:.9rem">
                <i class="bx <?= $icon ?>"></i>
              </div>
              <span style="font-size:12.5px;font-weight:600"><?= $label ?></span>
            </div>
          </td>
          <td>
            <?php if ($log['first_name']): ?>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:<?= ROLE_COLORS[$log['role_id']] ?? 'var(--primary)' ?>">
                  <?= getInitials($log['first_name'], $log['last_name']) ?>
                </div>
                <div class="td-name"><?= clean($log['first_name']) ?> <?= clean($log['last_name']) ?></div>
              </div>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:13px">Système</span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px;color:var(--text-muted);max-width:300px" class="truncate">
            <?= clean($log['description'] ?: '—') ?>
          </td>
          <td style="font-size:12px;color:var(--text-light)"><?= clean($log['ip_address'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?= formatDateTime($log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
  <?php if ($pag['has_prev']): ?>
    <a href="?page=<?= $pag['prev_page'] ?>&q=<?= urlencode($search) ?>"><i class="bx bx-chevron-left"></i></a>
  <?php endif; ?>
  <?php for ($i = max(1,$pag['current_page']-2); $i <= min($pag['total_pages'],$pag['current_page']+2); $i++): ?>
    <<?= $i===$pag['current_page']?'span class="active"':'a href="?page='.$i.'&q='.urlencode($search).'"' ?>><?= $i ?></<?= $i===$pag['current_page']?'span':'a' ?>>
  <?php endfor; ?>
  <?php if ($pag['has_next']): ?>
    <a href="?page=<?= $pag['next_page'] ?>&q=<?= urlencode($search) ?>"><i class="bx bx-chevron-right"></i></a>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>