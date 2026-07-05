<?php
// ============================================================
//  SmartSchool — Gestion des classes
//  Emplacement : admin/classes.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des classes';
$pageSection = 'classes';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;

$teachers = dbFetchAll(
    "SELECT u.id, u.first_name, u.last_name
     FROM teachers t JOIN users u ON t.user_id = u.id
     WHERE t.status = 'actif' ORDER BY u.last_name"
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name']     ?? '');
        $level    = trim($_POST['level']    ?? '');
        $section  = trim($_POST['section']  ?? '');
        $capacity = (int)($_POST['capacity'] ?? 35);
        $room     = trim($_POST['room']     ?? '');
        $teacherId= (int)($_POST['class_teacher_id'] ?? 0) ?: null;

        if (empty($name)) {
            redirectWith(BASE_URL . '/admin/classes.php', 'danger', 'Le nom de la classe est obligatoire.');
        }

        dbExecute(
            "INSERT INTO classes (academic_year_id, name, level, section, capacity, room, class_teacher_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$yearId, $name, $level, $section, $capacity, $room, $teacherId]
        );
        logActivity('add_class', "Classe ajoutee : $name");
        redirectWith(BASE_URL . '/admin/classes.php', 'success', "Classe $name creee avec succes !");
    }

    if ($action === 'edit') {
        $classId  = (int)($_POST['class_id']  ?? 0);
        $name     = trim($_POST['name']       ?? '');
        $level    = trim($_POST['level']      ?? '');
        $section  = trim($_POST['section']    ?? '');
        $capacity = (int)($_POST['capacity']  ?? 35);
        $room     = trim($_POST['room']       ?? '');
        $teacherId= (int)($_POST['class_teacher_id'] ?? 0) ?: null;

        dbExecute(
            "UPDATE classes SET name=?, level=?, section=?, capacity=?, room=?, class_teacher_id=? WHERE id=?",
            [$name, $level, $section, $capacity, $room, $teacherId, $classId]
        );
        logActivity('edit_class', "Classe modifiee : $name");
        redirectWith(BASE_URL . '/admin/classes.php', 'success', "Classe $name mise a jour.");
    }

    if ($action === 'delete') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $cls     = dbFetchOne("SELECT name FROM classes WHERE id = ?", [$classId]);
        if ($cls) {
            dbExecute("UPDATE students SET class_id = NULL WHERE class_id = ?", [$classId]);
            dbExecute("DELETE FROM classes WHERE id = ?", [$classId]);
            logActivity('delete_class', "Classe supprimee : {$cls['name']}");
            redirectWith(BASE_URL . '/admin/classes.php', 'success', 'Classe supprimee.');
        }
        redirectWith(BASE_URL . '/admin/classes.php', 'danger', 'Classe introuvable.');
    }
}

// ── Liste des classes avec effectifs ─────────────────────
$classes = dbFetchAll(
    "SELECT c.id, c.name, c.level, c.section, c.capacity, c.room,
            u.first_name AS teacher_fn, u.last_name AS teacher_ln,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status='actif') AS student_count
     FROM classes c
     LEFT JOIN users u ON c.class_teacher_id = u.id
     WHERE c.academic_year_id = ?
     ORDER BY c.name",
    [$yearId]
);

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
      <i class="bx bx-building" style="color:var(--primary)"></i>
      Gestion des classes
    </h1>
    <p><?= count($classes) ?> classe<?= count($classes) > 1 ? 's' : '' ?> — <?= clean($currentYear['name'] ?? '') ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Nouvelle classe
    </button>
  </div>
</div>

