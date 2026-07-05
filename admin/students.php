<?php
// ============================================================
//  SmartSchool — Gestion des eleves
//  Emplacement : admin/students.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Gestion des eleves';
$pageSection = 'students';
$user        = currentUser();

// Annee scolaire courante
$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;

// Classes pour filtres
$classes = dbFetchAll(
    "SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name",
    [$yearId]
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // AJOUTER
    if ($action === 'add') {
        $firstName  = trim($_POST['first_name']       ?? '');
        $lastName   = trim($_POST['last_name']        ?? '');
        $email      = trim($_POST['email']            ?? '');
        $phone      = trim($_POST['phone']            ?? '');
        $dob        = trim($_POST['date_of_birth']    ?? '');
        $gender     = trim($_POST['gender']           ?? 'M');
        $classId    = (int)($_POST['class_id']        ?? 0) ?: null;
        $enrollDate = trim($_POST['enrollment_date']  ?? date('Y-m-d'));
        $prevSchool = trim($_POST['previous_school']  ?? '');
        $password   = trim($_POST['password']         ?? 'SmartSchool2025!');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            redirectWith(BASE_URL . '/admin/students.php', 'danger', 'Prenom, nom et email sont obligatoires.');
        }

        $exists = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            redirectWith(BASE_URL . '/admin/students.php', 'danger', 'Cet email est deja utilise.');
        }

        $username  = strtolower($firstName . '.' . $lastName . rand(10, 99));
        $studentNb = generateStudentNumber();

        try {
            getDB()->beginTransaction();

            dbExecute(
                "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, gender, date_of_birth)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [ROLE_STUDENT, $username, $email, hashPassword($password),
                 $firstName, $lastName, $phone, $gender, $dob ?: null]
            );
            $userId = dbLastId();

            dbExecute(
                "INSERT INTO students (user_id, class_id, academic_year_id, student_number, enrollment_date, previous_school)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $classId, $yearId, $studentNb, $enrollDate, $prevSchool]
            );

            getDB()->commit();
            logActivity('add_student', "Eleve ajoute : $firstName $lastName ($studentNb)");
            redirectWith(BASE_URL . '/admin/students.php', 'success',
                "Eleve $firstName $lastName ajoute ! Matricule : $studentNb");

        } catch (Exception $e) {
            getDB()->rollBack();
            redirectWith(BASE_URL . '/admin/students.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    // MODIFIER
    if ($action === 'edit') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $firstName = trim($_POST['first_name']  ?? '');
        $lastName  = trim($_POST['last_name']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $phone     = trim($_POST['phone']       ?? '');
        $dob       = trim($_POST['date_of_birth']?? '');
        $gender    = trim($_POST['gender']      ?? 'M');
        $classId   = (int)($_POST['class_id']   ?? 0) ?: null;
        $status    = trim($_POST['status']      ?? 'actif');

        $student = dbFetchOne("SELECT * FROM students WHERE id = ?", [$studentId]);
        if (!$student) {
            redirectWith(BASE_URL . '/admin/students.php', 'danger', 'Eleve introuvable.');
        }

        try {
            getDB()->beginTransaction();
            dbExecute(
                "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, gender=?, date_of_birth=? WHERE id=?",
                [$firstName, $lastName, $email, $phone, $gender, $dob ?: null, $student['user_id']]
            );
            dbExecute(
                "UPDATE students SET class_id=?, status=? WHERE id=?",
                [$classId, $status, $studentId]
            );
            getDB()->commit();
            logActivity('edit_student', "Eleve modifie : $firstName $lastName");
            redirectWith(BASE_URL . '/admin/students.php', 'success', "Eleve $firstName $lastName mis a jour.");
        } catch (Exception $e) {
            getDB()->rollBack();
            redirectWith(BASE_URL . '/admin/students.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    // SUPPRIMER
    if ($action === 'delete') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = dbFetchOne(
            "SELECT s.*, u.first_name, u.last_name FROM students s
             JOIN users u ON s.user_id = u.id WHERE s.id = ?",
            [$studentId]
        );
        if ($student) {
            dbExecute("DELETE FROM users WHERE id = ?", [$student['user_id']]);
            logActivity('delete_student', "Eleve supprime : {$student['first_name']} {$student['last_name']}");
            redirectWith(BASE_URL . '/admin/students.php', 'success', 'Eleve supprime avec succes.');
        }
        redirectWith(BASE_URL . '/admin/students.php', 'danger', 'Eleve introuvable.');
    }
}

// ── Filtres ──────────────────────────────────────────────
$search       = trim($_GET['search']   ?? '');
$filterClass  = (int)($_GET['class']   ?? 0);
$filterStatus = trim($_GET['status']  ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = ["s.academic_year_id = $yearId"];
$params = [];

if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.student_number LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
if ($filterClass)  { $where[] = "s.class_id = $filterClass"; }
if ($filterStatus) { $where[] = "s.status = '" . addslashes($filterStatus) . "'"; }

$whereStr = 'WHERE ' . implode(' AND ', $where);

$total   = dbFetchOne(
    "SELECT COUNT(*) c FROM students s JOIN users u ON s.user_id = u.id $whereStr", $params
)['c'] ?? 0;

$pag      = paginate($total, $page);
$students = dbFetchAll(
    "SELECT s.id, s.student_number, s.status, s.enrollment_date, s.class_id,
            u.first_name, u.last_name, u.email, u.phone, u.gender, u.date_of_birth,
            c.name AS class_name
     FROM students s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     $whereStr
     ORDER BY s.created_at DESC
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

<!-- ENTETE -->
<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-graduation" style="color:var(--primary)"></i>
      Gestion des eleves
    </h1>
    <p><?= $total ?> eleve<?= $total > 1 ? 's' : '' ?> — Annee <?= clean($currentYear['name'] ?? '') ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" onclick="exportCSV()">
      <i class="bx bx-download"></i> Exporter CSV
    </button>
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-user-plus"></i> Nouvel eleve
    </button>
  </div>
</div>

<!-- FILTRES -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="search-wrap" style="flex:1;min-width:200px">
        <i class="bx bx-search"></i>
        <input type="text" name="search" class="search-input"
               placeholder="Nom, email, matricule..."
               value="<?= clean($search) ?>">
      </div>
      <select name="class" class="form-control" style="width:160px">
        <option value="">Toutes classes</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>
            <?= clean($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control" style="width:140px">
        <option value="">Tous statuts</option>
        <option value="actif"     <?= $filterStatus === 'actif'     ? 'selected' : '' ?>>Actif</option>
        <option value="suspendu"  <?= $filterStatus === 'suspendu'  ? 'selected' : '' ?>>Suspendu</option>
        <option value="diplome"   <?= $filterStatus === 'diplome'   ? 'selected' : '' ?>>Diplome</option>
        <option value="transfere" <?= $filterStatus === 'transfere' ? 'selected' : '' ?>>Transfere</option>
      </select>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
      <?php if ($search || $filterClass || $filterStatus): ?>
        <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-secondary">
          <i class="bx bx-x"></i> Effacer
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLEAU -->
<div class="card">
  <div class="table-wrap">
    <table class="table" id="studentsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Eleve</th>
          <th>Matricule</th>
          <th>Classe</th>
          <th>Telephone</th>
          <th>Inscription</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="bx bx-graduation"></i></div>
                <h3>Aucun eleve trouve</h3>
                <p><?= $search ? 'Aucun resultat pour "'.clean($search).'"' : 'Ajoutez votre premier eleve.' ?></p>
                <?php if (!$search): ?>
                  <button class="btn btn-primary" data-modal="modalAdd">
                    <i class="bx bx-user-plus"></i> Ajouter un eleve
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students as $i => $s): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px"><?= $pag['offset'] + $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-40"
                     style="background:<?= $s['gender'] === 'F' ? '#ec4899' : 'var(--primary)' ?>">
                  <?= getInitials($s['first_name'], $s['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                  <div class="td-sub"><?= clean($s['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <code style="font-size:11.5px;background:var(--primary-bg);color:var(--primary);padding:3px 8px;border-radius:5px;font-weight:600">
                <?= clean($s['student_number']) ?>
              </code>
            </td>
            <td><?= clean($s['class_name'] ?? '—') ?></td>
            <td style="color:var(--text-muted)"><?= clean($s['phone'] ?? '—') ?></td>
            <td style="color:var(--text-muted);font-size:12.5px"><?= formatDate($s['enrollment_date']) ?></td>
            <td>
              <?php $badges = ['actif'=>'success','suspendu'=>'danger','diplome'=>'primary','transfere'=>'warning']; ?>
              <span class="badge badge-<?= $badges[$s['status']] ?? 'gray' ?>">
                <?= clean($s['status']) ?>
              </span>
            </td>
            <td>
              <div class="td-actions">
                <button class="btn btn-sm btn-secondary btn-icon"
                        title="Modifier"
                        onclick='editStudent(<?= json_encode([
                          "id"           => $s["id"],
                          "first_name"   => $s["first_name"],
                          "last_name"    => $s["last_name"],
                          "email"        => $s["email"],
                          "phone"        => $s["phone"],
                          "date_of_birth"=> $s["date_of_birth"],
                          "gender"       => $s["gender"],
                          "class_id"     => $s["class_id"],
                          "status"       => $s["status"],
                        ], JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>
                  <i class="bx bx-edit"></i>
                </button>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"     value="delete">
                  <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          onclick="return confirm('Supprimer <?= addslashes($s['first_name'] . ' ' . $s['last_name']) ?> ? Cette action est irreversible.')">
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
      <?= $pag['offset']+1 ?> – <?= min($pag['offset']+$pag['per_page'], $total) ?>
      sur <?= $total ?> eleves
    </span>
    <nav class="pagination">
      <?php if ($pag['has_prev']): ?>
        <a href="?page=<?= $pag['prev_page'] ?>&search=<?= urlencode($search) ?>&class=<?= $filterClass ?>&status=<?= $filterStatus ?>">
          <i class="bx bx-chevron-left"></i>
        </a>
      <?php endif; ?>
      <?php for ($pg = max(1,$pag['current_page']-2); $pg <= min($pag['total_pages'],$pag['current_page']+2); $pg++): ?>
        <?php if ($pg === $pag['current_page']): ?>
          <span class="active"><?= $pg ?></span>
        <?php else: ?>
          <a href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&class=<?= $filterClass ?>&status=<?= $filterStatus ?>"><?= $pg ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['has_next']): ?>
        <a href="?page=<?= $pag['next_page'] ?>&search=<?= urlencode($search) ?>&class=<?= $filterClass ?>&status=<?= $filterStatus ?>">
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
      <h3><i class="bx bx-user-plus" style="color:var(--primary)"></i> Nouvel eleve</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" placeholder="Lucas" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" placeholder="Durand" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email <span class="form-required">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="eleve@exemple.fr" required>
          </div>
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" class="form-control" placeholder="+225 00 00 00 00">
          </div>
          <div class="form-group">
            <label class="form-label">Date de naissance</label>
            <input type="date" name="date_of_birth" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" class="form-control">
              <option value="M">Masculin</option>
              <option value="F">Feminin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-control">
              <option value="">Sans classe</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date d'inscription</label>
            <input type="date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Ecole precedente</label>
            <input type="text" name="previous_school" class="form-control" placeholder="Ancienne ecole">
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
          <i class="bx bx-user-plus"></i> Ajouter l'eleve
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFIER -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Modifier l'eleve</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"     value="edit">
      <input type="hidden" name="student_id" id="eId">
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
            <label class="form-label">Date de naissance</label>
            <input type="date" name="date_of_birth" id="eDob" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" id="eGender" class="form-control">
              <option value="M">Masculin</option>
              <option value="F">Feminin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classe</label>
            <select name="class_id" id="eClass" class="form-control">
              <option value="">Sans classe</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Statut</label>
            <select name="status" id="eStatus" class="form-control">
              <option value="actif">Actif</option>
              <option value="suspendu">Suspendu</option>
              <option value="diplome">Diplome</option>
              <option value="transfere">Transfere</option>
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
function editStudent(d) {
  document.getElementById('eId').value     = d.id;
  document.getElementById('eFn').value     = d.first_name;
  document.getElementById('eLn').value     = d.last_name;
  document.getElementById('eEmail').value  = d.email;
  document.getElementById('ePhone').value  = d.phone || '';
  document.getElementById('eDob').value    = d.date_of_birth || '';
  document.getElementById('eGender').value = d.gender || 'M';
  document.getElementById('eClass').value  = d.class_id || '';
  document.getElementById('eStatus').value = d.status || 'actif';
  SS_Modal.open('modalEdit');
}

function exportCSV() {
  const rows  = [['Prenom','Nom','Email','Matricule','Classe','Statut']];
  document.querySelectorAll('#studentsTable tbody tr').forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 7) return;
    const nm = tds[1].querySelector('.td-name')?.textContent.trim().split(' ') || [];
    rows.push([nm[0]||'', nm.slice(1).join(' ')||'',
      tds[1].querySelector('.td-sub')?.textContent.trim()||'',
      tds[2].textContent.trim(), tds[3].textContent.trim(),
      tds[6].textContent.trim()
    ].map(v => '\"'+v.replace(/\"/g,'\"\"')+'\"').join(','));
  });
  const a = document.createElement('a');
  a.href     = 'data:text/csv;charset=utf-8,' + encodeURIComponent(rows.join('\n'));
  a.download = 'eleves.csv';
  a.click();
}
";
require_once INCLUDES_PATH . '/footer.php';
?>