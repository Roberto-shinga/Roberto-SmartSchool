<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Mon emploi du temps';
$pageSection = 'timetable';
$studentId   = getCurrentStudentId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$student     = dbFetchOne("SELECT class_id FROM students WHERE id=?", [$studentId]);

$slots = $student['class_id'] ?? null ? dbFetchAll(
    "SELECT tt.*, sub.name AS subject_name, sub.color, tu.first_name AS t_fn, tu.last_name AS t_ln
     FROM timetable tt
     JOIN teacher_assignments ta ON tt.assignment_id = ta.id
     JOIN subjects sub ON ta.subject_id = sub.id
     JOIN teachers te ON ta.teacher_id = te.id
     JOIN users tu ON te.user_id = tu.id
     WHERE ta.class_id = ? AND tt.academic_year_id = ?
     ORDER BY tt.day_of_week, tt.start_time",
    [$student['class_id'], $yearId]
) : [];

$slotsByDay = [];
foreach ($slots as $s) $slotsByDay[(int)$s['day_of_week']][] = $s;

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header"><div><h1>Mon emploi du temps</h1><p>Annee <?= clean($currentYear['name'] ?? '—') ?></p></div></div>

    <?php if (empty($slots)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-calendar"></i></div><h3>Emploi du temps non disponible</h3></div>
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
              <div class="text-xs text-muted"><?= clean($s['t_fn'] . ' ' . $s['t_ln']) ?><?= $s['room'] ? ' · ' . clean($s['room']) : '' ?></div>
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
