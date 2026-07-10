<?php
// ============================================================
//  SmartSchool — Analytiques avancees
//  Emplacement : admin/analytics.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Analytiques';
$pageSection = 'analytics';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;
$currentTerm = dbFetchOne("SELECT id, name FROM terms WHERE is_current = 1");
$termId      = $currentTerm['id'] ?? 1;

// ── Stats globales ───────────────────────────────────────
$totalStudents  = dbFetchOne("SELECT COUNT(*) c FROM students WHERE status='actif'")['c'] ?? 0;
$totalTeachers  = dbFetchOne("SELECT COUNT(*) c FROM teachers WHERE status='actif'")['c'] ?? 0;
$totalClasses   = dbFetchOne("SELECT COUNT(*) c FROM classes WHERE academic_year_id=?", [$yearId])['c'] ?? 0;
$totalSubjects  = dbFetchOne("SELECT COUNT(*) c FROM subjects WHERE is_active=1")['c'] ?? 0;
$totalGrades    = dbFetchOne("SELECT COUNT(*) c FROM grades g JOIN class_subjects cs ON g.class_subject_id=cs.id WHERE cs.academic_year_id=?", [$yearId])['c'] ?? 0;
$avgGrade       = dbFetchOne("SELECT ROUND(AVG(score/max_score*20),2) c FROM grades")['c'] ?? 0;

// Taux de presence global
$attStats = dbFetchOne(
    "SELECT
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) present,
        COUNT(*) total
     FROM attendance WHERE YEAR(date) = YEAR(NOW())"
) ?: ['present'=>0,'total'=>0];
$attendanceRate = $attStats['total'] > 0 ? round(($attStats['present']/$attStats['total'])*100) : 0;

// ── Moyennes par matiere ─────────────────────────────────
$gradesBySubject = dbFetchAll("
    SELECT s.name, s.color, s.coefficient,
           ROUND(AVG(g.score/g.max_score*20),2) AS avg,
           COUNT(g.id) AS nb
    FROM grades g
    JOIN class_subjects cs ON g.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.academic_year_id = ?
    GROUP BY s.id, s.name, s.color, s.coefficient
    ORDER BY avg DESC",
    [$yearId]
);
$subjLabels = json_encode(array_column($gradesBySubject, 'name'));
$subjAvgs   = json_encode(array_column($gradesBySubject, 'avg'));
$subjColors = json_encode(array_column($gradesBySubject, 'color'));

// ── Moyennes par classe ──────────────────────────────────
$gradesByClass = dbFetchAll("
    SELECT c.name, ROUND(AVG(g.score/g.max_score*20),2) AS avg, COUNT(DISTINCT s.id) nb_students
    FROM grades g
    JOIN class_subjects cs ON g.class_subject_id = cs.id
    JOIN classes c ON cs.class_id = c.id
    JOIN students s ON g.student_id = s.id
    WHERE cs.academic_year_id = ?
    GROUP BY c.id, c.name
    ORDER BY avg DESC",
    [$yearId]
);
$classLabels = json_encode(array_column($gradesByClass, 'name'));
$classAvgs   = json_encode(array_column($gradesByClass, 'avg'));

// ── Distribution des notes ───────────────────────────────
$distribution = dbFetchOne("
    SELECT
        SUM(CASE WHEN score/max_score*20 >= 18 THEN 1 ELSE 0 END) excellent,
        SUM(CASE WHEN score/max_score*20 >= 16 AND score/max_score*20 < 18 THEN 1 ELSE 0 END) tres_bien,
        SUM(CASE WHEN score/max_score*20 >= 14 AND score/max_score*20 < 16 THEN 1 ELSE 0 END) bien,
        SUM(CASE WHEN score/max_score*20 >= 12 AND score/max_score*20 < 14 THEN 1 ELSE 0 END) assez_bien,
        SUM(CASE WHEN score/max_score*20 >= 10 AND score/max_score*20 < 12 THEN 1 ELSE 0 END) passable,
        SUM(CASE WHEN score/max_score*20 < 10 THEN 1 ELSE 0 END) insuffisant
    FROM grades"
) ?: [];

