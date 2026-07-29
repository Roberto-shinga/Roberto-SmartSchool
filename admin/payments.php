<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Paiements';
$pageSection = 'payments';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current=1");
$yearId      = $currentYear['id'] ?? 1;

// ── Traitement POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Enregistrer un paiement
    if ($action === 'add') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $feeId     = (int)($_POST['fee_id']     ?? 0);
        $amount    = (float)($_POST['amount']   ?? 0);
        $method    = trim($_POST['method']      ?? 'especes');
        $notes     = trim($_POST['notes']       ?? '');
        $date      = trim($_POST['payment_date']?? date('Y-m-d H:i:s'));

        if ($studentId && $feeId && $amount > 0) {
            $receipt = generateReceiptNumber();
            dbExecute(
                "INSERT INTO payments (student_id, fee_id, amount_paid, payment_date, payment_method, receipt_number, status, collected_by, notes)
                 VALUES (?,?,?,?,?,?,'paye',?,?)",
                [$studentId, $feeId, $amount, $date, $method, $receipt, $user['id'], $notes]
            );
            logActivity('add_payment', "Paiement $receipt — $amount FC — élève #$studentId");
            redirectWith(BASE_URL . '/admin/payments.php', 'success', "Paiement enregistré ! Reçu : $receipt");
        } else {
            redirectWith(BASE_URL . '/admin/payments.php?action=add', 'danger', 'Données invalides.');
        }
    }

    // Valider une preuve bancaire
    if ($action === 'validate_proof') {
        $proofId = (int)($_POST['proof_id'] ?? 0);
        dbExecute(
            "UPDATE payment_proofs SET status='valide', verified_by=?, verified_at=NOW() WHERE id=?",
            [$user['id'], $proofId]
        );
        // Marquer le paiement comme payé
        $proof = dbFetchOne("SELECT payment_id FROM payment_proofs WHERE id=?", [$proofId]);
        if ($proof) {
            dbExecute("UPDATE payments SET status='paye' WHERE id=?", [$proof['payment_id']]);
        }
        logActivity('validate_proof', "Preuve #$proofId validée");
        redirectWith(BASE_URL . '/admin/payments.php?tab=proofs', 'success', 'Preuve validée et paiement confirmé.');
    }

    // Rejeter une preuve
    if ($action === 'reject_proof') {
        $proofId = (int)($_POST['proof_id']    ?? 0);
        $reason  = trim($_POST['reject_reason']?? '');
        dbExecute(
            "UPDATE payment_proofs SET status='rejete', verified_by=?, verified_at=NOW(), reject_reason=? WHERE id=?",
            [$user['id'], $reason, $proofId]
        );
        logActivity('reject_proof', "Preuve #$proofId rejetée : $reason");
        redirectWith(BASE_URL . '/admin/payments.php?tab=proofs', 'success', 'Preuve rejetée.');
    }

    // Supprimer un paiement
    if ($action === 'delete') {
        $pid = (int)($_POST['payment_id'] ?? 0);
        dbExecute("DELETE FROM payments WHERE id=?", [$pid]);
        logActivity('delete_payment', "Paiement #$pid supprimé");
        redirectWith(BASE_URL . '/admin/payments.php', 'success', 'Paiement supprimé.');
    }
}

// ── Filtres ──────────────────────────────────────────────────
$tab      = $_GET['tab']    ?? 'list';
$search   = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterMethod = $_GET['method'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));

// ── Liste paiements ──────────────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR p.receipt_number LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s,$s,$s,$s,$s]);
}
if ($filterStatus) { $where[] = "p.status=?"; $params[] = $filterStatus; }
if ($filterMethod) { $where[] = "p.payment_method=?"; $params[] = $filterMethod; }
$whereStr = implode(' AND ', $where);

$total = dbFetchOne(
    "SELECT COUNT(*) c FROM payments p
     JOIN students s ON p.student_id=s.id
     LEFT JOIN users u ON s.user_id=u.id
     WHERE $whereStr", $params
)['c'] ?? 0;
$pag = paginate($total, $page);

