<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ACCOUNTANT);

$pageTitle   = 'Tableau de bord';
$pageSection = 'dashboard';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

$totalToday = dbFetchOne(
    "SELECT COALESCE(SUM(amount_paid),0) t FROM payments WHERE status IN ('paye','partiel') AND DATE(payment_date)=CURDATE()"
)['t'] ?? 0;

$totalMonth = dbFetchOne(
    "SELECT COALESCE(SUM(amount_paid),0) t FROM payments
     WHERE status IN ('paye','partiel') AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())"
)['t'] ?? 0;

$pendingProofs = dbCount('payment_proofs', "status='en_attente'");
$nbPaymentsToday = dbCount('payments', "DATE(payment_date)=CURDATE() AND status IN ('paye','partiel')");

$recentPayments = dbFetchAll(
    "SELECT p.*, s.student_number, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln, fc.name AS category_name
     FROM payments p
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     JOIN fees f ON p.fee_id = f.id
     JOIN fee_categories fc ON f.category_id = fc.id
     ORDER BY p.payment_date DESC LIMIT 8"
);
$statusBadge = ['paye'=>'success','partiel'=>'warning','en_attente'=>'gray','rejete'=>'danger','annule'=>'danger'];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="hero-banner animate-in">
      <div class="hero-content">
        <div class="hero-title">Bonjour, <?= clean($user['first_name']) ?> 👋</div>
        <div class="hero-sub">Espace Comptable — <?= clean($currentYear['name'] ?? '—') ?></div>
        <div class="hero-stats">
          <div><div class="hero-stat-val"><?= formatMoney($totalToday) ?></div><div class="hero-stat-lbl">Encaisse aujourd'hui</div></div>
          <div><div class="hero-stat-val"><?= $nbPaymentsToday ?></div><div class="hero-stat-lbl">Paiement(s) aujourd'hui</div></div>
          <div><div class="hero-stat-val"><?= $pendingProofs ?></div><div class="hero-stat-lbl">Preuve(s) a valider</div></div>
        </div>
      </div>
      <div class="hero-icon"><i class="bx bx-wallet"></i></div>
    </div>

    <div class="grid-3 mb-6">
      <div class="stat-card c-success"><div class="stat-icon c-success"><i class="bx bx-money"></i></div><div><div class="stat-value" style="font-size:18px"><?= formatMoney($totalMonth) ?></div><div class="stat-label">Encaisse ce mois</div></div></div>
      <div class="stat-card c-warning"><div class="stat-icon c-warning"><i class="bx bx-file-blank"></i></div><div><div class="stat-value"><?= $pendingProofs ?></div><div class="stat-label">Preuves en attente</div></div></div>
      <div class="stat-card c-primary"><div class="stat-icon c-primary"><i class="bx bx-receipt"></i></div><div><div class="stat-value"><?= $nbPaymentsToday ?></div><div class="stat-label">Paiements aujourd'hui</div></div></div>
    </div>

    <?php if ($pendingProofs > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:20px">
      <i class="bx bx-error-circle"></i>
      <div><?= $pendingProofs ?> preuve(s) de paiement en attente de validation.
      <a href="<?= BASE_URL ?>/accountant/proofs.php" style="font-weight:700">Voir les preuves →</a></div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-history"></i> Paiements recents</h3>
        <a href="<?= BASE_URL ?>/accountant/payments.php" class="btn btn-ghost btn-sm">Tout voir</a>
      </div>
      <?php if (empty($recentPayments)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-receipt"></i></div><h3>Aucun paiement enregistre</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Recu</th><th>Categorie</th><th>Montant</th><th>Statut</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($recentPayments as $p): ?>
            <tr>
              <td class="text-sm"><?= clean($p['fn'] . ' ' . $p['ln']) ?><div class="text-xs text-muted"><?= clean($p['student_number']) ?></div></td>
              <td class="text-sm font-mono"><?= clean($p['receipt_number']) ?></td>
              <td class="text-sm text-muted"><?= clean($p['category_name']) ?></td>
              <td class="font-semibold"><?= formatMoney($p['amount_paid']) ?></td>
              <td><span class="badge badge-<?= $statusBadge[$p['status']] ?? 'gray' ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
              <td class="text-sm text-muted"><?= formatDateTime($p['payment_date']) ?></td>
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
