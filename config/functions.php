<?php
// ============================================================
//  SmartSchool RDC — Fonctions globales
//  Fichier : config/functions.php
// ============================================================

// ════════════════════════════════════════════════
//  SESSION & AUTHENTIFICATION
// ════════════════════════════════════════════════

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    // Timeout inactivite
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']['id']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function currentRole(): int
{
    return (int)($_SESSION['user']['role_id'] ?? 0);
}

function requireRole(int ...$roles): void
{
    if (!isLoggedIn()) {
        redirectWith(BASE_URL . '/auth/login.php', 'warning', 'Veuillez vous connecter.');
    }
    if (!empty($roles) && !in_array(currentRole(), $roles)) {
        redirect(BASE_URL . '/auth/unauthorized.php');
    }
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'                   => $user['id'],
        'role_id'              => $user['role_id'],
        'username'             => $user['username'],
        'email'                => $user['email'] ?? null,
        'first_name'           => $user['first_name'],
        'last_name'            => $user['last_name'],
        'must_change_password' => $user['must_change_password'] ?? 0,
    ];
    dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    logActivity('login', 'Connexion reussie : ' . $user['username']);
}

function logoutUser(): void
{
    session_unset();
    session_destroy();
}

function hashPassword(string $pwd): string
{
    return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $pwd, string $hash): bool
{
    return password_verify($pwd, $hash);
}

// ════════════════════════════════════════════════
//  CSRF
// ════════════════════════════════════════════════

function csrfToken(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrfToken() . '">';
}

function verifyCsrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Token CSRF invalide. Veuillez recharger la page.');
    }
}

// ════════════════════════════════════════════════
//  FLASH MESSAGES
// ════════════════════════════════════════════════

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function showFlash(): string
{
    $f = getFlash();
    if (!$f) return '';
    $icons = ['success'=>'bx-check-circle','danger'=>'bx-x-circle','warning'=>'bx-error','info'=>'bx-info-circle'];
    $icon  = $icons[$f['type']] ?? 'bx-info-circle';
    return '<div class="alert alert-' . clean($f['type']) . ' animate-in" style="margin-bottom:20px">
              <i class="bx ' . $icon . '"></i> ' . clean($f['message']) . '
            </div>';
}

// ════════════════════════════════════════════════
//  REDIRECTIONS
// ════════════════════════════════════════════════

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function redirectWith(string $url, string $type, string $message): void
{
    setFlash($type, $message);
    redirect($url);
}

// ════════════════════════════════════════════════
//  SECURITE & NETTOYAGE
// ════════════════════════════════════════════════

function clean(mixed $val): string
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeString(string $str): string
{
    return trim(strip_tags($str));
}

function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

// ════════════════════════════════════════════════
//  MATRICULE & IDENTIFIANTS
// ════════════════════════════════════════════════

