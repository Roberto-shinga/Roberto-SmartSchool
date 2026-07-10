<?php
// ============================================================
//  SmartSchool — Gestion des parents
//  Emplacement : admin/parents.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des parents';
$pageSection = 'parents';
$user        = currentUser();

// Eleves actifs pour le lien parent-enfant
$students = dbFetchAll(
    "SELECT s.id, s.student_number, u.first_name, u.last_name
     FROM students s JOIN users u ON s.user_id = u.id
     WHERE s.status = 'actif' ORDER BY u.last_name"
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $firstName   = trim($_POST['first_name']   ?? '');
        $lastName    = trim($_POST['last_name']    ?? '');
        $email       = trim($_POST['email']        ?? '');
        $phone       = trim($_POST['phone']        ?? '');
        $gender      = trim($_POST['gender']       ?? 'F');
        $address     = trim($_POST['address']      ?? '');
        $studentId   = (int)($_POST['student_id']  ?? 0);
        $relationship= trim($_POST['relationship'] ?? 'parent');
        $password    = trim($_POST['password']     ?? 'SmartSchool2025!');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Prenom, nom et email obligatoires.');
        }

        $exists = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Email deja utilise.');
        }

        $username = strtolower($firstName . '.' . $lastName . rand(10,99));

        try {
            getDB()->beginTransaction();
            dbExecute(
                "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, gender, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [ROLE_PARENT, $username, $email, hashPassword($password),
                 $firstName, $lastName, $phone, $gender, $address]
            );
            $userId = dbLastId();

            if ($studentId) {
                dbExecute(
                    "INSERT INTO parent_student (parent_id, student_id, relationship, is_primary)
                     VALUES (?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)",
                    [$userId, $studentId, $relationship]
                );
            }

            getDB()->commit();
            logActivity('add_parent', "Parent ajoute : $firstName $lastName");
            redirectWith(BASE_URL . '/admin/parents.php', 'success', "Parent $firstName $lastName ajoute avec succes !");
        } catch (Exception $e) {
            getDB()->rollBack();
            redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    if ($action === 'edit') {
        $parentUserId = (int)($_POST['parent_user_id'] ?? 0);
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name']  ?? '');
        $email        = trim($_POST['email']      ?? '');
        $phone        = trim($_POST['phone']      ?? '');
        $address      = trim($_POST['address']    ?? '');

        dbExecute(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=? WHERE id=?",
            [$firstName, $lastName, $email, $phone, $address, $parentUserId]
        );
        logActivity('edit_parent', "Parent modifie : $firstName $lastName");
        redirectWith(BASE_URL . '/admin/parents.php', 'success', "Parent $firstName $lastName mis a jour.");
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $p      = dbFetchOne("SELECT first_name, last_name FROM users WHERE id = ? AND role_id = ?", [$userId, ROLE_PARENT]);
        if ($p) {
            dbExecute("DELETE FROM users WHERE id = ?", [$userId]);
            logActivity('delete_parent', "Parent supprime : {$p['first_name']} {$p['last_name']}");
            redirectWith(BASE_URL . '/admin/parents.php', 'success', 'Parent supprime.');
        }
        redirectWith(BASE_URL . '/admin/parents.php', 'danger', 'Parent introuvable.');
    }

    if ($action === 'link') {
        $parentId    = (int)($_POST['parent_id']   ?? 0);
        $studentId   = (int)($_POST['student_id']  ?? 0);
        $relationship= trim($_POST['relationship'] ?? 'parent');
        if ($parentId && $studentId) {
            dbExecute(
                "INSERT INTO parent_student (parent_id, student_id, relationship, is_primary)
                 VALUES (?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)",
                [$parentId, $studentId, $relationship]
            );
            redirectWith(BASE_URL . '/admin/parents.php', 'success', 'Lien parent-enfant cree.');
        }
    }
}

// ── Liste parents ────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = ["u.role_id = " . ROLE_PARENT];
$params = [];

