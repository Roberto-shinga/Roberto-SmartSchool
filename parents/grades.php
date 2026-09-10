<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireParent();

$pageTitle   = 'Notes & bulletins';
$pageSection = 'grades';
$user        = currentUser();

$children = getParentChildren($user['id']);
if (empty($children)) {
    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    echo '<div class="main-content"><div class="page-wrapper"><div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-group"></i></div><h3>Aucun enfant lie</h3></div></div></div>';
    require INCLUDES_PATH . '/footer.php';
    exit;
}

$studentId = (int)($_GET['student_id'] ?? $children[0]['id']);

// GARDE-FOU : cet eleve doit etre un enfant de ce parent, jamais faire
// confiance a un student_id fourni par l'URL sans cette verification.
if (!parentOwnsStudent($user['id'], $studentId)) {
    redirectWith($_SERVER['PHP_SELF'], 'danger', "Cet eleve n'est pas lie a ton compte.");
}

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$termsList   = dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);
$termFlt     = (int)($_GET['term_id'] ?? 0);
if (!$termFlt) {
    $cur = dbFetchOne("SELECT id FROM terms WHERE academic_year_id=? AND is_current=1", [$yearId]);
    $termFlt = $cur ? (int)$cur['id'] : ($termsList[0]['id'] ?? 0);
}

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

$reports = dbFetchAll(
    "SELECT rc.*, t.name AS term_name FROM report_cards rc JOIN terms t ON rc.term_id=t.id
     WHERE rc.student_id=? AND rc.is_published=1 ORDER BY t.start_date DESC",
    [$studentId]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Notes & bulletins</h1><p>Suivi scolaire</p></div>
      <div class="page-header-actions">
        <form method="GET" style="display:flex;gap:10px">
          <?php if (count($children) > 1): ?>
          <select name="student_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?><option value="<?= $c['id'] ?>" <?= $studentId===$c['id']?'selected':'' ?>><?= clean($c['fn'] . ' ' . $c['ln']) ?></option><?php endforeach; ?>
          </select>
          <?php else: ?>
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
          <?php endif; ?>
          <select name="term_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($termsList as $t): ?><option value="<?= $t['id'] ?>" <?= $termFlt===$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <?php
      $currentReport = null;
      foreach ($reports as $r) if ((int)$r['term_id'] === $termFlt) { $currentReport = $r; break; }
    ?>
    <?php if ($currentReport): $m = getMention((float)$currentReport['average']); ?>
    <div class="grid-3 mb-6">
      <div class="stat-card c-primary"><div class="stat-icon c-primary"><i class="bx bx-star"></i></div><div><div class="stat-value" style="color:<?= $m['color'] ?>"><?= number_format((float)$currentReport['average'],2) ?>/20</div><div class="stat-label"><?= $m['label'] ?></div></div></div>
      <div class="stat-card c-purple"><div class="stat-icon c-purple"><i class="bx bx-medal"></i></div><div><div class="stat-value">#<?= $currentReport['rank'] ?></div><div class="stat-label">sur <?= $currentReport['total_students'] ?></div></div></div>
      <div class="stat-card c-cyan"><div class="stat-icon c-cyan"><i class="bx bx-group"></i></div><div><div class="stat-value"><?= number_format((float)$currentReport['class_average'],1) ?>/20</div><div class="stat-label">Moyenne classe</div></div></div>
    </div>
    <?php if ($currentReport['teacher_comment'] || $currentReport['admin_comment']): ?>
    <div class="card" style="margin-bottom:24px">
      <div class="card-body">
        <?php if ($currentReport['teacher_comment']): ?><p class="text-sm"><strong>Appreciation :</strong> <?= clean($currentReport['teacher_comment']) ?></p><?php endif; ?>
        <?php if ($currentReport['admin_comment']): ?><p class="text-sm" style="margin-top:6px"><strong>Administration :</strong> <?= clean($currentReport['admin_comment']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php endif; endif; ?>

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
