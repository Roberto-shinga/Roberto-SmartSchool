<?php
// ============================================================
//  SmartSchool — Dashboard Enseignant
//  Emplacement : teachers/index.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_TEACHER);

$pageTitle   = 'Mon tableau de bord';
$pageSection = 'dashboard';
$user        = currentUser();

// Profil enseignant
$teacher = dbFetchOne(
    "SELECT t.* FROM teachers t WHERE t.user_id = ?",
    [$user['id']]
);
$teacherId = $teacher['id'] ?? 0;

// Annee et trimestre courants
$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;
$currentTerm = dbFetchOne("SELECT id, name FROM terms WHERE is_current = 1");
$termId      = $currentTerm['id'] ?? 1;

// Mes classes et matieres
$myClasses = dbFetchAll(
    "SELECT DISTINCT c.id, c.name, sub.name AS subject_name, sub.color,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status='actif') AS student_count
     FROM class_subjects cs
     JOIN classes c ON cs.class_id = c.id
     JOIN subjects sub ON cs.subject_id = sub.id
     WHERE cs.teacher_id = ? AND cs.academic_year_id = ?
     ORDER BY c.name",
    [$teacherId, $yearId]
);

// Nombre de classes distinctes
$myClassIds = array_unique(array_column($myClasses, 'id'));

// Nombre de notes saisies ce trimestre
$myGrades = dbFetchOne(
    "SELECT COUNT(*) c FROM grades g
     JOIN class_subjects cs ON g.class_subject_id = cs.id
     WHERE cs.teacher_id = ? AND g.term_id = ?",
    [$teacherId, $termId]
)['c'] ?? 0;

// Taux de presence (classes de cet enseignant)
$attRate = 0;
if (!empty($myClassIds)) {
    $ids = implode(',', $myClassIds);
    $att = dbFetchOne(
        "SELECT
            SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) present,
            COUNT(*) total
         FROM attendance WHERE class_id IN ($ids) AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
    );
    $attRate = ($att && $att['total'] > 0) ? round(($att['present']/$att['total'])*100) : 0;
}

// Messages non lus
$unreadMsgs  = countUnreadMessages($user['id']);
$unreadNotifs= countUnreadNotifications($user['id']);

// Emploi du temps du jour
$today       = date('N'); // 1=Lundi ... 7=Dimanche
$todaySlots  = dbFetchAll(
    "SELECT tt.start_time, tt.end_time, tt.room,
            sub.name AS subject_name, sub.color,
            c.name AS class_name
     FROM timetable tt
     JOIN class_subjects cs ON tt.class_subject_id = cs.id
     JOIN subjects sub ON cs.subject_id = sub.id
     JOIN classes c ON cs.class_id = c.id
     WHERE cs.teacher_id = ? AND tt.day_of_week = ?
     ORDER BY tt.start_time",
    [$teacherId, $today]
);

// Prochaines evaluations (notes recentes)
$recentGrades = dbFetchAll(
    "SELECT g.score, g.max_score, g.date, g.grade_type, g.title,
            u.first_name, u.last_name,
            sub.name AS subject_name, sub.color,
            c.name AS class_name
     FROM grades g
     JOIN class_subjects cs ON g.class_subject_id = cs.id
     JOIN subjects sub ON cs.subject_id = sub.id
     JOIN classes c ON cs.class_id = c.id
     JOIN students st ON g.student_id = st.id
     JOIN users u ON st.user_id = u.id
     WHERE cs.teacher_id = ?
     ORDER BY g.created_at DESC LIMIT 8",
    [$teacherId]
);