if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $s        = "%$search%";
    $params   = [$s, $s, $s];
}

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total    = dbFetchOne("SELECT COUNT(*) c FROM users u $whereStr", $params)['c'] ?? 0;
$pag      = paginate($total, $page);

$parents = dbFetchAll(
    "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.address, u.is_active,
            GROUP_CONCAT(CONCAT(su.first_name,' ',su.last_name) SEPARATOR ', ') AS children_names,
            COUNT(ps.student_id) AS children_count
     FROM users u
     LEFT JOIN parent_student ps ON ps.parent_id = u.id
     LEFT JOIN students st ON ps.student_id = st.id
     LEFT JOIN users su ON st.user_id = su.id
     $whereStr
     GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.address, u.is_active
     ORDER BY u.last_name
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
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
    <p><?= $total ?> parent<?= $total > 1 ? 's' : '' ?> enregistre<?= $total > 1 ? 's' : '' ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-user-plus"></i> Nouveau parent
    </button>
  </div>
</div>

<!-- FILTRE -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end">
      <div class="search-wrap" style="flex:1">
        <i class="bx bx-search"></i>
        <input type="text" name="search" class="search-input"
               placeholder="Nom, email..."
               value="<?= clean($search) ?>">
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
      <?php if ($search): ?>
        <a href="<?= BASE_URL ?>/admin/parents.php" class="btn btn-secondary">
          <i class="bx bx-x"></i>
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLEAU -->
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Parent</th>
          <th>Telephone</th>
          <th>Enfant(s)</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parents)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="bx bx-group"></i></div>
                <h3>Aucun parent enregistre</h3>
                <p>Ajoutez des parents pour les lier aux eleves.</p>
                <button class="btn btn-primary" data-modal="modalAdd">
                  <i class="bx bx-user-plus"></i> Ajouter un parent
                </button>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($parents as $i => $p): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px"><?= $pag['offset']+$i+1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-40"
                     style="background:<?= $p['gender']==='F' ? '#ec4899' : '#6366f1' ?>">
                  <?= getInitials($p['first_name'], $p['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($p['first_name']) ?> <?= clean($p['last_name']) ?></div>
                  <div class="td-sub"><?= clean($p['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="color:var(--text-muted)"><?= clean($p['phone'] ?? '—') ?></td>
            <td>
              <?php if ($p['children_count'] > 0): ?>
                <div style="font-size:12.5px;color:var(--text-secondary)">
                  <span class="badge badge-primary"><?= $p['children_count'] ?> enfant<?= $p['children_count']>1?'s':'' ?></span>
                  <div style="margin-top:4px;color:var(--text-muted)"><?= clean($p['children_names']) ?></div>
                </div>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:12.5px">Aucun enfant lie</span>
                <button class="btn btn-xs btn-secondary" style="font-size:11px;padding:2px 8px;margin-left:6px"
                        onclick="openLink(<?= $p['user_id'] ?>)">
                  <i class="bx bx-link"></i> Lier
                </button>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?= $p['is_active'] ? 'success' : 'danger' ?>">
                <?= $p['is_active'] ? 'Actif' : 'Inactif' ?>
              </span>
            </td>
            <td>
              <div class="td-actions">
                <button class="btn btn-sm btn-secondary btn-icon"
                        title="Modifier"
                        onclick='openEdit(<?= json_encode([
                          "user_id"    => $p["user_id"],
                          "first_name" => $p["first_name"],
                          "last_name"  => $p["last_name"],
                          "email"      => $p["email"],
                          "phone"      => $p["phone"],
                          "address"    => $p["address"],
                        ], JSON_HEX_QUOT|JSON_HEX_APOS) ?>)'>
                  <i class="bx bx-edit"></i>
                </button>
                <button class="btn btn-sm btn-secondary btn-icon"
                        title="Lier un enfant"
                        onclick="openLink(<?= $p['user_id'] ?>)">
                  <i class="bx bx-link"></i>
                </button>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          onclick="return confirm('Supprimer ce parent ?')">
                    <i class="bx bx-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <span style="font-size:13px;color:var(--text-muted)">
      <?= $pag['offset']+1 ?> – <?= min($pag['offset']+$pag['per_page'],$total) ?> sur <?= $total ?>
    </span>
    <nav class="pagination">
      <?php if ($pag['has_prev']): ?>
        <a href="?page=<?= $pag['prev_page'] ?>&search=<?= urlencode($search) ?>"><i class="bx bx-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($pg=max(1,$pag['current_page']-2);$pg<=min($pag['total_pages'],$pag['current_page']+2);$pg++): ?>
        <?php if ($pg===$pag['current_page']): ?><span class="active"><?= $pg ?></span>
        <?php else: ?><a href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>"><?= $pg ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['has_next']): ?>
        <a href="?page=<?= $pag['next_page'] ?>&search=<?= urlencode($search) ?>"><i class="bx bx-chevron-right"></i></a>
      <?php endif; ?>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL AJOUTER -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-user-plus" style="color:var(--primary)"></i> Nouveau parent</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" placeholder="Sophie" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" placeholder="Durand" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email <span class="form-required">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="parent@exemple.fr" required>
          </div>
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" class="form-control" placeholder="+225 00 00 00 00">
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" class="form-control">
              <option value="F">Feminin</option>
              <option value="M">Masculin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Mot de passe initial</label>
            <input type="text" name="password" class="form-control" value="SmartSchool2025!">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <input type="text" name="address" class="form-control" placeholder="Adresse du parent">
        </div>
        <div style="border-top:1px solid var(--border-light);padding-top:16px;margin-top:4px">
          <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:12px">
            <i class="bx bx-link"></i> Lier a un enfant (optionnel)
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Enfant</label>
              <select name="student_id" class="form-control">
                <option value="">Selectionner</option>
                <?php foreach ($students as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?> (<?= clean($s['student_number']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Lien de parente</label>
              <select name="relationship" class="form-control">
                <option value="pere">Pere</option>
                <option value="mere">Mere</option>
                <option value="tuteur">Tuteur</option>
                <option value="tutrice">Tutrice</option>
                <option value="autre">Autre</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-user-plus"></i> Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFIER -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Modifier le parent</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"        value="edit">
      <input type="hidden" name="parent_user_id" id="eUserId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom</label>
            <input type="text" name="first_name" id="eFn" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom</label>
            <input type="text" name="last_name" id="eLn" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" id="eEmail" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" id="ePhone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <input type="text" name="address" id="eAddress" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL LIER ENFANT -->
<div class="modal-overlay" id="modalLink">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3><i class="bx bx-link" style="color:var(--primary)"></i> Lier un enfant</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"    value="link">
      <input type="hidden" name="parent_id" id="lParentId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Enfant <span class="form-required">*</span></label>
          <select name="student_id" class="form-control" required>
            <option value="">Selectionner</option>
            <?php foreach ($students as $s): ?>
              <option value="<?= $s['id'] ?>"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?> (<?= clean($s['student_number']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Lien de parente</label>
          <select name="relationship" class="form-control">
            <option value="pere">Pere</option>
            <option value="mere">Mere</option>
            <option value="tuteur">Tuteur</option>
            <option value="tutrice">Tutrice</option>
            <option value="autre">Autre</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-link"></i> Lier</button>
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
  document.getElementById('eUserId').value  = d.user_id;
  document.getElementById('eFn').value      = d.first_name;
  document.getElementById('eLn').value      = d.last_name;
  document.getElementById('eEmail').value   = d.email;
  document.getElementById('ePhone').value   = d.phone || '';
  document.getElementById('eAddress').value = d.address || '';
  SS_Modal.open('modalEdit');
}

function openLink(parentId) {
  document.getElementById('lParentId').value = parentId;
  SS_Modal.open('modalLink');
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
