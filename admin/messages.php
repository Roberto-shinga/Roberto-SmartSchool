<?php
// ============================================================
//  SmartSchool — Messagerie interne
//  Emplacement : admin/messages.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN);

$pageTitle   = 'Messages';
$pageSection = 'messages';
$user        = currentUser();

$allUsers = dbFetchAll(
    "SELECT u.id, u.first_name, u.last_name, r.label AS role_label
     FROM users u JOIN roles r ON u.role_id = r.id
     WHERE u.is_active = 1 AND u.id != ?
     ORDER BY r.id, u.last_name",
    [$user['id']]
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $subject    = trim($_POST['subject']      ?? '');
        $body       = trim($_POST['body']         ?? '');
        $parentId   = (int)($_POST['parent_id']   ?? 0) ?: null;

        if (!$receiverId || empty($subject) || empty($body)) {
            redirectWith(BASE_URL . '/admin/messages.php', 'danger', 'Tous les champs sont obligatoires.');
        }

        dbExecute(
            "INSERT INTO messages (sender_id, receiver_id, subject, body, parent_id)
             VALUES (?, ?, ?, ?, ?)",
            [$user['id'], $receiverId, $subject, $body, $parentId]
        );

        // Notifier le destinataire
        $receiver = dbFetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$receiverId]);
        createNotification(
            $receiverId,
            'Nouveau message',
            'Vous avez recu un message de ' . $user['first_name'] . ' ' . $user['last_name'] . ' : ' . $subject,
            'info'
        );

        logActivity('send_message', "Message envoye a l'utilisateur $receiverId : $subject");
        redirectWith(BASE_URL . '/admin/messages.php?view=sent', 'success', 'Message envoye avec succes !');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['message_id'] ?? 0);
        dbExecute("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)",
            [$id, $user['id'], $user['id']]);
        redirectWith(BASE_URL . '/admin/messages.php', 'success', 'Message supprime.');
    }

    if ($action === 'mark_read') {
        $id = (int)($_POST['message_id'] ?? 0);
        dbExecute("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?",
            [$id, $user['id']]);
        redirectWith(BASE_URL . '/admin/messages.php?view=inbox&id=' . $id, 'success', '');
    }
}

// ── Navigation ───────────────────────────────────────────
$view      = trim($_GET['view']    ?? 'inbox');
$messageId = (int)($_GET['id']     ?? 0);
$compose   = isset($_GET['compose']);

// Boite de reception
$inbox = dbFetchAll(
    "SELECT m.*, u.first_name, u.last_name,
            r.label AS sender_role
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     JOIN roles r ON u.role_id = r.id
     WHERE m.receiver_id = ?
     ORDER BY m.created_at DESC LIMIT 50",
    [$user['id']]
);

// Messages envoyes
$sent = dbFetchAll(
    "SELECT m.*, u.first_name, u.last_name,
            r.label AS receiver_role
     FROM messages m
     JOIN users u ON m.receiver_id = u.id
     JOIN roles r ON u.role_id = r.id
     WHERE m.sender_id = ?
     ORDER BY m.created_at DESC LIMIT 50",
    [$user['id']]
);

// Message ouvert
$openedMsg = null;
if ($messageId) {
    $openedMsg = dbFetchOne(
        "SELECT m.*,
                su.first_name AS s_fn, su.last_name AS s_ln, su.email AS s_email,
                ru.first_name AS r_fn, ru.last_name AS r_ln
         FROM messages m
         JOIN users su ON m.sender_id   = su.id
         JOIN users ru ON m.receiver_id = ru.id
         WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)",
        [$messageId, $user['id'], $user['id']]
    );
    // Marquer comme lu
    if ($openedMsg && $openedMsg['receiver_id'] == $user['id'] && !$openedMsg['is_read']) {
        dbExecute("UPDATE messages SET is_read = 1 WHERE id = ?", [$messageId]);
    }
}

$unreadCount = countUnreadMessages($user['id']);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-envelope" style="color:var(--primary)"></i> Messages
      <?php if ($unreadCount > 0): ?>
        <span class="badge badge-primary"><?= $unreadCount ?> non lu<?= $unreadCount > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </h1>
    <p>Messagerie interne de l'etablissement</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalCompose">
      <i class="bx bx-edit"></i> Nouveau message
    </button>
  </div>
</div>

