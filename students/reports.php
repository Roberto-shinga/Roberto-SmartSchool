<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Mes bulletins';
$pageSection = 'reports';
$studentId   = getCurrentStudentId();

// Uniquement les bulletins publies : un brouillon administratif
// ne doit jamais etre visible par l'eleve avant publication officielle.
$reports = dbFetchAll(
    "SELECT rc.*, t.name AS term_name
     FROM report_cards rc JOIN terms t ON rc.term_id = t.id
     WHERE rc.student_id = ? AND rc.is_published = 1
     ORDER BY t.start_date DESC",
    [$studentId]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mes bulletins</h1><p>Bulletins publies par l'administration</p></div>
    </div>

    <?php if (empty($reports)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-file"></i></div>
        <h3>Aucun bulletin publie</h3>
        <p>Ton bulletin apparaitra ici des que l'administration l'aura publie.</p>
      </div>
    <?php else: foreach ($reports as $r): $mention = getMention((float)$r['average']); ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><h3><i class="bx bx-file"></i> <?= clean($r['term_name']) ?></h3></div>
      <div class="card-body">
        <div class="grid-3 mb-6">
          <div class="stat-card c-primary"><div class="stat-icon c-primary"><i class="bx bx-star"></i></div><div><div class="stat-value" style="color:<?= $mention['color'] ?>"><?= number_format((float)$r['average'],2) ?>/20</div><div class="stat-label"><?= $mention['label'] ?></div></div></div>
          <div class="stat-card c-purple"><div class="stat-icon c-purple"><i class="bx bx-medal"></i></div><div><div class="stat-value">#<?= $r['rank'] ?></div><div class="stat-label">sur <?= $r['total_students'] ?> eleves</div></div></div>
          <div class="stat-card c-cyan"><div class="stat-icon c-cyan"><i class="bx bx-group"></i></div><div><div class="stat-value"><?= number_format((float)$r['class_average'],1) ?>/20</div><div class="stat-label">Moyenne de la classe</div></div></div>
        </div>
        <div class="grid-2">
          <div><div class="text-xs text-muted" style="margin-bottom:4px">Conduite</div><div class="text-sm font-semibold"><?= clean($r['conduct'] ?: 'Non evaluee') ?></div></div>
        </div>
        <?php if ($r['teacher_comment']): ?>
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light)">
            <div class="text-xs text-muted" style="margin-bottom:4px">Appreciation du titulaire</div>
            <div class="text-sm"><?= nl2br(clean($r['teacher_comment'])) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($r['admin_comment']): ?>
          <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light)">
            <div class="text-xs text-muted" style="margin-bottom:4px">Note de l'administration</div>
            <div class="text-sm"><?= nl2br(clean($r['admin_comment'])) ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
