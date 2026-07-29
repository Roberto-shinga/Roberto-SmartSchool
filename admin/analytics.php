<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Analytique';
$pageSection = 'analytics';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Effectifs par niveau ───────────────────────────────────────
$byLevel = dbFetchAll(
    "SELECT l.name, COUNT(s.id) c FROM levels l
     LEFT JOIN students s ON s.level_id = l.id AND s.status='actif' AND s.academic_year_id=?
     GROUP BY l.id, l.name ORDER BY l.order_index",
    [$yearId]
);
$maxLevel = 1; foreach ($byLevel as $l) $maxLevel = max($maxLevel, (int)$l['c']);

// ── Repartition par genre ──────────────────────────────────────
$byGender = dbFetchAll(
    "SELECT COALESCE(s.gender, u.gender) AS gender, COUNT(*) c
     FROM students s LEFT JOIN users u ON s.user_id = u.id
     WHERE s.status='actif' AND s.academic_year_id=? GROUP BY gender",
    [$yearId]
);
$genderMap = ['M' => 0, 'F' => 0];
foreach ($byGender as $g) if (isset($genderMap[$g['gender']])) $genderMap[$g['gender']] = (int)$g['c'];
$totalGender = max(1, array_sum($genderMap));

// ── Moyenne generale par classe (dernier trimestre avec des notes) ─
$avgByClass = dbFetchAll(
    "SELECT c.name, AVG(rc.average) avg_score, COUNT(DISTINCT rc.student_id) nb
     FROM report_cards rc
     JOIN students s ON rc.student_id = s.id
     JOIN classes c ON s.class_id = c.id
     WHERE c.academic_year_id = ?
     GROUP BY c.id, c.name HAVING nb > 0 ORDER BY avg_score DESC LIMIT 10",
    [$yearId]
);

// ── Taux de presence (30 derniers jours) ───────────────────────
$attendanceRate = dbFetchOne(
    "SELECT ROUND(SUM(status='present') / NULLIF(COUNT(*),0) * 100, 1) rate
     FROM attendance WHERE date >= CURDATE() - INTERVAL 30 DAY"
)['rate'] ?? null;

$attendanceByClass = dbFetchAll(
    "SELECT c.name, ROUND(SUM(a.status='present') / NULLIF(COUNT(*),0) * 100, 1) rate
     FROM attendance a JOIN classes c ON a.class_id = c.id
     WHERE a.date >= CURDATE() - INTERVAL 30 DAY AND c.academic_year_id = ?
     GROUP BY c.id, c.name ORDER BY rate ASC LIMIT 8",
    [$yearId]
);

// ── Charge des enseignants ─────────────────────────────────────
$teacherLoad = dbFetchAll(
    "SELECT u.first_name, u.last_name, COUNT(DISTINCT ta.class_id) nb_classes,
            COUNT(DISTINCT ta.subject_id) nb_subjects, SUM(ta.hours_per_week) nb_hours
     FROM teacher_assignments ta
     JOIN teachers t ON ta.teacher_id = t.id
     JOIN users u ON t.user_id = u.id
     WHERE ta.academic_year_id = ?
     GROUP BY u.id, u.first_name, u.last_name ORDER BY nb_hours DESC LIMIT 10",
    [$yearId]
);

