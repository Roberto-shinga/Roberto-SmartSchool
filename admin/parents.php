<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Parents';
$pageSection = 'parents';
$admin       = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Creer un compte parent directement ────────────────────
    if ($action === 'create') {
        $firstName = sanitizeString($_POST['first_name'] ?? '');
        $lastName  = sanitizeString($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email']     ?? ''));
        $phone     = sanitizeString($_POST['phone']      ?? '');

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Adresse email invalide.');
        } elseif (dbFetchOne("SELECT id FROM users WHERE email=?", [$email])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette adresse email est deja utilisee.');
        } else {
            $tempPwd  = generateTempPassword();
            $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
            dbExecute(
                "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, is_active, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)",
                [ROLE_PARENT, $username, $email, hashPassword($tempPwd), $firstName, $lastName, $phone]
            );
            logActivity('parent_created', "Parent cree : $firstName $lastName");
            $_SESSION['flash_temp_pwd'] = ['name' => "$firstName $lastName", 'username' => $username, 'password' => $tempPwd];
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Compte parent cree.');
        }
    }

    // ── Lier un enfant ─────────────────────────────────────────
    if ($action === 'link') {
        $parentId  = (int)($_POST['parent_id']  ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $rel       = sanitizeString($_POST['relationship'] ?? 'parent');
        $isPrimary = !empty($_POST['is_primary']) ? 1 : 0;

        if (!$parentId || !$studentId) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Selectionnez un parent et un eleve.');
        } elseif (dbFetchOne("SELECT 1 FROM parent_student WHERE parent_id=? AND student_id=?", [$parentId, $studentId])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cet enfant est deja lie a ce parent.');
        } else {
            dbExecute("INSERT INTO parent_student (parent_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)",
                [$parentId, $studentId, $rel, $isPrimary]);
            logActivity('parent_child_linked', "Parent #$parentId <-> Eleve #$studentId");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Enfant lie avec succes.');
        }
    }

    // ── Delier un enfant ────────────────────────────────────────
    if ($action === 'unlink') {
        $parentId  = (int)($_POST['parent_id']  ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        dbExecute("DELETE FROM parent_student WHERE parent_id=? AND student_id=?", [$parentId, $studentId]);
        logActivity('parent_child_unlinked', "Parent #$parentId <-> Eleve #$studentId");
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Enfant delie.');
    }
}

$tempPwdShow = null;
if (!empty($_SESSION['flash_temp_pwd'])) { $tempPwdShow = $_SESSION['flash_temp_pwd']; unset($_SESSION['flash_temp_pwd']); }

