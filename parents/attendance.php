<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireParent();

$pageTitle   = 'Presences';
$pageSection = 'attendance';
$user        = currentUser();

$children = getParentChildren($user['id']);
if (empty($children)) {
    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    echo '<div class="main-content"><div class="page-wrapper"><div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-group"></i></div><h3>Aucun enfant lie</h3></div></div></div>';
    require INCLUDES_PATH . '/footer.php';
    exit;
}

$studentId = (int)($_GET['student_id'] ?? $children[0]['id']);

// GARDE-FOU : verification serveur du lien parent-enfant
if (!parentOwnsStudent($user['id'], $studentId)) {
    redirectWith($_SERVER['PHP_SELF'], 'danger', "Cet eleve n'est pas lie a ton compte.");
}

$monthFlt = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthFlt)) $monthFlt = date('Y-m');

$records = dbFetchAll(
    "SELECT * FROM attendance WHERE student_id=? AND DATE_FORMAT(date, '%Y-%m')=? ORDER BY date DESC",
    [$studentId, $monthFlt]
);

$stats = ['present' => 0, 'absent' => 0, 'retard' => 0, 'excuse' => 0];
foreach ($records as $r) $stats[$r['status']] = ($stats[$r['status']] ?? 0) + 1;
$total = array_sum($stats);
$rate  = $total > 0 ? round(($stats['present'] / $total) * 100) : null;

$statusMeta = [
    'present' => ['success', 'bx-check-circle',  'Present'],
    'absent'  => ['danger',  'bx-x-circle',       'Absent'],
    'retard'  => ['warning', 'bx-time-five',      'Retard'],
    'excuse'  => ['info',    'bx-shield-quarter', 'Excuse'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Presences</h1><p>Suivi mensuel</p></div>
      <div class="page-header-actions">
        <form method="GET" style="display:flex;gap:10px">
          <?php if (count($children) > 1): ?>
          <select name="student_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?><option value="<?= $c['id'] ?>" <?= $studentId===$c['id']?'selected':'' ?>><?= clean($c['fn'] . ' ' . $c['ln']) ?></option><?php endforeach; ?>
          </select>
          <?php else: ?>
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
          <?php endif; ?>
          <input type="month" name="month" class="form-control" value="<?= clean($monthFlt) ?>" onchange="this.form.submit()">
        </form>
      </div>
    </div>

    <div class="grid-4 mb-6">
      <?php foreach ($statusMeta as $key => [$color, $icon, $label]): ?>
      <div class="stat-card c-<?= $color ?>"><div class="stat-icon c-<?= $color ?>"><i class="bx <?= $icon ?>"></i></div><div><div class="stat-value"><?= $stats[$key] ?></div><div class="stat-label"><?= $label ?></div></div></div>
      <?php endforeach; ?>
    </div>

    <?php if ($rate !== null): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <div class="flex justify-between text-sm" style="margin-bottom:8px">
          <span>Taux de presence du mois</span><span class="font-semibold"><?= $rate ?>%</span>
        </div>
        <div class="progress"><div class="progress-bar <?= $rate >= 80 ? 'success' : '' ?>" data-value="<?= $rate ?>" style="width:0"></div></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <?php if (empty($records)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-calendar"></i></div><h3>Aucun enregistrement pour ce mois</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Date</th><th>Statut</th></tr></thead>
          <tbody>
            <?php foreach ($records as $r): [$color, $icon, $label] = $statusMeta[$r['status']] ?? ['gray','bx-help-circle','Inconnu']; ?>
            <tr>
              <td class="text-sm"><?= formatDate($r['date']) ?></td>
              <td><span class="badge badge-<?= $color ?>"><i class="bx <?= $icon ?>"></i> <?= $label ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
