<?php
// ============================================================
//  SmartSchool RDC — Connexion base de donnees (PDO)
//  Fichier : config/database.php
// ============================================================

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHAR
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        // Afficher une page d'erreur propre
        http_response_code(503);
        die('
        <!DOCTYPE html><html lang="fr"><head>
        <meta charset="UTF-8"><title>Erreur de connexion</title>
        <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8faff;margin:0;}
        .box{background:#fff;border-radius:12px;padding:40px;max-width:500px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,0.08);}
        h2{color:#ef4444;margin-bottom:12px;}p{color:#64748b;font-size:14px;}</style>
        </head><body><div class="box">
        <h2>⚠️ Impossible de se connecter a la base de donnees</h2>
        <p>Verifiez que MySQL est demarré dans XAMPP et que la base <strong>smartschool</strong> existe.</p>
        <p style="font-size:12px;margin-top:16px;color:#94a3b8">' . htmlspecialchars($e->getMessage()) . '</p>
        </div></body></html>');
    }
    return $pdo;
}

// ── Requêtes raccourcies ─────────────────────────────────────

function dbFetchAll(string $sql, array $params = []): array
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbFetchOne(string $sql, array $params = []): ?array
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dbExecute(string $sql, array $params = []): int
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function dbLastId(): int
{
    return (int) getDB()->lastInsertId();
}

function dbCount(string $table, string $where = '1=1', array $params = []): int
{
    $row = dbFetchOne("SELECT COUNT(*) c FROM `$table` WHERE $where", $params);
    return (int)($row['c'] ?? 0);
}
