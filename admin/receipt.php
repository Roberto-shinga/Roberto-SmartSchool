<?php
// ============================================================
//  SmartSchool — Recu de paiement (impression)
//  Emplacement : admin/receipt.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT);

$paymentId = (int)($_GET['id'] ?? 0);
if (!$paymentId) {
    die('<p style="color:red;font-family:sans-serif;padding:20px">ID de paiement manquant.</p>');
}

$payment = dbFetchOne(
    "SELECT p.*,
            u.first_name, u.last_name, u.email, u.phone,
            s.student_number,
            c.name AS class_name,
            fc.name AS fee_name,
            f.amount AS fee_total,
            uc.first_name AS col_fn, uc.last_name AS col_ln,
            ay.name AS year_name
     FROM payments p
     JOIN students s    ON p.student_id  = s.id
     JOIN users u       ON s.user_id     = u.id
     LEFT JOIN classes c ON s.class_id  = c.id
     JOIN fees f        ON p.fee_id      = f.id
     JOIN fee_categories fc ON f.category_id = fc.id
     JOIN users uc      ON p.collected_by = uc.id
     JOIN academic_years ay ON f.academic_year_id = ay.id
     WHERE p.id = ?",
    [$paymentId]
);

if (!$payment) {
    die('<p style="color:red;font-family:sans-serif;padding:20px">Paiement introuvable.</p>');
}

