<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Tableau de bord technique';
$pageSection = 'dashboard';
$user        = currentUser();

$setupDone     = isSetupCompleted();
$nbAdmins      = dbCount('users', "role_id = ? AND is_active = 1", [ROLE_ADMIN]);
$nbActiveUsers = dbCount('users', "is_active = 1");
$nbInactive    = dbCount('users', "is_active = 0");

$recentSecurityLogs = dbFetchAll(
    "SELECT al.*, u.first_name, u.last_name
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     WHERE al.action LIKE 'superadmin_%' OR al.action LIKE 'login%'
     ORDER BY al.created_at DESC LIMIT 8"
);

$actionLabels = [
    'superadmin_2fa_sent'        => ['Code 2FA envoye',        'bx-mail-send',    'info'],
    'superadmin_2fa_verified'    => ['Connexion verifiee',     'bx-shield-quarter','success'],
    'superadmin_2fa_failed'      => ['Code 2FA incorrect',     'bx-error',        'danger'],
    'superadmin_2fa_mail_failed' => ['Echec envoi email 2FA',  'bx-envelope',     'warning'],
    'login'                      => ['Connexion reussie',      'bx-log-in',       'success'],
    'login_failed'               => ['Tentative de connexion echouee', 'bx-error', 'danger'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Espace Super Administrateur</h1>
        <p>Configuration, securite et supervision technique — <?= clean($user['first_name']) ?></p>
      </div>
    </div>

    <?php if (!$setupDone): ?>
    <div class="alert alert-warning" style="margin-bottom:24px">
      <i class="bx bx-error-circle"></i>
      <div>
        <strong>Configuration initiale non terminee.</strong>
        L'assistant de configuration (etablissement → creation de l'Administrateur principal → transfert
        de la gestion quotidienne) n'a pas encore ete complete. Cette etape arrive dans la prochaine phase
        de developpement.
      </div>
    </div>
    <?php endif; ?>

    <div class="grid-3 mb-6">
      <div class="stat-card c-purple animate-in">
        <div class="stat-icon c-purple"><i class="bx bx-user-check"></i></div>
        <div>
          <div class="stat-value"><?= $nbAdmins ?></div>
          <div class="stat-label">Administrateur(s) actif(s)</div>
        </div>
      </div>
      <div class="stat-card c-primary animate-in d1">
        <div class="stat-icon c-primary"><i class="bx bx-group"></i></div>
        <div>
          <div class="stat-value"><?= $nbActiveUsers ?></div>
          <div class="stat-label">Comptes actifs (tous roles)</div>
        </div>
      </div>
      <div class="stat-card c-danger animate-in d2">
        <div class="stat-icon c-danger"><i class="bx bx-block"></i></div>
        <div>
          <div class="stat-value"><?= $nbInactive ?></div>
          <div class="stat-label">Comptes desactives</div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- Rappel des responsabilites -->
      <div class="card animate-in">
        <div class="card-header"><h3><i class="bx bx-info-circle"></i> Perimetre du Super Administrateur</h3></div>
        <div class="card-body">
          <p class="text-sm text-muted" style="line-height:1.7;margin-bottom:14px">
            Ce compte est reserve a la configuration, la securite et la maintenance technique de la
            plateforme. La gestion quotidienne (eleves, notes, presences, paiements) releve de
            l'Administrateur principal, des enseignants et du comptable.
          </p>
          <div style="display:flex;flex-direction:column;gap:8px">
            <div class="flex items-center gap-3 text-sm"><i class="bx bx-user-check text-primary"></i> Gerer les comptes Administrateur</div>
            <div class="flex items-center gap-3 text-sm"><i class="bx bx-key text-primary"></i> Definir les roles et permissions</div>
            <div class="flex items-center gap-3 text-sm"><i class="bx bx-lock-alt text-primary"></i> Parametrer la securite (mots de passe, 2FA)</div>
            <div class="flex items-center gap-3 text-sm"><i class="bx bx-list-ul text-primary"></i> Consulter les journaux d'audit</div>
            <div class="flex items-center gap-3 text-sm"><i class="bx bx-cloud-upload text-primary"></i> Sauvegarder / restaurer la base de donnees</div>
          </div>
        </div>
      </div>

      <!-- Journaux de securite recents -->
      <div class="card animate-in d1">
        <div class="card-header">
          <h3><i class="bx bx-shield-quarter"></i> Activite de securite recente</h3>
        </div>
        <div class="card-body" style="padding:8px 0">
          <?php if (empty($recentSecurityLogs)): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-shield"></i></div>
              <p>Aucun evenement de securite recent.</p>
            </div>
          <?php else: ?>
            <?php foreach ($recentSecurityLogs as $log):
              [$label, $icon, $color] = $actionLabels[$log['action']] ?? [$log['action'], 'bx-info-circle', 'primary'];
              $actor = $log['first_name'] ? clean($log['first_name'] . ' ' . $log['last_name']) : 'Systeme';
            ?>
            <div style="display:flex;gap:12px;padding:10px 20px;align-items:flex-start">
              <div class="stat-icon c-<?= $color ?>" style="width:34px;height:34px;font-size:1rem">
                <i class="bx <?= $icon ?>"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div class="text-sm font-semibold" style="color:var(--text-primary)"><?= $label ?></div>
                <div class="text-xs text-muted truncate"><?= $actor ?> — <?= clean($log['description'] ?: '') ?></div>
              </div>
              <div class="text-xs text-muted" style="white-space:nowrap"><?= timeAgo($log['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
