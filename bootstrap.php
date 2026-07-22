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