$schoolName    = getSetting('school_name', APP_NAME);
$schoolAddress = getSetting('school_address', '');
$schoolPhone   = getSetting('school_phone', '');
$schoolEmail   = getSetting('school_email', '');
$currency      = getSetting('currency', 'FCFA');
$balance       = $payment['fee_total'] - $payment['amount_paid'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recu <?= clean($payment['receipt_number']) ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family : 'Segoe UI', Arial, sans-serif;
      font-size   : 13px;
      color       : #1e293b;
      background  : #f8fafc;
      padding     : 20px;
    }

    .receipt {
      max-width    : 520px;
      margin       : 0 auto;
      background   : #fff;
      border-radius: 12px;
      border       : 1px solid #e2e8f0;
      overflow     : hidden;
      box-shadow   : 0 4px 24px rgba(0,0,0,0.08);
    }

    /* Header */
    .receipt-header {
      background : linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color      : #fff;
      padding    : 28px 32px;
      text-align : center;
    }
    .receipt-header .school-icon {
      width          : 56px;
      height         : 56px;
      background     : rgba(255,255,255,0.18);
      border-radius  : 50%;
      display        : flex;
      align-items    : center;
      justify-content: center;
      font-size      : 1.8rem;
      margin         : 0 auto 12px;
      border         : 2px solid rgba(255,255,255,0.30);
    }
    .receipt-header h1 {
      font-size    : 20px;
      font-weight  : 800;
      margin-bottom: 4px;
    }
    .receipt-header p {
      font-size : 12px;
      opacity   : 0.82;
      line-height: 1.5;
    }

    /* Badge recu */
    .receipt-badge {
      background : rgba(255,255,255,0.20);
      border     : 1px solid rgba(255,255,255,0.35);
      border-radius: 99px;
      padding    : 5px 16px;
      font-size  : 11px;
      font-weight: 700;
      display    : inline-block;
      margin-top : 12px;
      letter-spacing: 0.05em;
    }

    /* Corps */
    .receipt-body { padding: 28px 32px; }

    /* Numero recu + statut */
    .receipt-meta {
      display        : flex;
      align-items    : center;
      justify-content: space-between;
      padding-bottom : 18px;
      border-bottom  : 2px dashed #e2e8f0;
      margin-bottom  : 20px;
    }
    .receipt-num-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
    .receipt-num       { font-size: 16px; font-weight: 800; color: #6366f1; margin-top: 3px; }

    .status-badge {
      padding      : 5px 14px;
      border-radius: 99px;
      font-size    : 12px;
      font-weight  : 700;
    }
    .status-paye    { background: #d1fae5; color: #059669; }
    .status-partiel { background: #fef3c7; color: #d97706; }
    .status-annule  { background: #fee2e2; color: #dc2626; }

    /* Section infos */
    .info-section { margin-bottom: 20px; }
    .info-section-title {
      font-size     : 10px;
      font-weight   : 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color         : #94a3b8;
      margin-bottom : 10px;
      display       : flex;
      align-items   : center;
      gap           : 6px;
    }
    .info-grid {
      display              : grid;
      grid-template-columns: repeat(2, 1fr);
      gap                  : 10px;
    }
    .info-item-label { font-size: 11px; color: #94a3b8; }
    .info-item-value { font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px; }

    /* Montant principal */
    .amount-box {
      background   : linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border       : 1.5px solid #86efac;
      border-radius: 10px;
      padding      : 20px 24px;
      text-align   : center;
      margin       : 20px 0;
    }
    .amount-label { font-size: 12px; color: #16a34a; font-weight: 600; margin-bottom: 6px; }
    .amount-value { font-size: 28px; font-weight: 800; color: #15803d; }
    .amount-currency { font-size: 14px; font-weight: 600; margin-left: 4px; }

    /* Ligne de detail */
    .detail-row {
      display        : flex;
      justify-content: space-between;
      align-items    : center;
      padding        : 8px 0;
      border-bottom  : 1px solid #f1f5f9;
      font-size      : 13px;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-row .label { color: #64748b; }
    .detail-row .value { font-weight: 600; color: #1e293b; }
    .detail-row.balance .value { color: #dc2626; font-weight: 700; }
    .detail-row.total   .label,
    .detail-row.total   .value { font-weight: 700; font-size: 14px; color: #1e293b; }

    /* Pied */
    .receipt-footer {
      border-top : 2px dashed #e2e8f0;
      padding-top: 18px;
      margin-top : 20px;
      text-align : center;
    }
    .receipt-footer p { font-size: 12px; color: #94a3b8; line-height: 1.6; }
    .receipt-footer .signature {
      margin-top     : 20px;
      display        : flex;
      justify-content: space-between;
      font-size      : 12px;
      color          : #64748b;
    }
    .sig-box {
      text-align : center;
      flex       : 1;
    }
    .sig-line {
      border-bottom: 1px solid #cbd5e1;
      margin       : 30px 16px 6px;
    }

    /* Boutons impression */
    .print-actions {
      display        : flex;
      justify-content: center;
      gap            : 12px;
      margin-top     : 20px;
    }
    .btn-print {
      padding       : 10px 24px;
      background    : linear-gradient(135deg, #6366f1, #8b5cf6);
      color         : #fff;
      border        : none;
      border-radius : 8px;
      font-size     : 13px;
      font-weight   : 700;
      cursor        : pointer;
      display       : flex;
      align-items   : center;
      gap           : 8px;
      font-family   : sans-serif;
    }
    .btn-close {
      padding       : 10px 24px;
      background    : #f1f5f9;
      color         : #64748b;
      border        : 1px solid #e2e8f0;
      border-radius : 8px;
      font-size     : 13px;
      font-weight   : 700;
      cursor        : pointer;
      font-family   : sans-serif;
    }

    @media print {
      body { padding: 0; background: #fff; }
      .receipt { box-shadow: none; border: none; max-width: 100%; }
      .print-actions { display: none; }
    }
  </style>
</head>
<body>

<div class="receipt">

  <!-- EN-TETE -->
  <div class="receipt-header">
    <div class="school-icon">🎓</div>
    <h1><?= clean($schoolName) ?></h1>
    <p>
      <?= clean($schoolAddress) ?><br>
      <?= clean($schoolPhone) ?> — <?= clean($schoolEmail) ?>
    </p>
    <div class="receipt-badge">RECU DE PAIEMENT OFFICIEL</div>
  </div>

  <!-- CORPS -->
  <div class="receipt-body">

    <!-- Numero recu + statut -->
    <div class="receipt-meta">
      <div>
        <div class="receipt-num-label">Numero de recu</div>
        <div class="receipt-num"><?= clean($payment['receipt_number']) ?></div>
      </div>
      <?php
      $statusClass = [
        'paye'    => 'status-paye',
        'partiel' => 'status-partiel',
        'annule'  => 'status-annule',
      ][$payment['status']] ?? 'status-paye';
      $statusLabel = [
        'paye'    => '✓ Paye',
        'partiel' => '⏳ Partiel',
        'annule'  => '✗ Annule',
      ][$payment['status']] ?? 'Paye';
      ?>
      <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>

    <!-- Infos eleve -->
    <div class="info-section">
      <div class="info-section-title">
        🎓 Informations de l'eleve
      </div>
      <div class="info-grid">
        <div>
          <div class="info-item-label">Nom complet</div>
          <div class="info-item-value">
            <?= clean($payment['first_name']) ?> <?= clean($payment['last_name']) ?>
          </div>
        </div>
        <div>
          <div class="info-item-label">Matricule</div>
          <div class="info-item-value" style="color:#6366f1">
            <?= clean($payment['student_number']) ?>
          </div>
        </div>
        <div>
          <div class="info-item-label">Classe</div>
          <div class="info-item-value"><?= clean($payment['class_name'] ?? '—') ?></div>
        </div>
        <div>
          <div class="info-item-label">Annee scolaire</div>
          <div class="info-item-value"><?= clean($payment['year_name']) ?></div>
        </div>
      </div>
    </div>

    <!-- Montant principal -->
    <div class="amount-box">
      <div class="amount-label">Montant recu</div>
      <div>
        <span class="amount-value">
          <?= number_format($payment['amount_paid'], 0, ',', ' ') ?>
        </span>
        <span class="amount-currency"><?= $currency ?></span>
      </div>
    </div>

    <!-- Details paiement -->
    <div class="info-section">
      <div class="info-section-title">💳 Details du paiement</div>

      <div class="detail-row">
        <span class="label">Type de frais</span>
        <span class="value"><?= clean($payment['fee_name']) ?></span>
      </div>
      <div class="detail-row total">
        <span class="label">Montant total des frais</span>
        <span class="value"><?= number_format($payment['fee_total'], 0, ',', ' ') ?> <?= $currency ?></span>
      </div>
      <div class="detail-row">
        <span class="label">Montant paye</span>
        <span class="value" style="color:#059669">
          <?= number_format($payment['amount_paid'], 0, ',', ' ') ?> <?= $currency ?>
        </span>
      </div>
      <?php if ($balance > 0): ?>
      <div class="detail-row balance">
        <span class="label">Reste a payer</span>
        <span class="value"><?= number_format($balance, 0, ',', ' ') ?> <?= $currency ?></span>
      </div>
      <?php else: ?>
      <div class="detail-row">
        <span class="label">Solde</span>
        <span class="value" style="color:#059669">Solde</span>
      </div>
      <?php endif; ?>
      <div class="detail-row">
        <span class="label">Mode de paiement</span>
        <span class="value"><?= clean(ucfirst(str_replace('_',' ',$payment['payment_method']))) ?></span>
      </div>
      <div class="detail-row">
        <span class="label">Date du paiement</span>
        <span class="value"><?= formatDateTime($payment['payment_date']) ?></span>
      </div>
      <div class="detail-row">
        <span class="label">Collecte par</span>
        <span class="value"><?= clean($payment['col_fn']) ?> <?= clean($payment['col_ln']) ?></span>
      </div>
    </div>

    <!-- Pied recu -->
    <div class="receipt-footer">
      <p>
        Ce recu est emis par <?= clean($schoolName) ?> et constitue une preuve officielle de paiement.<br>
        Conservez ce document precieusement.
      </p>
      <div class="signature">
        <div class="sig-box">
          <div class="sig-line"></div>
          <div>Signature du parent/eleve</div>
        </div>
        <div class="sig-box">
          <div class="sig-line"></div>
          <div>Cachet et signature du responsable</div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Boutons impression -->
<div class="print-actions">
  <button class="btn-print" onclick="window.print()">
    🖨️ Imprimer le recu
  </button>
  <button class="btn-close" onclick="window.close()">
    ✕ Fermer
  </button>
</div>

</body>
</html>