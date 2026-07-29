<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$file = basename($_GET['file'] ?? '');
$path = BACKUPS_PATH . '/' . $file;

if (!$file || !str_ends_with($file, '.sql') || !is_file($path)) {
    http_response_code(404);
    die('Fichier introuvable.');
}

logActivity('backup_downloaded', $file);

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
