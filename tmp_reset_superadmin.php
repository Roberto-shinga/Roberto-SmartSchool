<?php
require 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

$password = 'SmartSchool2025!';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo = getDB();
$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE role_id = 1 LIMIT 1');
$stmt->execute([$hash]);
echo 'UPDATED' . PHP_EOL;
