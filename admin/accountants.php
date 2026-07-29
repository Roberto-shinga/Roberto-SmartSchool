<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Comptables';
$pageSection = 'accountants';
$admin       = currentUser();

// ── Traitement des actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Inviter un nouveau comptable ──────────────────────────
    if ($action === 'invite') {
        $firstName = sanitizeString($_POST['first_name'] ?? '');
        $lastName  = sanitizeString($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email']     ?? ''));
        $phone     = sanitizeString($_POST['phone']      ?? '');
        $gender    = in_array($_POST['gender'] ?? '', ['M','F']) ? $_POST['gender'] : 'M';

        if (empty($firstName) || empty($lastName)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Prenom et nom obligatoires.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Adresse email invalide.');
        } elseif (dbFetchOne("SELECT id FROM users WHERE email=?", [$email])) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Cette adresse email est deja utilisee.');
        } else {
            try {
                $username = strtolower($firstName . '.' . $lastName . rand(100, 999));
                dbExecute(
                    "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, gender, staff_number, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
                    [ROLE_ACCOUNTANT, $username, $email, hashPassword(generateToken(24)), $firstName, $lastName, $phone, $gender, generateStaffNumber(ROLE_ACCOUNTANT)]
                );
                $userId = dbLastId();

                $token  = createInvitation($userId, ROLE_ACCOUNTANT, $admin['id']);
                logActivity('invitation_created', "Profil comptable cree : $firstName $lastName");
                $mailOk = sendInvitationEmail($email, $firstName, ROLE_LABELS[ROLE_ACCOUNTANT], $token);
                logActivity('invitation_sent', "Invitation envoyee a $email" . ($mailOk ? '' : ' (echec envoi email)'));

                redirectWith($_SERVER['PHP_SELF'], 'success',
                    "Comptable cree. Invitation envoyee a $email." .
                    (APP_ENV === 'development' ? " Lien : " . BASE_URL . "/auth/activate-account.php?token=$token" : ''));
            } catch (Exception $e) {
                redirectWith($_SERVER['PHP_SELF'], 'danger', 'Erreur lors de la creation du profil comptable.');
            }
        }
    }

    // ── Renvoyer une invitation ────────────────────────────────
    if ($action === 'resend') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $u = dbFetchOne("SELECT * FROM users WHERE id=? AND role_id=?", [$userId, ROLE_ACCOUNTANT]);
        if ($u && !$u['is_active']) {
            $token  = createInvitation($userId, ROLE_ACCOUNTANT, $admin['id']);
            $mailOk = sendInvitationEmail($u['email'], $u['first_name'], ROLE_LABELS[ROLE_ACCOUNTANT], $token);
            logActivity('invitation_resent', "Invitation renvoyee a " . $u['email'] . ($mailOk ? '' : ' (echec envoi email)'));
            redirectWith($_SERVER['PHP_SELF'], 'success',
                "Invitation renvoyee." . (APP_ENV === 'development' ? " Lien : " . BASE_URL . "/auth/activate-account.php?token=$token" : ''));
        } else {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Impossible de renvoyer une invitation pour ce compte.');
        }
    }

    // ── Annuler une invitation ──────────────────────────────────
    if ($action === 'cancel') {
        $invId = (int)($_POST['invitation_id'] ?? 0);
        cancelInvitation($invId);
        logActivity('invitation_cancelled', "Invitation #$invId annulee");
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Invitation annulee.');
    }
}

// ── Liste des comptables ──────────────────────────────────────
$accountants = dbFetchAll(
    "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.staff_number, u.is_active, u.created_at,
            (SELECT status     FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_status,
            (SELECT id         FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_id,
            (SELECT expires_at FROM invitations WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) AS inv_expires
     FROM users u
     WHERE u.role_id = ?
     ORDER BY u.created_at DESC",
    [ROLE_ACCOUNTANT]
);

$invBadge = [
    'pending'   => ['warning', 'Invitation en attente'],
    'expired'   => ['danger',  'Invitation expiree'],
    'cancelled' => ['gray',    'Invitation annulee'],
    'used'      => ['success', 'Actif'],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Comptables</h1>
        <p><?= count($accountants) ?> comptable(s)</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="addAccountantModal">
          <i class="bx bx-user-plus"></i> Ajouter un comptable
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3><i class="bx bx-calculator"></i> Liste des comptables</h3>
        <div class="search-wrap" style="max-width:260px">
          <i class="bx bx-search"></i>
          <input type="text" class="search-input" placeholder="Rechercher..." data-table-search="accountantsTable">
        </div>
      </div>

      <?php if (empty($accountants)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-calculator"></i></div>
          <h3>Aucun comptable</h3>
          <p>Commencez par inviter votre premier comptable.</p>
          <button class="btn btn-primary btn-sm" data-modal="addAccountantModal">
            <i class="bx bx-user-plus"></i> Ajouter un comptable
          </button>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table" id="accountantsTable">
          <thead>
            <tr>
              <th>Comptable</th><th>Matricule</th><th>Telephone</th><th>Statut</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accountants as $a):
              $status = $a['is_active'] ? 'used' : ($a['inv_status'] ?: 'pending');
              [$badgeColor, $badgeLabel] = $invBadge[$status] ?? ['gray', 'Inconnu'];
            ?>
            <tr>
              <td class="td-user">
                <div class="avatar avatar-32" style="background:var(--purple)"><?= getInitials($a['first_name'], $a['last_name']) ?></div>
                <div>
                  <div class="td-name"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                  <div class="td-sub"><?= clean($a['email']) ?></div>
                </div>
              </td>
              <td class="text-sm"><?= clean($a['staff_number'] ?: '—') ?></td>
              <td class="text-sm"><?= clean($a['phone'] ?: '—') ?></td>
              <td>
                <span class="badge badge-<?= $badgeColor ?>"><?= $badgeLabel ?></span>
                <?php if (!$a['is_active'] && $status === 'pending' && $a['inv_expires']): ?>
                  <div class="text-xs text-muted" style="margin-top:3px">Expire le <?= formatDateTime($a['inv_expires']) ?></div>
                <?php endif; ?>
              </td>
              <td class="td-actions">
                <?php if (!$a['is_active']): ?>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend">
                    <input type="hidden" name="user_id" value="<?= $a['user_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Renvoyer l'invitation">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </form>
                  <?php if ($a['inv_id'] && $status === 'pending'): ?>
                  <form method="POST" style="display:inline" data-confirm="Annuler cette invitation ?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="invitation_id" value="<?= $a['inv_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Annuler l'invitation" style="color:var(--danger)">
                      <i class="bx bx-x-circle"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-xs text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══ Modal : Ajouter un comptable ══ -->
<div class="modal-overlay" id="addAccountantModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="invite">
      <div class="modal-header">
        <h3><i class="bx bx-user-plus"></i> Inviter un comptable</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Prenom <span class="form-required">*</span></label>
            <input type="text" name="first_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom <span class="form-required">*</span></label>
            <input type="text" name="last_name" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email professionnel <span class="form-required">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="marie.kabongo@email.com" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" class="form-control" placeholder="+243 00 000 0000">
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="gender" class="form-control">
              <option value="F">Feminin</option>
              <option value="M">Masculin</option>
            </select>
          </div>
        </div>

        <div class="alert alert-info" style="margin-bottom:0">
          <i class="bx bx-info-circle"></i> Le matricule professionnel est genere automatiquement. Un email d'invitation
          sera envoye pour que le comptable active son compte et definisse lui-meme son mot de passe.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-send"></i> Creer et envoyer l'invitation</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
