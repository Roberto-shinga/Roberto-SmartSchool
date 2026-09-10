<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Notes';
$pageSection = 'grades';
$user        = currentUser();
$teacherId   = getCurrentTeacherId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$assignments = $teacherId ? getTeacherAssignments($teacherId, $yearId) : [];
$assignmentIds = array_column($assignments, 'id');

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$termId       = (int)($_GET['term_id'] ?? 0);

// ── GARDE-FOU : l'affectation doit appartenir a ce professeur ──
// Meme si l'ID vient de l'URL ou d'un formulaire modifie a la main.
$assignment = null;
if ($assignmentId) {
    if (!$teacherId || !teacherOwnsAssignment($teacherId, $assignmentId)) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', "Tu n'as pas acces a cette classe/matiere.");
    }
    $assignment = dbFetchOne(
        "SELECT ta.*, c.name AS class_name, s.name AS subject_name
         FROM teacher_assignments ta JOIN classes c ON ta.class_id=c.id JOIN subjects s ON ta.subject_id=s.id
         WHERE ta.id = ?", [$assignmentId]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $postAssignmentId = (int)($_POST['assignment_id'] ?? 0);

    // Re-verification systematique cote serveur avant TOUTE ecriture
    if (!$teacherId || !teacherOwnsAssignment($teacherId, $postAssignmentId)) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', "Action refusee : cette classe/matiere ne t'est pas attribuee.");
    } else {
        if ($action === 'add') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $termIdPost= (int)($_POST['term_id'] ?? 0);
            $gradeType = in_array($_POST['grade_type'] ?? '', ['controle','interrogation','examen','tp','projet','devoir']) ? $_POST['grade_type'] : 'controle';
            $title     = sanitizeString($_POST['title'] ?? '');
            $score     = (float)($_POST['score'] ?? 0);
            $maxScore  = max(1, (float)($_POST['max_score'] ?? 20));
            $coef      = max(0.1, (float)($_POST['coefficient'] ?? 1));
            $date      = $_POST['date'] ?: date('Y-m-d');
            $comment   = sanitizeString($_POST['comment'] ?? '');

            // L'eleve doit bien etre dans la classe de cette affectation
            $studentOk = dbFetchOne("SELECT 1 FROM students WHERE id=? AND class_id=?", [$studentId, $assignment['class_id'] ?? 0]);

            if (!$studentOk) {
                redirectWith($_SERVER['PHP_SELF'] . "?assignment_id=$postAssignmentId&term_id=$termIdPost", 'danger', 'Eleve invalide pour cette classe.');
            } elseif ($score < 0 || $score > $maxScore) {
                redirectWith($_SERVER['PHP_SELF'] . "?assignment_id=$postAssignmentId&term_id=$termIdPost", 'danger', 'La note doit etre comprise entre 0 et le maximum.');
            } else {
                dbExecute(
                    "INSERT INTO grades (student_id, assignment_id, term_id, grade_type, title, score, max_score, coefficient, date, comment, graded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$studentId, $postAssignmentId, $termIdPost, $gradeType, $title ?: null, $score, $maxScore, $coef, $date, $comment ?: null, $user['id']]
                );
                logActivity('grade_added', "Note ajoutee (eleve #$studentId)");
                redirectWith($_SERVER['PHP_SELF'] . "?assignment_id=$postAssignmentId&term_id=$termIdPost", 'success', 'Note enregistree.');
            }
        }

        if ($action === 'update') {
            $gradeId  = (int)($_POST['id'] ?? 0);
            // Re-verifie que la note appartient bien a une affectation de ce professeur
            $owned = dbFetchOne("SELECT g.* FROM grades g WHERE g.id=? AND g.assignment_id=?", [$gradeId, $postAssignmentId]);
            $score    = (float)($_POST['score'] ?? 0);
            $maxScore = max(1, (float)($_POST['max_score'] ?? 20));
            $comment  = sanitizeString($_POST['comment'] ?? '');

            if (!$owned) {
                redirectWith($_SERVER['PHP_SELF'], 'danger', 'Note introuvable ou non autorisee.');
            } elseif ($score < 0 || $score > $maxScore) {
                redirectWith($_SERVER['PHP_SELF'] . "?assignment_id=$postAssignmentId&term_id=$termId", 'danger', 'Note invalide.');
            } else {
                dbExecute("UPDATE grades SET score=?, max_score=?, comment=? WHERE id=?", [$score, $maxScore, $comment ?: null, $gradeId]);
                logActivity('grade_updated', "Note #$gradeId modifiee");
                redirectWith($_SERVER['PHP_SELF'] . "?assignment_id=$postAssignmentId&term_id=$termId", 'success', 'Note modifiee.');
            }
        }

        if ($action === 'delete') {
            $gradeId = (int)($_POST['id'] ?? 0);
            $owned = dbFetchOne("SELECT id FROM grades WHERE id=? AND assignment_id=?", [$gradeId, $postAssignmentId]);
            if ($owned) {
                dbExecute("DELETE FROM grades WHERE id=?", [$gradeId]);
                logActivity('grade_deleted', "Note #$gradeId supprimee");
                redirectWith($_SERVER['PHP_SELF'] . "?assignment_id=$postAssignmentId&term_id=$termId", 'success', 'Note supprimee.');
            }
        }
    }
}

