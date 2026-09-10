<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Messages';
$pageSection = 'messages';
$user        = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $subject    = sanitizeString($_POST['subject'] ?? 'Nouveau message');
    $body       = sanitizeString($_POST['body'] ?? '');

    if (!$receiverId || empty($body)) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', 'Destinataire et message obligatoires.');
    } else {
        dbExecute("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)", [$user['id'], $receiverId, $subject, $body]);
        createNotification($receiverId, 'Nouveau message : ' . $subject, mb_substr($body, 0, 100), 'info', BASE_URL . '/students/messages.php');
        logActivity('message_sent', "Message envoye a l'utilisateur #$receiverId");
        redirectWith($_SERVER['PHP_SELF'] . "?with=$receiverId", 'success', 'Message envoye.');
    }
}

$otherUserId = (int)($_GET['with'] ?? 0);

$conversations = dbFetchAll(
    "SELECT other.id AS other_id, other.first_name, other.last_name, other.role_id,
            MAX(m.created_at) AS last_date,
            SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM messages m JOIN users other ON other.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     GROUP BY other.id, other.first_name, other.last_name, other.role_id ORDER BY last_date DESC",
    [$user['id'], $user['id'], $user['id'], $user['id']]
);

$thread = [];
if ($otherUserId && dbFetchOne("SELECT id FROM users WHERE id=?", [$otherUserId])) {
    $thread = dbFetchAll(
        "SELECT m.*, u.first_name FROM messages m JOIN users u ON m.sender_id=u.id
         WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at",
        [$user['id'], $otherUserId, $otherUserId, $user['id']]
    );
    dbExecute("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?", [$user['id'], $otherUserId]);
} else {
    $otherUserId = 0;
}

// Destinataires possibles : les enseignants de ses classes + l'administration
$studentId = getCurrentStudentId();
$student   = dbFetchOne("SELECT class_id FROM students WHERE id=?", [$studentId]);
$possibleRecipients = dbFetchAll(
    "SELECT DISTINCT u.id, u.first_name, u.last_name, u.role_id
     FROM users u
     LEFT JOIN teachers t ON t.user_id = u.id
     LEFT JOIN teacher_assignments ta ON ta.teacher_id = t.id AND ta.class_id = ?
     WHERE u.is_active = 1 AND (u.role_id IN (?, ?) OR ta.id IS NOT NULL)
     ORDER BY u.role_id, u.first_name",
    [$student['class_id'] ?? 0, ROLE_SUPER_ADMIN, ROLE_ADMIN]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Messages</h1><p>Enseignants et administration</p></div>
      <div class="page-header-actions"><button class="btn btn-primary" data-modal="newMessageModal"><i class="bx bx-edit"></i> Nouveau message</button></div>
    </div>

    <div class="grid-2" style="grid-template-columns:320px 1fr;align-items:start">
      <div class="card">
        <div class="card-header"><h3><i class="bx bx-conversation"></i> Conversations</h3></div>
        <div style="max-height:560px;overflow-y:auto">
          <?php if (empty($conversations)): ?>
            <div class="empty-state" style="padding:24px 12px"><p class="text-sm text-muted">Aucune conversation.</p></div>
          <?php else: foreach ($conversations as $c): ?>
            <a href="<?= BASE_URL ?>/students/messages.php?with=<?= $c['other_id'] ?>"
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
          <?php foreach ($thread as $m): $mine = (int)$m['sender_id'] === (int)$user['id']; ?>
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
      <div class="modal-header">
        <h3><i class="bx bx-edit"></i> Nouveau message</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Destinataire <span class="form-required">*</span></label>
          <select name="receiver_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($possibleRecipients as $r): ?>
              <option value="<?= $r['id'] ?>"><?= clean($r['first_name'] . ' ' . $r['last_name']) ?> — <?= clean(ROLE_LABELS[$r['role_id']] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Objet</label><input type="text" name="subject" class="form-control" value="Nouveau message"></div>
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