// ── Inscriptions par mois (annee en cours) ─────────────────────
$enrollTrend = dbFetchAll(
    "SELECT DATE_FORMAT(enrollment_date, '%Y-%m') ym, COUNT(*) c
     FROM students WHERE academic_year_id = ? GROUP BY ym ORDER BY ym",
    [$yearId]
);
$maxEnroll = 1; foreach ($enrollTrend as $e) $maxEnroll = max($maxEnroll, (int)$e['c']);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Analytique</h1><p>Vue d'ensemble pedagogique — annee <?= clean($currentYear['name'] ?? '—') ?></p></div>
    </div>

    <div class="grid-3 mb-6">
      <div class="stat-card c-primary">
        <div class="stat-icon c-primary"><i class="bx bx-graduation"></i></div>
        <div><div class="stat-value"><?= array_sum(array_column($byLevel, 'c')) ?></div><div class="stat-label">Eleves actifs</div></div>
      </div>
      <div class="stat-card c-success">
        <div class="stat-icon c-success"><i class="bx bx-check-square"></i></div>
        <div><div class="stat-value"><?= $attendanceRate !== null ? $attendanceRate . '%' : '—' ?></div><div class="stat-label">Presence (30j)</div></div>
      </div>
      <div class="stat-card c-purple">
        <div class="stat-icon c-purple"><i class="bx bx-male-female"></i></div>
        <div><div class="stat-value"><?= round(($genderMap['F'] / $totalGender) * 100) ?>% F / <?= round(($genderMap['M'] / $totalGender) * 100) ?>% M</div><div class="stat-label">Repartition genre</div></div>
      </div>
    </div>

    <div class="grid-2 mb-6">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-bar-chart-alt-2"></i> Effectifs par niveau</h3></div>
        <div class="card-body">
          <?php foreach ($byLevel as $l): ?>
            <div style="margin-bottom:14px">
              <div class="flex justify-between text-sm" style="margin-bottom:6px"><span><?= clean($l['name']) ?></span><span class="font-semibold"><?= (int)$l['c'] ?></span></div>
              <div class="progress"><div class="progress-bar" data-value="<?= round(($l['c']/$maxLevel)*100) ?>" style="width:0"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-trending-up"></i> Inscriptions par mois</h3></div>
        <div class="card-body">
          <?php if (empty($enrollTrend)): ?><p class="text-sm text-muted">Aucune donnee.</p><?php else: foreach ($enrollTrend as $e): ?>
            <div style="margin-bottom:14px">
              <div class="flex justify-between text-sm" style="margin-bottom:6px"><span><?= clean($e['ym']) ?></span><span class="font-semibold"><?= (int)$e['c'] ?></span></div>
              <div class="progress"><div class="progress-bar success" data-value="<?= round(($e['c']/$maxEnroll)*100) ?>" style="width:0"></div></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="grid-2 mb-6">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-medal"></i> Meilleures moyennes par classe</h3></div>
        <?php if (empty($avgByClass)): ?>
          <div class="card-body"><p class="text-sm text-muted">Aucun bulletin calcule pour l'instant.</p></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table"><thead><tr><th>Classe</th><th>Moyenne</th></tr></thead>
          <tbody><?php foreach ($avgByClass as $a): $m = getMention((float)$a['avg_score']); ?>
            <tr><td class="text-sm"><?= clean($a['name']) ?></td><td class="font-semibold" style="color:<?= $m['color'] ?>"><?= number_format((float)$a['avg_score'],2) ?>/20</td></tr>
          <?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-error-circle"></i> Classes a surveiller (presence)</h3></div>
        <?php if (empty($attendanceByClass)): ?>
          <div class="card-body"><p class="text-sm text-muted">Aucune donnee de presence recente.</p></div>
        <?php else: ?>
        <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
          <table class="table"><thead><tr><th>Classe</th><th>Taux de presence</th></tr></thead>
          <tbody><?php foreach ($attendanceByClass as $a): ?>
            <tr><td class="text-sm"><?= clean($a['name']) ?></td><td class="font-semibold" style="color:<?= $a['rate'] < 80 ? 'var(--danger)' : 'var(--success)' ?>"><?= $a['rate'] ?>%</td></tr>
          <?php endforeach; ?></tbody></table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="bx bx-chalkboard"></i> Charge des enseignants</h3></div>
      <?php if (empty($teacherLoad)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-chalkboard"></i></div><p>Aucune affectation enregistree.</p></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Enseignant</th><th>Classes</th><th>Matieres</th><th>Heures/semaine</th></tr></thead>
          <tbody>
            <?php foreach ($teacherLoad as $t): ?>
            <tr>
              <td class="text-sm"><?= clean($t['first_name'] . ' ' . $t['last_name']) ?></td>
              <td class="text-sm"><?= (int)$t['nb_classes'] ?></td>
              <td class="text-sm"><?= (int)$t['nb_subjects'] ?></td>
              <td class="font-semibold"><?= (int)$t['nb_hours'] ?>h</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
