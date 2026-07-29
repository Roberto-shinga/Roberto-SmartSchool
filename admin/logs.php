<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = "Journal d'activité";
$pageSection = 'logs';
$user        = currentUser();

// Récupération des filtres
$q            = trim($_GET['q'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? '');
$roleFilt     = (int)($_GET['role'] ?? 0);
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

// Construction de la requête SQL
$where  = ['1=1'];
$params = [];

if ($q !== '') {
    $where[]  = "(al.action LIKE ? OR al.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s        = "%$q%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}

if ($filterAction !== '') {
    $where[]  = "al.action = ?";
    $params[] = $filterAction;
}

if ($roleFilt > 0) {
    $where[]  = "u.role_id = ?";
    $params[] = $roleFilt;
}

if ($dateFrom !== '') {
    $where[]  = "al.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[]  = "al.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Pagination et récupération des logs
$total = dbFetchOne("SELECT COUNT(*) c FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id $whereSql", $params)['c'] ?? 0;
$pg    = paginate($total, $page, 30);

$logs = dbFetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.role_id
     FROM activity_logs al 
     LEFT JOIN users u ON al.user_id = u.id
     $whereSql 
     ORDER BY al.created_at DESC 
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$actionsList = dbFetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");

// Metadonnées pour le rendu graphique des actions
$actionMeta = [
    'login'             => ['bx-log-in',       'success', 'Connexion'],
    'login_failed'      => ['bx-error',        'danger',  'Échec connexion'],
    'logout'            => ['bx-log-out',      'gray',    'Déconnexion'],
    'register'          => ['bx-user-plus',    'info',    'Inscription'],
    'change_password'   => ['bx-lock-alt',     'warning', 'Changement mot de passe'],
    'add_payment'       => ['bx-credit-card',  'success', 'Paiement ajouté'],
    'delete_payment'    => ['bx-trash',        'danger',  'Paiement supprimé'],
    'add_fee'           => ['bx-money',        'primary', 'Frais ajouté'],
    'update_settings'   => ['bx-cog',          'cyan',    'Paramètres modifiés'],
    'validate_proof'    => ['bx-check-circle', 'success', 'Preuve validée'],
    'reject_proof'      => ['bx-x-circle',     'danger',  'Preuve rejetée'],
    'account_activated' => ['bx-user-check',   'success', 'Compte activé'],
];

function getActionMeta(string $action, array $map): array {
    if (isset($map[$action])) return $map[$action];
    foreach ($map as $k => $meta) {
        if (str_starts_with($action, $k)) return $meta;
    }
    return ['bx-info-circle', 'primary', $action];
}

function qsUrlLog(int $p, string $q, string $act, int $r, string $from, string $to): string {
    return '?' . http_build_query(array_filter([
        'page'          => $p,
        'q'             => $q ?: null,
        'action_filter' => $act ?: null,
        'role'          => $r ?: null,
        'date_from'     => $from ?: null,
        'date_to'       => $to ?: null
    ]));
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Journal d'activité</h1>
        <p><?= number_format($total) ?> événement(s) enregistré(s)</p>
      </div>
    </div>

    <!-- Barre de filtres complète -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-4" style="align-items:end; gap:12px">
          
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Utilisateur, action..." value="<?= clean($q) ?>">
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Type d'action</label>
            <select name="action_filter" class="form-control">
              <option value="">Toutes les actions</option>
              <?php foreach ($actionsList as $a): ?>
                <option value="<?= clean($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
                  <?= $actionMeta[$a['action']][2] ?? clean($a['action']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Rôle</label>
            <select name="role" class="form-control">
              <option value="0">Tous les rôles</option>
              <?php foreach (ROLE_LABELS as $rid => $lbl): ?>
                <option value="<?= $rid ?>" <?= $roleFilt === $rid ? 'selected' : '' ?>><?= clean($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Du</label>
            <input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>">
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Au</label>
            <input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>">
          </div>

          <div style="grid-column: 1 / -1; display:flex; gap:10px; margin-top:8px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bx bx-filter-alt"></i> Filtrer</button>
            <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-ghost btn-sm">Réinitialiser</a>
          </div>

        </form>
      </div>
    </div>

    <!-- Tableau d'affichage des logs -->
    <div class="card">
      <?php if (empty($logs)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-history"></i></div>
          <h3>Aucun événement trouvé</h3>
        </div>
      <?php else: ?>
        <div class="table-wrap" style="border:none; box-shadow:none; border-radius:0">
          <table class="table">
            <thead>
              <tr>
                <th>Action</th>
                <th>Utilisateur</th>
                <th>Description</th>
                <th>IP</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): 
                [$icon, $color, $label] = getActionMeta($log['action'], $actionMeta);
              ?>
                <tr>
                  <td>
                    <div style="display:flex; align-items:center; gap:8px">
                      <div class="stat-icon c-<?= $color ?>" style="width:30px; height:30px; font-size:.9rem">
                        <i class="bx <?= $icon ?>"></i>
                      </div>
                      <span style="font-size:12.5px; font-weight:600"><?= clean($label) ?></span>
                    </div>
                  </td>
                  <td>
                    <?php if ($log['first_name']): ?>
                      <div class="td-user">
                        <div class="avatar avatar-32" style="background:<?= ROLE_COLORS[$log['role_id']] ?? 'var(--primary)' ?>">
                          <?= getInitials($log['first_name'], $log['last_name']) ?>
                        </div>
                        <div>
                          <div class="td-name"><?= clean($log['first_name'] . ' ' . $log['last_name']) ?></div>
                          <div class="text-xs text-muted"><?= clean(ROLE_LABELS[$log['role_id']] ?? '') ?></div>
                        </div>
                      </div>
                    <?php else: ?>
                      <span style="color:var(--text-muted); font-size:13px">Système</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:13px; color:var(--text-muted); max-width:300px" class="truncate">
                    <?= clean($log['description'] ?: '—') ?>
                  </td>
                  <td style="font-size:12px; color:var(--text-light)"><?= clean($log['ip_address'] ?? '—') ?></td>
                  <td style="font-size:12px; color:var(--text-muted); white-space:nowrap"><?= formatDateTime($log['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($pg['total_pages'] > 1): ?>
          <div class="pagination">
            <?php if ($pg['has_prev']): ?>
              <a href="<?= qsUrlLog($pg['prev_page'], $q, $filterAction, $roleFilt, $dateFrom, $dateTo) ?>"><i class="bx bx-chevron-left"></i></a>
            <?php endif; ?>

            <?php for ($p = max(1, $pg['current_page'] - 2); $p <= min($pg['total_pages'], $pg['current_page'] + 2); $p++): ?>
              <?php if ($p === $pg['current_page']): ?>
                <span class="active"><?= $p ?></span>
              <?php else: ?>
                <a href="<?= qsUrlLog($p, $q, $filterAction, $roleFilt, $dateFrom, $dateTo) ?>"><?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pg['has_next']): ?>
              <a href="<?= qsUrlLog($pg['next_page'], $q, $filterAction, $roleFilt, $dateFrom, $dateTo) ?>"><i class="bx bx-chevron-right"></i></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>