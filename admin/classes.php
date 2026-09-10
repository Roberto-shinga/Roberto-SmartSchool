<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Classes';
$pageSection = 'classes';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = sanitizeString($_POST['name'] ?? '');
        $levelId   = (int)($_POST['level_id'] ?? 0) ?: null;
        $optionId  = (int)($_POST['option_id'] ?? 0) ?: null;
        $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;
        $gradeYear = (int)($_POST['grade_year'] ?? 0) ?: null;
        $section   = sanitizeString($_POST['section'] ?? 'A');
        $capacity  = max(1, (int)($_POST['capacity'] ?? 40));
        $room      = sanitizeString($_POST['room'] ?? '');
        $teacherId = (int)($_POST['class_teacher_id'] ?? 0) ?: null;

        if (empty($name) || !$levelId) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Nom et niveau obligatoires.');
        } elseif ($id) {
            dbExecute(
                "UPDATE classes SET name=?, level_id=?, option_id=?, section_id=?, grade_year=?, section=?, capacity=?, room=?, class_teacher_id=?
                 WHERE id=?",
                [$name, $levelId, $optionId, $sectionId, $gradeYear, $section, $capacity, $room ?: null, $teacherId, $id]
            );
            logActivity('class_updated', "Classe modifiee : $name");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Classe modifiee.');
        } else {
            dbExecute(
                "INSERT INTO classes (academic_year_id, level_id, option_id, section_id, name, grade_year, section, capacity, room, class_teacher_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$yearId, $levelId, $optionId, $sectionId, $name, $gradeYear, $section, $capacity, $room ?: null, $teacherId]
            );
            logActivity('class_created', "Classe creee : $name");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Classe creee.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $nb = dbCount('students', "class_id=?", [$id]);
        if ($nb > 0) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', "Impossible : $nb eleve(s) sont encore dans cette classe.");
        } else {
            dbExecute("DELETE FROM classes WHERE id=?", [$id]);
            logActivity('class_deleted', "Classe #$id supprimee");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Classe supprimee.');
        }
    }
}

$classes = dbFetchAll(
    "SELECT c.*, l.name AS level_name, o.name AS option_name, sec.name AS section_name,
            u.first_name AS t_fn, u.last_name AS t_ln,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status='actif') AS nb_students
     FROM classes c
     LEFT JOIN levels l ON c.level_id = l.id
     LEFT JOIN school_options o ON c.option_id = o.id
     LEFT JOIN sections sec ON c.section_id = sec.id
     LEFT JOIN users u ON c.class_teacher_id = u.id
     WHERE c.academic_year_id = ?
     ORDER BY l.order_index, c.grade_year, c.section",
    [$yearId]
);

