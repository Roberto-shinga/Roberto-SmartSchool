<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Paiements';
$pageSection = 'payments';
$admin       = currentUser();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Enregistrer un paiement ─────────────────────────────────
    if ($action === 'create') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $feeId     = (int)($_POST['fee_id'] ?? 0);
        $amount    = (float)($_POST['amount_paid'] ?? 0);
        $method    = in_array($_POST['payment_method'] ?? '', ['especes','virement','cheque','mobile_money','carte']) ? $_POST['payment_method'] : 'especes';
        $notes     = sanitizeString($_POST['notes'] ?? '');

        $fee = $feeId ? dbFetchOne("SELECT * FROM fees WHERE id=?", [$feeId]) : null;

        if (!$studentId || !$fee || $amount <= 0) {
            redirectWith($_SERVER['PHP_SELF'], 'danger', 'Eleve, frais et montant valides sont obligatoires.');
        } else {
            $status = $amount >= (float)$fee['amount'] ? 'paye' : 'partiel';
            dbExecute(
                "INSERT INTO payments (student_id, fee_id, amount_paid, payment_method, receipt_number, status, collected_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$studentId, $feeId, $amount, $method, generateReceiptNumber(), $status, $admin['id'], $notes ?: null]
            );
            $paymentId = dbLastId();
            logActivity('payment_recorded', "Paiement #$paymentId enregistre : " . formatMoney($amount));
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Paiement enregistre avec succes.');
        }
    }

    // ── Valider / rejeter une preuve de paiement ─────────────────
    if ($action === 'verify_proof') {
        $proofId = (int)($_POST['proof_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $reason   = sanitizeString($_POST['reject_reason'] ?? '');
        $proof    = dbFetchOne("SELECT * FROM payment_proofs WHERE id=?", [$proofId]);

        if ($proof && in_array($decision, ['valide','rejete'])) {
            dbExecute("UPDATE payment_proofs SET status=?, verified_by=?, verified_at=NOW(), reject_reason=? WHERE id=?",
                [$decision, $admin['id'], $decision === 'rejete' ? ($reason ?: null) : null, $proofId]);
            if ($decision === 'valide') {
                dbExecute("UPDATE payments SET status='paye' WHERE id=?", [$proof['payment_id']]);
            } else {
                dbExecute("UPDATE payments SET status='rejete' WHERE id=?", [$proof['payment_id']]);
            }
            logActivity('payment_proof_' . $decision, "Preuve #$proofId");
            redirectWith($_SERVER['PHP_SELF'], 'success', 'Preuve de paiement traitee.');
        }
    }
}

// ── Preuves en attente ────────────────────────────────────────
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

// ── Filtres et liste des paiements ────────────────────────────
$search    = trim($_GET['q'] ?? '');
$statusFlt = $_GET['status'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));