$search = trim($_GET['q'] ?? '');
$where  = ["u.role_id = ?"]; $params = [ROLE_PARENT];
if ($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$parents = dbFetchAll(
    "SELECT u.*, COUNT(ps.student_id) AS nb_children
     FROM users u LEFT JOIN parent_student ps ON ps.parent_id = u.id
     $whereSql GROUP BY u.id ORDER BY u.created_at DESC",
    $params
);

// Enfants lies, groupes par parent (evite le N+1 : une seule requete)
$allLinks = dbFetchAll(
    "SELECT ps.parent_id, ps.student_id, ps.relationship, ps.is_primary,
            COALESCE(s.first_name, su.first_name) AS fn, COALESCE(s.last_name, su.last_name) AS ln,
            s.student_number, c.name AS class_name
     FROM parent_student ps
     JOIN students s ON ps.student_id = s.id
     LEFT JOIN users su ON s.user_id = su.id
     LEFT JOIN classes c ON s.class_id = c.id"
);
$linksByParent = [];
foreach ($allLinks as $l) $linksByParent[$l['parent_id']][] = $l;

$allStudents = dbFetchAll(
    "SELECT s.id, s.student_number, COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln, c.name AS class_name
     FROM students s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN classes c ON s.class_id = c.id
     ORDER BY fn, ln"
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <?php if ($tempPwdShow): ?>
    <div class="alert alert-warning" style="align-items:flex-start">
      <i class="bx bx-key" style="margin-top:2px"></i>
      <div>
        <strong>Mot de passe temporaire pour <?= clean($tempPwdShow['name']) ?></strong><br>
        Identifiant : <code style="font-family:var(--font-mono)"><?= clean($tempPwdShow['username']) ?></code> —
        Mot de passe : <code style="font-family:var(--font-mono);font-weight:700"><?= clean($tempPwdShow['password']) ?></code>
      </div>
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div>
        <h1>Parents</h1>
        <p><?= count($parents) ?> parent(s) inscrit(s)</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="addParentModal"><i class="bx bx-user-plus"></i> Ajouter un parent</button>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-group"></i> Liste des parents</h3>
        <form method="GET" class="search-wrap" style="max-width:260px">
          <i class="bx bx-search"></i>
          <input type="text" name="q" class="search-input" placeholder="Nom ou email..." value="<?= clean($search) ?>">
        </form>
      </div>

      <?php if (empty($parents)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-group"></i></div>
          <h3>Aucun parent</h3>
          <p>Les parents peuvent s'inscrire eux-memes, ou tu peux en creer un ici.</p>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Parent</th><th>Telephone</th><th>Enfant(s)</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($parents as $p): ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--warning)"><?= getInitials($p['first_name'], $p['last_name']) ?></div>
                <div>
                  <div class="td-name"><?= clean($p['first_name'] . ' ' . $p['last_name']) ?></div>
                  <div class="td-sub"><?= clean($p['email']) ?></div>
                </div>
              </td>
              <td class="text-sm"><?= clean($p['phone'] ?: '—') ?></td>
              <td>
                <?php $links = $linksByParent[$p['id']] ?? []; ?>
                <?php if (empty($links)): ?>
                  <span class="text-sm text-muted">Aucun enfant lie</span>
                <?php else: ?>
                  <?php foreach ($links as $l): ?>
                    <div class="text-sm" style="margin-bottom:2px">
                      <?= clean($l['fn'] . ' ' . $l['ln']) ?>
                      <span class="text-xs text-muted">(<?= clean($l['class_name'] ?: '—') ?>)</span>
                      <form method="POST" style="display:inline" data-confirm="Delier cet enfant ?">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="unlink">
                        <input type="hidden" name="parent_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="student_id" value="<?= $l['student_id'] ?>">
                        <button type="submit" style="border:none;background:none;color:var(--danger);cursor:pointer" title="Delier">
                          <i class="bx bx-x"></i>
                        </button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= $p['is_active'] ? 'success' : 'gray' ?>"><?= $p['is_active'] ? 'Actif' : 'Inactif' ?></span></td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" title="Lier un enfant"
                        onclick="document.getElementById('link_parent_id').value='<?= $p['id'] ?>';
                                 document.getElementById('link_parent_name').textContent='<?= clean($p['first_name'] . ' ' . $p['last_name']) ?>';
                                 SS.openModal('linkChildModal')">
                  <i class="bx bx-link"></i>
                </button>
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

<!-- ══ Modal : Ajouter un parent ══ -->
<div class="modal-overlay" id="addParentModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h3><i class="bx bx-user-plus"></i> Nouveau parent</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Prenom <span class="form-required">*</span></label><input type="text" name="first_name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="last_name" class="form-control" required></div>
        </div>
        <div class="form-group"><label class="form-label">Email <span class="form-required">*</span></label><input type="email" name="email" class="form-control" required></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Telephone</label><input type="text" name="phone" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Creer le compte</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal : Lier un enfant ══ -->
<div class="modal-overlay" id="linkChildModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="link">
      <input type="hidden" name="parent_id" id="link_parent_id" value="">
      <div class="modal-header">
        <h3><i class="bx bx-link"></i> Lier un enfant a <span id="link_parent_name"></span></h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Eleve <span class="form-required">*</span></label>
          <select name="student_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= $st['id'] ?>"><?= clean($st['fn'] . ' ' . $st['ln']) ?> — <?= clean($st['student_number']) ?> (<?= clean($st['class_name'] ?: '—') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Lien de parente</label>
            <select name="relationship" class="form-control">
              <option value="pere">Pere</option>
              <option value="mere">Mere</option>
              <option value="tuteur">Tuteur</option>
              <option value="parent">Autre</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-check" style="margin-top:32px">
              <input type="checkbox" name="is_primary" value="1" checked>
              <span class="form-check-label">Contact principal</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Lier</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