$payments = dbFetchAll(
    "SELECT p.*, 
            COALESCE(u.first_name, s.first_name) fn, COALESCE(u.last_name, s.last_name) ln,
            s.student_number, fc.name AS fee_name, c.name AS class_name,
            uc.first_name AS col_fn, uc.last_name AS col_ln
     FROM payments p
     JOIN students s ON p.student_id=s.id
     LEFT JOIN users u ON s.user_id=u.id
     LEFT JOIN classes c ON s.class_id=c.id
     JOIN fees f ON p.fee_id=f.id
     JOIN fee_categories fc ON f.category_id=fc.id
     LEFT JOIN users uc ON p.collected_by=uc.id
     WHERE $whereStr
     ORDER BY p.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// ── Preuves en attente ───────────────────────────────────────
$proofs = dbFetchAll(
    "SELECT pp.*, p.receipt_number, p.amount_paid,
            COALESCE(u.first_name,s.first_name) fn, COALESCE(u.last_name,s.last_name) ln,
            s.student_number
     FROM payment_proofs pp
     JOIN payments p ON pp.payment_id=p.id
     JOIN students s ON p.student_id=s.id
     LEFT JOIN users u ON s.user_id=u.id
     ORDER BY pp.created_at DESC"
);
$pendingCount = count(array_filter($proofs, fn($p) => $p['status'] === 'en_attente'));

// ── Stats rapides ─────────────────────────────────────────────
$statsMonth = dbFetchOne(
    "SELECT COALESCE(SUM(CASE WHEN status='paye' THEN amount_paid ELSE 0 END),0) total,
            COUNT(*) nb
     FROM payments
     WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())"
);

// ── Données pour ajout ────────────────────────────────────────
$students = dbFetchAll(
    "SELECT s.id, s.student_number, s.first_name AS sfn, s.last_name AS sln,
            u.first_name AS ufn, u.last_name AS uln, c.name AS class_name
     FROM students s
     LEFT JOIN users u ON s.user_id=u.id
     LEFT JOIN classes c ON s.class_id=c.id
     WHERE s.status='actif'
     ORDER BY COALESCE(u.last_name,s.last_name)"
);
$fees = dbFetchAll(
    "SELECT f.id, f.amount, fc.name, f.due_date
     FROM fees f JOIN fee_categories fc ON f.category_id=fc.id
     WHERE f.academic_year_id=?
     ORDER BY fc.name", [$yearId]
);

