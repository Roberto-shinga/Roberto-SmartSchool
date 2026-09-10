<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$user      = currentUser();
$teacherId = getCurrentTeacherId();
$quizId    = (int)($_GET['quiz_id'] ?? 0);

// GARDE-FOU : ce quiz doit appartenir a un cours de cet enseignant
$quiz = $quizId ? dbFetchOne(
    "SELECT q.*, c.title AS course_title, c.id AS course_id FROM quizzes q
     JOIN courses c ON q.course_id = c.id
     WHERE q.id=? AND c.teacher_id=?",
    [$quizId, $teacherId]
) : null;
if (!$quiz) redirectWith(BASE_URL . '/teachers/courses.php', 'danger', 'Quiz introuvable ou non autorise.');

$pageTitle   = clean($quiz['title']);
$pageSection = 'courses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $postQuizId = (int)($_POST['quiz_id'] ?? 0);

    if ($postQuizId !== $quizId) {
        redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$quizId", 'danger', 'Action refusee.');
    } else {
        if ($action === 'save_question') {
            $id       = (int)($_POST['id'] ?? 0);
            $question = sanitizeString($_POST['question'] ?? '');
            $type     = in_array($_POST['type'] ?? '', ['qcm','vrai_faux','texte']) ? $_POST['type'] : 'qcm';
            $correct  = trim($_POST['correct'] ?? '');
            $explanation = sanitizeString($_POST['explanation'] ?? '');
            $points   = max(1, (int)($_POST['points'] ?? 1));
            $order    = (int)($_POST['order_index'] ?? 0);

            $options = null;
            if ($type === 'qcm') {
                $lines = array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')));
                $options = json_encode(array_values($lines));
            }

            if (empty($question) || $correct === '') {
                redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$quizId", 'danger', 'Question et bonne reponse obligatoires.');
            } elseif ($type === 'qcm' && empty(json_decode($options, true))) {
                redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$quizId", 'danger', 'Ajoute au moins une option pour un QCM (une par ligne).');
            } elseif ($id) {
                $owned = dbFetchOne("SELECT id FROM quiz_questions WHERE id=? AND quiz_id=?", [$id, $quizId]);
                if ($owned) {
                    dbExecute("UPDATE quiz_questions SET question=?, type=?, options=?, correct=?, explanation=?, points=?, order_index=? WHERE id=?",
                        [$question, $type, $options, $correct, $explanation ?: null, $points, $order, $id]);
                    redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$quizId", 'success', 'Question modifiee.');
                }
            } else {
                dbExecute("INSERT INTO quiz_questions (quiz_id, question, type, options, correct, explanation, points, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$quizId, $question, $type, $options, $correct, $explanation ?: null, $points, $order]);
                redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$quizId", 'success', 'Question ajoutee.');
            }
        }

        if ($action === 'delete_question') {
            $id = (int)($_POST['id'] ?? 0);
            if (dbFetchOne("SELECT id FROM quiz_questions WHERE id=? AND quiz_id=?", [$id, $quizId])) {
                dbExecute("DELETE FROM quiz_questions WHERE id=?", [$id]);
                redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$quizId", 'success', 'Question supprimee.');
            }
        }
    }
}

