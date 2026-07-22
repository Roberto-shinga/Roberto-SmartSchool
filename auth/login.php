<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

if (isLoggedIn()) redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL);

$error  = '';
$tab    = trim($_GET['tab'] ?? 'email');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $tab      = trim($_POST['tab']      ?? 'email');
    $password = trim($_POST['password'] ?? '');

    // ── Connexion EMAIL ──────────────────────────────────
    if ($tab === 'email') {
        $login = trim($_POST['login'] ?? '');
        if (empty($login) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $user = dbFetchOne(
                "SELECT u.*, r.name AS role_name FROM users u
                 JOIN roles r ON u.role_id = r.id
                 WHERE (u.email=? OR u.username=?) AND u.is_active=1 LIMIT 1",
                [$login, $login]
            );
            if (!$user || !verifyPassword($password, $user['password'])) {
                $error = 'Identifiants incorrects.';
                logActivity('login_failed', 'Echec email : ' . $login);
            } else {
                loginUser($user);
                if (!empty($_POST['remember'])) {
                    setcookie('ss_remember', generateToken(), time() + REMEMBER_DAYS * 86400, '/', '', false, true);
                }
                if ($user['must_change_password']) {
                    $_SESSION['force_pwd_change'] = true;
                    redirectWith(BASE_URL . '/auth/change-password.php', 'warning',
                        'Veuillez definir votre mot de passe personnel avant de continuer.');
                }
                redirectWith(ROLE_REDIRECTS[$user['role_id']] ?? BASE_URL, 'success',
                    'Bienvenue ' . $user['first_name'] . ' !');
            }
        }
    }

    // ── Connexion MATRICULE (eleves 7e+) ─────────────────
    if ($tab === 'matricule') {
        $matricule = strtoupper(trim($_POST['matricule'] ?? ''));
        if (empty($matricule) || empty($password)) {
            $error = 'Matricule et mot de passe obligatoires.';
        } else {
            $student = dbFetchOne(
                "SELECT s.id AS student_id, s.first_login, u.id, u.first_name, u.last_name,
                        u.password, u.role_id, u.is_active, u.must_change_password
                 FROM students s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.student_number=? AND s.has_account=1 AND u.is_active=1 LIMIT 1",
                [$matricule]
            );
            if (!$student || !verifyPassword($password, $student['password'])) {
                $error = 'Matricule ou mot de passe incorrect.';
                logActivity('login_failed', 'Echec matricule : ' . $matricule);
            } else {
                $userRow = dbFetchOne("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=?", [$student['id']]);
                loginUser($userRow);
                if (!empty($_POST['remember'])) {
                    setcookie('ss_remember', generateToken(), time() + REMEMBER_DAYS * 86400, '/', '', false, true);
                }
                // Premiere connexion
                if ($student['first_login'] || $student['must_change_password']) {
                    dbExecute("UPDATE students SET first_login=0 WHERE id=?", [$student['student_id']]);
                    $_SESSION['force_pwd_change'] = true;
                    redirectWith(BASE_URL . '/auth/change-password.php', 'info',
                        'Premiere connexion : definissez votre mot de passe personnel.');
                }
                redirectWith(BASE_URL . '/students/index.php', 'success',
                    'Bienvenue ' . $student['first_name'] . ' !');
            }
        }
    }
}

