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

// ════════════════════════════════════════════════
//  ENVOI D'EMAILS REELS (PHPMailer / SMTP)
// ════════════════════════════════════════════════

// Habillage HTML commun a tous les emails envoyes par la plateforme,
// coherent avec la charte indigo/violet de SmartSchool.
function buildEmailHtml(string $preheader, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
{
    $schoolName = getSetting('school_name', APP_NAME);
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $cta = '<div style="text-align:center;margin:28px 0">
            <a href="' . htmlspecialchars($ctaUrl) . '" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
               text-decoration:none;padding:13px 28px;border-radius:10px;font-weight:700;font-size:14px;display:inline-block">
               ' . htmlspecialchars($ctaText) . '</a></div>';
    }
    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f5f9;font-family:Segoe UI,Arial,sans-serif">
        <span style="display:none;max-height:0;overflow:hidden">' . htmlspecialchars($preheader) . '</span>
        <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
        <tr><td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(15,23,42,.08)">
          <tr><td style="background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:24px 32px">
            <span style="color:#fff;font-size:18px;font-weight:800">' . htmlspecialchars($schoolName) . '</span>
          </td></tr>
          <tr><td style="padding:32px">
            <div style="color:#334155;font-size:14px;line-height:1.7">' . $bodyHtml . '</div>
            ' . $cta . '
          </td></tr>
          <tr><td style="padding:18px 32px;background:#f8fafc;border-top:1px solid #e2e8f0">
            <span style="color:#94a3b8;font-size:11.5px">Cet email a ete envoye automatiquement par ' . htmlspecialchars($schoolName) . '. Merci de ne pas y repondre directement.</span>
          </td></tr>
        </table>
        </td></tr>
        </table>
        </body></html>';
}

// Envoie un email reel via SMTP (PHPMailer). Si SMTP_HOST n'est pas
// configure (config/mail.php vide), ne tente rien et retourne un
// echec "not_configured" — laisse l'appelant gerer un repli (ex:
// afficher le code/lien a l'ecran en mode developpement).
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
{
    if (empty(SMTP_HOST)) {
        return ['success' => false, 'error' => 'not_configured'];
    }
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'invalid_email'];
    }

    require_once 'C:/xampp/htdocs/SmartSchool/libraries/phpmailer/src/Exception.php';
    require_once 'C:/xampp/htdocs/SmartSchool/libraries/phpmailer/src/PHPMailer.php';
    require_once 'C:/xampp/htdocs/SmartSchool/libraries/phpmailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $debugLog = '';
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Diagnostic temporaire : capture le dialogue SMTP complet pour
        // comprendre precisement pourquoi l'authentification echoue.
        // A retirer une fois le probleme resolu (voir SMTP_DEBUG plus bas).
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $mail->SMTPDebug   = 2;
            $mail->Debugoutput = function ($str, $level) use (&$debugLog) {
                $debugLog .= trim($str) . ' | ';
            };
        }

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $detail = $debugLog ?: $mail->ErrorInfo;
        logActivity('mail_send_failed', "Echec envoi vers $toEmail : " . $mail->ErrorInfo . ($debugLog ? " || DEBUG: $detail" : ''));
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}



// ════════════════════════════════════════════════
//  INVITATIONS (enseignants, comptables)
// ════════════════════════════════════════════════

// Cree une invitation pour un utilisateur DEJA cree (profil pre-rempli
// par l'administrateur). Invalide automatiquement toute invitation
// encore en attente pour ce meme utilisateur (jamais deux tokens actifs).
// Retourne le token EN CLAIR (a inserer dans le lien envoye par email —
// seul son hash est conserve en base).
function createInvitation(int $userId, int $roleId, int $invitedBy, int $hours = 48): string
{
    dbExecute(
        "UPDATE invitations SET status='cancelled', cancelled_at=NOW() WHERE user_id=? AND status='pending'",
        [$userId]
    );

    $token = bin2hex(random_bytes(32));
    dbExecute(
        "INSERT INTO invitations (user_id, role_id, token_hash, invited_by, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))",
        [$userId, $roleId, hash('sha256', $token), $invitedBy, $hours]
    );

    return $token;
}

