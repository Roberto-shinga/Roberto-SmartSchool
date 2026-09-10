<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Messages';
$pageSection = 'messages';
$admin       = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $subject    = sanitizeString($_POST['subject'] ?? '');
        $body       = sanitizeString($_POST['body'] ?? '');
        $parentId   = (int)($_POST['parent_id'] ?? 0) ?: null;

        if (!$receiverId || empty($subject) || empty($body)) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Destinataire, objet et message sont obligatoires.');
        } else {
            dbExecute("INSERT INTO messages (sender_id, receiver_id, subject, body, parent_id) VALUES (?, ?, ?, ?, ?)",
                [$admin['id'], $receiverId, $subject, $body, $parentId]);
            createNotification($receiverId, 'Nouveau message : ' . $subject, mb_substr($body, 0, 100), 'info', BASE_URL . '/admin/messages.php');
            logActivity('message_sent', "Message envoye a l'utilisateur #$receiverId");
            redirectWith($_SERVER['PHP_SELF'] . "?with=$receiverId", 'success', 'Message envoye.');
        }
    }

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        dbExecute("UPDATE messages SET is_read=1 WHERE id=? AND receiver_id=?", [$id, $admin['id']]);
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Marque comme lu.');
    }
}

$otherUserId = (int)($_GET['with'] ?? 0);

// ── Liste des conversations (regroupees par interlocuteur) ────
$conversations = dbFetchAll(
    "SELECT other.id AS other_id, other.first_name, other.last_name, other.role_id,
            MAX(m.created_at) AS last_date,
            SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM messages m
     JOIN users other ON other.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     GROUP BY other.id, other.first_name, other.last_name, other.role_id
     ORDER BY last_date DESC",
    [$admin['id'], $admin['id'], $admin['id'], $admin['id']]
);

$thread = [];
if ($otherUserId && dbFetchOne("SELECT id FROM users WHERE id=?", [$otherUserId])) {
    $thread = dbFetchAll(
        "SELECT m.*, u.first_name, u.last_name FROM messages m JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at",
        [$admin['id'], $otherUserId, $otherUserId, $admin['id']]
    );
    dbExecute("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?", [$admin['id'], $otherUserId]);
} else {
    $otherUserId = 0;
}

$allUsers = dbFetchAll("SELECT id, first_name, last_name, role_id FROM users WHERE is_active=1 AND id != ? ORDER BY first_name", [$admin['id']]);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Messages</h1><p>Messagerie interne</p></div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="newMessageModal"><i class="bx bx-edit"></i> Nouveau message</button>
      </div>
    </div>

    <div class="grid-2" style="grid-template-columns:320px 1fr;align-items:start">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-conversation"></i> Conversations</h3></div>
        <div style="max-height:560px;overflow-y:auto">
          <?php if (empty($conversations)): ?>
            <div class="empty-state" style="padding:24px 12px"><p class="text-sm text-muted">Aucune conversation.</p></div>
          <?php else: foreach ($conversations as $c): ?>
            <a href="<?= BASE_URL ?>/admin/messages.php?with=<?= $c['other_id'] ?>"
               style="display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border-light);text-decoration:none;<?= $otherUserId===(int)$c['other_id']?'background:var(--primary-bg)':'' ?>">
              <div class="avatar avatar-36" style="background:var(--primary)"><?= getInitials($c['first_name'], $c['last_name']) ?></div>
              <div style="flex:1;min-width:0">
                <div class="text-sm font-semibold" style="color:var(--text-primary)"><?= clean($c['first_name'] . ' ' . $c['last_name']) ?></div>
                <div class="text-xs text-muted"><?= clean(ROLE_LABELS[$c['role_id']] ?? '') ?></div>
              </div>
              <?php if ($c['unread'] > 0): ?><span class="badge badge-primary" style="height:fit-content"><?= $c['unread'] ?></span><?php endif; ?>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card" style="min-height:400px">
        <?php if (!$otherUserId): ?>
          <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-conversation"></i></div><h3>Selectionne une conversation</h3></div>
        <?php else: ?>
        <div class="card-header"><h3><i class="bx bx-user"></i> <?= clean($thread[0]['first_name'] ?? '') ?></h3></div>
        <div class="card-body" style="max-height:420px;overflow-y:auto;display:flex;flex-direction:column;gap:12px">
          <?php foreach ($thread as $m): $mine = (int)$m['sender_id'] === (int)$admin['id']; ?>
            <div style="max-width:75%;<?= $mine ? 'align-self:flex-end' : '' ?>">
              <div style="background:<?= $mine ? 'var(--grad-primary)' : 'var(--bg-hover)' ?>;color:<?= $mine ? '#fff' : 'var(--text-primary)' ?>;padding:10px 14px;border-radius:var(--radius)">
                <div class="text-xs font-semibold" style="margin-bottom:2px;opacity:.85"><?= clean($m['subject']) ?></div>
                <div class="text-sm"><?= nl2br(clean($m['body'])) ?></div>
              </div>
              <div class="text-xs text-muted" style="margin-top:3px;<?= $mine ? 'text-align:right' : '' ?>"><?= timeAgo($m['created_at']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border-light)">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="receiver_id" value="<?= $otherUserId ?>">
            <input type="hidden" name="subject" value="Re: conversation">
            <div style="display:flex;gap:8px">
              <input type="text" name="body" class="form-control" placeholder="Repondre..." required>
              <button type="submit" class="btn btn-primary"><i class="bx bx-send"></i></button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ══ Modal : Nouveau message ══ -->
<div class="modal-overlay" id="newMessageModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="send">
      <div class="modal-header">
        <h3><i class="bx bx-edit"></i> Nouveau message</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Destinataire <span class="form-required">*</span></label>
          <select name="receiver_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= $u['id'] ?>"><?= clean($u['first_name'] . ' ' . $u['last_name']) ?> — <?= clean(ROLE_LABELS[$u['role_id']] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Objet <span class="form-required">*</span></label><input type="text" name="subject" class="form-control" required></div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Message <span class="form-required">*</span></label><textarea name="body" class="form-control" rows="4" required></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-send"></i> Envoyer</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
