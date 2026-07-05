<?php
// ============================================================
//  SmartSchool — Tableau de bord Admin
//  Emplacement : admin/index.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

// Protection : Admin et Super Admin uniquement
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Tableau de bord';
$pageSection = 'dashboard';
$user        = currentUser();

// ── Statistiques ─────────────────────────────────────────
$totalStudents  = dbFetchOne("SELECT COUNT(*) c FROM students WHERE status='actif'")['c'] ?? 0;
$totalTeachers  = dbFetchOne("SELECT COUNT(*) c FROM teachers WHERE status='actif'")['c'] ?? 0;
$totalClasses   = dbFetchOne("SELECT COUNT(*) c FROM classes c JOIN academic_years ay ON c.academic_year_id = ay.id WHERE ay.is_current = 1")['c'] ?? 0;
$totalParents   = dbFetchOne("SELECT COUNT(*) c FROM users WHERE role_id = ?", [ROLE_PARENT])['c'] ?? 0;

$revenueMonth   = dbFetchOne("SELECT COALESCE(SUM(amount_paid),0) c FROM payments WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW()) AND status='paye'")['c'] ?? 0;
$revenueTotal   = dbFetchOne("SELECT COALESCE(SUM(amount_paid),0) c FROM payments WHERE status='paye'")['c'] ?? 0;
$pendingPayments= dbFetchOne("SELECT COUNT(*) c FROM payments WHERE status='partiel'")['c'] ?? 0;

$presentToday   = dbFetchOne("SELECT COUNT(*) c FROM attendance WHERE date=CURDATE() AND status='present'")['c'] ?? 0;
$absentToday    = dbFetchOne("SELECT COUNT(*) c FROM attendance WHERE date=CURDATE() AND status='absent'")['c'] ?? 0;
$totalToday     = $presentToday + $absentToday;
$attendanceRate = $totalToday > 0 ? round(($presentToday / $totalToday) * 100) : 0;

