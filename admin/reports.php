<?php
// ============================================================
//  SmartSchool — Bulletins scolaires
//  Emplacement : admin/reports.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_TEACHER);

$pageTitle   = 'Bulletins scolaires';
$pageSection = 'reports';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;
$classes     = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name", [$yearId]);
$terms       = dbFetchAll("SELECT id, name FROM terms WHERE academic_year_id = ? ORDER BY start_date", [$yearId]);
$currentTerm = dbFetchOne("SELECT id, name FROM terms WHERE is_current = 1");

// ── Generer bulletin pour un eleve ───────────────────────
if (isset($_GET['generate']) && isset($_GET['student_id']) && isset($_GET['term_id'])) {
    $studentId = (int)$_GET['student_id'];
    $termId    = (int)$_GET['term_id'];

    // Calculer moyenne
    $avgData = dbFetchOne(
        "SELECT ROUND(AVG(g.score/g.max_score*20),2) avg_score
         FROM grades g WHERE g.student_id = ? AND g.term_id = ?",
        [$studentId, $termId]
    );
    $average = $avgData['avg_score'] ?? 0;

    $existing = dbFetchOne(
        "SELECT id FROM report_cards WHERE student_id=? AND term_id=?",
        [$studentId, $termId]
    );

    if ($existing) {
        dbExecute("UPDATE report_cards SET average=?, is_published=1 WHERE id=?", [$average, $existing['id']]);
    } else {
        dbExecute(
            "INSERT INTO report_cards (student_id, term_id, average, is_published) VALUES (?,?,?,1)",
            [$studentId, $termId, $average]
        );
    }
    redirectWith(BASE_URL . '/admin/reports.php?view=' . $studentId . '&term=' . $termId,
        'success', 'Bulletin genere avec succes !');
}

$viewMode = trim($_GET['mode'] ?? 'list');

// ── VUE LISTE ─────────────────────────────────────────────
$filterClass = (int)($_GET['class'] ?? 0);
$filterTerm  = (int)($_GET['term']  ?? ($currentTerm['id'] ?? 0));

$where  = ['s.status = "actif"'];
if ($filterClass) $where[] = "s.class_id = $filterClass";
$whereStr = 'WHERE ' . implode(' AND ', $where);

$students = dbFetchAll(
    "SELECT s.id, u.first_name, u.last_name, s.student_number, c.name AS class_name,
            (SELECT ROUND(AVG(g.score/g.max_score*20),2) FROM grades g WHERE g.student_id = s.id AND g.term_id = $filterTerm) AS average,
            (SELECT id FROM report_cards rc WHERE rc.student_id = s.id AND rc.term_id = $filterTerm) AS report_id
     FROM students s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     $whereStr
     ORDER BY u.last_name, u.first_name"
);

// ── VUE DETAIL D'UN BULLETIN ──────────────────────────────
$reportStudent = null;
$reportGrades  = [];
$reportAvg     = 0;
$reportMention = null;

