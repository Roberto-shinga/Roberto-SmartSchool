<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Frais scolaires';
$pageSection = 'fees';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current=1");
$yearId      = $currentYear['id'] ?? 1;
$levels      = dbFetchAll("SELECT * FROM levels WHERE is_active=1 ORDER BY order_index");
$categories  = dbFetchAll("SELECT * FROM fee_categories ORDER BY name");

// ── Traitement POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_fee') {
        $catId    = (int)($_POST['category_id'] ?? 0);
        $amount   = (float)($_POST['amount']    ?? 0);
        $levelId  = (int)($_POST['level_id']    ?? 0) ?: null;
        $classId  = (int)($_POST['class_id']    ?? 0) ?: null;
        $dueDate  = trim($_POST['due_date']     ?? '') ?: null;
        $desc     = trim($_POST['description']  ?? '');

        if ($catId && $amount > 0) {
            dbExecute(
                "INSERT INTO fees (category_id, academic_year_id, level_id, class_id, amount, due_date, description)
                 VALUES (?,?,?,?,?,?,?)",
                [$catId, $yearId, $levelId, $classId, $amount, $dueDate, $desc]
            );
            logActivity('add_fee', "Nouveau frais : cat #$catId — $amount FC");
            redirectWith(BASE_URL . '/admin/fees.php', 'success', 'Frais ajouté avec succès !');
        }
    }

    if ($action === 'add_category') {
        $name    = trim($_POST['cat_name']       ?? '');
        $desc    = trim($_POST['cat_desc']       ?? '');
        $recur   = isset($_POST['is_recurring'])  ? 1 : 0;
        if ($name) {
            dbExecute("INSERT INTO fee_categories (name, description, is_recurring) VALUES (?,?,?)", [$name, $desc, $recur]);
            redirectWith(BASE_URL . '/admin/fees.php', 'success', "Catégorie \"$name\" créée !");
        }
    }

    if ($action === 'delete_fee') {
        $feeId = (int)($_POST['fee_id'] ?? 0);
        $used  = dbFetchOne("SELECT COUNT(*) c FROM payments WHERE fee_id=?", [$feeId])['c'] ?? 0;
        if ($used > 0) {
            redirectWith(BASE_URL . '/admin/fees.php', 'danger',
                'Ce frais ne peut pas être supprimé car des paiements y sont associés.');
        } else {
            dbExecute("DELETE FROM fees WHERE id=?", [$feeId]);
            redirectWith(BASE_URL . '/admin/fees.php', 'success', 'Frais supprimé.');
        }
    }
}

// ── Données ───────────────────────────────────────────────────
$fees = dbFetchAll(
    "SELECT f.*, fc.name AS cat_name, fc.is_recurring,
            l.name AS level_name, c.name AS class_name,
            (SELECT COUNT(*) FROM payments p WHERE p.fee_id=f.id AND p.status='paye') paid_count,
            (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p WHERE p.fee_id=f.id AND p.status='paye') paid_total
     FROM fees f
     JOIN fee_categories fc ON f.category_id=fc.id
     LEFT JOIN levels l ON f.level_id=l.id
     LEFT JOIN classes c ON f.class_id=c.id
     WHERE f.academic_year_id=?
     ORDER BY fc.name, f.amount DESC",
    [$yearId]
);

$classes = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY name", [$yearId]);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1>Frais scolaires</h1>
    <p>Année <?= clean($currentYear['name'] ?? '—') ?> — <?= count($fees) ?> frais définis</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" data-modal="modalCategory">
      <i class="bx bx-plus"></i> Catégorie
    </button>
    <button class="btn btn-primary" data-modal="modalFee">
      <i class="bx bx-plus"></i> Nouveau frais
    </button>
  </div>
</div>

