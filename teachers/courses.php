<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Mes cours';
$pageSection = 'courses';
$user        = currentUser();
$teacherId   = getCurrentTeacherId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = sanitizeString($_POST['title'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '');
        $subjectId   = (int)($_POST['subject_id'] ?? 0);
        $levelId     = (int)($_POST['level_id'] ?? 0) ?: null;
        $optionId    = (int)($_POST['option_id'] ?? 0) ?: null;
        $gradeYear   = (int)($_POST['grade_year'] ?? 0) ?: null;

        // GARDE-FOU : la matiere doit faire partie de ses affectations
        $subjectOk = dbFetchOne(
            "SELECT 1 FROM teacher_assignments WHERE teacher_id=? AND subject_id=? AND academic_year_id=?",
            [$teacherId, $subjectId, $yearId]
        );

        if (empty($title) || !$subjectOk) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Titre obligatoire et matiere valide (parmi tes affectations).');
        } elseif ($id) {
            // Verifie que ce cours lui appartient avant modification
            $owned = dbFetchOne("SELECT id FROM courses WHERE id=? AND teacher_id=?", [$id, $teacherId]);
            if (!$owned) {
                redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cours introuvable.');
            } else {
                dbExecute("UPDATE courses SET title=?, description=?, subject_id=?, level_id=?, option_id=?, grade_year=? WHERE id=?",
                    [$title, $description ?: null, $subjectId, $levelId, $optionId, $gradeYear, $id]);
                logActivity('course_updated', "Cours modifie : $title");
                redirectWith($_SERVER['PHP_SELF'], 'success', 'Cours modifie.');
            }
        } else {
            dbExecute(
                "INSERT INTO courses (title, description, subject_id, level_id, option_id, grade_year, teacher_id, academic_year_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $description ?: null, $subjectId, $levelId, $optionId, $gradeYear, $teacherId, $yearId]
            );
            logActivity('course_created', "Cours cree : $title");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Cours cree. Ajoute maintenant des lecons avant de le publier.');
        }
    }

    if ($action === 'toggle_publish') {
        $id = (int)($_POST['id'] ?? 0);
        $c  = dbFetchOne("SELECT * FROM courses WHERE id=? AND teacher_id=?", [$id, $teacherId]);
        if ($c) {
            dbExecute("UPDATE courses SET is_published=? WHERE id=?", [$c['is_published'] ? 0 : 1, $id]);
            logActivity('course_publish_toggled', $c['title']);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Statut de publication mis a jour.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $c  = dbFetchOne("SELECT * FROM courses WHERE id=? AND teacher_id=?", [$id, $teacherId]);
        if ($c) {
            dbExecute("DELETE FROM courses WHERE id=?", [$id]);
            logActivity('course_deleted', $c['title']);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Cours supprime.');
        }
    }
}

$mySubjects = $teacherId ? dbFetchAll(
    "SELECT DISTINCT s.id, s.name FROM subjects s JOIN teacher_assignments ta ON ta.subject_id=s.id
     WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY s.name",
    [$teacherId, $yearId]
) : [];
$levelsList  = dbFetchAll("SELECT * FROM levels WHERE is_active=1 ORDER BY order_index");
$optionsList = dbFetchAll("SELECT * FROM school_options WHERE is_active=1 ORDER BY name");

