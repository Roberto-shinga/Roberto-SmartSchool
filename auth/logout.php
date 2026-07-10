<?php
// ============================================================
//  SmartSchool — Page de déconnexion
//  Emplacement : auth/logout.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

// 1. Journaliser l'activité avant de détruire la session (si l'utilisateur était connecté)
if (isLoggedIn()) {
    logActivity('logout', 'Déconnexion de l\'utilisateur ID : ' . ($_SESSION['user_id'] ?? 'Inconnu'));
}

// 2. Vider les variables de session actives
$_SESSION = [];

// 3. Détruire complètement la session côté serveur
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}
session_destroy();

// 4. Supprimer le cookie "Se souvenir de moi" (ss_remember) s'il existe
if (isset($_COOKIE['ss_remember'])) {
    setcookie('ss_remember', '', time() - 3600, '/', '', false, true);
}

// 5. Rediriger vers la page de connexion avec un message de succès
// Note : Si votre fonction 'redirectWith' recrée une session pour le flash message, 
// elle s'occupera d'appeler session_start() automatiquement si nécessaire.
redirectWith(BASE_URL . '/auth/login.php', 'success', 'Vous avez été déconnecté avec succès. À bientôt !');
exit;