// Envoie l'email d'invitation. Meme limite que le 2FA : sans SMTP
// configure sur XAMPP, mail() echoue souvent silencieusement.
function sendInvitationEmail(string $toEmail, string $firstName, string $roleLabel, string $token, int $hours = 48): bool
{
    if (empty($toEmail)) return false;

    $link       = BASE_URL . '/auth/activate-account.php?token=' . $token;
    $schoolName = getSetting('school_name', APP_NAME);

    $body = "<p>Bonjour <strong>" . htmlspecialchars($firstName) . "</strong>,</p>
        <p>Vous avez ete invite(e) a rejoindre <strong>" . htmlspecialchars($schoolName) . "</strong> en tant que
        <strong>" . htmlspecialchars($roleLabel) . "</strong> sur la plateforme SmartSchool.</p>
        <p>Pour activer votre compte, cliquez sur le bouton ci-dessous et definissez votre propre mot de passe.</p>
        <p style='color:#94a3b8;font-size:12.5px'>Cette invitation expire dans $hours heures et ne peut etre utilisee qu'une seule fois.
        Si vous n'etes pas a l'origine de cette demande, ignorez simplement ce message.</p>";

    $result = sendMail(
        $toEmail, $firstName,
        '[' . APP_NAME . '] Invitation a rejoindre ' . $schoolName,
        buildEmailHtml('Vous avez ete invite(e) a rejoindre ' . $schoolName, $body, 'Activer mon compte', $link)
    );

    if (!$result['success'] && $result['error'] !== 'not_configured') {
        logActivity('invitation_mail_failed', "Envoi invitation echoue vers $toEmail : " . $result['error']);
    }
    return $result['success'];
}

// Recherche une invitation par token en clair (le hash est recalcule
// pour la comparaison — le token en clair n'est jamais stocke).
// Marque automatiquement comme 'expired' si la date est depassee.
function findInvitationByToken(string $token): ?array
{
    $hash = hash('sha256', $token);
    $inv  = dbFetchOne(
        "SELECT i.*, u.first_name, u.last_name, u.email, u.username, u.is_active
         FROM invitations i JOIN users u ON i.user_id = u.id
         WHERE i.token_hash = ? LIMIT 1",
        [$hash]
    );

    if ($inv && $inv['status'] === 'pending' && strtotime($inv['expires_at']) < time()) {
        dbExecute("UPDATE invitations SET status='expired' WHERE id=?", [$inv['id']]);
        $inv['status'] = 'expired';
    }
    return $inv ?: null;
}

function cancelInvitation(int $invitationId): void
{
    dbExecute(
        "UPDATE invitations SET status='cancelled', cancelled_at=NOW() WHERE id=? AND status='pending'",
        [$invitationId]
    );
}

// ════════════════════════════════════════════════
//  REINITIALISATION DE MOT DE PASSE
// ════════════════════════════════════════════════

// Cree une demande de reinitialisation et invalide toute demande encore
// en attente pour ce meme utilisateur. Retourne le token EN CLAIR (seul
// son hash est conserve en base — meme principe que les invitations).
function createPasswordReset(int $userId, int $hours = 1): string
{
    dbExecute("UPDATE password_resets SET status='expired' WHERE user_id=? AND status='pending'", [$userId]);

    $token = bin2hex(random_bytes(32));
    dbExecute(
        "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))",
        [$userId, hash('sha256', $token), $hours]
    );
    return $token;
}

function sendPasswordResetEmail(string $toEmail, string $firstName, string $token): bool
{
    if (empty($toEmail)) return false;

    $link = BASE_URL . '/auth/reset-password.php?token=' . $token;
    $body = "<p>Bonjour <strong>" . htmlspecialchars($firstName) . "</strong>,</p>
        <p>Une demande de reinitialisation de mot de passe a ete faite pour ton compte SmartSchool.</p>
        <p>Clique sur le bouton ci-dessous pour definir un nouveau mot de passe :</p>
        <p style='color:#94a3b8;font-size:12.5px'>Ce lien expire dans 1 heure et ne peut etre utilise qu'une seule fois.
        Si tu n'es pas a l'origine de cette demande, ignore simplement ce message : ton mot de passe actuel reste inchange.</p>";

    $result = sendMail($toEmail, $firstName, '[' . APP_NAME . '] Reinitialisation de mot de passe',
        buildEmailHtml('Reinitialisation de ton mot de passe SmartSchool', $body, 'Definir un nouveau mot de passe', $link));

    if (!$result['success'] && $result['error'] !== 'not_configured') {
        logActivity('password_reset_mail_failed', "Envoi echoue vers $toEmail : " . $result['error']);
    }
    return $result['success'];
}

