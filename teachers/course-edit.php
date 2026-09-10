<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$user      = currentUser();
$teacherId = getCurrentTeacherId();
$courseId  = (int)($_GET['course_id'] ?? 0);

// GARDE-FOU : ce cours doit appartenir a cet enseignant
$course = $courseId ? dbFetchOne(
    "SELECT c.*, s.name AS subject_name FROM courses c JOIN subjects s ON c.subject_id=s.id WHERE c.id=? AND c.teacher_id=?",
    [$courseId, $teacherId]
) : null;
if (!$course) redirectWith(BASE_URL . '/teachers/courses.php', 'danger', "Cours introuvable ou non autorise.");

$pageTitle   = clean($course['title']);
$pageSection = 'courses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $postCourseId = (int)($_POST['course_id'] ?? 0);

    // Re-verification systematique : le cours cible doit etre CELUI-CI
    if ($postCourseId !== $courseId) {
        redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId", 'danger', 'Action refusee.');
    } else {

        // ── Lecons ───────────────────────────────────────────────
        if ($action === 'save_lesson') {
            $id       = (int)($_POST['id'] ?? 0);
            $title    = sanitizeString($_POST['title'] ?? '');
            $content  = sanitizeString($_POST['content'] ?? '');
            $order    = (int)($_POST['order_index'] ?? 0);
            $duration = (int)($_POST['duration_min'] ?? 0) ?: null;

            if (empty($title)) {
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId", 'danger', 'Titre de la lecon obligatoire.');
            } elseif ($id) {
                $owned = dbFetchOne("SELECT id FROM lessons WHERE id=? AND course_id=?", [$id, $courseId]);
                if ($owned) {
                    dbExecute("UPDATE lessons SET title=?, content=?, order_index=?, duration_min=? WHERE id=?",
                        [$title, $content ?: null, $order, $duration, $id]);
                    redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId", 'success', 'Lecon modifiee.');
                }
            } else {
                dbExecute("INSERT INTO lessons (course_id, title, content, order_index, duration_min) VALUES (?, ?, ?, ?, ?)",
                    [$courseId, $title, $content ?: null, $order, $duration]);
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId", 'success', 'Lecon ajoutee.');
            }
        }

        if ($action === 'toggle_lesson_publish') {
            $id = (int)($_POST['id'] ?? 0);
            $l  = dbFetchOne("SELECT * FROM lessons WHERE id=? AND course_id=?", [$id, $courseId]);
            if ($l) dbExecute("UPDATE lessons SET is_published=? WHERE id=?", [$l['is_published'] ? 0 : 1, $id]);
            redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId", 'success', 'Statut mis a jour.');
        }

        if ($action === 'delete_lesson') {
            $id = (int)($_POST['id'] ?? 0);
            if (dbFetchOne("SELECT id FROM lessons WHERE id=? AND course_id=?", [$id, $courseId])) {
                dbExecute("DELETE FROM lessons WHERE id=?", [$id]);
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId", 'success', 'Lecon supprimee.');
            }
        }

        // ── Ressources ───────────────────────────────────────────
        if ($action === 'add_resource') {
            $title = sanitizeString($_POST['title'] ?? '');
            $type  = in_array($_POST['type'] ?? '', ['pdf','video','image','lien','document']) ? $_POST['type'] : 'document';
            $extUrl = sanitizeString($_POST['external_url'] ?? '');

            if (empty($title)) {
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'danger', 'Titre obligatoire.');
            } elseif ($type === 'lien') {
                if (empty($extUrl)) {
                    redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'danger', 'URL obligatoire pour un lien.');
                } else {
                    dbExecute("INSERT INTO learning_resources (course_id, title, type, external_url) VALUES (?, ?, 'lien', ?)", [$courseId, $title, $extUrl]);
                    redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'success', 'Ressource ajoutee.');
                }
            } elseif (empty($_FILES['file']['name'])) {
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'danger', 'Fichier obligatoire.');
            } else {
                $allowed = $type === 'image' ? ALLOWED_IMG : ($type === 'video' ? ['mp4','webm','mov'] : ALLOWED_DOCS);
                $upload = uploadFile($_FILES['file'], 'learning_resources', $allowed);
                if (!$upload['success']) {
                    redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'danger', $upload['error']);
                } else {
                    dbExecute("INSERT INTO learning_resources (course_id, title, type, file_path) VALUES (?, ?, ?, ?)", [$courseId, $title, $type, $upload['filename']]);
                    redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'success', 'Ressource ajoutee.');
                }
            }
        }

        if ($action === 'delete_resource') {
            $id = (int)($_POST['id'] ?? 0);
            if (dbFetchOne("SELECT id FROM learning_resources WHERE id=? AND course_id=?", [$id, $courseId])) {
                dbExecute("DELETE FROM learning_resources WHERE id=?", [$id]);
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#resources", 'success', 'Ressource supprimee.');
            }
        }

        // ── Quiz ─────────────────────────────────────────────────
        if ($action === 'save_quiz') {
            $id          = (int)($_POST['id'] ?? 0);
            $title       = sanitizeString($_POST['title'] ?? '');
            $description = sanitizeString($_POST['description'] ?? '');
            $duration    = (int)($_POST['duration_min'] ?? 0) ?: null;
            $maxAttempts = max(0, (int)($_POST['max_attempts'] ?? 3));
            $passScore   = max(1, min(100, (int)($_POST['pass_score'] ?? 50)));

            if (empty($title)) {
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#quizzes", 'danger', 'Titre du quiz obligatoire.');
            } elseif ($id) {
                $owned = dbFetchOne("SELECT id FROM quizzes WHERE id=? AND course_id=?", [$id, $courseId]);
                if ($owned) {
                    dbExecute("UPDATE quizzes SET title=?, description=?, duration_min=?, max_attempts=?, pass_score=? WHERE id=?",
                        [$title, $description ?: null, $duration, $maxAttempts, $passScore, $id]);
                    redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#quizzes", 'success', 'Quiz modifie.');
                }
            } else {
                dbExecute("INSERT INTO quizzes (course_id, title, description, duration_min, max_attempts, pass_score) VALUES (?, ?, ?, ?, ?, ?)",
                    [$courseId, $title, $description ?: null, $duration, $maxAttempts, $passScore]);
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#quizzes", 'success', 'Quiz cree. Ajoute des questions avant de le publier.');
            }
        }

        if ($action === 'toggle_quiz_publish') {
            $id = (int)($_POST['id'] ?? 0);
            $q  = dbFetchOne("SELECT * FROM quizzes WHERE id=? AND course_id=?", [$id, $courseId]);
            if ($q) dbExecute("UPDATE quizzes SET is_published=? WHERE id=?", [$q['is_published'] ? 0 : 1, $id]);
            redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#quizzes", 'success', 'Statut mis a jour.');
        }

        if ($action === 'delete_quiz') {
            $id = (int)($_POST['id'] ?? 0);
            if (dbFetchOne("SELECT id FROM quizzes WHERE id=? AND course_id=?", [$id, $courseId])) {
                dbExecute("DELETE FROM quizzes WHERE id=?", [$id]);
                redirectWith($_SERVER['PHP_SELF'] . "?course_id=$courseId#quizzes", 'success', 'Quiz supprime.');
            }
        }
    }
}

