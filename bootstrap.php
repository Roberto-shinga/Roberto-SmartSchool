<?php
// ============================================================
//  SmartSchool RDC — Point d'entree global
//  Fichier : bootstrap.php (racine)
// ============================================================

// Erreurs (desactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Timezone RDC (Kinshasa)
date_default_timezone_set('Africa/Kinshasa');

// Chargement config
require_once 'C:/xampp/htdocs/SmartSchool/config/constants.php';
require_once 'C:/xampp/htdocs/SmartSchool/config/database.php';
require_once 'C:/xampp/htdocs/SmartSchool/config/functions.php';

// Demarrer la session
startSession();

// Protection XSS header
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Mode maintenance ──────────────────────────────────────────
// Bloque tout le site sauf le Super Admin (deja verifie 2FA) et les
// pages d'authentification / l'espace superadmin lui-meme.
if (getSetting('maintenance_mode', '0') === '1') {
    $scriptPath   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $isSuperAdmin = isLoggedIn() && (int)($_SESSION['user']['role_id'] ?? 0) === ROLE_SUPER_ADMIN
                    && !empty($_SESSION['superadmin_2fa_ok']);
    $isExempt     = str_contains($scriptPath, '/superadmin/') || str_contains($scriptPath, '/auth/');

    if (!$isSuperAdmin && !$isExempt) {
        http_response_code(503);
        require ROOT_PATH . '/includes/maintenance-view.php';
        exit;
    }
}
