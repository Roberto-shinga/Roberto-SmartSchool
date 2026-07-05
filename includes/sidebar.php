<?php
// ============================================================
//  SmartSchool — Sidebar dynamique par role
//  Emplacement : includes/sidebar.php
// ============================================================

$user        = currentUser();
$role        = currentRole();
$pageSection = $pageSection ?? '';
$schoolName  = getSetting('school_name', APP_NAME);

// Menus selon le role
$menus = [];

// ── ADMIN & SUPER ADMIN ──────────────────────────────────
if (in_array($role, [ROLE_ADMIN, ROLE_SUPER_ADMIN])) {
    $menus = [
        [
            'title' => 'PRINCIPAL',
            'items' => [
                ['id'=>'dashboard',  'label'=>'Tableau de bord', 'icon'=>'bx-home-alt',       'url'=> BASE_URL.'/admin/index.php'],
                ['id'=>'analytics',  'label'=>'Analytiques',     'icon'=>'bx-bar-chart-alt-2', 'url'=> BASE_URL.'/admin/analytics.php'],
            ],
        ],
        [
            'title' => 'GESTION',
            'items' => [
                ['id'=>'students',  'label'=>'Eleves',        'icon'=>'bx-graduation',   'url'=> BASE_URL.'/admin/students.php',
                 'badge'=> dbFetchOne("SELECT COUNT(*) c FROM students WHERE status='actif'")['c'] ?? 0],
                ['id'=>'teachers',  'label'=>'Enseignants',   'icon'=>'bx-chalkboard',   'url'=> BASE_URL.'/admin/teachers.php'],
                ['id'=>'parents',   'label'=>'Parents',       'icon'=>'bx-group',        'url'=> BASE_URL.'/admin/parents.php'],
                ['id'=>'classes',   'label'=>'Classes',       'icon'=>'bx-building',     'url'=> BASE_URL.'/admin/classes.php'],
                ['id'=>'subjects',  'label'=>'Matieres',      'icon'=>'bx-book-open',    'url'=> BASE_URL.'/admin/subjects.php'],
                ['id'=>'timetable', 'label'=>'Emploi du temps','icon'=>'bx-calendar',   'url'=> BASE_URL.'/admin/timetable.php'],
            ],
        ],
        [
            'title' => 'SUIVI SCOLAIRE',
            'items' => [
                ['id'=>'grades',     'label'=>'Notes',      'icon'=>'bx-star',         'url'=> BASE_URL.'/admin/grades.php'],
                ['id'=>'attendance', 'label'=>'Presences',  'icon'=>'bx-check-square', 'url'=> BASE_URL.'/admin/attendance.php'],
                ['id'=>'reports',    'label'=>'Bulletins',  'icon'=>'bx-file',         'url'=> BASE_URL.'/admin/reports.php'],
            ],
        ],
        [
            'title' => 'FINANCES',
            'items' => [
                ['id'=>'payments',  'label'=>'Paiements',      'icon'=>'bx-credit-card', 'url'=> BASE_URL.'/admin/payments.php'],
                ['id'=>'fees',      'label'=>'Frais scolaires','icon'=>'bx-money',       'url'=> BASE_URL.'/admin/fees.php'],
                ['id'=>'financial', 'label'=>'Rapport financier','icon'=>'bx-trending-up','url'=> BASE_URL.'/admin/financial.php'],
            ],
        ],
        [
            'title' => 'COMMUNICATION',
            'items' => [
                ['id'=>'notifications','label'=>'Notifications','icon'=>'bx-bell',    'url'=> BASE_URL.'/admin/notifications.php',
                 'badge'=> countUnreadNotifications((int)$user['id'])],
                ['id'=>'messages',  'label'=>'Messages',     'icon'=>'bx-envelope',    'url'=> BASE_URL.'/admin/messages.php',
                 'badge'=> countUnreadMessages((int)$user['id'])],
            ],
        ],
        [
            'title' => 'SYSTEME',
            'items' => [
                ['id'=>'settings',  'label'=>'Parametres',   'icon'=>'bx-cog',         'url'=> BASE_URL.'/admin/settings.php'],
                ['id'=>'logs',      'label'=>'Journal',      'icon'=>'bx-list-check',  'url'=> BASE_URL.'/admin/logs.php'],
            ],
        ],
    ];
}

