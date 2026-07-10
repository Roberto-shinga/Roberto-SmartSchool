<?php
// ============================================================
//  SmartSchool — Page de connexion
//  Emplacement : auth/login.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

// Deja connecte ? Rediriger vers son dashboard
if (isLoggedIn()) {
    redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL);
}

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($login) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $user = dbFetchOne(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE (u.email = ? OR u.username = ?)
             AND u.is_active = 1
             LIMIT 1",
            [$login, $login]
        );

        if (!$user || !verifyPassword($password, $user['password'])) {
            $error = 'Identifiants incorrects. Veuillez reessayer.';
            logActivity('login_failed', 'Tentative echouee : ' . $login);
        } else {
            loginUser($user);

            if ($remember) {
                setcookie(
                    'ss_remember',
                    generateToken(),
                    time() + (REMEMBER_DAYS * 86400),
                    '/', '', false, true
                );
            }

            $dest = ROLE_REDIRECTS[$user['role_id']] ?? BASE_URL;
            redirectWith($dest, 'success', 'Bienvenue ' . $user['first_name'] . ' !');
        }
    }
}

$schoolName = getSetting('school_name', APP_NAME);
$flash      = getFlash();
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — <?= clean($schoolName) ?></title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Boxicons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">

  <style>
    /* ── Layout login deux colonnes ── */
    body {
      min-height : 100vh;
      display    : flex;
      background : var(--bg-body);
    }

    .login-page {
      display   : flex;
      width     : 100%;
      min-height: 100vh;
    }

    /* ── Colonne gauche : image + texte ── */
    .login-left {
      flex      : 1;
      position  : relative;
      overflow  : hidden;
      display   : none;
    }
    @media (min-width: 960px) { .login-left { display: block; } }

    .login-left img {
      width     : 100%;
      height    : 100%;
      object-fit: cover;
      display   : block;
    }

    .login-left-overlay {
      position      : absolute;
      inset         : 0;
      background    : linear-gradient(
        155deg,
        rgba(79, 70, 229, 0.92) 0%,
        rgba(99, 102, 241, 0.80) 40%,
        rgba(139, 92, 246, 0.72) 100%
      );
      display        : flex;
      flex-direction : column;
      justify-content: space-between;
      padding        : 40px;
    }

    .login-brand {
      display    : flex;
      align-items: center;
      gap        : 14px;
    }
    .login-brand-icon {
      width          : 46px;
      height         : 46px;
      background     : rgba(255,255,255,0.18);
      border         : 1px solid rgba(255,255,255,0.30);
      border-radius  : 12px;
      display        : flex;
      align-items    : center;
      justify-content: center;
      font-size      : 1.5rem;
      color          : #fff;
    }
    .login-brand-name { font-size: 1.4rem; font-weight: 800; color: #fff; }
    .login-brand-sub  { font-size: 0.78rem; color: rgba(255,255,255,0.72); margin-top: 2px; }

    .login-hero { color: #fff; }
    .login-hero h2 {
      font-size    : 2rem;
      font-weight  : 800;
      line-height  : 1.3;
      margin-bottom: 14px;
      color        : #fff;
    }
    .login-hero p {
      font-size  : 0.95rem;
      color      : rgba(255,255,255,0.80);
      line-height: 1.7;
      max-width  : 380px;
    }

    .login-stats {
      display  : flex;
      gap      : 32px;
      margin-top: 32px;
    }
    .login-stat-value { font-size: 1.8rem; font-weight: 800; color: #fff; line-height: 1; }
    .login-stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.70); margin-top: 4px; }

    /* Badges roles sur le panneau gauche */
    .login-roles {
      display  : flex;
      flex-wrap: wrap;
      gap      : 8px;
      margin-top: 28px;
    }
    .login-role-chip {
      display    : flex;
      align-items: center;
      gap        : 6px;
      padding    : 6px 12px;
      background : rgba(255,255,255,0.14);
      border     : 1px solid rgba(255,255,255,0.22);
      border-radius: 99px;
      font-size  : 12px;
      font-weight: 600;
      color      : #fff;
    }

    .login-left-footer {
      font-size: 12px;
      color    : rgba(255,255,255,0.50);
    }

    /* ── Colonne droite : formulaire ── */
    .login-right {
      width          : 100%;
      max-width      : 480px;
      background     : var(--bg-card);
      display        : flex;
      flex-direction : column;
      justify-content: center;
      padding        : 40px 44px;
      overflow-y     : auto;
      min-height     : 100vh;
    }
    @media (max-width: 960px) { .login-right { max-width: 100%; padding: 32px 24px; } }
    @media (max-width: 480px) { .login-right { padding: 24px 18px; } }

    /* Brand visible uniquement sur mobile */
    .mobile-brand {
      display      : flex;
      align-items  : center;
      gap          : 12px;
      margin-bottom: 32px;
    }
    @media (min-width: 960px) { .mobile-brand { display: none; } }

    .mobile-brand-icon {
      width          : 40px;
      height         : 40px;
      background     : var(--grad-primary);
      border-radius  : 10px;
      display        : flex;
      align-items    : center;
      justify-content: center;
      color          : #fff;
      font-size      : 1.2rem;
      box-shadow     : var(--shadow-primary);
    }
    .mobile-brand-name { font-size: 1.2rem; font-weight: 800; color: var(--primary); }

    .login-title {
      font-size    : 1.65rem;
      font-weight  : 800;
      color        : var(--text-primary);
      margin-bottom: 6px;
    }
    .login-subtitle {
      font-size    : 13.5px;
      color        : var(--text-muted);
      margin-bottom: 28px;
    }

    /* Comptes demo */
    .demo-section {
      background   : var(--bg-body);
      border       : 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding      : 16px;
      margin-bottom: 24px;
    }
    body.dark-mode .demo-section { background: rgba(99,102,241,0.06); }

    .demo-title {
      font-size     : 11px;
      font-weight   : 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color         : var(--text-muted);
      margin-bottom : 12px;
      display       : flex;
      align-items   : center;
      gap           : 6px;
    }

    .demo-grid {
      display              : grid;
      grid-template-columns: repeat(2, 1fr);
      gap                  : 7px;
    }
    @media (max-width: 380px) { .demo-grid { grid-template-columns: 1fr; } }

    .demo-btn {
      display      : flex;
      align-items  : center;
      gap          : 9px;
      padding      : 8px 10px;
      background   : var(--bg-card);
      border       : 1px solid var(--border);
      border-radius: var(--radius);
      cursor       : pointer;
      transition   : all var(--transition);
      text-align   : left;
      width        : 100%;
    }
    body.dark-mode .demo-btn { background: rgba(255,255,255,0.04); }
    .demo-btn:hover { border-color: var(--primary); background: var(--primary-bg); }

    .demo-btn-icon {
      width          : 30px;
      height         : 30px;
      border-radius  : var(--radius-sm);
      display        : flex;
      align-items    : center;
      justify-content: center;
      font-size      : 0.95rem;
      color          : #fff;
      flex-shrink    : 0;
    }
    .demo-btn-name  { font-size: 12px; font-weight: 700; color: var(--text-primary); display: block; line-height: 1.2; }
    .demo-btn-email { font-size: 10.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }

    /* Separateur */
    .or-divider {
      display    : flex;
      align-items: center;
      gap        : 12px;
      font-size  : 12px;
      color      : var(--text-muted);
      margin     : 20px 0;
    }
    .or-divider::before,
    .or-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    /* Lien mot de passe */
    .forgot-link {
      font-size : 12.5px;
      color     : var(--primary);
      font-weight: 600;
    }
    .forgot-link:hover { text-decoration: underline; }

    /* Bouton connexion */
    .btn-login {
      width          : 100%;
      padding        : 12px;
      background     : var(--grad-primary);
      color          : #fff;
      border         : none;
      border-radius  : var(--radius);
      font-size      : 15px;
      font-weight    : 700;
      font-family    : var(--font);
      cursor         : pointer;
      display        : flex;
      align-items    : center;
      justify-content: center;
      gap            : 9px;
      box-shadow     : var(--shadow-primary);
      transition     : all var(--transition-md);
      margin-top     : 6px;
    }
    .btn-login:hover    { filter: brightness(1.07); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.42); }
    .btn-login:active   { transform: translateY(0); }
    .btn-login:disabled { opacity: 0.75; cursor: not-allowed; transform: none; }

    /* Pied formulaire */
    .login-footer {
      text-align: center;
      margin-top: 28px;
      font-size : 12px;
      color     : var(--text-muted);
    }
    .login-footer p + p { margin-top: 5px; }
    .login-footer strong { color: var(--primary); }

    /* Bouton dark mode */
    .dark-btn {
      position       : fixed;
      top            : 16px;
      right          : 16px;
      width          : 38px;
      height         : 38px;
      border-radius  : 50%;
      background     : var(--bg-card);
      border         : 1px solid var(--border);
      display        : flex;
      align-items    : center;
      justify-content: center;
      color          : var(--text-muted);
      font-size      : 1.1rem;
      cursor         : pointer;
      box-shadow     : var(--shadow-sm);
      transition     : all var(--transition);
      z-index        : 999;
    }
    .dark-btn:hover { color: var(--primary); border-color: var(--primary); }
  </style>
