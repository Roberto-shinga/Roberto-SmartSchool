<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Mes eleves';
$pageSection = 'students';
$teacherId   = getCurrentTeacherId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$myClasses   = $teacherId ? getTeacherClasses($teacherId, $yearId) : [];
$classIds    = array_column($myClasses, 'id');

$classFlt = (int)($_GET['class_id'] ?? 0);
// GARDE-FOU : si un class_id est fourni, il doit faire partie de ses classes
if ($classFlt && !in_array($classFlt, $classIds)) {
    redirectWith($_SERVER['PHP_SELF'], 'danger', "Tu n'as pas acces a cette classe.");
}

$students = [];
if (!empty($classIds)) {
    $targetIds = $classFlt ? [$classFlt] : $classIds;
    $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
    $students = dbFetchAll(
        "SELECT s.*, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln, u.email, c.name AS class_name
         FROM students s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id
         WHERE s.class_id IN ($placeholders) AND s.status='actif'
         ORDER BY c.grade_year, c.section, fn, ln",
        $targetIds
    );
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mes eleves</h1><p><?= count($students) ?> eleve(s) dans tes classes</p></div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" style="max-width:320px">
          <select name="class_id" class="form-control" onchange="this.form.submit()">
            <option value="0">Toutes mes classes</option>
            <?php foreach ($myClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $classFlt===$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if (empty($students)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-user-x"></i></div><h3>Aucun eleve</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Matricule</th><th>Classe</th><th>Genre</th></tr></thead>
          <tbody>
            <?php foreach ($students as $s): ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--success)"><?= getInitials($s['fn'], $s['ln']) ?></div>
                <div><div class="td-name"><?= clean($s['fn'] . ' ' . $s['ln']) ?></div><?php if($s['email']): ?><div class="td-sub"><?= clean($s['email']) ?></div><?php endif; ?></div>
              </td>
              <td class="text-sm font-mono"><?= clean($s['student_number']) ?></td>
              <td class="text-sm text-muted"><?= clean($s['class_name']) ?></td>
              <td class="text-sm text-muted"><?= $s['gender'] === 'F' ? 'Feminin' : 'Masculin' ?></td>
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
