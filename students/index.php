<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle  = 'Tableau de bord';
$pageSection = 'dashboard';
$user       = currentUser();
$studentId  = getCurrentStudentId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$currentTerm = dbFetchOne("SELECT * FROM terms WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

if (!$studentId) {
    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    echo '<div class="main-content"><div class="page-wrapper"><div class="alert alert-danger">
          <i class="bx bx-error-circle"></i> Aucune fiche eleve n\'est liee a ce compte. Contacte l\'administration.
          </div></div></div>';
    require INCLUDES_PATH . '/footer.php';
    exit;
}

$student = dbFetchOne(
    "SELECT s.*, c.name AS class_name, c.id AS class_id FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=?",
    [$studentId]
);

// ── Bulletin du trimestre courant (si publie) ──────────────────
$report = $currentTerm ? dbFetchOne(
    "SELECT * FROM report_cards WHERE student_id=? AND term_id=? AND is_published=1",
    [$studentId, $currentTerm['id']]
) : null;

// ── Taux de presence (30 derniers jours) ───────────────────────
$attStats = dbFetchOne(
    "SELECT COUNT(*) total, SUM(status='present') present FROM attendance WHERE student_id=? AND date >= CURDATE() - INTERVAL 30 DAY",
    [$studentId]
);
$attRate = ($attStats && $attStats['total'] > 0) ? round(($attStats['present'] / $attStats['total']) * 100) : null;

// ── Emploi du temps du jour ─────────────────────────────────────
$todayDow = (int)date('N');
$todaySlots = $student['class_id'] ? dbFetchAll(
    "SELECT tt.*, sub.name AS subject_name, sub.color, tu.first_name AS t_fn, tu.last_name AS t_ln
     FROM timetable tt
     JOIN teacher_assignments ta ON tt.assignment_id = ta.id
     JOIN subjects sub ON ta.subject_id = sub.id
     JOIN teachers te ON ta.teacher_id = te.id
     JOIN users tu ON te.user_id = tu.id
     WHERE ta.class_id = ? AND tt.academic_year_id = ? AND tt.day_of_week = ?
     ORDER BY tt.start_time",
    [$student['class_id'], $yearId, $todayDow]
) : [];

// ── Dernieres notes ─────────────────────────────────────────────
$recentGrades = dbFetchAll(
    "SELECT g.*, sub.name AS subject_name, sub.color
     FROM grades g JOIN teacher_assignments ta ON g.assignment_id=ta.id JOIN subjects sub ON ta.subject_id=sub.id
     WHERE g.student_id=? ORDER BY g.date DESC LIMIT 6",
    [$studentId]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="hero-banner animate-in">
      <div class="hero-content">
        <div class="hero-title">Bonjour, <?= clean($user['first_name']) ?> 👋</div>
        <div class="hero-sub"><?= clean($student['class_name'] ?? '—') ?> · <?= clean($currentYear['name'] ?? '—') ?></div>
        <div class="hero-stats">
          <div><div class="hero-stat-val"><?= $report ? number_format((float)$report['average'],1) : '—' ?></div><div class="hero-stat-lbl">Moyenne</div></div>
          <div><div class="hero-stat-val"><?= $report && $report['rank'] ? '#'.$report['rank'] : '—' ?></div><div class="hero-stat-lbl">Rang</div></div>
          <div><div class="hero-stat-val"><?= $attRate !== null ? $attRate.'%' : '—' ?></div><div class="hero-stat-lbl">Presence (30j)</div></div>
        </div>
      </div>
      <div class="hero-icon"><i class="bx bx-graduation"></i></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-calendar-alt"></i> Aujourd'hui — <?= DAYS_OF_WEEK[$todayDow] ?? '' ?></h3></div>
        <div class="card-body">
          <?php if (empty($todaySlots)): ?>
            <p class="text-sm text-muted">Aucun cours prevu aujourd'hui.</p>
          <?php else: foreach ($todaySlots as $s): ?>
            <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--border-light)">
              <div class="text-sm font-mono text-muted" style="white-space:nowrap"><?= substr($s['start_time'],0,5) ?></div>
              <div style="flex:1">
                <div class="text-sm font-semibold"><?= clean($s['subject_name']) ?></div>
                <div class="text-xs text-muted"><?= clean($s['t_fn'] . ' ' . $s['t_ln']) ?><?= $s['room'] ? ' · ' . clean($s['room']) : '' ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-star"></i> Dernieres notes</h3></div>
        <div class="card-body" style="padding:8px 0">
          <?php if (empty($recentGrades)): ?>
            <div class="empty-state" style="padding:20px"><p class="text-sm text-muted">Aucune note pour l'instant.</p></div>
          <?php else: foreach ($recentGrades as $g): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 20px;border-bottom:1px solid var(--border-light)">
              <div>
                <div class="text-sm font-semibold"><?= clean($g['subject_name']) ?></div>
                <div class="text-xs text-muted"><?= ucfirst($g['grade_type']) ?> · <?= formatDate($g['date']) ?></div>
              </div>
              <div class="font-semibold"><?= number_format((float)$g['score'],1) ?>/<?= number_format((float)$g['max_score'],1) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
