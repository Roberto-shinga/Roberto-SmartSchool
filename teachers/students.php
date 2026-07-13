<?php
// ============================================================
//  SmartSchool — Mes eleves (Enseignant)
//  Emplacement : teachers/students.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_TEACHER);

$pageTitle   = 'Mes eleves';
$pageSection = 'students';
$user        = currentUser();

$teacher  = dbFetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user['id']]);
$teacherId= $teacher['id'] ?? 0;
$yearId   = dbFetchOne("SELECT id FROM academic_years WHERE is_current=1")['id'] ?? 1;
$termId   = dbFetchOne("SELECT id FROM terms WHERE is_current=1")['id'] ?? 1;

// Classes de cet enseignant
$myClasses = dbFetchAll(
    "SELECT DISTINCT c.id, c.name FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
     WHERE cs.teacher_id=? AND cs.academic_year_id=? ORDER BY c.name",
    [$teacherId, $yearId]
);
$myClassIds = array_column($myClasses, 'id');

$filterClass = (int)($_GET['class'] ?? ($myClassIds[0] ?? 0));
$search      = trim($_GET['search'] ?? '');

$where  = ["s.status='actif'"];
$params = [];
if (!empty($myClassIds)) {
    $where[] = "s.class_id IN (" . implode(',', $myClassIds) . ")";
}
if ($filterClass) { $where[] = "s.class_id = $filterClass"; }
if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s        = "%$search%";
    $params   = [$s, $s];
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$students = dbFetchAll(
    "SELECT s.id, s.student_number, u.first_name, u.last_name, u.gender,
            c.name AS class_name,
            ROUND(AVG(g.score/g.max_score*20),1) AS avg_grade,
            SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absences
     FROM students s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN grades g ON g.student_id = s.id AND g.term_id = $termId
     LEFT JOIN attendance a ON a.student_id = s.id AND MONTH(a.date) = MONTH(NOW())
     $whereStr
     GROUP BY s.id, s.student_number, u.first_name, u.last_name, u.gender, c.name
     ORDER BY u.last_name",
    $params
);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-graduation" style="color:var(--primary)"></i> Mes eleves
    </h1>
    <p><?= count($students) ?> eleve<?= count($students)>1?'s':'' ?></p>
  </div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="search-wrap" style="flex:1;min-width:160px">
        <i class="bx bx-search"></i>
        <input type="text" name="search" class="search-input" placeholder="Rechercher..." value="<?= clean($search) ?>">
      </div>
      <select name="class" class="form-control" style="width:160px">
        <option value="">Toutes mes classes</option>
        <?php foreach ($myClasses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Eleve</th>
          <th>Matricule</th>
          <th>Classe</th>
          <th>Moy. trimestre</th>
          <th>Absences (mois)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-graduation"></i></div>
              <h3>Aucun eleve</h3>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($students as $i => $s):
            $avg = $s['avg_grade'];
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px"><?= $i+1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-40" style="background:<?= $s['gender']==='F'?'#ec4899':'var(--primary)' ?>">
                  <?= getInitials($s['first_name'], $s['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                </div>
              </div>
            </td>
            <td><code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 7px;border-radius:4px"><?= clean($s['student_number']) ?></code></td>
            <td><?= clean($s['class_name'] ?? '—') ?></td>
            <td>
              <?php if ($avg !== null): ?>
                <span style="font-weight:700;color:<?= $avg>=10?'var(--success)':'var(--danger)' ?>"><?= $avg ?>/20</span>
                <?php $m = getMention($avg); ?>
                <span class="badge" style="background:<?= $m['color'] ?>20;color:<?= $m['color'] ?>;font-size:10px;margin-left:4px"><?= $m['label'] ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted)">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($s['absences'] > 0): ?>
                <span class="badge badge-danger"><?= $s['absences'] ?> absence<?= $s['absences']>1?'s':'' ?></span>
              <?php else: ?>
                <span class="badge badge-success">Aucune</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>