// ── ENSEIGNANT ───────────────────────────────────────────
elseif ($role === ROLE_TEACHER) {
    $menus = [
        [
            'title' => 'MON ESPACE',
            'items' => [
                ['id'=>'dashboard',  'label'=>'Tableau de bord',   'icon'=>'bx-home-alt',    'url'=> BASE_URL.'/teachers/index.php'],
                ['id'=>'timetable',  'label'=>'Mon emploi du temps','icon'=>'bx-calendar',   'url'=> BASE_URL.'/teachers/timetable.php'],
                ['id'=>'attendance', 'label'=>'Faire l\'appel',    'icon'=>'bx-check-square','url'=> BASE_URL.'/teachers/attendance.php'],
                ['id'=>'grades',     'label'=>'Saisir les notes',  'icon'=>'bx-star',        'url'=> BASE_URL.'/teachers/grades.php'],
                ['id'=>'students',   'label'=>'Mes eleves',        'icon'=>'bx-graduation',  'url'=> BASE_URL.'/teachers/students.php'],
                ['id'=>'reports',    'label'=>'Bulletins',         'icon'=>'bx-file',        'url'=> BASE_URL.'/teachers/reports.php'],
            ],
        ],
        [
            'title' => 'COMMUNICATION',
            'items' => [
                ['id'=>'messages',     'label'=>'Messages',      'icon'=>'bx-envelope', 'url'=> BASE_URL.'/teachers/messages.php',
                 'badge'=> countUnreadMessages((int)$user['id'])],
                ['id'=>'notifications','label'=>'Notifications', 'icon'=>'bx-bell',     'url'=> BASE_URL.'/teachers/notifications.php',
                 'badge'=> countUnreadNotifications((int)$user['id'])],
            ],
        ],
    ];
}

// ── ELEVE ─────────────────────────────────────────────────
elseif ($role === ROLE_STUDENT) {
    $menus = [
        [
            'title' => 'MON ESPACE',
            'items' => [
                ['id'=>'dashboard',  'label'=>'Tableau de bord',   'icon'=>'bx-home-alt',    'url'=> BASE_URL.'/students/index.php'],
                ['id'=>'timetable',  'label'=>'Emploi du temps',   'icon'=>'bx-calendar',    'url'=> BASE_URL.'/students/timetable.php'],
                ['id'=>'grades',     'label'=>'Mes notes',         'icon'=>'bx-star',        'url'=> BASE_URL.'/students/grades.php'],
                ['id'=>'attendance', 'label'=>'Mes presences',     'icon'=>'bx-check-square','url'=> BASE_URL.'/students/attendance.php'],
                ['id'=>'reports',    'label'=>'Mes bulletins',     'icon'=>'bx-file',        'url'=> BASE_URL.'/students/reports.php'],
                ['id'=>'payments',   'label'=>'Mes paiements',     'icon'=>'bx-credit-card', 'url'=> BASE_URL.'/students/payments.php'],
            ],
        ],
        [
            'title' => 'COMMUNICATION',
            'items' => [
                ['id'=>'messages',     'label'=>'Messages',      'icon'=>'bx-envelope', 'url'=> BASE_URL.'/students/messages.php',
                 'badge'=> countUnreadMessages((int)$user['id'])],
                ['id'=>'notifications','label'=>'Notifications', 'icon'=>'bx-bell',     'url'=> BASE_URL.'/students/notifications.php',
                 'badge'=> countUnreadNotifications((int)$user['id'])],
            ],
        ],
    ];
}