$schoolName  = getSetting('school_name', APP_NAME);
$flash       = getFlash();
$googleUrl   = buildGoogleAuthUrl();
$nbStudents  = dbFetchOne("SELECT COUNT(*) c FROM students WHERE status='actif'")['c'] ?? 0;
$nbTeachers  = dbFetchOne("SELECT COUNT(*) c FROM teachers WHERE status='actif'")['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — <?= clean($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">
  <style>
    body{min-height:100vh;display:flex;background:var(--bg-body);}
    .auth-page{display:flex;width:100%;min-height:100vh;}
    .auth-left{flex:1;display:none;position:relative;overflow:hidden;}
    @media(min-width:960px){.auth-left{display:block;}}
    .auth-img{width:100%;height:100%;object-fit:cover;display:block;}
    .auth-overlay{position:absolute;inset:0;background:linear-gradient(155deg,rgba(79,70,229,0.92),rgba(99,102,241,0.80),rgba(139,92,246,0.75));display:flex;flex-direction:column;justify-content:space-between;padding:44px;}
    .bubble{position:absolute;border-radius:50%;background:rgba(255,255,255,0.07);}
    .b1{width:220px;height:220px;top:-60px;right:-60px;animation:float 7s ease-in-out infinite;}
    .b2{width:140px;height:140px;bottom:100px;left:20px;animation:float 7s 2.5s ease-in-out infinite;}
    .b3{width:90px;height:90px;top:38%;right:80px;animation:float 7s 5s ease-in-out infinite;}
    @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-18px);}}
    .a-brand{display:flex;align-items:center;gap:16px;position:relative;z-index:1;}
    .a-brand-icon{width:52px;height:52px;background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.28);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;}
    .a-brand-name{font-size:1.5rem;font-weight:800;color:#fff;}
    .a-brand-sub{font-size:0.73rem;color:rgba(255,255,255,0.64);}
    .a-hero{position:relative;z-index:1;}
    .a-hero h2{font-size:2.3rem;font-weight:800;color:#fff;line-height:1.25;margin-bottom:14px;}
    .a-hero p{font-size:0.95rem;color:rgba(255,255,255,0.76);line-height:1.7;max-width:380px;margin-bottom:22px;}
    .a-stats{display:flex;gap:32px;}
    .a-stat-v{font-size:1.9rem;font-weight:800;color:#fff;line-height:1;}
    .a-stat-l{font-size:0.72rem;color:rgba(255,255,255,0.64);margin-top:3px;}
    .a-foot{font-size:12px;color:rgba(255,255,255,0.40);position:relative;z-index:1;}
    .auth-right{width:100%;max-width:490px;background:var(--bg-card);display:flex;flex-direction:column;justify-content:center;padding:44px 50px;overflow-y:auto;min-height:100vh;}
    @media(max-width:960px){.auth-right{max-width:100%;padding:32px 24px;}}
    @media(max-width:480px){.auth-right{padding:24px 16px;}}
    .m-brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
    @media(min-width:960px){.m-brand{display:none;}}
    .m-brand-icon{width:42px;height:42px;background:var(--grad-primary);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;box-shadow:var(--shadow-primary);}
    .btn-google{width:100%;padding:11px 16px;background:var(--bg-card);color:var(--text-primary);border:1.5px solid var(--border);border-radius:var(--radius);font-size:14px;font-weight:600;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all var(--transition);box-shadow:var(--shadow-sm);text-decoration:none;}
    .btn-google:hover{border-color:#4285F4;box-shadow:0 4px 14px rgba(66,133,244,0.18);transform:translateY(-1px);}
    .divider{display:flex;align-items:center;gap:12px;font-size:12px;color:var(--text-muted);margin:16px 0;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .login-tabs{display:flex;border-bottom:2px solid var(--border-light);margin-bottom:22px;}
    .ltab{flex:1;padding:10px;text-align:center;font-size:13px;font-weight:600;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;transition:all var(--transition);background:none;font-family:var(--font);}
    .ltab:hover{color:var(--primary);}
    .ltab.active{color:var(--primary);border-bottom-color:var(--primary);}
    .ltab i{margin-right:5px;}
    .btn-auth{width:100%;padding:13px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);transition:all var(--transition-md);}
    .btn-auth:hover{filter:brightness(1.07);transform:translateY(-1px);}
    .btn-auth:disabled{opacity:0.75;cursor:not-allowed;transform:none;}
    .hint-box{background:var(--primary-bg);border:1px solid rgba(79,70,229,0.25);border-radius:var(--radius);padding:12px 14px;margin-bottom:16px;font-size:12.5px;color:var(--primary-dark);display:flex;align-items:flex-start;gap:9px;}
    .hint-box i{flex-shrink:0;margin-top:1px;}
    .dark-btn{position:fixed;top:16px;right:16px;width:36px;height:36px;border-radius:50%;background:var(--bg-card);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:1.1rem;cursor:pointer;z-index:999;box-shadow:var(--shadow-sm);transition:all var(--transition);}
    .dark-btn:hover{color:var(--primary);border-color:var(--primary);}
    @keyframes slideUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
    @keyframes spin{to{transform:rotate(360deg);}}
    .au{animation:slideUp 0.35s ease forwards;}
    .d1{animation-delay:.05s;opacity:0;} .d2{animation-delay:.10s;opacity:0;} .d3{animation-delay:.15s;opacity:0;}
  </style>
</head>
<body>

<button class="dark-btn" id="darkBtn"><i class="bx <?= isDarkMode()?'bx-sun':'bx-moon' ?>"></i></button>

<div class="auth-page">
  <!-- GAUCHE -->
  <div class="auth-left">
    <?php if (file_exists('C:/xampp/htdocs/SmartSchool/assets/images/school-bg.jpg')): ?>
      <img class="auth-img" src="<?= ASSETS_URL ?>/images/school-bg.jpg" alt="Ecole">
    <?php else: ?>
      <div style="width:100%;height:100%;background:linear-gradient(135deg,#1e1b4b,#4f46e5,#7c3aed)"></div>
    <?php endif; ?>
    <div class="auth-overlay">
      <div class="bubble b1"></div><div class="bubble b2"></div><div class="bubble b3"></div>
      <div class="a-brand">
        <div class="a-brand-icon"><i class="bx bx-buildings"></i></div>
        <div><div class="a-brand-name"><?= clean($schoolName) ?></div><div class="a-brand-sub">Systeme intelligent de gestion scolaire — RDC</div></div>
      </div>
      <div class="a-hero">
        <h2>Bienvenue sur<br><?= clean($schoolName) ?>.</h2>
        <p>Plateforme complete de gestion scolaire — eleves, notes, presences, finances et apprentissage numerique.</p>
        <div class="a-stats">
          <div><div class="a-stat-v"><?= $nbStudents ?></div><div class="a-stat-l">Eleves inscrits</div></div>
          <div><div class="a-stat-v"><?= $nbTeachers ?></div><div class="a-stat-l">Enseignants</div></div>
          <div><div class="a-stat-v">6</div><div class="a-stat-l">Roles</div></div>
        </div>
      </div>
      <div class="a-foot">&copy; <?= APP_YEAR ?> <?= clean($schoolName) ?></div>
    </div>
  </div>

  <!-- DROITE -->
  <div class="auth-right">
    <div class="m-brand">
      <div class="m-brand-icon"><i class="bx bx-buildings"></i></div>
      <span style="font-size:1.2rem;font-weight:800;color:var(--primary)"><?= clean($schoolName) ?></span>
    </div>

    <div class="au"><h1 style="font-size:1.8rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Connexion</h1>
    <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:22px">Accedez a votre espace personnel</p></div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= clean($flash['type']) ?> au">
        <i class="bx bx-info-circle"></i> <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger au"><i class="bx bx-x-circle"></i> <?= clean($error) ?></div>
    <?php endif; ?>

    <!-- Google -->
    <?php if (!empty($googleUrl) && $tab === 'email'): ?>
    <div class="au d1">
      <a href="<?= clean($googleUrl) ?>" class="btn-google">
        <svg width="20" height="20" viewBox="0 0 24 24" style="flex-shrink:0">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Continuer avec Google
      </a>
    </div>
    <div class="divider au d1">ou avec vos identifiants</div>
    <?php endif; ?>

    <!-- Onglets -->
    <div class="login-tabs au d1">
      <button class="ltab <?= $tab==='email'?'active':'' ?>" onclick="switchTab('email')"><i class="bx bx-envelope"></i> Email</button>
      <button class="ltab <?= $tab==='matricule'?'active':'' ?>" onclick="switchTab('matricule')"><i class="bx bx-id-card"></i> Eleve (matricule)</button>
    </div>

    <!-- Formulaire EMAIL -->
    <div id="fEmail" style="<?= $tab!=='email'?'display:none':'' ?>">
      <form method="POST" data-loading>
        <?= csrfField() ?><input type="hidden" name="tab" value="email">
        <div class="form-group au d2">
          <label class="form-label">Email ou nom d'utilisateur <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-user input-icon"></i>
            <input type="text" name="login" class="form-control" placeholder="votre@email.fr" value="<?= clean($_POST['login'] ?? '') ?>" required autocomplete="username" autofocus>
          </div>
        </div>
        <div class="form-group au d3">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <label class="form-label" style="margin:0">Mot de passe <span class="form-required">*</span></label>
            <a href="<?= BASE_URL ?>/auth/forgot-password.php" style="font-size:12.5px;color:var(--primary);font-weight:600">Oublie ?</a>
          </div>
          <div class="input-wrap"><i class="bx bx-lock-alt input-icon"></i>
            <input type="password" name="password" id="p1" class="form-control pr" placeholder="••••••••" required autocomplete="current-password">
            <span class="input-icon-right" data-toggle-pwd="p1" style="cursor:pointer"><i class="bx bx-show" id="i1"></i></span>
          </div>
        </div>
        <label class="form-check" style="margin-bottom:18px"><input type="checkbox" name="remember" class="form-check-input"><span class="form-check-label" style="font-size:13px">Se souvenir (<?= REMEMBER_DAYS ?> jours)</span></label>
        <button type="submit" class="btn-auth"><i class="bx bx-log-in"></i> Se connecter</button>
      </form>
    </div>

    <!-- Formulaire MATRICULE -->
    <div id="fMatricule" style="<?= $tab!=='matricule'?'display:none':'' ?>">
      <div class="hint-box"><i class="bx bx-info-circle"></i>
        <div><strong>Pour les eleves (7e annee et plus)</strong><br>Utilisez votre matricule et le mot de passe communique par l'etablissement. A la premiere connexion, vous definirez votre propre mot de passe.</div>
      </div>
      <form method="POST" data-loading>
        <?= csrfField() ?><input type="hidden" name="tab" value="matricule">
        <div class="form-group">
          <label class="form-label">Matricule scolaire <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-id-card input-icon"></i>
            <input type="text" name="matricule" class="form-control" data-uppercase placeholder="STU-2024-0001" value="<?= clean($_POST['matricule'] ?? '') ?>" required style="letter-spacing:.05em">
          </div>
          <span class="form-hint">Format : STU-AAAA-XXXX</span>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-lock-alt input-icon"></i>
            <input type="password" name="password" id="p2" class="form-control pr" placeholder="••••••••" required>
            <span class="input-icon-right" data-toggle-pwd="p2" style="cursor:pointer"><i class="bx bx-show" id="i2"></i></span>
          </div>
        </div>
        <label class="form-check" style="margin-bottom:18px"><input type="checkbox" name="remember" class="form-check-input"><span class="form-check-label" style="font-size:13px">Se souvenir</span></label>
        <button type="submit" class="btn-auth"><i class="bx bx-log-in"></i> Se connecter</button>
      </form>
    </div>

    <div style="text-align:center;margin-top:22px;padding-top:16px;border-top:1px solid var(--border-light)">
      <p style="font-size:13.5px;color:var(--text-muted)">Pas encore de compte ?
        <a href="<?= BASE_URL ?>/auth/register.php" style="color:var(--primary);font-weight:700"><i class="bx bx-user-plus"></i> Faire une demande</a>
      </p>
    </div>
  </div>
</div>

<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<script>
function switchTab(t) {
  document.getElementById('fEmail').style.display      = t==='email'     ?'':'none';
  document.getElementById('fMatricule').style.display  = t==='matricule' ?'':'none';
  document.querySelectorAll('.ltab').forEach((b,i)=>{
    b.classList.toggle('active',(t==='email'&&i===0)||(t==='matricule'&&i===1));
  });
}
document.getElementById('darkBtn').addEventListener('click',function(){
  const dark=document.body.classList.toggle('dark-mode');
  document.documentElement.classList.toggle('dark-mode',dark);
  this.querySelector('i').className=dark?'bx bx-sun':'bx bx-moon';
  document.cookie='ss_dark_mode='+(dark?'1':'0')+';expires='+new Date(Date.now()+365*86400000).toUTCString()+';path=/';
});
</script>
</body></html>