$statusBadge = [
    'paye'       => ['badge-success', 'Payé'],
    'partiel'    => ['badge-warning', 'Partiel'],
    'en_attente' => ['badge-gray',    'En attente'],
    'rejete'     => ['badge-danger',  'Rejeté'],
    'annule'     => ['badge-danger',  'Annulé'],
];
$methodLabels = [
    'especes'      => 'Espèces',
    'virement'     => 'Virement',
    'cheque'       => 'Chèque',
    'mobile_money' => 'Mobile Money',
    'carte'        => 'Carte',
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1>Paiements</h1>
    <p><?= $total ?> paiement<?= $total > 1 ? 's' : '' ?> enregistré<?= $total > 1 ? 's' : '' ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Nouveau paiement
    </button>
  </div>
</div>

<!-- Stats rapides -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:22px">
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-money"></i></div>
    <div>
      <div class="stat-value" style="font-size:18px"><?= formatMoney($statsMonth['total']) ?></div>
      <div class="stat-label">Encaissé ce mois</div>
    </div>
  </div>
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-receipt"></i></div>
    <div>
      <div class="stat-value"><?= $statsMonth['nb'] ?></div>
      <div class="stat-label">Transactions ce mois</div>
    </div>
  </div>
  <?php if ($pendingCount > 0): ?>
  <div class="stat-card c-warning" style="cursor:pointer" onclick="location.href='?tab=proofs'">
    <div class="stat-icon c-warning"><i class="bx bx-file-blank"></i></div>
    <div>
      <div class="stat-value"><?= $pendingCount ?></div>
      <div class="stat-label">Preuves à valider</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Onglets -->
<div class="tabs">
  <button class="tab-btn <?= $tab==='list'?'active':'' ?>" onclick="location.href='?tab=list'">
    <i class="bx bx-list-ul"></i> Liste des paiements
  </button>
  <button class="tab-btn <?= $tab==='proofs'?'active':'' ?>" onclick="location.href='?tab=proofs'">
    <i class="bx bx-file-blank"></i> Preuves bancaires
    <?php if ($pendingCount > 0): ?>
      <span class="badge badge-warning" style="margin-left:4px"><?= $pendingCount ?></span>
    <?php endif; ?>
  </button>
</div>

<?php if ($tab === 'list'): ?>

<!-- Filtres -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="tab" value="list">
      <div class="search-wrap" style="flex:1;min-width:200px">
        <i class="bx bx-search"></i>
        <input type="text" name="q" class="search-input" placeholder="Élève, reçu..." value="<?= clean($search) ?>">
      </div>
      <select name="status" class="form-control" style="width:140px">
        <option value="">Tous statuts</option>
        <?php foreach ($statusBadge as $k => [$cls,$lbl]): ?>
          <option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <select name="method" class="form-control" style="width:150px">
        <option value="">Toutes méthodes</option>
        <?php foreach ($methodLabels as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $filterMethod===$k?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt"></i> Filtrer</button>
      <?php if ($search || $filterStatus || $filterMethod): ?>
        <a href="?tab=list" class="btn btn-secondary">Réinitialiser</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Tableau -->
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Élève</th><th>Reçu</th><th>Frais</th><th>Montant</th>
        <th>Méthode</th><th>Statut</th><th>Date</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($payments)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-state-icon"><i class="bx bx-receipt"></i></div>
            <h3>Aucun paiement trouvé</h3>
            <button class="btn btn-primary" data-modal="modalAdd"><i class="bx bx-plus"></i> Enregistrer</button>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($payments as $p):
          [$bdg, $lbl] = $statusBadge[$p['status']] ?? ['badge-gray', $p['status']];
        ?>
        <tr>
          <td>
            <div class="td-user">
              <div class="avatar avatar-32" style="background:var(--success)"><?= getInitials((string)$p['fn'], (string)$p['ln']) ?></div>
              <div>
                <div class="td-name"><?= clean($p['fn']) ?> <?= clean($p['ln']) ?></div>
                <div class="td-sub"><?= clean($p['student_number']) ?> <?= $p['class_name'] ? '· ' . clean($p['class_name']) : '' ?></div>
              </div>
            </div>
          </td>
          <td><code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 7px;border-radius:4px"><?= clean($p['receipt_number']) ?></code></td>
          <td class="text-sm"><?= clean($p['fee_name']) ?></td>
          <td style="font-weight:700;color:var(--success)"><?= formatMoney($p['amount_paid']) ?></td>
          <td class="text-sm text-muted"><?= $methodLabels[$p['payment_method']] ?? $p['payment_method'] ?></td>
          <td><span class="badge <?= $bdg ?>"><?= $lbl ?></span></td>
          <td class="text-sm text-muted"><?= formatDateTime($p['payment_date']) ?></td>
          <td>
            <div class="td-actions">
              <a href="<?= BASE_URL ?>/admin/payments.php?receipt=<?= $p['id'] ?>"
                 class="btn btn-sm btn-ghost btn-icon" title="Voir reçu">
                <i class="bx bx-printer"></i>
              </a>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-sm btn-ghost btn-icon" title="Supprimer"
                        data-confirm="Supprimer ce paiement ?">
                  <i class="bx bx-trash" style="color:var(--danger)"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
  <?php if ($pag['has_prev']): ?>
    <a href="?tab=list&page=<?= $pag['prev_page'] ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>"><i class="bx bx-chevron-left"></i></a>
  <?php endif; ?>
  <?php for ($i = max(1,$pag['current_page']-2); $i <= min($pag['total_pages'],$pag['current_page']+2); $i++): ?>
    <?php if ($i === $pag['current_page']): ?>
      <span class="active"><?= $i ?></span>
    <?php else: ?>
      <a href="?tab=list&page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($pag['has_next']): ?>
    <a href="?tab=list&page=<?= $pag['next_page'] ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>"><i class="bx bx-chevron-right"></i></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php else: // tab = proofs ?>

<!-- Preuves bancaires -->
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Élève</th><th>Reçu</th><th>Montant</th><th>Fichier</th><th>Statut</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if (empty($proofs)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-state-icon"><i class="bx bx-file-blank"></i></div>
            <h3>Aucune preuve bancaire</h3>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($proofs as $pr):
          $statusMap = ['en_attente'=>['badge-warning','En attente'],'valide'=>['badge-success','Validée'],'rejete'=>['badge-danger','Rejetée']];
          [$bdg,$lbl] = $statusMap[$pr['status']] ?? ['badge-gray',$pr['status']];
        ?>
        <tr>
          <td>
            <div class="td-user">
              <div class="avatar avatar-32" style="background:var(--primary)"><?= getInitials((string)$pr['fn'],(string)$pr['ln']) ?></div>
              <div>
                <div class="td-name"><?= clean($pr['fn']) ?> <?= clean($pr['ln']) ?></div>
                <div class="td-sub"><?= clean($pr['student_number']) ?></div>
              </div>
            </div>
          </td>
          <td><code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 7px;border-radius:4px"><?= clean($pr['receipt_number']) ?></code></td>
          <td style="font-weight:700"><?= formatMoney($pr['amount_paid']) ?></td>
          <td>
            <a href="<?= UPLOADS_URL ?>/proofs/<?= clean($pr['file_path']) ?>" target="_blank"
               class="btn btn-sm btn-ghost" style="font-size:12px">
              <i class="bx bx-file"></i> <?= clean($pr['file_name']) ?>
            </a>
            <?php if ($pr['reference']): ?>
              <div style="font-size:11px;color:var(--text-muted)">Ref: <?= clean($pr['reference']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $bdg ?>"><?= $lbl ?></span></td>
          <td class="text-sm text-muted"><?= formatDateTime($pr['created_at']) ?></td>
          <td>
            <?php if ($pr['status'] === 'en_attente'): ?>
            <div class="td-actions">
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"   value="validate_proof">
                <input type="hidden" name="proof_id" value="<?= $pr['id'] ?>">
                <button type="submit" class="btn btn-sm btn-success" title="Valider"
                        data-confirm="Valider cette preuve et confirmer le paiement ?">
                  <i class="bx bx-check"></i> Valider
                </button>
              </form>
              <button class="btn btn-sm btn-outline-danger" onclick="openReject(<?= $pr['id'] ?>)">
                <i class="bx bx-x"></i> Rejeter
              </button>
            </div>
            <?php elseif ($pr['reject_reason']): ?>
              <span style="font-size:12px;color:var(--danger)"><?= clean($pr['reject_reason']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

</div>
</div>

<!-- MODAL AJOUTER PAIEMENT -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-credit-card" style="color:var(--primary)"></i> Enregistrer un paiement</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST" data-loading>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Élève <span class="form-required">*</span></label>
            <select name="student_id" class="form-control" required>
              <option value="">Sélectionner un élève</option>
              <?php foreach ($students as $s):
                $name = trim(($s['ufn'] ?: $s['sfn']) . ' ' . ($s['uln'] ?: $s['sln']));
              ?>
                <option value="<?= $s['id'] ?>">
                  <?= clean($name) ?> (<?= clean($s['student_number']) ?>)
                  <?= $s['class_name'] ? '— ' . clean($s['class_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Type de frais <span class="form-required">*</span></label>
            <select name="fee_id" id="feeSelect" class="form-control" required onchange="fillAmount(this)">
              <option value="">Sélectionner</option>
              <?php foreach ($fees as $f): ?>
                <option value="<?= $f['id'] ?>" data-amount="<?= $f['amount'] ?>">
                  <?= clean($f['name']) ?> — <?= formatMoney($f['amount']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Montant payé (FC) <span class="form-required">*</span></label>
            <div class="input-wrap">
              <i class="bx bx-money input-icon"></i>
              <input type="number" name="amount" id="amountInput" class="form-control" min="0" step="100" required placeholder="0">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Méthode de paiement</label>
            <select name="method" class="form-control">
              <?php foreach ($methodLabels as $k => $lbl): ?>
                <option value="<?= $k ?>"><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date du paiement</label>
            <input type="datetime-local" name="payment_date" class="form-control"
                   value="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Observation...">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL REJETER PREUVE -->
<div class="modal-overlay" id="modalReject">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3><i class="bx bx-x-circle" style="color:var(--danger)"></i> Rejeter la preuve</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"   value="reject_proof">
      <input type="hidden" name="proof_id" id="rejectProofId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Motif du rejet <span class="form-required">*</span></label>
          <textarea name="reject_reason" class="form-control" rows="3"
                    placeholder="Expliquer pourquoi la preuve est rejetée..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-danger"><i class="bx bx-x"></i> Rejeter</button>
      </div>
    </form>
  </div>
</div>

<?php
$pageScript = "
function fillAmount(sel) {
  const opt = sel.options[sel.selectedIndex];
  const amt = opt.dataset.amount;
  if (amt) document.getElementById('amountInput').value = amt;
}
function openReject(id) {
  document.getElementById('rejectProofId').value = id;
  document.getElementById('modalReject').classList.add('open');
}
";
require INCLUDES_PATH . '/footer.php';
?>