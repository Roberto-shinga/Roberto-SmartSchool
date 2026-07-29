<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Parametres de securite';
$pageSection = 'security';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $pwdMinLength   = max(6, min(32, (int)($_POST['pwd_min_length'] ?? 8)));
    $pwdReqNumber   = !empty($_POST['pwd_require_number']) ? '1' : '0';
    $pwdReqSymbol   = !empty($_POST['pwd_require_symbol']) ? '1' : '0';
    $sessionTimeout = max(15, min(480, (int)($_POST['session_timeout'] ?? 120)));
    $superadmin2fa  = !empty($_POST['superadmin_2fa_enabled']) ? '1' : '0';

    setSetting('pwd_min_length', (string)$pwdMinLength);
    setSetting('pwd_require_number', $pwdReqNumber);
    setSetting('pwd_require_symbol', $pwdReqSymbol);
    setSetting('session_timeout_minutes', (string)$sessionTimeout);
    setSetting('superadmin_2fa_enabled', $superadmin2fa);

    logActivity('security_settings_updated', "Politique mdp min=$pwdMinLength, 2FA=$superadmin2fa, session={$sessionTimeout}min");
    redirectWith($_SERVER['PHP_SELF'], 'success', 'Parametres de securite mis a jour.');
}

$pwdMinLength   = (int)getSetting('pwd_min_length', '8');
$pwdReqNumber   = getSetting('pwd_require_number', '0') === '1';
$pwdReqSymbol   = getSetting('pwd_require_symbol', '0') === '1';
$sessionTimeout = (int)getSetting('session_timeout_minutes', '120');
$superadmin2fa  = getSetting('superadmin_2fa_enabled', '1') === '1';

// ── Activite de connexion suspecte ────────────────────────────
$failedLogins24h = dbFetchOne(
    "SELECT COUNT(*) c FROM activity_logs WHERE action='login_failed' AND created_at >= NOW() - INTERVAL 24 HOUR"
)['c'] ?? 0;

$suspiciousIps = dbFetchAll(
    "SELECT ip_address, COUNT(*) attempts, MAX(created_at) last_attempt
     FROM activity_logs
     WHERE action='login_failed' AND created_at >= NOW() - INTERVAL 24 HOUR AND ip_address IS NOT NULL
     GROUP BY ip_address HAVING attempts >= 3
     ORDER BY attempts DESC LIMIT 10"
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Parametres de securite</h1><p>Politique de mot de passe, double authentification, sessions</p></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-lock-alt"></i> Politique de securite</h3></div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
              <label class="form-label">Longueur minimale du mot de passe</label>
              <input type="number" name="pwd_min_length" class="form-control" min="6" max="32" value="<?= $pwdMinLength ?>">
              <span class="form-hint">Applique aux nouveaux mots de passe (activation de compte, changement).</span>
            </div>
            <div class="form-group">
              <label class="form-check">
                <input type="checkbox" name="pwd_require_number" <?= $pwdReqNumber ? 'checked' : '' ?>>
                <span class="form-check-label">Exiger au moins un chiffre</span>
              </label>
            </div>
            <div class="form-group">
              <label class="form-check">
                <input type="checkbox" name="pwd_require_symbol" <?= $pwdReqSymbol ? 'checked' : '' ?>>
                <span class="form-check-label">Exiger au moins un caractere special</span>
              </label>
            </div>
            <div class="form-group">
              <label class="form-label">Expiration de session (minutes)</label>
              <input type="number" name="session_timeout" class="form-control" min="15" max="480" value="<?= $sessionTimeout ?>">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-check">
                <input type="checkbox" name="superadmin_2fa_enabled" <?= $superadmin2fa ? 'checked' : '' ?>>
                <span class="form-check-label">Double authentification obligatoire pour le Super Administrateur</span>
              </label>
              <span class="form-hint">Fortement recommande de laisser active en production.</span>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:18px">
              <i class="bx bx-save"></i> Enregistrer
            </button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bx bx-error-circle"></i> Activite de connexion suspecte</h3></div>
        <div class="card-body">
          <div class="stat-card c-danger" style="margin-bottom:18px">
            <div class="stat-icon c-danger"><i class="bx bx-error"></i></div>
            <div>
              <div class="stat-value"><?= (int)$failedLogins24h ?></div>
              <div class="stat-label">Tentatives echouees (24h)</div>
            </div>
          </div>

          <?php if (empty($suspiciousIps)): ?>
            <div class="empty-state" style="padding:24px 12px">
              <div class="empty-state-icon" style="font-size:2rem"><i class="bx bx-shield"></i></div>
              <p>Aucune adresse IP suspecte detectee sur les dernieres 24 heures.</p>
            </div>
          <?php else: ?>
            <p class="text-sm text-muted" style="margin-bottom:10px">Adresses avec 3 tentatives ou plus :</p>
            <?php foreach ($suspiciousIps as $ip): ?>
              <div class="flex justify-between items-center" style="padding:8px 0;border-bottom:1px solid var(--border-light)">
                <span class="text-sm font-mono"><?= clean($ip['ip_address']) ?></span>
                <span class="badge badge-danger"><?= (int)$ip['attempts'] ?> tentatives</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
