<?php
// ============================================================
//  SmartSchool — Gestion des Parents (Espace Admin)
//  Emplacement : admin/parents.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

// Sécurité : Vérifier si l'utilisateur est connecté et admin
if (!isLoggedIn() || !in_array(currentRole(), ['superadmin', 'admin', 1, 2])) {
    redirectWith(BASE_URL . '/auth/login.php', 'error', 'Accès refusé. Vous devez être administrateur.');
}

// Traitement de la suppression (si demandée)
if (isset($_GET['delete_id'])) {
    verifyCsrf(); // Optionnel : si passé via un lien sécurisé, sinon via formulaire POST
    $deleteId = (int)$_GET['delete_id'];
    
    // On désactive ou supprime le parent (ici on le supprime, ou met is_active = 0 selon votre logique)
    dbExecute("DELETE FROM users WHERE id = ? AND role_id = (SELECT id FROM roles WHERE name = 'parent' LIMIT 1)", [$deleteId]);
    logActivity('parent_deleted', 'Suppression du compte parent ID : ' . $deleteId);
    redirectWith(BASE_URL . '/admin/parents.php', 'success', 'Le compte parent a été supprimé avec succès.');
}

// Récupération de tous les parents et des élèves associés
// Note : Cette requête utilise GROUP_CONCAT pour lister les enfants liés via une table de liaison (ex: student_parent)
$parents = dbFetchAll(
    "SELECT u.*, 
            GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') AS enfants
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN student_parent sp ON u.id = sp.parent_id
     LEFT JOIN users s ON sp.student_id = s.id
     WHERE r.name = 'parent' OR u.role_id = 5
     GROUP BY u.id
     ORDER BY u.last_name ASC, u.first_name ASC"
);

$schoolName = getSetting('school_name', APP_NAME);
$dashboardUrl = ROLE_REDIRECTS[currentRole()] ?? BASE_URL;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Parents — <?= clean($schoolName) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
      flex-wrap: wrap;
      gap: 16px;
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

    .header-actions {
      display: flex;
      gap: 12px;
    }

    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      border-radius: var(--radius, 8px);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all var(--transition, 0.2s);
      cursor: pointer;
    }

    .btn-secondary {
      background: var(--bg-card);
      border: 1px solid var(--border);
      color: var(--text-primary);
    }
    .btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

    .btn-primary {
      background: var(--grad-primary);
      color: #fff;
      border: none;
      box-shadow: var(--shadow-primary);
    }
    .btn-primary:hover { filter: brightness(1.08); }

    /* Table & Cards */
    .parent-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg, 16px);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .table-responsive { overflow-x: auto; }

    .parent-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 14px;
    }

    .parent-table th {
      background: var(--bg-body);
      padding: 16px;
      font-weight: 700;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border);
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.05em;
    }

    .parent-table td {
      padding: 16px;
      border-bottom: 1px solid var(--border);
      color: var(--text-primary);
      vertical-align: middle;
    }

    .parent-name {
      font-weight: 700;
      color: var(--text-primary);
    }

    .parent-meta {
      font-size: 12px;
      color: var(--text-muted);
    }

    .children-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .child-badge {
      background: var(--primary-bg);
      color: var(--primary);
      padding: 4px 10px;
      border-radius: 99px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
    }
    .status-active { background: rgba(5, 150, 105, 0.15); color: #059669; }
    .status-inactive { background: rgba(220, 38, 38, 0.15); color: #dc2626; }

    .row-actions {
      display: flex;
      gap: 8px;
    }

    .btn-icon {
      width: 32px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      border: 1px solid var(--border);
      background: var(--bg-card);
      color: var(--text-muted);
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-icon:hover { color: var(--primary); border-color: var(--primary); }
    .btn-delete:hover { color: #dc2626; border-color: #dc2626; background: rgba(220, 38, 38, 0.05); }

  </style>
</head>
<body>

<div class="container">
  
  <?php if ($flash): ?>
    <div class="alert alert-<?= clean($flash['type']) ?>" style="margin-bottom: 20px;">
      <i class="bx bx-<?= $flash['type'] === 'success' ? 'check-circle' : 'error' ?>"></i>
      <?= clean($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="page-header">
    <h1 class="page-title">
      <i class="bx bx-group"></i>
      Gestion des Parents
    </h1>
    <div class="header-actions">
      <a href="<?= $dashboardUrl ?>" class="btn-action btn-secondary">
        <i class="bx bx-arrow-back"></i> Tableau de bord
      </a>
      <a href="<?= BASE_URL ?>/admin/parents-add.php" class="btn-action btn-primary">
        <i class="bx bx-user-plus"></i> Ajouter un parent
      </a>
    </div>
  </div>

  <div class="parent-card">
    <div class="table-responsive">
      <table class="parent-table">
        <thead>
          <tr>
            <th>Nom du Parent</th>
            <th>Contact & Identifiants</th>
            <th>Élève(s) associé(s)</th>
            <th>Statut</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($parents)): ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px;">
                Aucun parent enregistré pour le moment.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($parents as $parent): ?>
              <tr>
                <td>
                  <div class="parent-name"><?= clean($parent['last_name'] . ' ' . $parent['first_name']) ?></div>
                  <div class="parent-meta">Utilisateur : @<?= clean($parent['username']) ?></div>
                </td>
                
                <td>
                  <div style="display:flex; flex-direction:column; gap:2px;">
                    <span><i class="bx bx-envelope" style="color:var(--text-muted)"></i> <?= clean($parent['email']) ?></span>
                    <?php if(!empty($parent['phone'])): ?>
                      <span class="parent-meta"><i class="bx bx-phone"></i> <?= clean($parent['phone']) ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                
                <td>
                  <div class="children-tags">
                    <?php if (!empty($parent['enfants'])): 
                      $enfantsArr = explode(', ', $parent['enfants']);
                      foreach ($enfantsArr as $enfant): ?>
                        <span class="child-badge"><i class="bx bx-graduation"></i> <?= clean($enfant) ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">Aucun élève lié</span>
                    <?php endif; ?>
                  </div>
                </td>
                
                <td>
                  <span class="status-badge <?= $parent['is_active'] == 1 ? 'status-active' : 'status-inactive' ?>">
                    <i class="bx bx-circle"></i> <?= $parent['is_active'] == 1 ? 'Actif' : 'Inactif' ?>
                  </span>
                </td>
                
                <td style="text-align: right;">
                  <div class="row-actions" style="justify-content: flex-end;">
                    <a href="<?= BASE_URL ?>/admin/parents-edit.php?id=<?= $parent['id'] ?>" class="btn-icon" title="Modifier">
                      <i class="bx bx-edit-alt"></i>
                    </a>
                    <a href="?delete_id=<?= $parent['id'] ?>" class="btn-icon btn-delete" title="Supprimer" 
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce compte parent ?');">
                      <i class="bx bx-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

</body>
</html>