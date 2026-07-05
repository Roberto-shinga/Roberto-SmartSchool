<?php
// ============================================================
//  SmartSchool — Fichier de test
//  Emplacement : C:\xampp\htdocs\SmartSchool\test.php
//  URL         : http://localhost/SmartSchool/test.php
//  SUPPRIMER apres validation !
// ============================================================

// Afficher toutes les erreurs PHP

require_once __DIR__ . '/bootstrap.php';
echo getSetting('school_name', 'SmartSchool') . '<br>';
echo ROLE_LABELS[ROLE_ADMIN] . '<br>';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<style>
    body  { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
    .ok   { background: #d4edda; border: 1px solid #28a745; padding: 12px 16px; border-radius: 6px; margin: 8px 0; color: #155724; font-size: 15px; }
    .err  { background: #f8d7da; border: 1px solid #dc3545; padding: 12px 16px; border-radius: 6px; margin: 8px 0; color: #721c24; font-size: 15px; }
    h1    { color: #6366f1; }
    h2    { color: #333; border-bottom: 2px solid #6366f1; padding-bottom: 6px; margin-top: 24px; }
</style>';

echo '<h1>🎓 SmartSchool — Test de configuration</h1>';

// ── TEST 1 : PHP ──
echo '<h2>1. PHP</h2>';
echo '<div class="ok">✅ PHP fonctionne — version : <strong>' . PHP_VERSION . '</strong></div>';

// ── TEST 2 : Fichier database.php ──
echo '<h2>2. Fichier config/database.php</h2>';
$dbFile = __DIR__ . '/config/database.php';
if (file_exists($dbFile)) {
    echo '<div class="ok">✅ Fichier database.php trouve</div>';
    require_once $dbFile;
} else {
    echo '<div class="err">❌ Fichier database.php introuvable dans config/<br>
    Chemin cherche : ' . $dbFile . '</div>';
    die();
}

// ── TEST 3 : Connexion MySQL ──
echo '<h2>3. Connexion MySQL</h2>';
try {
    $db = getDB();
    echo '<div class="ok">✅ Connexion MySQL reussie !</div>';
} catch (Exception $e) {
    echo '<div class="err">❌ Erreur MySQL : ' . $e->getMessage() . '</div>';
    die();
}

// ── TEST 4 : Base de donnees ──
echo '<h2>4. Base de donnees "smartschool"</h2>';
try {
    $tables = dbFetchAll("SHOW TABLES");
    if (empty($tables)) {
        echo '<div class="err">❌ La base "smartschool" est vide.<br>
        Importe le fichier database/smartschool.sql dans phpMyAdmin.</div>';
    } else {
        echo '<div class="ok">✅ Base de donnees OK — '
           . count($tables) . ' tables trouvees</div>';
        foreach ($tables as $t) {
            echo '<div class="ok" style="padding:6px 16px">📋 '
               . array_values($t)[0] . '</div>';
        }
    }
} catch (Exception $e) {
    echo '<div class="err">❌ Erreur : ' . $e->getMessage() . '</div>';
}

// ── TEST 5 : Utilisateurs ──
echo '<h2>5. Comptes utilisateurs</h2>';
try {
    $users = dbFetchAll("SELECT id, username, email, role_id FROM users");
    if (empty($users)) {
        echo '<div class="err">❌ Aucun utilisateur en base.<br>
        Importe le fichier SQL.</div>';
    } else {
        echo '<div class="ok">✅ ' . count($users) . ' utilisateurs trouves</div>';
        foreach ($users as $u) {
            echo '<div class="ok" style="padding:6px 16px">
                👤 ' . $u['username'] . ' — ' . $u['email'] . ' (role: ' . $u['role_id'] . ')
            </div>';
        }
    }
} catch (Exception $e) {
    echo '<div class="err">❌ Table users inexistante.<br>
    Importe le fichier SQL d\'abord.</div>';
}

// ── TEST 6 : Mot de passe ──
echo '<h2>6. Test mot de passe</h2>';
try {
    $admin = dbFetchOne(
        "SELECT password FROM users WHERE email = 'admin@smartschool.fr'"
    );
    if ($admin) {
        $testPwd = 'SmartSchool2025!';
        if (password_verify($testPwd, $admin['password'])) {
            echo '<div class="ok">✅ Mot de passe <strong>' . $testPwd . '</strong> correct !</div>';
        } else {
            echo '<div class="err">❌ Mot de passe incorrect.<br>
            Utilise le formulaire ci-dessous pour le corriger.</div>';

            // Formulaire de correction
            if (isset($_POST['fix'])) {
                $hash = password_hash($testPwd, PASSWORD_BCRYPT);
                dbExecute("UPDATE users SET password = ?", [$hash]);
                echo '<div class="ok">✅ Mot de passe mis a jour !
                Recharge la page pour verifier.</div>';
            } else {
                echo '<form method="POST" style="margin-top:10px">
                    <button name="fix" type="submit"
                        style="padding:10px 20px;background:#6366f1;color:#fff;
                               border:none;border-radius:6px;cursor:pointer;font-size:14px">
                        🔧 Corriger le mot de passe automatiquement
                    </button>
                </form>';
            }
        }
    } else {
        echo '<div class="err">❌ Compte admin introuvable.</div>';
    }
} catch (Exception $e) {
    echo '<div class="err">❌ ' . $e->getMessage() . '</div>';
}

// ── RESULTAT FINAL ──
echo '<h2>7. Resultat</h2>';
echo '<div class="ok" style="font-size:16px;font-weight:bold">
    ✅ Si tous les tests sont verts, tu peux acceder a :<br><br>
    👉 <a href="http://localhost/SmartSchool/auth/login.php">
        http://localhost/SmartSchool/auth/login.php
    </a>
</div>';

echo '<p style="color:#999;font-size:13px;margin-top:20px">
    ⚠️ Supprime ce fichier test.php apres validation.
</p>';
?>