<!-- Tableau des frais -->
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Catégorie</th><th>Cible</th><th>Montant</th><th>Échéance</th>
        <th>Paiements</th><th>Collecté</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($fees)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-state-icon"><i class="bx bx-money"></i></div>
            <h3>Aucun frais défini</h3>
            <p>Définissez les frais de l'année scolaire.</p>
            <button class="btn btn-primary" data-modal="modalFee"><i class="bx bx-plus"></i> Ajouter</button>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($fees as $f): ?>
        <tr>
          <td>
            <div style="font-weight:600;color:var(--text-primary)"><?= clean($f['cat_name']) ?></div>
            <?php if ($f['description']): ?>
              <div style="font-size:12px;color:var(--text-muted)"><?= clean($f['description']) ?></div>
            <?php endif; ?>
            <?php if ($f['is_recurring']): ?>
              <span class="badge badge-info" style="font-size:10px">Récurrent</span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px">
            <?php if ($f['class_name']): ?>
              <span class="badge badge-purple"><?= clean($f['class_name']) ?></span>
            <?php elseif ($f['level_name']): ?>
              <span class="badge badge-primary"><?= clean($f['level_name']) ?></span>
            <?php else: ?>
              <span class="badge badge-gray">Tous les élèves</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:700;font-size:15px;color:var(--primary)"><?= formatMoney($f['amount']) ?></td>
          <td style="font-size:13px;color:var(--text-muted)">
            <?= $f['due_date'] ? formatDate($f['due_date']) : '—' ?>
          </td>
          <td>
            <span style="font-weight:600"><?= $f['paid_count'] ?></span>
            <span style="color:var(--text-muted);font-size:12px"> paiement<?= $f['paid_count'] > 1 ? 's' : '' ?></span>
          </td>
          <td style="font-weight:600;color:var(--success)"><?= formatMoney($f['paid_total']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete_fee">
              <input type="hidden" name="fee_id" value="<?= $f['id'] ?>">
              <button type="submit" class="btn btn-sm btn-ghost btn-icon"
                      data-confirm="Supprimer ce frais ? Les paiements associés seront conservés."
                      title="Supprimer">
                <i class="bx bx-trash" style="color:var(--danger)"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Catégories existantes -->
<div class="card" style="margin-top:24px">
  <div class="card-header">
    <h3><i class="bx bx-category"></i> Catégories de frais</h3>
    <button class="btn btn-sm btn-primary" data-modal="modalCategory">
      <i class="bx bx-plus"></i> Ajouter
    </button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;padding:16px">
    <?php foreach ($categories as $cat): ?>
    <div style="padding:14px;background:var(--bg-body);border:1px solid var(--border);border-radius:var(--radius-lg)">
      <div style="font-weight:700;color:var(--text-primary);margin-bottom:4px"><?= clean($cat['name']) ?></div>
      <?php if ($cat['description']): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px"><?= clean($cat['description']) ?></div>
      <?php endif; ?>
      <span class="badge <?= $cat['is_recurring'] ? 'badge-info' : 'badge-gray' ?>">
        <?= $cat['is_recurring'] ? 'Récurrent' : 'Ponctuel' ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

</div>
</div>

<!-- MODAL : Nouveau frais -->
<div class="modal-overlay" id="modalFee">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-money" style="color:var(--primary)"></i> Nouveau frais</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST" data-loading>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_fee">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Catégorie <span class="form-required">*</span></label>
          <select name="category_id" class="form-control" required>
            <option value="">Sélectionner une catégorie</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Montant (FC) <span class="form-required">*</span></label>
          <div class="input-wrap">
            <i class="bx bx-money input-icon"></i>
            <input type="number" name="amount" class="form-control" min="0" step="100" required placeholder="150000">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Niveau concerné</label>
            <select name="level_id" class="form-control">
              <option value="">Tous les niveaux</option>
              <?php foreach ($levels as $l): ?>
                <option value="<?= $l['id'] ?>"><?= clean($l['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classe concernée</label>
            <select name="class_id" class="form-control">
              <option value="">Toutes les classes</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Date d'échéance</label>
          <input type="date" name="due_date" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Précisions...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL : Nouvelle catégorie -->
<div class="modal-overlay" id="modalCategory">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3><i class="bx bx-category" style="color:var(--primary)"></i> Nouvelle catégorie</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST" data-loading>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_category">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom <span class="form-required">*</span></label>
          <input type="text" name="cat_name" class="form-control" required placeholder="Ex: Frais de transport">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="cat_desc" class="form-control" rows="2" placeholder="Description..."></textarea>
        </div>
        <label class="form-check">
          <input type="checkbox" name="is_recurring" class="form-check-input" checked>
          <span class="form-check-label">Frais récurrent (mensuel/trimestriel)</span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Créer</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>