// ── Presence par jour de semaine ─────────────────────────
$attByDay = dbFetchAll("
    SELECT DAYNAME(date) jour, DAYOFWEEK(date) num,
           ROUND(AVG(CASE WHEN status='present' THEN 100.0 ELSE 0 END),1) taux
    FROM attendance
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DAYOFWEEK(date), DAYNAME(date)
    ORDER BY DAYOFWEEK(date)
");
$dayLabels = json_encode(array_column($attByDay, 'jour'));
$dayRates  = json_encode(array_column($attByDay, 'taux'));

// ── Top 10 meilleurs eleves ──────────────────────────────
$topStudents = dbFetchAll("
    SELECT u.first_name, u.last_name, s.student_number, c.name AS class_name,
           ROUND(AVG(g.score/g.max_score*20),2) AS avg
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    GROUP BY s.id, u.first_name, u.last_name, s.student_number, c.name
    ORDER BY avg DESC LIMIT 10"
);

// ── Eleves en difficulte ─────────────────────────────────
$weakStudents = dbFetchAll("
    SELECT u.first_name, u.last_name, s.student_number, c.name AS class_name,
           ROUND(AVG(g.score/g.max_score*20),2) AS avg,
           COUNT(g.id) AS nb_notes
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    GROUP BY s.id, u.first_name, u.last_name, s.student_number, c.name
    HAVING avg < 10 AND nb_notes >= 1
    ORDER BY avg ASC LIMIT 10"
);

// ── Taux de presence par classe ──────────────────────────
$attByClass = dbFetchAll("
    SELECT c.name,
           COUNT(*) total,
           SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) present
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON a.class_id = c.id
    WHERE YEAR(a.date) = YEAR(NOW())
    GROUP BY c.id, c.name
    ORDER BY c.name"
);

// ── Evolution inscriptions ───────────────────────────────
$enrollByMonth = dbFetchAll("
    SELECT DATE_FORMAT(enrollment_date,'%b') lbl,
           DATE_FORMAT(enrollment_date,'%Y-%m') ym,
           COUNT(*) nb
    FROM students
    WHERE YEAR(enrollment_date) = YEAR(NOW())
    GROUP BY ym, lbl ORDER BY ym"
);
$enrollLabels = json_encode(array_column($enrollByMonth,'lbl'));
$enrollValues = json_encode(array_column($enrollByMonth,'nb'));

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
      <i class="bx bx-bar-chart-alt-2" style="color:var(--primary)"></i>
      Analytiques avancees
    </h1>
    <p>Tableaux de bord et indicateurs de performance — <?= clean($currentYear['name'] ?? '') ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" onclick="window.print()">
      <i class="bx bx-printer"></i> Imprimer
    </button>
  </div>
</div>

