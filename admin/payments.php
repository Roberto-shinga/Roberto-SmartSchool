<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Paiements';
$pageSection = 'payments';
$user        = currentUser();

// Récupération de l'année académique courante
$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 1;

// ── TRAITEMENT POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Enregistrer un paiement (formulaire principal)
    if (in_array($action, ['create', 'add'])) {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $feeId     = (int)($_POST['fee_id']     ?? 0);
        $amount    = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
        $method    = in_array($_POST['payment_method'] ?? $_POST['method'] ?? '', ['especes','virement','cheque','mobile_money','carte']) ? ($_POST['payment_method'] ?? $_POST['method']) : 'especes';
        $notes     = sanitizeString($_POST['notes'] ?? '');
        $date      = trim($_POST['payment_date'] ?? date('Y-m-d H:i:s'));

        $fee = $feeId ? dbFetchOne("SELECT * FROM fees WHERE id=?", [$feeId]) : null;

        if (!$studentId || !$fee || $amount <= 0) {
            redirectWith(BASE_URL . '/admin/payments.php', 'danger', 'Élève, frais et montant valide sont obligatoires.');
        } else {
            $receipt = generateReceiptNumber();
            $status  = $amount >= (float)$fee['amount'] ? 'paye' : 'partiel';

            dbExecute(
                "INSERT INTO payments (student_id, fee_id, amount_paid, payment_date, payment_method, receipt_number, status, collected_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$studentId, $feeId, $amount, $date, $method, $receipt, $status, $user['id'], $notes ?: null]
            );

            $paymentId = dbLastId();
            logActivity('payment_recorded', "Paiement #$paymentId ($receipt) enregistré : " . formatMoney($amount));
            redirectWith(BASE_URL . '/admin/payments.php', 'success', "Paiement enregistré avec succès ! Reçu N° : $receipt");
        }
    }

    // Valider une preuve bancaire
    if ($action === 'verify_proof' || $action === 'validate_proof') {
        $proofId  = (int)($_POST['proof_id'] ?? 0);
        $decision = $_POST['decision'] ?? 'valide';
        $reason   = sanitizeString($_POST['reject_reason'] ?? '');
        $proof    = dbFetchOne("SELECT * FROM payment_proofs WHERE id=?", [$proofId]);

        if ($proof && in_array($decision, ['valide', 'rejete'])) {
            dbExecute(
                "UPDATE payment_proofs SET status=?, verified_by=?, verified_at=NOW(), reject_reason=? WHERE id=?",
                [$decision, $user['id'], $decision === 'rejete' ? ($reason ?: null) : null, $proofId]
            );

            $newStatus = ($decision === 'valide') ? 'paye' : 'rejete';
            dbExecute("UPDATE payments SET status=? WHERE id=?", [$newStatus, $proof['payment_id']]);

            logActivity('payment_proof_' . $decision, "Preuve #$proofId " . ($decision === 'valide' ? 'validée' : 'rejetée'));
            redirectWith(BASE_URL . '/admin/payments.php?tab=proofs', 'success', 'Preuve de paiement traitée avec succès.');
        }
    }

    // Supprimer un paiement
    if ($action === 'delete') {
        $pid = (int)($_POST['payment_id'] ?? 0);
        if ($pid > 0) {
            dbExecute("DELETE FROM payments WHERE id=?", [$pid]);
            logActivity('delete_payment', "Paiement #$pid supprimé");
            redirectWith(BASE_URL . '/admin/payments.php', 'success', 'Paiement supprimé.');
        }
    }
}

// ── GET & FILTRES ─────────────────────────────────────────────────────────────
$tab          = $_GET['tab']    ?? 'list';
$search       = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterMethod = $_GET['method'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));

// ── REQUÊTE LISTE PAIEMENTS ───────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = "(p.receipt_number LIKE ? OR s.student_number LIKE ? OR COALESCE(s.first_name, u.first_name) LIKE ? OR COALESCE(s.last_name, u.last_name) LIKE ?)";
    $s = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
}
if ($filterStatus !== '' && in_array($filterStatus, ['en_attente','paye','partiel','rejete','annule'])) {
    $where[]  = "p.status = ?";
    $params[] = $filterStatus;
}
if ($filterMethod !== '') {
    $where[]  = "p.payment_method = ?";
    $params[] = $filterMethod;
}
$whereSql = implode(' AND ', $where);

