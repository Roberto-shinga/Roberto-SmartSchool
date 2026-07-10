<?php
// ============================================================
//  SmartSchool — Journal d'activite
//  Emplacement : admin/logs.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Journal d\'activite';
$pageSection = 'logs';
$user        = currentUser();

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['action'] === 'clear') {
        $days = (int)($_POST['days'] ?? 30);
        dbExecute("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
        logActivity('clear_logs', "Journal nettoye (> $days jours)");
        redirectWith(BASE_URL . '/admin/logs.php', 'success', "Entrees de plus de $days jours supprimees.");
    }
}

// ── Filtres ──────────────────────────────────────────────
$search     = trim($_GET['search']  ?? '');
$filterUser = (int)($_GET['user']   ?? 0);
$filterDate = trim($_GET['date']    ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(al.action LIKE ? OR al.description LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s]);
}
if ($filterUser) {
    $where[] = "al.user_id = $filterUser";
}
if ($filterDate) {
    $where[] = "DATE(al.created_at) = '" . addslashes($filterDate) . "'";
}

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total    = dbFetchOne("SELECT COUNT(*) c FROM activity_logs al $whereStr", $params)['c'] ?? 0;
$pag      = paginate($total, $page, 25);

$logs = dbFetchAll(
    "SELECT al.id, al.action, al.description, al.ip_address, al.created_at,
            u.first_name, u.last_name, r.label AS role_label
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     LEFT JOIN roles r ON u.role_id = r.id
     $whereStr
     ORDER BY al.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// Liste des utilisateurs pour le filtre
$users = dbFetchAll(
    "SELECT DISTINCT u.id, u.first_name, u.last_name
     FROM activity_logs al JOIN users u ON al.user_id = u.id
     ORDER BY u.last_name LIMIT 50"
);

// Stats
$todayCount  = dbFetchOne("SELECT COUNT(*) c FROM activity_logs WHERE DATE(created_at) = CURDATE()")['c'] ?? 0;
$weekCount   = dbFetchOne("SELECT COUNT(*) c FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0;
$totalCount  = dbFetchOne("SELECT COUNT(*) c FROM activity_logs")['c'] ?? 0;

// Actions les plus frequentes
$topActions = dbFetchAll(
    "SELECT action, COUNT(*) nb FROM activity_logs
     GROUP BY action ORDER BY nb DESC LIMIT 8"
);

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
      <i class="bx bx-list-check" style="color:var(--primary)"></i>
      Journal d'activite
    </h1>
    <p><?= $total ?> entree<?= $total > 1 ? 's' : '' ?> enregistree<?= $total > 1 ? 's' : '' ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-outline-danger" data-modal="modalClear">
      <i class="bx bx-trash"></i> Nettoyer
    </button>
  </div>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:24px">
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-calendar-check"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $todayCount ?></div>
      <div class="stat-label">Actions aujourd'hui</div>
    </div>
  </div>
  <div class="stat-card c-cyan">
    <div class="stat-icon c-cyan"><i class="bx bx-calendar"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $weekCount ?></div>
      <div class="stat-label">Cette semaine</div>
    </div>
  </div>
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-data"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($totalCount) ?></div>
      <div class="stat-label">Total entrees</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">

  <!-- LISTE DES LOGS -->
  <div>
    <!-- Filtres -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-body" style="padding:14px 18px">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <div class="search-wrap" style="flex:1;min-width:160px">
            <i class="bx bx-search"></i>
            <input type="text" name="search" class="search-input"
                   placeholder="Rechercher une action..."
                   value="<?= clean($search) ?>">
          </div>
          <select name="user" class="form-control" style="width:160px">
            <option value="">Tous les utilisateurs</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= $filterUser==$u['id']?'selected':'' ?>>
                <?= clean($u['first_name']) ?> <?= clean($u['last_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="date" name="date" class="form-control" style="width:150px"
                 value="<?= clean($filterDate) ?>">
          <button type="submit" class="btn btn-primary">
            <i class="bx bx-filter-alt"></i> Filtrer
          </button>
          <?php if ($search || $filterUser || $filterDate): ?>
            <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-secondary">
              <i class="bx bx-x"></i>
            </a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if (empty($logs)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-list-check"></i></div>
          <h3>Aucune activite</h3>
          <p>Aucune entree dans le journal pour ces filtres.</p>
        </div>
      <?php else: ?>
        <?php
        $actionColors = [
          'login'             => ['color'=>'#10b981','icon'=>'bx-log-in'],
          'logout'            => ['color'=>'#64748b','icon'=>'bx-log-out'],
          'login_failed'      => ['color'=>'#ef4444','icon'=>'bx-x-circle'],
          'add_student'       => ['color'=>'#6366f1','icon'=>'bx-user-plus'],
          'edit_student'      => ['color'=>'#3b82f6','icon'=>'bx-edit'],
          'delete_student'    => ['color'=>'#ef4444','icon'=>'bx-user-minus'],
          'add_teacher'       => ['color'=>'#06b6d4','icon'=>'bx-user-plus'],
          'edit_teacher'      => ['color'=>'#3b82f6','icon'=>'bx-edit'],
          'add_payment'       => ['color'=>'#10b981','icon'=>'bx-credit-card'],
          'cancel_payment'    => ['color'=>'#f59e0b','icon'=>'bx-x-circle'],
          'add_grade'         => ['color'=>'#8b5cf6','icon'=>'bx-star'],
          'edit_grade'        => ['color'=>'#6366f1','icon'=>'bx-edit'],
          'delete_grade'      => ['color'=>'#ef4444','icon'=>'bx-trash'],
          'save_attendance'   => ['color'=>'#10b981','icon'=>'bx-check-square'],
          'send_notification' => ['color'=>'#f59e0b','icon'=>'bx-bell'],
          'send_message'      => ['color'=>'#06b6d4','icon'=>'bx-send'],
          'update_settings'   => ['color'=>'#64748b','icon'=>'bx-cog'],
          'generate_reports'  => ['color'=>'#8b5cf6','icon'=>'bx-file'],
          'add_class'         => ['color'=>'#6366f1','icon'=>'bx-building'],
          'add_subject'       => ['color'=>'#ec4899','icon'=>'bx-book-open'],
          'change_password'   => ['color'=>'#ef4444','icon'=>'bx-lock-alt'],
          'clear_logs'        => ['color'=>'#ef4444','icon'=>'bx-trash'],
        ];
        foreach ($logs as $log):
          $ac = $actionColors[$log['action']] ?? ['color'=>'#6366f1','icon'=>'bx-pulse'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border-light)">
          <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:<?= $ac['color'] ?>18;color:<?= $ac['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
            <i class="bx <?= $ac['icon'] ?>"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
              <div style="display:flex;align-items:center;gap:8px">
                <?php if ($log['first_name']): ?>
                  <span style="font-size:13px;font-weight:600;color:var(--text-primary)">
                    <?= clean($log['first_name']) ?> <?= clean($log['last_name']) ?>
                  </span>
                  <span style="font-size:11px;color:var(--text-muted)"><?= clean($log['role_label'] ?? '') ?></span>
                <?php else: ?>
                  <span style="font-size:13px;color:var(--text-muted)">Systeme</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                <?php if ($log['ip_address']): ?>
                  <span style="font-size:11px;color:var(--text-light);font-family:monospace">
                    <?= clean($log['ip_address']) ?>
                  </span>
                <?php endif; ?>
                <span style="font-size:11.5px;color:var(--text-muted)"><?= timeAgo($log['created_at']) ?></span>
              </div>
            </div>
            <div style="font-size:13px;color:var(--text-muted);margin-top:3px">
              <?= clean($log['description'] ?: $log['action']) ?>
            </div>
            <div style="margin-top:4px">
              <span style="font-size:10.5px;font-weight:600;padding:2px 8px;border-radius:99px;background:<?= $ac['color'] ?>18;color:<?= $ac['color'] ?>">
                <?= clean($log['action']) ?>
              </span>
              <span style="font-size:11px;color:var(--text-light);margin-left:8px">
                <?= formatDateTime($log['created_at']) ?>
              </span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($pag['total_pages'] > 1): ?>
        <div style="padding:14px 20px;border-top:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
          <span style="font-size:13px;color:var(--text-muted)">
            <?= $pag['offset']+1 ?> – <?= min($pag['offset']+$pag['per_page'],$total) ?> sur <?= $total ?>
          </span>
          <nav class="pagination">
            <?php if ($pag['has_prev']): ?>
              <a href="?page=<?= $pag['prev_page'] ?>&search=<?= urlencode($search) ?>&user=<?= $filterUser ?>&date=<?= $filterDate ?>">
                <i class="bx bx-chevron-left"></i>
              </a>
            <?php endif; ?>
            <?php for ($pg=max(1,$pag['current_page']-2);$pg<=min($pag['total_pages'],$pag['current_page']+2);$pg++): ?>
              <?php if ($pg===$pag['current_page']): ?>
                <span class="active"><?= $pg ?></span>
              <?php else: ?>
                <a href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&user=<?= $filterUser ?>&date=<?= $filterDate ?>"><?= $pg ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($pag['has_next']): ?>
              <a href="?page=<?= $pag['next_page'] ?>&search=<?= urlencode($search) ?>&user=<?= $filterUser ?>&date=<?= $filterDate ?>">
                <i class="bx bx-chevron-right"></i>
              </a>
            <?php endif; ?>
          </nav>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- PANNEAU LATERAL : ACTIONS FREQUENTES -->
  <div class="card">
    <div class="card-header">
      <h3><i class="bx bx-bar-chart-alt"></i> Actions frequentes</h3>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($topActions)): ?>
        <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">Aucune donnee</div>
      <?php else:
        $maxNb = max(array_column($topActions, 'nb'));
        foreach ($topActions as $ta):
          $ac2 = $actionColors[$ta['action']] ?? ['color'=>'#6366f1','icon'=>'bx-pulse'];
          $pct = $maxNb > 0 ? round(($ta['nb']/$maxNb)*100) : 0;
      ?>
        <div style="padding:12px 18px;border-bottom:1px solid var(--border-light)">
          <div style="display:flex;justify-content:space-between;margin-bottom:5px">
            <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:6px">
              <i class="bx <?= $ac2['icon'] ?>" style="color:<?= $ac2['color'] ?>"></i>
              <?= clean($ta['action']) ?>
            </span>
            <span style="font-size:12px;font-weight:700;color:var(--text-primary)"><?= $ta['nb'] ?></span>
          </div>
          <div class="progress" style="height:5px">
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $ac2['color'] ?>"></div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div>

<!-- MODAL NETTOYER -->
<div class="modal-overlay" id="modalClear">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3><i class="bx bx-trash" style="color:var(--danger)"></i> Nettoyer le journal</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="clear">
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="bx bx-error"></i>
          Cette action supprimera definitivement les anciennes entrees.
        </div>
        <div class="form-group">
          <label class="form-label">Supprimer les entrees de plus de</label>
          <select name="days" class="form-control">
            <option value="7">7 jours</option>
            <option value="30" selected>30 jours</option>
            <option value="60">60 jours</option>
            <option value="90">90 jours</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Confirmer la suppression ?')">
          <i class="bx bx-trash"></i> Nettoyer
        </button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