<!-- KPI PRINCIPAUX -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:24px">
  <?php
  $kpis = [
    ['val'=>$totalStudents,  'label'=>'Eleves actifs',    'icon'=>'bx-graduation',    'color'=>'c-primary'],
    ['val'=>$totalTeachers,  'label'=>'Enseignants',       'icon'=>'bx-chalkboard',   'color'=>'c-success'],
    ['val'=>$totalClasses,   'label'=>'Classes',           'icon'=>'bx-building',     'color'=>'c-cyan'],
    ['val'=>$totalSubjects,  'label'=>'Matieres actives',  'icon'=>'bx-book-open',    'color'=>'c-purple'],
    ['val'=>$avgGrade.'/20', 'label'=>'Moyenne generale',  'icon'=>'bx-star',         'color'=>'c-warning'],
    ['val'=>$attendanceRate.'%','label'=>'Taux presence',  'icon'=>'bx-check-circle', 'color'=>'c-success'],
  ];
  foreach ($kpis as $k): ?>
  <div class="stat-card <?= $k['color'] ?>">
    <div class="stat-icon <?= $k['color'] ?>"><i class="bx <?= $k['icon'] ?>"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:22px"><?= $k['val'] ?></div>
      <div class="stat-label"><?= $k['label'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- GRAPHIQUES LIGNE 1 -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Moyennes par matiere -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-book-open"></i> Moyennes par matiere /20</h3>
    </div>
    <div class="card-body">
      <div style="position:relative;height:260px">
        <canvas id="chartSubjects"></canvas>
      </div>
    </div>
  </div>

  <!-- Distribution des notes -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-pie-chart-alt-2"></i> Distribution des notes</h3>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:20px">
        <div style="position:relative;height:200px;width:200px;flex-shrink:0">
          <canvas id="chartDistrib"></canvas>
        </div>
        <div style="flex:1">
          <?php
          $distItems = [
            ['label'=>'Excellent (18-20)',   'val'=>$distribution['excellent']  ?? 0, 'color'=>'#10b981'],
            ['label'=>'Tres bien (16-18)',   'val'=>$distribution['tres_bien']  ?? 0, 'color'=>'#6366f1'],
            ['label'=>'Bien (14-16)',         'val'=>$distribution['bien']       ?? 0, 'color'=>'#3b82f6'],
            ['label'=>'Assez bien (12-14)',   'val'=>$distribution['assez_bien'] ?? 0, 'color'=>'#f59e0b'],
            ['label'=>'Passable (10-12)',     'val'=>$distribution['passable']   ?? 0, 'color'=>'#f97316'],
            ['label'=>'Insuffisant (<10)',    'val'=>$distribution['insuffisant']?? 0, 'color'=>'#ef4444'],
          ];
          foreach ($distItems as $d): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border-light)">
            <div style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text-secondary)">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= $d['color'] ?>;flex-shrink:0"></span>
              <?= $d['label'] ?>
            </div>
            <span style="font-size:13px;font-weight:700;color:var(--text-primary)"><?= $d['val'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- GRAPHIQUES LIGNE 2 -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Moyennes par classe -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-building"></i> Moyennes par classe</h3>
    </div>
    <div class="card-body">
      <div style="position:relative;height:240px">
        <canvas id="chartClasses"></canvas>
      </div>
    </div>
  </div>

  <!-- Presence par jour -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-calendar-check"></i> Taux de presence par jour (30j)</h3>
    </div>
    <div class="card-body">
      <div style="position:relative;height:240px">
        <canvas id="chartAttDay"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- PRESENCE PAR CLASSE -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3><i class="bx bx-check-square"></i> Taux de presence par classe (annee en cours)</h3>
  </div>
  <div class="card-body">
    <?php if (empty($attByClass)): ?>
      <p style="color:var(--text-muted);text-align:center;padding:24px">Aucune donnee de presence.</p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:14px">
        <?php foreach ($attByClass as $ac):
          $rate = $ac['total'] > 0 ? round(($ac['present']/$ac['total'])*100) : 0;
          $barColor = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
        ?>
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:5px">
            <span style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= clean($ac['name']) ?></span>
            <span style="font-size:13px;font-weight:700;color:var(--text-primary)"><?= $rate ?>%</span>
          </div>
          <div class="progress">
            <div class="progress-bar <?= $barColor ?>" style="width:<?= $rate ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- TOP ELEVES + ELEVES EN DIFFICULTE -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Top 10 eleves -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-trophy" style="color:#f59e0b"></i> Top 10 meilleurs eleves</h3>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Eleve</th>
            <th>Classe</th>
            <th>Moyenne</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($topStudents)): ?>
            <tr><td colspan="4">
              <div class="empty-state" style="padding:24px">
                <p>Aucune note enregistree.</p>
              </div>
            </td></tr>
          <?php else: ?>
            <?php foreach ($topStudents as $i => $s):
              $mention = getMention($s['avg']);
            ?>
            <tr>
              <td>
                <?php if ($i < 3): ?>
                  <span style="font-size:1.2rem"><?= ['🥇','🥈','🥉'][$i] ?></span>
                <?php else: ?>
                  <span style="font-size:13px;color:var(--text-muted)"><?= $i+1 ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32" style="background:var(--primary)">
                    <?= getInitials($s['first_name'], $s['last_name']) ?>
                  </div>
                  <div>
                    <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                    <div class="td-sub"><?= clean($s['student_number']) ?></div>
                  </div>
                </div>
              </td>
              <td style="color:var(--text-muted)"><?= clean($s['class_name'] ?? '—') ?></td>
              <td>
                <span style="font-weight:700;font-size:15px;color:var(--success)"><?= $s['avg'] ?>/20</span>
                <div><span class="badge" style="font-size:10px;background:<?= $mention['color'] ?>20;color:<?= $mention['color'] ?>"><?= $mention['label'] ?></span></div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Eleves en difficulte -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-error-circle" style="color:var(--danger)"></i> Eleves en difficulte</h3>
      <span class="badge badge-danger"><?= count($weakStudents) ?></span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Eleve</th>
            <th>Classe</th>
            <th>Moyenne</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($weakStudents)): ?>
            <tr><td colspan="3">
              <div class="empty-state" style="padding:24px">
                <div class="empty-state-icon"><i class="bx bx-check-circle"></i></div>
                <h3>Aucun eleve en difficulte</h3>
              </div>
            </td></tr>
          <?php else: ?>
            <?php foreach ($weakStudents as $s): ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32" style="background:var(--danger)">
                    <?= getInitials($s['first_name'], $s['last_name']) ?>
                  </div>
                  <div>
                    <div class="td-name"><?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?></div>
                    <div class="td-sub"><?= clean($s['student_number']) ?></div>
                  </div>
                </div>
              </td>
              <td style="color:var(--text-muted)"><?= clean($s['class_name'] ?? '—') ?></td>
              <td>
                <span style="font-weight:700;font-size:15px;color:var(--danger)"><?= $s['avg'] ?>/20</span>
                <div style="font-size:11px;color:var(--text-muted)"><?= $s['nb_notes'] ?> note(s)</div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- INSCRIPTIONS PAR MOIS -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3><i class="bx bx-user-plus"></i> Evolution des inscriptions (annee en cours)</h3>
  </div>
  <div class="card-body">
    <div style="position:relative;height:200px">
      <canvas id="chartEnroll"></canvas>
    </div>
  </div>
