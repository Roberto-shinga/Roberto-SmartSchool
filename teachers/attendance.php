<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Presences';
$pageSection = 'attendance';
$user        = currentUser();
$teacherId   = getCurrentTeacherId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$myClasses   = $teacherId ? getTeacherClasses($teacherId, $yearId) : [];

$classId = (int)($_GET['class_id'] ?? 0);
$date    = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

// ── GARDE-FOU : la classe doit etre l'une des siennes ──────────
if ($classId && (!$teacherId || !teacherOwnsClass($teacherId, $classId, $yearId))) {
    redirectWith($_SERVER['PHP_SELF'], 'danger', "Tu n'as pas acces a cette classe.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postClassId = (int)($_POST['class_id'] ?? 0);
    $postDate    = $_POST['date'] ?? date('Y-m-d');
    $statuses    = $_POST['status'] ?? [];

    if (!$teacherId || !teacherOwnsClass($teacherId, $postClassId, $yearId)) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', "Action refusee : cette classe ne t'est pas attribuee.");
    } else {
        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            if (!in_array($status, ['present','absent','retard','excuse'])) continue;
            // L'eleve doit appartenir a cette classe precise
            if (!dbFetchOne("SELECT 1 FROM students WHERE id=? AND class_id=?", [$studentId, $postClassId])) continue;
            dbExecute(
                "INSERT INTO attendance (student_id, class_id, date, status, recorded_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_by = VALUES(recorded_by)",
                [$studentId, $postClassId, $postDate, $status, $user['id']]
            );
        }
        logActivity('attendance_recorded', "Classe #$postClassId, $postDate (enseignant)");
        redirectWith($_SERVER['PHP_SELF'] . "?class_id=$postClassId&date=$postDate", 'success', 'Presences enregistrees.');
    }
}

$students = [];
$stats    = ['present' => 0, 'absent' => 0, 'retard' => 0, 'excuse' => 0];
if ($classId) {
    $students = dbFetchAll(
        "SELECT s.id, s.student_number, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln, a.status AS current_status
         FROM students s LEFT JOIN users u ON s.user_id=u.id
         LEFT JOIN attendance a ON a.student_id=s.id AND a.class_id=? AND a.date=?
         WHERE s.class_id=? AND s.status='actif' ORDER BY fn, ln",
        [$classId, $date, $classId]
    );
    foreach ($students as $s) { $st = $s['current_status'] ?: 'present'; $stats[$st] = ($stats[$st] ?? 0) + 1; }
}

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
      <div><h1>Presences</h1><p>Appel limite a tes classes attribuees</p></div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-4" style="align-items:end">
          <div class="form-group" style="margin-bottom:0;grid-column:span 2">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner...</option>
              <?php foreach ($myClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= clean($date) ?>" onchange="this.form.submit()"></div>
        </form>
      </div>
    </div>

    <?php if (empty($myClasses)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-error"></i></div><h3>Aucune classe attribuee</h3></div>
    <?php elseif (!$classId): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-check-square"></i></div><h3>Selectionne une classe</h3></div>
    <?php elseif (empty($students)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-user-x"></i></div><h3>Aucun eleve actif</h3></div>
    <?php else: ?>

    <div class="grid-4 mb-6">
      <?php foreach ($statusMeta as $key => [$color, $icon, $label]): ?>
      <div class="stat-card c-<?= $color ?>"><div class="stat-icon c-<?= $color ?>"><i class="bx <?= $icon ?>"></i></div><div><div class="stat-value"><?= $stats[$key] ?></div><div class="stat-label"><?= $label ?></div></div></div>
      <?php endforeach; ?>
    </div>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <input type="hidden" name="date" value="<?= clean($date) ?>">
      <div class="card">
        <div class="card-header">
          <h3><i class="bx bx-list-check"></i> Appel du <?= formatDate($date) ?></h3>
          <button type="button" class="btn btn-ghost btn-sm" onclick="markAll('present')">Tous presents</button>
        </div>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table">
            <thead><tr><th>Eleve</th><th>Matricule</th><th>Statut</th></tr></thead>
            <tbody>
              <?php foreach ($students as $s): $cur = $s['current_status'] ?: 'present'; ?>
              <tr>
                <td class="td-user"><div class="avatar avatar-32" style="background:var(--cyan)"><?= getInitials($s['fn'], $s['ln']) ?></div><div class="td-name"><?= clean($s['fn'] . ' ' . $s['ln']) ?></div></td>
                <td class="text-sm font-mono"><?= clean($s['student_number']) ?></td>
                <td>
                  <div class="att-toggle" style="display:flex;gap:6px">
                    <?php foreach ($statusMeta as $key => [$color, $icon, $label]): ?>
                    <label style="cursor:pointer">
                      <input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $key ?>" <?= $cur===$key?'checked':'' ?> style="display:none" class="att-radio">
                      <span class="badge badge-<?= $cur===$key?$color:'gray' ?> att-badge" style="cursor:pointer;<?= $cur===$key?'':'opacity:.45' ?>" title="<?= $label ?>"><i class="bx <?= $icon ?>"></i> <?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border-light)">
          <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer les presences</button>
        </div>
      </div>
    </form>
    <?php endif; ?>

  </div>
</div>

<script>
document.querySelectorAll('.att-toggle').forEach(group => {
  group.querySelectorAll('label').forEach(label => {
    label.addEventListener('click', () => {
      const radio = label.querySelector('.att-radio'); radio.checked = true;
      group.querySelectorAll('.att-badge').forEach(b => { b.style.opacity = '.45'; b.className = b.className.replace(/badge-\w+/, 'badge-gray'); });
      const badge = label.querySelector('.att-badge'); badge.style.opacity = '1';
      const colors = {present:'success', absent:'danger', retard:'warning', excuse:'info'};
      badge.className = badge.className.replace(/badge-\w+/, 'badge-' + colors[radio.value]);
    });
  });
});
function markAll(status) { document.querySelectorAll(`.att-radio[value="${status}"]`).forEach(r => r.closest('label').click()); }
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
