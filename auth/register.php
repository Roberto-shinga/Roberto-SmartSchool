<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';

if (isLoggedIn()) redirect(ROLE_REDIRECTS[currentRole()] ?? BASE_URL);

$error    = '';
$prefill  = $_SESSION['google_prefill'] ?? [];
$fromG    = isset($_GET['from']) && $_GET['from'] === 'google';

// Roles disponibles a l'auto-inscription (uniquement Parent).
// Enseignants et comptables sont crees exclusivement par l'administration,
// via une invitation securisee par email (voir admin/teachers.php,
// admin/accountants.php et auth/activate-account.php).
$regRoles = [
    ROLE_PARENT => ['label'=>'Parent', 'icon'=>'bx-group', 'desc'=>'Suivre la scolarite de mon enfant'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $roleId    = (int)($_POST['role_id']   ?? 0);
    $firstName = sanitizeString($_POST['first_name']  ?? '');
    $lastName  = sanitizeString($_POST['last_name']   ?? '');
    $email     = strtolower(trim($_POST['email']      ?? ''));
    $phone     = sanitizeString($_POST['phone']       ?? '');
    $gender    = in_array($_POST['gender'] ?? '', ['M','F']) ? $_POST['gender'] : 'M';
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm']  ?? '';
    $googleId  = $_POST['google_id'] ?? '';
    $isGoogle  = !empty($googleId);

    if (!array_key_exists($roleId, $regRoles)) {
        $error = 'Role non autorise pour l\'inscription en ligne. Contactez l\'administration.';
    } elseif (empty($firstName) || empty($lastName)) {
        $error = 'Prenom et nom obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (!$isGoogle && strlen($password) < 6) {
        $error = 'Mot de passe trop court (minimum 6 caracteres).';
    } elseif (!$isGoogle && $password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $exists = dbFetchOne("SELECT id FROM users WHERE email=?", [$email]);
        if ($exists) {
            $error = 'Cette adresse email est deja utilisee.';
        } else {
            try {
                getDB()->beginTransaction();
                $username = strtolower($firstName . '.' . $lastName . rand(10,99));
                $pwdHash  = $isGoogle ? hashPassword(generateToken(16)) : hashPassword($password);

                dbExecute(
                    "INSERT INTO users (role_id,username,email,password,first_name,last_name,phone,gender,is_active) VALUES (?,?,?,?,?,?,?,?,1)",
                    [$roleId, $username, $email, $pwdHash, $firstName, $lastName, $phone, $gender]
                );
                $userId = dbLastId();

                getDB()->commit();
                unset($_SESSION['google_prefill']);

                // Notifier admins
                $admins = dbFetchAll("SELECT id FROM users WHERE role_id IN (1,2) AND is_active=1");
                foreach ($admins as $adm) {
                    createNotification($adm['id'], 'Nouveau compte',
                        "$firstName $lastName s'est inscrit(e) en tant que {$regRoles[$roleId]['label']}.", 'info');
                }

                logActivity('register', "$firstName $lastName — " . $regRoles[$roleId]['label']);

                if ($isGoogle) {
                    $newUser = dbFetchOne("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=?", [$userId]);
                    loginUser($newUser);
                    redirectWith(ROLE_REDIRECTS[$roleId] ?? BASE_URL, 'success', "Bienvenue $firstName !");
                }
                redirectWith(BASE_URL . '/auth/login.php', 'success', 'Compte cree ! Vous pouvez vous connecter.');
            } catch (Exception $e) {
                getDB()->rollBack();
                $error = 'Erreur lors de la creation du compte. Reessayez.';
            }
        }
    }
}

$schoolName = getSetting('school_name', APP_NAME);
$flash      = getFlash();
$googleUrl  = buildGoogleAuthUrl();
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Inscription — <?= clean($schoolName) ?></title>
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
    .b1{width:200px;height:200px;top:-50px;right:-50px;animation:float 7s ease-in-out infinite;}
    .b2{width:130px;height:130px;bottom:90px;left:20px;animation:float 7s 2.5s ease-in-out infinite;}
    @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-18px);}}
    .a-brand{display:flex;align-items:center;gap:16px;position:relative;z-index:1;}
    .a-brand-icon{width:52px;height:52px;background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.28);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;}
    .a-hero{position:relative;z-index:1;}
    .a-hero h2{font-size:2rem;font-weight:800;color:#fff;line-height:1.25;margin-bottom:14px;}
    .a-hero p{font-size:0.93rem;color:rgba(255,255,255,0.76);line-height:1.7;max-width:380px;}
    .a-note{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.22);border-radius:var(--radius);padding:14px 16px;color:rgba(255,255,255,0.88);font-size:12.5px;line-height:1.6;position:relative;z-index:1;}
    .a-foot{font-size:12px;color:rgba(255,255,255,0.40);position:relative;z-index:1;}
    .auth-right{width:100%;max-width:520px;background:var(--bg-card);display:flex;flex-direction:column;justify-content:center;padding:36px 48px;overflow-y:auto;min-height:100vh;}
    @media(max-width:960px){.auth-right{max-width:100%;padding:28px 22px;}}
    .m-brand{display:flex;align-items:center;gap:12px;margin-bottom:24px;}
    @media(min-width:960px){.m-brand{display:none;}}
    .m-brand-icon{width:40px;height:40px;background:var(--grad-primary);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;}
    .role-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:18px;}
    .role-card{display:flex;flex-direction:column;align-items:center;gap:7px;padding:14px 10px;border:2px solid var(--border);border-radius:var(--radius-lg);cursor:pointer;transition:all var(--transition);text-align:center;}
    .role-card:hover,.role-card.sel{border-color:var(--primary);background:var(--primary-bg);}
    .role-card input{display:none;}
    .role-icon{width:42px;height:42px;border-radius:var(--radius);background:var(--bg-body);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--text-muted);transition:all var(--transition);}
    .role-card.sel .role-icon,.role-card:hover .role-icon{background:var(--grad-primary);color:#fff;}
    .role-label{font-size:12.5px;font-weight:700;color:var(--text-primary);}
    .role-desc{font-size:11px;color:var(--text-muted);line-height:1.3;}
    .btn-google{width:100%;padding:11px;background:var(--bg-card);color:var(--text-primary);border:1.5px solid var(--border);border-radius:var(--radius);font-size:14px;font-weight:600;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all var(--transition);box-shadow:var(--shadow-sm);text-decoration:none;}
    .btn-google:hover{border-color:#4285F4;transform:translateY(-1px);}
    .divider{display:flex;align-items:center;gap:12px;font-size:12px;color:var(--text-muted);margin:14px 0;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .btn-auth{width:100%;padding:12px;background:var(--grad-primary);color:#fff;border:none;border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:var(--shadow-primary);transition:all var(--transition-md);}
    .btn-auth:hover{filter:brightness(1.07);transform:translateY(-1px);}
    .pwd-bars{display:flex;gap:4px;margin-top:6px;}
    .pwd-bar{flex:1;height:4px;border-radius:2px;background:var(--border);transition:background .3s;}
    @keyframes spin{to{transform:rotate(360deg);}}
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-left">
    <?php if (file_exists('C:/xampp/htdocs/SmartSchool/assets/images/school-bg.jpg')): ?>
      <img class="auth-img" src="<?= ASSETS_URL ?>/images/school-bg.jpg" alt="">
    <?php else: ?>
      <div style="width:100%;height:100%;background:linear-gradient(135deg,#1e1b4b,#4f46e5,#7c3aed)"></div>
    <?php endif; ?>
    <div class="auth-overlay">
      <div class="bubble b1"></div><div class="bubble b2"></div>
      <div class="a-brand">
        <div class="a-brand-icon"><i class="bx bx-buildings"></i></div>
        <div><div style="font-size:1.5rem;font-weight:800;color:#fff"><?= clean($schoolName) ?></div><div style="font-size:.73rem;color:rgba(255,255,255,.64)">Systeme de gestion scolaire</div></div>
      </div>
      <div class="a-hero">
        <h2>Rejoignez notre<br>communaute scolaire.</h2>
        <p>Creez votre compte parent ou comptable pour acceder a la plateforme SmartSchool.</p>
      </div>
      <div class="a-note">
        <strong>ℹ️ Information importante</strong><br>
        Les comptes <strong>Enseignants</strong> et <strong>Eleves</strong> sont crees exclusivement par l'administration scolaire. Si vous etes enseignant ou eleve, contactez votre directeur.
      </div>
      <div class="a-foot">&copy; <?= APP_YEAR ?> <?= clean($schoolName) ?></div>
    </div>
  </div>

  <div class="auth-right">
    <div class="m-brand">
      <div class="m-brand-icon"><i class="bx bx-buildings"></i></div>
      <span style="font-size:1.1rem;font-weight:800;color:var(--primary)"><?= clean($schoolName) ?></span>
    </div>

    <h1 style="font-size:1.7rem;font-weight:800;color:var(--text-primary);margin-bottom:4px">Creer un compte</h1>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px">Inscription disponible pour les parents et comptables</p>

    <?php if ($flash): ?><div class="alert alert-<?= clean($flash['type']) ?>"><i class="bx bx-info-circle"></i> <?= clean($flash['message']) ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-danger"><i class="bx bx-x-circle"></i> <?= clean($error) ?></div><?php endif; ?>

    <!-- Google pre-rempli -->
    <?php if ($fromG && !empty($prefill)): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--success-bg);border:1px solid var(--success);border-radius:var(--radius);margin-bottom:18px">
        <?php if (!empty($prefill['avatar_url'])): ?>
          <img src="<?= clean($prefill['avatar_url']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover" alt="">
        <?php endif; ?>
        <div><div style="font-size:13px;font-weight:700;color:var(--success-dark)">Compte Google verifie</div>
        <div style="font-size:12px;color:var(--text-muted)"><?= clean($prefill['email']) ?></div></div>
        <i class="bx bx-check-circle" style="color:var(--success);font-size:1.4rem;margin-left:auto"></i>
      </div>
    <?php endif; ?>

    <!-- Bouton Google -->
    <?php if (!$fromG && !empty($googleUrl)): ?>
      <a href="<?= clean($googleUrl) ?>" class="btn-google">
        <svg width="20" height="20" viewBox="0 0 24 24" style="flex-shrink:0">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg> S'inscrire avec Google
      </a>
      <div class="divider">ou avec votre email</div>
    <?php endif; ?>

    <form method="POST" data-loading>
      <?= csrfField() ?>
      <input type="hidden" name="google_id"  value="<?= clean($prefill['google_id']  ?? '') ?>">
      <input type="hidden" name="avatar_url" value="<?= clean($prefill['avatar_url'] ?? '') ?>">

      <!-- Choix role -->
      <div style="margin-bottom:16px">
        <label class="form-label">Je suis... <span class="form-required">*</span></label>
        <div class="role-grid">
          <?php foreach ($regRoles as $rId => $role): ?>
          <label class="role-card" id="rc<?= $rId ?>">
            <input type="radio" name="role_id" value="<?= $rId ?>" onchange="selRole(<?= $rId ?>)"
                   <?= (isset($_POST['role_id']) && $_POST['role_id']==$rId)?'checked':'' ?> required>
            <div class="role-icon"><i class="bx <?= $role['icon'] ?>"></i></div>
            <div><div class="role-label"><?= $role['label'] ?></div><div class="role-desc"><?= $role['desc'] ?></div></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Prenom <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-user input-icon"></i>
            <input type="text" name="first_name" class="form-control" placeholder="Jean" required value="<?= clean($_POST['first_name'] ?? $prefill['first_name'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Nom <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-user input-icon"></i>
            <input type="text" name="last_name" class="form-control" placeholder="Dupont" required value="<?= clean($_POST['last_name'] ?? $prefill['last_name'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email <span class="form-required">*</span></label>
        <div class="input-wrap"><i class="bx bx-envelope input-icon"></i>
          <input type="email" name="email" class="form-control" placeholder="votre@email.fr" required
                 value="<?= clean($_POST['email'] ?? $prefill['email'] ?? '') ?>"
                 <?= ($fromG && !empty($prefill['email'])) ? 'readonly style="background:var(--bg-body)"' : '' ?>>
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Telephone</label>
          <div class="input-wrap"><i class="bx bx-phone input-icon"></i>
            <input type="text" name="phone" class="form-control" placeholder="+243 00 000 0000" value="<?= clean($_POST['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Genre</label>
          <select name="gender" class="form-control">
            <option value="M">Masculin</option>
            <option value="F">Feminin</option>
          </select>
        </div>
      </div>

      <?php if (!$fromG): ?>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Mot de passe <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-lock-alt input-icon"></i>
            <input type="password" name="password" id="npwd" class="form-control pr" placeholder="Min. 6 car." required minlength="6">
            <span class="input-icon-right" data-toggle-pwd="npwd" style="cursor:pointer"><i class="bx bx-show"></i></span>
          </div>
          <div class="pwd-bars"><div class="pwd-bar" id="b1"></div><div class="pwd-bar" id="b2"></div><div class="pwd-bar" id="b3"></div><div class="pwd-bar" id="b4"></div></div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmer <span class="form-required">*</span></label>
          <div class="input-wrap"><i class="bx bx-lock input-icon"></i>
            <input type="password" name="confirm" class="form-control" placeholder="Repeter" required>
          </div>
        </div>
      </div>
      <?php else: ?>
        <div class="alert alert-info"><i class="bx bx-info-circle"></i> Inscription via Google — aucun mot de passe requis.</div>
      <?php endif; ?>

      <button type="submit" class="btn-auth"><i class="bx bx-user-plus"></i> <?= $fromG ? 'Finaliser mon inscription' : 'Creer mon compte' ?></button>
    </form>

    <div style="text-align:center;margin-top:18px;padding-top:14px;border-top:1px solid var(--border-light)">
      <p style="font-size:13.5px;color:var(--text-muted)">Deja un compte ? <a href="<?= BASE_URL ?>/auth/login.php" style="color:var(--primary);font-weight:700">Se connecter</a></p>
    </div>
  </div>
</div>

<script src="<?= ASSETS_URL ?>/js/main.js"></script>
<script>
function selRole(id){
  document.querySelectorAll('.role-card').forEach(c=>c.classList.remove('sel'));
  document.getElementById('rc'+id)?.classList.add('sel');
}
document.querySelectorAll('.role-card input:checked').forEach(r=>selRole(parseInt(r.value)));
const np=document.getElementById('npwd');
np?.addEventListener('input',function(){
  const v=this.value;let s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;
  if(/[0-9]/.test(v)&&/\W/.test(v))s++;
  const c=['#ef4444','#f59e0b','#3b82f6','#10b981'];
  for(let i=1;i<=4;i++){document.getElementById('b'+i).style.background=i<=s?c[s-1]:'var(--border)';}
});
</script>
</body></html>
