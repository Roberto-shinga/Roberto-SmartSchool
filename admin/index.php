<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Tableau de bord';
$pageSection = 'dashboard';
$user        = currentUser();

// ── Année / trimestre en cours ───────────────────────────────
$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$currentTerm = dbFetchOne("SELECT * FROM terms WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

// ── Statistiques principales ─────────────────────────────────
$nbStudents = dbCount('students', "status='actif'");
$nbTeachers = dbCount('teachers', "status='actif'");
$nbClasses  = dbCount('classes',  "academic_year_id=?", [$yearId]);
$nbParents  = dbCount('users',    "role_id=? AND is_active=1", [ROLE_PARENT]);

$revenueMonth = dbFetchOne(
    "SELECT COALESCE(SUM(amount_paid),0) t FROM payments
     WHERE status='paye' AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())"
)['t'] ?? 0;

$revenuePrevMonth = dbFetchOne(
    "SELECT COALESCE(SUM(amount_paid),0) t FROM payments
     WHERE status='paye' AND MONTH(payment_date)=MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(payment_date)=YEAR(CURDATE() - INTERVAL 1 MONTH)"
)['t'] ?? 0;
$revenueTrend = $revenuePrevMonth > 0
    ? round((($revenueMonth - $revenuePrevMonth) / $revenuePrevMonth) * 100)
    : ($revenueMonth > 0 ? 100 : 0);

// ── Répartition des élèves par niveau ────────────────────────
$byLevel = dbFetchAll(
    "SELECT l.id, l.name, COUNT(s.id) c
     FROM levels l
     LEFT JOIN students s ON s.level_id = l.id AND s.status='actif'
     GROUP BY l.id, l.name, l.order_index
     ORDER BY l.order_index"
);
$maxLevel = 1;
foreach ($byLevel as $lv) $maxLevel = max($maxLevel, (int)$lv['c']);

// ── Taux de présence aujourd'hui ─────────────────────────────
$attToday     = dbCount('attendance', "date=CURDATE()");
$presentToday = dbCount('attendance', "date=CURDATE() AND status='present'");
$attRate      = $attToday > 0 ? round(($presentToday / $attToday) * 100) : null;

// ── Activité récente ──────────────────────────────────────────
$activities = dbFetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.role_id
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC LIMIT 7"
);

$actionIcons = [
    'login'            => ['bx-log-in',        'success'],
    'login_failed'     => ['bx-error',         'danger'],
    'logout'           => ['bx-log-out',       'gray'],
    'register'         => ['bx-user-plus',     'info'],
    'change_password'  => ['bx-lock-alt',      'warning'],
];

// ── Paiements récents ─────────────────────────────────────────
$recentPayments = dbFetchAll(
    "SELECT p.*, s.student_number, s.first_name AS s_fn, s.last_name AS s_ln, u.first_name AS u_fn, u.last_name AS u_ln
     FROM payments p
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     ORDER BY p.payment_date DESC LIMIT 6"
);
$payStatusBadge = [
    'paye'       => 'success', 'partiel'   => 'warning',
    'en_attente' => 'gray',    'rejete'    => 'danger', 'annule' => 'danger',
];

