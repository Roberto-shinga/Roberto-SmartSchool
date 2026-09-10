<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireParent();

$pageTitle   = 'Paiements';
$pageSection = 'payments';
$user        = currentUser();

$children = getParentChildren($user['id']);
if (empty($children)) {
    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    echo '<div class="main-content"><div class="page-wrapper"><div class="empty-state">
          <div class="empty-state-icon"><i class="bx bx-group"></i></div><h3>Aucun enfant lie</h3></div></div></div>';
    require INCLUDES_PATH . '/footer.php';
    exit;
}

$studentId = (int)($_POST['student_id'] ?? $_GET['student_id'] ?? $children[0]['id']);

// GARDE-FOU : verification serveur du lien parent-enfant, y compris sur
// le formulaire POST (ne jamais faire confiance au champ cache seul).
if (!parentOwnsStudent($user['id'], $studentId)) {
    redirectWith($_SERVER['PHP_SELF'], 'danger', "Cet eleve n'est pas lie a ton compte.");
}

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$student     = dbFetchOne("SELECT * FROM students WHERE id=?", [$studentId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $feeId  = (int)($_POST['fee_id'] ?? 0);
    $amount = (float)($_POST['amount_paid'] ?? 0);
    $method = in_array($_POST['payment_method'] ?? '', ['especes','virement','cheque','mobile_money','carte']) ? $_POST['payment_method'] : 'virement';
    $fee    = dbFetchOne("SELECT * FROM fees WHERE id=? AND academic_year_id=?", [$feeId, $yearId]);

    if (!$fee || $amount <= 0) {
        redirectWith($_SERVER['PHP_SELF'] . "?student_id=$studentId", 'danger', 'Frais et montant valides obligatoires.');
    } elseif (empty($_FILES['proof']['name'])) {
        redirectWith($_SERVER['PHP_SELF'] . "?student_id=$studentId", 'danger', 'Une preuve de paiement (photo ou PDF du recu) est obligatoire.');
    } else {
        $upload = uploadFile($_FILES['proof'], 'payment_proofs', array_merge(ALLOWED_IMG, ['pdf']));
        if (!$upload['success']) {
            redirectWith($_SERVER['PHP_SELF'] . "?student_id=$studentId", 'danger', $upload['error']);
        } else {
            dbExecute(
                "INSERT INTO payments (student_id, fee_id, amount_paid, payment_method, receipt_number, status, notes)
                 VALUES (?, ?, ?, ?, ?, 'en_attente', 'Declare par le parent, en attente de validation')",
                [$studentId, $feeId, $amount, $method, generateReceiptNumber()]
            );
            $paymentId = dbLastId();
            dbExecute(
                "INSERT INTO payment_proofs (payment_id, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)",
                [$paymentId, $_FILES['proof']['name'], $upload['filename'], $user['id']]
            );
            logActivity('payment_declared', "Paiement declare par le parent pour l'eleve #$studentId : " . formatMoney($amount));
            redirectWith($_SERVER['PHP_SELF'] . "?student_id=$studentId", 'success', 'Paiement declare. Il sera valide par l\'administration.');
        }
    }
}

$applicableFees = dbFetchAll(
    "SELECT f.*, fc.name AS category_name FROM fees f JOIN fee_categories fc ON f.category_id = fc.id
     WHERE f.academic_year_id = ? AND (f.class_id = ? OR (f.class_id IS NULL AND (f.level_id = ? OR f.level_id IS NULL)))
     ORDER BY f.due_date",
    [$yearId, $student['class_id'], $student['level_id']]
);
$totalDue  = array_sum(array_column($applicableFees, 'amount'));
$totalPaid = dbFetchOne("SELECT COALESCE(SUM(amount_paid),0) t FROM payments WHERE student_id=? AND status IN ('paye','partiel')", [$studentId])['t'] ?? 0;
$balance   = max(0, $totalDue - $totalPaid);

$payments = dbFetchAll(
    "SELECT p.*, fc.name AS category_name FROM payments p JOIN fees f ON p.fee_id=f.id JOIN fee_categories fc ON f.category_id=fc.id
     WHERE p.student_id=? ORDER BY p.payment_date DESC",
    [$studentId]
);
$statusBadge = ['paye'=>'success','partiel'=>'warning','en_attente'=>'gray','rejete'=>'danger','annule'=>'danger'];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Paiements</h1><p>Frais scolaires et historique</p></div>
      <div class="page-header-actions">
        <?php if (count($children) > 1): ?>
        <form method="GET">
          <select name="student_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?><option value="<?= $c['id'] ?>" <?= $studentId===$c['id']?'selected':'' ?>><?= clean($c['fn'] . ' ' . $c['ln']) ?></option><?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
        <button class="btn btn-primary" data-modal="declarePaymentModal"><i class="bx bx-plus"></i> Declarer un paiement</button>
      </div>
    </div>

    <div class="grid-3 mb-6">
      <div class="stat-card c-primary"><div class="stat-icon c-primary"><i class="bx bx-receipt"></i></div><div><div class="stat-value" style="font-size:18px"><?= formatMoney($totalDue) ?></div><div class="stat-label">Total du</div></div></div>
      <div class="stat-card c-success"><div class="stat-icon c-success"><i class="bx bx-check-circle"></i></div><div><div class="stat-value" style="font-size:18px"><?= formatMoney($totalPaid) ?></div><div class="stat-label">Total paye</div></div></div>
      <div class="stat-card c-danger"><div class="stat-icon c-danger"><i class="bx bx-error-circle"></i></div><div><div class="stat-value" style="font-size:18px"><?= formatMoney($balance) ?></div><div class="stat-label">Solde restant</div></div></div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><h3><i class="bx bx-money"></i> Frais applicables</h3></div>
      <?php if (empty($applicableFees)): ?>
        <div class="card-body"><p class="text-sm text-muted">Aucun frais defini pour cette classe.</p></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table"><thead><tr><th>Categorie</th><th>Montant</th><th>Echeance</th></tr></thead>
        <tbody><?php foreach ($applicableFees as $f): ?>
          <tr><td class="text-sm"><?= clean($f['category_name']) ?></td><td class="font-semibold"><?= formatMoney($f['amount']) ?></td><td class="text-sm text-muted"><?= $f['due_date'] ? formatDate($f['due_date']) : '—' ?></td></tr>
        <?php endforeach; ?></tbody></table>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="bx bx-history"></i> Historique</h3></div>
      <?php if (empty($payments)): ?>
        <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-receipt"></i></div><h3>Aucun paiement declare</h3></div>
      <?php else: ?>
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table"><thead><tr><th>Recu</th><th>Categorie</th><th>Montant</th><th>Statut</th><th>Date</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?>
          <tr>
            <td class="text-sm font-mono"><?= clean($p['receipt_number']) ?></td>
            <td class="text-sm text-muted"><?= clean($p['category_name']) ?></td>
            <td class="font-semibold"><?= formatMoney($p['amount_paid']) ?></td>
            <td><span class="badge badge-<?= $statusBadge[$p['status']] ?? 'gray' ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
            <td class="text-sm text-muted"><?= formatDateTime($p['payment_date']) ?></td>
          </tr>
        <?php endforeach; ?></tbody></table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══ Modal : Declarer un paiement ══ -->
<div class="modal-overlay" id="declarePaymentModal">
  <div class="modal">
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="student_id" value="<?= $studentId ?>">
      <div class="modal-header">
        <h3><i class="bx bx-plus"></i> Declarer un paiement</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info"><i class="bx bx-info-circle"></i> Le paiement restera "en attente" jusqu'a validation de la preuve par l'administration.</div>
        <div class="form-group">
          <label class="form-label">Frais concerne <span class="form-required">*</span></label>
          <select name="fee_id" class="form-control" required>
            <option value="">Selectionner...</option>
            <?php foreach ($applicableFees as $f): ?><option value="<?= $f['id'] ?>"><?= clean($f['category_name']) ?> — <?= formatMoney($f['amount']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Montant verse (FC) <span class="form-required">*</span></label><input type="number" name="amount_paid" class="form-control" min="1" step="100" required></div>
          <div class="form-group">
            <label class="form-label">Methode</label>
            <select name="payment_method" class="form-control">
              <option value="virement">Virement</option><option value="mobile_money">Mobile money</option>
              <option value="especes">Especes</option><option value="cheque">Cheque</option><option value="carte">Carte</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Preuve de paiement (photo ou PDF) <span class="form-required">*</span></label>
          <input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
          <span class="form-hint">Formats acceptes : JPG, PNG, PDF — 10 Mo maximum.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-upload"></i> Envoyer</button>
      </div>
    </form>
  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