if ($viewMode === 'detail' && isset($_GET['student_id'])) {
    $sid  = (int)$_GET['student_id'];
    $tid  = (int)($_GET['term'] ?? $filterTerm);

    $reportStudent = dbFetchOne(
        "SELECT s.*, u.first_name, u.last_name, u.email, u.date_of_birth, c.name AS class_name
         FROM students s JOIN users u ON s.user_id=u.id
         LEFT JOIN classes c ON s.class_id=c.id
         WHERE s.id = ?", [$sid]
    );

    $reportGrades = dbFetchAll(
        "SELECT sub.name AS subject, sub.coefficient,
                ROUND(AVG(g.score/g.max_score*20),2) AS avg_score,
                COUNT(g.id) AS nb_grades
         FROM grades g
         JOIN class_subjects cs ON g.class_subject_id = cs.id
         JOIN subjects sub ON cs.subject_id = sub.id
         WHERE g.student_id = ? AND g.term_id = ?
         GROUP BY sub.id, sub.name, sub.coefficient
         ORDER BY sub.name",
        [$sid, $tid]
    );

    $sumWeighted = 0; $sumCoeff = 0;
    foreach ($reportGrades as $g) {
        $sumWeighted += $g['avg_score'] * $g['coefficient'];
        $sumCoeff    += $g['coefficient'];
    }
    $reportAvg     = $sumCoeff > 0 ? round($sumWeighted / $sumCoeff, 2) : 0;
    $reportMention = getMention($reportAvg);
}

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<?php if ($viewMode !== 'detail'): ?>
<!-- ══ VUE LISTE ══ -->
<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-file" style="color:var(--primary)"></i>
      Bulletins scolaires
    </h1>
    <p>Generez et consultez les bulletins des eleves</p>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Classe</label>
        <select name="class" class="form-control">
          <option value="">Toutes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:150px">
        <label class="form-label">Trimestre</label>
        <select name="term" class="form-control">
          <?php foreach ($terms as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterTerm==$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt"></i> Filtrer</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Eleve</th><th>Classe</th><th>Moyenne</th><th>Mention</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-file"></i></div>
              <h3>Aucun eleve</h3>
            </div>
          </td></tr>
        <?php else: foreach ($students as $s):
          $avg = $s['average'] ?? 0;
          $mention = getMention($avg);
        ?>
        <tr>
          <td>
            <div class="td-user">
              <div class="avatar avatar-32" style="background:var(--primary)"><?= getInitials($s['first_name'],$s['last_name']) ?></div>
              <div>
                <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                <div class="td-sub"><?= clean($s['student_number']) ?></div>
              </div>
            </div>
          </td>
          <td><?= clean($s['class_name'] ?? '—') ?></td>
          <td style="font-weight:700;color:<?= $avg>=10?'var(--success)':'var(--danger)' ?>"><?= $avg ?: '—' ?>/20</td>
          <td><span class="badge" style="background:<?= $mention['color'] ?>20;color:<?= $mention['color'] ?>"><?= $mention['label'] ?></span></td>
          <td>
            <span class="badge badge-<?= $s['report_id'] ? 'success' : 'gray' ?>">
              <?= $s['report_id'] ? 'Genere' : 'Non genere' ?>
            </span>
          </td>
          <td>
            <div class="td-actions">
              <a href="?mode=detail&student_id=<?= $s['id'] ?>&term=<?= $filterTerm ?>" class="btn btn-sm btn-secondary btn-icon" title="Voir">
                <i class="bx bx-show"></i>
              </a>
              <a href="?generate=1&student_id=<?= $s['id'] ?>&term_id=<?= $filterTerm ?>" class="btn btn-sm btn-primary btn-icon" title="Generer">
                <i class="bx bx-refresh"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ══ VUE DETAIL BULLETIN ══ -->
<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-file" style="color:var(--primary)"></i>
      Bulletin scolaire
    </h1>
  </div>
  <div class="page-header-actions">
    <a href="?mode=list" class="btn btn-secondary"><i class="bx bx-arrow-back"></i> Retour</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="bx bx-printer"></i> Imprimer</button>
  </div>
</div>

<?php if ($reportStudent): ?>
<div class="card" id="bulletinPrint">
  <div class="card-body" style="padding:36px">

    <!-- En-tete bulletin -->
    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid var(--primary);padding-bottom:20px;margin-bottom:24px">
      <div>
        <h2 style="font-size:20px;font-weight:800;color:var(--primary)"><?= clean(getSetting('school_name', APP_NAME)) ?></h2>
        <p style="font-size:12px;color:var(--text-muted)">Bulletin de notes — <?= clean($terms[array_search($filterTerm, array_column($terms,'id'))]['name'] ?? '') ?></p>
      </div>
      <div style="text-align:right">
        <div class="avatar avatar-56" style="background:var(--primary);margin-left:auto"><?= getInitials($reportStudent['first_name'],$reportStudent['last_name']) ?></div>
      </div>
    </div>

    <!-- Infos eleve -->
    <div class="grid-2" style="margin-bottom:28px">
      <div>
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">Eleve</div>
        <div style="font-size:16px;font-weight:700;color:var(--text-primary)"><?= clean($reportStudent['first_name']) ?> <?= clean($reportStudent['last_name']) ?></div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">Matricule</div>
        <div style="font-size:16px;font-weight:700;color:var(--text-primary)"><?= clean($reportStudent['student_number']) ?></div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">Classe</div>
        <div style="font-size:14px;color:var(--text-secondary)"><?= clean($reportStudent['class_name'] ?? '—') ?></div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">Date</div>
        <div style="font-size:14px;color:var(--text-secondary)"><?= date('d/m/Y') ?></div>
      </div>
    </div>

    <!-- Tableau notes -->
    <table class="table" style="margin-bottom:24px">
      <thead>
        <tr><th>Matiere</th><th>Coefficient</th><th>Nb evaluations</th><th>Moyenne /20</th><th>Mention</th></tr>
      </thead>
      <tbody>
        <?php foreach ($reportGrades as $g):
          $m = getMention($g['avg_score']);
        ?>
        <tr>
          <td style="font-weight:600"><?= clean($g['subject']) ?></td>
          <td><?= number_format($g['coefficient'],1) ?></td>
          <td><?= $g['nb_grades'] ?></td>
          <td style="font-weight:700;color:<?= $g['avg_score']>=10?'var(--success)':'var(--danger)' ?>"><?= $g['avg_score'] ?>/20</td>
          <td><span class="badge" style="background:<?= $m['color'] ?>20;color:<?= $m['color'] ?>"><?= $m['label'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Moyenne generale -->
    <div style="background:var(--grad-primary);border-radius:var(--radius-lg);padding:24px;text-align:center;color:#fff;margin-bottom:24px">
      <div style="font-size:13px;opacity:0.85">Moyenne generale</div>
      <div style="font-size:42px;font-weight:800"><?= $reportAvg ?>/20</div>
      <div style="font-size:14px;font-weight:600;margin-top:6px"><?= $reportMention['label'] ?></div>
    </div>

    <div class="form-group">
      <a href="?generate=1&student_id=<?= $reportStudent['id'] ?>&term_id=<?= $filterTerm ?>" class="btn btn-primary">
        <i class="bx bx-save"></i> Publier ce bulletin
      </a>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>
</div>

<style>
  @media print {
    .sidebar, .topbar, .page-header-actions, .btn { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
  }
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>