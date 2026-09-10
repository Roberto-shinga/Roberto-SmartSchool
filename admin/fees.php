<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Frais scolaires';
$pageSection = 'fees';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Categorie de frais ─────────────────────────────────────
    if ($action === 'save_category') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = sanitizeString($_POST['name'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '');
        $recurring   = !empty($_POST['is_recurring']) ? 1 : 0;

        if (empty($name)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Le nom de la categorie est obligatoire.');
        } elseif ($id) {
            dbExecute("UPDATE fee_categories SET name=?, description=?, is_recurring=? WHERE id=?", [$name, $description ?: null, $recurring, $id]);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Categorie modifiee.');
        } else {
            dbExecute("INSERT INTO fee_categories (name, description, is_recurring) VALUES (?, ?, ?)", [$name, $description ?: null, $recurring]);
            logActivity('fee_category_created', $name);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Categorie creee.');
        }
    }

    // ── Frais (montant) ────────────────────────────────────────
    if ($action === 'save_fee') {
        $id          = (int)($_POST['id'] ?? 0);
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $levelId     = (int)($_POST['level_id'] ?? 0) ?: null;
        $classId     = (int)($_POST['class_id'] ?? 0) ?: null;
        $amount      = (float)($_POST['amount'] ?? 0);
        $dueDate     = $_POST['due_date'] ?: null;
        $description = sanitizeString($_POST['description'] ?? '');

        if (!$categoryId || $amount <= 0) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Categorie et montant valides obligatoires.');
        } elseif ($id) {
            dbExecute("UPDATE fees SET category_id=?, level_id=?, class_id=?, amount=?, due_date=?, description=? WHERE id=?",
                [$categoryId, $levelId, $classId, $amount, $dueDate, $description ?: null, $id]);
            logActivity('fee_updated', "Frais #$id modifie");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Frais modifie.');
        } else {
            dbExecute("INSERT INTO fees (category_id, academic_year_id, level_id, class_id, amount, due_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$categoryId, $yearId, $levelId, $classId, $amount, $dueDate, $description ?: null]);
            logActivity('fee_created', "Nouveau frais cree");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Frais cree.');
        }
    }

    if ($action === 'delete_fee') {
        $id = (int)($_POST['id'] ?? 0);
        if (dbCount('payments', 'fee_id=?', [$id]) > 0) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Impossible : des paiements sont deja lies a ce frais.');
        } else {
            dbExecute("DELETE FROM fees WHERE id=?", [$id]);
            logActivity('fee_deleted', "Frais #$id supprime");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Frais supprime.');
        }
    }
}