$total = dbFetchOne(
    "SELECT COUNT(*) c FROM payments p
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     WHERE $whereSql",
    $params
)['c'] ?? 0;

$pag = paginate($total, $page);

$payments = dbFetchAll(
    "SELECT p.*, 
            COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
            s.student_number, fc.name AS category_name, c.name AS class_name,
            cu.first_name AS coll_fn, cu.last_name AS coll_ln
     FROM payments p
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     JOIN fees f ON p.fee_id = f.id
     JOIN fee_categories fc ON f.category_id = fc.id
     LEFT JOIN users cu ON p.collected_by = cu.id
     WHERE $whereSql
     ORDER BY p.payment_date DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// ── PREUVES EN ATTENTE & STATISTIQUES ─────────────────────────────────────────
$proofs = dbFetchAll(
    "SELECT pp.*, p.receipt_number, p.amount_paid,
            COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln,
            s.student_number
     FROM payment_proofs pp
     JOIN payments p ON pp.payment_id = p.id
     JOIN students s ON p.student_id = s.id
     LEFT JOIN users u ON s.user_id = u.id
     ORDER BY pp.created_at DESC"
);
$pendingProofsCount = count(array_filter($proofs, fn($p) => $p['status'] === 'en_attente'));

$statsMonth = dbFetchOne(
    "SELECT COALESCE(SUM(CASE WHEN status='paye' THEN amount_paid ELSE 0 END), 0) AS total,
            COUNT(*) AS nb
     FROM payments
     WHERE MONTH(payment_date) = MONTH(NOW()) AND YEAR(payment_date) = YEAR(NOW())"
);

// ── DONNÉES AUXILIAIRES POUR MODALE ENREGISTREMENT ────────────────────────────
$allStudents = dbFetchAll(
    "SELECT s.id, s.student_number, COALESCE(s.first_name, u.first_name) fn, COALESCE(s.last_name, u.last_name) ln
     FROM students s 
     LEFT JOIN users u ON s.user_id = u.id 
     WHERE s.status = 'actif' 
     ORDER BY fn, ln"
);

$feesList = dbFetchAll(
    "SELECT f.id, f.amount, fc.name AS category_name 
     FROM fees f 
     JOIN fee_categories fc ON f.category_id = fc.id 
     WHERE f.academic_year_id = ? 
     ORDER BY fc.name",
    [$yearId]
);

$statusBadge = [
    'paye'       => ['badge-success', 'Payé'],
    'partiel'    => ['badge-warning', 'Partiel'],
    'en_attente' => ['badge-gray',    'En attente'],
    'rejete'     => ['badge-danger',  'Rejeté'],
    'annule'     => ['badge-danger',  'Annulé']
];

$methodLabels = [
    'especes'      => 'Espèces',
    'virement'     => 'Virement',
    'cheque'       => 'Chèque',
    'mobile_money' => 'Mobile Money',
    'carte'        => 'Carte'
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
        <button class="btn btn-primary" data-modal="addPaymentModal">
          <i class="bx bx-plus"></i> Nouveau paiement
        </button>
      </div>
    </div>

    <!-- Quick Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:22px">
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
      <?php if ($pendingProofsCount > 0): ?>
      <div class="stat-card c-warning" style="cursor:pointer" onclick="location.href='?tab=proofs'">
        <div class="stat-icon c-warning"><i class="bx bx-file-blank"></i></div>
        <div>
          <div class="stat-value"><?= $pendingProofsCount ?></div>
          <div class="stat-label">Preuves à valider</div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Onglets -->
    <div class="tabs">
      <button class="tab-btn <?= $tab === 'list' ? 'active' : '' ?>" onclick="location.href='?tab=list'">
        <i class="bx bx-list-ul"></i> Liste des paiements
      </button>
      <button class="tab-btn <?= $tab === 'proofs' ? 'active' : '' ?>" onclick="location.href='?tab=proofs'">
        <i class="bx bx-file-blank"></i> Preuves bancaires
        <?php if ($pendingProofsCount > 0): ?>
          <span class="badge badge-warning" style="margin-left:4px"><?= $pendingProofsCount ?></span>
        <?php endif; ?>
      </button>
    </div>

    <?php if ($tab === 'list'): ?>

    <!-- Onglet Liste : Filtres -->
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
            <?php foreach ($statusBadge as $k => [$cls, $lbl]): ?>
              <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <select name="method" class="form-control" style="width:150px">
            <option value="">Toutes méthodes</option>
            <?php foreach ($methodLabels as $k => $lbl): ?>
              <option value="<?= $k ?>" <?= $filterMethod === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt"></i> Filtrer</button>
          <?php if ($search || $filterStatus || $filterMethod): ?>
            <a href="?tab=list" class="btn btn-secondary">Réinitialiser</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Onglet Liste : Tableau -->
    <div class="card">
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead>
            <tr>
              <th>Élève</th>
              <th>Reçu</th>
              <th>Catégorie</th>
              <th>Montant</th>
              <th>Méthode</th>
              <th>Statut</th>
              <th>Encaissé par</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr>
                <td colspan="9">
                  <div class="empty-state">
                    <div class="empty-state-icon"><i class="bx bx-receipt"></i></div>
                    <h3>Aucun paiement trouvé</h3>
                    <button class="btn btn-primary" data-modal="addPaymentModal"><i class="bx bx-plus"></i> Enregistrer un paiement</button>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($payments as $p): 
                [$bdgClass, $bdgLabel] = $statusBadge[$p['status']] ?? ['badge-gray', $p['status']];
              ?>
              <tr>
                <td>
                  <div class="td-user">
                    <div class="avatar avatar-32" style="background:var(--success)"><?= getInitials((string)$p['fn'], (string)$p['ln']) ?></div>
                    <div>
                      <div class="td-name"><?= clean($p['fn'] . ' ' . $p['ln']) ?></div>
                      <div class="td-sub"><?= clean($p['student_number']) ?> <?= $p['class_name'] ? '· ' . clean($p['class_name']) : '' ?></div>
                    </div>
                  </div>
                </td>
                <td><code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 7px;border-radius:4px"><?= clean($p['receipt_number']) ?></code></td>
                <td class="text-sm text-muted"><?= clean($p['category_name']) ?></td>
                <td style="font-weight:700;color:var(--success)"><?= formatMoney($p['amount_paid']) ?></td>
                <td class="text-sm text-muted"><?= $methodLabels[$p['payment_method']] ?? ucfirst($p['payment_method']) ?></td>
                <td><span class="badge <?= $bdgClass ?>"><?= $bdgLabel ?></span></td>
                <td class="text-sm text-muted"><?= $p['coll_fn'] ? clean($p['coll_fn'] . ' ' . $p['coll_ln']) : '—' ?></td>
                <td class="text-sm text-muted"><?= formatDateTime($p['payment_date']) ?></td>
                <td>
                  <div class="td-actions">
                    <a href="<?= BASE_URL ?>/admin/payments.php?receipt=<?= $p['id'] ?>" class="btn btn-sm btn-ghost btn-icon" title="Voir reçu">
                      <i class="bx bx-printer"></i>
                    </a>
                    <form method="POST" style="display:inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-ghost btn-icon" title="Supprimer" data-confirm="Voulez-vous vraiment supprimer ce paiement ?">
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
          <a href="?tab=list&page=<?= $pag['prev_page'] ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&method=<?= $filterMethod ?>"><i class="bx bx-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $pag['current_page'] - 2); $i <= min($pag['total_pages'], $pag['current_page'] + 2); $i++): ?>
          <?php if ($i === $pag['current_page']): ?>
            <span class="active"><?= $i ?></span>
          <?php else: ?>
            <a href="?tab=list&page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&method=<?= $filterMethod ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pag['has_next']): ?>
          <a href="?tab=list&page=<?= $pag['next_page'] ?>&q=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&method=<?= $filterMethod ?>"><i class="bx bx-chevron-right"></i></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php else: // Onglet Preuves bancaires ?>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Élève</th>
            <th>Reçu</th>
            <th>Montant</th>
            <th>Fichier</th>
            <th>Statut</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($proofs)): ?>
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <div class="empty-state-icon"><i class="bx bx-file-blank"></i></div>
                  <h3>Aucune preuve bancaire disponible</h3>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($proofs as $pr):
              $statusMap = ['en_attente' => ['badge-warning','En attente'], 'valide' => ['badge-success','Validée'], 'rejete' => ['badge-danger','Rejetée']];
              [$bdg, $lbl] = $statusMap[$pr['status']] ?? ['badge-gray', $pr['status']];
            ?>
            <tr>
              <td>
                <div class="td-user">
                  <div class="avatar avatar-32" style="background:var(--primary)"><?= getInitials((string)$pr['fn'], (string)$pr['ln']) ?></div>
                  <div>
                    <div class="td-name"><?= clean($pr['fn'] . ' ' . $pr['ln']) ?></div>
                    <div class="td-sub"><?= clean($pr['student_number']) ?></div>
                  </div>
                </div>
              </td>
              <td><code style="font-size:11px;background:var(--primary-bg);color:var(--primary);padding:2px 7px;border-radius:4px"><?= clean($pr['receipt_number']) ?></code></td>
              <td style="font-weight:700"><?= formatMoney($pr['amount_paid']) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/uploads/<?= clean($pr['file_path']) ?>" target="_blank" class="btn btn-sm btn-ghost" style="font-size:12px">
                  <i class="bx bx-paperclip"></i> <?= clean($pr['file_name']) ?>
                </a>
              </td>
              <td><span class="badge <?= $bdg ?>"><?= $lbl ?></span></td>
              <td class="text-sm text-muted"><?= formatDateTime($pr['created_at']) ?></td>
              <td>
                <?php if ($pr['status'] === 'en_attente'): ?>
                <div class="td-actions">
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="verify_proof">
                    <input type="hidden" name="proof_id" value="<?= $pr['id'] ?>">
                    <input type="hidden" name="decision" value="valide">
                    <button type="submit" class="btn btn-sm btn-success" data-confirm="Valider cette preuve et marquer le paiement comme payé ?">
                      <i class="bx bx-check"></i> Valider
                    </button>
                  </form>
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="openRejectModal(<?= $pr['id'] ?>)">
                    <i class="bx bx-x"></i> Rejeter
                  </button>
                </div>
                <?php elseif ($pr['reject_reason']): ?>
                  <span style="font-size:12px;color:var(--danger)"><?= clean($pr['reject_reason']) ?></span>
                <?php else: ?>
                  <span class="text-muted text-sm">—</span>
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

<!-- ══ MODAL : Nouveau Paiement ══ -->
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
          <label class="form-label">Élève <span class="form-required">*</span></label>
          <select name="student_id" class="form-control" required>
            <option value="">Sélectionner un élève...</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= $st['id'] ?>"><?= clean($st['fn'] . ' ' . $st['ln']) ?> — <?= clean($st['student_number']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Frais <span class="form-required">*</span></label>
          <select name="fee_id" class="form-control" required>
            <option value="">Sélectionner des frais...</option>
            <?php foreach ($feesList as $f): ?>
              <option value="<?= $f['id'] ?>"><?= clean($f['category_name']) ?> — <?= formatMoney($f['amount']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Montant versé (FC) <span class="form-required">*</span></label>
            <input type="number" name="amount_paid" class="form-control" min="1" step="100" required>
          </div>
          <div class="form-group">
            <label class="form-label">Méthode</label>
            <select name="payment_method" class="form-control">
              <option value="especes">Espèces</option>
              <option value="virement">Virement</option>
              <option value="cheque">Chèque</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="carte">Carte</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL : Rejeter Preuve Bancaire ══ -->
<div class="modal-overlay" id="rejectProofModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="verify_proof">
      <input type="hidden" name="decision" value="rejete">
      <input type="hidden" name="proof_id" id="reject_proof_id" value="0">
      <div class="modal-header">
        <h3><i class="bx bx-x-circle" style="color:var(--danger)"></i> Rejeter la preuve de paiement</h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Motif du rejet</label>
          <textarea name="reject_reason" class="form-control" rows="3" placeholder="Ex: Image illisible, reçu non conforme..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-danger"><i class="bx bx-x"></i> Confirmer le rejet</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(proofId) {
    document.getElementById('reject_proof_id').value = proofId;
    if (typeof SS !== 'undefined' && SS.openModal) {
        SS.openModal('rejectProofModal');
    } else {
        document.getElementById('rejectProofModal').classList.add('active');
    }
}
</script>

<?php 
require INCLUDES_PATH . '/footer.php'; 
?>