// ── Preuves de paiement en attente de validation ──────────────
$pendingProofs = dbCount('payment_proofs', "status='en_attente'");

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <!-- Bannière hero -->
    <div class="hero-banner animate-in">
      <div class="hero-content">
        <div class="hero-title">Bonjour, <?= clean($user['first_name']) ?> 👋</div>
        <div class="hero-sub">
          Année scolaire <?= clean($currentYear['name'] ?? '—') ?>
          <?= $currentTerm ? ' · ' . clean($currentTerm['name']) . ' en cours' : '' ?>
        </div>
        <div class="hero-stats">
          <div><div class="hero-stat-val"><?= $nbStudents ?></div><div class="hero-stat-lbl">Élèves actifs</div></div>
          <div><div class="hero-stat-val"><?= $nbTeachers ?></div><div class="hero-stat-lbl">Enseignants</div></div>
          <div><div class="hero-stat-val"><?= $nbClasses ?></div><div class="hero-stat-lbl">Classes ouvertes</div></div>
        </div>
      </div>
      <div class="hero-icon"><i class="bx bx-buildings"></i></div>
    </div>

    <div class="page-header">
      <div>
        <h1>Vue d'ensemble</h1>
        <p>Résumé de l'activité de l'établissement</p>
      </div>
      <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/admin/students.php?action=add" class="btn btn-secondary">
          <i class="bx bx-user-plus"></i> Nouvel élève
        </a>
        <a href="<?= BASE_URL ?>/admin/payments.php?action=add" class="btn btn-primary">
          <i class="bx bx-credit-card"></i> Nouveau paiement
        </a>
      </div>
    </div>

    <!-- Cartes statistiques -->
    <div class="grid-4 mb-6">
      <div class="stat-card c-primary animate-in">
        <div class="stat-icon c-primary"><i class="bx bx-graduation"></i></div>
        <div>
          <div class="stat-value"><?= $nbStudents ?></div>
          <div class="stat-label">Élèves inscrits</div>
        </div>
      </div>
      <div class="stat-card c-cyan animate-in d1">
        <div class="stat-icon c-cyan"><i class="bx bx-chalkboard"></i></div>
        <div>
          <div class="stat-value"><?= $nbTeachers ?></div>
          <div class="stat-label">Enseignants actifs</div>
        </div>
      </div>
      <div class="stat-card c-purple animate-in d2">
        <div class="stat-icon c-purple"><i class="bx bx-group"></i></div>
        <div>
          <div class="stat-value"><?= $nbParents ?></div>
          <div class="stat-label">Parents inscrits</div>
        </div>
      </div>
      <div class="stat-card c-success animate-in d3">
        <div class="stat-icon c-success"><i class="bx bx-money"></i></div>
        <div>
          <div class="stat-value" style="font-size:20px"><?= formatMoney($revenueMonth) ?></div>
          <div class="stat-label">Revenus du mois</div>
          <?php if ($revenuePrevMonth > 0 || $revenueMonth > 0): ?>
          <div class="stat-change <?= $revenueTrend >= 0 ? 'up' : 'down' ?>">
            <i class="bx <?= $revenueTrend >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' ?>"></i>
            <?= abs($revenueTrend) ?>% vs mois dernier
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid-2 mb-6">

      <!-- Répartition par niveau + présence -->
      <div class="card animate-in">
        <div class="card-header">
          <h3><i class="bx bx-bar-chart-alt-2"></i> Répartition des élèves par niveau</h3>
        </div>
        <div class="card-body">
          <?php if (empty($byLevel)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-data"></i></div>
              <p>Aucune donnée disponible pour le moment.</p>
            </div>
          <?php else: ?>
            <?php foreach ($byLevel as $lv): ?>
              <div style="margin-bottom:16px">
                <div class="flex justify-between mb-4" style="margin-bottom:6px">
                  <span class="text-sm font-semibold" style="color:var(--text-secondary)"><?= clean($lv['name']) ?></span>
                  <span class="text-sm font-bold text-primary"><?= (int)$lv['c'] ?> élève<?= $lv['c'] > 1 ? 's' : '' ?></span>
                </div>
                <div class="progress">
                  <div class="progress-bar" data-value="<?= $maxLevel > 0 ? round(($lv['c'] / $maxLevel) * 100) : 0 ?>" style="width:0"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-light)">
            <div class="flex justify-between items-center" style="margin-bottom:8px">
              <span class="text-sm font-semibold" style="color:var(--text-secondary)">
                <i class="bx bx-check-square" style="color:var(--success);margin-right:4px"></i> Présence aujourd'hui
              </span>
              <span class="text-sm font-bold"><?= $attRate !== null ? $attRate . '%' : '—' ?></span>
            </div>
            <?php if ($attRate !== null): ?>
              <div class="progress"><div class="progress-bar success" data-value="<?= $attRate ?>" style="width:0"></div></div>
              <div class="form-hint" style="margin-top:6px"><?= $presentToday ?> présent(s) sur <?= $attToday ?> enregistré(s)</div>
            <?php else: ?>
              <div class="form-hint">Aucun appel enregistré aujourd'hui.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Activité récente -->
      <div class="card animate-in d1">
        <div class="card-header">
          <h3><i class="bx bx-history"></i> Activité récente</h3>
          <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-ghost btn-sm">Tout voir</a>
        </div>
        <div class="card-body" style="padding:8px 0">
          <?php if (empty($activities)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-time-five"></i></div>
              <p>Aucune activité enregistrée récemment.</p>
            </div>
          <?php else: ?>
            <?php foreach ($activities as $a):
              [$icon, $color] = $actionIcons[$a['action']] ?? ['bx-info-circle', 'primary'];
              $actorName = $a['first_name'] ? clean($a['first_name'] . ' ' . $a['last_name']) : 'Système';
            ?>
            <div style="display:flex;gap:12px;padding:10px 20px;align-items:flex-start">
              <div class="stat-icon c-<?= $color === 'gray' ? 'primary' : $color ?>" style="width:34px;height:34px;font-size:1rem;<?= $color === 'gray' ? 'opacity:.55' : '' ?>">
                <i class="bx <?= $icon ?>"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div class="text-sm font-semibold" style="color:var(--text-primary)"><?= $actorName ?></div>
                <div class="text-xs text-muted truncate"><?= clean($a['description'] ?: $a['action']) ?></div>
              </div>
              <div class="text-xs text-muted" style="white-space:nowrap"><?= timeAgo($a['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Paiements récents -->
    <div class="card animate-in d2">
      <div class="card-header">
        <h3><i class="bx bx-credit-card"></i> Paiements récents</h3>
        <div class="flex gap-2" style="align-items:center">
          <?php if ($pendingProofs > 0): ?>
            <a href="<?= BASE_URL ?>/admin/payments.php?filter=proofs" class="badge badge-warning" style="text-decoration:none">
              <i class="bx bx-file-blank"></i> <?= $pendingProofs ?> preuve<?= $pendingProofs > 1 ? 's' : '' ?> à valider
            </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-ghost btn-sm">Tout voir</a>
        </div>
      </div>
      <?php if (empty($recentPayments)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-receipt"></i></div>
          <h3>Aucun paiement enregistré</h3>
          <p>Les paiements récents apparaîtront ici dès qu'ils seront enregistrés.</p>
          <a href="<?= BASE_URL ?>/admin/payments.php?action=add" class="btn btn-primary btn-sm">
            <i class="bx bx-plus"></i> Enregistrer un paiement
          </a>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead>
            <tr>
              <th>Élève</th><th>Reçu</th><th>Montant</th><th>Méthode</th><th>Statut</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPayments as $p):
              $fn = $p['u_fn'] ?: $p['s_fn']; $ln = $p['u_ln'] ?: $p['s_ln'];
            ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--primary)"><?= getInitials($fn ?? '?', $ln ?? '') ?></div>
                <div>
                  <div class="td-name"><?= clean(trim(($fn ?? '') . ' ' . ($ln ?? ''))) ?></div>
                  <div class="td-sub"><?= clean($p['student_number']) ?></div>
                </div>
              </td>
              <td><?= clean($p['receipt_number']) ?></td>
              <td class="font-semibold"><?= formatMoney($p['amount_paid']) ?></td>
              <td class="text-sm text-muted"><?= clean(ucfirst(str_replace('_', ' ', $p['payment_method']))) ?></td>
              <td><span class="badge badge-<?= $payStatusBadge[$p['status']] ?? 'gray' ?>"><?= clean(ucfirst(str_replace('_',' ',$p['status']))) ?></span></td>
              <td class="text-sm text-muted"><?= formatDateTime($p['payment_date']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<<<<<<< HEAD
<?php require INCLUDES_PATH . '/footer.php'; ?>
=======
<?php require INCLUDES_PATH . '/footer.php'; ?>
>>>>>>> 774e2838f42a360319dead9662c112cbf4c08126
