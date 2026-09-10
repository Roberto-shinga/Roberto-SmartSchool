<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Structure academique';
$pageSection = 'academic-structure';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Cycles ───────────────────────────────────────────────────
    if ($action === 'save_cycle') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = sanitizeString($_POST['name'] ?? '');
        $code = strtoupper(sanitizeString($_POST['code'] ?? ''));
        $order = (int)($_POST['order_index'] ?? 0);

        if (empty($name) || empty($code)) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=cycles', 'danger', 'Nom et code obligatoires.');
        } elseif (dbFetchOne("SELECT id FROM cycles WHERE code=? AND id!=?", [$code, $id])) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=cycles', 'danger', 'Ce code de cycle existe deja.');
        } elseif ($id) {
            dbExecute("UPDATE cycles SET name=?, code=?, order_index=? WHERE id=?", [$name, $code, $order, $id]);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=cycles', 'success', 'Cycle modifie.');
        } else {
            dbExecute("INSERT INTO cycles (name, code, order_index) VALUES (?, ?, ?)", [$name, $code, $order]);
            logActivity('cycle_created', $name);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=cycles', 'success', 'Cycle cree.');
        }
    }

    if ($action === 'toggle_cycle') {
        $id = (int)($_POST['id'] ?? 0);
        $c  = dbFetchOne("SELECT * FROM cycles WHERE id=?", [$id]);
        if ($c) dbExecute("UPDATE cycles SET is_active=? WHERE id=?", [$c['is_active'] ? 0 : 1, $id]);
        redirectWith($_SERVER['PHP_SELF'] . '?tab=cycles', 'success', 'Statut mis a jour.');
    }

    // ── Niveaux ──────────────────────────────────────────────────
    if ($action === 'save_level') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = sanitizeString($_POST['name'] ?? '');
        $code    = strtoupper(sanitizeString($_POST['code'] ?? ''));
        $cycleId = (int)($_POST['cycle_id'] ?? 0) ?: null;
        $order   = (int)($_POST['order_index'] ?? 0);

        if (empty($name) || empty($code)) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=levels', 'danger', 'Nom et code obligatoires.');
        } elseif (dbFetchOne("SELECT id FROM levels WHERE code=? AND id!=?", [$code, $id])) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=levels', 'danger', 'Ce code de niveau existe deja.');
        } elseif ($id) {
            dbExecute("UPDATE levels SET name=?, code=?, cycle_id=?, order_index=? WHERE id=?", [$name, $code, $cycleId, $order, $id]);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=levels', 'success', 'Niveau modifie.');
        } else {
            dbExecute("INSERT INTO levels (name, code, cycle_id, order_index) VALUES (?, ?, ?, ?)", [$name, $code, $cycleId, $order]);
            logActivity('level_created', $name);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=levels', 'success', 'Niveau cree.');
        }
    }

    if ($action === 'toggle_level') {
        $id = (int)($_POST['id'] ?? 0);
        $l  = dbFetchOne("SELECT * FROM levels WHERE id=?", [$id]);
        if ($l) dbExecute("UPDATE levels SET is_active=? WHERE id=?", [$l['is_active'] ? 0 : 1, $id]);
        redirectWith($_SERVER['PHP_SELF'] . '?tab=levels', 'success', 'Statut mis a jour.');
    }

    // ── Sections ─────────────────────────────────────────────────
    if ($action === 'save_section') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = sanitizeString($_POST['name'] ?? '');
        $code    = strtoupper(sanitizeString($_POST['code'] ?? ''));
        $cycleId = (int)($_POST['cycle_id'] ?? 0) ?: null;

        if (empty($name)) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=sections', 'danger', 'Nom obligatoire.');
        } elseif ($id) {
            dbExecute("UPDATE sections SET name=?, code=?, cycle_id=? WHERE id=?", [$name, $code ?: null, $cycleId, $id]);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=sections', 'success', 'Section modifiee.');
        } else {
            dbExecute("INSERT INTO sections (name, code, cycle_id) VALUES (?, ?, ?)", [$name, $code ?: null, $cycleId]);
            logActivity('section_created', $name);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=sections', 'success', 'Section creee.');
        }
    }

    if ($action === 'toggle_section') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = dbFetchOne("SELECT * FROM sections WHERE id=?", [$id]);
        if ($s) dbExecute("UPDATE sections SET is_active=? WHERE id=?", [$s['is_active'] ? 0 : 1, $id]);
        redirectWith($_SERVER['PHP_SELF'] . '?tab=sections', 'success', 'Statut mis a jour.');
    }

    // ── Options / filieres (table existante school_options) ──────
    if ($action === 'save_option') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = sanitizeString($_POST['name'] ?? '');
        $code    = strtoupper(sanitizeString($_POST['code'] ?? ''));
        $levelId = (int)($_POST['level_id'] ?? 0) ?: null;

        if (empty($name) || empty($code)) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=options', 'danger', 'Nom et code obligatoires.');
        } elseif (dbFetchOne("SELECT id FROM school_options WHERE code=? AND id!=?", [$code, $id])) {
            redirectWith($_SERVER['PHP_SELF'] . '?tab=options', 'danger', 'Ce code d\'option existe deja.');
        } elseif ($id) {
            dbExecute("UPDATE school_options SET name=?, code=?, level_id=? WHERE id=?", [$name, $code, $levelId, $id]);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=options', 'success', 'Option modifiee.');
        } else {
            dbExecute("INSERT INTO school_options (name, code, level_id) VALUES (?, ?, ?)", [$name, $code, $levelId]);
            logActivity('option_created', $name);
            redirectWith($_SERVER['PHP_SELF'] . '?tab=options', 'success', 'Option creee.');
        }
    }

    if ($action === 'toggle_option') {
        $id = (int)($_POST['id'] ?? 0);
        $o  = dbFetchOne("SELECT * FROM school_options WHERE id=?", [$id]);
        if ($o) dbExecute("UPDATE school_options SET is_active=? WHERE id=?", [$o['is_active'] ? 0 : 1, $id]);
        redirectWith($_SERVER['PHP_SELF'] . '?tab=options', 'success', 'Statut mis a jour.');
    }
}

