<?php
// ============================================================
//  SmartSchool — Gestion des parents d'élèves
//  Emplacement : admin/parents.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
// Sécurité : On réutilise tes constantes ou rôles pour restreindre l'accès
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des parents';
$pageSection = 'parents';
$user        = currentUser();

// ── Traitement POST (Exemple de suppression) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $parentId = (int)$_POST['parent_id'] ?? 0;
        
        // Vérification de l'existence du parent
        $parentCheck = dbFetchOne("SELECT username FROM users WHERE id = ? AND role_id = (SELECT id FROM roles WHERE name = 'parent' LIMIT 1)", [$parentId]);
        
        if ($parentCheck) {
            // Nettoyage des liaisons enfants avant suppression (clé étrangère)
            dbExecute("DELETE FROM parent_student WHERE parent_id = ?", [$parentId]);
            // Suppression du compte utilisateur
            dbExecute("DELETE FROM users WHERE id = ?", [$parentId]);
            
            logActivity('delete_parent', "Compte parent supprime : @{$parentCheck['username']}");
            redirectWith(BASE_URL . '/admin/parents.php', 'success', 'Le compte parent a été supprimé avec succès.');
        }
        redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Compte parent introuvable.');
    }
}

// ── Liste des parents avec agrégation de leurs enfants ─────────────────────
$parents = dbFetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.username, u.is_active,
            GROUP_CONCAT(CONCAT(stud_u.first_name, ' ', stud_u.last_name) SEPARATOR ', ') AS enfants
     FROM users u
     JOIN roles r ON u.role_id = r.id
     LEFT JOIN parent_student ps ON u.id = ps.parent_id
     LEFT JOIN students s ON ps.student_id = s.id
     LEFT JOIN users stud_u ON s.user_id = stud_u.id
     WHERE r.name = 'parent'
     GROUP BY u.id
     ORDER BY u.last_name ASC, u.first_name ASC"
);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout"> <div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?> <div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-group" style="color:var(--primary)"></i>
      Gestion des parents
    </h1>
    <p><?= count($parents) ?> parent<?= count($parents) > 1 ? 's' : '' ?> enregistré(s)</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/admin/parents-add.php" class="btn btn-primary">
      <i class="bx bx-plus"></i> Nouveau parent
    </a>
  </div>
</div>

<?php if (empty($parents)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bx bx-group"></i></div>
      <h3>Aucun parent</h3>
      <p>Ajoutez un premier parent d'élève pour commencer le suivi familial.</p>
      <a href="<?= BASE_URL ?>/admin/parents-add.php" class="btn btn-primary">
        <i class="bx bx-plus"></i> Créer un compte parent
      </a>
    </div>
  </div>
<?php else: ?>
<div class="grid-auto"> <?php foreach ($parents as $p): 
    $statusColor = $p['is_active'] == 1 ? 'success' : 'danger';
  ?>
  <div class="card card-hover"> <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="avatar avatar-48" style="background:var(--grad-primary);color:#fff;font-weight:800;font-size:1.1rem;display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:var(--radius)">
          <?= getInitials($p['first_name'], $p['last_name']) ?>
        </div>
        <div class="td-actions">
          <a href="<?= BASE_URL ?>/admin/parents-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary btn-icon">
            <i class="bx bx-edit"></i>
          </a>
          <form method="POST" style="display:inline">
            <?= csrfField() ?> <input type="hidden" name="action"    value="delete">
            <input type="hidden" name="parent_id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                    onclick="return confirm('Supprimer définitivement le compte de <?= addslashes($p['first_name'] . ' ' . $p['last_name']) ?> ?')">
              <i class="bx bx-trash"></i>
            </button>
          </form>
        </div>
      </div>

      <h3 style="font-size:16px;font-weight:800;color:var(--text-primary);margin-bottom:2px">
        <?= clean($p['last_name'] . ' ' . $p['first_name']) ?>
      </h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">
        Utilisateur : <span style="font-weight:600;color:var(--primary)">@<?= clean($p['username']) ?></span>
      </p>

      <div style="font-size:13px;color:var(--text-primary);display:flex;flex-direction:column;gap:4px;margin-bottom:16px">
        <span style="display:flex;align-items:center;gap:6px">
          <i class="bx bx-envelope" style="color:var(--text-muted)"></i> <?= clean($p['email']) ?>
        </span>
        <?php if (!empty($p['phone'])): ?>
          <span style="display:flex;align-items:center;gap:6px">
            <i class="bx bx-phone" style="color:var(--text-muted)"></i> <?= clean($p['phone']) ?>
          </span>
        <?php endif; ?>
      </div>

      <div style="padding-top:12px;border-top:1px solid var(--border-light)">
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:700;letter-spacing:0.03em;margin-bottom:6px">
          Élève(s) associé(s)
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php if (!empty($p['enfants'])): 
            $enfantsArr = explode(', ', $p['enfants']);
            foreach ($enfantsArr as $enfant): ?>
              <span style="background:var(--primary-bg);color:var(--primary);font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:99px;display:inline-flex;align-items:center;gap:4px">
                <i class="bx bx-graduation"></i> <?= clean($enfant) ?>
              </span>
            <?php endforeach; ?>
          <?php else: ?>
            <span style="font-size:12.5px;color:var(--text-light);font-style:italic">
              <i class="bx bx-user-x"></i> Aucun enfant lié
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
</div>
</div>

<?php 
// Initialisation de ta variable de script de bas de page
$pageScript = "";
require_once INCLUDES_PATH . '/footer.php';
?>