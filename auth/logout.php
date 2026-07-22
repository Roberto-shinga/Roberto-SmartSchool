<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
logActivity('logout', 'Deconnexion');
logoutUser();
redirectWith(BASE_URL . '/auth/login.php', 'success', 'Vous avez ete deconnecte.');
