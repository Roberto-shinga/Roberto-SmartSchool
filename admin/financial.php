<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Finances';
$pageSection = 'financial';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Totaux generaux ────────────────────────────────────────────
$totalCollected = dbFetchOne(
    "SELECT COALESCE(SUM(p.amount_paid),0) t FROM payments p
     JOIN fees f ON p.fee_id = f.id WHERE f.academic_year_id=? AND p.status IN ('paye','partiel')",
    [$yearId]
)['t'] ?? 0;

$nbPayments = dbFetchOne(
    "SELECT COUNT(*) c FROM payments p JOIN fees f ON p.fee_id=f.id WHERE f.academic_year_id=? AND p.status IN ('paye','partiel')",
    [$yearId]
)['c'] ?? 0;

// ── Montant attendu (frais x eleves eligibles) ─────────────────
$fees = dbFetchAll("SELECT * FROM fees WHERE academic_year_id=?", [$yearId]);
$totalExpected = 0;
foreach ($fees as $f) {
    if ($f['class_id'])      $eligible = dbCount('students', "class_id=? AND status='actif'", [$f['class_id']]);
    elseif ($f['level_id'])  $eligible = dbCount('students', "level_id=? AND status='actif' AND academic_year_id=?", [$f['level_id'], $yearId]);
    else                     $eligible = dbCount('students', "status='actif' AND academic_year_id=?", [$yearId]);
    $totalExpected += $eligible * (float)$f['amount'];
}
$recoveryRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 1) : 0;
$outstanding  = max(0, $totalExpected - $totalCollected);

// ── Repartition par categorie ──────────────────────────────────
$byCategory = dbFetchAll(
    "SELECT fc.name, COALESCE(SUM(p.amount_paid),0) t
     FROM fee_categories fc
     JOIN fees f ON f.category_id = fc.id AND f.academic_year_id = ?
     LEFT JOIN payments p ON p.fee_id = f.id AND p.status IN ('paye','partiel')
     GROUP BY fc.id, fc.name HAVING t > 0 ORDER BY t DESC",
    [$yearId]
);
$maxCategory = 1;
foreach ($byCategory as $bc) $maxCategory = max($maxCategory, (float)$bc['t']);

// ── Tendance mensuelle (6 derniers mois) ───────────────────────
$byMonth = dbFetchAll(
    "SELECT DATE_FORMAT(p.payment_date, '%Y-%m') ym, COALESCE(SUM(p.amount_paid),0) t
     FROM payments p JOIN fees f ON p.fee_id=f.id
     WHERE f.academic_year_id=? AND p.status IN ('paye','partiel') AND p.payment_date >= NOW() - INTERVAL 6 MONTH
     GROUP BY ym ORDER BY ym",
    [$yearId]
);
$maxMonth = 1;
foreach ($byMonth as $bm) $maxMonth = max($maxMonth, (float)$bm['t']);

// ── Top classes par encaissement ───────────────────────────────
$byClass = dbFetchAll(
    "SELECT c.name, COALESCE(SUM(p.amount_paid),0) t
     FROM classes c
     JOIN students s ON s.class_id = c.id
     JOIN payments p ON p.student_id = s.id
     JOIN fees f ON p.fee_id = f.id AND f.academic_year_id = ?
     WHERE c.academic_year_id = ? AND p.status IN ('paye','partiel')
     GROUP BY c.id, c.name ORDER BY t DESC LIMIT 8",
    [$yearId, $yearId]
);

// ── Eleves en impaye (top 10) ──────────────────────────────────
$dueByStudent = dbFetchAll(
    "SELECT s.id,
            SUM(CASE WHEN (f.class_id IS NULL OR f.class_id = s.class_id) AND (f.level_id IS NULL OR f.level_id = s.level_id)
                     THEN f.amount ELSE 0 END) AS due_total
     FROM students s
     CROSS JOIN fees f
     WHERE s.status = 'actif' AND s.academic_year_id = ? AND f.academic_year_id = ?
     GROUP BY s.id",
    [$yearId, $yearId]
);
$paidByStudent = dbFetchAll(
    "SELECT p.student_id, SUM(p.amount_paid) paid FROM payments p
     JOIN fees f ON p.fee_id = f.id WHERE f.academic_year_id=? AND p.status IN ('paye','partiel') GROUP BY p.student_id",
    [$yearId]
);
$paidMap = [];
foreach ($paidByStudent as $row) $paidMap[$row['student_id']] = (float)$row['paid'];

$balances = [];
foreach ($dueByStudent as $row) {
    $balance = (float)$row['due_total'] - ($paidMap[$row['id']] ?? 0);
    if ($balance > 0) $balances[$row['id']] = $balance;
}
arsort($balances);
$topDebtors = array_slice($balances, 0, 10, true);

