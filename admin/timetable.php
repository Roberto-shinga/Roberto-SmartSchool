<?php
// ============================================================
//  SmartSchool — Emploi du temps
//  Emplacement : admin/timetable.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Emploi du temps';
$pageSection = 'timetable';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;

$classes = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name", [$yearId]);

// Associations classe-matiere-enseignant disponibles
$classSubjects = dbFetchAll(
    "SELECT cs.id, cs.class_id, sub.name AS subject_name, sub.color,
            u.first_name, u.last_name
     FROM class_subjects cs
     JOIN subjects sub ON cs.subject_id = sub.id
     JOIN teachers t ON cs.teacher_id = t.id
     JOIN users u ON t.user_id = u.id
     WHERE cs.academic_year_id = ?
     ORDER BY sub.name",
    [$yearId]
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $classSubjectId = (int)($_POST['class_subject_id'] ?? 0);
        $day            = (int)($_POST['day_of_week']      ?? 1);
        $startTime      = trim($_POST['start_time']         ?? '');
        $endTime        = trim($_POST['end_time']           ?? '');
        $room           = trim($_POST['room']                ?? '');

        if (!$classSubjectId || !$startTime || !$endTime) {
            redirectWith(BASE_URL . '/admin/timetable.php', 'danger', 'Tous les champs sont obligatoires.');
        }

        dbExecute(
            "INSERT INTO timetable (class_subject_id, day_of_week, start_time, end_time, room, academic_year_id)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$classSubjectId, $day, $startTime, $endTime, $room, $yearId]
        );
        logActivity('add_timetable', 'Creneau ajoute a l\'emploi du temps');
        redirectWith(BASE_URL . '/admin/timetable.php?class_id='.($_POST['class_id']??''), 'success', 'Creneau ajoute avec succes !');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['timetable_id'] ?? 0);
        dbExecute("DELETE FROM timetable WHERE id = ?", [$id]);
        logActivity('delete_timetable', 'Creneau supprime');
        redirectWith(BASE_URL . '/admin/timetable.php', 'success', 'Creneau supprime.');
    }
}

$selectedClass = (int)($_GET['class_id'] ?? (count($classes) ? $classes[0]['id'] : 0));

// Recuperer emploi du temps de la classe
$timetableData = [];
if ($selectedClass) {
    $rows = dbFetchAll(
        "SELECT tt.id, tt.day_of_week, tt.start_time, tt.end_time, tt.room,
                sub.name AS subject_name, sub.color,
                u.first_name, u.last_name
         FROM timetable tt
         JOIN class_subjects cs ON tt.class_subject_id = cs.id
         JOIN subjects sub ON cs.subject_id = sub.id
         JOIN teachers t ON cs.teacher_id = t.id
         JOIN users u ON t.user_id = u.id
         WHERE cs.class_id = ?
         ORDER BY tt.day_of_week, tt.start_time",
        [$selectedClass]
    );
    foreach ($rows as $r) {
        $timetableData[$r['day_of_week']][] = $r;
    }
}

$days = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi'];

// Filtrer class_subjects pour la classe selectionnee
$csForClass = array_filter($classSubjects, fn($cs) => $cs['class_id'] == $selectedClass);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-calendar" style="color:var(--primary)"></i>
      Emploi du temps
    </h1>
    <p>Organisez les creneaux horaires par classe</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd" <?= !$selectedClass ? 'disabled' : '' ?>>
      <i class="bx bx-plus"></i> Ajouter un creneau
    </button>
  </div>
</div>

<!-- Selecteur de classe -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:220px">
        <label class="form-label">Selectionner une classe</label>
        <select name="class_id" class="form-control" onchange="this.form.submit()">
          <option value="">Choisir une classe</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $selectedClass == $c['id'] ? 'selected' : '' ?>>
              <?= clean($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if (!$selectedClass): ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bx bx-calendar"></i></div>
    <h3>Selectionner une classe</h3>
    <p>Choisissez une classe pour voir son emploi du temps.</p>
  </div>
</div>
<?php else: ?>

<!-- Grille emploi du temps -->
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-time-five"></i> Planning hebdomadaire</h3>
  </div>
  <div style="overflow-x:auto">
    <div style="display:grid;grid-template-columns:repeat(6,minmax(160px,1fr));gap:1px;background:var(--border);min-width:960px">
      <?php foreach ($days as $dayNum => $dayName): ?>
      <div>
        <div style="background:var(--bg-hover);padding:12px;text-align:center;font-weight:700;font-size:13px;color:var(--text-primary)">
          <?= $dayName ?>
        </div>
        <div style="background:var(--bg-card);min-height:400px;padding:8px;display:flex;flex-direction:column;gap:8px">
          <?php
          $daySlots = $timetableData[$dayNum] ?? [];
          usort($daySlots, fn($a,$b) => strcmp($a['start_time'], $b['start_time']));
          if (empty($daySlots)):
          ?>
            <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:12px">
              Aucun cours
            </div>
          <?php else: ?>
            <?php foreach ($daySlots as $slot): ?>
            <div style="
              background   : <?= clean($slot['color']) ?>12;
              border-left  : 3px solid <?= clean($slot['color']) ?>;
              border-radius: var(--radius-sm);
              padding      : 10px;
              position     : relative;
            ">
              <form method="POST" style="position:absolute;top:4px;right:4px">
                <?= csrfField() ?>
                <input type="hidden" name="action"       value="delete">
                <input type="hidden" name="timetable_id" value="<?= $slot['id'] ?>">
                <button type="submit" style="width:18px;height:18px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.8rem"
                        onclick="return confirm('Supprimer ce creneau ?')">
                  <i class="bx bx-x"></i>
                </button>
              </form>
              <div style="font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:4px;padding-right:16px">
                <?= clean($slot['subject_name']) ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px;margin-bottom:2px">
                <i class="bx bx-time"></i>
                <?= substr($slot['start_time'],0,5) ?> – <?= substr($slot['end_time'],0,5) ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px">
                <i class="bx bx-user"></i>
                <?= clean($slot['first_name']) ?> <?= clean(substr($slot['last_name'],0,1)) ?>.
              </div>
              <?php if ($slot['room']): ?>
              <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px;margin-top:2px">
                <i class="bx bx-map-pin"></i> <?= clean($slot['room']) ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- MODAL AJOUTER CRENEAU -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-plus" style="color:var(--primary)"></i> Nouveau creneau</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"   value="add">
      <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Matiere / Enseignant <span class="form-required">*</span></label>
          <select name="class_subject_id" class="form-control" required>
            <option value="">Selectionner</option>
            <?php foreach ($csForClass as $cs): ?>
              <option value="<?= $cs['id'] ?>">
                <?= clean($cs['subject_name']) ?> — <?= clean($cs['first_name']) ?> <?= clean($cs['last_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Jour <span class="form-required">*</span></label>
          <select name="day_of_week" class="form-control" required>
            <?php foreach ($days as $num => $name): ?>
              <option value="<?= $num ?>"><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Heure de debut <span class="form-required">*</span></label>
            <input type="time" name="start_time" class="form-control" value="08:00" required>
          </div>
          <div class="form-group">
            <label class="form-label">Heure de fin <span class="form-required">*</span></label>
            <input type="time" name="end_time" class="form-control" value="09:00" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Salle</label>
          <input type="text" name="room" class="form-control" placeholder="Salle 01">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Ajouter</button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
