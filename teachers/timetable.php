<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Mon emploi du temps';
$pageSection = 'timetable';
$teacherId   = getCurrentTeacherId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

$slots = $teacherId ? dbFetchAll(
    "SELECT tt.*, c.name AS class_name, s.name AS subject_name, s.color
     FROM timetable tt
     JOIN teacher_assignments ta ON tt.assignment_id = ta.id
     JOIN classes c ON ta.class_id = c.id
     JOIN subjects s ON ta.subject_id = s.id
     WHERE ta.teacher_id = ? AND tt.academic_year_id = ?
     ORDER BY tt.day_of_week, tt.start_time",
    [$teacherId, $yearId]
) : [];

$slotsByDay = [];
foreach ($slots as $s) $slotsByDay[(int)$s['day_of_week']][] = $s;

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mon emploi du temps</h1><p>Annee <?= clean($currentYear['name'] ?? '—') ?></p></div>
    </div>

    <?php if (empty($slots)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-calendar"></i></div><h3>Aucun creneau planifie</h3><p>L'administration n'a pas encore renseigne ton emploi du temps.</p></div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach (DAYS_OF_WEEK as $dayNum => $dayLabel): ?>
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-calendar-alt"></i> <?= $dayLabel ?></h3></div>
        <div class="card-body" style="padding:12px">
          <?php if (empty($slotsByDay[$dayNum])): ?>
            <p class="text-xs text-muted" style="padding:8px">Aucun cours</p>
          <?php else: foreach ($slotsByDay[$dayNum] as $s): ?>
            <div style="border-left:3px solid <?= clean($s['color']) ?>;padding:8px 10px;margin-bottom:8px;background:var(--bg-hover);border-radius:6px">
              <div class="text-sm font-semibold"><?= clean($s['subject_name']) ?></div>
              <div class="text-xs text-muted"><?= substr($s['start_time'],0,5) ?> - <?= substr($s['end_time'],0,5) ?></div>
              <div class="text-xs text-muted"><?= clean($s['class_name']) ?><?= $s['room'] ? ' · ' . clean($s['room']) : '' ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
