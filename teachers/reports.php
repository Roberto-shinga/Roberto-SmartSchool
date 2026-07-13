<?php
// ============================================================
//  SmartSchool — Bulletins (Enseignant)
//  Emplacement : teachers/reports.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_TEACHER);

$pageTitle   = 'Bulletins';
$pageSection = 'reports';
$user        = currentUser();

$teacher   = dbFetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user['id']]);
$teacherId = $teacher['id'] ?? 0;
$yearId    = dbFetchOne("SELECT id FROM academic_years WHERE is_current=1")['id'] ?? 1;
$terms     = dbFetchAll("SELECT id, name FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);
$currentTerm = dbFetchOne("SELECT id FROM terms WHERE is_current=1");

$myClasses = dbFetchAll(
    "SELECT DISTINCT c.id, c.name FROM class_subjects cs JOIN classes c ON cs.class_id=c.id
     WHERE cs.teacher_id=? AND cs.academic_year_id=? ORDER BY c.name",
    [$teacherId, $yearId]
);

$filterClass = (int)($_GET['class_id'] ?? ($myClasses[0]['id'] ?? 0));
$filterTerm  = (int)($_GET['term_id']  ?? ($currentTerm['id'] ?? 0));

$reports = [];
if ($filterClass && $filterTerm) {
    $reports = dbFetchAll(
        "SELECT rc.id, rc.average, rc.rank, rc.class_average, rc.conduct, rc.is_published,
                u.first_name, u.last_name, s.student_number
         FROM report_cards rc
         JOIN students s ON rc.student_id = s.id
         JOIN users u ON s.user_id = u.id
         WHERE s.class_id = ? AND rc.term_id = ? AND rc.is_published = 1
         ORDER BY rc.rank ASC",
        [$filterClass, $filterTerm]
    );
}

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-file" style="color:var(--primary)"></i> Bulletins
    </h1>
    <p>Consultez les bulletins publies de vos classes</p>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Classe</label>
        <select name="class_id" class="form-control">
          <?php foreach ($myClasses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Trimestre</label>
        <select name="term_id" class="form-control">
          <?php foreach ($terms as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterTerm==$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-search"></i> Afficher
      </button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Rang</th><th>Eleve</th><th>Moyenne</th><th>Moy. classe</th><th>Appreciation</th></tr>
      </thead>
      <tbody>
        <?php if (empty($reports)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-file"></i></div>
              <h3>Aucun bulletin publie</h3>
              <p>Les bulletins seront visibles une fois publies par l'administration.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($reports as $r):
            $avg     = $r['average'];
            $mention = getMention($avg ?? 0);
          ?>
          <tr>
            <td>
              <div style="width:28px;height:28px;border-radius:50%;background:<?= ($r['rank']??9)<=3?'var(--warning-bg)':'var(--bg-body)' ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:<?= ($r['rank']??9)<=3?'var(--warning-dark)':'var(--text-muted)' ?>">
                <?= $r['rank'] ?? '—' ?>
              </div>
            </td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:<?= ($avg??0)>=10?'var(--success)':'var(--danger)' ?>"><?= getInitials($r['first_name'],$r['last_name']) ?></div>
                <div>
                  <div class="td-name"><?= clean($r['first_name']) ?> <?= clean($r['last_name']) ?></div>
                  <div class="td-sub"><?= clean($r['student_number']) ?></div>
                </div>
              </div>
            </td>
            <td><span style="font-size:16px;font-weight:800;color:<?= ($avg??0)>=10?'var(--success)':'var(--danger)' ?>"><?= $avg ?? '—' ?>/20</span></td>
            <td style="color:var(--text-muted)"><?= $r['class_average'] ?? '—' ?>/20</td>
            <td>
              <span class="badge" style="background:<?= $mention['color'] ?>20;color:<?= $mention['color'] ?>"><?= $r['conduct'] ?? '—' ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>