<!-- Layout messagerie -->
<div style="display:grid;grid-template-columns:250px 1fr;gap:0;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;min-height:600px">

  <!-- BARRE LATERALE MESSAGERIE -->
  <div style="border-right:1px solid var(--border);display:flex;flex-direction:column">

    <div style="padding:16px">
      <button class="btn btn-primary btn-block" data-modal="modalCompose">
        <i class="bx bx-edit"></i> Nouveau
      </button>
    </div>

    <nav style="padding:0 8px">
      <a href="?view=inbox" style="
        display:flex;align-items:center;gap:10px;
        padding:10px 12px;border-radius:var(--radius);
        font-size:13.5px;font-weight:600;
        background:<?= $view==='inbox' ? 'var(--primary-bg)' : 'transparent' ?>;
        color:<?= $view==='inbox' ? 'var(--primary)' : 'var(--text-secondary)' ?>;
        text-decoration:none;margin-bottom:4px;
      ">
        <i class="bx bx-inbox"></i>
        Boite de reception
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge" style="margin-left:auto"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="?view=sent" style="
        display:flex;align-items:center;gap:10px;
        padding:10px 12px;border-radius:var(--radius);
        font-size:13.5px;font-weight:600;
        background:<?= $view==='sent' ? 'var(--primary-bg)' : 'transparent' ?>;
        color:<?= $view==='sent' ? 'var(--primary)' : 'var(--text-secondary)' ?>;
        text-decoration:none;
      ">
        <i class="bx bx-send"></i> Messages envoyes
      </a>
    </nav>

    <!-- Stats rapides -->
    <div style="margin-top:auto;padding:16px;border-top:1px solid var(--border-light)">
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;font-weight:700">Statistiques</div>
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
        <span style="color:var(--text-muted)">Recus</span>
        <span style="font-weight:700"><?= count($inbox) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12.5px">
        <span style="color:var(--text-muted)">Envoyes</span>
        <span style="font-weight:700"><?= count($sent) ?></span>
      </div>
    </div>
  </div>

  <!-- CONTENU PRINCIPAL -->
  <div style="display:flex;flex-direction:column">

    <?php if ($openedMsg): ?>
    <!-- VUE MESSAGE OUVERT -->
    <div style="padding:20px 24px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:12px">
      <a href="?view=<?= $view ?>" class="btn btn-sm btn-secondary btn-icon">
        <i class="bx bx-arrow-back"></i>
      </a>
      <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);flex:1">
        <?= clean($openedMsg['subject']) ?>
      </h3>
      <form method="POST" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="action"     value="delete">
        <input type="hidden" name="message_id" value="<?= $openedMsg['id'] ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                onclick="return confirm('Supprimer ce message ?')">
          <i class="bx bx-trash"></i>
        </button>
      </form>
    </div>

    <div style="padding:24px;flex:1">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border-light)">
        <div class="avatar avatar-48" style="background:var(--primary)">
          <?= getInitials($openedMsg['s_fn'], $openedMsg['s_ln']) ?>
        </div>
        <div style="flex:1">
          <div style="font-size:14px;font-weight:700;color:var(--text-primary)">
            <?= clean($openedMsg['s_fn']) ?> <?= clean($openedMsg['s_ln']) ?>
            <?php if ($openedMsg['sender_id'] == $user['id']): ?>
              <span style="font-size:12px;color:var(--text-muted);font-weight:400"> (moi)</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted)">
            A : <?= clean($openedMsg['r_fn']) ?> <?= clean($openedMsg['r_ln']) ?>
            &nbsp;&middot;&nbsp; <?= formatDateTime($openedMsg['created_at']) ?>
          </div>
        </div>
      </div>

      <div style="font-size:14px;color:var(--text-secondary);line-height:1.8;white-space:pre-line">
        <?= clean($openedMsg['body']) ?>
      </div>
    </div>

    <!-- Repondre -->
    <?php if ($openedMsg['receiver_id'] == $user['id']): ?>
    <div style="padding:20px 24px;border-top:1px solid var(--border-light);background:var(--bg-body)">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action"      value="send">
        <input type="hidden" name="receiver_id" value="<?= $openedMsg['sender_id'] ?>">
        <input type="hidden" name="subject"     value="Re: <?= clean($openedMsg['subject']) ?>">
        <input type="hidden" name="parent_id"   value="<?= $openedMsg['id'] ?>">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <div class="avatar avatar-32" style="background:var(--primary);flex-shrink:0">
            <?= getInitials($user['first_name'], $user['last_name']) ?>
          </div>
          <div style="flex:1">
            <textarea name="body" class="form-control" rows="3"
                      placeholder="Ecrire une reponse..." required
                      style="margin-bottom:10px"></textarea>
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bx bx-reply"></i> Repondre
            </button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- LISTE MESSAGES -->
    <div style="padding:12px 16px;border-bottom:1px solid var(--border-light);font-size:13px;font-weight:600;color:var(--text-muted)">
      <?= $view === 'inbox' ? 'Boite de reception' : 'Messages envoyes' ?>
    </div>

    <?php $list = $view === 'inbox' ? $inbox : $sent; ?>
    <?php if (empty($list)): ?>
      <div class="empty-state" style="flex:1">
        <div class="empty-state-icon">
          <i class="bx <?= $view==='inbox' ? 'bx-inbox' : 'bx-send' ?>"></i>
        </div>
        <h3>Aucun message</h3>
        <p><?= $view==='inbox' ? 'Votre boite est vide.' : 'Vous n\'avez encore rien envoye.' ?></p>
        <button class="btn btn-primary" data-modal="modalCompose">
          <i class="bx bx-edit"></i> Nouveau message
        </button>
      </div>
    <?php else: ?>
      <?php foreach ($list as $m):
        $isUnread = ($view === 'inbox' && !$m['is_read']);
        $name = $view === 'inbox'
          ? $m['first_name'] . ' ' . $m['last_name']
          : 'A : ' . $m['first_name'] . ' ' . $m['last_name'];
        $role = $view === 'inbox' ? ($m['sender_role'] ?? '') : ($m['receiver_role'] ?? '');
      ?>
      <a href="?view=<?= $view ?>&id=<?= $m['id'] ?>" style="
        display:flex;align-items:flex-start;gap:14px;
        padding:16px 20px;
        border-bottom:1px solid var(--border-light);
        background:<?= $isUnread ? 'var(--primary-bg)' : 'transparent' ?>;
        text-decoration:none;
        transition:background 0.15s;
      " onmouseenter="this.style.background='var(--bg-hover)'"
         onmouseleave="this.style.background='<?= $isUnread ? 'var(--primary-bg)' : 'transparent' ?>'">
        <div class="avatar avatar-40" style="background:<?= $isUnread ? 'var(--primary)' : 'var(--text-muted)' ?>;flex-shrink:0">
          <?= getInitials($m['first_name'], $m['last_name']) ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px">
            <span style="font-size:13.5px;font-weight:<?= $isUnread?'700':'500' ?>;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= clean($name) ?>
            </span>
            <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap"><?= timeAgo($m['created_at']) ?></span>
          </div>
          <div style="font-size:13px;font-weight:<?= $isUnread?'600':'400' ?>;color:<?= $isUnread?'var(--text-primary)':'var(--text-muted)' ?>;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= clean($m['subject']) ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px">
            <?= clean(substr($m['body'], 0, 80)) ?>...
          </div>
        </div>
        <?php if ($isUnread): ?>
          <span style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:6px"></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL COMPOSER -->
