<?php
// ============================================================
//  SmartSchool — Connexion base de donnees
//  Emplacement : C:\xampp\htdocs\SmartSchool\config\database.php
// ============================================================

// Parametres de connexion XAMPP par defaut
define('DB_HOST',    'localhost');
define('DB_NAME',    'smartschool');
define('DB_USER',    'root');
define('DB_PASS',    '');           // XAMPP : mot de passe vide par defaut
define('DB_CHARSET', 'utf8mb4');

// Connexion PDO — creee une seule fois
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Affiche l'erreur clairement pendant le developpement
            die('<h2 style="color:red;font-family:sans-serif">
                Erreur de connexion MySQL :<br>
                ' . $e->getMessage() . '<br><br>
                Verifie que MySQL est demarre dans XAMPP.
            </h2>');
        }
    }

    return $pdo;
}

// SELECT — retourne toutes les lignes
function dbFetchAll(string $sql, array $params = []): array
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// SELECT — retourne une seule ligne
function dbFetchOne(string $sql, array $params = []): array|false
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// INSERT / UPDATE / DELETE
function dbExecute(string $sql, array $params = []): int
{
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

// Dernier ID insere
function dbLastId(): string
{
    return getDB()->lastInsertId();
}
