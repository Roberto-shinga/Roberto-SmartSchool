<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = "Journal d'activite";
$pageSection = 'logs';

$q        = trim($_GET['q'] ?? '');
$roleFilt = (int)($_GET['role'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($q !== '') { $where[] = "(al.action LIKE ? OR al.description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($roleFilt > 0) { $where[] = "u.role_id = ?"; $params[] = $roleFilt; }
if ($dateFrom !== '') { $where[] = "al.created_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo   !== '') { $where[] = "al.created_at <= ?"; $params[] = $dateTo   . ' 23:59:59'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = dbFetchOne("SELECT COUNT(*) c FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id $whereSql", $params)['c'] ?? 0;
$pg = paginate($total, $page);

$logs = dbFetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.role_id
     FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id
     $whereSql ORDER BY al.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$actionColors = [
    'login' => 'success', 'login_failed' => 'danger', 'logout' => 'gray', 'register' => 'info',
    'student_' => 'primary', 'class_' => 'primary', 'subject_' => 'primary', 'parent_' => 'primary',
    'payment_' => 'success', 'fee_' => 'success', 'invitation_' => 'warning', 'account_activated' => 'success',
    'attendance_' => 'cyan', 'grade_' => 'cyan', 'timetable_' => 'cyan', 'report_' => 'cyan',
    'notification_' => 'purple', 'message_' => 'purple', 'settings_' => 'gray', 'academic_year_' => 'gray', 'term_' => 'gray',
];
function logColor(string $action, array $map): string {
    foreach ($map as $k => $c) if (str_starts_with($action, $k) || $action === rtrim($k, '_')) return $c;
    return 'gray';
}
function qsUrlLog(int $page, string $q, int $role, string $from, string $to): string {
    return '?' . http_build_query(array_filter(['page'=>$page,'q'=>$q?:null,'role'=>$role?:null,'date_from'=>$from?:null,'date_to'=>$to?:null]));
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Journal d'activite</h1><p><?= $total ?> evenement(s)</p></div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-4" style="align-items:end">
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Recherche</label><input type="text" name="q" class="form-control" value="<?= clean($q) ?>"></div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Role</label>
            <select name="role" class="form-control">
              <option value="0">Tous</option>
              <?php foreach (ROLE_LABELS as $rid => $lbl): ?><option value="<?= $rid ?>" <?= $roleFilt===$rid?'selected':'' ?>><?= clean($lbl) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Du</label><input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>"></div>
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Au</label><input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>"></div>
          <div style="grid-column:1/-1;display:flex;gap:10px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bx bx-filter-alt"></i> Filtrer</button>
            <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-ghost btn-sm">Reinitialiser</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if (empty($logs)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-list-ul"></i></div><h3>Aucun evenement trouve</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Date</th><th>Acteur</th><th>Action</th><th>Description</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $log): $actor = $log['first_name'] ? clean($log['first_name'].' '.$log['last_name']) : 'Systeme'; ?>
            <tr>
              <td class="text-sm text-muted" style="white-space:nowrap"><?= formatDateTime($log['created_at']) ?></td>
              <td class="text-sm"><?= $actor ?><?php if ($log['role_id']): ?><div class="text-xs text-muted"><?= clean(ROLE_LABELS[$log['role_id']] ?? '') ?></div><?php endif; ?></td>
              <td><span class="badge badge-<?= logColor($log['action'], $actionColors) ?>"><?= clean($log['action']) ?></span></td>
              <td class="text-sm text-muted"><?= clean($log['description'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pg['total_pages'] > 1): ?>
      <div class="pagination">
        <?php if ($pg['has_prev']): ?><a href="<?= qsUrlLog($pg['prev_page'], $q, $roleFilt, $dateFrom, $dateTo) ?>"><i class="bx bx-chevron-left"></i></a><?php endif; ?>
        <?php for ($p = 1; $p <= $pg['total_pages']; $p++): ?>
          <?php if ($p === $pg['current_page']): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= qsUrlLog($p, $q, $roleFilt, $dateFrom, $dateTo) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($pg['has_next']): ?><a href="<?= qsUrlLog($pg['next_page'], $q, $roleFilt, $dateFrom, $dateTo) ?>"><i class="bx bx-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