$lessons   = dbFetchAll("SELECT * FROM lessons WHERE course_id=? ORDER BY order_index", [$courseId]);
$resources = dbFetchAll("SELECT * FROM learning_resources WHERE course_id=? ORDER BY order_index", [$courseId]);
$quizzes   = dbFetchAll(
    "SELECT q.*, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) AS nb_questions FROM quizzes q WHERE q.course_id=? ORDER BY q.created_at",
    [$courseId]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <a href="<?= BASE_URL ?>/teachers/courses.php" class="text-sm text-muted" style="display:inline-block;margin-bottom:6px"><i class="bx bx-arrow-back"></i> Retour a mes cours</a>
        <h1><?= clean($course['title']) ?></h1>
        <p><?= clean($course['subject_name']) ?> — <span class="badge badge-<?= $course['is_published']?'success':'gray' ?>"><?= $course['is_published']?'Publie':'Brouillon' ?></span></p>
      </div>
    </div>

    <div class="tabs">
      <button type="button" class="tab-btn active" data-tab-target="lessons">Lecons (<?= count($lessons) ?>)</button>
      <button type="button" class="tab-btn" data-tab-target="resources">Ressources (<?= count($resources) ?>)</button>
      <button type="button" class="tab-btn" data-tab-target="quizzes">Quiz (<?= count($quizzes) ?>)</button>
    </div>

    <!-- ══ Onglet Lecons ══ -->
    <div class="tab-pane" data-tab="lessons">
      <div class="card">
        <div class="card-header">
          <h3><i class="bx bx-list-ul"></i> Lecons</h3>
          <button type="button" class="btn btn-primary btn-sm" onclick="openLessonModal()"><i class="bx bx-plus"></i> Ajouter</button>
        </div>
        <?php if (empty($lessons)): ?>
          <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-book-open"></i></div><h3>Aucune lecon</h3></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table">
            <thead><tr><th>#</th><th>Titre</th><th>Duree</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($lessons as $le): ?>
              <tr>
                <td class="text-sm text-muted"><?= (int)$le['order_index'] ?></td>
                <td class="text-sm font-semibold"><?= clean($le['title']) ?></td>
                <td class="text-sm text-muted"><?= $le['duration_min'] ? (int)$le['duration_min'] . ' min' : '—' ?></td>
                <td>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_lesson_publish">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="id" value="<?= $le['id'] ?>">
                    <button type="submit" class="badge badge-<?= $le['is_published']?'success':'gray' ?>" style="border:none;cursor:pointer"><?= $le['is_published']?'Publiee':'Brouillon' ?></button>
                  </form>
                </td>
                <td class="td-actions">
                  <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openLessonModal(<?= htmlspecialchars(json_encode($le), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
                  <form method="POST" style="display:inline" data-confirm="Supprimer cette lecon ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_lesson">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="id" value="<?= $le['id'] ?>">
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

    <!-- ══ Onglet Ressources ══ -->
    <div class="tab-pane" data-tab="resources" style="display:none">
      <div class="card">
        <div class="card-header">
          <h3><i class="bx bx-paperclip"></i> Ressources</h3>
          <button type="button" class="btn btn-primary btn-sm" onclick="SS.openModal('resourceModal')"><i class="bx bx-plus"></i> Ajouter</button>
        </div>
        <?php if (empty($resources)): ?>
          <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-paperclip"></i></div><h3>Aucune ressource</h3></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table">
            <thead><tr><th>Titre</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($resources as $r): ?>
              <tr>
                <td class="text-sm font-semibold"><?= clean($r['title']) ?></td>
                <td class="text-sm text-muted"><?= ucfirst($r['type']) ?></td>
                <td class="td-actions">
                  <form method="POST" style="display:inline" data-confirm="Supprimer cette ressource ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_resource">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
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

    <!-- ══ Onglet Quiz ══ -->
    <div class="tab-pane" data-tab="quizzes" style="display:none">
      <div class="card">
        <div class="card-header">
          <h3><i class="bx bx-brain"></i> Quiz</h3>
          <button type="button" class="btn btn-primary btn-sm" onclick="openQuizModal()"><i class="bx bx-plus"></i> Ajouter</button>
        </div>
        <?php if (empty($quizzes)): ?>
          <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-brain"></i></div><h3>Aucun quiz</h3></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table">
            <thead><tr><th>Titre</th><th>Questions</th><th>Seuil</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($quizzes as $q): ?>
              <tr>
                <td class="text-sm font-semibold"><?= clean($q['title']) ?></td>
                <td class="text-sm text-muted"><?= (int)$q['nb_questions'] ?></td>
                <td class="text-sm text-muted"><?= (int)$q['pass_score'] ?>%</td>
                <td>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_quiz_publish">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
                    <button type="submit" class="badge badge-<?= $q['is_published']?'success':'gray' ?>" style="border:none;cursor:pointer" <?= $q['nb_questions']==0 ? 'disabled title="Ajoute des questions avant de publier"' : '' ?>><?= $q['is_published']?'Publie':'Brouillon' ?></button>
                  </form>
                </td>
                <td class="td-actions">
                  <a href="<?= BASE_URL ?>/teachers/quiz-edit.php?quiz_id=<?= $q['id'] ?>" class="btn btn-ghost btn-icon btn-sm" title="Questions"><i class="bx bx-list-ul"></i></a>
                  <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openQuizModal(<?= htmlspecialchars(json_encode($q), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
                  <form method="POST" style="display:inline" data-confirm="Supprimer ce quiz et ses questions ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_quiz">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="id" value="<?= $q['id'] ?>">
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
</div>

<!-- ══ Modal : Lecon ══ -->
<div class="modal-overlay" id="lessonModal">
  <div class="modal modal-lg">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_lesson">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="id" id="le_id" value="">
      <div class="modal-header">
        <h3 id="le_title"><i class="bx bx-plus"></i> Nouvelle lecon</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Titre <span class="form-required">*</span></label><input type="text" name="title" id="le_name" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Ordre</label><input type="number" name="order_index" id="le_order" class="form-control" value="0"></div>
          <div class="form-group"><label class="form-label">Duree (minutes)</label><input type="number" name="duration_min" id="le_duration" class="form-control"></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Contenu</label><textarea name="content" id="le_content" class="form-control" rows="6"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal : Ressource ══ -->
<div class="modal-overlay" id="resourceModal">
  <div class="modal">
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_resource">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <div class="modal-header">
        <h3><i class="bx bx-plus"></i> Nouvelle ressource</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Titre <span class="form-required">*</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" id="res_type" class="form-control" onchange="document.getElementById('res_file_box').style.display=this.value==='lien'?'none':'block';document.getElementById('res_url_box').style.display=this.value==='lien'?'block':'none'">
            <option value="document">Document</option><option value="pdf">PDF</option><option value="image">Image</option><option value="video">Video</option><option value="lien">Lien externe</option>
          </select>
        </div>
        <div class="form-group" id="res_file_box"><label class="form-label">Fichier</label><input type="file" name="file" class="form-control"></div>
        <div class="form-group" id="res_url_box" style="display:none;margin-bottom:0"><label class="form-label">URL</label><input type="text" name="external_url" class="form-control" placeholder="https://..."></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal : Quiz ══ -->
<div class="modal-overlay" id="quizModal">
  <div class="modal modal-lg">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_quiz">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="id" id="qz_id" value="">
      <div class="modal-header">
        <h3 id="qz_title"><i class="bx bx-plus"></i> Nouveau quiz</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Titre <span class="form-required">*</span></label><input type="text" name="title" id="qz_name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="qz_desc" class="form-control" rows="2"></textarea></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Duree (minutes, optionnel)</label><input type="number" name="duration_min" id="qz_duration" class="form-control"></div>
          <div class="form-group"><label class="form-label">Tentatives max (0 = illimite)</label><input type="number" name="max_attempts" id="qz_attempts" class="form-control" value="3"></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Score minimum pour reussir (%)</label><input type="number" name="pass_score" id="qz_pass" class="form-control" value="50" min="1" max="100"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
// Onglets simples (pas besoin du composant .tabs generique ici)
document.querySelectorAll('[data-tab-target]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('[data-tab]').forEach(p => p.style.display = p.dataset.tab === btn.dataset.tabTarget ? '' : 'none');
  });
});

function openLessonModal(l) {
  const f = document.querySelector('#lessonModal form'); f.reset();
  document.getElementById('le_title').innerHTML = l ? '<i class="bx bx-edit"></i> Modifier la lecon' : '<i class="bx bx-plus"></i> Nouvelle lecon';
  document.getElementById('le_id').value = l ? l.id : '';
  document.getElementById('le_name').value = l ? l.title : '';
  document.getElementById('le_order').value = l ? l.order_index : 0;
  document.getElementById('le_duration').value = l ? (l.duration_min || '') : '';
  document.getElementById('le_content').value = l ? (l.content || '') : '';
  SS.openModal('lessonModal');
}
function openQuizModal(q) {
  const f = document.querySelector('#quizModal form'); f.reset();
  document.getElementById('qz_title').innerHTML = q ? '<i class="bx bx-edit"></i> Modifier le quiz' : '<i class="bx bx-plus"></i> Nouveau quiz';
  document.getElementById('qz_id').value = q ? q.id : '';
  document.getElementById('qz_name').value = q ? q.title : '';
  document.getElementById('qz_desc').value = q ? (q.description || '') : '';
  document.getElementById('qz_duration').value = q ? (q.duration_min || '') : '';
  document.getElementById('qz_attempts').value = q ? q.max_attempts : 3;
  document.getElementById('qz_pass').value = q ? q.pass_score : 50;
  SS.openModal('quizModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