$categories = dbFetchAll("SELECT * FROM fee_categories ORDER BY name");
$fees = dbFetchAll(
    "SELECT f.*, fc.name AS category_name, l.name AS level_name, c.name AS class_name,
            (SELECT COUNT(*) FROM payments p WHERE p.fee_id = f.id AND p.status='paye') AS nb_paid
     FROM fees f
     JOIN fee_categories fc ON f.category_id = fc.id
     LEFT JOIN levels l ON f.level_id = l.id
     LEFT JOIN classes c ON f.class_id = c.id
     WHERE f.academic_year_id = ?
     ORDER BY f.due_date, fc.name",
    [$yearId]
);
$levelsList  = dbFetchAll("SELECT * FROM levels WHERE is_active=1 ORDER BY order_index");
$classesList = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Frais scolaires</h1><p>Categories et montants — annee <?= clean($currentYear['name'] ?? '—') ?></p></div>
      <div class="page-header-actions">
        <button class="btn btn-secondary" onclick="openCategoryModal()"><i class="bx bx-folder-plus"></i> Nouvelle categorie</button>
        <button class="btn btn-primary" onclick="openFeeModal()"><i class="bx bx-plus"></i> Nouveau frais</button>
      </div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><h3><i class="bx bx-category"></i> Categories de frais</h3></div>
      <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach ($categories as $cat): ?>
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;cursor:pointer"
             onclick='openCategoryModal(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)'>
          <div class="text-sm font-semibold"><?= clean($cat['name']) ?></div>
          <div class="text-xs text-muted"><?= $cat['is_recurring'] ? 'Recurrent' : 'Ponctuel' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="bx bx-money"></i> Frais de l'annee</h3></div>
      <?php if (empty($fees)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-money"></i></div>
          <h3>Aucun frais defini</h3>
          <p>Ajoute un frais pour cette annee scolaire.</p>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Categorie</th><th>Montant</th><th>Portee</th><th>Echeance</th><th>Paiements recus</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($fees as $f): ?>
            <tr data-fee='<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>'>
              <td class="text-sm font-semibold"><?= clean($f['category_name']) ?><div class="text-xs text-muted"><?= clean($f['description'] ?: '') ?></div></td>
              <td class="font-semibold"><?= formatMoney($f['amount']) ?></td>
              <td class="text-sm text-muted">
                <?= $f['class_name'] ? clean($f['class_name']) : ($f['level_name'] ? clean($f['level_name']) : 'Tout l\'etablissement') ?>
              </td>
              <td class="text-sm text-muted"><?= $f['due_date'] ? formatDate($f['due_date']) : '—' ?></td>
              <td class="text-sm"><?= (int)$f['nb_paid'] ?></td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openFeeModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
                <form method="POST" style="display:inline" data-confirm="Supprimer ce frais ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_fee">
                  <input type="hidden" name="id" value="<?= $f['id'] ?>">
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

<!-- ══ Modal : Categorie ══ -->
<div class="modal-overlay" id="categoryModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_category">
      <input type="hidden" name="id" id="cat_id" value="">
      <div class="modal-header">
        <h3 id="cat_title"><i class="bx bx-folder-plus"></i> Nouvelle categorie</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="name" id="cat_name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="cat_desc" class="form-control" rows="2"></textarea></div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-check"><input type="checkbox" name="is_recurring" id="cat_recurring" value="1" checked><span class="form-check-label">Frais recurrent (chaque annee)</span></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal : Frais ══ -->
<div class="modal-overlay" id="feeModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_fee">
      <input type="hidden" name="id" id="fee_id" value="">
      <div class="modal-header">
        <h3 id="fee_title"><i class="bx bx-plus"></i> Nouveau frais</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Categorie <span class="form-required">*</span></label>
          <select name="category_id" id="fee_category" class="form-control" required>
            <?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Montant (FC) <span class="form-required">*</span></label><input type="number" name="amount" id="fee_amount" class="form-control" min="0" step="100" required></div>
          <div class="form-group"><label class="form-label">Echeance</label><input type="date" name="due_date" id="fee_due" class="form-control"></div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Niveau (optionnel)</label>
            <select name="level_id" id="fee_level" class="form-control">
              <option value="">Tout l'etablissement</option>
              <?php foreach ($levelsList as $l): ?><option value="<?= $l['id'] ?>"><?= clean($l['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classe (optionnel)</label>
            <select name="class_id" id="fee_class" class="form-control">
              <option value="">Toutes les classes</option>
              <?php foreach ($classesList as $c): ?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Description</label>
          <input type="text" name="description" id="fee_desc" class="form-control">
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
function openCategoryModal(c) {
  const f = document.querySelector('#categoryModal form'); f.reset();
  document.getElementById('cat_title').innerHTML = c ? '<i class="bx bx-edit"></i> Modifier la categorie' : '<i class="bx bx-folder-plus"></i> Nouvelle categorie';
  document.getElementById('cat_id').value = c ? c.id : '';
  document.getElementById('cat_name').value = c ? c.name : '';
  document.getElementById('cat_desc').value = c ? (c.description || '') : '';
  document.getElementById('cat_recurring').checked = c ? !!parseInt(c.is_recurring) : true;
  SS.openModal('categoryModal');
}
function openFeeModal(f) {
  const form = document.querySelector('#feeModal form'); form.reset();
  document.getElementById('fee_title').innerHTML = f ? '<i class="bx bx-edit"></i> Modifier le frais' : '<i class="bx bx-plus"></i> Nouveau frais';
  document.getElementById('fee_id').value = f ? f.id : '';
  document.getElementById('fee_category').value = f ? f.category_id : '';
  document.getElementById('fee_amount').value = f ? f.amount : '';
  document.getElementById('fee_due').value = f ? (f.due_date || '') : '';
  document.getElementById('fee_level').value = f ? (f.level_id || '') : '';
  document.getElementById('fee_class').value = f ? (f.class_id || '') : '';
  document.getElementById('fee_desc').value = f ? (f.description || '') : '';
  SS.openModal('feeModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
