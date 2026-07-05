<?php
// ============================================================
//  SmartSchool — Rapport financier
//  Emplacement : admin/financial.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT);

$pageTitle   = 'Rapport financier';
$pageSection = 'financial';
$user        = currentUser();

// ── Stats globales ───────────────────────────────────────
$totalRevenue   = dbFetchOne("SELECT COALESCE(SUM(amount_paid),0) c FROM payments WHERE status='paye'")['c'] ?? 0;
$totalExpected  = dbFetchOne(
    "SELECT COALESCE(SUM(f.amount * (SELECT COUNT(*) FROM students s WHERE s.status='actif' AND (f.class_id IS NULL OR s.class_id = f.class_id))),0) c
     FROM fees f WHERE f.academic_year_id = (SELECT id FROM academic_years WHERE is_current=1)"
)['c'] ?? 0;
$totalPending   = dbFetchOne("SELECT COUNT(*) c FROM payments WHERE status='partiel'")['c'] ?? 0;
$collectionRate = $totalExpected > 0 ? round(($totalRevenue / $totalExpected) * 100) : 0;

// ── Revenus par mois (12 mois) ───────────────────────────
$monthlyRevenue = dbFetchAll("
    SELECT DATE_FORMAT(payment_date,'%b %Y') lbl,
           DATE_FORMAT(payment_date,'%Y-%m') ym,
           COALESCE(SUM(amount_paid),0) total
    FROM payments
    WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND status='paye'
    GROUP BY ym, lbl ORDER BY ym
");
$monthLabels = json_encode(array_column($monthlyRevenue, 'lbl')   ?: []);
$monthValues = json_encode(array_column($monthlyRevenue, 'total') ?: []);

// ── Revenus par categorie de frais ───────────────────────
$byCategory = dbFetchAll("
    SELECT fc.name, COALESCE(SUM(p.amount_paid),0) total
    FROM fee_categories fc
    LEFT JOIN fees f ON f.category_id = fc.id
    LEFT JOIN payments p ON p.fee_id = f.id AND p.status='paye'
    GROUP BY fc.id, fc.name
    ORDER BY total DESC
");
$catLabels = json_encode(array_column($byCategory, 'name'));
$catValues = json_encode(array_map('floatval', array_column($byCategory, 'total')));

// ── Revenus par mode de paiement ─────────────────────────
$byMethod = dbFetchAll("
    SELECT payment_method, COUNT(*) nb, COALESCE(SUM(amount_paid),0) total
    FROM payments WHERE status='paye'
    GROUP BY payment_method ORDER BY total DESC
");

// ── Top classes contributrices ───────────────────────────
$byClass = dbFetchAll("
    SELECT c.name, COALESCE(SUM(p.amount_paid),0) total, COUNT(p.id) nb
    FROM classes c
    JOIN students s ON s.class_id = c.id
    JOIN payments p ON p.student_id = s.id AND p.status='paye'
    GROUP BY c.id, c.name
    ORDER BY total DESC LIMIT 6
");

// ── Eleves avec impayes ──────────────────────────────────
$unpaidStudents = dbFetchAll("
    SELECT u.first_name, u.last_name, s.student_number, c.name AS class_name,
           p.amount_paid, f.amount AS fee_amount, fc.name AS fee_name, p.payment_date
    FROM payments p
    JOIN students s ON p.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    JOIN fees f ON p.fee_id = f.id
    JOIN fee_categories fc ON f.category_id = fc.id
    WHERE p.status = 'partiel'
    ORDER BY p.payment_date DESC LIMIT 10
");

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
      <i class="bx bx-trending-up" style="color:var(--primary)"></i>
      Rapport financier
    </h1>
    <p>Vue d'ensemble des finances de l'etablissement</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" onclick="window.print()">
      <i class="bx bx-printer"></i> Imprimer
    </button>
  </div>
</div>

<!-- STATS PRINCIPALES -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;margin-bottom:24px">
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-money"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatMoney($totalRevenue) ?></div>
      <div class="stat-label">Revenus totaux</div>
    </div>
  </div>
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-target-lock"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatMoney($totalExpected) ?></div>
      <div class="stat-label">Revenus attendus</div>
    </div>
  </div>
  <div class="stat-card c-cyan">
    <div class="stat-icon c-cyan"><i class="bx bx-pie-chart-alt"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $collectionRate ?>%</div>
      <div class="stat-label">Taux de recouvrement</div>
    </div>
  </div>
  <div class="stat-card c-warning">
    <div class="stat-icon c-warning"><i class="bx bx-time"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $totalPending ?></div>
      <div class="stat-label">Paiements partiels</div>
    </div>
  </div>
</div>

<!-- Barre de progression globale -->
<div class="card" style="margin-bottom:24px">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
      <span style="font-size:13px;font-weight:600;color:var(--text-primary)">Taux de recouvrement global</span>
      <span style="font-size:13px;font-weight:700;color:var(--primary)"><?= $collectionRate ?>%</span>
    </div>
    <div class="progress" style="height:10px">
      <div class="progress-bar <?= $collectionRate >= 70 ? 'success' : 'warning' ?>" style="width:<?= min(100,$collectionRate) ?>%"></div>
    </div>
  </div>
</div>

<!-- GRAPHIQUES -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-line-chart"></i> Evolution des revenus (12 mois)</h3>
    </div>
    <div class="card-body">
      <div style="position:relative;height:260px">
        <canvas id="chartMonthly"></canvas>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-pie-chart-alt-2"></i> Repartition par categorie</h3>
    </div>
    <div class="card-body">
      <div style="position:relative;height:260px">
        <canvas id="chartCategory"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:24px">

  <!-- Modes de paiement -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-wallet"></i> Par mode de paiement</h3>
    </div>
    <div class="card-body">
      <?php
      $methodIcons  = ['especes'=>'bx-money','virement'=>'bx-transfer','mobile_money'=>'bx-mobile','cheque'=>'bx-file','carte'=>'bx-credit-card'];
      $methodColors = ['especes'=>'#10b981','virement'=>'#6366f1','mobile_money'=>'#f59e0b','cheque'=>'#06b6d4','carte'=>'#ec4899'];
      foreach ($byMethod as $m):
        $pct = $totalRevenue > 0 ? round(($m['total']/$totalRevenue)*100) : 0;
      ?>
      <div style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary)">
            <i class="bx <?= $methodIcons[$m['payment_method']] ?? 'bx-wallet' ?>" style="color:<?= $methodColors[$m['payment_method']] ?? '#6366f1' ?>"></i>
            <?= ucfirst(str_replace('_',' ',$m['payment_method'])) ?>
          </span>
          <span style="font-size:13px;font-weight:700;color:var(--text-primary)"><?= formatMoney($m['total']) ?></span>
        </div>
        <div class="progress">
          <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $methodColors[$m['payment_method']] ?? '#6366f1' ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top classes -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-trophy"></i> Classes les plus contributrices</h3>
    </div>
    <div class="card-body" style="padding:0">
      <table class="table">
        <tbody>
          <?php foreach ($byClass as $i => $c): ?>
          <tr>
            <td style="width:32px">
              <div style="width:26px;height:26px;border-radius:50%;background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">
                <?= $i+1 ?>
              </div>
            </td>
            <td style="font-weight:600;color:var(--text-primary)"><?= clean($c['name']) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= $c['nb'] ?> paiements</td>
            <td style="text-align:right;font-weight:700;color:var(--success)"><?= formatMoney($c['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- IMPAYES -->
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-error-circle" style="color:var(--warning)"></i> Paiements partiels recents</h3>
    <a href="<?= BASE_URL ?>/admin/payments.php?status=partiel" class="btn btn-sm btn-secondary">Voir tout</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Eleve</th>
          <th>Classe</th>
          <th>Frais</th>
          <th>Montant attendu</th>
          <th>Montant paye</th>
          <th>Reste a payer</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($unpaidStudents)): ?>
          <tr><td colspan="6">
            <div class="empty-state" style="padding:32px">
              <div class="empty-state-icon"><i class="bx bx-check-circle"></i></div>
              <h3>Aucun impaye</h3>
              <p>Tous les paiements sont a jour !</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($unpaidStudents as $u):
            $remaining = $u['fee_amount'] - $u['amount_paid'];
          ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:var(--warning)">
                  <?= getInitials($u['first_name'], $u['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($u['first_name']) ?> <?= clean($u['last_name']) ?></div>
                  <div class="td-sub"><?= clean($u['student_number']) ?></div>
                </div>
              </div>
            </td>
            <td><?= clean($u['class_name'] ?? '—') ?></td>
            <td style="color:var(--text-muted)"><?= clean($u['fee_name']) ?></td>
            <td><?= formatMoney($u['fee_amount']) ?></td>
            <td style="color:var(--success);font-weight:600"><?= formatMoney($u['amount_paid']) ?></td>
            <td style="color:var(--danger);font-weight:700"><?= formatMoney($remaining) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>
</div>

<?php
$pageScript = "
document.addEventListener('DOMContentLoaded', function() {
  SS_Charts.line('chartMonthly', $monthLabels,
    [{ label: 'Revenus', data: $monthValues, color: '#10b981', fill: true }]
  );

  SS_Charts.donut('chartCategory', $catLabels, $catValues,
    ['#6366f1','#8b5cf6','#3b82f6','#10b981','#f59e0b','#ec4899']
  );
});
";
require_once INCLUDES_PATH . '/footer.php';
?>