// Absences recentes dans mes classes
$recentAbsences = dbFetchAll(
    "SELECT a.date, a.status, a.reason,
            u.first_name, u.last_name,
            c.name AS class_name
     FROM attendance a
     JOIN students s ON a.student_id = s.id
     JOIN users u ON s.user_id = u.id
     JOIN classes c ON a.class_id = c.id
     WHERE a.class_id IN (" . (empty($myClassIds) ? '0' : implode(',', $myClassIds)) . ")
     AND a.status = 'absent'
     AND a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     ORDER BY a.date DESC LIMIT 6"
);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<!-- BIENVENUE -->
<div style="background:var(--grad-primary);border-radius:var(--radius-xl);padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:20px;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(99,102,241,0.28)">
  <div style="position:absolute;top:-20px;right:15%;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.07)"></div>
  <div style="position:relative;z-index:1">
    <h2 style="font-size:20px;font-weight:800;color:#fff;margin-bottom:4px">
      Bonjour, <?= clean($user['first_name']) ?> ! 👋
    </h2>
    <p style="font-size:13px;color:rgba(255,255,255,0.80)">
      <?= date('l d F Y') ?> —
      <?php if (!empty($todaySlots)): ?>
        <?= count($todaySlots) ?> cours aujourd'hui
      <?php else: ?>
        Pas de cours aujourd'hui
      <?php endif; ?>
    </p>
    <div style="display:flex;gap:24px;margin-top:16px">
      <div>
        <div style="font-size:20px;font-weight:800;color:#fff"><?= count($myClassIds) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,0.70)">Classes</div>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#fff"><?= count($myClasses) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,0.70)">Matieres</div>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#fff"><?= $attRate ?>%</div>
        <div style="font-size:11px;color:rgba(255,255,255,0.70)">Presence (30j)</div>
      </div>
    </div>
  </div>
  <div style="position:relative;z-index:1;flex-shrink:0">
    <div style="width:70px;height:70px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem">
      🎓
    </div>
  </div>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:24px">
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-building"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= count($myClassIds) ?></div>
      <div class="stat-label">Classes enseignees</div>
    </div>
  </div>
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-star"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $myGrades ?></div>
      <div class="stat-label">Notes ce trimestre</div>
    </div>
  </div>
  <div class="stat-card c-cyan">
    <div class="stat-icon c-cyan"><i class="bx bx-check-square"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $attRate ?>%</div>
      <div class="stat-label">Taux presence</div>
    </div>
  </div>
  <div class="stat-card c-warning">
    <div class="stat-icon c-warning"><i class="bx bx-envelope"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $unreadMsgs ?></div>
      <div class="stat-label">Messages non lus</div>
    </div>
  </div>
</div>