</head>

<body>

<!-- Bouton dark mode -->
<button class="dark-btn" id="darkBtn" title="Mode sombre">
  <i class="bx <?= isDarkMode() ? 'bx-sun' : 'bx-moon' ?>"></i>
</button>

<div class="login-page">

  <!-- ══ GAUCHE : IMAGE + TEXTE ══ -->
  <div class="login-left">
    <img
      src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1200&q=80&auto=format&fit=crop"
      alt=""
    >
    <div class="login-left-overlay">

      <!-- Logo -->
      <div class="login-brand">
        <div class="login-brand-icon">
          <i class="bx bx-buildings"></i>
        </div>
        <div>
          <div class="login-brand-name"><?= clean($schoolName) ?></div>
          <div class="login-brand-sub">Systeme de gestion scolaire</div>
        </div>
      </div>

      <!-- Texte central -->
      <div class="login-hero">
        <h2>Gerez votre ecole<br>en toute simplicite.</h2>
        <p>
          Plateforme complete pour administrer eleves, enseignants,
          notes, presences et finances — en un seul endroit.
        </p>

        <!-- Stats -->
        <div class="login-stats">
          <div>
            <div class="login-stat-value">500+</div>
            <div class="login-stat-label">Eleves geres</div>
          </div>
          <div>
            <div class="login-stat-value">6</div>
            <div class="login-stat-label">Roles securises</div>
          </div>
          <div>
            <div class="login-stat-value">100%</div>
            <div class="login-stat-label">Responsive</div>
          </div>
        </div>

        <!-- Chips roles -->
        <div class="login-roles">
          <div class="login-role-chip"><i class="bx bx-shield-alt-2"></i> Super Admin</div>
          <div class="login-role-chip"><i class="bx bx-user-check"></i> Admin</div>
          <div class="login-role-chip"><i class="bx bx-chalkboard"></i> Enseignant</div>
          <div class="login-role-chip"><i class="bx bx-graduation"></i> Eleve</div>
          <div class="login-role-chip"><i class="bx bx-group"></i> Parent</div>
          <div class="login-role-chip"><i class="bx bx-calculator"></i> Comptable</div>
        </div>
      </div>

      <div class="login-left-footer">
        &copy; <?= APP_YEAR ?> <?= clean($schoolName) ?> — Tous droits reserves
      </div>

    </div>
  </div>

  <!-- ══ DROITE : FORMULAIRE ══ -->
  <div class="login-right">

    <!-- Brand mobile -->
    <div class="mobile-brand">
      <div class="mobile-brand-icon"><i class="bx bx-buildings"></i></div>
      <span class="mobile-brand-name"><?= clean($schoolName) ?></span>
    </div>

    <h1 class="login-title">Bienvenue !</h1>
    <p class="login-subtitle">Connectez-vous a votre espace personnel</p>

    <!-- Flash message -->
    <?php if ($flash): ?>
      <div class="alert alert-<?= clean($flash['type']) ?>">
        <i class="bx bx-<?= $flash['type'] === 'success' ? 'check-circle' : 'error' ?>"></i>
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Erreur -->
    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bx bx-x-circle"></i>
        <?= clean($error) ?>
      </div>
    <?php endif; ?>

    <!-- Comptes demo -->
    <div class="demo-section">
      <div class="demo-title">
        <i class="bx bx-info-circle"></i>
        Comptes de demonstration — cliquez pour remplir
      </div>
      <div class="demo-grid">
        <?php
        $demos = [
          ['email'=>'admin@smartschool.fr',     'name'=>'Admin',       'color'=>'#6366f1','icon'=>'bx-user-check'],
          ['email'=>'martin@smartschool.fr',     'name'=>'Enseignant',  'color'=>'#0891b2','icon'=>'bx-chalkboard'],
          ['email'=>'eleve@smartschool.fr',      'name'=>'Eleve',       'color'=>'#059669','icon'=>'bx-graduation'],
          ['email'=>'parent@smartschool.fr',     'name'=>'Parent',      'color'=>'#d97706','icon'=>'bx-group'],
          ['email'=>'comptable@smartschool.fr',  'name'=>'Comptable',   'color'=>'#dc2626','icon'=>'bx-calculator'],
          ['email'=>'superadmin@smartschool.fr', 'name'=>'Super Admin', 'color'=>'#7c3aed','icon'=>'bx-shield-alt-2'],
        ];
        foreach ($demos as $d): ?>
        <button type="button" class="demo-btn"
                onclick="fillForm('<?= $d['email'] ?>')">
          <div class="demo-btn-icon" style="background:<?= $d['color'] ?>">
            <i class="bx <?= $d['icon'] ?>"></i>
          </div>
          <div style="min-width:0">
            <span class="demo-btn-name"><?= $d['name'] ?></span>
            <span class="demo-btn-email"><?= $d['email'] ?></span>
          </div>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="or-divider">ou connectez-vous manuellement</div>

    <!-- Formulaire -->
    <form method="POST" id="loginForm" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="login">
          Email ou nom d'utilisateur <span class="form-required">*</span>
        </label>
        <div class="input-wrap">
          <i class="bx bx-user input-icon"></i>
          <input
            type="text"
            id="login"
            name="login"
            class="form-control"
            placeholder="admin@smartschool.fr"
            value="<?= clean($_POST['login'] ?? '') ?>"
            autocomplete="username"
            required
          >
        </div>
      </div>

      <div class="form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px">
          <label class="form-label" for="password" style="margin:0">
            Mot de passe <span class="form-required">*</span>
          </label>
          <a href="<?= BASE_URL ?>/auth/forgot-password.php" class="forgot-link">
            Mot de passe oublie ?
          </a>
        </div>
        <div class="input-wrap">
          <i class="bx bx-lock-alt input-icon"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="form-control pr"
            placeholder="••••••••••"
            autocomplete="current-password"
            required
          >
          <span class="input-icon-right" id="togglePwd">
            <i class="bx bx-show" id="togglePwdIcon"></i>
          </span>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:20px">
        <label class="form-check">
          <input type="checkbox" name="remember" class="form-check-input">
          <span class="form-check-label">
            Se souvenir de moi (<?= REMEMBER_DAYS ?> jours)
          </span>
        </label>
      </div>

      <button type="submit" class="btn-login" id="submitBtn">
        <i class="bx bx-log-in"></i>
        Se connecter
      </button>
    </form>

    <div class="login-footer">
      <p>Mot de passe demo : <strong>SmartSchool2025!</strong></p>
      <p>&copy; <?= APP_YEAR ?> <?= clean($schoolName) ?></p>
    </div>

  </div>