// ── PARENT ────────────────────────────────────────────────
elseif ($role === ROLE_PARENT) {
    $menus = [
        [
            'title' => 'SUIVI ENFANT',
            'items' => [
                ['id'=>'dashboard',  'label'=>'Tableau de bord', 'icon'=>'bx-home-alt',    'url'=> BASE_URL.'/parents/index.php'],
                ['id'=>'grades',     'label'=>'Notes',           'icon'=>'bx-star',        'url'=> BASE_URL.'/parents/grades.php'],
                ['id'=>'attendance', 'label'=>'Presences',       'icon'=>'bx-check-square','url'=> BASE_URL.'/parents/attendance.php'],
                ['id'=>'timetable',  'label'=>'Emploi du temps', 'icon'=>'bx-calendar',    'url'=> BASE_URL.'/parents/timetable.php'],
                ['id'=>'reports',    'label'=>'Bulletins',       'icon'=>'bx-file',        'url'=> BASE_URL.'/parents/reports.php'],
                ['id'=>'payments',   'label'=>'Paiements',       'icon'=>'bx-credit-card', 'url'=> BASE_URL.'/parents/payments.php'],
            ],
        ],
        [
            'title' => 'COMMUNICATION',
            'items' => [
                ['id'=>'messages',     'label'=>'Contacter l\'ecole','icon'=>'bx-envelope','url'=> BASE_URL.'/parents/messages.php',
                 'badge'=> countUnreadMessages((int)$user['id'])],
                ['id'=>'notifications','label'=>'Notifications',     'icon'=>'bx-bell',    'url'=> BASE_URL.'/parents/notifications.php',
                 'badge'=> countUnreadNotifications((int)$user['id'])],
            ],
        ],
    ];
}

// ── COMPTABLE ─────────────────────────────────────────────
elseif ($role === ROLE_ACCOUNTANT) {
    $menus = [
        [
            'title' => 'FINANCES',
            'items' => [
                ['id'=>'dashboard', 'label'=>'Tableau de bord',   'icon'=>'bx-home-alt',   'url'=> BASE_URL.'/accountant/index.php'],
                ['id'=>'payments',  'label'=>'Paiements',         'icon'=>'bx-credit-card','url'=> BASE_URL.'/accountant/payments.php'],
                ['id'=>'fees',      'label'=>'Frais scolaires',   'icon'=>'bx-money',      'url'=> BASE_URL.'/accountant/fees.php'],
                ['id'=>'receipts',  'label'=>'Recus',             'icon'=>'bx-receipt',    'url'=> BASE_URL.'/accountant/receipts.php'],
                ['id'=>'financial', 'label'=>'Rapport financier', 'icon'=>'bx-bar-chart',  'url'=> BASE_URL.'/accountant/financial.php'],
                ['id'=>'students',  'label'=>'Liste eleves',      'icon'=>'bx-graduation', 'url'=> BASE_URL.'/accountant/students.php'],
            ],
        ],
    ];
}
?>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <a href="<?= BASE_URL ?>" class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <i class="bx bx-buildings"></i>
    </div>
    <div class="sidebar-brand-text">
      <div class="sidebar-brand-name"><?= clean($schoolName) ?></div>
      <div class="sidebar-brand-sub">Gestion scolaire</div>
    </div>
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <?php foreach ($menus as $group): ?>
    <div class="nav-section">
      <div class="nav-section-title"><?= clean($group['title']) ?></div>

      <?php foreach ($group['items'] as $item):
        $isActive = ($pageSection === $item['id']);
        $badge    = !empty($item['badge']) && $item['badge'] > 0 ? $item['badge'] : null;
      ?>
      <div class="nav-item">
        <a
          href="<?= clean($item['url']) ?>"
          class="nav-link <?= $isActive ? 'active' : '' ?>"
          data-tip="<?= clean($item['label']) ?>"
        >
          <i class="bx <?= clean($item['icon']) ?> nav-icon"></i>
          <span class="nav-text"><?= clean($item['label']) ?></span>
          <?php if ($badge): ?>
            <span class="nav-badge"><?= $badge > 99 ? '99+' : $badge ?></span>
          <?php endif; ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <!-- Pied sidebar -->
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/auth/logout.php"
       class="sidebar-user"
       onclick="return confirm('Voulez-vous vous deconnecter ?')">
      <div class="avatar avatar-32"
           style="background:<?= ROLE_COLORS[$role] ?? '#6366f1' ?>;flex-shrink:0">
        <?= getInitials($user['first_name'], $user['last_name']) ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name">
          <?= clean($user['first_name']) ?> <?= clean($user['last_name']) ?>
        </div>
        <div class="sidebar-user-role"><?= ROLE_LABELS[$role] ?? '' ?></div>
      </div>
      <i class="bx bx-log-out" style="color:var(--text-muted);font-size:1rem;flex-shrink:0"></i>
    </a>
  </div>

</aside>