$termsList = dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);

$students = [];
$gradesByStudent = [];
if ($assignment && $termId) {
    $students = dbFetchAll(
        "SELECT s.id, s.student_number, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln
         FROM students s LEFT JOIN users u ON s.user_id=u.id
         WHERE s.class_id=? AND s.status='actif' ORDER BY fn, ln",
        [$assignment['class_id']]
    );
    $allGrades = dbFetchAll(
        "SELECT * FROM grades WHERE assignment_id=? AND term_id=? ORDER BY date DESC",
        [$assignmentId, $termId]
    );
    foreach ($allGrades as $g) $gradesByStudent[$g['student_id']][] = $g;
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Notes</h1><p>Saisie limitee a tes classes et matieres attribuees</p></div>
    </div>

    <?php if (empty($assignments)): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-error"></i></div><h3>Aucune affectation</h3><p>Contacte l'administration pour obtenir une classe/matiere.</p></div>
    <?php else: ?>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-3" style="align-items:end">
          <div class="form-group" style="margin-bottom:0;grid-column:span 2">
            <label class="form-label">Classe / Matiere</label>
            <select name="assignment_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner...</option>
              <?php foreach ($assignments as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $assignmentId===$a['id']?'selected':'' ?>><?= clean($a['subject_name']) ?> — <?= clean($a['class_name']) ?></option>
              <?php endforeach; ?>
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

    <?php if (!$assignment || !$termId): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-star"></i></div><h3>Selectionne une classe/matiere et un trimestre</h3></div>
    <?php else: ?>

    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-book-open"></i> <?= clean($assignment['subject_name']) ?> — <?= clean($assignment['class_name']) ?></h3>
        <button class="btn btn-primary btn-sm" onclick="openAddModal()"><i class="bx bx-plus"></i> Ajouter une note</button>
      </div>
      <?php if (empty($students)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-user-x"></i></div><h3>Aucun eleve actif dans cette classe</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Notes du trimestre</th></tr></thead>
          <tbody>
            <?php foreach ($students as $st): $grades = $gradesByStudent[$st['id']] ?? []; ?>
            <tr>
              <td class="text-sm" style="white-space:nowrap;vertical-align:top;padding-top:14px">
                <?= clean($st['fn'] . ' ' . $st['ln']) ?><div class="text-xs text-muted"><?= clean($st['student_number']) ?></div>
              </td>
              <td>
                <?php if (empty($grades)): ?>
                  <span class="text-xs text-muted">Aucune note</span>
                <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  <?php foreach ($grades as $g): ?>
                  <div style="border:1px solid var(--border);border-radius:var(--radius);padding:6px 10px;cursor:pointer"
                       onclick='openEditModal(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)'>
                    <div class="text-xs text-muted"><?= ucfirst($g['grade_type']) ?><?= $g['title'] ? ' — '.clean($g['title']) : '' ?></div>
                    <div class="font-semibold text-sm"><?= number_format((float)$g['score'],1) ?>/<?= number_format((float)$g['max_score'],1) ?></div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Ajouter une note ══ -->
<div class="modal-overlay" id="addGradeModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
      <input type="hidden" name="term_id" value="<?= $termId ?>">
      <div class="modal-header">
        <h3><i class="bx bx-plus"></i> Ajouter une note</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Eleve <span class="form-required">*</span></label>
          <select name="student_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($students as $st): ?><option value="<?= $st['id'] ?>"><?= clean($st['fn'] . ' ' . $st['ln']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="grade_type" class="form-control">
              <option value="controle">Controle</option><option value="interrogation">Interrogation</option>
              <option value="examen">Examen</option><option value="tp">TP</option><option value="projet">Projet</option><option value="devoir">Devoir</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Titre</label><input type="text" name="title" class="form-control" placeholder="Ex : Devoir n°2"></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Note <span class="form-required">*</span></label><input type="number" step="0.5" name="score" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Sur</label><input type="number" step="0.5" name="max_score" class="form-control" value="20" required></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Coefficient</label><input type="number" step="0.5" name="coefficient" class="form-control" value="1"></div>
          <div class="form-group"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Commentaire</label><textarea name="comment" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal : Modifier / supprimer une note ══ -->
<div class="modal-overlay" id="editGradeModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
      <input type="hidden" name="id" id="eg_id">
      <div class="modal-header">
        <h3><i class="bx bx-edit"></i> Modifier la note</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Note</label><input type="number" step="0.5" name="score" id="eg_score" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Sur</label><input type="number" step="0.5" name="max_score" id="eg_max" class="form-control" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Commentaire</label><textarea name="comment" id="eg_comment" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <form method="POST" data-confirm="Supprimer cette note ?" style="display:inline">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
          <input type="hidden" name="id" id="eg_del_id">
          <button type="submit" class="btn btn-ghost" style="color:var(--danger)"><i class="bx bx-trash"></i> Supprimer</button>
        </form>
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() { SS.openModal('addGradeModal'); }
function openEditModal(g) {
  document.getElementById('eg_id').value = g.id;
  document.getElementById('eg_del_id').value = g.id;
  document.getElementById('eg_score').value = g.score;
  document.getElementById('eg_max').value = g.max_score;
  document.getElementById('eg_comment').value = g.comment || '';
  SS.openModal('editGradeModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