// Recherche une demande par token en clair. Marque automatiquement comme
// 'expired' si la date est depassee.
function findPasswordResetByToken(string $token): ?array
{
    $hash = hash('sha256', $token);
    $row  = dbFetchOne(
        "SELECT pr.*, u.first_name, u.last_name, u.email, u.username
         FROM password_resets pr JOIN users u ON pr.user_id = u.id
         WHERE pr.token_hash = ? LIMIT 1",
        [$hash]
    );
    if ($row && $row['status'] === 'pending' && strtotime($row['expires_at']) < time()) {
        dbExecute("UPDATE password_resets SET status='expired' WHERE id=?", [$row['id']]);
        $row['status'] = 'expired';
    }
    return $row ?: null;
}

// ════════════════════════════════════════════════
//  ENSEIGNANT — acces limite aux classes/matieres attribuees
// ════════════════════════════════════════════════

function requireTeacher(): void
{
    requireRole(ROLE_TEACHER);
}

// Recupere l'id de la fiche "teachers" liee a l'utilisateur connecte.
function getCurrentTeacherId(): ?int
{
    $user = currentUser();
    if (!$user) return null;
    $row = dbFetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user['id']]);
    return $row ? (int)$row['id'] : null;
}

// Toutes les affectations (classe x matiere) de l'enseignant pour une annee donnee.
function getTeacherAssignments(int $teacherId, int $yearId): array
{
    return dbFetchAll(
        "SELECT ta.id, ta.class_id, ta.subject_id, ta.hours_per_week,
                c.name AS class_name, s.name AS subject_name, s.color AS subject_color
         FROM teacher_assignments ta
         JOIN classes c  ON ta.class_id = c.id
         JOIN subjects s ON ta.subject_id = s.id
         WHERE ta.teacher_id = ? AND ta.academic_year_id = ?
         ORDER BY c.grade_year, c.section, s.name",
        [$teacherId, $yearId]
    );
}

// Classes distinctes enseignees par ce professeur (pour les selecteurs).
function getTeacherClasses(int $teacherId, int $yearId): array
{
    return dbFetchAll(
        "SELECT DISTINCT c.id, c.name
         FROM teacher_assignments ta JOIN classes c ON ta.class_id = c.id
         WHERE ta.teacher_id = ? AND ta.academic_year_id = ?
         ORDER BY c.grade_year, c.section",
        [$teacherId, $yearId]
    );
}

// GARDE-FOU SERVEUR : verifie qu'une affectation appartient bien a cet
// enseignant avant toute lecture/ecriture (notes, presences...).
// Ne JAMAIS faire confiance a un class_id/assignment_id venant du formulaire
// ou de l'URL sans repasser par cette verification.
function teacherOwnsAssignment(int $teacherId, int $assignmentId): bool
{
    return (bool)dbFetchOne(
        "SELECT 1 FROM teacher_assignments WHERE id = ? AND teacher_id = ?",
        [$assignmentId, $teacherId]
    );
}

function teacherOwnsClass(int $teacherId, int $classId, int $yearId): bool
{
    return (bool)dbFetchOne(
        "SELECT 1 FROM teacher_assignments WHERE teacher_id = ? AND class_id = ? AND academic_year_id = ?",
        [$teacherId, $classId, $yearId]
    );
}


// ════════════════════════════════════════════════
//  ELEVE — acces strict a ses propres donnees
// ════════════════════════════════════════════════

function requireStudent(): void
{
    requireRole(ROLE_STUDENT);
}

// Recupere l'id de la fiche "students" liee a l'utilisateur connecte.
// Un eleve ne fournit jamais lui-meme son student_id : il est toujours
// derive de sa session, jamais d'un parametre GET/POST.
function getCurrentStudentId(): ?int
{
    $user = currentUser();
    if (!$user) return null;
    $row = dbFetchOne("SELECT id FROM students WHERE user_id = ?", [$user['id']]);
    return $row ? (int)$row['id'] : null;
}

// ════════════════════════════════════════════════
//  SMARTSCHOOL LEARNING — attribution automatique des badges
// ════════════════════════════════════════════════

