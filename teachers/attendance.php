<?php
// ============================================================
//  SmartSchool — Appel (Enseignant)
//  Emplacement : teachers/attendance.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_TEACHER);

$pageTitle   = 'Faire l\'appel';
$pageSection = 'attendance';
$user        = currentUser();

$teacher = dbFetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user['id']]);
$teacherId = $teacher['id'] ?? 0;

$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;

// Mes classes uniquement
$myClasses = dbFetchAll(
    "SELECT DISTINCT c.id, c.name
     FROM class_subjects cs
     JOIN classes c ON cs.class_id = c.id
     WHERE cs.teacher_id = ? AND cs.academic_year_id = ?
     ORDER BY c.name",
    [$teacherId, $yearId]
);

// Traitement identique au module admin — on inclut la logique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_attendance') {
        $classId  = (int)($_POST['class_id'] ?? 0);
        $date     = trim($_POST['date']      ?? date('Y-m-d'));
        $statuses = $_POST['statuses']       ?? [];
        $reasons  = $_POST['reasons']        ?? [];

        // Verifier que cette classe appartient bien a cet enseignant
        $allowed = array_column($myClasses, 'id');
        if (!in_array($classId, $allowed)) {
            redirectWith(BASE_URL . '/teachers/attendance.php', 'danger', 'Acces non autorise.');
        }

        $saved = 0;
        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            $reason    = trim($reasons[$studentId] ?? '');
            $existing  = dbFetchOne(
                "SELECT id FROM attendance WHERE student_id=? AND class_id=? AND date=?",
                [$studentId, $classId, $date]
            );
            if ($existing) {
                dbExecute("UPDATE attendance SET status=?, reason=?, recorded_by=? WHERE id=?",
                    [$status, $reason, $user['id'], $existing['id']]);
            } else {
                dbExecute("INSERT INTO attendance (student_id, class_id, date, status, reason, recorded_by) VALUES (?,?,?,?,?,?)",
                    [$studentId, $classId, $date, $status, $reason, $user['id']]);
            }
            $saved++;
        }
        logActivity('save_attendance', "Appel enregistre par enseignant $teacherId — $saved eleves");
        redirectWith(BASE_URL . '/teachers/attendance.php?class_id='.$classId.'&date='.$date,
            'success', "Appel enregistre pour $saved eleve(s) !");
    }
}

$selectedClass = (int)($_GET['class_id'] ?? ($myClasses[0]['id'] ?? 0));
$selectedDate  = trim($_GET['date'] ?? date('Y-m-d'));

$classStudents = [];
$attendanceMap = [];

if ($selectedClass) {
    $classStudents = dbFetchAll(
        "SELECT s.id, u.first_name, u.last_name, u.gender, s.student_number
         FROM students s JOIN users u ON s.user_id = u.id
         WHERE s.class_id = ? AND s.status='actif'
         ORDER BY u.last_name, u.first_name",
        [$selectedClass]
    );
    if (!empty($classStudents)) {
        $ids = implode(',', array_column($classStudents, 'id'));
        $existing = dbFetchAll(
            "SELECT student_id, status, reason FROM attendance
             WHERE class_id=? AND date=? AND student_id IN ($ids)",
            [$selectedClass, $selectedDate]
        );
        foreach ($existing as $e) $attendanceMap[$e['student_id']] = $e;
    }
}

$selectedClassName = '';
foreach ($myClasses as $c) { if ($c['id'] == $selectedClass) { $selectedClassName = $c['name']; break; } }

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
      <i class="bx bx-check-square" style="color:var(--primary)"></i> Faire l'appel
    </h1>
    <p>Enregistrez les presences de vos eleves</p>
  </div>
</div>