function generateStudentNumber(): string
{
    $year  = date('Y');
    $count = dbFetchOne("SELECT COUNT(*) c FROM students WHERE YEAR(created_at) = ?", [$year])['c'] ?? 0;
    return 'STU-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function generateEmployeeId(): string
{
    $count = dbFetchOne("SELECT COUNT(*) c FROM teachers")['c'] ?? 0;
    return 'EMP-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function generateReceiptNumber(): string
{
    return 'REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

// Generer un mot de passe temporaire pour premiere connexion
function generateTempPassword(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pwd   = '';
    for ($i = 0; $i < 8; $i++) {
        $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pwd;
}

// ════════════════════════════════════════════════
//  FORMATAGE
// ════════════════════════════════════════════════

function formatMoney(float $amount, string $currency = null): string
{
    $cur = $currency ?? getSetting('currency', 'FC');
    return number_format($amount, 0, ',', '.') . ' ' . $cur;
}

function formatDate(string|null $date): string
{
    if (!$date) return '—';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime(string|null $dt): string
{
    if (!$dt) return '—';
    return date('d/m/Y H:i', strtotime($dt));
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'A l\'instant';
    if ($diff < 3600)   return floor($diff / 60) . ' min';
    if ($diff < 86400)  return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'j';
    return formatDate($datetime);
}

function getInitials(string $fn, string $ln): string
{
    return strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1));
}

// ════════════════════════════════════════════════
//  MENTIONS / APPRÉCIATIONS
// ════════════════════════════════════════════════

function getMention(float $score): array
{
    foreach (GRADE_MENTIONS as $m) {
        if ($score >= $m['min'] && $score <= $m['max']) return $m;
    }
    return ['label' => 'Non note', 'color' => '#94a3b8'];
}

// ════════════════════════════════════════════════
//  PARAMÈTRES SYSTÈME
// ════════════════════════════════════════════════

function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = dbFetchOne("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
        $cache[$key] = $row['setting_value'] ?? $default;
    }
    return $cache[$key] ?: $default;
}

function setSetting(string $key, string $value): void
{
    $exists = dbFetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
    if ($exists) {
        dbExecute("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
    } else {
        dbExecute("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
    }
}

// ════════════════════════════════════════════════
//  THEME / DARK MODE
// ════════════════════════════════════════════════

function isDarkMode(): bool
{
    return ($_COOKIE['ss_dark_mode'] ?? '0') === '1';
}

function themeClass(): string
{
    return isDarkMode() ? 'dark-mode' : '';
}

// ════════════════════════════════════════════════
//  NOTIFICATIONS & MESSAGES
// ════════════════════════════════════════════════

function createNotification(int $userId, string $title, string $message, string $type = 'info', string $link = ''): void
{
    dbExecute(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)",
        [$userId, $title, $message, $type, $link]
    );
}

function countUnreadNotifications(int $userId): int
{
    return (int)(dbFetchOne("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])['c'] ?? 0);
}

function countUnreadMessages(int $userId): int
{
    return (int)(dbFetchOne("SELECT COUNT(*) c FROM messages WHERE receiver_id = ? AND is_read = 0", [$userId])['c'] ?? 0);
}

// ════════════════════════════════════════════════
//  LOGS
// ════════════════════════════════════════════════

function logActivity(string $action, string $description = ''): void
{
    $userId = $_SESSION['user']['id'] ?? null;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
    try {
        dbExecute(
            "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)",
            [$userId, $action, $description, $ip]
        );
    } catch (Exception $e) {
        // Ne pas bloquer si le log echoue
    }
}

// ════════════════════════════════════════════════
//  PAGINATION
// ════════════════════════════════════════════════

function paginate(int $total, int $page = 1, int $perPage = PER_PAGE): array
{
    $totalPages  = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($page, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
        'prev_page'    => $currentPage - 1,
        'next_page'    => $currentPage + 1,
    ];
}

// ════════════════════════════════════════════════
//  UPLOAD DE FICHIERS
// ════════════════════════════════════════════════

function uploadFile(array $file, string $subDir = 'general', array $allowed = null): array
{
    $allowed  = $allowed ?? array_merge(ALLOWED_IMG, ALLOWED_DOCS);
    $destDir  = UPLOADS_PATH . '/' . $subDir;

    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Erreur lors du telechargement.'];
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 10 Mo).'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'error' => 'Format de fichier non autorise.'];
    }

    $newName = uniqid() . '_' . time() . '.' . $ext;
    $destPath = $destDir . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Impossible de sauvegarder le fichier.'];
    }

    return [
        'success'   => true,
        'filename'  => $newName,
        'path'      => $destPath,
        'url'       => UPLOADS_URL . '/' . $subDir . '/' . $newName,
        'extension' => $ext,
    ];
}

// ════════════════════════════════════════════════
//  GOOGLE OAUTH
// ════════════════════════════════════════════════

function buildGoogleAuthUrl(): string
{
    if (empty(GOOGLE_CLIENT_ID)) return '';

    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    return GOOGLE_AUTH_URL . '?' . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);
}

// ════════════════════════════════════════════════
//  HELPERS ELEVES
// ════════════════════════════════════════════════

// Nom complet d'un eleve (avec ou sans compte user)
function getStudentFullName(array $student): string
{
    if (!empty($student['first_name'])) {
        return trim($student['first_name'] . ' ' . ($student['last_name'] ?? ''));
    }
    // Chercher dans users si lien user_id
    if (!empty($student['user_id'])) {
        $u = dbFetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$student['user_id']]);
        if ($u) return trim($u['first_name'] . ' ' . $u['last_name']);
    }
    return 'Eleve #' . $student['id'];
}

// Verifier si un eleve doit avoir un compte (7e+)
function studentNeedsAccount(int $levelId): bool
{
    return $levelId >= ACCOUNT_MIN_LEVEL;
}