$courses = $teacherId ? dbFetchAll(
    "SELECT c.*, s.name AS subject_name, s.color, l.name AS level_name,
            (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) AS nb_lessons,
            (SELECT COUNT(*) FROM quizzes WHERE course_id=c.id) AS nb_quizzes
     FROM courses c JOIN subjects s ON c.subject_id=s.id LEFT JOIN levels l ON c.level_id=l.id
     WHERE c.teacher_id=? AND c.academic_year_id=? ORDER BY c.created_at DESC",
    [$teacherId, $yearId]
) : [];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mes cours</h1><p>SmartSchool Learning — contenu pour tes classes</p></div>
      <div class="page-header-actions">
        <?php if (empty($mySubjects)): ?>
          <span class="text-sm text-muted">Aucune matiere attribuee pour l'instant</span>
        <?php else: ?>
          <button class="btn btn-primary" onclick="openCourseModal()"><i class="bx bx-plus"></i> Nouveau cours</button>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($courses)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-book-open"></i></div>
        <h3>Aucun cours cree</h3>
        <p>Cree ton premier cours, puis ajoute des lecons et des quiz depuis sa page de gestion.</p>
      </div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach ($courses as $c): ?>
      <div class="card" data-course='<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>'>
        <div class="card-body">
          <div class="flex justify-between items-start" style="margin-bottom:10px">
            <div class="flex items-center gap-2">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= clean($c['color']) ?>"></span>
              <span class="text-xs text-muted"><?= clean($c['subject_name']) ?></span>
            </div>
            <span class="badge badge-<?= $c['is_published'] ? 'success' : 'gray' ?>"><?= $c['is_published'] ? 'Publie' : 'Brouillon' ?></span>
          </div>
          <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:6px"><?= clean($c['title']) ?></div>
          <div class="text-xs text-muted" style="margin-bottom:14px"><?= clean($c['level_name'] ?: 'Tous niveaux') ?></div>
          <div class="text-xs text-muted" style="margin-bottom:14px"><?= (int)$c['nb_lessons'] ?> lecon(s) · <?= (int)$c['nb_quizzes'] ?> quiz</div>
          <div style="display:flex;gap:8px">
            <a href="<?= BASE_URL ?>/teachers/course-edit.php?course_id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1"><i class="bx bx-cog"></i> Gerer</a>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle_publish">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="<?= $c['is_published'] ? 'Depublier' : 'Publier' ?>">
                <i class="bx <?= $c['is_published'] ? 'bx-hide' : 'bx-show' ?>"></i>
              </button>
            </form>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openCourseModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
            <form method="POST" style="display:inline" data-confirm="Supprimer ce cours et tout son contenu (lecons, quiz) ?">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger)"><i class="bx bx-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Ajouter / modifier un cours ══ -->
<div class="modal-overlay" id="courseModal">
  <div class="modal modal-lg">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="crs_id" value="">
      <div class="modal-header">
        <h3 id="crs_title"><i class="bx bx-plus"></i> Nouveau cours</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Titre <span class="form-required">*</span></label>
          <input type="text" name="title" id="crs_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Matiere <span class="form-required">*</span></label>
          <select name="subject_id" id="crs_subject" class="form-control" required>
            <?php foreach ($mySubjects as $s): ?><option value="<?= $s['id'] ?>"><?= clean($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Niveau (optionnel)</label>
            <select name="level_id" id="crs_level" class="form-control">
              <option value="">Tous niveaux</option>
              <?php foreach ($levelsList as $l): ?><option value="<?= $l['id'] ?>"><?= clean($l['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Option (optionnel)</label>
            <select name="option_id" id="crs_option" class="form-control">
              <option value="">Aucune</option>
              <?php foreach ($optionsList as $o): ?><option value="<?= $o['id'] ?>"><?= clean($o['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Description</label>
          <textarea name="description" id="crs_desc" class="form-control" rows="3"></textarea>
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
function openCourseModal(c) {
  const f = document.querySelector('#courseModal form');
  f.reset();
  document.getElementById('crs_title').innerHTML = c ? '<i class="bx bx-edit"></i> Modifier le cours' : '<i class="bx bx-plus"></i> Nouveau cours';
  document.getElementById('crs_id').value = c ? c.id : '';
  document.getElementById('crs_name').value = c ? c.title : '';
  document.getElementById('crs_subject').value = c ? c.subject_id : '';
  document.getElementById('crs_level').value = c ? (c.level_id || '') : '';
  document.getElementById('crs_option').value = c ? (c.option_id || '') : '';
  document.getElementById('crs_desc').value = c ? (c.description || '') : '';
  SS.openModal('courseModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
