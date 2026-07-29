<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireSuperAdmin();

$pageTitle   = 'Roles & permissions';
$pageSection = 'roles';

$roleCounts = dbFetchAll(
    "SELECT r.id, r.name, r.label, COUNT(u.id) AS total, SUM(u.is_active) AS actifs
     FROM roles r LEFT JOIN users u ON u.role_id = r.id
     GROUP BY r.id, r.name, r.label ORDER BY r.id"
);
$countsByRole = [];
foreach ($roleCounts as $rc) $countsByRole[$rc['id']] = $rc;

$rolePerimeters = [
    ROLE_SUPER_ADMIN => [
        'desc' => 'Configuration initiale, securite, journaux d\'audit, sauvegardes et maintenance technique. Ne gere pas les operations quotidiennes.',
        'perms' => ['Gerer les comptes Administrateur', 'Parametrer la securite (mots de passe, 2FA)', 'Consulter les journaux d\'audit', 'Sauvegarder / restaurer la base de donnees', 'Activer le mode maintenance'],
    ],
    ROLE_ADMIN => [
        'desc' => 'Gestion quotidienne complete de l\'etablissement : eleves, enseignants, comptables, classes, notes, presences, finances.',
        'perms' => ['Inviter enseignants et comptables', 'Gerer eleves, classes et matieres', 'Consulter notes, presences et bulletins', 'Suivre les finances et paiements'],
    ],
    ROLE_TEACHER => [
        'desc' => 'Acces limite aux classes et matieres qui lui sont explicitement attribuees (teacher_assignments).',
        'perms' => ['Saisir les notes de ses classes/matieres', 'Faire l\'appel de presence', 'Consulter son emploi du temps', 'Publier des cours et quiz'],
    ],
    ROLE_STUDENT => [
        'desc' => 'Compte personnel a partir de la 7e annee. Acces en lecture a ses propres donnees uniquement.',
        'perms' => ['Consulter ses notes et bulletins', 'Consulter ses presences', 'Acceder a SmartSchool Learning'],
    ],
    ROLE_PARENT => [
        'desc' => 'Suivi des enfants qui lui sont rattaches (parent_student).',
        'perms' => ['Consulter les notes et presences de ses enfants', 'Consulter les paiements et soldes', 'Recevoir des notifications'],
    ],
    ROLE_ACCOUNTANT => [
        'desc' => 'Perimetre strictement financier. Ne peut ni modifier les notes, ni gerer les roles, ni acceder aux fonctions du Super Admin.',
        'perms' => ['Enregistrer et valider les paiements', 'Gerer les frais scolaires', 'Emettre des recus', 'Consulter les rapports financiers'],
    ],
];

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div>
        <h1>Roles & permissions</h1>
        <p>Perimetre de chaque role dans SmartSchool</p>
      </div>
    </div>

    <div class="alert alert-info" style="margin-bottom:24px">
      <i class="bx bx-info-circle"></i>
      Les permissions sont actuellement fixees par role au niveau du code (<code>requireRole()</code>).
      Cette page sert de reference visuelle ; un systeme de permissions granulaires et configurables
      pourra etre ajoute ulterieurement si le besoin se precise.
    </div>

    <div class="grid-2">
      <?php foreach (ROLE_LABELS as $roleId => $label):
        $rp    = $rolePerimeters[$roleId];
        $count = $countsByRole[$roleId] ?? ['total'=>0,'actifs'=>0];
      ?>
      <div class="card animate-in">
        <div class="card-header">
          <h3>
            <i class="bx <?= ROLE_ICONS[$roleId] ?>" style="color:<?= ROLE_COLORS[$roleId] ?>"></i>
            <?= clean($label) ?>
          </h3>
          <span class="badge badge-primary"><?= (int)$count['total'] ?> compte(s)</span>
        </div>
        <div class="card-body">
          <p class="text-sm text-muted" style="margin-bottom:12px;line-height:1.6"><?= $rp['desc'] ?></p>
          <div style="display:flex;flex-direction:column;gap:7px">
            <?php foreach ($rp['perms'] as $p): ?>
              <div class="flex items-center gap-2 text-sm">
                <i class="bx bx-check" style="color:var(--success)"></i> <?= clean($p) ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ((int)$count['total'] > 0): ?>
          <div class="text-xs text-muted" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border-light)">
            <?= (int)$count['actifs'] ?> actif(s) sur <?= (int)$count['total'] ?> compte(s)
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