<div class="modal-overlay" id="modalCompose">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Nouveau message</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="send">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Destinataire <span class="form-required">*</span></label>
          <select name="receiver_id" class="form-control" required>
            <option value="">Selectionner un destinataire</option>
            <?php
            $currentGroup = '';
            foreach ($allUsers as $u):
              if ($u['role_label'] !== $currentGroup):
                if ($currentGroup) echo '</optgroup>';
                $currentGroup = $u['role_label'];
                echo '<optgroup label="' . clean($u['role_label']) . '">';
              endif;
            ?>
              <option value="<?= $u['id'] ?>"><?= clean($u['first_name']) ?> <?= clean($u['last_name']) ?></option>
            <?php endforeach; ?>
            <?php if ($currentGroup) echo '</optgroup>'; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Sujet <span class="form-required">*</span></label>
          <input type="text" name="subject" class="form-control"
                 placeholder="Objet du message" required
                 value="<?= clean($_GET['subject'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Message <span class="form-required">*</span></label>
          <textarea name="body" class="form-control" rows="6"
                    placeholder="Ecrivez votre message ici..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-send"></i> Envoyer le message
        </button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php
$pageScript = "";
if ($compose) $pageScript .= "document.addEventListener('DOMContentLoaded', () => SS_Modal.open('modalCompose'));";
require_once INCLUDES_PATH . '/footer.php';
?>