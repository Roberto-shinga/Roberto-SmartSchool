<?php
require_once __DIR__ . '/../bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Assistant de configuration';
$pageSection = 'setup';
$superAdmin  = currentUser();

$setup = dbFetchOne("SELECT * FROM system_setup ORDER BY id LIMIT 1");
if (!$setup) {
    dbExecute("INSERT INTO system_setup (setup_completed, current_step) VALUES (0, 1)");
    $setup = dbFetchOne("SELECT * FROM system_setup ORDER BY id LIMIT 1");
}

$steps = [
    1 => ['label' => 'Etablissement',   'icon' => 'bx-building-house'],
    2 => ['label' => 'Annee scolaire',  'icon' => 'bx-calendar'],
    3 => ['label' => 'Structure',       'icon' => 'bx-sitemap'],
    4 => ['label' => 'Administrateur',  'icon' => 'bx-user-check'],
    5 => ['label' => 'Verification',    'icon' => 'bx-check-double'],
];
$maxStep = max(array_keys($steps));

// On peut revenir en arriere pour revoir une etape, mais jamais sauter
// au-dela de la progression deja enregistree.
$step = (int)($_GET['step'] ?? $setup['current_step']);
$step = max(1, min($step, (int)$setup['current_step']));

$tempPwdShow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // â”€â”€ Etape 1 : Etablissement â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($action === 'save_school') {
        $name    = sanitizeString($_POST['name'] ?? '');
        $address = sanitizeString($_POST['address'] ?? '');
        $city    = sanitizeString($_POST['city'] ?? '');
        $province = sanitizeString($_POST['province'] ?? '');
        $phone   = sanitizeString($_POST['phone'] ?? '');
        $email   = sanitizeString($_POST['email'] ?? '');
        $schoolType = in_array($_POST['school_type'] ?? '', ['prive','public','conventionne','autre']) ? $_POST['school_type'] : 'prive';
        $director = sanitizeString($_POST['director_name'] ?? '');

        if (empty($name)) {
            redirectWith($_SERVER['PHP_SELF'] . '?step=1', 'danger', "Le nom de l'etablissement est obligatoire.");
        } else {
            $existing = dbFetchOne("SELECT id FROM schools ORDER BY id LIMIT 1");
            if ($existing) {
                dbExecute(
                    "UPDATE schools SET name=?, address=?, city=?, province=?, phone=?, email=?, school_type=?, director_name=? WHERE id=?",
                    [$name, $address ?: null, $city ?: null, $province ?: null, $phone ?: null, $email ?: null, $schoolType, $director ?: null, $existing['id']]
                );
            } else {
                dbExecute(
                    "INSERT INTO schools (name, address, city, province, phone, email, school_type, director_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$name, $address ?: null, $city ?: null, $province ?: null, $phone ?: null, $email ?: null, $schoolType, $director ?: null]
                );
            }
            setSetting('school_name', $name);
            $newStep = max((int)$setup['current_step'], 2);
            dbExecute("UPDATE system_setup SET current_step=? WHERE id=?", [$newStep, $setup['id']]);
            logActivity('setup_step_completed', 'Etape 1 : Etablissement');
            redirect($_SERVER['PHP_SELF'] . '?step=2');
        }
    }

    // â”€â”€ Etape 2 : Annee scolaire â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($action === 'save_year') {
        $name  = sanitizeString($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end   = $_POST['end_date'] ?? '';

        if (empty($name) || empty($start) || empty($end)) {
            redirectWith($_SERVER['PHP_SELF'] . '?step=2', 'danger', 'Nom et dates obligatoires.');
        } else {
            $existing = dbFetchOne("SELECT id FROM academic_years WHERE name=?", [$name]);
            dbExecute("UPDATE academic_years SET is_current=0");
            if ($existing) {
                dbExecute("UPDATE academic_years SET start_date=?, end_date=?, is_current=1 WHERE id=?", [$start, $end, $existing['id']]);
            } else {
                dbExecute("INSERT INTO academic_years (name, start_date, end_date, is_current) VALUES (?, ?, ?, 1)", [$name, $start, $end]);
            }
            $newStep = max((int)$setup['current_step'], 3);
            dbExecute("UPDATE system_setup SET current_step=? WHERE id=?", [$newStep, $setup['id']]);
            logActivity('setup_step_completed', 'Etape 2 : Annee scolaire ' . $name);
            redirect($_SERVER['PHP_SELF'] . '?step=3');
        }
    }

    // â”€â”€ Etape 3 : Structure (juste une confirmation) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($action === 'confirm_structure') {
        $newStep = max((int)$setup['current_step'], 4);
        dbExecute("UPDATE system_setup SET current_step=? WHERE id=?", [$newStep, $setup['id']]);
        redirect($_SERVER['PHP_SELF'] . '?step=4');
    }

    // â”€â”€ Etape 4 : Administrateur principal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($action === 'create_admin') {
        $firstName = sanitizeString($_POST['first_name'] ?? '');
        $lastName  = sanitizeString($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email']     ?? ''));
        $phone     = sanitizeString($_POST['phone']      ?? '');

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'] . '?step=4', 'danger', 'Prenom et nom obligatoires.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith($_SERVER['PHP_SELF'] . '?step=4', 'danger', 'Adresse email invalide.');
        } elseif (dbFetchOne("SELECT id FROM users WHERE email=?", [$email])) {
            redirectWith($_SERVER['PHP_SELF'] . '?step=4', 'danger', 'Cette adresse email est deja utilisee.');
        } else {
            $tempPwd  = generateTempPassword();
            $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
            dbExecute(
                "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, is_active, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)",
                [ROLE_ADMIN, $username, $email, hashPassword($tempPwd), $firstName, $lastName, $phone]
            );
            logActivity('admin_created', "Administrateur principal cree via l'assistant : $firstName $lastName ($email)");

            $_SESSION['setup_temp_pwd'] = ['name' => "$firstName $lastName", 'username' => $username, 'password' => $tempPwd];
            $newStep = max((int)$setup['current_step'], 5);
            dbExecute("UPDATE system_setup SET current_step=? WHERE id=?", [$newStep, $setup['id']]);
            logActivity('setup_step_completed', 'Etape 4 : Administrateur principal');
            redirect($_SERVER['PHP_SELF'] . '?step=5');
        }
    }

    // â”€â”€ Etape 4 (alternative) : passer cette etape si un admin existe deja â”€â”€
    if ($action === 'skip_admin') {
        $newStep = max((int)$setup['current_step'], 5);
        dbExecute("UPDATE system_setup SET current_step=? WHERE id=?", [$newStep, $setup['id']]);
        redirect($_SERVER['PHP_SELF'] . '?step=5');
    }

    // â”€â”€ Etape 5 : Terminer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($action === 'finish') {
        dbExecute("UPDATE system_setup SET setup_completed=1, completed_at=NOW() WHERE id=?", [$setup['id']]);
        logActivity('setup_completed', 'Configuration initiale terminee par ' . $superAdmin['username']);
        redirectWith(BASE_URL . '/superadmin/index.php', 'success', 'Configuration initiale terminee. Bienvenue sur SmartSchool !');
    }
}