// Verifie les conditions de tous les badges pour cet eleve et attribue
// ceux qui viennent d'etre debloques. Retourne les badges nouvellement
// obtenus (pour affichage d'un message de felicitations).
// A appeler apres : validation d'une lecon, soumission d'un quiz.
function checkLearningBadges(int $studentId): array
{
    $badges  = dbFetchAll("SELECT * FROM badges");
    $already = array_column(dbFetchAll("SELECT badge_id FROM student_badges WHERE student_id=?", [$studentId]), 'badge_id');
    $newly   = [];

    $nbLessonsDone = dbCount('lesson_progress', "student_id=? AND completed=1", [$studentId]);
    $nbCoursesTouched = dbFetchOne(
        "SELECT COUNT(DISTINCT le.course_id) c FROM lesson_progress lp
         JOIN lessons le ON lp.lesson_id = le.id WHERE lp.student_id=? AND lp.completed=1",
        [$studentId]
    )['c'] ?? 0;
    $nbQuizzesPassed = dbCount('quiz_attempts', "student_id=? AND passed=1", [$studentId]);
    $hasPerfectScore = dbFetchOne(
        "SELECT 1 FROM quiz_attempts WHERE student_id=? AND total_points > 0 AND score = total_points LIMIT 1",
        [$studentId]
    );

    $conditions = [
        // nom du badge => condition remplie ?
        'Premier pas' => $nbQuizzesPassed >= 1,
        'Assidu'      => $nbCoursesTouched >= 5,
        'Expert'      => (bool)$hasPerfectScore,
        'Curieux'     => $nbLessonsDone >= 1,
        'Champion'    => $nbQuizzesPassed >= 10,
    ];

    foreach ($badges as $b) {
        if (in_array($b['id'], $already)) continue;
        if (!empty($conditions[$b['name']])) {
            dbExecute("INSERT INTO student_badges (student_id, badge_id) VALUES (?, ?)", [$studentId, $b['id']]);
            logActivity('badge_earned', $b['name'] . " (eleve #$studentId)");
            $newly[] = $b;
        }
    }
    return $newly;
}

// ════════════════════════════════════════════════
//  PARENT — acces strict aux enfants lies (parent_student)
// ════════════════════════════════════════════════

function requireParent(): void
{
    requireRole(ROLE_PARENT);
}

// Tous les enfants lies au parent connecte, avec infos utiles pour les
// selecteurs (nom, classe, matricule).
function getParentChildren(int $parentUserId): array
{
    return dbFetchAll(
        "SELECT s.id, s.student_number, s.class_id,
                COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
                c.name AS class_name, ps.relationship, ps.is_primary
         FROM parent_student ps
         JOIN students s ON ps.student_id = s.id
         LEFT JOIN users u ON s.user_id = u.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE ps.parent_id = ?
         ORDER BY fn, ln",
        [$parentUserId]
    );
}

// GARDE-FOU SERVEUR : verifie qu'un eleve est bien un enfant de ce parent
// avant toute lecture (notes, presences, paiements...). A appeler des
// qu'un student_id provient d'un parametre GET/POST.
function parentOwnsStudent(int $parentUserId, int $studentId): bool
{
    return (bool)dbFetchOne(
        "SELECT 1 FROM parent_student WHERE parent_id = ? AND student_id = ?",
        [$parentUserId, $studentId]
    );
}


// ════════════════════════════════════════════════

// Acces reserve au Super Admin, avec verification 2FA obligatoire.
// A utiliser en tete de TOUTES les pages de /superadmin.
function requireSuperAdmin(): void
{
    requireRole(ROLE_SUPER_ADMIN);
    if (empty($_SESSION['superadmin_2fa_ok'])) {
        redirectWith(BASE_URL . '/auth/login.php', 'warning',
            'Verification de securite requise. Veuillez vous reconnecter.');
    }
}

// Empeche un utilisateur (y compris Super Admin) de modifier son propre
// role. Ne jamais faire confiance au formulaire pour cette verification.
function preventSelfRoleChange(int $targetUserId): void
{
    $current = currentUser();
    if ($current && (int)$current['id'] === $targetUserId) {
        http_response_code(403);
        die('Action interdite : vous ne pouvez pas modifier votre propre role.');
    }
}

