<?php
// ============================================================
//  SmartSchool — Journal des activités (Logs)
//  Emplacement : admin/journal.php ou auth/journal.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

// Sécurité : Vérifier si l'utilisateur est connecté et s'il a le droit d'accès
// (Adaptez cette condition selon vos fonctions, par exemple si seuls les rôles 1 et 2 y ont accès)
if (!isLoggedIn() || !in_array(currentRole(), ['superadmin', 'admin', 1, 2])) {
    redirectWith(BASE_URL . '/auth/login.php', 'error', 'Accès refusé. Vous devez être administrateur.');
}

// Gestion de la pagination simplifiée
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$perPage = 25; // Nombre de lignes par page
$offset = ($page - 1) * $perPage;

// Récupération des logs depuis la base de données
// Note : Ajustez les noms des colonnes (id, action, description, user_id, created_at) selon votre table
$logs = dbFetchAll(
    "SELECT l.*, u.username, u.first_name, u.last_name, r.name AS role_name
     FROM activity_logs l
     LEFT JOIN users u ON l.user_id = u.id
     LEFT JOIN roles r ON u.role_id = r.id
     ORDER BY l.created_at DESC
     LIMIT ? OFFSET ?",
    [$perPage, $offset]
);

// Compte total pour la pagination
$totalLogs = dbFetchOne("SELECT COUNT(*) AS total FROM activity_logs")['total'] ?? 0;
$totalPages = ceil($totalLogs / $perPage);

$schoolName = getSetting('school_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Journal des activités — <?= clean($schoolName) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2 family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">

  <style>
    body {
      background: var(--bg-body);
      font-family: 'Plus Jakarta Sans', sans-serif;
      padding: 40px var(--container-padding, 24px);
      color: var(--text-primary);
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
    }

    .page-title {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .page-title i {
      color: var(--primary);
      background: var(--primary-bg);
      padding: 10px;
      border-radius: 12px;
    }

    /* Table & Card Design */
    .log-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg, 16px);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .table-responsive {
      overflow-x: auto;
    }

    .log-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 14px;
    }

    .log-table th {
      background: var(--bg-body);
      padding: 16px;
      font-weight: 700;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border);
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.05em;
    }

    .log-table td {
      padding: 16px;
      border-bottom: 1px solid var(--border);
      color: var(--text-primary);
      vertical-align: middle;
    }

    .log-table tr:last-child td {
      border-bottom: none;
    }

    /* Badges pour les types d'actions */
    .badge-action {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .action-login { background: rgba(5, 150, 105, 0.15); color: #059669; }
    .action-login_failed { background: rgba(220, 38, 38, 0.15); color: #dc2626; }
    .action-logout { background: rgba(217, 119, 6, 0.15); color: #d97706; }
    .action-default { background: var(--bg-body); color: var(--text-muted); }

    .user-info {
      display: flex;
      flex-direction: column;
    }
    .user-name { font-weight: 600; }
    .user-role { font-size: 11px; color: var(--text-muted); }

    .timestamp {
      font-variant-numeric: tabular-nums;
      color: var(--text-muted);
    }

    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      padding: 20px;
      background: var(--bg-card);
      border-top: 1px solid var(--border);
    }

    .page-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 36px;
      height: 36px;
      padding: 0 8px;
      border: 1px solid var(--border);
      border-radius: var(--radius, 8px);
      background: var(--bg-card);
      color: var(--text-primary);
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      transition: all var(--transition, 0.2s);
    }

    .page-link:hover, .page-link.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .page-link.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius, 8px);
      color: var(--text-primary);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all var(--transition, 0.2s);
    }
    .btn-back:hover {
      border-color: var(--primary);
      color: var(--primary);
    }
  </style>
</head>
<body>

<div class="container">
  
  <div class="page-header">
    <h1 class="page-title">
      <i class="bx bx-history"></i>
      Journal des activités
    </h1>
    <a href="<?= BASE_URL . '/admin/index.php' ?>" class="btn-back">
      <i class="bx bx-arrow-back"></i> Retour au tableau de bord
    </a>
  </div>

  <div class="log-card">
    <div class="table-responsive">
      <table class="log-table">
        <thead>
          <tr>
            <th>Date & Heure</th>
            <th>Action</th>
            <th>Utilisateur</th>
            <th>Détails / Description</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 32px;">
                Aucune activité enregistrée dans le journal pour le moment.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): 
              // Détermination de la classe CSS du badge selon le type d'action
              $actionClass = 'action-default';
              if ($log['action'] === 'login') $actionClass = 'action-login';
              if ($log['action'] === 'login_failed') $actionClass = 'action-login_failed';
              if ($log['action'] === 'logout') $actionClass = 'action-logout';
            ?>
              <tr>
                <td class="timestamp">
                  <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                </td>
                
                <td>
                  <span class="badge-action <?= $actionClass ?>">
                    <?php if($log['action'] === 'login'): ?><i class="bx bx-check-circle"></i><?php endif; ?>
                    <?php if($log['action'] === 'login_failed'): ?><i class="bx bx-x-circle"></i><?php endif; ?>
                    <?php if($log['action'] === 'logout'): ?><i class="bx bx-log-out-circle"></i><?php endif; ?>
                    <?= clean($log['action']) ?>
                  </span>
                </td>
                
                <td>
                  <div class="user-info">
                    <?php if ($log['user_id']): ?>
                      <span class="user-name"><?= clean(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? $log['username'])) ?></span>
                      <span class="user-role"><?= clean($log['role_name'] ?? 'Utilisateur') ?></span>
                    <?php else: ?>
                      <span class="user-name" style="color: var(--text-muted); font-style: italic;">Système / Visiteur</span>
                    <?php endif; ?>
                  </div>
                </td>
                
                <td style="word-break: break-word;">
                  <?= clean($log['description']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <a href="?p=<?= $page - 1 ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">
          <i class="bx bx-chevron-left"></i>
        </a>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?p=<?= $i ?>" class="page-link <?= $page === $i ? 'active' : '' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <a href="?p=<?= $i ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <i class="bx bx-chevron-right"></i>
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>