$debtorDetails = [];
if (!empty($topDebtors)) {
    $ids = implode(',', array_map('intval', array_keys($topDebtors)));
    $rows = dbFetchAll(
        "SELECT s.id, s.student_number, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln, c.name AS class_name
         FROM students s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id
         WHERE s.id IN ($ids)"
    );
    foreach ($rows as $r) $debtorDetails[$r['id']] = $r;
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Finances</h1><p>Vue d'ensemble — annee <?= clean($currentYear['name'] ?? '—') ?></p></div>
      <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-secondary"><i class="bx bx-list-ul"></i> Voir les paiements</a>
      </div>
    </div>

    <div class="grid-4 mb-6">
      <div class="stat-card c-success">
        <div class="stat-icon c-success"><i class="bx bx-wallet"></i></div>
        <div><div class="stat-value" style="font-size:19px"><?= formatMoney($totalCollected) ?></div><div class="stat-label">Total encaisse</div></div>
      </div>
      <div class="stat-card c-primary">
        <div class="stat-icon c-primary"><i class="bx bx-receipt"></i></div>
        <div><div class="stat-value" style="font-size:19px"><?= formatMoney($totalExpected) ?></div><div class="stat-label">Total attendu</div></div>
      </div>
      <div class="stat-card c-cyan">
        <div class="stat-icon c-cyan"><i class="bx bx-trending-up"></i></div>
        <div><div class="stat-value"><?= $recoveryRate ?>%</div><div class="stat-label">Taux de recouvrement</div></div>
      </div>
      <div class="stat-card c-danger">
        <div class="stat-icon c-danger"><i class="bx bx-error-circle"></i></div>
        <div><div class="stat-value" style="font-size:19px"><?= formatMoney($outstanding) ?></div><div class="stat-label">Solde restant</div></div>
      </div>
    </div>

    <div class="grid-2 mb-6">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-pie-chart-alt"></i> Repartition par categorie</h3></div>
        <div class="card-body">
          <?php if (empty($byCategory)): ?>
            <p class="text-sm text-muted">Aucun paiement enregistre pour l'instant.</p>
          <?php else: foreach ($byCategory as $bc): ?>
            <div style="margin-bottom:14px">
              <div class="flex justify-between text-sm" style="margin-bottom:6px">
                <span style="color:var(--text-secondary)"><?= clean($bc['name']) ?></span>
                <span class="font-semibold"><?= formatMoney($bc['t']) ?></span>
              </div>
              <div class="progress"><div class="progress-bar" data-value="<?= round(($bc['t']/$maxCategory)*100) ?>" style="width:0"></div></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-line-chart"></i> Tendance (6 derniers mois)</h3></div>
        <div class="card-body">
          <?php if (empty($byMonth)): ?>
            <p class="text-sm text-muted">Pas assez de donnees.</p>
          <?php else: foreach ($byMonth as $bm): ?>
            <div style="margin-bottom:14px">
              <div class="flex justify-between text-sm" style="margin-bottom:6px">
                <span style="color:var(--text-secondary)"><?= clean($bm['ym']) ?></span>
                <span class="font-semibold"><?= formatMoney($bm['t']) ?></span>
              </div>
              <div class="progress"><div class="progress-bar success" data-value="<?= round(($bm['t']/$maxMonth)*100) ?>" style="width:0"></div></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-buildings"></i> Top classes par encaissement</h3></div>
        <?php if (empty($byClass)): ?>
          <div class="card-body"><p class="text-sm text-muted">Aucune donnee.</p></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table">
            <thead><tr><th>Classe</th><th>Encaisse</th></tr></thead>
            <tbody><?php foreach ($byClass as $bc): ?><tr><td class="text-sm"><?= clean($bc['name']) ?></td><td class="font-semibold"><?= formatMoney($bc['t']) ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-error-circle" style="color:var(--danger)"></i> Eleves en impaye (top 10)</h3></div>
        <?php if (empty($topDebtors)): ?>
          <div class="card-body"><p class="text-sm" style="color:var(--success)"><i class="bx bx-check-circle"></i> Aucun solde impaye detecte.</p></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table">
            <thead><tr><th>Eleve</th><th>Classe</th><th>Solde du</th></tr></thead>
            <tbody>
              <?php foreach ($topDebtors as $studentId => $balance): $d = $debtorDetails[$studentId] ?? null; if (!$d) continue; ?>
              <tr>
                <td class="text-sm"><?= clean($d['fn'] . ' ' . $d['ln']) ?><div class="text-xs text-muted"><?= clean($d['student_number']) ?></div></td>
                <td class="text-sm text-muted"><?= clean($d['class_name'] ?: '—') ?></td>
                <td class="font-semibold" style="color:var(--danger)"><?= formatMoney($balance) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
