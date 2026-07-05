<?php
// ============================================================
//  SmartSchool — Frais scolaires
//  Emplacement : admin/fees.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT);

$pageTitle   = 'Frais scolaires';
$pageSection = 'fees';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;
$classes     = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name", [$yearId]);
$categories  = dbFetchAll("SELECT id, name FROM fee_categories ORDER BY name");

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $classId    = (int)($_POST['class_id']    ?? 0) ?: null;
        $amount     = (float)($_POST['amount']    ?? 0);
        $dueDate    = trim($_POST['due_date']     ?? '');
        $desc       = trim($_POST['description']  ?? '');

        if (!$categoryId || $amount <= 0) {
            redirectWith(BASE_URL . '/admin/fees.php', 'danger', 'Categorie et montant sont obligatoires.');
        }

        dbExecute(
            "INSERT INTO fees (category_id, academic_year_id, class_id, amount, due_date, description)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$categoryId, $yearId, $classId, $amount, $dueDate ?: null, $desc]
        );
        logActivity('add_fee', "Frais ajoute : $amount FCFA");
        redirectWith(BASE_URL . '/admin/fees.php', 'success', 'Frais ajoute avec succes !');
    }

    if ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name) {
            dbExecute("INSERT INTO fee_categories (name, is_recurring) VALUES (?, 1)", [$name]);
            redirectWith(BASE_URL . '/admin/fees.php', 'success', "Categorie $name ajoutee.");
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['fee_id'] ?? 0);
        dbExecute("DELETE FROM fees WHERE id = ?", [$id]);
        logActivity('delete_fee', "Frais supprime : ID $id");
        redirectWith(BASE_URL . '/admin/fees.php', 'success', 'Frais supprime.');
    }
}

// Liste des frais avec stats de paiement
$fees = dbFetchAll(
    "SELECT f.id, f.amount, f.due_date, f.description,
            fc.name AS category_name,
            c.name AS class_name,
            (SELECT COUNT(*) FROM payments p WHERE p.fee_id = f.id AND p.status='paye') AS paid_count,
            (SELECT COALESCE(SUM(amount_paid),0) FROM payments p WHERE p.fee_id = f.id AND p.status='paye') AS total_collected
     FROM fees f
     JOIN fee_categories fc ON f.category_id = fc.id
     LEFT JOIN classes c ON f.class_id = c.id
     WHERE f.academic_year_id = ?
     ORDER BY f.due_date ASC",
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
      <i class="bx bx-money" style="color:var(--primary)"></i>
      Frais scolaires
    </h1>
    <p><?= count($fees) ?> type<?= count($fees) > 1 ? 's' : '' ?> de frais — <?= clean($currentYear['name'] ?? '') ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" data-modal="modalCategory">
      <i class="bx bx-folder-plus"></i> Categorie
    </button>
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Nouveaux frais
    </button>
  </div>
</div>

<?php if (empty($fees)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bx bx-money"></i></div>
      <h3>Aucun frais configure</h3>
      <p>Definissez les frais scolaires pour cette annee.</p>
      <button class="btn btn-primary" data-modal="modalAdd">
        <i class="bx bx-plus"></i> Ajouter des frais
      </button>
    </div>
  </div>
<?php else: ?>
<div class="grid-auto">
  <?php foreach ($fees as $f):
    $isOverdue = $f['due_date'] && strtotime($f['due_date']) < time();
  ?>
  <div class="card card-hover">
    <div class="card-body">
      <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:14px">
        <div>
          <span class="badge badge-primary"><?= clean($f['category_name']) ?></span>
        </div>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="fee_id" value="<?= $f['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                  onclick="return confirm('Supprimer ces frais ?')">
            <i class="bx bx-trash"></i>
          </button>
        </form>
      </div>

      <div style="font-size:24px;font-weight:800;color:var(--text-primary);margin-bottom:4px">
        <?= formatMoney($f['amount']) ?>
      </div>
      <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px">
        <?= clean($f['description'] ?: 'Aucune description') ?>
      </p>

      <div style="display:flex;flex-direction:column;gap:8px;padding-top:14px;border-top:1px solid var(--border-light)">
        <div style="display:flex;justify-content:space-between;font-size:12.5px">
          <span style="color:var(--text-muted)">Classe concernee</span>
          <span style="font-weight:600;color:var(--text-primary)"><?= clean($f['class_name'] ?? 'Toutes') ?></span>
        </div>
        <?php if ($f['due_date']): ?>
        <div style="display:flex;justify-content:space-between;font-size:12.5px">
          <span style="color:var(--text-muted)">Date limite</span>
          <span style="font-weight:600;color:<?= $isOverdue ? 'var(--danger)' : 'var(--text-primary)' ?>">
            <?= formatDate($f['due_date']) ?>
            <?php if ($isOverdue): ?> <i class="bx bx-error-circle"></i><?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-size:12.5px">
          <span style="color:var(--text-muted)">Paiements recus</span>
          <span style="font-weight:600;color:var(--success)"><?= $f['paid_count'] ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12.5px">
          <span style="color:var(--text-muted)">Total collecte</span>
          <span style="font-weight:700;color:var(--success)"><?= formatMoney($f['total_collected']) ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL AJOUTER FRAIS -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-plus" style="color:var(--primary)"></i> Nouveaux frais</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Categorie <span class="form-required">*</span></label>
          <select name="category_id" class="form-control" required>
            <option value="">Selectionner</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Montant <span class="form-required">*</span></label>
            <input type="number" name="amount" class="form-control" min="1" step="100" required>
          </div>
          <div class="form-group">
            <label class="form-label">Date limite</label>
            <input type="date" name="due_date" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Classe concernee</label>
          <select name="class_id" class="form-control">
            <option value="">Toutes les classes</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL NOUVELLE CATEGORIE -->
<div class="modal-overlay" id="modalCategory">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3><i class="bx bx-folder-plus" style="color:var(--primary)"></i> Nouvelle categorie</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_category">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom de la categorie <span class="form-required">*</span></label>
          <input type="text" name="cat_name" class="form-control" placeholder="Ex : Frais d'examen" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Creer</button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
