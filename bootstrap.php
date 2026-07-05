<?php
// ============================================================
//  SmartSchool — Bootstrap
//  Emplacement : C:\xampp\htdocs\SmartSchool\bootstrap.php
//  Ce fichier est inclus par TOUTES les pages du projet.
//  Il charge les 3 fichiers de config dans le bon ordre.
// ============================================================

// Racine absolue du projet
define('ROOT_PATH_ABS', 'C:/xampp/htdocs/SmartSchool');

// Afficher les erreurs PHP pendant le developpement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Chargement dans l'ordre correct
require_once ROOT_PATH_ABS . '/config/database.php';
require_once ROOT_PATH_ABS . '/config/constants.php';
require_once ROOT_PATH_ABS . '/config/functions.php';