<!-- GRILLE DE CARTES CLASSES -->
<?php if (empty($classes)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bx bx-building"></i></div>
      <h3>Aucune classe</h3>
      <p>Creez votre premiere classe pour commencer.</p>
      <button class="btn btn-primary" data-modal="modalAdd">
        <i class="bx bx-plus"></i> Creer une classe
      </button>
    </div>
  </div>
<?php else: ?>
<div class="grid-auto">
  <?php foreach ($classes as $c):
    $fillRate = $c['capacity'] > 0 ? round(($c['student_count'] / $c['capacity']) * 100) : 0;
    $fillColor = $fillRate >= 90 ? 'danger' : ($fillRate >= 70 ? 'warning' : 'success');
  ?>
  <div class="card card-hover">
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="width:48px;height:48px;border-radius:var(--radius);background:var(--grad-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:800">
          <?= strtoupper(substr($c['name'], 0, 2)) ?>
        </div>
        <div class="td-actions">
          <button class="btn btn-sm btn-secondary btn-icon"
                  onclick='openEdit(<?= json_encode([
                    "id"      => $c["id"],
                    "name"    => $c["name"],
                    "level"   => $c["level"],
                    "section" => $c["section"],
                    "capacity"=> $c["capacity"],
                    "room"    => $c["room"],
                  ], JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>
            <i class="bx bx-edit"></i>
          </button>
          <form method="POST" style="display:inline">
            <?= csrfField() ?>
            <input type="hidden" name="action"   value="delete">
            <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                    onclick="return confirm('Supprimer la classe <?= addslashes($c['name']) ?> ?')">
              <i class="bx bx-trash"></i>
            </button>
          </form>
        </div>
      </div>

      <h3 style="font-size:17px;font-weight:800;color:var(--text-primary);margin-bottom:4px">
        <?= clean($c['name']) ?>
      </h3>
      <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:16px">
        <?php if ($c['room']): ?>
          <i class="bx bx-map-pin"></i> <?= clean($c['room']) ?>
        <?php else: ?>
          Aucune salle assignee
        <?php endif; ?>
      </p>

      <!-- Effectif -->
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px">
          <span style="color:var(--text-muted)">Effectif</span>
          <span style="font-weight:700;color:var(--text-primary)">
            <?= $c['student_count'] ?> / <?= $c['capacity'] ?>
          </span>
        </div>
        <div class="progress">
          <div class="progress-bar <?= $fillColor ?>" style="width:<?= min(100,$fillRate) ?>%"></div>
        </div>
      </div>

      <!-- Prof principal -->
      <div style="display:flex;align-items:center;gap:8px;padding-top:12px;border-top:1px solid var(--border-light)">
        <?php if ($c['teacher_fn']): ?>
          <div class="avatar avatar-32" style="background:var(--cyan-dark)">
            <?= getInitials($c['teacher_fn'], $c['teacher_ln']) ?>
          </div>
          <div>
            <div style="font-size:12px;color:var(--text-muted)">Professeur principal</div>
            <div style="font-size:13px;font-weight:600;color:var(--text-primary)">
              <?= clean($c['teacher_fn']) ?> <?= clean($c['teacher_ln']) ?>
            </div>
          </div>
        <?php else: ?>
          <span style="font-size:12.5px;color:var(--text-light)">
            <i class="bx bx-user-x"></i> Aucun professeur assigne
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-footer">
      <a href="<?= BASE_URL ?>/admin/students.php?class=<?= $c['id'] ?>"
         class="btn btn-sm btn-secondary btn-block">
        <i class="bx bx-group"></i> Voir les eleves
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL AJOUTER -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-plus" style="color:var(--primary)"></i> Nouvelle classe</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom de la classe <span class="form-required">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="6eme A" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Niveau</label>
            <input type="text" name="level" class="form-control" placeholder="6eme">
          </div>
          <div class="form-group">
            <label class="form-label">Section</label>
            <input type="text" name="section" class="form-control" placeholder="A">
          </div>
          <div class="form-group">
            <label class="form-label">Capacite</label>
            <input type="number" name="capacity" class="form-control" value="35" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Salle</label>
            <input type="text" name="room" class="form-control" placeholder="Salle 01">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Professeur principal</label>
          <select name="class_teacher_id" class="form-control">
            <option value="">Aucun</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= clean($t['first_name']) ?> <?= clean($t['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Creer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFIER -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Modifier la classe</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"   value="edit">
      <input type="hidden" name="class_id" id="eId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom <span class="form-required">*</span></label>
          <input type="text" name="name" id="eName" class="form-control" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Niveau</label>
            <input type="text" name="level" id="eLevel" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Section</label>
            <input type="text" name="section" id="eSection" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Capacite</label>
            <input type="number" name="capacity" id="eCapacity" class="form-control" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Salle</label>
            <input type="text" name="room" id="eRoom" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Professeur principal</label>
          <select name="class_teacher_id" class="form-control">
            <option value="">Aucun</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= clean($t['first_name']) ?> <?= clean($t['last_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php
$pageScript = "
function openEdit(d) {
  document.getElementById('eId').value       = d.id;
  document.getElementById('eName').value     = d.name;
  document.getElementById('eLevel').value    = d.level || '';
  document.getElementById('eSection').value  = d.section || '';
  document.getElementById('eCapacity').value = d.capacity;
  document.getElementById('eRoom').value     = d.room || '';
  SS_Modal.open('modalEdit');
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