// Genere un code a 6 chiffres, le stocke hache en base (purpose='login'),
// et tente de l'envoyer par email. Retourne le code en clair UNIQUEMENT
// pour l'affichage en mode developpement (voir verify-2fa.php).
function generate2FACode(int $userId, string $purpose = 'login'): string
{
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    dbExecute(
        "INSERT INTO two_factor_codes (user_id, code_hash, purpose, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))",
        [$userId, password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]), $purpose, SUPERADMIN_2FA_TTL]
    );

    return $code;
}

// Envoie le code par email (envoi reel si SMTP configure dans
// config/mail.php, sinon echec "not_configured" gere par l'appelant
// via le bandeau de mode developpement).
function send2FACodeEmail(string $toEmail, string $firstName, string $code): bool
{
    if (empty($toEmail)) return false;

    $minutes = (int)(SUPERADMIN_2FA_TTL / 60);
    $body = "<p>Bonjour <strong>" . htmlspecialchars($firstName) . "</strong>,</p>
        <p>Voici votre code de verification pour vous connecter en tant que <strong>Super Administrateur</strong> :</p>
        <div style='text-align:center;margin:24px 0'>
          <span style='display:inline-block;background:#f4f5f9;border:1.5px dashed #c7c9f5;border-radius:10px;
                       padding:14px 28px;font-size:28px;font-weight:800;letter-spacing:8px;color:#4f46e5;font-family:monospace'>$code</span>
        </div>
        <p>Ce code expire dans <strong>$minutes minutes</strong> et ne peut etre utilise qu'une seule fois.</p>
        <p style='color:#94a3b8;font-size:12.5px'>Si vous n'etes pas a l'origine de cette demande, ignorez ce message.</p>";

    $result = sendMail($toEmail, $firstName, '[' . APP_NAME . '] Code de verification', buildEmailHtml('Votre code de verification SmartSchool', $body));

    if (!$result['success'] && $result['error'] !== 'not_configured') {
        logActivity('superadmin_2fa_mail_failed', "Envoi email 2FA echoue vers $toEmail : " . $result['error']);
    }
    return $result['success'];
}

// Verifie le code saisi pour l'utilisateur donne. Gere les tentatives
// et l'expiration. Retourne un tableau ['ok' => bool, 'error' => ?string].
function verify2FACode(int $userId, string $inputCode, string $purpose = 'login'): array
{
    $row = dbFetchOne(
        "SELECT * FROM two_factor_codes
         WHERE user_id = ? AND purpose = ? AND used = 0
         ORDER BY created_at DESC LIMIT 1",
        [$userId, $purpose]
    );

    if (!$row) {
        return ['ok' => false, 'error' => 'Aucun code actif. Demandez-en un nouveau.'];
    }
    if (strtotime($row['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'Ce code a expire. Demandez-en un nouveau.'];
    }
    if ($row['attempts'] >= SUPERADMIN_2FA_MAX_ATTEMPTS) {
        return ['ok' => false, 'error' => 'Trop de tentatives. Demandez un nouveau code.'];
    }

    dbExecute("UPDATE two_factor_codes SET attempts = attempts + 1 WHERE id = ?", [$row['id']]);

    if (!password_verify($inputCode, $row['code_hash'])) {
        return ['ok' => false, 'error' => 'Code incorrect.'];
    }

    dbExecute("UPDATE two_factor_codes SET used = 1 WHERE id = ?", [$row['id']]);
    return ['ok' => true, 'error' => null];
}

// ════════════════════════════════════════════════
//  ASSISTANT DE CONFIGURATION INITIALE
// ════════════════════════════════════════════════

function isSetupCompleted(): bool
{
    $row = dbFetchOne("SELECT setup_completed FROM system_setup ORDER BY id LIMIT 1");
    return !empty($row['setup_completed']);
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

// Matricule generique pour un role sans table de profil dediee
// (ex : Comptable). Format : PREFIXE-ANNEE-000n, ex COMPT-2026-001.
function generateStaffNumber(int $roleId): string
{
    $prefixes = [ROLE_ACCOUNTANT => 'COMPT'];
    $prefix   = $prefixes[$roleId] ?? 'STAFF';
    $year     = date('Y');
    $count    = dbFetchOne(
        "SELECT COUNT(*) c FROM users WHERE role_id = ? AND YEAR(created_at) = ?",
        [$roleId, $year]
    )['c'] ?? 0;
    return $prefix . '-' . $year . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
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
