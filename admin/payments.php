<?php
// ============================================================
//  SmartSchool — Gestion des paiements
//  Emplacement : admin/payments.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT);

$pageTitle   = 'Gestion des paiements';
$pageSection = 'payments';
$user        = currentUser();

// Listes pour les selects
$students = dbFetchAll(
    "SELECT s.id, s.student_number, u.first_name, u.last_name
     FROM students s JOIN users u ON s.user_id = u.id
     WHERE s.status = 'actif' ORDER BY u.last_name, u.first_name"
);
$fees = dbFetchAll(
    "SELECT f.id, f.amount, fc.name, ay.name AS year_name
     FROM fees f
     JOIN fee_categories fc ON f.category_id = fc.id
     JOIN academic_years ay ON f.academic_year_id = ay.id
     ORDER BY ay.is_current DESC, fc.name"
);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // AJOUTER PAIEMENT
    if ($action === 'add') {
        $studentId = (int)($_POST['student_id']     ?? 0);
        $feeId     = (int)($_POST['fee_id']         ?? 0);
        $amount    = (float)($_POST['amount_paid']  ?? 0);
        $method    = trim($_POST['payment_method']  ?? 'especes');
        $status    = trim($_POST['status']          ?? 'paye');
        $notes     = trim($_POST['notes']           ?? '');
        $date      = trim($_POST['payment_date']    ?? date('Y-m-d H:i:s'));

        if (!$studentId || !$feeId || $amount <= 0) {
            redirectWith(BASE_URL . '/admin/payments.php', 'danger',
                'Eleve, frais et montant sont obligatoires.');
        }

        $receipt = generateReceiptNumber();

        try {
            dbExecute(
                "INSERT INTO payments
                 (student_id, fee_id, amount_paid, payment_date, payment_method, receipt_number, status, collected_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$studentId, $feeId, $amount, $date, $method, $receipt, $status, $user['id'], $notes]
            );
            logActivity('add_payment', "Paiement enregistre : $receipt — $amount FCFA");
            redirectWith(BASE_URL . '/admin/payments.php', 'success',
                "Paiement enregistre ! Recu : $receipt");
        } catch (Exception $e) {
            redirectWith(BASE_URL . '/admin/payments.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    // ANNULER PAIEMENT
    if ($action === 'cancel') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $payment   = dbFetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
        if ($payment) {
            dbExecute("UPDATE payments SET status = 'annule' WHERE id = ?", [$paymentId]);
            logActivity('cancel_payment', "Paiement annule : {$payment['receipt_number']}");
            redirectWith(BASE_URL . '/admin/payments.php', 'success', 'Paiement annule.');
        }
        redirectWith(BASE_URL . '/admin/payments.php', 'danger', 'Paiement introuvable.');
    }
}

// ── Filtres ──────────────────────────────────────────────
$search       = trim($_GET['search']  ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMethod = trim($_GET['method'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR p.receipt_number LIKE ?)";
    $s        = "%$search%";
    $params   = array_merge($params, [$s, $s, $s]);
}
if ($filterStatus) { $where[] = "p.status = '" . addslashes($filterStatus) . "'"; }
if ($filterMethod) { $where[] = "p.payment_method = '" . addslashes($filterMethod) . "'"; }

$whereStr = 'WHERE ' . implode(' AND ', $where);

$total = dbFetchOne(
    "SELECT COUNT(*) c
     FROM payments p
     JOIN students s ON p.student_id = s.id
     JOIN users u ON s.user_id = u.id
     $whereStr", $params
)['c'] ?? 0;

$pag      = paginate($total, $page);
$payments = dbFetchAll(
    "SELECT p.id, p.receipt_number, p.amount_paid, p.payment_date,
            p.payment_method, p.status, p.notes,
            u.first_name, u.last_name,
            s.student_number,
            fc.name AS fee_name,
            uc.first_name AS col_fn, uc.last_name AS col_ln
     FROM payments p
     JOIN students s  ON p.student_id  = s.id
     JOIN users u     ON s.user_id     = u.id
     JOIN fees f      ON p.fee_id      = f.id
     JOIN fee_categories fc ON f.category_id = fc.id
     JOIN users uc    ON p.collected_by = uc.id
     $whereStr
     ORDER BY p.payment_date DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// Stats financieres
$statsMonth = dbFetchOne(
    "SELECT COALESCE(SUM(amount_paid),0) total, COUNT(*) nb
     FROM payments
     WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())
     AND status='paye'"
) ?: ['total'=>0,'nb'=>0];

$statsTotal  = dbFetchOne("SELECT COALESCE(SUM(amount_paid),0) total FROM payments WHERE status='paye'")['total'] ?? 0;
$statsPending= dbFetchOne("SELECT COUNT(*) c FROM payments WHERE status='partiel'")['c'] ?? 0;

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<!-- ENTETE -->
<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-credit-card" style="color:var(--primary)"></i>
      Gestion des paiements
    </h1>
    <p><?= $total ?> paiement<?= $total > 1 ? 's' : '' ?> enregistre<?= $total > 1 ? 's' : '' ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" onclick="exportPayments()">
      <i class="bx bx-download"></i> Exporter
    </button>
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Nouveau paiement
    </button>
  </div>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:24px">
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-money"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:17px"><?= formatMoney($statsMonth['total']) ?></div>
      <div class="stat-label">Recettes ce mois</div>
      <span class="stat-change up">
        <i class="bx bx-receipt"></i> <?= $statsMonth['nb'] ?> paiements
      </span>
    </div>
  </div>
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-trending-up"></i></div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:17px"><?= formatMoney($statsTotal) ?></div>
      <div class="stat-label">Total cumule</div>
    </div>
  </div>
  <div class="stat-card c-warning">
    <div class="stat-icon c-warning"><i class="bx bx-time"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $statsPending ?></div>
      <div class="stat-label">Paiements partiels</div>
      <span class="stat-change down"><i class="bx bx-error"></i> A regulariser</span>
    </div>
  </div>
</div>

<!-- FILTRES -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="search-wrap" style="flex:1;min-width:200px">
        <i class="bx bx-search"></i>
        <input type="text" name="search" class="search-input"
               placeholder="Eleve, numero de recu..."
               value="<?= clean($search) ?>">
      </div>
      <select name="status" class="form-control" style="width:140px">
        <option value="">Tous statuts</option>
        <option value="paye"    <?= $filterStatus === 'paye'    ? 'selected' : '' ?>>Paye</option>
        <option value="partiel" <?= $filterStatus === 'partiel' ? 'selected' : '' ?>>Partiel</option>
        <option value="annule"  <?= $filterStatus === 'annule'  ? 'selected' : '' ?>>Annule</option>
      </select>
      <select name="method" class="form-control" style="width:160px">
        <option value="">Tous modes</option>
        <option value="especes"      <?= $filterMethod === 'especes'      ? 'selected' : '' ?>>Especes</option>
        <option value="virement"     <?= $filterMethod === 'virement'     ? 'selected' : '' ?>>Virement</option>
        <option value="mobile_money" <?= $filterMethod === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
        <option value="cheque"       <?= $filterMethod === 'cheque'       ? 'selected' : '' ?>>Cheque</option>
        <option value="carte"        <?= $filterMethod === 'carte'        ? 'selected' : '' ?>>Carte</option>
      </select>
      <button type="submit" class="btn btn-primary">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
      <?php if ($search || $filterStatus || $filterMethod): ?>
        <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-secondary">
          <i class="bx bx-x"></i> Effacer
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLEAU -->
<div class="card">
  <div class="table-wrap">
    <table class="table" id="paymentsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Eleve</th>
          <th>Recu</th>
          <th>Type de frais</th>
          <th>Montant</th>
          <th>Mode</th>
          <th>Date</th>
          <th>Collecte par</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="10">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="bx bx-credit-card"></i></div>
                <h3>Aucun paiement</h3>
                <p>Aucun paiement enregistre pour le moment.</p>
                <button class="btn btn-primary" data-modal="modalAdd">
                  <i class="bx bx-plus"></i> Premier paiement
                </button>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($payments as $i => $p): ?>
          <?php
            $statusColors = [
              'paye'    => ['badge'=>'success', 'icon'=>'bx-check-circle'],
              'partiel' => ['badge'=>'warning', 'icon'=>'bx-time'],
              'annule'  => ['badge'=>'danger',  'icon'=>'bx-x-circle'],
            ];
            $sc = $statusColors[$p['status']] ?? ['badge'=>'gray','icon'=>'bx-circle'];

            $methodIcons = [
              'especes'      => 'bx-money',
              'virement'     => 'bx-transfer',
              'mobile_money' => 'bx-mobile',
              'cheque'       => 'bx-file',
              'carte'        => 'bx-credit-card',
            ];
            $mi = $methodIcons[$p['payment_method']] ?? 'bx-wallet';
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px"><?= $pag['offset'] + $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:var(--primary)">
                  <?= getInitials($p['first_name'], $p['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($p['first_name']) ?> <?= clean($p['last_name']) ?></div>
                  <div class="td-sub"><?= clean($p['student_number']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <code style="font-size:11px;background:var(--success-bg);color:var(--success-dark);padding:2px 8px;border-radius:5px;font-weight:600">
                <?= clean($p['receipt_number']) ?>
              </code>
            </td>
            <td style="color:var(--text-muted)"><?= clean($p['fee_name']) ?></td>
            <td style="font-weight:700;color:var(--success);font-size:14px">
              <?= formatMoney($p['amount_paid']) ?>
            </td>
            <td>
              <span style="display:flex;align-items:center;gap:5px;font-size:13px;color:var(--text-muted)">
                <i class="bx <?= $mi ?>"></i>
                <?= clean(ucfirst(str_replace('_',' ',$p['payment_method']))) ?>
              </span>
            </td>
            <td style="font-size:12.5px;color:var(--text-muted)">
              <?= formatDateTime($p['payment_date']) ?>
            </td>
            <td style="font-size:12.5px;color:var(--text-muted)">
              <?= clean($p['col_fn']) ?> <?= clean($p['col_ln']) ?>
            </td>
            <td>
              <span class="badge badge-<?= $sc['badge'] ?>">
                <i class="bx <?= $sc['icon'] ?>"></i>
                <?= clean($p['status']) ?>
              </span>
            </td>
            <td>
              <div class="td-actions">
                <button class="btn btn-sm btn-secondary btn-icon"
                        title="Imprimer le recu"
                        onclick="printReceipt(<?= $p['id'] ?>)">
                  <i class="bx bx-printer"></i>
                </button>
                <?php if ($p['status'] !== 'annule'): ?>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"     value="cancel">
                  <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          onclick="return confirm('Annuler le paiement <?= addslashes($p['receipt_number']) ?> ?')">
                    <i class="bx bx-x-circle"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <span style="font-size:13px;color:var(--text-muted)">
      <?= $pag['offset']+1 ?> – <?= min($pag['offset']+$pag['per_page'],$total) ?>
      sur <?= $total ?> paiements
    </span>
    <nav class="pagination">
      <?php if ($pag['has_prev']): ?>
        <a href="?page=<?= $pag['prev_page'] ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&method=<?= $filterMethod ?>">
          <i class="bx bx-chevron-left"></i>
        </a>
      <?php endif; ?>
      <?php for ($pg = max(1,$pag['current_page']-2); $pg <= min($pag['total_pages'],$pag['current_page']+2); $pg++): ?>
        <?php if ($pg === $pag['current_page']): ?>
          <span class="active"><?= $pg ?></span>
        <?php else: ?>
          <a href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&method=<?= $filterMethod ?>"><?= $pg ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['has_next']): ?>
        <a href="?page=<?= $pag['next_page'] ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&method=<?= $filterMethod ?>">
          <i class="bx bx-chevron-right"></i>
        </a>
      <?php endif; ?>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL AJOUTER PAIEMENT -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-credit-card" style="color:var(--primary)"></i> Nouveau paiement</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">
          <!-- Eleve -->
          <div class="form-group">
            <label class="form-label">Eleve <span class="form-required">*</span></label>
            <select name="student_id" class="form-control" required>
              <option value="">Selectionner un eleve</option>
              <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>">
                  <?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?>
                  (<?= clean($s['student_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Type de frais -->
          <div class="form-group">
            <label class="form-label">Type de frais <span class="form-required">*</span></label>
            <select name="fee_id" class="form-control" id="feeSelect" required onchange="updateAmount(this)">
              <option value="">Selectionner les frais</option>
              <?php foreach ($fees as $f): ?>
                <option value="<?= $f['id'] ?>" data-amount="<?= $f['amount'] ?>">
                  <?= clean($f['name']) ?> — <?= formatMoney($f['amount']) ?>
                  (<?= clean($f['year_name']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Montant -->
          <div class="form-group">
            <label class="form-label">Montant paye <span class="form-required">*</span></label>
            <div class="input-wrap">
              <i class="bx bx-money input-icon"></i>
              <input type="number" name="amount_paid" id="amountInput"
                     class="form-control" placeholder="0" min="1" step="100" required>
            </div>
            <span class="form-hint" id="amountHint"></span>
          </div>

          <!-- Mode de paiement -->
          <div class="form-group">
            <label class="form-label">Mode de paiement</label>
            <select name="payment_method" class="form-control">
              <option value="especes">Especes</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="virement">Virement bancaire</option>
              <option value="cheque">Cheque</option>
              <option value="carte">Carte bancaire</option>
            </select>
          </div>

          <!-- Statut -->
          <div class="form-group">
            <label class="form-label">Statut du paiement</label>
            <select name="status" class="form-control">
              <option value="paye">Paye integralement</option>
              <option value="partiel">Paiement partiel</option>
            </select>
          </div>

          <!-- Date -->
          <div class="form-group">
            <label class="form-label">Date du paiement</label>
            <input type="datetime-local" name="payment_date" class="form-control"
                   value="<?= date('Y-m-d\TH:i') ?>">
          </div>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"
                    placeholder="Observations eventuelles..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-save"></i> Enregistrer le paiement
        </button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php
$schoolName = getSetting('school_name', APP_NAME);
$currency   = getSetting('currency', 'FCFA');

$pageScript = "
// Mise a jour du montant quand on choisit les frais
function updateAmount(select) {
  const opt    = select.options[select.selectedIndex];
  const amount = opt.dataset.amount || 0;
  document.getElementById('amountInput').value = amount;
  document.getElementById('amountHint').textContent =
    amount > 0 ? 'Montant total des frais : ' + parseFloat(amount).toLocaleString('fr-FR') + ' $currency' : '';
}

// Impression recu (fenetre d'impression)
function printReceipt(id) {
  const win = window.open(
    window.SS.baseUrl + '/admin/receipt.php?id=' + id,
    'recu',
    'width=600,height=700,scrollbars=yes'
  );
  win.focus();
}

// Export CSV
function exportPayments() {
  const rows = [['Eleve','Recu','Frais','Montant','Mode','Date','Statut']];
  document.querySelectorAll('#paymentsTable tbody tr').forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 9) return;
    rows.push([
      tds[1].querySelector('.td-name')?.textContent.trim() || '',
      tds[2].textContent.trim(),
      tds[3].textContent.trim(),
      tds[4].textContent.trim(),
      tds[5].textContent.trim(),
      tds[6].textContent.trim(),
      tds[8].textContent.trim(),
    ].map(v => '\"' + v.replace(/\"/g,'\"\"') + '\"').join(','));
  });
  const a    = document.createElement('a');
  a.href     = 'data:text/csv;charset=utf-8,' + encodeURIComponent(rows.join('\n'));
  a.download = 'paiements.csv';
  a.click();
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