$levelsList   = dbFetchAll("SELECT * FROM levels WHERE is_active=1 ORDER BY order_index");
$optionsList  = dbFetchAll("SELECT * FROM school_options WHERE is_active=1 ORDER BY name");
$sectionsList = dbFetchAll("SELECT * FROM sections WHERE is_active=1 ORDER BY name");
$teachersList = dbFetchAll(
    "SELECT u.id, u.first_name, u.last_name FROM users u JOIN teachers t ON t.user_id=u.id
     WHERE u.is_active=1 ORDER BY u.first_name"
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Classes</h1><p><?= count($classes) ?> classe(s) — annee <?= clean($currentYear['name'] ?? '—') ?></p></div>
      <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openClassModal()"><i class="bx bx-plus"></i> Ajouter une classe</button>
      </div>
    </div>

    <div class="grid-3">
      <?php foreach ($classes as $c): ?>
      <div class="card animate-in" data-class='<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>'>
        <div class="card-body">
          <div class="flex justify-between items-start" style="margin-bottom:10px">
            <div>
              <div style="font-weight:700;font-size:15px;color:var(--text-primary)"><?= clean($c['name']) ?></div>
              <div class="text-xs text-muted"><?= clean($c['level_name'] ?: '—') ?><?= $c['option_name'] ? ' · ' . clean($c['option_name']) : '' ?><?= $c['section_name'] ? ' · ' . clean($c['section_name']) : '' ?></div>
            </div>
            <span class="badge badge-primary"><?= (int)$c['nb_students'] ?>/<?= (int)$c['capacity'] ?></span>
          </div>
          <div class="text-sm text-muted" style="margin-bottom:4px"><i class="bx bx-door-open"></i> <?= clean($c['room'] ?: 'Salle non definie') ?></div>
          <div class="text-sm text-muted" style="margin-bottom:14px">
            <i class="bx bx-user-voice"></i>
            <?= $c['t_fn'] ? clean($c['t_fn'] . ' ' . $c['t_ln']) : 'Aucun titulaire' ?>
          </div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" style="flex:1" onclick='openClassModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'>
              <i class="bx bx-edit"></i> Modifier
            </button>
            <form method="POST" data-confirm="Supprimer la classe <?= clean($c['name']) ?> ?">
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

  </div>
</div>

<!-- ══ Modal : Ajouter / modifier une classe ══ -->
<div class="modal-overlay" id="classModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="cls_id" value="">
      <div class="modal-header">
        <h3 id="cls_modal_title"><i class="bx bx-building"></i> Ajouter une classe</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom de la classe <span class="form-required">*</span></label>
          <input type="text" name="name" id="cls_name" class="form-control" placeholder="Ex : 7eme Annee B" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Niveau <span class="form-required">*</span></label>
            <select name="level_id" id="cls_level" class="form-control" required>
              <option value="">Selectionner...</option>
              <?php foreach ($levelsList as $l): ?>
                <option value="<?= $l['id'] ?>"><?= clean($l['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Option / filiere</label>
            <select name="option_id" id="cls_option" class="form-control">
              <option value="">Aucune</option>
              <?php foreach ($optionsList as $o): ?>
                <option value="<?= $o['id'] ?>"><?= clean($o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Section pedagogique</label>
            <select name="section_id" id="cls_section_id" class="form-control">
              <option value="">Aucune</option>
              <?php foreach ($sectionsList as $s): ?>
                <option value="<?= $s['id'] ?>"><?= clean($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Ex : scientifique, commerciale (Humanites). Configurable dans Structure academique.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Division (A/B/C)</label>
            <input type="text" name="section" id="cls_section" class="form-control" maxlength="10" value="A">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Annee (1 a 8)</label>
            <input type="number" name="grade_year" id="cls_grade" class="form-control" min="1" max="8">
          </div>
          <div class="form-group">
            <label class="form-label">Capacite</label>
            <input type="number" name="capacity" id="cls_capacity" class="form-control" min="1" value="40">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Salle</label>
            <input type="text" name="room" id="cls_room" class="form-control" placeholder="Ex : Salle 12">
          </div>
          <div class="form-group">
            <label class="form-label">Titulaire de classe</label>
            <select name="class_teacher_id" id="cls_teacher" class="form-control">
              <option value="">Aucun</option>
              <?php foreach ($teachersList as $t): ?>
                <option value="<?= $t['id'] ?>"><?= clean($t['first_name'] . ' ' . $t['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
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
function openClassModal(cls) {
  const f = document.querySelector('#classModal form');
  f.reset();
  document.getElementById('cls_modal_title').innerHTML = cls
    ? '<i class="bx bx-edit"></i> Modifier la classe'
    : '<i class="bx bx-building"></i> Ajouter une classe';
  document.getElementById('cls_id').value       = cls ? cls.id : '';
  document.getElementById('cls_name').value     = cls ? cls.name : '';
  document.getElementById('cls_level').value    = cls ? (cls.level_id || '') : '';
  document.getElementById('cls_option').value   = cls ? (cls.option_id || '') : '';
  document.getElementById('cls_section_id').value = cls ? (cls.section_id || '') : '';
  document.getElementById('cls_grade').value    = cls ? (cls.grade_year || '') : '';
  document.getElementById('cls_section').value  = cls ? (cls.section || 'A') : 'A';
  document.getElementById('cls_capacity').value = cls ? cls.capacity : 40;
  document.getElementById('cls_room').value     = cls ? (cls.room || '') : '';
  document.getElementById('cls_teacher').value  = cls ? (cls.class_teacher_id || '') : '';
  SS.openModal('classModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