</div>

<script>
  // Remplir le formulaire avec un compte demo
  function fillForm(email) {
    document.getElementById('login').value    = email;
    document.getElementById('password').value = 'SmartSchool2025!';
    // Feedback visuel
    event.currentTarget.style.borderColor = '#6366f1';
    setTimeout(() => { event.currentTarget.style.borderColor = ''; }, 500);
  }

  // Toggle affichage mot de passe
  document.getElementById('togglePwd').addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon  = document.getElementById('togglePwdIcon');
    if (input.type === 'password') {
      input.type     = 'text';
      icon.className = 'bx bx-hide';
    } else {
      input.type     = 'password';
      icon.className = 'bx bx-show';
    }
  });

  // Bouton dark mode
  document.getElementById('darkBtn').addEventListener('click', function () {
    const dark = document.body.classList.toggle('dark-mode');
    document.documentElement.classList.toggle('dark-mode', dark);
    document.getElementById('darkBtn').querySelector('i').className =
      dark ? 'bx bx-sun' : 'bx bx-moon';
    const exp = new Date(Date.now() + 365 * 86400000).toUTCString();
    document.cookie = 'ss_dark_mode=' + (dark ? '1' : '0')
                    + ';expires=' + exp + ';path=/';
  });

  // Spinner sur soumission
  document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled   = true;
    btn.innerHTML  = '<span style="width:15px;height:15px;border:2px solid rgba(255,255,255,0.35);'
                   + 'border-top-color:#fff;border-radius:50%;display:inline-block;'
                   + 'animation:spin 0.7s linear infinite"></span> Connexion...';
  });
</script>

</body>
</html>