if (!empty($_SESSION['setup_temp_pwd'])) { $tempPwdShow = $_SESSION['setup_temp_pwd']; unset($_SESSION['setup_temp_pwd']); }

$school      = dbFetchOne("SELECT * FROM schools ORDER BY id LIMIT 1") ?: [];
$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1") ?: [];
$cycles      = dbFetchAll("SELECT c.*, (SELECT COUNT(*) FROM levels WHERE cycle_id=c.id) AS nb_levels FROM cycles c WHERE is_active=1 ORDER BY order_index");
$nbLevels    = dbCount('levels', "is_active=1");
$existingAdmins = dbFetchAll("SELECT id, first_name, last_name, email FROM users WHERE role_id=? AND is_active=1", [ROLE_ADMIN]);

$progressPct = round((($step - 1) / ($maxStep - 1)) * 100);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <?php if ($setup['setup_completed']): ?>
    <div class="alert alert-info" style="margin-bottom:20px">
      <i class="bx bx-info-circle"></i> La configuration initiale a deja ete terminee. Tu peux revoir les etapes
      ci-dessous, mais les parametres se modifient normalement depuis <a href="<?= BASE_URL ?>/admin/settings.php" style="font-weight:700">Admin â†’ Parametres</a>.
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div><h1>Configuration SmartSchool</h1><p>Assistant de mise en route</p></div>
    </div>

    <!-- â•â• Barre de progression â•â• -->
    <div class="card" style="margin-bottom:24px">
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;margin-bottom:10px">
          <?php foreach ($steps as $num => $s):
            $done = $num < (int)$setup['current_step'] || ($num === (int)$setup['current_step'] && $setup['setup_completed']);
            $active = $num === $step;
          ?>
          <a href="<?= $num <= (int)$setup['current_step'] ? '?step=' . $num : '#' ?>"
             style="flex:1;text-align:center;text-decoration:none;<?= $num > (int)$setup['current_step'] ? 'pointer-events:none' : '' ?>">
            <div style="width:38px;height:38px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;
                        background:<?= $done ? 'var(--success)' : ($active ? 'var(--primary)' : 'var(--border)') ?>;
                        color:<?= ($done || $active) ? '#fff' : 'var(--text-muted)' ?>;font-weight:700">
              <?= $done ? '<i class="bx bx-check"></i>' : $num ?>
            </div>
            <div class="text-xs" style="color:<?= $active ? 'var(--primary)' : 'var(--text-muted)' ?>;font-weight:<?= $active ? '700' : '400' ?>"><?= $s['label'] ?></div>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="progress"><div class="progress-bar success" data-value="<?= $progressPct ?>" style="width:0"></div></div>
      </div>
    </div>

    <!-- â•â• Etape 1 : Etablissement â•â• -->
    <?php if ($step === 1): ?>
    <div class="card">
      <div class="card-header"><h3><i class="bx bx-building-house"></i> Informations de l'etablissement</h3></div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_school">
          <div class="form-group"><label class="form-label">Nom de l'etablissement <span class="form-required">*</span></label><input type="text" name="name" class="form-control" value="<?= clean($school['name'] ?? '') ?>" required></div>
          <div class="form-group"><label class="form-label">Adresse</label><input type="text" name="address" class="form-control" value="<?= clean($school['address'] ?? '') ?>"></div>
          <div class="grid-2">
            <div class="form-group"><label class="form-label">Ville</label><input type="text" name="city" class="form-control" value="<?= clean($school['city'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Province</label><input type="text" name="province" class="form-control" value="<?= clean($school['province'] ?? '') ?>"></div>
          </div>
          <div class="grid-2">
            <div class="form-group"><label class="form-label">Telephone</label><input type="text" name="phone" class="form-control" value="<?= clean($school['phone'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= clean($school['email'] ?? '') ?>"></div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Type d'etablissement</label>
              <select name="school_type" class="form-control">
                <?php foreach (['prive'=>'Prive','public'=>'Public','conventionne'=>'Conventionne','autre'=>'Autre'] as $val=>$lbl): ?>
                  <option value="<?= $val ?>" <?= ($school['school_type'] ?? 'prive')===$val?'selected':'' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Directeur / Chef d'etablissement</label><input type="text" name="director_name" class="form-control" value="<?= clean($school['director_name'] ?? '') ?>"></div>
          </div>
          <p class="text-xs text-muted" style="margin-bottom:18px">D'autres champs (sigle, logo, numero d'identification, province educationnelle...) restent modifiables plus tard depuis Admin â†’ Parametres.</p>
          <button type="submit" class="btn btn-primary"><i class="bx bx-chevron-right"></i> Continuer</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- â•â• Etape 2 : Annee scolaire â•â• -->
    <?php if ($step === 2): ?>
    <div class="card">
      <div class="card-header"><h3><i class="bx bx-calendar"></i> Annee scolaire en cours</h3></div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_year">
          <div class="form-group"><label class="form-label">Libelle <span class="form-required">*</span></label><input type="text" name="name" class="form-control" placeholder="Ex : 2026-2027" value="<?= clean($currentYear['name'] ?? '') ?>" required></div>
          <div class="grid-2">
            <div class="form-group"><label class="form-label">Date de debut <span class="form-required">*</span></label><input type="date" name="start_date" class="form-control" value="<?= clean($currentYear['start_date'] ?? '') ?>" required></div>
            <div class="form-group"><label class="form-label">Date de fin <span class="form-required">*</span></label><input type="date" name="end_date" class="form-control" value="<?= clean($currentYear['end_date'] ?? '') ?>" required></div>
          </div>
          <div style="display:flex;gap:10px">
            <a href="?step=1" class="btn btn-secondary"><i class="bx bx-chevron-left"></i> Retour</a>
            <button type="submit" class="btn btn-primary"><i class="bx bx-chevron-right"></i> Continuer</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- â•â• Etape 3 : Structure â•â• -->
    <?php if ($step === 3): ?>
    <div class="card">
      <div class="card-header"><h3><i class="bx bx-sitemap"></i> Structure academique</h3></div>
      <div class="card-body">
        <p class="text-sm text-muted" style="margin-bottom:16px">
          Une structure de base (cycles et niveaux) est deja prete. Tu peux la personnaliser maintenant ou plus tard â€”
          rien n'est fige, tout reste configurable.
        </p>
        <div class="grid-3 mb-6">
          <?php foreach ($cycles as $c): ?>
          <div style="border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px">
            <div class="text-sm font-semibold"><?= clean($c['name']) ?></div>
            <div class="text-xs text-muted"><?= (int)$c['nb_levels'] ?> niveau(x)</div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>/admin/academic-structure.php" target="_blank" class="btn btn-secondary btn-sm" style="margin-bottom:18px">
          <i class="bx bx-external-link"></i> Personnaliser la structure (nouvel onglet)
        </a>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="confirm_structure">
          <div style="display:flex;gap:10px">
            <a href="?step=2" class="btn btn-secondary"><i class="bx bx-chevron-left"></i> Retour</a>
            <button type="submit" class="btn btn-primary"><i class="bx bx-chevron-right"></i> Continuer</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- â•â• Etape 4 : Administrateur principal â•â• -->
    <?php if ($step === 4): ?>
    <div class="card">
      <div class="card-header"><h3><i class="bx bx-user-check"></i> Administrateur principal</h3></div>
      <div class="card-body">
        <?php if (!empty($existingAdmins)): ?>
        <div class="alert alert-info" style="margin-bottom:18px">
          <i class="bx bx-info-circle"></i> <?= count($existingAdmins) ?> administrateur(s) existe(nt) deja :
          <?= implode(', ', array_map(fn($a) => clean($a['first_name'] . ' ' . $a['last_name']), $existingAdmins)) ?>.
          Tu peux en creer un supplementaire ci-dessous, ou passer cette etape.
        </div>
        <?php endif; ?>
        <p class="text-sm text-muted" style="margin-bottom:16px">
          L'Administrateur principal gerera le fonctionnement quotidien de l'etablissement (eleves, enseignants,
          classes, finances...). Un mot de passe temporaire sera genere â€” a communiquer de maniere securisee.
        </p>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create_admin">
          <div class="grid-2">
            <div class="form-group"><label class="form-label">Prenom <span class="form-required">*</span></label><input type="text" name="first_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="last_name" class="form-control" required></div>
          </div>
          <div class="form-group"><label class="form-label">Email professionnel <span class="form-required">*</span></label><input type="email" name="email" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Telephone</label><input type="text" name="phone" class="form-control"></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="?step=3" class="btn btn-secondary"><i class="bx bx-chevron-left"></i> Retour</a>
            <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Creer le compte</button>
          </div>
        </form>
        <?php if (!empty($existingAdmins)): ?>
        <form method="POST" style="margin-top:12px">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="skip_admin">
          <button type="submit" class="btn btn-ghost btn-sm">Passer cette etape (un administrateur existe deja)</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- â•â• Etape 5 : Verification â•â• -->
    <?php if ($step === 5): ?>
    <div class="card">
      <div class="card-header"><h3><i class="bx bx-check-double"></i> Verification et mise en route</h3></div>
      <div class="card-body">
        <?php if ($tempPwdShow): ?>
        <div class="alert alert-warning" style="align-items:flex-start;margin-bottom:20px">
          <i class="bx bx-key" style="margin-top:2px"></i>
          <div>
            <strong>Identifiants de l'administrateur cree : <?= clean($tempPwdShow['name']) ?></strong><br>
            Identifiant : <code style="font-family:var(--font-mono)"><?= clean($tempPwdShow['username']) ?></code> â€”
            Mot de passe : <code style="font-family:var(--font-mono);font-weight:700"><?= clean($tempPwdShow['password']) ?></code><br>
            <span class="text-xs">A communiquer de maniere securisee. Changement exige a la premiere connexion.</span>
          </div>
        </div>
        <?php endif; ?>

        <p class="text-sm text-muted" style="margin-bottom:16px">Recapitulatif :</p>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px">
          <div class="flex justify-between text-sm"><span class="text-muted">Etablissement</span><span class="font-semibold"><?= clean($school['name'] ?? 'Non configure') ?></span></div>
          <div class="flex justify-between text-sm"><span class="text-muted">Annee scolaire</span><span class="font-semibold"><?= clean($currentYear['name'] ?? 'Non configuree') ?></span></div>
          <div class="flex justify-between text-sm"><span class="text-muted">Cycles actifs</span><span class="font-semibold"><?= count($cycles) ?></span></div>
          <div class="flex justify-between text-sm"><span class="text-muted">Niveaux actifs</span><span class="font-semibold"><?= $nbLevels ?></span></div>
          <div class="flex justify-between text-sm"><span class="text-muted">Administrateur(s)</span><span class="font-semibold"><?= count($existingAdmins) + ($tempPwdShow ? 1 : 0) ?></span></div>
        </div>

        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="finish">
          <div style="display:flex;gap:10px">
            <a href="?step=4" class="btn btn-secondary"><i class="bx bx-chevron-left"></i> Retour</a>
            <button type="submit" class="btn btn-primary"><i class="bx bx-check-circle"></i> Terminer la configuration</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>

