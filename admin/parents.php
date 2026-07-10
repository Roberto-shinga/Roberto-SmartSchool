<?php
// ============================================================
//  SmartSchool — Gestion des parents d'élèves
//  Emplacement : admin/parents.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des parents';
$pageSection = 'parents';
$user        = currentUser();

// Récupération de tous les élèves actifs pour pouvoir les associer au parent dans la modale
$students = dbFetchAll(
    "SELECT s.id, u.first_name, u.last_name, c.name AS class_name 
     FROM students s 
     JOIN users u ON s.user_id = u.id 
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE s.status = 'actif' 
     ORDER BY u.last_name, u.first_name"
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $username  = trim($_POST['username']   ?? '');
        $password  = trim($_POST['password']   ?? '');
        $studentIds = $_POST['student_ids']    ?? []; // Tableau d'enfants sélectionnés

        // Vérifications de base
        if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
            redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Tous les champs obligatoires (*) doivent être remplis.');
        }

        // Vérification unicité email/username
        $check = dbFetchOne("SELECT id FROM users WHERE email = ? OR username = ?", [$email, $username]);
        if ($check) {
            redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Cet email ou nom d\'utilisateur est déjà pris.');
        }

        // Récupérer l'ID du rôle parent dynamiquement
        $roleParent = dbFetchOne("SELECT id FROM roles WHERE name = 'parent' LIMIT 1");
        $roleId = $roleParent['id'] ?? 5;

        // Hachage du mot de passe (utilise ta fonction native présente dans ton login)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insertion du compte parent dans `users`
        dbExecute(
            "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [$roleId, $username, $email, $hashedPassword, $firstName, $lastName, $phone]
        );

        // Récupération du parent inséré
        $newParentId = dbFetchOne("SELECT LAST_INSERT_ID() AS id")['id'];

        // Liaison avec le ou les enfants sélectionnés dans la table `parent_student`
        if (!empty($studentIds) && $newParentId) {
            foreach ($studentIds as $studentId) {
                dbExecute("INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)", [$newParentId, (int)$studentId]);
            }
        }

        logActivity('add_parent', "Parent ajoute : $firstName $lastName (@$username)");
        redirectWith(BASE_URL . '/admin/parents.php', 'success', "Compte parent pour $firstName $lastName créé avec succès !");
    }

    if ($action === 'delete') {
        $parentId = (int)$_POST['parent_id'] ?? 0;
        $parentCheck = dbFetchOne("SELECT username FROM users WHERE id = ? AND role_id = (SELECT id FROM roles WHERE name = 'parent' LIMIT 1)", [$parentId]);
        
        if ($parentCheck) {
            dbExecute("DELETE FROM parent_student WHERE parent_id = ?", [$parentId]);
            dbExecute("DELETE FROM users WHERE id = ?", [$parentId]);
            logActivity('delete_parent', "Compte parent supprime : @{$parentCheck['username']}");
            redirectWith(BASE_URL . '/admin/parents.php', 'success', 'Le compte parent a été supprimé.');
        }
        redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Compte parent introuvable.');
    }
}

// ── Liste des parents avec agrégation de leurs enfants ─────────────────────
$parents = dbFetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.username, u.is_active,
            GROUP_CONCAT(CONCAT(stud_u.first_name, ' ', stud_u.last_name, ' (', c.name, ')') SEPARATOR ', ') AS enfants
     FROM users u
     JOIN roles r ON u.role_id = r.id
     LEFT JOIN parent_student ps ON u.id = ps.parent_id
     LEFT JOIN students s ON ps.student_id = s.id
     LEFT JOIN users stud_u ON s.user_id = stud_u.id
     LEFT JOIN classes c ON s.class_id = c.id
     WHERE r.name = 'parent'
     GROUP BY u.id
     ORDER BY u.last_name ASC, u.first_name ASC"
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
      <i class="bx bx-group" style="color:var(--primary)"></i>
      Gestion des parents
    </h1>
    <p><?= count($parents) ?> parent<?= count($parents) > 1 ? 's' : '' ?> enregistré(s)</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Nouveau parent
    </button>
  </div>
</div>

<?php if (empty($parents)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bx bx-group"></i></div>
      <h3>Aucun parent</h3>
      <p>Créez votre premier compte parent pour commencer le suivi.</p>
      <button class="btn btn-primary" data-modal="modalAdd">
        <i class="bx bx-plus"></i> Créer un parent
      </button>
    </div>
  </div>
<?php else: ?>
<div class="grid-auto">
  <?php foreach ($parents as $p): ?>
  <div class="card card-hover">
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="avatar avatar-48" style="background:var(--grad-primary);color:#fff;font-weight:800;font-size:1.1rem;display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:var(--radius)">
          <?= getInitials($p['first_name'], $p['last_name']) ?>
        </div>
        <div class="td-actions">
          <button class="btn btn-sm btn-secondary btn-icon" onclick="alert('Lien de modification en cours d\'intégration')">
            <i class="bx bx-edit"></i>
          </button>
          <form method="POST" style="display:inline">
            <?= csrfField() ?>
            <input type="hidden" name="action"    value="delete">
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
        Identifiant : <span style="font-weight:600;color:var(--primary)">@<?= clean($p['username']) ?></span>
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
              <i class="bx bx-user-x"></i> Aucun élève associé
            </span>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="modalAdd">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-header">
      <h3><i class="bx bx-user-plus" style="color:var(--primary)"></i> Nouveau parent d'élève</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      
      <div class="modal-body">
        
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prénom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" placeholder="Jean" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" placeholder="Dupont" required>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Email <span class="form-required">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="parent@exemple.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="phone" class="form-control" placeholder="+225 07000000">
          </div>
        </div>

        <div class="grid-2" style="background: var(--bg-body); padding: 12px; border-radius: var(--radius); margin-bottom: 16px;">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Nom d'utilisateur <span class="form-required">*</span></label>
            <input type="text" name="username" class="form-control" placeholder="j.dupont" required>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Mot de passe provisoire <span class="form-required">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Associer à un ou plusieurs élèves</label>
          <select name="student_ids[]" class="form-control" multiple style="height: 120px; padding: 8px;">
            <?php foreach ($students as $s): ?>
              <option value="<?= $s['id'] ?>">
                <?= clean($s['last_name']) ?> <?= clean($s['first_name']) ?> (<?= clean($s['class_name'] ?? 'Sans classe') ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">
            <i class="bx bx-info-circle"></i> Maintenez la touche <kbd>Ctrl</kbd> (ou <kbd>Cmd</kbd> sur Mac) enfoncée pour sélectionner plusieurs enfants.
          </small>
        </div>

      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Créer le compte</button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php 
$pageScript = "";
require_once INCLUDES_PATH . '/footer.php';
?>