<!-- Selectionner classe et date -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label">Ma classe</label>
        <select name="class_id" class="form-control">
          <?php foreach ($myClasses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $selectedClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control"
               value="<?= clean($selectedDate) ?>" max="<?= date('Y-m-d') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-search"></i> Charger
      </button>
    </form>
  </div>
</div>

<?php if ($selectedClass && !empty($classStudents)): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-group"></i> <?= clean($selectedClassName) ?> — <?= formatDate($selectedDate) ?></h3>
    <button class="btn btn-sm btn-secondary" onclick="markAll('present')">
      <i class="bx bx-check-double"></i> Tous presents
    </button>
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action"   value="save_attendance">
    <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
    <input type="hidden" name="date"     value="<?= clean($selectedDate) ?>">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Eleve</th>
            <th style="text-align:center">Present</th>
            <th style="text-align:center">Absent</th>
            <th style="text-align:center">Retard</th>
            <th style="text-align:center">Excuse</th>
            <th>Motif</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classStudents as $i => $s):
            $att    = $attendanceMap[$s['id']] ?? null;
            $status = $att['status'] ?? 'present';
            $reason = $att['reason'] ?? '';
            $colors = ['present'=>'rgba(16,185,129,0.06)','absent'=>'rgba(239,68,68,0.06)','retard'=>'rgba(245,158,11,0.06)','excuse'=>'rgba(99,102,241,0.06)'];
          ?>
          <tr id="row-<?= $s['id'] ?>" style="background:<?= $colors[$status] ?? '' ?>">
            <td style="color:var(--text-muted);font-size:12px"><?= $i+1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:<?= $s['gender']==='F'?'#ec4899':'var(--primary)' ?>">
                  <?= getInitials($s['first_name'], $s['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                  <div class="td-sub"><?= clean($s['student_number']) ?></div>
                </div>
              </div>
            </td>
            <?php foreach (['present'=>'#10b981','absent'=>'#ef4444','retard'=>'#f59e0b','excuse'=>'#6366f1'] as $opt => $clr): ?>
            <td style="text-align:center">
              <label style="cursor:pointer;display:flex;justify-content:center">
                <input type="radio" name="statuses[<?= $s['id'] ?>]" value="<?= $opt ?>"
                       <?= $status===$opt?'checked':'' ?>
                       onchange="handleChange(<?= $s['id'] ?>, '<?= $opt ?>')"
                       style="width:18px;height:18px;accent-color:<?= $clr ?>;cursor:pointer">
              </label>
            </td>
            <?php endforeach; ?>
            <td>
              <input type="text" name="reasons[<?= $s['id'] ?>]" id="reason-<?= $s['id'] ?>"
                     class="form-control" placeholder="Motif..."
                     value="<?= clean($reason) ?>"
                     style="font-size:12.5px;padding:6px 10px;<?= in_array($status,['absent','excuse'])?'':'display:none' ?>">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:13px;color:var(--text-muted)"><?= count($classStudents) ?> eleve(s)</span>
      <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer l'appel</button>
    </div>
  </form>
</div>
<?php elseif ($selectedClass): ?>
<div class="card"><div class="empty-state">
  <div class="empty-state-icon"><i class="bx bx-user-x"></i></div>
  <h3>Aucun eleve dans cette classe</h3>
</div></div>
<?php endif; ?>

</div>
</div>
</div>

<?php
$pageScript = "
function handleChange(sid, status) {
  const r = document.getElementById('reason-'+sid);
  const row = document.getElementById('row-'+sid);
  const colors = {present:'rgba(16,185,129,0.06)',absent:'rgba(239,68,68,0.06)',retard:'rgba(245,158,11,0.06)',excuse:'rgba(99,102,241,0.06)'};
  if (r) r.style.display = (status==='absent'||status==='excuse') ? '' : 'none';
  if (row) row.style.background = colors[status] || '';
}
function markAll(status) {
  document.querySelectorAll('input[type=radio][value='+status+']').forEach(r => {
    r.checked = true;
    const sid = r.name.match(/\d+/)?.[0];
    if (sid) handleChange(parseInt(sid), status);
  });
}
document.querySelectorAll('input[type=radio]:checked').forEach(r => {
  const sid = r.name.match(/\d+/)?.[0];
  if (sid) handleChange(parseInt(sid), r.value);
});
";
require_once INCLUDES_PATH . '/footer.php';
?>