// ── Graphique inscriptions 6 mois ────────────────────────
$enrollData = dbFetchAll("
    SELECT DATE_FORMAT(enrollment_date,'%b %Y') lbl,
           DATE_FORMAT(enrollment_date,'%Y-%m') ym,
           COUNT(*) total
    FROM students
    WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym, lbl ORDER BY ym
");
$enrollLabels = json_encode(array_column($enrollData, 'lbl') ?: ['Jan','Fev','Mar','Avr','Mai','Jun']);
$enrollValues = json_encode(array_column($enrollData, 'total') ?: [0,0,0,0,0,0]);

// ── Graphique paiements 6 mois ───────────────────────────
$payData = dbFetchAll("
    SELECT DATE_FORMAT(payment_date,'%b %Y') lbl,
           DATE_FORMAT(payment_date,'%Y-%m') ym,
           COALESCE(SUM(amount_paid),0) total
    FROM payments
    WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym, lbl ORDER BY ym
");
$payLabels = json_encode(array_column($payData, 'lbl') ?: ['Jan','Fev','Mar','Avr','Mai','Jun']);
$payValues = json_encode(array_column($payData, 'total') ?: [0,0,0,0,0,0]);

// ── Graphique presences 7 jours ──────────────────────────
$attData = dbFetchOne("
    SELECT
      SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) present,
      SUM(CASE WHEN status='absent'  THEN 1 ELSE 0 END) absent,
      SUM(CASE WHEN status='retard'  THEN 1 ELSE 0 END) retard,
      SUM(CASE WHEN status='excuse'  THEN 1 ELSE 0 END) excuse
    FROM attendance
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
") ?: ['present'=>0,'absent'=>0,'retard'=>0,'excuse'=>0];

// ── Graphique notes par matiere ──────────────────────────
$gradesData = dbFetchAll("
    SELECT s.name subj, s.color,
           ROUND(AVG(g.score / g.max_score * 20), 1) avg
    FROM grades g
    JOIN class_subjects cs ON g.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN terms t ON g.term_id = t.id
    WHERE t.is_current = 1
    GROUP BY s.id, s.name, s.color
    ORDER BY avg DESC LIMIT 8
");
$gradeLabels = json_encode(array_column($gradesData, 'subj') ?: ['Math','FR','ANG','SCI']);
$gradeValues = json_encode(array_column($gradesData, 'avg')  ?: [14,13,12,15]);
$gradeColors = json_encode(array_column($gradesData, 'color') ?: ['#6366f1','#8b5cf6','#3b82f6','#10b981']);

// ── Eleves recents ───────────────────────────────────────
$recentStudents = dbFetchAll("
    SELECT u.first_name, u.last_name, u.email,
           s.student_number, s.status, s.enrollment_date,
           c.name class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY s.created_at DESC LIMIT 6
");

// ── Paiements recents ────────────────────────────────────
$recentPayments = dbFetchAll("
    SELECT p.amount_paid, p.payment_date, p.status, p.receipt_number,
           u.first_name, u.last_name,
           fc.name fee_name
    FROM payments p
    JOIN students s   ON p.student_id  = s.id
    JOIN users u      ON s.user_id     = u.id
    JOIN fees f       ON p.fee_id      = f.id
    JOIN fee_categories fc ON f.category_id = fc.id
    ORDER BY p.payment_date DESC LIMIT 5
");

// ── Activite recente ─────────────────────────────────────
$recentActivity = dbFetchAll("
    SELECT al.action, al.description, al.created_at,
           u.first_name, u.last_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 6
");

// ── Trimestres ───────────────────────────────────────────
$terms = dbFetchAll("
    SELECT name, start_date, end_date, is_current
    FROM terms
    WHERE end_date >= CURDATE()
    ORDER BY start_date ASC LIMIT 3
");

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<!-- ════ BIENVENUE ════ -->
<div style="
  background   : var(--grad-primary);
  border-radius: var(--radius-xl);
  padding      : 32px 36px;
  margin-bottom: 24px;
  display      : flex;
  align-items  : center;
  justify-content: space-between;
  gap          : 20px;
  position     : relative;
  overflow     : hidden;
  box-shadow   : 0 8px 28px rgba(99,102,241,0.28);
">
  <!-- Cercles decoratifs -->
  <div style="position:absolute;top:-30px;right:15%;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.07)"></div>
  <div style="position:absolute;bottom:-40px;right:5%;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.05)"></div>

  <div style="position:relative;z-index:1">
    <h2 style="font-size:22px;font-weight:800;color:#fff;margin-bottom:6px">
      Bonjour, <?= clean($user['first_name']) ?> ! 👋
    </h2>
    <p style="font-size:13.5px;color:rgba(255,255,255,0.80)">
      <?= date('l d F Y') ?> &mdash; Voici l\'apercu de votre etablissement
    </p>
    <!-- Mini stats dans la banniere -->
    <div style="display:flex;gap:28px;margin-top:20px">
      <div>
        <div style="font-size:22px;font-weight:800;color:#fff"><?= $totalStudents ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,0.70)">Eleves actifs</div>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:#fff"><?= $attendanceRate ?>%</div>
        <div style="font-size:11px;color:rgba(255,255,255,0.70)">Presence aujourd\'hui</div>
      </div>
      <div>
        <div style="font-size:22px;font-weight:800;color:#fff"><?= formatMoney($revenueMonth) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,0.70)">Recettes ce mois</div>
      </div>
    </div>
  </div>

  <div style="position:relative;z-index:1;flex-shrink:0">
    <div style="width:80px;height:80px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem">
      🎓
    </div>
  </div>
</div>

<!-- ════ STATISTIQUES ════ -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;margin-bottom:24px">

  <div class="stat-card c-primary animate-in">
    <div class="stat-icon c-primary"><i class="bx bx-graduation"></i></div>
    <div class="stat-info">
      <div class="stat-value" data-count="<?= $totalStudents ?>"><?= $totalStudents ?></div>
      <div class="stat-label">Eleves actifs</div>
      <span class="stat-change up"><i class="bx bx-trending-up"></i> +12 ce mois</span>
    </div>
  </div>

  <div class="stat-card c-success animate-in" style="animation-delay:0.05s">
    <div class="stat-icon c-success"><i class="bx bx-chalkboard"></i></div>
    <div class="stat-info">
      <div class="stat-value" data-count="<?= $totalTeachers ?>"><?= $totalTeachers ?></div>
      <div class="stat-label">Enseignants</div>
      <span class="stat-change up"><i class="bx bx-check-circle"></i> Tous actifs</span>
    </div>
  </div>

  <div class="stat-card c-cyan animate-in" style="animation-delay:0.10s">
    <div class="stat-icon c-cyan"><i class="bx bx-building"></i></div>
    <div class="stat-info">
      <div class="stat-value" data-count="<?= $totalClasses ?>"><?= $totalClasses ?></div>
      <div class="stat-label">Classes ouvertes</div>
      <span class="stat-change up"><i class="bx bx-calendar"></i> Annee en cours</span>
    </div>
  </div>

  <div class="stat-card c-warning animate-in" style="animation-delay:0.15s">
    <div class="stat-icon c-warning"><i class="bx bx-check-square"></i></div>
    <div class="stat-info">
      <div class="stat-value" data-count="<?= $attendanceRate ?>"><?= $attendanceRate ?></div>
      <div class="stat-label">% Presence aujourd\'hui</div>
      <span class="stat-change <?= $attendanceRate >= 80 ? 'up' : 'down' ?>">
        <i class="bx bx-user-check"></i>
        <?= $presentToday ?> / <?= $totalToday ?>
      </span>
    </div>
  </div>

  <div class="stat-card c-success animate-in" style="animation-delay:0.20s">
    <div class="stat-icon c-success"><i class="bx bx-money"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatMoney($revenueMonth) ?></div>
      <div class="stat-label">Recettes ce mois</div>
      <span class="stat-change up"><i class="bx bx-trending-up"></i> +8% vs precedent</span>
    </div>
  </div>

  <div class="stat-card c-danger animate-in" style="animation-delay:0.25s">
    <div class="stat-icon c-danger"><i class="bx bx-error-circle"></i></div>
    <div class="stat-info">
      <div class="stat-value" data-count="<?= $pendingPayments ?>"><?= $pendingPayments ?></div>
      <div class="stat-label">Paiements partiels</div>
      <span class="stat-change down"><i class="bx bx-time"></i> A regulariser</span>
    </div>
  </div>

</div>

<!-- ════ ACTIONS RAPIDES ════ -->
<div class="card animate-in" style="margin-bottom:24px;animation-delay:0.30s">
  <div class="card-header">
    <h3><i class="bx bx-zap"></i> Actions rapides</h3>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px">

      <?php
      $actions = [
        ['url'=>'/admin/students.php?action=add', 'icon'=>'bx-user-plus',   'label'=>'Ajouter eleve',    'bg'=>'var(--grad-primary)'],
        ['url'=>'/admin/teachers.php?action=add', 'icon'=>'bx-user-check',  'label'=>'Ajouter prof',     'bg'=>'var(--grad-success)'],
        ['url'=>'/admin/attendance.php',          'icon'=>'bx-check-square','label'=>'Faire l\'appel',   'bg'=>'var(--grad-cyan)'],
        ['url'=>'/admin/grades.php',              'icon'=>'bx-star',        'label'=>'Saisir notes',     'bg'=>'linear-gradient(135deg,#f59e0b,#ef4444)'],
        ['url'=>'/admin/payments.php?action=add', 'icon'=>'bx-credit-card', 'label'=>'Paiement',         'bg'=>'var(--grad-success)'],
        ['url'=>'/admin/reports.php',             'icon'=>'bx-file',        'label'=>'Bulletins',        'bg'=>'var(--grad-purple)'],
        ['url'=>'/admin/messages.php?compose=1',  'icon'=>'bx-envelope',    'label'=>'Message',          'bg'=>'linear-gradient(135deg,#ec4899,#8b5cf6)'],
        ['url'=>'/admin/settings.php',            'icon'=>'bx-cog',         'label'=>'Parametres',       'bg'=>'linear-gradient(135deg,#64748b,#334155)'],
      ];
      foreach ($actions as $a): ?>
      <a href="<?= BASE_URL . $a['url'] ?>" style="
        display        : flex;
        flex-direction : column;
        align-items    : center;
        gap            : 10px;
        padding        : 18px 10px;
        background     : var(--bg-body);
        border         : 1.5px solid var(--border);
        border-radius  : var(--radius-lg);
        text-decoration: none;
        transition     : all 0.2s ease;
        text-align     : center;
      "
      onmouseenter="this.style.borderColor='var(--primary)';this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-md)'"
      onmouseleave="this.style.borderColor='var(--border)';this.style.transform='';this.style.boxShadow=''">
        <div style="width:46px;height:46px;border-radius:var(--radius);background:<?= $a['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff">
          <i class="bx <?= $a['icon'] ?>"></i>
        </div>
        <span style="font-size:12px;font-weight:600;color:var(--text-secondary);line-height:1.3">
          <?= $a['label'] ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ════ GRAPHIQUES LIGNE 1 ════ -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Inscriptions -->
  <div class="card animate-in" style="animation-delay:0.35s">
    <div class="card-header">
      <h3><i class="bx bx-trending-up"></i> Inscriptions (6 mois)</h3>
      <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-sm btn-secondary">Voir tout</a>
    </div>
    <div class="card-body">
      <div style="position:relative;height:220px">
        <canvas id="chartEnroll"></canvas>
      </div>
    </div>
  </div>

  <!-- Presences donut -->
  <div class="card animate-in" style="animation-delay:0.40s">
    <div class="card-header">
      <h3><i class="bx bx-pie-chart-alt"></i> Presences (7 jours)</h3>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:24px">
        <div style="position:relative;width:150px;height:150px;flex-shrink:0">
          <canvas id="chartAtt"></canvas>
          <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
            <div style="font-size:22px;font-weight:800;color:var(--text-primary)"><?= $attendanceRate ?>%</div>
            <div style="font-size:11px;color:var(--text-muted)">Presence</div>
          </div>
        </div>
        <div style="flex:1">
          <?php
          $attLegend = [
            ['label'=>'Presents', 'val'=>$attData['present'], 'color'=>'#10b981'],
            ['label'=>'Absents',  'val'=>$attData['absent'],  'color'=>'#ef4444'],
            ['label'=>'Retards',  'val'=>$attData['retard'],  'color'=>'#f59e0b'],
            ['label'=>'Excuses',  'val'=>$attData['excuse'],  'color'=>'#6366f1'],
          ];
          foreach ($attLegend as $leg): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)">
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary)">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= $leg['color'] ?>;display:inline-block"></span>
              <?= $leg['label'] ?>
            </div>
            <span style="font-size:13px;font-weight:700;color:var(--text-primary)"><?= $leg['val'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ════ GRAPHIQUES LIGNE 2 ════ -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Paiements -->
  <div class="card animate-in" style="animation-delay:0.45s">
    <div class="card-header">
      <h3><i class="bx bx-bar-chart-alt-2"></i> Paiements (6 mois)</h3>
      <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-sm btn-secondary">Details</a>
    </div>
    <div class="card-body">
      <div style="position:relative;height:220px">
        <canvas id="chartPay"></canvas>
      </div>
    </div>
    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:13px;color:var(--text-muted)">Total cumule</span>
      <span style="font-size:16px;font-weight:800;color:var(--success)"><?= formatMoney($revenueTotal) ?></span>
    </div>
  </div>

  <!-- Notes par matiere -->
  <div class="card animate-in" style="animation-delay:0.50s">
    <div class="card-header">
      <h3><i class="bx bx-book-open"></i> Moyennes par matiere</h3>
    </div>
    <div class="card-body">
      <div style="position:relative;height:220px">
        <canvas id="chartGrades"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- ════ TABLEAUX ════ -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Eleves recents -->
  <div class="card animate-in" style="animation-delay:0.55s">
    <div class="card-header">
      <h3><i class="bx bx-group"></i> Eleves recents</h3>
      <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-sm btn-primary">
        <i class="bx bx-plus"></i> Ajouter
      </a>
    </div>
    <div style="overflow-x:auto">
      <?php if (empty($recentStudents)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-graduation"></i></div>
          <h3>Aucun eleve</h3>
          <p>Commencez par inscrire des eleves.</p>
          <a href="<?= BASE_URL ?>/admin/students.php?action=add" class="btn btn-primary">
            <i class="bx bx-user-plus"></i> Premier eleve
          </a>
        </div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Eleve</th>
              <th>Matricule</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentStudents as $s): ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32" style="background:var(--primary)">
                    <?= getInitials($s['first_name'], $s['last_name']) ?>
                  </div>
                  <div>
                    <div class="td-name">
                      <?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?>
                    </div>
                    <div class="td-sub"><?= clean($s['class_name'] ?? 'Sans classe') ?></div>
                  </div>
                </div>
              </td>
              <td>
                <code style="font-size:11px;background:var(--bg-body);padding:2px 7px;border-radius:5px;color:var(--primary)">
                  <?= clean($s['student_number']) ?>
                </code>
              </td>
              <td>
                <span class="badge badge-<?= $s['status'] === 'actif' ? 'success' : 'warning' ?>">
                  <?= clean($s['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Paiements recents -->
  <div class="card animate-in" style="animation-delay:0.60s">
    <div class="card-header">
      <h3><i class="bx bx-credit-card"></i> Paiements recents</h3>
      <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-sm btn-secondary">Tout voir</a>
    </div>
    <div style="overflow-x:auto">
      <?php if (empty($recentPayments)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-money"></i></div>
          <h3>Aucun paiement</h3>
          <p>Aucun paiement enregistre.</p>
        </div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Eleve</th>
              <th>Montant</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPayments as $p): ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32"
                       style="background:<?= $p['status']==='paye' ? 'var(--success)' : 'var(--warning)' ?>">
                    <?= getInitials($p['first_name'], $p['last_name']) ?>
                  </div>
                  <div>
                    <div class="td-name">
                      <?= clean($p['first_name']) ?> <?= clean($p['last_name']) ?>
                    </div>
                    <div class="td-sub"><?= clean($p['fee_name']) ?></div>
                  </div>
                </div>
              </td>
              <td style="font-weight:700;color:var(--success)">
                +<?= formatMoney($p['amount_paid']) ?>
              </td>
              <td>
                <span class="badge badge-<?= $p['status']==='paye' ? 'success' : 'warning' ?>">
                  <?= clean($p['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ════ ACTIVITE + TRIMESTRES ════ -->
<div class="grid-2" style="margin-bottom:24px">

  <!-- Activite recente -->
  <div class="card animate-in" style="animation-delay:0.65s">
    <div class="card-header">
      <h3><i class="bx bx-history"></i> Activite recente</h3>
    </div>
    <?php if (empty($recentActivity)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-time"></i></div>
        <p>Aucune activite enregistree.</p>
      </div>
    <?php else: ?>
      <ul style="list-style:none">
        <?php foreach ($recentActivity as $act): ?>
        <li style="display:flex;align-items:flex-start;gap:12px;padding:12px 22px;border-bottom:1px solid var(--border-light)">
          <div style="width:34px;height:34px;border-radius:var(--radius-sm);background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem">
            <i class="bx bx-pulse"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;color:var(--text-secondary)">
              <?php if ($act['first_name']): ?>
                <strong style="color:var(--text-primary)"><?= clean($act['first_name']) ?></strong> —
              <?php endif; ?>
              <?= clean($act['description'] ?: $act['action']) ?>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;display:flex;align-items:center;gap:4px">
              <i class="bx bx-time-five"></i><?= timeAgo($act['created_at']) ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Trimestres -->
  <div class="card animate-in" style="animation-delay:0.70s">
    <div class="card-header">
      <h3><i class="bx bx-calendar"></i> Calendrier scolaire</h3>
      <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-sm btn-secondary">Gerer</a>
    </div>
    <div class="card-body">
      <?php if (empty($terms)): ?>
        <div class="empty-state" style="padding:24px">
          <p>Aucun trimestre configure.</p>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
          <?php foreach ($terms as $t): ?>
          <div style="
            padding      : 16px;
            border-radius: var(--radius);
            border       : 1.5px solid <?= $t['is_current'] ? 'var(--primary)' : 'var(--border)' ?>;
            background   : <?= $t['is_current'] ? 'var(--primary-bg)' : 'var(--bg-body)' ?>;
          ">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
              <span style="font-size:14px;font-weight:700;color:var(--text-primary)">
                <?= clean($t['name']) ?>
              </span>
              <?php if ($t['is_current']): ?>
                <span class="badge badge-primary"><i class="bx bx-radio-circle-marked"></i> En cours</span>
              <?php else: ?>
                <span class="badge badge-gray">A venir</span>
              <?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:6px">
              <i class="bx bx-calendar"></i>
              <?= formatDate($t['start_date']) ?> → <?= formatDate($t['end_date']) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

</div><!-- /.page-wrapper -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->

<?php
$pageScript = "
document.addEventListener('DOMContentLoaded', function() {

  // Graphique inscriptions
  SS_Charts.bar('chartEnroll',
    {$enrollLabels},
    [{ label: 'Inscriptions', data: {$enrollValues}, color: '#6366f1' }],
    { plugins: { legend: { display: false } } }
  );

  // Graphique presences (donut)
  SS_Charts.donut('chartAtt',
    ['Presents','Absents','Retards','Excuses'],
    [{$attData['present']},{$attData['absent']},{$attData['retard']},{$attData['excuse']}],
    ['#10b981','#ef4444','#f59e0b','#6366f1'],
    { plugins: { legend: { display: false } } }
  );

  // Graphique paiements
  SS_Charts.line('chartPay',
    {$payLabels},
    [{ label: 'Paiements', data: {$payValues}, color: '#10b981', fill: true }]
  );

  // Graphique notes
  SS_Charts.bar('chartGrades',
    {$gradeLabels},
    [{ label: 'Moyenne /20', data: {$gradeValues}, color: '#6366f1' }],
    {
      plugins: { legend: { display: false } },
      scales : { y: { max: 20 } }
    }
  );
});
";
require_once INCLUDES_PATH . '/footer.php';
?>