$where = ["1=1"]; $params = [];
if ($search !== '') {
    $where[] = "(p.receipt_number LIKE ? OR s.student_number LIKE ? OR COALESCE(s.first_name,u.first_name) LIKE ? OR COALESCE(s.last_name,u.last_name) LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
if ($statusFlt !== '' && in_array($statusFlt, ['en_attente','paye','partiel','rejete','annule'])) {
    $where[] = "p.status = ?"; $params[] = $statusFlt;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = dbFetchOne(
    "SELECT COUNT(*) c FROM payments p JOIN students s ON p.student_id=s.id LEFT JOIN users u ON s.user_id=u.id $whereSql",
    $params
)['c'] ?? 0;
$pg = paginate($total, $page);

$payments = dbFetchAll(
    "SELECT p.*, s.student_number, COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
            fc.name AS category_name, cu.first_name AS coll_fn, cu.last_name AS coll_ln
     FROM payments p
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     JOIN fees f ON p.fee_id = f.id
     JOIN fee_categories fc ON f.category_id = fc.id
     LEFT JOIN users cu ON p.collected_by = cu.id
     $whereSql
     ORDER BY p.payment_date DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$statusBadge = ['paye'=>'success','partiel'=>'warning','en_attente'=>'gray','rejete'=>'danger','annule'=>'danger'];

$allStudents = dbFetchAll(
    "SELECT s.id, s.student_number, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln
     FROM students s LEFT JOIN users u ON s.user_id=u.id WHERE s.status='actif' ORDER BY fn, ln"
);
$feesList = dbFetchAll(
    "SELECT f.id, f.amount, fc.name AS category_name FROM fees f JOIN fee_categories fc ON f.category_id=fc.id WHERE f.academic_year_id=? ORDER BY fc.name",
    [$yearId]
);

function qsUrlPay(int $page, string $q, string $status): string {
    return '?' . http_build_query(array_filter(['page'=>$page,'q'=>$q?:null,'status'=>$status?:null]));
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Paiements</h1><p><?= $total ?> paiement(s) enregistre(s)</p></div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-modal="addPaymentModal"><i class="bx bx-plus"></i> Nouveau paiement</button>
      </div>
    </div>

    <?php if (!empty($pendingProofs)): ?>
    <div class="card" style="margin-bottom:20px;border-color:var(--warning)">
      <div class="card-header"><h3><i class="bx bx-file-blank" style="color:var(--warning)"></i> Preuves de paiement a valider (<?= count($pendingProofs) ?>)</h3></div>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Recu</th><th>Montant</th><th>Fichier</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($pendingProofs as $pf): ?>
            <tr>
              <td class="text-sm"><?= clean($pf['fn'] . ' ' . $pf['ln']) ?><div class="text-xs text-muted"><?= clean($pf['student_number']) ?></div></td>
              <td class="text-sm font-mono"><?= clean($pf['receipt_number']) ?></td>
              <td class="font-semibold"><?= formatMoney($pf['amount_paid']) ?></td>
              <td class="text-sm"><a href="<?= BASE_URL ?>/uploads/<?= clean($pf['file_path']) ?>" target="_blank"><i class="bx bx-paperclip"></i> <?= clean($pf['file_name']) ?></a></td>
              <td class="td-actions">
                <form method="POST" style="display:inline" data-confirm="Valider cette preuve de paiement ?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="verify_proof">
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
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-3">
          <div class="form-group" style="margin-bottom:0;grid-column:span 2">
            <div class="search-wrap"><i class="bx bx-search"></i><input type="text" name="q" class="search-input" placeholder="Recu, matricule ou nom..." value="<?= clean($search) ?>"></div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <select name="status" class="form-control" onchange="this.form.submit()">
              <option value="">Tous statuts</option>
              <option value="paye" <?= $statusFlt==='paye'?'selected':'' ?>>Paye</option>
              <option value="partiel" <?= $statusFlt==='partiel'?'selected':'' ?>>Partiel</option>
              <option value="en_attente" <?= $statusFlt==='en_attente'?'selected':'' ?>>En attente</option>
              <option value="rejete" <?= $statusFlt==='rejete'?'selected':'' ?>>Rejete</option>
              <option value="annule" <?= $statusFlt==='annule'?'selected':'' ?>>Annule</option>
            </select>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <?php if (empty($payments)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-receipt"></i></div><h3>Aucun paiement trouve</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Eleve</th><th>Recu</th><th>Categorie</th><th>Montant</th><th>Methode</th><th>Statut</th><th>Encaisse par</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
              <td class="text-sm"><?= clean($p['fn'] . ' ' . $p['ln']) ?><div class="text-xs text-muted"><?= clean($p['student_number']) ?></div></td>
              <td class="text-sm font-mono"><?= clean($p['receipt_number']) ?></td>
              <td class="text-sm text-muted"><?= clean($p['category_name']) ?></td>
              <td class="font-semibold"><?= formatMoney($p['amount_paid']) ?></td>
              <td class="text-sm text-muted"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
              <td><span class="badge badge-<?= $statusBadge[$p['status']] ?? 'gray' ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
              <td class="text-sm text-muted"><?= $p['coll_fn'] ? clean($p['coll_fn'] . ' ' . $p['coll_ln']) : '—' ?></td>
              <td class="text-sm text-muted"><?= formatDateTime($p['payment_date']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pg['total_pages'] > 1): ?>
      <div class="pagination">
        <?php if ($pg['has_prev']): ?><a href="<?= qsUrlPay($pg['prev_page'], $search, $statusFlt) ?>"><i class="bx bx-chevron-left"></i></a><?php endif; ?>
        <?php for ($p = 1; $p <= $pg['total_pages']; $p++): ?>
          <?php if ($p === $pg['current_page']): ?><span class="active"><?= $p ?></span><?php else: ?><a href="<?= qsUrlPay($p, $search, $statusFlt) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($pg['has_next']): ?><a href="<?= qsUrlPay($pg['next_page'], $search, $statusFlt) ?>"><i class="bx bx-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══ Modal : Nouveau paiement ══ -->
<div class="modal-overlay" id="addPaymentModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h3><i class="bx bx-plus"></i> Nouveau paiement</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Eleve <span class="form-required">*</span></label>
          <select name="student_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($allStudents as $st): ?><option value="<?= $st['id'] ?>"><?= clean($st['fn'] . ' ' . $st['ln']) ?> — <?= clean($st['student_number']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Frais <span class="form-required">*</span></label>
          <select name="fee_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($feesList as $f): ?><option value="<?= $f['id'] ?>"><?= clean($f['category_name']) ?> — <?= formatMoney($f['amount']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Montant verse (FC) <span class="form-required">*</span></label><input type="number" name="amount_paid" class="form-control" min="1" step="100" required></div>
          <div class="form-group">
            <label class="form-label">Methode</label>
            <select name="payment_method" class="form-control">
              <option value="especes">Especes</option><option value="virement">Virement</option>
              <option value="cheque">Cheque</option><option value="mobile_money">Mobile money</option><option value="carte">Carte</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Modal : Rejeter une preuve ══ -->
<div class="modal-overlay" id="rejectProofModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="verify_proof">
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
