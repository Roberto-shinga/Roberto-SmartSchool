<?php
// ============================================================
//  SmartSchool — Gestion des enseignants
//  Emplacement : admin/teachers.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des enseignants';
$pageSection = 'teachers';
$user        = currentUser();

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // AJOUTER
    if ($action === 'add') {
        $firstName  = trim($_POST['first_name']   ?? '');
        $lastName   = trim($_POST['last_name']    ?? '');
        $email      = trim($_POST['email']        ?? '');
        $phone      = trim($_POST['phone']        ?? '');
        $gender     = trim($_POST['gender']       ?? 'M');
        $hireDate   = trim($_POST['hire_date']    ?? date('Y-m-d'));
        $qual       = trim($_POST['qualification']?? '');
        $spec       = trim($_POST['speciality']   ?? '');
        $salary     = (float)($_POST['salary']    ?? 0);
        $password   = trim($_POST['password']     ?? 'SmartSchool2025!');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            redirectWith(BASE_URL . '/admin/teachers.php', 'danger', 'Prenom, nom et email obligatoires.');
        }

        $exists = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            redirectWith(BASE_URL . '/admin/teachers.php', 'danger', 'Email deja utilise.');
        }

        $username  = strtolower($firstName . '.' . $lastName . rand(10,99));
        $employeeId = 'EMP-' . str_pad(
            (dbFetchOne("SELECT COUNT(*) c FROM teachers")['c'] ?? 0) + 1,
            4, '0', STR_PAD_LEFT
        );

        try {
            getDB()->beginTransaction();
            dbExecute(
                "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, gender)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [ROLE_TEACHER, $username, $email, hashPassword($password),
                 $firstName, $lastName, $phone, $gender]
            );
            $userId = dbLastId();

            dbExecute(
                "INSERT INTO teachers (user_id, employee_id, hire_date, qualification, speciality, salary)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $employeeId, $hireDate, $qual, $spec, $salary]
            );

            getDB()->commit();
            logActivity('add_teacher', "Enseignant ajoute : $firstName $lastName ($employeeId)");
            redirectWith(BASE_URL . '/admin/teachers.php', 'success',
                "Enseignant $firstName $lastName ajoute ! ID : $employeeId");
        } catch (Exception $e) {
            getDB()->rollBack();
            redirectWith(BASE_URL . '/admin/teachers.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    // MODIFIER
    if ($action === 'edit') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $firstName = trim($_POST['first_name']   ?? '');
        $lastName  = trim($_POST['last_name']    ?? '');
        $email     = trim($_POST['email']        ?? '');
        $phone     = trim($_POST['phone']        ?? '');
        $gender    = trim($_POST['gender']       ?? 'M');
        $qual      = trim($_POST['qualification']?? '');
        $spec      = trim($_POST['speciality']   ?? '');
        $salary    = (float)($_POST['salary']    ?? 0);
        $status    = trim($_POST['status']       ?? 'actif');

        $teacher = dbFetchOne("SELECT * FROM teachers WHERE id = ?", [$teacherId]);
        if (!$teacher) {
            redirectWith(BASE_URL . '/admin/teachers.php', 'danger', 'Enseignant introuvable.');
        }

        try {
            getDB()->beginTransaction();
            dbExecute(
                "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, gender=? WHERE id=?",
                [$firstName, $lastName, $email, $phone, $gender, $teacher['user_id']]
            );
            dbExecute(
                "UPDATE teachers SET qualification=?, speciality=?, salary=?, status=? WHERE id=?",
                [$qual, $spec, $salary, $status, $teacherId]
            );
            getDB()->commit();
            logActivity('edit_teacher', "Enseignant modifie : $firstName $lastName");
            redirectWith(BASE_URL . '/admin/teachers.php', 'success', "Enseignant $firstName $lastName mis a jour.");
        } catch (Exception $e) {
            getDB()->rollBack();
            redirectWith(BASE_URL . '/admin/teachers.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    // SUPPRIMER
    if ($action === 'delete') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $teacher   = dbFetchOne(
            "SELECT t.*, u.first_name, u.last_name FROM teachers t
             JOIN users u ON t.user_id = u.id WHERE t.id = ?",
            [$teacherId]
        );
        if ($teacher) {
            dbExecute("DELETE FROM users WHERE id = ?", [$teacher['user_id']]);
            logActivity('delete_teacher', "Enseignant supprime : {$teacher['first_name']} {$teacher['last_name']}");
            redirectWith(BASE_URL . '/admin/teachers.php', 'success', 'Enseignant supprime.');
        }
        redirectWith(BASE_URL . '/admin/teachers.php', 'danger', 'Enseignant introuvable.');
    }
}

// ── Filtres ──────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.employee_id LIKE ? OR t.speciality LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($filterStatus) {
    $where[] = "t.status = '" . addslashes($filterStatus) . "'";
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$total    = dbFetchOne(
    "SELECT COUNT(*) c FROM teachers t JOIN users u ON t.user_id = u.id $whereStr", $params
)['c'] ?? 0;

$pag      = paginate($total, $page);
$teachers = dbFetchAll(
    "SELECT t.id, t.employee_id, t.hire_date, t.qualification, t.speciality, t.salary, t.status,
            u.first_name, u.last_name, u.email, u.phone, u.gender
     FROM teachers t
     JOIN users u ON t.user_id = u.id
     $whereStr
     ORDER BY t.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// Stats rapides
$totalActive = dbFetchOne("SELECT COUNT(*) c FROM teachers WHERE status='actif'")['c'] ?? 0;
$avgSalary   = dbFetchOne("SELECT COALESCE(AVG(salary),0) c FROM teachers WHERE status='actif'")['c'] ?? 0;
$totalSalary = dbFetchOne("SELECT COALESCE(SUM(salary),0) c FROM teachers WHERE status='actif'")['c'] ?? 0;

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<!-- ENTETE -->
<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-chalkboard" style="color:var(--primary)"></i>
      Gestion des enseignants
    </h1>
    <p><?= $total ?> enseignant<?= $total > 1 ? 's' : '' ?> au total</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-user-plus"></i> Nouvel enseignant
    </button>
  </div>
</div>

<!-- MINI STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:24px">
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-chalkboard"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $totalActive ?></div>
      <div class="stat-label">Enseignants actifs</div>
    </div>
  </div>
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-money"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatMoney($totalSalary) ?></div>
      <div class="stat-label">Masse salariale</div>
    </div>
  </div>
  <div class="stat-card c-cyan">
    <div class="stat-icon c-cyan"><i class="bx bx-trending-up"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatMoney($avgSalary) ?></div>
      <div class="stat-label">Salaire moyen</div>
    </div>
  </div>
</div>

<!-- FILTRES -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="search-wrap" style="flex:1;min-width:200px">
        <i class="bx bx-search"></i>
        <input type="text" name="search" class="search-input"
               placeholder="Nom, email, specialite..."
               value="<?= clean($search) ?>">
      </div>
      <select name="status" class="form-control" style="width:150px">
        <option value="">Tous les statuts</option>
        <option value="actif"    <?= $filterStatus === 'actif'    ? 'selected' : '' ?>>Actif</option>
        <option value="conge"    <?= $filterStatus === 'conge'    ? 'selected' : '' ?>>En conge</option>
        <option value="retraite" <?= $filterStatus === 'retraite' ? 'selected' : '' ?>>Retraite</option>
      </select>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
      <?php if ($search || $filterStatus): ?>
        <a href="<?= BASE_URL ?>/admin/teachers.php" class="btn btn-secondary">
          <i class="bx bx-x"></i> Effacer
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
          <th>Enseignant</th>
          <th>ID Employe</th>
          <th>Specialite</th>
          <th>Telephone</th>
          <th>Embauche le</th>
          <th>Salaire</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($teachers)): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="bx bx-chalkboard"></i></div>
                <h3>Aucun enseignant trouve</h3>
                <p><?= $search ? 'Aucun resultat.' : 'Ajoutez votre premier enseignant.' ?></p>
                <?php if (!$search): ?>
                  <button class="btn btn-primary" data-modal="modalAdd">
                    <i class="bx bx-user-plus"></i> Ajouter
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($teachers as $i => $t): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px"><?= $pag['offset'] + $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-40"
                     style="background:<?= $t['gender']==='F' ? '#ec4899' : 'var(--cyan-dark)' ?>">
                  <?= getInitials($t['first_name'], $t['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($t['first_name']) ?> <?= clean($t['last_name']) ?></div>
                  <div class="td-sub"><?= clean($t['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <code style="font-size:11.5px;background:var(--cyan-bg);color:var(--cyan-dark);padding:3px 8px;border-radius:5px;font-weight:600">
                <?= clean($t['employee_id']) ?>
              </code>
            </td>
            <td style="color:var(--text-muted)"><?= clean($t['speciality'] ?? '—') ?></td>
            <td style="color:var(--text-muted)"><?= clean($t['phone'] ?? '—') ?></td>
            <td style="color:var(--text-muted);font-size:12.5px"><?= formatDate($t['hire_date']) ?></td>
            <td style="font-weight:600;color:var(--success)"><?= formatMoney($t['salary']) ?></td>
            <td>
              <?php $sb = ['actif'=>'success','conge'=>'warning','retraite'=>'gray']; ?>
              <span class="badge badge-<?= $sb[$t['status']] ?? 'gray' ?>">
                <?= clean($t['status']) ?>
              </span>
            </td>
            <td>
              <div class="td-actions">
                <button class="btn btn-sm btn-secondary btn-icon"
                        title="Modifier"
                        onclick='editTeacher(<?= json_encode([
                          "id"            => $t["id"],
                          "first_name"    => $t["first_name"],
                          "last_name"     => $t["last_name"],
                          "email"         => $t["email"],
                          "phone"         => $t["phone"],
                          "gender"        => $t["gender"],
                          "qualification" => $t["qualification"],
                          "speciality"    => $t["speciality"],
                          "salary"        => $t["salary"],
                          "status"        => $t["status"],
                        ], JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>
                  <i class="bx bx-edit"></i>
                </button>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"     value="delete">
                  <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          onclick="return confirm('Supprimer <?= addslashes($t['first_name'].' '.$t['last_name']) ?> ?')">
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
      <?= $pag['offset']+1 ?> – <?= min($pag['offset']+$pag['per_page'], $total) ?> sur <?= $total ?>
    </span>
    <nav class="pagination">
      <?php if ($pag['has_prev']): ?>
        <a href="?page=<?= $pag['prev_page'] ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>">
          <i class="bx bx-chevron-left"></i>
        </a>
      <?php endif; ?>
      <?php for ($pg = max(1,$pag['current_page']-2); $pg <= min($pag['total_pages'],$pag['current_page']+2); $pg++): ?>
        <?php if ($pg === $pag['current_page']): ?>
          <span class="active"><?= $pg ?></span>
        <?php else: ?>
          <a href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>"><?= $pg ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['has_next']): ?>
        <a href="?page=<?= $pag['next_page'] ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>">
          <i class="bx bx-chevron-right"></i>
        </a>
      <?php endif; ?>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL AJOUTER -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-user-plus" style="color:var(--primary)"></i> Nouvel enseignant</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" placeholder="Jean" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" placeholder="Martin" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email <span class="form-required">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="prof@exemple.fr" required>
          </div>
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" class="form-control" placeholder="+225 00 00 00 00">
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" class="form-control">
              <option value="M">Masculin</option>
              <option value="F">Feminin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date d'embauche</label>
            <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Qualification</label>
            <input type="text" name="qualification" class="form-control" placeholder="Master Mathematiques">
          </div>
          <div class="form-group">
            <label class="form-label">Specialite</label>
            <input type="text" name="speciality" class="form-control" placeholder="Maths, Physique">
          </div>
          <div class="form-group">
            <label class="form-label">Salaire mensuel</label>
            <input type="number" name="salary" class="form-control" placeholder="0" min="0" step="1000">
          </div>
          <div class="form-group">
            <label class="form-label">Mot de passe initial</label>
            <input type="text" name="password" class="form-control" value="SmartSchool2025!">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-user-plus"></i> Ajouter
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFIER -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Modifier l'enseignant</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"     value="edit">
      <input type="hidden" name="teacher_id" id="eId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" id="eFn" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" id="eLn" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email <span class="form-required">*</span></label>
            <input type="email" name="email" id="eEmail" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" id="ePhone" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" id="eGender" class="form-control">
              <option value="M">Masculin</option>
              <option value="F">Feminin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Qualification</label>
            <input type="text" name="qualification" id="eQual" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Specialite</label>
            <input type="text" name="speciality" id="eSpec" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Salaire</label>
            <input type="number" name="salary" id="eSalary" class="form-control" min="0" step="1000">
          </div>
          <div class="form-group">
            <label class="form-label">Statut</label>
            <select name="status" id="eStatus" class="form-control">
              <option value="actif">Actif</option>
              <option value="conge">En conge</option>
              <option value="retraite">Retraite</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-save"></i> Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php
$pageScript = "
function editTeacher(d) {
  document.getElementById('eId').value     = d.id;
  document.getElementById('eFn').value     = d.first_name;
  document.getElementById('eLn').value     = d.last_name;
  document.getElementById('eEmail').value  = d.email;
  document.getElementById('ePhone').value  = d.phone || '';
  document.getElementById('eGender').value = d.gender || 'M';
  document.getElementById('eQual').value   = d.qualification || '';
  document.getElementById('eSpec').value   = d.speciality || '';
  document.getElementById('eSalary').value = d.salary || 0;
  document.getElementById('eStatus').value = d.status || 'actif';
  SS_Modal.open('modalEdit');
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
