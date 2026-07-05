<?php
// ============================================================
//  SmartSchool — Gestion des presences
//  Emplacement : admin/attendance.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_TEACHER);

$pageTitle   = 'Gestion des presences';
$pageSection = 'attendance';
$user        = currentUser();

// Annee scolaire courante
$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;

// Classes disponibles
$classes = dbFetchAll(
    "SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name",
    [$yearId]
);

// ── Traitement POST — Enregistrer appel ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_attendance') {
        $classId    = (int)($_POST['class_id'] ?? 0);
        $date       = trim($_POST['date']      ?? date('Y-m-d'));
        $statuses   = $_POST['statuses']       ?? [];
        $reasons    = $_POST['reasons']        ?? [];

        if (!$classId || empty($statuses)) {
            redirectWith(BASE_URL . '/admin/attendance.php', 'danger', 'Donnees invalides.');
        }

        $saved = 0;
        foreach ($statuses as $studentId => $status) {
            $studentId = (int)$studentId;
            $reason    = trim($reasons[$studentId] ?? '');

            // Verifier si deja enregistre
            $existing = dbFetchOne(
                "SELECT id FROM attendance WHERE student_id = ? AND class_id = ? AND date = ?",
                [$studentId, $classId, $date]
            );

            if ($existing) {
                dbExecute(
                    "UPDATE attendance SET status=?, reason=?, recorded_by=? WHERE id=?",
                    [$status, $reason, $user['id'], $existing['id']]
                );
            } else {
                dbExecute(
                    "INSERT INTO attendance (student_id, class_id, date, status, reason, recorded_by)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$studentId, $classId, $date, $status, $reason, $user['id']]
                );
            }
            $saved++;
        }

        logActivity('save_attendance', "Appel enregistre : classe $classId — $date ($saved eleves)");
        redirectWith(BASE_URL . '/admin/attendance.php?class_id='.$classId.'&date='.$date,
            'success', "Appel enregistre pour $saved eleve(s) !");
    }
}

// ── Filtres ──────────────────────────────────────────────
$selectedClass = (int)($_GET['class_id'] ?? (count($classes) > 0 ? $classes[0]['id'] : 0));
$selectedDate  = trim($_GET['date']      ?? date('Y-m-d'));
$viewMode      = trim($_GET['view']      ?? 'appel');  // appel | historique

// ── Mode APPEL : liste des eleves de la classe ───────────
$classStudents = [];
$attendanceMap = [];

if ($selectedClass && $viewMode === 'appel') {
    $classStudents = dbFetchAll(
        "SELECT s.id, u.first_name, u.last_name, u.gender, s.student_number
         FROM students s
         JOIN users u ON s.user_id = u.id
         WHERE s.class_id = ? AND s.status = 'actif'
         ORDER BY u.last_name, u.first_name",
        [$selectedClass]
    );

    // Recuperer presences deja enregistrees pour ce jour
    if (!empty($classStudents)) {
        $ids = implode(',', array_column($classStudents, 'id'));
        $existing = dbFetchAll(
            "SELECT student_id, status, reason FROM attendance
             WHERE class_id = ? AND date = ? AND student_id IN ($ids)",
            [$selectedClass, $selectedDate]
        );
        foreach ($existing as $e) {
            $attendanceMap[$e['student_id']] = $e;
        }
    }
}

// ── Mode HISTORIQUE ───────────────────────────────────────
$history    = [];
$histFilter = [];
$histParams = [];

if ($viewMode === 'historique') {
    $dateFrom = trim($_GET['from'] ?? date('Y-m-01'));
    $dateTo   = trim($_GET['to']   ?? date('Y-m-d'));

    $histFilter = ["a.date BETWEEN ? AND ?"];
    $histParams = [$dateFrom, $dateTo];

    if ($selectedClass) {
        $histFilter[] = "a.class_id = $selectedClass";
    }

    $histWhere = 'WHERE ' . implode(' AND ', $histFilter);

    $history = dbFetchAll(
        "SELECT a.date, a.status, a.reason,
                u.first_name, u.last_name,
                s.student_number,
                c.name AS class_name
         FROM attendance a
         JOIN students s ON a.student_id = s.id
         JOIN users u    ON s.user_id    = u.id
         JOIN classes c  ON a.class_id   = c.id
         $histWhere
         ORDER BY a.date DESC, u.last_name
         LIMIT 100",
        $histParams
    );
}

// Classe selectionnee
$selectedClassName = '';
foreach ($classes as $c) {
    if ($c['id'] == $selectedClass) {
        $selectedClassName = $c['name'];
        break;
    }
}

// Stats du jour
$statsToday = dbFetchOne(
    "SELECT
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) present,
        SUM(CASE WHEN status='absent'  THEN 1 ELSE 0 END) absent,
        SUM(CASE WHEN status='retard'  THEN 1 ELSE 0 END) retard,
        SUM(CASE WHEN status='excuse'  THEN 1 ELSE 0 END) excuse
     FROM attendance WHERE date = CURDATE()"
) ?: ['present'=>0,'absent'=>0,'retard'=>0,'excuse'=>0];

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
      <i class="bx bx-check-square" style="color:var(--primary)"></i>
      Gestion des presences
    </h1>
    <p>Faire l'appel et consulter l'historique des presences</p>
  </div>