<!-- ACTIONS RAPIDES -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h3><i class="bx bx-zap"></i> Actions rapides</h3></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px">
      <?php
      $actions = [
        ['url'=>'/teachers/attendance.php', 'icon'=>'bx-check-square','label'=>'Faire l\'appel', 'bg'=>'var(--grad-primary)'],
        ['url'=>'/teachers/grades.php',     'icon'=>'bx-star',        'label'=>'Saisir notes',   'bg'=>'var(--grad-success)'],
        ['url'=>'/teachers/timetable.php',  'icon'=>'bx-calendar',    'label'=>'Mon planning',   'bg'=>'var(--grad-cyan)'],
        ['url'=>'/teachers/students.php',   'icon'=>'bx-graduation',  'label'=>'Mes eleves',     'bg'=>'linear-gradient(135deg,#f59e0b,#ef4444)'],
        ['url'=>'/teachers/messages.php',   'icon'=>'bx-envelope',    'label'=>'Messages',       'bg'=>'var(--grad-purple)'],
        ['url'=>'/teachers/reports.php',    'icon'=>'bx-file',        'label'=>'Bulletins',      'bg'=>'linear-gradient(135deg,#64748b,#334155)'],
      ];
      foreach ($actions as $a): ?>
      <a href="<?= BASE_URL . $a['url'] ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px 8px;background:var(--bg-body);border:1.5px solid var(--border);border-radius:var(--radius-lg);text-decoration:none;transition:all 0.2s;text-align:center"
         onmouseenter="this.style.borderColor='var(--primary)';this.style.transform='translateY(-2px)'"
         onmouseleave="this.style.borderColor='var(--border)';this.style.transform=''">
        <div style="width:42px;height:42px;border-radius:var(--radius);background:<?= $a['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff">
          <i class="bx <?= $a['icon'] ?>"></i>
        </div>
        <span style="font-size:11.5px;font-weight:600;color:var(--text-secondary);line-height:1.3"><?= $a['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:24px">

  <!-- COURS DU JOUR -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-time-five"></i> Cours d'aujourd'hui</h3>
      <a href="<?= BASE_URL ?>/teachers/timetable.php" class="btn btn-sm btn-secondary">Planning complet</a>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($todaySlots)): ?>
        <div class="empty-state" style="padding:32px">
          <div class="empty-state-icon"><i class="bx bx-coffee"></i></div>
          <h3>Pas de cours aujourd'hui</h3>
          <p>Profitez de votre journee !</p>
        </div>
      <?php else: ?>
        <?php foreach ($todaySlots as $slot): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border-light)">
          <div style="width:4px;height:48px;border-radius:4px;background:<?= clean($slot['color']) ?>;flex-shrink:0"></div>
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600;color:var(--text-primary)"><?= clean($slot['subject_name']) ?></div>
            <div style="font-size:12.5px;color:var(--text-muted);display:flex;align-items:center;gap:12px;margin-top:3px">
              <span><i class="bx bx-building"></i> <?= clean($slot['class_name']) ?></span>
              <?php if ($slot['room']): ?>
                <span><i class="bx bx-map-pin"></i> <?= clean($slot['room']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:13px;font-weight:700;color:var(--primary)"><?= substr($slot['start_time'],0,5) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= substr($slot['end_time'],0,5) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- MES CLASSES -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-book-open"></i> Mes classes & matieres</h3>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($myClasses)): ?>
        <div class="empty-state" style="padding:32px">
          <div class="empty-state-icon"><i class="bx bx-book-open"></i></div>
          <h3>Aucune classe assignee</h3>
          <p>Contactez l'administration.</p>
        </div>
      <?php else: ?>
        <?php foreach ($myClasses as $c): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border-light)">
          <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:<?= clean($c['color']) ?>20;color:<?= clean($c['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
            <i class="bx bx-book-open"></i>
          </div>
          <div style="flex:1">
            <div style="font-size:13.5px;font-weight:600;color:var(--text-primary)"><?= clean($c['subject_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= clean($c['name']) ?> — <?= $c['student_count'] ?> eleves</div>
          </div>
          <a href="<?= BASE_URL ?>/teachers/attendance.php?class_id=<?= $c['id'] ?>"
             class="btn btn-sm btn-secondary">
            <i class="bx bx-check-square"></i> Appel
          </a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="grid-2">

  <!-- NOTES RECENTES -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-star"></i> Notes recentes</h3>
      <a href="<?= BASE_URL ?>/teachers/grades.php" class="btn btn-sm btn-primary">
        <i class="bx bx-plus"></i> Saisir
      </a>
    </div>
    <div style="overflow-x:auto">
      <?php if (empty($recentGrades)): ?>
        <div class="empty-state" style="padding:32px">
          <div class="empty-state-icon"><i class="bx bx-star"></i></div>
          <h3>Aucune note saisie</h3>
        </div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr><th>Eleve</th><th>Matiere</th><th>Note</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentGrades as $g):
              $sur20 = $g['max_score'] > 0 ? round($g['score']/$g['max_score']*20, 1) : 0;
            ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32" style="background:var(--primary)">
                    <?= getInitials($g['first_name'], $g['last_name']) ?>
                  </div>
                  <span class="td-name"><?= clean($g['first_name']) ?> <?= clean($g['last_name']) ?></span>
                </div>
              </td>
              <td><span style="display:flex;align-items:center;gap:5px;font-size:12.5px"><span style="width:8px;height:8px;border-radius:50%;background:<?= clean($g['color']) ?>"></span><?= clean($g['subject_name']) ?></span></td>
              <td style="font-weight:700;color:<?= $sur20>=10?'var(--success)':'var(--danger)' ?>"><?= $sur20 ?>/20</td>
              <td style="font-size:12px;color:var(--text-muted)"><?= formatDate($g['date']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ABSENCES RECENTES -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-user-x" style="color:var(--danger)"></i> Absences (7 derniers jours)</h3>
      <a href="<?= BASE_URL ?>/teachers/attendance.php" class="btn btn-sm btn-secondary">Voir tout</a>
    </div>
    <div style="overflow-x:auto">
      <?php if (empty($recentAbsences)): ?>
        <div class="empty-state" style="padding:32px">
          <div class="empty-state-icon"><i class="bx bx-check-circle"></i></div>
          <h3>Aucune absence</h3>
          <p>Tous les eleves sont presents !</p>
        </div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr><th>Eleve</th><th>Classe</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentAbsences as $a): ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32" style="background:var(--danger)">
                    <?= getInitials($a['first_name'], $a['last_name']) ?>
                  </div>
                  <div>
                    <div class="td-name"><?= clean($a['first_name']) ?> <?= clean($a['last_name']) ?></div>
                    <?php if ($a['reason']): ?>
                      <div class="td-sub"><?= clean($a['reason']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="color:var(--text-muted)"><?= clean($a['class_name']) ?></td>
              <td style="font-size:12px;color:var(--text-muted)"><?= formatDate($a['date']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>