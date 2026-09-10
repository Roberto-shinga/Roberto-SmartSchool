<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Mes badges';
$pageSection = 'badges';
$studentId   = getCurrentStudentId();

$allBadges = dbFetchAll("SELECT * FROM badges ORDER BY points");
$earned    = dbFetchAll("SELECT badge_id, earned_at FROM student_badges WHERE student_id=?", [$studentId]);
$earnedMap = [];
foreach ($earned as $e) $earnedMap[$e['badge_id']] = $e['earned_at'];

$totalPoints = 0;
foreach ($allBadges as $b) if (isset($earnedMap[$b['id']])) $totalPoints += (int)$b['points'];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="hero-banner animate-in">
      <div class="hero-content">
        <div class="hero-title">Mes badges</div>
        <div class="hero-sub"><?= count($earnedMap) ?> badge(s) sur <?= count($allBadges) ?> obtenu(s)</div>
        <div class="hero-stats">
          <div><div class="hero-stat-val"><?= $totalPoints ?></div><div class="hero-stat-lbl">Points cumules</div></div>
        </div>
      </div>
      <div class="hero-icon"><i class="bx bx-award"></i></div>
    </div>

    <div class="grid-3">
      <?php foreach ($allBadges as $b): $isEarned = isset($earnedMap[$b['id']]); ?>
      <div class="card" style="<?= $isEarned ? '' : 'opacity:.55' ?>">
        <div class="card-body" style="text-align:center;padding:28px 20px">
          <div style="width:64px;height:64px;border-radius:50%;background:<?= $isEarned ? clean($b['color']) : 'var(--border)' ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.8rem;color:#fff">
            <i class="bx <?= clean($b['icon']) ?>"></i>
          </div>
          <div style="font-weight:700;color:var(--text-primary);margin-bottom:6px"><?= clean($b['name']) ?></div>
          <div class="text-sm text-muted" style="margin-bottom:10px"><?= clean($b['description']) ?></div>
          <?php if ($isEarned): ?>
            <span class="badge badge-success"><?= (int)$b['points'] ?> points - obtenu le <?= formatDate($earnedMap[$b['id']]) ?></span>
          <?php else: ?>
            <span class="badge badge-gray"><i class="bx bx-lock-alt"></i> Non debloque</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
