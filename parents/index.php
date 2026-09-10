<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireParent();

$pageTitle   = 'Tableau de bord';
$pageSection = 'dashboard';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$currentTerm = dbFetchOne("SELECT * FROM terms WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

$children = getParentChildren($user['id']);

// Pour chaque enfant : moyenne du trimestre courant (si publiee), presence
// 30 jours, solde restant. Regroupe en quelques requetes, pas de N+1 lourd
// vu le petit nombre d'enfants par parent.
$childrenData = [];
foreach ($children as $c) {
    $report = $currentTerm ? dbFetchOne(
        "SELECT * FROM report_cards WHERE student_id=? AND term_id=? AND is_published=1",
        [$c['id'], $currentTerm['id']]
    ) : null;

    $att = dbFetchOne(
        "SELECT COUNT(*) total, SUM(status='present') present FROM attendance WHERE student_id=? AND date >= CURDATE() - INTERVAL 30 DAY",
        [$c['id']]
    );
    $attRate = ($att && $att['total'] > 0) ? round(($att['present'] / $att['total']) * 100) : null;

    $totalDue = dbFetchOne(
        "SELECT COALESCE(SUM(f.amount),0) t FROM fees f
         WHERE f.academic_year_id=? AND (f.class_id=? OR (f.class_id IS NULL AND (f.level_id IS NULL OR f.level_id=(SELECT level_id FROM students WHERE id=?))))",
        [$yearId, $c['class_id'], $c['id']]
    )['t'] ?? 0;
    $totalPaid = dbFetchOne(
        "SELECT COALESCE(SUM(amount_paid),0) t FROM payments WHERE student_id=? AND status IN ('paye','partiel')",
        [$c['id']]
    )['t'] ?? 0;

    $childrenData[] = array_merge($c, [
        'report' => $report, 'att_rate' => $attRate,
        'balance' => max(0, $totalDue - $totalPaid),
    ]);
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Bonjour, <?= clean($user['first_name']) ?> 👋</h1>
        <p>Suivi de <?= count($children) ?> enfant(s) — <?= clean($currentYear['name'] ?? '—') ?></p>
      </div>
    </div>

    <?php if (empty($children)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-group"></i></div>
        <h3>Aucun enfant lie a ton compte</h3>
        <p>Contacte l'administration de l'etablissement pour lier le profil de ton/tes enfant(s) a ce compte.</p>
      </div>
    <?php else: ?>

    <div class="grid-2">
      <?php foreach ($childrenData as $c): $mention = $c['report'] ? getMention((float)$c['report']['average']) : null; ?>
      <div class="card animate-in">
        <div class="card-header">
          <h3><i class="bx bx-user"></i> <?= clean($c['fn'] . ' ' . $c['ln']) ?></h3>
          <span class="badge badge-primary"><?= clean($c['class_name'] ?: '—') ?></span>
        </div>
        <div class="card-body">
          <div class="grid-3">
            <div>
              <div class="text-xs text-muted">Moyenne</div>
              <div class="font-semibold" style="color:<?= $mention['color'] ?? 'var(--text-primary)' ?>">
                <?= $c['report'] ? number_format((float)$c['report']['average'],1) . '/20' : '—' ?>
              </div>
            </div>
            <div>
              <div class="text-xs text-muted">Presence (30j)</div>
              <div class="font-semibold"><?= $c['att_rate'] !== null ? $c['att_rate'] . '%' : '—' ?></div>
            </div>
            <div>
              <div class="text-xs text-muted">Solde du</div>
              <div class="font-semibold" style="color:<?= $c['balance'] > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= formatMoney($c['balance']) ?></div>
            </div>
          </div>
          <div style="margin-top:16px;display:flex;gap:8px">
            <a href="<?= BASE_URL ?>/parents/grades.php?student_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1">Notes</a>
            <a href="<?= BASE_URL ?>/parents/attendance.php?student_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1">Presences</a>
            <a href="<?= BASE_URL ?>/parents/payments.php?student_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1">Paiements</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
