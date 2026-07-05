<?php
// ============================================================
//  SmartSchool — Gestion des matieres
//  Emplacement : admin/subjects.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des matieres';
$pageSection = 'subjects';
$user        = currentUser();

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $code  = strtoupper(trim($_POST['code'] ?? ''));
        $coeff = (float)($_POST['coefficient'] ?? 1);
        $color = trim($_POST['color'] ?? '#6366f1');
        $desc  = trim($_POST['description'] ?? '');

        if (empty($name) || empty($code)) {
            redirectWith(BASE_URL . '/admin/subjects.php', 'danger', 'Nom et code sont obligatoires.');
        }

        $exists = dbFetchOne("SELECT id FROM subjects WHERE code = ?", [$code]);
        if ($exists) {
            redirectWith(BASE_URL . '/admin/subjects.php', 'danger', 'Ce code de matiere existe deja.');
        }

        dbExecute(
            "INSERT INTO subjects (name, code, coefficient, color, description) VALUES (?, ?, ?, ?, ?)",
            [$name, $code, $coeff, $color, $desc]
        );
        logActivity('add_subject', "Matiere ajoutee : $name");
        redirectWith(BASE_URL . '/admin/subjects.php', 'success', "Matiere $name ajoutee avec succes !");
    }

    if ($action === 'edit') {
        $id    = (int)($_POST['subject_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $coeff = (float)($_POST['coefficient'] ?? 1);
        $color = trim($_POST['color'] ?? '#6366f1');
        $desc  = trim($_POST['description'] ?? '');
        $active= isset($_POST['is_active']) ? 1 : 0;

        dbExecute(
            "UPDATE subjects SET name=?, coefficient=?, color=?, description=?, is_active=? WHERE id=?",
            [$name, $coeff, $color, $desc, $active, $id]
        );
        logActivity('edit_subject', "Matiere modifiee : $name");
        redirectWith(BASE_URL . '/admin/subjects.php', 'success', "Matiere $name mise a jour.");
    }

    if ($action === 'delete') {
        $id  = (int)($_POST['subject_id'] ?? 0);
        $sub = dbFetchOne("SELECT name FROM subjects WHERE id = ?", [$id]);
        if ($sub) {
            dbExecute("DELETE FROM subjects WHERE id = ?", [$id]);
            logActivity('delete_subject', "Matiere supprimee : {$sub['name']}");
            redirectWith(BASE_URL . '/admin/subjects.php', 'success', 'Matiere supprimee.');
        }
        redirectWith(BASE_URL . '/admin/subjects.php', 'danger', 'Matiere introuvable ou utilisee ailleurs.');
    }
}

// Liste des matieres avec stats
$subjects = dbFetchAll(
    "SELECT s.*,
            (SELECT COUNT(*) FROM class_subjects cs WHERE cs.subject_id = s.id) AS class_count,
            (SELECT COUNT(*) FROM grades g JOIN class_subjects cs ON g.class_subject_id = cs.id WHERE cs.subject_id = s.id) AS grade_count
     FROM subjects s
     ORDER BY s.name"
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
      <i class="bx bx-book-open" style="color:var(--primary)"></i>
      Gestion des matieres
    </h1>
    <p><?= count($subjects) ?> matiere<?= count($subjects) > 1 ? 's' : '' ?> au programme</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Nouvelle matiere
    </button>
  </div>
</div>

<?php if (empty($subjects)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bx bx-book-open"></i></div>
      <h3>Aucune matiere</h3>
      <p>Ajoutez les matieres enseignees dans votre etablissement.</p>
      <button class="btn btn-primary" data-modal="modalAdd">
        <i class="bx bx-plus"></i> Ajouter une matiere
      </button>
    </div>
  </div>
<?php else: ?>
<div class="grid-auto">
  <?php foreach ($subjects as $s): ?>
  <div class="card card-hover">
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="width:48px;height:48px;border-radius:var(--radius);background:<?= clean($s['color']) ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;font-weight:800">
          <?= clean($s['code']) ?>
        </div>
        <div class="td-actions">
          <button class="btn btn-sm btn-secondary btn-icon"
                  onclick='openEdit(<?= json_encode([
                    "id"          => $s["id"],
                    "name"        => $s["name"],
                    "coefficient" => $s["coefficient"],
                    "color"       => $s["color"],
                    "description" => $s["description"],
                    "is_active"   => $s["is_active"],
                  ], JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>
            <i class="bx bx-edit"></i>
          </button>
          <form method="POST" style="display:inline">
            <?= csrfField() ?>
            <input type="hidden" name="action"     value="delete">
            <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                    onclick="return confirm('Supprimer la matiere <?= addslashes($s['name']) ?> ?')">
              <i class="bx bx-trash"></i>
            </button>
          </form>
        </div>
      </div>

      <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:4px">
        <?= clean($s['name']) ?>
      </h3>
      <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;min-height:18px">
        <?= clean($s['description'] ?: 'Aucune description') ?>
      </p>

      <div style="display:flex;gap:16px;padding-top:14px;border-top:1px solid var(--border-light)">
        <div>
          <div style="font-size:11px;color:var(--text-muted)">Coefficient</div>
          <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= number_format($s['coefficient'],1) ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted)">Classes</div>
          <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= $s['class_count'] ?></div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--text-muted)">Notes</div>
          <div style="font-size:15px;font-weight:700;color:var(--text-primary)"><?= $s['grade_count'] ?></div>
        </div>
        <div style="margin-left:auto">
          <span class="badge badge-<?= $s['is_active'] ? 'success' : 'gray' ?>">
            <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL AJOUTER -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-plus" style="color:var(--primary)"></i> Nouvelle matiere</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="Mathematiques" required>
          </div>
          <div class="form-group">
            <label class="form-label">Code <span class="form-required">*</span></label>
            <input type="text" name="code" class="form-control" placeholder="MATH" maxlength="10" required style="text-transform:uppercase">
          </div>
          <div class="form-group">
            <label class="form-label">Coefficient</label>
            <input type="number" name="coefficient" class="form-control" value="1" min="0.5" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Couleur</label>
            <input type="color" name="color" class="form-control" value="#6366f1" style="height:40px;padding:4px;cursor:pointer">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Description de la matiere..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFIER -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Modifier la matiere</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"     value="edit">
      <input type="hidden" name="subject_id" id="eId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="name" id="eName" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Coefficient</label>
            <input type="number" name="coefficient" id="eCoeff" class="form-control" min="0.5" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Couleur</label>
            <input type="color" name="color" id="eColor" class="form-control" style="height:40px;padding:4px;cursor:pointer">
          </div>
          <div class="form-group">
            <label class="form-label">Statut</label>
            <label class="form-check" style="margin-top:10px">
              <input type="checkbox" name="is_active" id="eActive" class="form-check-input">
              <span class="form-check-label">Matiere active</span>
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="eDesc" class="form-control" rows="2"></textarea>
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
  document.getElementById('eId').value     = d.id;
  document.getElementById('eName').value   = d.name;
  document.getElementById('eCoeff').value  = d.coefficient;
  document.getElementById('eColor').value  = d.color;
  document.getElementById('eDesc').value   = d.description || '';
  document.getElementById('eActive').checked = d.is_active == 1;
  SS_Modal.open('modalEdit');
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
