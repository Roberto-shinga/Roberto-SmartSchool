<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Emplois du temps';
$pageSection = 'timetable';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$classId     = (int)($_GET['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $day          = (int)($_POST['day_of_week'] ?? 0);
        $start        = $_POST['start_time'] ?? '';
        $end          = $_POST['end_time']   ?? '';
        $room         = sanitizeString($_POST['room'] ?? '');
        $formClassId  = (int)($_POST['class_id'] ?? 0);

        if (!$assignmentId || !$day || !$start || !$end) {
            redirectWith($_SERVER['PHP_SELF'] . "?class_id=$formClassId", 'danger', 'Tous les champs obligatoires doivent etre remplis.');
        } elseif ($start >= $end) {
            redirectWith($_SERVER['PHP_SELF'] . "?class_id=$formClassId", 'danger', 'L\'heure de fin doit etre apres l\'heure de debut.');
        } else {
            if ($id) {
                dbExecute("UPDATE timetable SET assignment_id=?, day_of_week=?, start_time=?, end_time=?, room=? WHERE id=?",
                    [$assignmentId, $day, $start, $end, $room ?: null, $id]);
                logActivity('timetable_updated', "Creneau #$id modifie");
                redirectWith($_SERVER['PHP_SELF'] . "?class_id=$formClassId", 'success', 'Creneau modifie.');
            } else {
                dbExecute("INSERT INTO timetable (assignment_id, day_of_week, start_time, end_time, room, academic_year_id) VALUES (?, ?, ?, ?, ?, ?)",
                    [$assignmentId, $day, $start, $end, $room ?: null, $yearId]);
                logActivity('timetable_created', "Nouveau creneau ajoute");
                redirectWith($_SERVER['PHP_SELF'] . "?class_id=$formClassId", 'success', 'Creneau ajoute.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $formClassId = (int)($_POST['class_id'] ?? 0);
        dbExecute("DELETE FROM timetable WHERE id=?", [$id]);
        logActivity('timetable_deleted', "Creneau #$id supprime");
        redirectWith($_SERVER['PHP_SELF'] . "?class_id=$formClassId", 'success', 'Creneau supprime.');
    }
}

$classesList = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);

$slots = [];
$assignments = [];
if ($classId) {
    $slots = dbFetchAll(
        "SELECT tt.*, sub.name AS subject_name, sub.color, tu.first_name AS t_fn, tu.last_name AS t_ln
         FROM timetable tt
         JOIN teacher_assignments ta ON tt.assignment_id = ta.id
         JOIN subjects sub ON ta.subject_id = sub.id
         JOIN teachers te ON ta.teacher_id = te.id
         JOIN users tu ON te.user_id = tu.id
         WHERE ta.class_id = ? AND tt.academic_year_id = ?
         ORDER BY tt.day_of_week, tt.start_time",
        [$classId, $yearId]
    );
    $assignments = dbFetchAll(
        "SELECT ta.id, sub.name AS subject_name, tu.first_name AS t_fn, tu.last_name AS t_ln
         FROM teacher_assignments ta
         JOIN subjects sub ON ta.subject_id = sub.id
         JOIN teachers te ON ta.teacher_id = te.id
         JOIN users tu ON te.user_id = tu.id
         WHERE ta.class_id = ? AND ta.academic_year_id = ?
         ORDER BY sub.name",
        [$classId, $yearId]
    );
}

$slotsByDay = [];
foreach ($slots as $s) $slotsByDay[(int)$s['day_of_week']][] = $s;

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Emplois du temps</h1><p>Creneaux horaires par classe</p></div>
      <?php if ($classId): ?>
      <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openSlotModal()"><i class="bx bx-plus"></i> Ajouter un creneau</button>
      </div>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET">
          <div class="form-group" style="margin-bottom:0;max-width:320px">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner une classe...</option>
              <?php foreach ($classesList as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>
    </div>

    <?php if (!$classId): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-calendar"></i></div><h3>Selectionne une classe</h3></div>
    <?php elseif (empty($assignments)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-error"></i></div>
        <h3>Aucun enseignant affecte a cette classe</h3>
        <p>Affecte d'abord des enseignants a cette classe depuis <a href="<?= BASE_URL ?>/admin/teachers.php">Enseignants</a>.</p>
      </div>
    <?php else: ?>

    <div class="grid-3">
      <?php foreach (DAYS_OF_WEEK as $dayNum => $dayLabel): ?>
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-calendar-alt"></i> <?= $dayLabel ?></h3></div>
        <div class="card-body" style="padding:12px">
          <?php if (empty($slotsByDay[$dayNum])): ?>
            <p class="text-xs text-muted" style="padding:8px">Aucun cours</p>
          <?php else: ?>
            <?php foreach ($slotsByDay[$dayNum] as $s): ?>
            <div style="border-left:3px solid <?= clean($s['color']) ?>;padding:8px 10px;margin-bottom:8px;background:var(--bg-hover);border-radius:6px">
              <div class="flex justify-between items-start">
                <div>
                  <div class="text-sm font-semibold"><?= clean($s['subject_name']) ?></div>
                  <div class="text-xs text-muted"><?= substr($s['start_time'],0,5) ?> - <?= substr($s['end_time'],0,5) ?></div>
                  <div class="text-xs text-muted"><?= clean($s['t_fn'] . ' ' . $s['t_ln']) ?><?= $s['room'] ? ' · ' . clean($s['room']) : '' ?></div>
                </div>
                <div style="display:flex;gap:2px">
                  <button type="button" class="btn btn-ghost btn-icon btn-sm" style="width:24px;height:24px"
                          onclick='openSlotModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'><i class="bx bx-edit" style="font-size:14px"></i></button>
                  <form method="POST" data-confirm="Supprimer ce creneau ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="width:24px;height:24px;color:var(--danger)"><i class="bx bx-trash" style="font-size:14px"></i></button>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Ajouter / modifier un creneau ══ -->
<div class="modal-overlay" id="slotModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="tt_id" value="">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <div class="modal-header">
        <h3 id="tt_title"><i class="bx bx-calendar-plus"></i> Ajouter un creneau</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Matiere / Enseignant <span class="form-required">*</span></label>
          <select name="assignment_id" id="tt_assignment" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($assignments as $a): ?>
              <option value="<?= $a['id'] ?>"><?= clean($a['subject_name']) ?> — <?= clean($a['t_fn'] . ' ' . $a['t_ln']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Jour <span class="form-required">*</span></label>
          <select name="day_of_week" id="tt_day" class="form-control" required>
            <?php foreach (DAYS_OF_WEEK as $num => $label): ?>
              <option value="<?= $num ?>"><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Debut <span class="form-required">*</span></label><input type="time" name="start_time" id="tt_start" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Fin <span class="form-required">*</span></label><input type="time" name="end_time" id="tt_end" class="form-control" required></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Salle</label>
          <input type="text" name="room" id="tt_room" class="form-control">
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
function openSlotModal(s) {
  const f = document.querySelector('#slotModal form');
  f.reset();
  document.getElementById('tt_title').innerHTML = s ? '<i class="bx bx-edit"></i> Modifier le creneau' : '<i class="bx bx-calendar-plus"></i> Ajouter un creneau';
  document.getElementById('tt_id').value = s ? s.id : '';
  document.getElementById('tt_assignment').value = s ? s.assignment_id : '';
  document.getElementById('tt_day').value = s ? s.day_of_week : '1';
  document.getElementById('tt_start').value = s ? s.start_time.substring(0,5) : '';
  document.getElementById('tt_end').value = s ? s.end_time.substring(0,5) : '';
  document.getElementById('tt_room').value = s ? (s.room || '') : '';
  SS.openModal('slotModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
