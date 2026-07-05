<?php
// ============================================================
//  SmartSchool — Fonctions utilitaires
//  Emplacement : config/functions.php
// ============================================================

// ════════════════════════════════════════════════
//  SESSION & AUTHENTIFICATION
// ════════════════════════════════════════════════

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['user_id']);
}

function currentUser(): array|null
{
    startSession();
    return $_SESSION['user'] ?? null;
}

function currentRole(): int
{
    $user = currentUser();
    return $user ? (int)$user['role_id'] : 0;
}

// Protege une page — redirige si pas le bon role
function requireRole(int ...$roles): void
{
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/login.php');
    }
    if (!in_array(currentRole(), $roles, true)) {
        redirect(BASE_URL . '/auth/unauthorized.php');
    }
}

function loginUser(array $user): void
{
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user']    = $user;

    // Mettre a jour last_login
    dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
}

function logoutUser(): void
{
    startSession();
    $_SESSION = [];
    session_destroy();
}

// ════════════════════════════════════════════════
//  SECURITE
// ════════════════════════════════════════════════

function csrfToken(): string
{
    startSession();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME
         . '" value="' . csrfToken() . '">';
}

function verifyCsrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        die('Token CSRF invalide.');
    }
}

function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

// ════════════════════════════════════════════════
//  REDIRECTIONS & FLASH
// ════════════════════════════════════════════════

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit();
}

function redirectWith(string $url, string $type, string $message): never
{
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    redirect($url);
}

function getFlash(): array|null
{
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash(): string
{
    $flash = getFlash();
    if (!$flash) return '';

    $icons = [
        'success' => 'bx-check-circle',
        'danger'  => 'bx-x-circle',
        'warning' => 'bx-error',
        'info'    => 'bx-info-circle',
    ];
    $icon = $icons[$flash['type']] ?? 'bx-bell';

    return '<div class="alert alert-' . $flash['type'] . '">'
         . '<i class="bx ' . $icon . '"></i> '
         . clean($flash['message'])
         . '</div>';
}

// ════════════════════════════════════════════════
//  FORMATAGE
// ════════════════════════════════════════════════

function formatMoney(float $amount): string
{
    $currency = getSetting('currency', 'FCFA');
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

function formatDate(string $date): string
{
    if (empty($date) || $date === '0000-00-00') return '—';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime(string $datetime): string
{
    if (empty($datetime)) return '—';
    return date('d/m/Y H:i', strtotime($datetime));
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'il y a ' . $diff . 's';
    if ($diff < 3600)   return 'il y a ' . floor($diff / 60) . 'min';
    if ($diff < 86400)  return 'il y a ' . floor($diff / 3600) . 'h';
    if ($diff < 604800) return 'il y a ' . floor($diff / 86400) . 'j';
    return formatDate($datetime);
}

function getInitials(string $firstName, string $lastName): string
{
    return strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
}

function getMention(float $avg): array
{
    foreach (GRADE_MENTIONS as $m) {
        if ($avg >= $m['min'] && $avg <= $m['max']) return $m;
    }
    return ['label' => 'Non note', 'color' => '#6b7280'];
}

function generateReceiptNumber(): string
{
    return 'REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function generateStudentNumber(): string
{
    $count = dbFetchOne("SELECT COUNT(*) AS c FROM students")['c'] ?? 0;
    return 'STU-' . date('Y') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// ════════════════════════════════════════════════
//  NOTIFICATIONS & MESSAGES
// ════════════════════════════════════════════════

function countUnreadNotifications(int $userId): int
{
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c FROM notifications
         WHERE user_id = ? AND is_read = 0",
        [$userId]
    );
    return (int)($row['c'] ?? 0);
}

function countUnreadMessages(int $userId): int
{
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c FROM messages
         WHERE receiver_id = ? AND is_read = 0",
        [$userId]
    );
    return (int)($row['c'] ?? 0);
}

function createNotification(int $userId, string $title, string $message, string $type = 'info'): void
{
    dbExecute(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, ?, ?, ?)",
        [$userId, $title, $message, $type]
    );
}

// ════════════════════════════════════════════════
//  PARAMETRES SYSTEME
// ════════════════════════════════════════════════

function getSetting(string $key, string $default = ''): string
{
    $row = dbFetchOne(
        "SELECT setting_value FROM system_settings WHERE setting_key = ?",
        [$key]
    );
    return $row ? (string)$row['setting_value'] : $default;
}

// ════════════════════════════════════════════════
//  THEME & UI
// ════════════════════════════════════════════════

function isDarkMode(): bool
{
    return isset($_COOKIE[DARK_MODE_COOKIE]) && $_COOKIE[DARK_MODE_COOKIE] === '1';
}

function themeClass(): string
{
    return isDarkMode() ? 'dark-mode' : '';
}

function avatarUrl(string $filename, string $folder = 'avatars'): string
{
    $path = UPLOADS_PATH . '/' . $folder . '/' . $filename;
    if (!empty($filename) && $filename !== 'default.png' && file_exists($path)) {
        return UPLOADS_URL . '/' . $folder . '/' . $filename;
    }
    return ASSETS_URL . '/images/default-avatar.png';
}

// ════════════════════════════════════════════════
//  PAGINATION
// ════════════════════════════════════════════════

function paginate(int $total, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
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
//  DIVERS
// ════════════════════════════════════════════════

function isAjax(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function logActivity(string $action, string $description = ''): void
{
    $userId = isLoggedIn() ? (int)($_SESSION['user_id'] ?? null) : null;
    try {
        dbExecute(
            "INSERT INTO activity_logs (user_id, action, description, ip_address)
             VALUES (?, ?, ?, ?)",
            [$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Exception $e) {
        // Silencieux si la table n'existe pas encore
    }
}
