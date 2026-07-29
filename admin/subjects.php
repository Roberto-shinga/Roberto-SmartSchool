<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Matieres';
$pageSection = 'subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = sanitizeString($_POST['name'] ?? '');
        $code   = strtoupper(sanitizeString($_POST['code'] ?? ''));
        $coef   = max(0.5, min(10, (float)($_POST['coefficient'] ?? 1)));
        $color  = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6366f1';
        $levelId= (int)($_POST['level_id'] ?? 0) ?: null;

        if (empty($name) || empty($code)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Nom et code obligatoires.');
        } elseif (dbFetchOne("SELECT id FROM subjects WHERE code=? AND id != ?", [$code, $id])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Ce code matiere existe deja.');
        } elseif ($id) {
            dbExecute("UPDATE subjects SET name=?, code=?, coefficient=?, color=?, level_id=? WHERE id=?",
                [$name, $code, $coef, $color, $levelId, $id]);
            logActivity('subject_updated', "Matiere modifiee : $name");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Matiere modifiee.');
        } else {
            dbExecute("INSERT INTO subjects (name, code, coefficient, color, level_id) VALUES (?, ?, ?, ?, ?)",
                [$name, $code, $coef, $color, $levelId]);
            logActivity('subject_created', "Matiere creee : $name");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Matiere creee.');
        }
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = dbFetchOne("SELECT * FROM subjects WHERE id=?", [$id]);
        if ($s) {
            dbExecute("UPDATE subjects SET is_active=? WHERE id=?", [$s['is_active'] ? 0 : 1, $id]);
            logActivity('subject_toggled', $s['name']);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Statut mis a jour.');
        }
    }
}

$subjects = dbFetchAll(
    "SELECT s.*, l.name AS level_name,
            (SELECT COUNT(*) FROM teacher_assignments ta WHERE ta.subject_id = s.id) AS nb_assignments
     FROM subjects s LEFT JOIN levels l ON s.level_id = l.id
     ORDER BY s.name"
);
$levelsList = dbFetchAll("SELECT * FROM levels WHERE is_active=1 ORDER BY order_index");

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Matieres</h1><p><?= count($subjects) ?> matiere(s)</p></div>
      <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openSubjectModal()"><i class="bx bx-plus"></i> Ajouter une matiere</button>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Matiere</th><th>Code</th><th>Coefficient</th><th>Niveau</th><th>Affectations</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($subjects as $s): ?>
            <tr data-subject='<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>'>
              <td>
                <div class="flex items-center gap-2">
                  <span style="width:10px;height:10px;border-radius:50%;background:<?= clean($s['color']) ?>;display:inline-block"></span>
                  <span class="font-semibold"><?= clean($s['name']) ?></span>
                </div>
              </td>
              <td class="text-sm font-mono"><?= clean($s['code']) ?></td>
              <td class="text-sm"><?= number_format((float)$s['coefficient'], 2) ?></td>
              <td class="text-sm text-muted"><?= clean($s['level_name'] ?: 'Tous niveaux') ?></td>
              <td class="text-sm text-muted"><?= (int)$s['nb_assignments'] ?> enseignant(s)</td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button type="submit" class="badge badge-<?= $s['is_active'] ? 'success' : 'gray' ?>" style="border:none;cursor:pointer">
                    <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                  </button>
                </form>
              </td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openSubjectModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>
                  <i class="bx bx-edit"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- ══ Modal : Ajouter / modifier une matiere ══ -->
<div class="modal-overlay" id="subjectModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="sub_id" value="">
      <div class="modal-header">
        <h3 id="sub_modal_title"><i class="bx bx-book-open"></i> Ajouter une matiere</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="name" id="sub_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Code <span class="form-required">*</span></label>
            <input type="text" name="code" id="sub_code" class="form-control" maxlength="20" style="text-transform:uppercase" required>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Coefficient</label>
            <input type="number" name="coefficient" id="sub_coef" class="form-control" step="0.5" min="0.5" max="10" value="1">
          </div>
          <div class="form-group">
            <label class="form-label">Couleur</label>
            <input type="color" name="color" id="sub_color" class="form-control" style="height:42px;padding:4px" value="#6366f1">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Niveau (optionnel)</label>
          <select name="level_id" id="sub_level" class="form-control">
            <option value="">Tous niveaux</option>
            <?php foreach ($levelsList as $l): ?>
              <option value="<?= $l['id'] ?>"><?= clean($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
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
function openSubjectModal(s) {
  const f = document.querySelector('#subjectModal form');
  f.reset();
  document.getElementById('sub_modal_title').innerHTML = s
    ? '<i class="bx bx-edit"></i> Modifier la matiere'
    : '<i class="bx bx-book-open"></i> Ajouter une matiere';
  document.getElementById('sub_id').value    = s ? s.id : '';
  document.getElementById('sub_name').value  = s ? s.name : '';
  document.getElementById('sub_code').value  = s ? s.code : '';
  document.getElementById('sub_coef').value  = s ? s.coefficient : 1;
  document.getElementById('sub_color').value = s ? s.color : '#6366f1';
  document.getElementById('sub_level').value = s ? (s.level_id || '') : '';
  SS.openModal('subjectModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
