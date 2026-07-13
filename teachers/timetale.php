<?php
// ============================================================
//  SmartSchool — Mon emploi du temps (Enseignant)
//  Emplacement : teachers/timetable.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_TEACHER);

$pageTitle   = 'Mon emploi du temps';
$pageSection = 'timetable';
$user        = currentUser();

$teacher   = dbFetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user['id']]);
$teacherId = $teacher['id'] ?? 0;
$yearId    = dbFetchOne("SELECT id FROM academic_years WHERE is_current=1")['id'] ?? 1;

$timetableData = [];
$rows = dbFetchAll(
    "SELECT tt.id, tt.day_of_week, tt.start_time, tt.end_time, tt.room,
            sub.name AS subject_name, sub.color,
            c.name AS class_name,
            (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id AND s.status='actif') AS student_count
     FROM timetable tt
     JOIN class_subjects cs ON tt.class_subject_id = cs.id
     JOIN subjects sub ON cs.subject_id = sub.id
     JOIN classes c ON cs.class_id = c.id
     WHERE cs.teacher_id = ?
     ORDER BY tt.day_of_week, tt.start_time",
    [$teacherId]
);
foreach ($rows as $r) {
    $timetableData[$r['day_of_week']][] = $r;
}

$days = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi'];
$today = date('N');

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-calendar" style="color:var(--primary)"></i> Mon emploi du temps
    </h1>
    <p><?= count($rows) ?> creneau<?= count($rows)>1?'x':'' ?> au total</p>
  </div>
</div>

<!-- Stats rapides -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-bottom:20px">
  <?php foreach ($days as $num => $name):
    $daySlots = $timetableData[$num] ?? [];
    $isToday  = $num == $today;
  ?>
  <div class="card" style="<?= $isToday?'border-color:var(--primary);background:var(--primary-bg)':'' ?>">
    <div class="card-body" style="padding:14px;text-align:center">
      <div style="font-size:12px;font-weight:700;color:<?= $isToday?'var(--primary)':'var(--text-muted)' ?>;margin-bottom:6px">
        <?= $name ?> <?= $isToday?'(auj.)':'' ?>
      </div>
      <div style="font-size:22px;font-weight:800;color:<?= $isToday?'var(--primary)':'var(--text-primary)' ?>">
        <?= count($daySlots) ?>
      </div>
      <div style="font-size:11px;color:var(--text-muted)">cours</div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Grille -->
<div class="card">
  <div style="overflow-x:auto">
    <div style="display:grid;grid-template-columns:repeat(6,minmax(160px,1fr));gap:1px;background:var(--border);min-width:960px">
      <?php foreach ($days as $dayNum => $dayName): ?>
      <div>
        <div style="background:<?= $dayNum==$today?'var(--primary-bg)':'var(--bg-hover)' ?>;padding:12px;text-align:center;font-weight:700;font-size:13px;color:<?= $dayNum==$today?'var(--primary)':'var(--text-primary)' ?>">
          <?= $dayName ?><?= $dayNum==$today?' ●':'' ?>
        </div>
        <div style="background:var(--bg-card);min-height:400px;padding:8px;display:flex;flex-direction:column;gap:8px">
          <?php
          $slots = $timetableData[$dayNum] ?? [];
          usort($slots, fn($a,$b) => strcmp($a['start_time'],$b['start_time']));
          if (empty($slots)):
          ?>
            <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:12px">Libre</div>
          <?php else: ?>
            <?php foreach ($slots as $slot): ?>
            <div style="background:<?= clean($slot['color']) ?>12;border-left:3px solid <?= clean($slot['color']) ?>;border-radius:var(--radius-sm);padding:10px">
              <div style="font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:4px">
                <?= clean($slot['subject_name']) ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px">
                <i class="bx bx-building"></i> <?= clean($slot['class_name']) ?> (<?= $slot['student_count'] ?> el.)
              </div>
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px">
                <i class="bx bx-time"></i> <?= substr($slot['start_time'],0,5) ?> – <?= substr($slot['end_time'],0,5) ?>
              </div>
              <?php if ($slot['room']): ?>
              <div style="font-size:11px;color:var(--text-muted)">
                <i class="bx bx-map-pin"></i> <?= clean($slot['room']) ?>
              </div>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/teachers/attendance.php?class_id=<?= $slot['id'] ?>"
                 style="display:inline-flex;align-items:center;gap:4px;margin-top:6px;font-size:11px;color:var(--primary);font-weight:600;text-decoration:none">
                <i class="bx bx-check-square"></i> Faire l'appel
              </a>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>