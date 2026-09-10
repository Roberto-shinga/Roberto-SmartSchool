<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Notes';
$pageSection = 'grades';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $score    = (float)($_POST['score'] ?? 0);
        $maxScore = max(1, (float)($_POST['max_score'] ?? 20));
        $comment  = sanitizeString($_POST['comment'] ?? '');

        if ($score < 0 || $score > $maxScore) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'La note doit etre comprise entre 0 et le maximum.');
        } else {
            dbExecute("UPDATE grades SET score=?, max_score=?, comment=? WHERE id=?", [$score, $maxScore, $comment ?: null, $id]);
            logActivity('grade_corrected', "Note #$id corrigee par l'administration");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Note corrigee.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("DELETE FROM grades WHERE id=?", [$id]);
        logActivity('grade_deleted', "Note #$id supprimee par l'administration");
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Note supprimee.');
    }
}

$classFlt   = (int)($_GET['class_id']   ?? 0);
$subjectFlt = (int)($_GET['subject_id'] ?? 0);
$termFlt    = (int)($_GET['term_id']    ?? 0);

$where  = ["ta.academic_year_id = ?"];
$params = [$yearId];
if ($classFlt)   { $where[] = "ta.class_id = ?";   $params[] = $classFlt; }
if ($subjectFlt) { $where[] = "ta.subject_id = ?"; $params[] = $subjectFlt; }
if ($termFlt)    { $where[] = "g.term_id = ?";     $params[] = $termFlt; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$grades = dbFetchAll(
    "SELECT g.*, s.student_number, COALESCE(s.first_name, su.first_name) AS fn, COALESCE(s.last_name, su.last_name) AS ln,
            sub.name AS subject_name, sub.color AS subject_color, tm.name AS term_name,
            tu.first_name AS teacher_fn, tu.last_name AS teacher_ln, c.name AS class_name
     FROM grades g
     JOIN teacher_assignments ta ON g.assignment_id = ta.id
     JOIN subjects sub ON ta.subject_id = sub.id
     JOIN classes c ON ta.class_id = c.id
     JOIN students s ON g.student_id = s.id
     LEFT JOIN users su ON s.user_id = su.id
     JOIN teachers te ON ta.teacher_id = te.id
     JOIN users tu ON te.user_id = tu.id
     JOIN terms tm ON g.term_id = tm.id
     $whereSql
     ORDER BY g.date DESC LIMIT 200",
    $params
);

$classesList  = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);
$subjectsList = dbFetchAll("SELECT id, name FROM subjects WHERE is_active=1 ORDER BY name");
$termsList    = dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Notes</h1><p>Supervision et correction — la saisie se fait depuis l'espace enseignant</p></div>
    </div>

    <div class="alert alert-info" style="margin-bottom:20px">
      <i class="bx bx-info-circle"></i>
      Cette page permet de <strong>consulter et corriger</strong> les notes saisies par les enseignants. La saisie
      quotidienne des notes se fait depuis l'espace Enseignant, filtree automatiquement selon ses classes/matieres attribuees.
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-3">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()">
              <option value="0">Toutes</option>
              <?php foreach ($classesList as $c): ?><option value="<?= $c['id'] ?>" <?= $classFlt===$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Matiere</label>
            <select name="subject_id" class="form-control" onchange="this.form.submit()">
              <option value="0">Toutes</option>
              <?php foreach ($subjectsList as $s): ?><option value="<?= $s['id'] ?>" <?= $subjectFlt===$s['id']?'selected':'' ?>><?= clean($s['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Trimestre</label>
            <select name="term_id" class="form-control" onchange="this.form.submit()">
              <option value="0">Tous</option>
              <?php foreach ($termsList as $t): ?><option value="<?= $t['id'] ?>" <?= $termFlt===$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if (empty($grades)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-star"></i></div>
          <h3>Aucune note trouvee</h3>
          <p>Aucune note ne correspond a ces criteres, ou aucun enseignant n'a encore saisi de notes.</p>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Matiere</th><th>Classe</th><th>Type</th><th>Note</th><th>Enseignant</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($grades as $g): ?>
            <tr data-grade='<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>'>
              <td class="text-sm"><?= clean($g['fn'] . ' ' . $g['ln']) ?><div class="text-xs text-muted"><?= clean($g['student_number']) ?></div></td>
              <td><span class="badge" style="background:<?= clean($g['subject_color']) ?>22;color:<?= clean($g['subject_color']) ?>"><?= clean($g['subject_name']) ?></span></td>
              <td class="text-sm text-muted"><?= clean($g['class_name']) ?></td>
              <td class="text-sm"><?= ucfirst($g['grade_type']) ?><?= $g['title'] ? ' — ' . clean($g['title']) : '' ?></td>
              <td class="font-semibold"><?= number_format((float)$g['score'], 1) ?> / <?= number_format((float)$g['max_score'], 1) ?></td>
              <td class="text-sm text-muted"><?= clean($g['teacher_fn'] . ' ' . $g['teacher_ln']) ?></td>
              <td class="text-sm text-muted"><?= formatDate($g['date']) ?></td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openGradeModal(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
                <form method="POST" style="display:inline" data-confirm="Supprimer cette note ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $g['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger)"><i class="bx bx-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══ Modal : corriger une note ══ -->
<div class="modal-overlay" id="gradeModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="gr_id">
      <div class="modal-header">
        <h3><i class="bx bx-edit"></i> Corriger la note</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <p class="text-sm text-muted" style="margin-bottom:14px" id="gr_context"></p>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Note</label><input type="number" step="0.5" name="score" id="gr_score" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Sur</label><input type="number" step="0.5" name="max_score" id="gr_max" class="form-control" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Commentaire</label>
          <textarea name="comment" id="gr_comment" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openGradeModal(g) {
  document.getElementById('gr_id').value = g.id;
  document.getElementById('gr_score').value = g.score;
  document.getElementById('gr_max').value = g.max_score;
  document.getElementById('gr_comment').value = g.comment || '';
  document.getElementById('gr_context').textContent = g.fn + ' ' + g.ln + ' — ' + g.subject_name;
  SS.openModal('gradeModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