</div>

</div>
</div>
</div>

<?php
$distribValues = json_encode([
    $distribution['excellent']   ?? 0,
    $distribution['tres_bien']   ?? 0,
    $distribution['bien']        ?? 0,
    $distribution['assez_bien']  ?? 0,
    $distribution['passable']    ?? 0,
    $distribution['insuffisant'] ?? 0,
]);

$pageScript = "
document.addEventListener('DOMContentLoaded', function() {

  // Moyennes par matiere
  SS_Charts.bar('chartSubjects', $subjLabels,
    [{ label: 'Moyenne /20', data: $subjAvgs, color: '#6366f1' }],
    { scales: { y: { max: 20 } }, plugins: { legend: { display: false } } }
  );

  // Distribution
  SS_Charts.donut('chartDistrib',
    ['Excellent','Tres bien','Bien','Assez bien','Passable','Insuffisant'],
    $distribValues,
    ['#10b981','#6366f1','#3b82f6','#f59e0b','#f97316','#ef4444'],
    { plugins: { legend: { display: false } } }
  );

  // Moyennes par classe
  SS_Charts.bar('chartClasses', $classLabels,
    [{ label: 'Moyenne /20', data: $classAvgs, color: '#06b6d4' }],
    { scales: { y: { max: 20 } }, plugins: { legend: { display: false } } }
  );

  // Presence par jour
  SS_Charts.line('chartAttDay', $dayLabels,
    [{ label: '% Presence', data: $dayRates, color: '#10b981', fill: true }],
    { scales: { y: { min: 0, max: 100 } } }
  );

  // Inscriptions
  SS_Charts.bar('chartEnroll', $enrollLabels,
    [{ label: 'Inscriptions', data: $enrollValues, color: '#8b5cf6' }],
    { plugins: { legend: { display: false } } }
  );
});
";
require_once INCLUDES_PATH . '/footer.php';
?>