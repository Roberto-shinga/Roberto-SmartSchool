<?php
// ============================================================
//  SmartSchool — Constantes globales
//  Emplacement : config/constants.php
// ============================================================

// ── Application ──
define('APP_NAME',    'SmartSchool');
define('APP_VERSION', '1.0.0');
define('APP_YEAR',    date('Y'));

// ── URL de base — NE PAS mettre de slash a la fin ──
define('BASE_URL',    'http://localhost/SmartSchool');
define('ASSETS_URL',  'http://localhost/SmartSchool/assets');
define('UPLOADS_URL', 'http://localhost/SmartSchool/uploads');

// ── Chemins absolus serveur ──
define('ROOT_PATH',     'C:/xampp/htdocs/SmartSchool');
define('CONFIG_PATH',   ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH',  ROOT_PATH . '/uploads');
define('ASSETS_PATH',   ROOT_PATH . '/assets');

// ── Roles (IDs dans la table roles) ──
define('ROLE_SUPER_ADMIN', 1);
define('ROLE_ADMIN',       2);
define('ROLE_TEACHER',     3);
define('ROLE_STUDENT',     4);
define('ROLE_PARENT',      5);
define('ROLE_ACCOUNTANT',  6);

// ── Redirection apres connexion selon le role ──
define('ROLE_REDIRECTS', [
    ROLE_SUPER_ADMIN => BASE_URL . '/admin/index.php',
    ROLE_ADMIN       => BASE_URL . '/admin/index.php',
    ROLE_TEACHER     => BASE_URL . '/teachers/index.php',
    ROLE_STUDENT     => BASE_URL . '/students/index.php',
    ROLE_PARENT      => BASE_URL . '/parents/index.php',
    ROLE_ACCOUNTANT  => BASE_URL . '/accountant/index.php',
]);

// ── Labels des roles ──
define('ROLE_LABELS', [
    ROLE_SUPER_ADMIN => 'Super Administrateur',
    ROLE_ADMIN       => 'Administrateur',
    ROLE_TEACHER     => 'Enseignant',
    ROLE_STUDENT     => 'Eleve',
    ROLE_PARENT      => 'Parent',
    ROLE_ACCOUNTANT  => 'Comptable',
]);

// ── Couleurs des roles ──
define('ROLE_COLORS', [
    ROLE_SUPER_ADMIN => '#7c3aed',
    ROLE_ADMIN       => '#6366f1',
    ROLE_TEACHER     => '#0891b2',
    ROLE_STUDENT     => '#059669',
    ROLE_PARENT      => '#d97706',
    ROLE_ACCOUNTANT  => '#dc2626',
]);

// ── Icones Boxicons des roles ──
define('ROLE_ICONS', [
    ROLE_SUPER_ADMIN => 'bx-shield-alt-2',
    ROLE_ADMIN       => 'bx-user-check',
    ROLE_TEACHER     => 'bx-chalkboard',
    ROLE_STUDENT     => 'bx-graduation',
    ROLE_PARENT      => 'bx-group',
    ROLE_ACCOUNTANT  => 'bx-calculator',
]);

// ── Session ──
define('SESSION_NAME',     'smartschool_sess');
define('SESSION_LIFETIME', 7200);
define('REMEMBER_DAYS',    30);

// ── Pagination ──
define('ITEMS_PER_PAGE', 15);

// ── Notes ──
define('MAX_GRADE',     20);
define('PASSING_GRADE', 10);

// ── Mentions selon la moyenne ──
define('GRADE_MENTIONS', [
    ['min' => 18, 'max' => 20, 'label' => 'Felicitations',  'color' => '#059669'],
    ['min' => 16, 'max' => 18, 'label' => 'Tres bien',      'color' => '#0891b2'],
    ['min' => 14, 'max' => 16, 'label' => 'Bien',           'color' => '#6366f1'],
    ['min' => 12, 'max' => 14, 'label' => 'Assez bien',     'color' => '#d97706'],
    ['min' => 10, 'max' => 12, 'label' => 'Passable',       'color' => '#f59e0b'],
    ['min' =>  0, 'max' => 10, 'label' => 'Insuffisant',    'color' => '#dc2626'],
]);

// ── Statuts presences ──
define('ATTENDANCE_STATUS', [
    'present' => ['label' => 'Present',  'color' => '#059669', 'icon' => 'bx-check-circle'],
    'absent'  => ['label' => 'Absent',   'color' => '#dc2626', 'icon' => 'bx-x-circle'],
    'retard'  => ['label' => 'Retard',   'color' => '#d97706', 'icon' => 'bx-time'],
    'excuse'  => ['label' => 'Excuse',   'color' => '#6366f1', 'icon' => 'bx-info-circle'],
]);

// ── Statuts paiements ──
define('PAYMENT_STATUS', [
    'paye'    => ['label' => 'Paye',    'color' => '#059669', 'bg' => '#d1fae5'],
    'partiel' => ['label' => 'Partiel', 'color' => '#d97706', 'bg' => '#fef3c7'],
    'annule'  => ['label' => 'Annule',  'color' => '#dc2626', 'bg' => '#fee2e2'],
]);

// ── Cookie dark mode ──
define('DARK_MODE_COOKIE', 'ss_dark_mode');

// ── CSRF ──
define('CSRF_TOKEN_NAME', 'ss_csrf_token');

// ── Upload ──
define('MAX_FILE_SIZE',     5 * 1024 * 1024);
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
