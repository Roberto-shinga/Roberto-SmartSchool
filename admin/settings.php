<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Paramètres';
$pageSection = 'settings';
$user        = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Informations générales & Logo ─────────────────────────
    if ($action === 'save_general') {
        foreach (['school_name', 'school_address', 'school_phone', 'school_email', 'school_motto', 'currency', 'currency_name'] as $key) {
            setSetting($key, sanitizeString($_POST[$key] ?? ''));
        }
        
        $maxGrade  = max(10, min(100, (int)($_POST['max_grade'] ?? 20)));
        $passGrade = max(1, min($maxGrade, (int)($_POST['passing_grade'] ?? 10)));
        setSetting('max_grade', (string)$maxGrade);
        setSetting('passing_grade', (string)$passGrade);

        // Upload logo
        if (!empty($_FILES['logo']['name'])) {
            $up = uploadFile($_FILES['logo'], 'logos', ['jpg', 'jpeg', 'png', 'webp']);
            if ($up['success']) {
                setSetting('school_logo', $up['filename']);
            } else {
                redirectWith($_SERVER['PHP_SELF'], 'danger', $up['error']);
            }
        }

        logActivity('settings_updated', 'Informations générales mises à jour');
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Paramètres mis à jour.');
    }

    // ── Année scolaire ──────────────────────────────────────────
    if ($action === 'add_year') {
        $name  = sanitizeString($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end   = $_POST['end_date']   ?? '';

        if (empty($name) || empty($start) || empty($end)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Nom et dates obligatoires.');
        } elseif (dbFetchOne("SELECT id FROM academic_years WHERE name=?", [$name])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette année scolaire existe déjà.');
        } else {
            dbExecute("INSERT INTO academic_years (name, start_date, end_date, is_current) VALUES (?, ?, ?, 0)", [$name, $start, $end]);
            logActivity('academic_year_created', $name);
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Année scolaire créée.');
        }
    }

    if ($action === 'set_current_year') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("UPDATE academic_years SET is_current=0");
        dbExecute("UPDATE academic_years SET is_current=1 WHERE id=?", [$id]);
        logActivity('academic_year_switched', "Année #$id définie comme courante");
        redirectWith($_SERVER['PHP_SELF'], 'warning', 'Année scolaire courante changée. Vérifiez les classes et affectations associées.');
    }

    // ── Trimestres ────────────────────────────────────────────
    if ($action === 'set_current_term') {
        $id   = (int)($_POST['id'] ?? 0);
        $term = dbFetchOne("SELECT * FROM terms WHERE id=?", [$id]);
        if ($term) {
            dbExecute("UPDATE terms SET is_current=0 WHERE academic_year_id=?", [$term['academic_year_id']]);
            dbExecute("UPDATE terms SET is_current=1 WHERE id=?", [$id]);
            logActivity('term_switched', "Trimestre #$id défini comme courant");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Trimestre courant mis à jour.');
        }
    }
}

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$years       = dbFetchAll("SELECT * FROM academic_years ORDER BY start_date DESC");
$terms       = $currentYear ? dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$currentYear['id']]) : [];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Paramètres</h1>
        <p>Informations de l'établissement, année scolaire et trimestres</p>
      </div>
    </div>

    <div class="grid-2 mb-6">
      
      <!-- Bloc Informations générales + Logo -->
      <div class="card">
        <div class="card-header">
          <h3><i class="bx bx-building-house"></i> Informations de l'établissement</h3>
        </div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_general">
            
            <div class="form-group">
              <label class="form-label">Nom de l'établissement</label>
              <input type="text" name="school_name" class="form-control" value="<?= clean(getSetting('school_name', 'SmartSchool')) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Devise / slogan</label>
              <input type="text" name="school_motto" class="form-control" value="<?= clean(getSetting('school_motto')) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Adresse</label>
              <input type="text" name="school_address" class="form-control" value="<?= clean(getSetting('school_address')) ?>">
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="text" name="school_phone" class="form-control" value="<?= clean(getSetting('school_phone')) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="school_email" class="form-control" value="<?= clean(getSetting('school_email')) ?>">
              </div>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Devise monétaire</label>
                <input type="text" name="currency" class="form-control" maxlength="10" value="<?= clean(getSetting('currency', 'FC')) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Nom de la devise</label>
                <input type="text" name="currency_name" class="form-control" value="<?= clean(getSetting('currency_name', 'Franc Congolais')) ?>">
              </div>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Note maximale</label>
                <input type="number" name="max_grade" class="form-control" min="10" max="100" value="<?= clean(getSetting('max_grade', '20')) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Note de passage</label>
                <input type="number" name="passing_grade" class="form-control" min="1" max="100" value="<?= clean(getSetting('passing_grade', '10')) ?>">
              </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
              <label class="form-label">Logo de l'école</label>
              <?php 
                $logo = getSetting('school_logo'); 
                if ($logo && file_exists(UPLOADS_PATH . '/logos/' . $logo)): 
              ?>
                <img src="<?= UPLOADS_URL ?>/logos/<?= clean($logo) ?>" style="height:60px; margin-bottom:8px; display:block; border-radius:var(--radius)">
              <?php endif; ?>
              <input type="file" name="logo" class="form-control" accept="image/*">
              <span class="form-hint" style="font-size: 0.8rem; color: var(--text-muted);">JPG, PNG ou WebP — max 10 Mo</span>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:12px;">
              <i class="bx bx-save"></i> Enregistrer les paramètres
            </button>
          </form>
        </div>
      </div>

      <!-- Bloc Année scolaire & Trimestres -->
      <div style="display:flex; flex-direction:column; gap:20px;">
        
        <!-- Gestion des années scolaires -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bx bx-calendar"></i> Année scolaire</h3>
          </div>
          <div class="card-body">
            <?php foreach ($years as $y): ?>
              <div class="flex justify-between items-center" style="padding:8px 0; border-bottom:1px solid var(--border-light)">
                <div>
                  <div class="text-sm font-semibold"><?= clean($y['name']) ?></div>
                  <div class="text-xs text-muted"><?= formatDate($y['start_date']) ?> — <?= formatDate($y['end_date']) ?></div>
                </div>
                <?php if ($y['is_current']): ?>
                  <span class="badge badge-success">Courante</span>
                <?php else: ?>
                  <form method="POST" data-confirm="Définir <?= clean($y['name']) ?> comme année courante ? Cela affecte toutes les pages liées à l'année active.">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="set_current_year">
                    <input type="hidden" name="id" value="<?= $y['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Activer</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

            <form method="POST" style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="add_year">
              <input type="text" name="name" class="form-control" placeholder="2025-2026" style="flex:1; min-width:100px" required>
              <input type="date" name="start_date" class="form-control" style="flex:1; min-width:130px" required>
              <input type="date" name="end_date" class="form-control" style="flex:1; min-width:130px" required>
              <button type="submit" class="btn btn-primary btn-sm"><i class="bx bx-plus"></i></button>
            </form>
          </div>
        </div>

        <!-- Gestion des trimestres -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bx bx-calendar-week"></i> Trimestres (<?= clean($currentYear['name'] ?? '—') ?>)</h3>
          </div>
          <div class="card-body">
            <?php if (empty($terms)): ?>
              <p class="text-sm text-muted">Aucun trimestre défini pour cette année.</p>
            <?php else: foreach ($terms as $t): ?>
              <div class="flex justify-between items-center" style="padding:8px 0; border-bottom:1px solid var(--border-light)">
                <div>
                  <div class="text-sm font-semibold"><?= clean($t['name']) ?></div>
                  <div class="text-xs text-muted"><?= formatDate($t['start_date']) ?> — <?= formatDate($t['end_date']) ?></div>
                </div>
                <?php if ($t['is_current']): ?>
                  <span class="badge badge-success">Courant</span>
                <?php else: ?>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="set_current_term">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Activer</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>