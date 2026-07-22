<?php
// ============================================================
//  SmartSchool RDC — Constantes globales
//  Fichier : config/constants.php
// ============================================================

// ── Application ─────────────────────────────────────────────
define('APP_NAME',     'SmartSchool');
define('APP_VERSION',  '2.0.0');
define('APP_YEAR',     date('Y'));

// ── Chemin absolu racine ─────────────────────────────────────
define('ROOT_PATH',     'C:/xampp/htdocs/SmartSchool');
define('BASE_URL',      'http://localhost/SmartSchool');
define('ASSETS_URL',    BASE_URL . '/assets');
define('UPLOADS_PATH',  ROOT_PATH . '/assets/uploads');
define('UPLOADS_URL',   ASSETS_URL . '/uploads');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH',   ROOT_PATH . '/config');

// ── Roles (IDs en base) ──────────────────────────────────────
define('ROLE_SUPER_ADMIN', 1);
define('ROLE_ADMIN',       2);
define('ROLE_TEACHER',     3);
define('ROLE_STUDENT',     4);
define('ROLE_PARENT',      5);
define('ROLE_ACCOUNTANT',  6);

// ── Labels des roles ─────────────────────────────────────────
define('ROLE_LABELS', [
    ROLE_SUPER_ADMIN => 'Super Administrateur',
    ROLE_ADMIN       => 'Administrateur',
    ROLE_TEACHER     => 'Enseignant',
    ROLE_STUDENT     => 'Eleve',
    ROLE_PARENT      => 'Parent',
    ROLE_ACCOUNTANT  => 'Comptable',
]);

// ── Icones des roles ─────────────────────────────────────────
define('ROLE_ICONS', [
    ROLE_SUPER_ADMIN => 'bx-shield-alt-2',
    ROLE_ADMIN       => 'bx-user-check',
    ROLE_TEACHER     => 'bx-chalkboard',
    ROLE_STUDENT     => 'bx-graduation',
    ROLE_PARENT      => 'bx-group',
    ROLE_ACCOUNTANT  => 'bx-calculator',
]);

// ── Couleurs des roles ───────────────────────────────────────
define('ROLE_COLORS', [
    ROLE_SUPER_ADMIN => '#7c3aed',
    ROLE_ADMIN       => '#6366f1',
    ROLE_TEACHER     => '#0891b2',
    ROLE_STUDENT     => '#059669',
    ROLE_PARENT      => '#d97706',
    ROLE_ACCOUNTANT  => '#dc2626',
]);

// ── Redirections apres connexion ─────────────────────────────
define('ROLE_REDIRECTS', [
    ROLE_SUPER_ADMIN => BASE_URL . '/admin/index.php',
    ROLE_ADMIN       => BASE_URL . '/admin/index.php',
    ROLE_TEACHER     => BASE_URL . '/teachers/index.php',
    ROLE_STUDENT     => BASE_URL . '/students/index.php',
    ROLE_PARENT      => BASE_URL . '/parents/index.php',
    ROLE_ACCOUNTANT  => BASE_URL . '/accountant/index.php',
]);

// ── Niveaux scolaires RDC ────────────────────────────────────
define('LEVEL_PRIMAIRE', 1);
define('LEVEL_CYCLE',    2);
define('LEVEL_HUM',      3);

// Annees par niveau
define('LEVEL_YEARS', [
    LEVEL_PRIMAIRE => [1, 2, 3, 4, 5, 6],
    LEVEL_CYCLE    => [7, 8],
    LEVEL_HUM      => [1, 2, 3, 4],
]);

// Eleves avec compte : a partir de la 7e annee (cycle terminal)
define('ACCOUNT_MIN_LEVEL', LEVEL_CYCLE);

// ── Options scolaires RDC ────────────────────────────────────
define('SCHOOL_OPTIONS', [
    'SCI'  => 'Scientifique',
    'LIT'  => 'Litteraire',
    'PED'  => 'Pedagogie',
    'COM'  => 'Commerciale et Gestion',
    'ELEC' => 'Electricite',
    'CC'   => 'Coupe et Couture',
    'INFO' => 'Informatique',
    'GEN'  => 'Generale',
]);

// ── Mentions / appréciations ─────────────────────────────────
define('GRADE_MENTIONS', [
    ['min'=>18, 'max'=>20, 'label'=>'Felicitations',   'color'=>'#10b981'],
    ['min'=>16, 'max'=>18, 'label'=>'Tres bien',       'color'=>'#6366f1'],
    ['min'=>14, 'max'=>16, 'label'=>'Bien',            'color'=>'#3b82f6'],
    ['min'=>12, 'max'=>14, 'label'=>'Assez bien',      'color'=>'#06b6d4'],
    ['min'=>10, 'max'=>12, 'label'=>'Passable',        'color'=>'#f59e0b'],
    ['min'=>0,  'max'=>10, 'label'=>'Insuffisant',     'color'=>'#ef4444'],
]);

// ── Jours de la semaine ──────────────────────────────────────
define('DAYS_OF_WEEK', [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
]);

// ── Session & Securite ───────────────────────────────────────
define('SESSION_NAME',   'SMARTSCHOOL_SESS');
define('CSRF_TOKEN_NAME','_csrf_token');
define('REMEMBER_DAYS',  30);
define('SESSION_TIMEOUT', 7200); // 2 heures

// ── Pagination ───────────────────────────────────────────────
define('PER_PAGE', 20);

// ── Upload ───────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_IMG',  ['jpg','jpeg','png','gif','webp']);
define('ALLOWED_DOCS', ['pdf','doc','docx','xls','xlsx','ppt','pptx']);

// ── Base de données ──────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartschool');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// ── Google OAuth (optionnel) ─────────────────────────────────
// Remplace par tes credentials Google Cloud Console
// Laisser vide pour desactiver
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID',     '');
    define('GOOGLE_CLIENT_SECRET', '');
    define('GOOGLE_REDIRECT_URI',  BASE_URL . '/auth/google-callback.php');
    define('GOOGLE_AUTH_URL',      'https://accounts.google.com/o/oauth2/v2/auth');
    define('GOOGLE_TOKEN_URL',     'https://oauth2.googleapis.com/token');
    define('GOOGLE_USERINFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');
}