$questions = dbFetchAll("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_index", [$quizId]);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <a href="<?= BASE_URL ?>/teachers/course-edit.php?course_id=<?= $quiz['course_id'] ?>#quizzes" class="text-sm text-muted" style="display:inline-block;margin-bottom:6px"><i class="bx bx-arrow-back"></i> Retour au cours</a>
        <h1><?= clean($quiz['title']) ?></h1>
        <p><?= clean($quiz['course_title']) ?> — Questions</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openQuestionModal()"><i class="bx bx-plus"></i> Ajouter une question</button>
      </div>
    </div>

    <?php if (!$quiz['is_published'] && !empty($questions)): ?>
    <div class="alert alert-info" style="margin-bottom:20px"><i class="bx bx-info-circle"></i> Ce quiz est encore en brouillon. Publie-le depuis la page du cours quand tu es pret.</div>
    <?php endif; ?>

    <?php if (empty($questions)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-brain"></i></div>
        <h3>Aucune question</h3>
        <p>Ajoute au moins une question avant de publier ce quiz.</p>
      </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach ($questions as $i => $q): $options = json_decode($q['options'] ?? '[]', true) ?: []; ?>
      <div class="card">
        <div class="card-body">
          <div class="flex justify-between items-start">
            <div style="flex:1">
              <div class="text-xs text-muted" style="margin-bottom:4px"><?= ucfirst(str_replace('_',' ',$q['type'])) ?> · <?= (int)$q['points'] ?> pt<?= $q['points']>1?'s':'' ?></div>
              <div class="text-sm font-semibold" style="margin-bottom:8px"><?= $i+1 ?>. <?= clean($q['question']) ?></div>
              <?php if ($q['type'] === 'qcm' && !empty($options)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px">
                  <?php foreach ($options as $opt): ?>
                    <span class="badge <?= mb_strtolower(trim($opt))===mb_strtolower(trim($q['correct'])) ? 'badge-success' : 'badge-gray' ?>"><?= clean($opt) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-sm" style="margin-bottom:6px">Bonne reponse : <span class="badge badge-success"><?= clean($q['correct']) ?></span></div>
              <?php endif; ?>
              <?php if ($q['explanation']): ?><div class="text-xs text-muted"><i class="bx bx-info-circle"></i> <?= clean($q['explanation']) ?></div><?php endif; ?>
            </div>
            <div class="td-actions">
              <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openQuestionModal(<?= htmlspecialchars(json_encode($q), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              <form method="POST" style="display:inline" data-confirm="Supprimer cette question ?">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_question">
                <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger)"><i class="bx bx-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Question ══ -->
<div class="modal-overlay" id="questionModal">
  <div class="modal modal-lg">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_question">
      <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
      <input type="hidden" name="id" id="q_id" value="">
      <div class="modal-header">
        <h3 id="q_title"><i class="bx bx-plus"></i> Nouvelle question</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Question <span class="form-required">*</span></label><textarea name="question" id="q_text" class="form-control" rows="2" required></textarea></div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" id="q_type" class="form-control" onchange="toggleQuestionType()">
            <option value="qcm">QCM (choix multiple)</option>
            <option value="vrai_faux">Vrai / Faux</option>
            <option value="texte">Reponse libre (texte court)</option>
          </select>
        </div>
        <div class="form-group" id="q_options_box">
          <label class="form-label">Options (une par ligne)</label>
          <textarea name="options" id="q_options" class="form-control" rows="4" placeholder="Option A&#10;Option B&#10;Option C"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Bonne reponse <span class="form-required">*</span></label>
          <input type="text" name="correct" id="q_correct" class="form-control" required placeholder="Doit correspondre exactement a une option (ou Vrai/Faux)">
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Points</label><input type="number" name="points" id="q_points" class="form-control" value="1" min="1"></div>
          <div class="form-group"><label class="form-label">Ordre</label><input type="number" name="order_index" id="q_order" class="form-control" value="0"></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Explication (affichee apres correction)</label>
          <textarea name="explanation" id="q_explanation" class="form-control" rows="2"></textarea>
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
function toggleQuestionType() {
  document.getElementById('q_options_box').style.display = document.getElementById('q_type').value === 'qcm' ? '' : 'none';
}
function openQuestionModal(q) {
  const f = document.querySelector('#questionModal form'); f.reset();
  document.getElementById('q_title').innerHTML = q ? '<i class="bx bx-edit"></i> Modifier la question' : '<i class="bx bx-plus"></i> Nouvelle question';
  document.getElementById('q_id').value = q ? q.id : '';
  document.getElementById('q_text').value = q ? q.question : '';
  document.getElementById('q_type').value = q ? q.type : 'qcm';
  document.getElementById('q_options').value = q && q.options ? JSON.parse(q.options).join('\n') : '';
  document.getElementById('q_correct').value = q ? q.correct : '';
  document.getElementById('q_points').value = q ? q.points : 1;
  document.getElementById('q_order').value = q ? q.order_index : 0;
  document.getElementById('q_explanation').value = q ? (q.explanation || '') : '';
  toggleQuestionType();
  SS.openModal('questionModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
