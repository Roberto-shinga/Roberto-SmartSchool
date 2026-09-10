<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireParent();

$pageTitle   = 'Mes enfants';
$pageSection = 'children';
$user        = currentUser();

$children = getParentChildren($user['id']);
$childIds = array_column($children, 'id');

// Details complets (une seule requete, pas de N+1)
$details = [];
if (!empty($childIds)) {
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    $rows = dbFetchAll(
        "SELECT s.*, COALESCE(s.first_name,u.first_name) fn, COALESCE(s.last_name,u.last_name) ln,
                u.email, c.name AS class_name, l.name AS level_name
         FROM students s LEFT JOIN users u ON s.user_id=u.id
         LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN levels l ON s.level_id=l.id
         WHERE s.id IN ($placeholders)",
        $childIds
    );
    foreach ($rows as $r) $details[$r['id']] = $r;
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mes enfants</h1><p><?= count($children) ?> enfant(s) lie(s) a ton compte</p></div>
    </div>

    <?php if (empty($children)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-group"></i></div>
        <h3>Aucun enfant lie</h3>
        <p>Contacte l'administration pour lier le profil de ton enfant a ce compte.</p>
      </div>
    <?php else: ?>

    <div class="grid-2">
      <?php foreach ($children as $c): $d = $details[$c['id']] ?? null; if (!$d) continue; ?>
      <div class="card">
        <div class="card-body">
          <div class="flex items-center gap-3" style="margin-bottom:16px">
            <div class="avatar avatar-48" style="background:var(--success)"><?= getInitials($d['fn'], $d['ln']) ?></div>
            <div>
              <div style="font-weight:700;font-size:16px;color:var(--text-primary)"><?= clean($d['fn'] . ' ' . $d['ln']) ?></div>
              <div class="text-xs text-muted"><?= clean($c['relationship'] ?: 'Parent') ?><?= $c['is_primary'] ? ' · Contact principal' : '' ?></div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <div class="flex justify-between text-sm"><span class="text-muted">Matricule</span><span class="font-mono"><?= clean($d['student_number']) ?></span></div>
            <div class="flex justify-between text-sm"><span class="text-muted">Classe</span><span><?= clean($d['class_name'] ?: '—') ?></span></div>
            <div class="flex justify-between text-sm"><span class="text-muted">Niveau</span><span><?= clean($d['level_name'] ?: '—') ?></span></div>
            <div class="flex justify-between text-sm"><span class="text-muted">Date de naissance</span><span><?= $d['date_of_birth'] ? formatDate($d['date_of_birth']) : '—' ?></span></div>
            <div class="flex justify-between text-sm">
              <span class="text-muted">Statut</span>
              <span class="badge badge-<?= $d['status']==='actif'?'success':'danger' ?>"><?= ucfirst($d['status']) ?></span>
            </div>
            <?php if ($d['scholarship']): ?>
              <div class="text-sm" style="color:var(--success)"><i class="bx bx-award"></i> Beneficie d'une bourse</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
