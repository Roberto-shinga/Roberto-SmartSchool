<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ACCOUNTANT);

$pageTitle  = 'Preuves bancaires';
$pageSection = 'proofs';
$accountant = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $proofId  = (int)($_POST['proof_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $reason   = sanitizeString($_POST['reject_reason'] ?? '');
    $proof    = dbFetchOne("SELECT * FROM payment_proofs WHERE id=?", [$proofId]);

    if ($proof && in_array($decision, ['valide','rejete'])) {
        dbExecute("UPDATE payment_proofs SET status=?, verified_by=?, verified_at=NOW(), reject_reason=? WHERE id=?",
            [$decision, $accountant['id'], $decision === 'rejete' ? ($reason ?: null) : null, $proofId]);
        if ($decision === 'valide') {
            dbExecute("UPDATE payments SET status='paye' WHERE id=?", [$proof['payment_id']]);
        } else {
            dbExecute("UPDATE payments SET status='rejete' WHERE id=?", [$proof['payment_id']]);
        }
        logActivity('payment_proof_' . $decision, "Preuve #$proofId");
        redirectWith($_SERVER['PHP_SELF'], 'success', 'Preuve de paiement traitee.');
    }
}

$tab = $_GET['tab'] ?? 'pending';

$pendingProofs = dbFetchAll(
    "SELECT pp.*, p.receipt_number, p.amount_paid, s.student_number,
            COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln
     FROM payment_proofs pp
     JOIN payments p ON pp.payment_id = p.id
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     WHERE pp.status = 'en_attente'
     ORDER BY pp.created_at"
);

$historyProofs = $tab === 'history' ? dbFetchAll(
    "SELECT pp.*, p.receipt_number, p.amount_paid, s.student_number,
            COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
            vu.first_name AS v_fn, vu.last_name AS v_ln
     FROM payment_proofs pp
     JOIN payments p ON pp.payment_id = p.id
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     LEFT JOIN users vu ON pp.verified_by = vu.id
     WHERE pp.status != 'en_attente'
     ORDER BY pp.verified_at DESC LIMIT 100"
) : [];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Preuves bancaires</h1><p>Validation des preuves de paiement uploadees</p></div>
    </div>

    <div class="tabs">
      <a href="?tab=pending" class="tab-btn <?= $tab==='pending'?'active':'' ?>">En attente (<?= count($pendingProofs) ?>)</a>
      <a href="?tab=history" class="tab-btn <?= $tab==='history'?'active':'' ?>">Historique</a>
    </div>

    <?php if ($tab === 'pending'): ?>
    <div class="card">
      <?php if (empty($pendingProofs)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-check-circle"></i></div><h3>Aucune preuve en attente</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Recu</th><th>Montant</th><th>Fichier</th><th>Envoye le</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($pendingProofs as $pf): ?>
            <tr>
              <td class="text-sm"><?= clean($pf['fn'] . ' ' . $pf['ln']) ?><div class="text-xs text-muted"><?= clean($pf['student_number']) ?></div></td>
              <td class="text-sm font-mono"><?= clean($pf['receipt_number']) ?></td>
              <td class="font-semibold"><?= formatMoney($pf['amount_paid']) ?></td>
              <td class="text-sm"><a href="<?= UPLOADS_URL ?>/payment_proofs/<?= clean($pf['file_path']) ?>" target="_blank"><i class="bx bx-paperclip"></i> <?= clean($pf['file_name']) ?></a></td>
              <td class="text-sm text-muted"><?= timeAgo($pf['created_at']) ?></td>
              <td class="td-actions">
                <form method="POST" style="display:inline" data-confirm="Valider cette preuve de paiement ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="proof_id" value="<?= $pf['id'] ?>">
                  <input type="hidden" name="decision" value="valide">
                  <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--success)" title="Valider"><i class="bx bx-check-circle"></i></button>
                </form>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" style="color:var(--danger)" title="Rejeter"
                        onclick="document.getElementById('reject_proof_id').value='<?= $pf['id'] ?>';SS.openModal('rejectProofModal')">
                  <i class="bx bx-x-circle"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card">
      <?php if (empty($historyProofs)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-history"></i></div><h3>Aucune preuve traitee pour l'instant</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Recu</th><th>Montant</th><th>Statut</th><th>Traite par</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($historyProofs as $pf): ?>
            <tr>
              <td class="text-sm"><?= clean($pf['fn'] . ' ' . $pf['ln']) ?><div class="text-xs text-muted"><?= clean($pf['student_number']) ?></div></td>
              <td class="text-sm font-mono"><?= clean($pf['receipt_number']) ?></td>
              <td class="font-semibold"><?= formatMoney($pf['amount_paid']) ?></td>
              <td>
                <span class="badge badge-<?= $pf['status']==='valide'?'success':'danger' ?>"><?= $pf['status']==='valide'?'Validee':'Rejetee' ?></span>
                <?php if ($pf['reject_reason']): ?><div class="text-xs text-muted" style="margin-top:2px"><?= clean($pf['reject_reason']) ?></div><?php endif; ?>
              </td>
              <td class="text-sm text-muted"><?= $pf['v_fn'] ? clean($pf['v_fn'] . ' ' . $pf['v_ln']) : '—' ?></td>
              <td class="text-sm text-muted"><?= formatDateTime($pf['verified_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : Rejeter une preuve ══ -->
<div class="modal-overlay" id="rejectProofModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="decision" value="rejete">
      <input type="hidden" name="proof_id" id="reject_proof_id">
      <div class="modal-header">
        <h3><i class="bx bx-x-circle" style="color:var(--danger)"></i> Rejeter la preuve</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Motif du rejet</label>
          <textarea name="reject_reason" class="form-control" rows="3" placeholder="Ex : montant illisible, reference incorrecte..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-danger"><i class="bx bx-x"></i> Rejeter</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
