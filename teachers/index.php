<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireTeacher();

$pageTitle   = 'Tableau de bord';
$pageSection = 'dashboard';
$user        = currentUser();
$teacherId   = getCurrentTeacherId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

if (!$teacherId) {
    // Securite : un compte role=enseignant sans fiche teachers ne devrait pas exister,
    // mais on ne laisse jamais planter la page si les donnees sont incoherentes.
    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    echo '<div class="main-content"><div class="page-wrapper"><div class="alert alert-danger">
          <i class="bx bx-error-circle"></i> Aucune fiche enseignant n\'est liee a ce compte. Contactez l\'administration.
          </div></div></div>';
    require INCLUDES_PATH . '/footer.php';
    exit;
}

$assignments = getTeacherAssignments($teacherId, $yearId);
$classes     = getTeacherClasses($teacherId, $yearId);
$classIds    = array_column($classes, 'id');

$nbStudents = 0;
if (!empty($classIds)) {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $nbStudents = dbFetchOne(
        "SELECT COUNT(*) c FROM students WHERE status='actif' AND class_id IN ($placeholders)",
        $classIds
    )['c'] ?? 0;
}

// ── Emploi du temps du jour ────────────────────────────────────
$todayDow = (int)date('N'); // 1=lundi ... 7=dimanche
$todaySlots = dbFetchAll(
    "SELECT tt.*, c.name AS class_name, s.name AS subject_name, s.color
     FROM timetable tt
     JOIN teacher_assignments ta ON tt.assignment_id = ta.id
     JOIN classes c ON ta.class_id = c.id
     JOIN subjects s ON ta.subject_id = s.id
     WHERE ta.teacher_id = ? AND tt.academic_year_id = ? AND tt.day_of_week = ?
     ORDER BY tt.start_time",
    [$teacherId, $yearId, $todayDow]
);

// ── Dernieres notes saisies par ce professeur ──────────────────
$recentGrades = dbFetchAll(
    "SELECT g.*, s.name AS subject_name, c.name AS class_name,
            COALESCE(st.first_name, u.first_name) AS fn, COALESCE(st.last_name, u.last_name) AS ln
     FROM grades g
     JOIN teacher_assignments ta ON g.assignment_id = ta.id
     JOIN subjects s ON ta.subject_id = s.id
     JOIN classes c ON ta.class_id = c.id
     JOIN students st ON g.student_id = st.id
     LEFT JOIN users u ON st.user_id = u.id
     WHERE ta.teacher_id = ?
     ORDER BY g.created_at DESC LIMIT 6",
    [$teacherId]
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
        <div class="hero-sub">Annee scolaire <?= clean($currentYear['name'] ?? '—') ?></div>
        <div class="hero-stats">
          <div><div class="hero-stat-val"><?= count($classes) ?></div><div class="hero-stat-lbl">Classe(s)</div></div>
          <div><div class="hero-stat-val"><?= count(array_unique(array_column($assignments, 'subject_id'))) ?></div><div class="hero-stat-lbl">Matiere(s)</div></div>
          <div><div class="hero-stat-val"><?= $nbStudents ?></div><div class="hero-stat-lbl">Eleves</div></div>
        </div>
      </div>
      <div class="hero-icon"><i class="bx bx-chalkboard"></i></div>
    </div>

    <div class="page-header">
      <div><h1>Mes classes et matieres</h1><p>Acces limite a tes affectations pour cette annee scolaire</p></div>
    </div>

    <?php if (empty($assignments)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-error"></i></div>
        <h3>Aucune affectation pour l'instant</h3>
        <p>L'administration ne t'a pas encore attribue de classe ou de matiere.</p>
      </div>
    <?php else: ?>
    <div class="grid-3 mb-6">
      <?php foreach ($assignments as $a): ?>
      <div class="card">
        <div class="card-body">
          <div class="flex items-center gap-2" style="margin-bottom:8px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= clean($a['subject_color']) ?>"></span>
            <span class="font-semibold"><?= clean($a['subject_name']) ?></span>
          </div>
          <div class="text-sm text-muted"><i class="bx bx-buildings"></i> <?= clean($a['class_name']) ?></div>
          <div class="text-xs text-muted" style="margin-top:4px"><?= (int)$a['hours_per_week'] ?>h / semaine</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

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
                <div class="text-xs text-muted"><?= clean($s['class_name']) ?><?= $s['room'] ? ' · ' . clean($s['room']) : '' ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-star"></i> Dernieres notes saisies</h3></div>
        <div class="card-body" style="padding:8px 0">
          <?php if (empty($recentGrades)): ?>
            <div class="empty-state" style="padding:20px"><p class="text-sm text-muted">Aucune note saisie recemment.</p></div>
          <?php else: foreach ($recentGrades as $g): ?>
            <div style="display:flex;justify-content:space-between;padding:8px 20px;border-bottom:1px solid var(--border-light)">
              <div>
                <div class="text-sm"><?= clean($g['fn'] . ' ' . $g['ln']) ?></div>
                <div class="text-xs text-muted"><?= clean($g['subject_name']) ?> · <?= clean($g['class_name']) ?></div>
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