</div>

<!-- STATS DU JOUR -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px">
  <?php
  $statItems = [
    ['val'=>$statsToday['present'],'label'=>'Presents aujourd\'hui','color'=>'c-success','icon'=>'bx-check-circle'],
    ['val'=>$statsToday['absent'], 'label'=>'Absents aujourd\'hui', 'color'=>'c-danger', 'icon'=>'bx-x-circle'],
    ['val'=>$statsToday['retard'], 'label'=>'Retards aujourd\'hui', 'color'=>'c-warning','icon'=>'bx-time'],
    ['val'=>$statsToday['excuse'], 'label'=>'Excuses aujourd\'hui', 'color'=>'c-primary','icon'=>'bx-info-circle'],
  ];
  foreach ($statItems as $st): ?>
  <div class="stat-card <?= $st['color'] ?>">
    <div class="stat-icon <?= $st['color'] ?>">
      <i class="bx <?= $st['icon'] ?>"></i>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $st['val'] ?? 0 ?></div>
      <div class="stat-label"><?= $st['label'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ONGLETS -->
<div class="tabs-wrapper">
  <div class="tabs">
    <button class="tab-btn <?= $viewMode === 'appel' ? 'active' : '' ?>"
            onclick="location.href='?view=appel&class_id=<?= $selectedClass ?>&date=<?= $selectedDate ?>'">
      <i class="bx bx-check-square"></i> Faire l'appel
    </button>
    <button class="tab-btn <?= $viewMode === 'historique' ? 'active' : '' ?>"
            onclick="location.href='?view=historique&class_id=<?= $selectedClass ?>'">
      <i class="bx bx-history"></i> Historique
    </button>
  </div>
</div>

<!-- ══ MODE APPEL ══ -->
<?php if ($viewMode === 'appel'): ?>

<!-- Selectionner classe et date -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="view" value="appel">
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label">Classe</label>
        <select name="class_id" class="form-control">
          <option value="">Choisir une classe</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $selectedClass == $c['id'] ? 'selected' : '' ?>>
              <?= clean($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control"
               value="<?= clean($selectedDate) ?>"
               max="<?= date('Y-m-d') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-search"></i> Charger les eleves
      </button>
    </form>
  </div>
</div>

<?php if ($selectedClass && !empty($classStudents)): ?>
<!-- Formulaire d'appel -->
<div class="card">
  <div class="card-header">
    <h3>
      <i class="bx bx-group"></i>
      Appel — <?= clean($selectedClassName) ?>
    </h3>
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:13px;color:var(--text-muted)">
        <?= formatDate($selectedDate) ?>
      </span>
      <button class="btn btn-sm btn-secondary" onclick="markAll('present')">
        <i class="bx bx-check-double"></i> Tous presents
      </button>
    </div>
  </div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action"   value="save_attendance">
    <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
    <input type="hidden" name="date"     value="<?= clean($selectedDate) ?>">

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Eleve</th>
            <th>Matricule</th>
            <th style="text-align:center">Present</th>
            <th style="text-align:center">Absent</th>
            <th style="text-align:center">Retard</th>
            <th style="text-align:center">Excuse</th>
            <th>Motif (si absent)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classStudents as $i => $s):
            $att    = $attendanceMap[$s['id']] ?? null;
            $status = $att['status'] ?? 'present';
            $reason = $att['reason'] ?? '';
          ?>
          <tr id="row-<?= $s['id'] ?>">
            <td style="color:var(--text-muted);font-size:12px"><?= $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32"
                     style="background:<?= $s['gender']==='F' ? '#ec4899' : 'var(--primary)' ?>">
                  <?= getInitials($s['first_name'], $s['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 6px;border-radius:4px">
                <?= clean($s['student_number']) ?>
              </code>
            </td>
            <?php
            $opts = ['present','absent','retard','excuse'];
            $colors = ['present'=>'#10b981','absent'=>'#ef4444','retard'=>'#f59e0b','excuse'=>'#6366f1'];
            foreach ($opts as $opt): ?>
            <td style="text-align:center">
              <label style="cursor:pointer;display:flex;justify-content:center">
                <input type="radio"
                       name="statuses[<?= $s['id'] ?>]"
                       value="<?= $opt ?>"
                       <?= $status === $opt ? 'checked' : '' ?>
                       onchange="handleAttChange(<?= $s['id'] ?>, '<?= $opt ?>')"
                       style="width:18px;height:18px;accent-color:<?= $colors[$opt] ?>;cursor:pointer">
              </label>
            </td>
            <?php endforeach; ?>
            <td>
              <input type="text"
                     name="reasons[<?= $s['id'] ?>]"
                     id="reason-<?= $s['id'] ?>"
                     class="form-control"
                     placeholder="Motif..."
                     value="<?= clean($reason) ?>"
                     style="font-size:12.5px;padding:6px 10px;<?= in_array($status,['absent','excuse']) ? '' : 'display:none' ?>">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:13px;color:var(--text-muted)">
        <i class="bx bx-group"></i> <?= count($classStudents) ?> eleve(s) dans cette classe
      </span>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-save"></i> Enregistrer l'appel
      </button>
    </div>
  </form>
</div>

<?php elseif ($selectedClass && empty($classStudents)): ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bx bx-user-x"></i></div>
    <h3>Aucun eleve dans cette classe</h3>
    <p>Assignez des eleves a cette classe d'abord.</p>
    <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-primary">
      <i class="bx bx-graduation"></i> Gerer les eleves
    </a>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bx bx-buildings"></i></div>
    <h3>Selectionner une classe</h3>
    <p>Choisissez une classe et une date pour faire l'appel.</p>
  </div>
</div>
<?php endif; ?>

<?php endif; // fin mode appel ?>

<!-- ══ MODE HISTORIQUE ══ -->
<?php if ($viewMode === 'historique'): ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="view" value="historique">
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Classe</label>
        <select name="class_id" class="form-control">
          <option value="">Toutes les classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $selectedClass == $c['id'] ? 'selected' : '' ?>>
              <?= clean($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Du</label>
        <input type="date" name="from" class="form-control"
               value="<?= clean($_GET['from'] ?? date('Y-m-01')) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Au</label>
        <input type="date" name="to" class="form-control"
               value="<?= clean($_GET['to'] ?? date('Y-m-d')) ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-history"></i> Historique des presences</h3>
    <span style="font-size:13px;color:var(--text-muted)"><?= count($history) ?> entrees</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Eleve</th>
          <th>Matricule</th>
          <th>Classe</th>
          <th>Statut</th>
          <th>Motif</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="bx bx-history"></i></div>
                <h3>Aucun enregistrement</h3>
                <p>Aucune presence enregistree pour cette periode.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($history as $h):
            $sc = [
              'present'=>['badge'=>'success','icon'=>'bx-check-circle'],
              'absent' =>['badge'=>'danger', 'icon'=>'bx-x-circle'],
              'retard' =>['badge'=>'warning','icon'=>'bx-time'],
              'excuse' =>['badge'=>'primary','icon'=>'bx-info-circle'],
            ][$h['status']] ?? ['badge'=>'gray','icon'=>'bx-circle'];
          ?>
          <tr>
            <td style="font-weight:600;color:var(--text-primary)"><?= formatDate($h['date']) ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:var(--primary)">
                  <?= getInitials($h['first_name'], $h['last_name']) ?>
                </div>
                <span class="td-name"><?= clean($h['first_name']) ?> <?= clean($h['last_name']) ?></span>
              </div>
            </td>
            <td>
              <code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 6px;border-radius:4px">
                <?= clean($h['student_number']) ?>
              </code>
            </td>
            <td><?= clean($h['class_name']) ?></td>
            <td>
              <span class="badge badge-<?= $sc['badge'] ?>">
                <i class="bx <?= $sc['icon'] ?>"></i>
                <?= clean($h['status']) ?>
              </span>
            </td>
            <td style="color:var(--text-muted);font-size:12.5px">
              <?= clean($h['reason'] ?? '—') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // fin mode historique ?>

</div>
</div>
</div>

<?php
$pageScript = "
// Afficher/cacher le champ motif selon le statut
function handleAttChange(studentId, status) {
  const reasonInput = document.getElementById('reason-' + studentId);
  if (!reasonInput) return;
  if (status === 'absent' || status === 'excuse') {
    reasonInput.style.display = '';
    reasonInput.focus();
  } else {
    reasonInput.style.display = 'none';
    reasonInput.value = '';
  }

  // Colorer la ligne selon le statut
  const row = document.getElementById('row-' + studentId);
  if (!row) return;
  const colors = {
    present: 'rgba(16,185,129,0.06)',
    absent : 'rgba(239,68,68,0.06)',
    retard : 'rgba(245,158,11,0.06)',
    excuse : 'rgba(99,102,241,0.06)',
  };
  row.style.background = colors[status] || '';
}

// Marquer tous les eleves avec un meme statut
function markAll(status) {
  document.querySelectorAll('input[type=radio][value=' + status + ']').forEach(radio => {
    radio.checked = true;
    const sid = radio.name.match(/\d+/)?.[0];
    if (sid) handleAttChange(parseInt(sid), status);
  });
}

// Appliquer couleur initiale pour les presences deja enregistrees
document.querySelectorAll('input[type=radio]:checked').forEach(radio => {
  const sid = radio.name.match(/\d+/)?.[0];
  if (sid) handleAttChange(parseInt(sid), radio.value);
});
";
require_once INCLUDES_PATH . '/footer.php';
?>
