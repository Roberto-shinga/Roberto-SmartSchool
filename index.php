<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

if (isLoggedIn()) {
    redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL . '/auth/login.php');
}
redirect(BASE_URL . '/auth/login.php');