$tab = $_GET['tab'] ?? 'cycles';

$cycles   = dbFetchAll("SELECT c.*, (SELECT COUNT(*) FROM levels WHERE cycle_id=c.id) AS nb_levels FROM cycles c ORDER BY order_index");
$levels   = dbFetchAll("SELECT l.*, c.name AS cycle_name FROM levels l LEFT JOIN cycles c ON l.cycle_id=c.id ORDER BY l.order_index");
$sections = dbFetchAll("SELECT s.*, c.name AS cycle_name FROM sections s LEFT JOIN cycles c ON s.cycle_id=c.id ORDER BY s.name");
$options  = dbFetchAll("SELECT o.*, l.name AS level_name FROM school_options o LEFT JOIN levels l ON o.level_id=l.id ORDER BY o.name");
$allCycles = dbFetchAll("SELECT * FROM cycles WHERE is_active=1 ORDER BY order_index");
$allLevels = dbFetchAll("SELECT * FROM levels WHERE is_active=1 ORDER BY order_index");

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Structure academique</h1>
        <p>Cycles, niveaux, sections et options — configurables ici, jamais codes en dur</p>
      </div>
    </div>

    <div class="tabs">
      <a href="?tab=cycles" class="tab-btn <?= $tab==='cycles'?'active':'' ?>">Cycles (<?= count($cycles) ?>)</a>
      <a href="?tab=levels" class="tab-btn <?= $tab==='levels'?'active':'' ?>">Niveaux (<?= count($levels) ?>)</a>
      <a href="?tab=sections" class="tab-btn <?= $tab==='sections'?'active':'' ?>">Sections (<?= count($sections) ?>)</a>
      <a href="?tab=options" class="tab-btn <?= $tab==='options'?'active':'' ?>">Options / filieres (<?= count($options) ?>)</a>
    </div>

    <!-- ══ Cycles ══ -->
    <?php if ($tab === 'cycles'): ?>
    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-layer"></i> Cycles</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('cycle')"><i class="bx bx-plus"></i> Ajouter</button>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Ordre</th><th>Nom</th><th>Code</th><th>Niveaux</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($cycles as $c): ?>
            <tr>
              <td class="text-sm text-muted"><?= (int)$c['order_index'] ?></td>
              <td class="text-sm font-semibold"><?= clean($c['name']) ?></td>
              <td class="text-sm font-mono"><?= clean($c['code']) ?></td>
              <td class="text-sm text-muted"><?= (int)$c['nb_levels'] ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?><input type="hidden" name="action" value="toggle_cycle"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="badge badge-<?= $c['is_active']?'success':'gray' ?>" style="border:none;cursor:pointer"><?= $c['is_active']?'Actif':'Inactif' ?></button>
                </form>
              </td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openModal("cycle", <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ Niveaux ══ -->
    <?php if ($tab === 'levels'): ?>
    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-list-ol"></i> Niveaux</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('level')"><i class="bx bx-plus"></i> Ajouter</button>
      </div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Ordre</th><th>Nom</th><th>Code</th><th>Cycle</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($levels as $l): ?>
            <tr>
              <td class="text-sm text-muted"><?= (int)$l['order_index'] ?></td>
              <td class="text-sm font-semibold"><?= clean($l['name']) ?></td>
              <td class="text-sm font-mono"><?= clean($l['code']) ?></td>
              <td class="text-sm text-muted"><?= clean($l['cycle_name'] ?: '—') ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?><input type="hidden" name="action" value="toggle_level"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                  <button type="submit" class="badge badge-<?= $l['is_active']?'success':'gray' ?>" style="border:none;cursor:pointer"><?= $l['is_active']?'Actif':'Inactif' ?></button>
                </form>
              </td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openModal("level", <?= htmlspecialchars(json_encode($l), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ Sections ══ -->
    <?php if ($tab === 'sections'): ?>
    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-git-branch"></i> Sections</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('section')"><i class="bx bx-plus"></i> Ajouter</button>
      </div>
      <?php if (empty($sections)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-git-branch"></i></div><h3>Aucune section</h3><p>Ex : scientifique, commerciale, litteraire (Humanites).</p></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Nom</th><th>Code</th><th>Cycle</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($sections as $s): ?>
            <tr>
              <td class="text-sm font-semibold"><?= clean($s['name']) ?></td>
              <td class="text-sm font-mono"><?= clean($s['code'] ?: '—') ?></td>
              <td class="text-sm text-muted"><?= clean($s['cycle_name'] ?: '—') ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?><input type="hidden" name="action" value="toggle_section"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button type="submit" class="badge badge-<?= $s['is_active']?'success':'gray' ?>" style="border:none;cursor:pointer"><?= $s['is_active']?'Active':'Inactive' ?></button>
                </form>
              </td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openModal("section", <?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ Options / filieres ══ -->
    <?php if ($tab === 'options'): ?>
    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-category-alt"></i> Options / filieres</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('option')"><i class="bx bx-plus"></i> Ajouter</button>
      </div>
      <?php if (empty($options)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-category-alt"></i></div><h3>Aucune option</h3><p>Ex : filieres techniques et professionnelles.</p></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Nom</th><th>Code</th><th>Niveau</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($options as $o): ?>
            <tr>
              <td class="text-sm font-semibold"><?= clean($o['name']) ?></td>
              <td class="text-sm font-mono"><?= clean($o['code']) ?></td>
              <td class="text-sm text-muted"><?= clean($o['level_name'] ?: 'Tous niveaux') ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?><input type="hidden" name="action" value="toggle_option"><input type="hidden" name="id" value="<?= $o['id'] ?>">
                  <button type="submit" class="badge badge-<?= $o['is_active']?'success':'gray' ?>" style="border:none;cursor:pointer"><?= $o['is_active']?'Active':'Inactive' ?></button>
                </form>
              </td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openModal("option", <?= htmlspecialchars(json_encode($o), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Cycle ══ -->
<div class="modal-overlay" id="cycleModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_cycle">
      <input type="hidden" name="id" id="cyc_id" value="">
      <div class="modal-header"><h3 id="cyc_title"><i class="bx bx-plus"></i> Nouveau cycle</h3><button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="name" id="cyc_name" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Code <span class="form-required">*</span></label><input type="text" name="code" id="cyc_code" class="form-control" style="text-transform:uppercase" required></div>
          <div class="form-group"><label class="form-label">Ordre</label><input type="number" name="order_index" id="cyc_order" class="form-control" value="0"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Annuler</button><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- ══ Modal : Niveau ══ -->
<div class="modal-overlay" id="levelModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_level">
      <input type="hidden" name="id" id="lvl_id" value="">
      <div class="modal-header"><h3 id="lvl_title"><i class="bx bx-plus"></i> Nouveau niveau</h3><button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="name" id="lvl_name" class="form-control" placeholder="Ex : 7eme Annee" required></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Code <span class="form-required">*</span></label><input type="text" name="code" id="lvl_code" class="form-control" style="text-transform:uppercase" required></div>
          <div class="form-group"><label class="form-label">Ordre</label><input type="number" name="order_index" id="lvl_order" class="form-control" value="0"></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Cycle</label>
          <select name="cycle_id" id="lvl_cycle" class="form-control">
            <option value="">Aucun</option>
            <?php foreach ($allCycles as $c): ?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Annuler</button><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- ══ Modal : Section ══ -->
<div class="modal-overlay" id="sectionModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_section">
      <input type="hidden" name="id" id="sec_id" value="">
      <div class="modal-header"><h3 id="sec_title"><i class="bx bx-plus"></i> Nouvelle section</h3><button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="name" id="sec_name" class="form-control" placeholder="Ex : Scientifique" required></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Code</label><input type="text" name="code" id="sec_code" class="form-control" style="text-transform:uppercase"></div>
          <div class="form-group">
            <label class="form-label">Cycle</label>
            <select name="cycle_id" id="sec_cycle" class="form-control">
              <option value="">Aucun</option>
              <?php foreach ($allCycles as $c): ?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Annuler</button><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- ══ Modal : Option ══ -->
<div class="modal-overlay" id="optionModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_option">
      <input type="hidden" name="id" id="opt_id" value="">
      <div class="modal-header"><h3 id="opt_title"><i class="bx bx-plus"></i> Nouvelle option</h3><button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button></div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="name" id="opt_name" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Code <span class="form-required">*</span></label><input type="text" name="code" id="opt_code" class="form-control" style="text-transform:uppercase" required></div>
          <div class="form-group">
            <label class="form-label">Niveau</label>
            <select name="level_id" id="opt_level" class="form-control">
              <option value="">Tous niveaux</option>
              <?php foreach ($allLevels as $l): ?><option value="<?= $l['id'] ?>"><?= clean($l['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Annuler</button><button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function openModal(type, data) {
  const prefix = {cycle:'cyc', level:'lvl', section:'sec', option:'opt'}[type];
  const modalId = type + 'Modal';
  const f = document.querySelector('#' + modalId + ' form');
  f.reset();
  document.getElementById(prefix + '_title').innerHTML = data
    ? '<i class="bx bx-edit"></i> Modifier'
    : '<i class="bx bx-plus"></i> Nouveau';
  document.getElementById(prefix + '_id').value = data ? data.id : '';
  document.getElementById(prefix + '_name').value = data ? data.name : '';
  if (document.getElementById(prefix + '_code')) document.getElementById(prefix + '_code').value = data ? (data.code || '') : '';
  if (document.getElementById(prefix + '_order')) document.getElementById(prefix + '_order').value = data ? data.order_index : 0;
  if (document.getElementById(prefix + '_cycle')) document.getElementById(prefix + '_cycle').value = data ? (data.cycle_id || '') : '';
  if (document.getElementById(prefix + '_level')) document.getElementById(prefix + '_level').value = data ? (data.level_id || '') : '';
  SS.openModal(modalId);
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
