<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Bulletins';
$pageSection = 'reports';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// Classes dont ce professeur est TITULAIRE (class_teacher_id sur la table classes)
$myClasses = dbFetchAll("SELECT id, name FROM classes WHERE class_teacher_id=? AND academic_year_id=? ORDER BY grade_year, section", [$user['id'], $yearId]);
$myClassIds = array_column($myClasses, 'id');

$classId = (int)($_GET['class_id'] ?? 0);
$termId  = (int)($_GET['term_id']  ?? 0);

// ── GARDE-FOU : la classe doit etre l'une de celles dont il est titulaire ──
if ($classId && !in_array($classId, $myClassIds)) {
    redirectWith($_SERVER['PHP_SELF'], 'danger', "Tu n'es titulaire d'aucune classe correspondante.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $postClassId = (int)($_POST['class_id'] ?? 0);

    if (!in_array($postClassId, $myClassIds)) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', 'Action refusee.');
    } else {
        // Verifie que le bulletin appartient bien a un eleve de cette classe
        $owned = dbFetchOne("SELECT rc.id FROM report_cards rc JOIN students s ON rc.student_id=s.id WHERE rc.id=? AND s.class_id=?", [$id, $postClassId]);
        if ($owned) {
            dbExecute("UPDATE report_cards SET teacher_comment=? WHERE id=?", [sanitizeString($_POST['teacher_comment'] ?? '') ?: null, $id]);
            logActivity('report_card_teacher_comment', "Bulletin #$id : appreciation ajoutee");
            redirectWith($_SERVER['PHP_SELF'] . "?class_id=$postClassId&term_id={$_POST['term_id']}", 'success', 'Appreciation enregistree.');
        }
    }
}

$termsList = dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);

$reports = [];
if ($classId && $termId) {
    $reports = dbFetchAll(
        "SELECT rc.*, s.student_number, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln
         FROM report_cards rc JOIN students s ON rc.student_id=s.id LEFT JOIN users u ON s.user_id=u.id
         WHERE s.class_id=? AND rc.term_id=? ORDER BY rc.`rank`",
        [$classId, $termId]
    );
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Bulletins</h1><p>Classes dont tu es titulaire</p></div>
    </div>

    <?php if (empty($myClasses)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-file"></i></div><h3>Tu n'es titulaire d'aucune classe</h3><p>Seul le titulaire d'une classe peut ajouter l'appreciation generale du bulletin.</p></div>
    <?php else: ?>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-3">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner...</option>
              <?php foreach ($myClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Trimestre</label>
            <select name="term_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner...</option>
              <?php foreach ($termsList as $t): ?><option value="<?= $t['id'] ?>" <?= $termId===$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>
    </div>

    <?php if (!$classId || !$termId): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-file"></i></div><h3>Selectionne une classe et un trimestre</h3></div>
    <?php elseif (empty($reports)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-calculator"></i></div><h3>Aucun bulletin calcule pour l'instant</h3><p>L'administration doit d'abord generer les bulletins depuis sa page de gestion.</p></div>
    <?php else: ?>

    <div class="card">
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Rang</th><th>Eleve</th><th>Moyenne</th><th>Conduite</th><th>Appreciation</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($reports as $r): $m = getMention((float)$r['average']); ?>
            <tr data-report='<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>'>
              <td class="font-semibold">#<?= $r['rank'] ?></td>
              <td class="text-sm"><?= clean($r['fn'] . ' ' . $r['ln']) ?><div class="text-xs text-muted"><?= clean($r['student_number']) ?></div></td>
              <td><span class="font-semibold" style="color:<?= $m['color'] ?>"><?= number_format((float)$r['average'],2) ?>/20</span></td>
              <td class="text-sm"><?= clean($r['conduct'] ?: '—') ?></td>
              <td class="text-sm text-muted truncate" style="max-width:200px"><?= clean($r['teacher_comment'] ?: 'Aucune') ?></td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openCommentModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Appreciation ══ -->
<div class="modal-overlay" id="commentModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="id" id="cm_id">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <input type="hidden" name="term_id" value="<?= $termId ?>">
      <div class="modal-header">
        <h3><i class="bx bx-edit"></i> Appreciation — <span id="cm_name"></span></h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <textarea name="teacher_comment" id="cm_comment" class="form-control" rows="3" placeholder="Ex : Trimestre serieux, continue ainsi."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCommentModal(r) {
  document.getElementById('cm_id').value = r.id;
  document.getElementById('cm_comment').value = r.teacher_comment || '';
  document.getElementById('cm_name').textContent = r.fn + ' ' + r.ln;
  SS.openModal('commentModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
