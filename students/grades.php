<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Mes notes';
$pageSection = 'grades';
$studentId   = getCurrentStudentId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$termFlt     = (int)($_GET['term_id'] ?? 0);

$termsList = dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);
if (!$termFlt) {
    $cur = dbFetchOne("SELECT id FROM terms WHERE academic_year_id=? AND is_current=1", [$yearId]);
    $termFlt = $cur ? (int)$cur['id'] : ($termsList[0]['id'] ?? 0);
}

// Toujours filtre sur $studentId derive de la session : aucune valeur
// externe ne peut faire acceder cette page aux notes d'un autre eleve.
$grades = $termFlt ? dbFetchAll(
    "SELECT g.*, sub.name AS subject_name, sub.color, tu.first_name AS t_fn, tu.last_name AS t_ln
     FROM grades g
     JOIN teacher_assignments ta ON g.assignment_id = ta.id
     JOIN subjects sub ON ta.subject_id = sub.id
     JOIN teachers te ON ta.teacher_id = te.id
     JOIN users tu ON te.user_id = tu.id
     WHERE g.student_id = ? AND g.term_id = ?
     ORDER BY sub.name, g.date DESC",
    [$studentId, $termFlt]
) : [];

$bySubject = [];
foreach ($grades as $g) $bySubject[$g['subject_name']][] = $g;

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mes notes</h1><p>Consultation par trimestre</p></div>
      <div class="page-header-actions">
        <form method="GET">
          <select name="term_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($termsList as $t): ?><option value="<?= $t['id'] ?>" <?= $termFlt===$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php if (empty($bySubject)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-star"></i></div><h3>Aucune note pour ce trimestre</h3></div>
    <?php else: foreach ($bySubject as $subjectName => $subjectGrades):
      $avg = array_sum(array_map(fn($g) => ($g['score']/$g['max_score'])*20, $subjectGrades)) / count($subjectGrades);
      $mention = getMention($avg);
    ?>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <h3><span style="width:10px;height:10px;border-radius:50%;background:<?= clean($subjectGrades[0]['color']) ?>;display:inline-block;margin-right:6px"></span><?= clean($subjectName) ?></h3>
        <span class="font-semibold" style="color:<?= $mention['color'] ?>">Moy. <?= number_format($avg,1) ?>/20</span>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Type</th><th>Note</th><th>Enseignant</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($subjectGrades as $g): ?>
            <tr>
              <td class="text-sm"><?= ucfirst($g['grade_type']) ?><?= $g['title'] ? ' — '.clean($g['title']) : '' ?></td>
              <td class="font-semibold"><?= number_format((float)$g['score'],1) ?>/<?= number_format((float)$g['max_score'],1) ?></td>
              <td class="text-sm text-muted"><?= clean($g['t_fn'] . ' ' . $g['t_ln']) ?></td>
              <td class="text-sm text-muted"><?= formatDate($g['date']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
