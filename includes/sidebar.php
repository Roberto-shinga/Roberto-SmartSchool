<?php
// ============================================================
//  SmartSchool — Sidebar + Topbar (dynamique par rôle)
//  Fichier : includes/sidebar.php
// ============================================================

$user        = currentUser();
$roleId      = currentRole();
$schoolName  = getSetting('school_name', APP_NAME);
$unreadMsgs  = countUnreadMessages($user['id']);
$unreadNotif = countUnreadNotifications($user['id']);
$section     = $pageSection ?? '';

// ── Menus par rôle ──────────────────────────────────────────
$menus = [];

if ($roleId === ROLE_SUPER_ADMIN) {
    $menus = [
        ['section' => 'SUPERVISION'],
        ['url'=>'/superadmin/index.php',        'icon'=>'bx-home-alt',       'label'=>'Tableau de bord',       'key'=>'dashboard'],
        ['section' => 'ADMINISTRATION'],
        ['url'=>'/superadmin/admins.php',       'icon'=>'bx-user-check',     'label'=>'Administrateurs',       'key'=>'admins'],
        ['url'=>'/superadmin/roles.php',        'icon'=>'bx-key',            'label'=>'Roles & permissions',   'key'=>'roles'],
        ['section' => 'SECURITE'],
        ['url'=>'/superadmin/security.php',     'icon'=>'bx-lock-alt',       'label'=>'Parametres de securite','key'=>'security'],
        ['url'=>'/superadmin/audit-logs.php',   'icon'=>'bx-list-ul',        'label'=>'Journaux d\'audit',      'key'=>'audit-logs'],
        ['section' => 'SYSTEME'],
        ['url'=>'/superadmin/backups.php',      'icon'=>'bx-cloud-upload',   'label'=>'Sauvegardes',           'key'=>'backups'],
        ['url'=>'/superadmin/maintenance.php',  'icon'=>'bx-wrench',         'label'=>'Maintenance',           'key'=>'maintenance'],
    ];
} elseif ($roleId === ROLE_ADMIN) {
    $menus = [
        ['section' => 'PRINCIPAL'],
        ['url'=>'/admin/index.php',       'icon'=>'bx-home-alt',      'label'=>'Tableau de bord',  'key'=>'dashboard'],
        ['url'=>'/admin/students.php',    'icon'=>'bx-graduation',    'label'=>'Élèves',           'key'=>'students'],
        ['url'=>'/admin/teachers.php',    'icon'=>'bx-chalkboard',    'label'=>'Enseignants',      'key'=>'teachers'],
        ['url'=>'/admin/accountants.php', 'icon'=>'bx-calculator',    'label'=>'Comptables',       'key'=>'accountants'],
        ['url'=>'/admin/parents.php',     'icon'=>'bx-group',         'label'=>'Parents',          'key'=>'parents'],
        ['section' => 'SCOLARITE'],
        ['url'=>'/admin/classes.php',     'icon'=>'bx-building',      'label'=>'Classes',          'key'=>'classes'],
        ['url'=>'/admin/subjects.php',    'icon'=>'bx-book-open',     'label'=>'Matières',         'key'=>'subjects'],
        ['url'=>'/admin/timetable.php',   'icon'=>'bx-calendar',      'label'=>'Emplois du temps', 'key'=>'timetable'],
        ['url'=>'/admin/grades.php',      'icon'=>'bx-star',          'label'=>'Notes',            'key'=>'grades'],
        ['url'=>'/admin/attendance.php',  'icon'=>'bx-check-square',  'label'=>'Présences',        'key'=>'attendance'],
        ['url'=>'/admin/reports.php',     'icon'=>'bx-file',          'label'=>'Bulletins',        'key'=>'reports'],
        ['section' => 'FINANCES'],
        ['url'=>'/admin/fees.php',        'icon'=>'bx-money',         'label'=>'Frais scolaires',  'key'=>'fees'],
        ['url'=>'/admin/payments.php',    'icon'=>'bx-credit-card',   'label'=>'Paiements',        'key'=>'payments'],
        ['url'=>'/admin/financial.php',   'icon'=>'bx-bar-chart-alt', 'label'=>'Rapport financier','key'=>'financial'],
        ['section' => 'COMMUNICATION'],
        ['url'=>'/admin/notifications.php','icon'=>'bx-bell',         'label'=>'Notifications',    'key'=>'notifications', 'badge'=>$unreadNotif],
        ['url'=>'/admin/messages.php',    'icon'=>'bx-envelope',      'label'=>'Messages',         'key'=>'messages',      'badge'=>$unreadMsgs],
        ['section' => 'SYSTEME'],
        ['url'=>'/admin/analytics.php',   'icon'=>'bx-line-chart',    'label'=>'Statistiques',     'key'=>'analytics'],
        ['url'=>'/admin/settings.php',    'icon'=>'bx-cog',           'label'=>'Paramètres',       'key'=>'settings'],
        ['url'=>'/admin/logs.php',        'icon'=>'bx-list-ul',       'label'=>'Journaux',         'key'=>'logs'],
    ];
} elseif ($roleId === ROLE_TEACHER) {
    $menus = [
        ['section' => 'MON ESPACE'],
        ['url'=>'/teachers/index.php',      'icon'=>'bx-home-alt',     'label'=>'Tableau de bord', 'key'=>'dashboard'],
        ['url'=>'/teachers/attendance.php', 'icon'=>'bx-check-square', 'label'=>'Faire l\'appel', 'key'=>'attendance'],
        ['url'=>'/teachers/grades.php',     'icon'=>'bx-star',         'label'=>'Mes notes',       'key'=>'grades'],
        ['url'=>'/teachers/students.php',   'icon'=>'bx-graduation',   'label'=>'Mes élèves',      'key'=>'students'],
        ['url'=>'/teachers/timetable.php',  'icon'=>'bx-calendar',     'label'=>'Mon planning',    'key'=>'timetable'],
        ['url'=>'/teachers/reports.php',    'icon'=>'bx-file',         'label'=>'Bulletins',       'key'=>'reports'],
        ['section' => 'COMMUNICATION'],
        ['url'=>'/teachers/messages.php',   'icon'=>'bx-envelope',     'label'=>'Messages',        'key'=>'messages', 'badge'=>$unreadMsgs],
    ];
} elseif ($roleId === ROLE_STUDENT) {
    $menus = [
        ['section' => 'MON ESPACE'],
        ['url'=>'/students/index.php',    'icon'=>'bx-home-alt',   'label'=>'Tableau de bord', 'key'=>'dashboard'],
        ['url'=>'/students/grades.php',   'icon'=>'bx-star',       'label'=>'Mes notes',       'key'=>'grades'],
        ['url'=>'/students/reports.php',  'icon'=>'bx-file',       'label'=>'Mes bulletins',   'key'=>'reports'],
        ['url'=>'/students/timetable.php','icon'=>'bx-calendar',   'label'=>'Mon emploi du temps','key'=>'timetable'],
        ['url'=>'/students/payments.php', 'icon'=>'bx-credit-card','label'=>'Mes paiements',   'key'=>'payments'],
        ['section' => 'APPRENTISSAGE'],
        ['url'=>'/learning/index.php',    'icon'=>'bx-book-open',  'label'=>'Mes cours',       'key'=>'learning'],
        ['url'=>'/learning/quizzes.php',  'icon'=>'bx-brain',      'label'=>'Quiz',             'key'=>'quizzes'],
        ['url'=>'/learning/badges.php',   'icon'=>'bx-award',      'label'=>'Mes badges',       'key'=>'badges'],
        ['section' => 'COMMUNICATION'],
        ['url'=>'/students/messages.php', 'icon'=>'bx-envelope',   'label'=>'Messages',         'key'=>'messages', 'badge'=>$unreadMsgs],
    ];
} elseif ($roleId === ROLE_PARENT) {
    $menus = [
        ['section' => 'MON ESPACE'],
        ['url'=>'/parents/index.php',    'icon'=>'bx-home-alt',   'label'=>'Tableau de bord', 'key'=>'dashboard'],
        ['url'=>'/parents/children.php', 'icon'=>'bx-group',      'label'=>'Mes enfants',     'key'=>'children'],
        ['url'=>'/parents/grades.php',   'icon'=>'bx-star',       'label'=>'Notes & bulletins','key'=>'grades'],
        ['url'=>'/parents/attendance.php','icon'=>'bx-check-square','label'=>'Présences',      'key'=>'attendance'],
        ['url'=>'/parents/payments.php', 'icon'=>'bx-credit-card','label'=>'Paiements',        'key'=>'payments'],
        ['section' => 'COMMUNICATION'],
        ['url'=>'/parents/messages.php', 'icon'=>'bx-envelope',   'label'=>'Messages',         'key'=>'messages', 'badge'=>$unreadMsgs],
    ];
} elseif ($roleId === ROLE_ACCOUNTANT) {
    $menus = [
        ['section' => 'FINANCES'],
        ['url'=>'/accountant/index.php',    'icon'=>'bx-home-alt',      'label'=>'Tableau de bord', 'key'=>'dashboard'],
        ['url'=>'/accountant/payments.php', 'icon'=>'bx-credit-card',   'label'=>'Paiements',       'key'=>'payments'],
        ['url'=>'/accountant/fees.php',     'icon'=>'bx-money',         'label'=>'Frais scolaires', 'key'=>'fees'],
        ['url'=>'/accountant/proofs.php',   'icon'=>'bx-file-blank',    'label'=>'Preuves bancaires','key'=>'proofs'],
        ['url'=>'/accountant/reports.php',  'icon'=>'bx-bar-chart-alt', 'label'=>'Rapports',        'key'=>'reports'],
        ['section' => 'COMMUNICATION'],
        ['url'=>'/accountant/messages.php', 'icon'=>'bx-envelope',      'label'=>'Messages',        'key'=>'messages', 'badge'=>$unreadMsgs],
    ];
}

// Avatar couleur selon rôle
$roleColor  = ROLE_COLORS[$roleId] ?? '#6366f1';
$roleColors_css = [
    1=>'var(--purple)',2=>'var(--primary)',3=>'var(--cyan)',
    4=>'var(--success)',5=>'var(--warning)',6=>'var(--danger)',
];
$avatarBg = $roleColors_css[$roleId] ?? 'var(--primary)';
?>

<!-- ══ SIDEBAR ══════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <a href="<?= BASE_URL ?>/index.php" class="sidebar-brand">
    <div class="sidebar-brand-icon"><i class="bx bx-buildings"></i></div>
    <div class="sidebar-brand-text">
      <div class="sidebar-brand-name"><?= clean($schoolName) ?></div>
      <div class="sidebar-brand-sub">Gestion scolaire RDC</div>
    </div>
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav" id="sidebarNav">
    <?php foreach ($menus as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="nav-section-title"><?= $item['section'] ?></div>
      <?php else: ?>
        <div class="nav-item">
          <a href="<?= BASE_URL . $item['url'] ?>"
             class="nav-link <?= $section === $item['key'] ? 'active' : '' ?>"
             data-tip="<?= clean($item['label']) ?>">
            <i class="bx <?= $item['icon'] ?> nav-icon"></i>
            <span class="nav-text"><?= clean($item['label']) ?></span>
            <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
              <span class="nav-badge"><?= $item['badge'] > 99 ? '99+' : $item['badge'] ?></span>
            <?php endif; ?>
          </a>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Footer utilisateur -->
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/auth/profile.php" class="sidebar-user">
      <div class="avatar avatar-40" style="background:<?= $avatarBg ?>">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= UPLOADS_URL ?>/avatars/<?= clean($user['avatar']) ?>" alt="avatar">
        <?php else: ?>
          <?= getInitials($user['first_name'], $user['last_name']) ?>
        <?php endif; ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= clean($user['first_name']) ?> <?= clean($user['last_name']) ?></div>
        <div class="sidebar-user-role"><?= clean(ROLE_LABELS[$roleId] ?? '') ?></div>
      </div>
    </a>
  </div>
</aside>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ══ TOPBAR ═══════════════════════════════════════ -->
<header class="topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="sidebarToggle" title="Menu">
      <i class="bx bx-menu"></i>
    </button>
    <div style="display:none" class="topbar-breadcrumb" id="topbarBreadcrumb">
      <!-- Breadcrumb injecté dynamiquement -->
    </div>
  </div>

  <div class="topbar-right">

    <!-- Recherche rapide -->
    <div style="position:relative;display:none" class="topbar-search">
      <i class="bx bx-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted)"></i>
      <input type="text" placeholder="Rechercher..."
             style="padding:7px 14px 7px 32px;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--bg-body);font-size:13px;color:var(--text-primary);font-family:var(--font);width:200px">
    </div>

    <!-- Dark mode -->
    <button class="topbar-icon-btn" id="darkModeBtn" title="Mode sombre/clair">
      <i class="bx <?= isDarkMode() ? 'bx-sun' : 'bx-moon' ?>"></i>
    </button>

    <!-- Notifications -->
    <?php if ($roleId !== ROLE_SUPER_ADMIN): ?>
    <div style="position:relative">
      <?php
        $msgBase = $roleId === ROLE_ADMIN ? 'admin' : ($roleId == 3 ? 'teachers' : ($roleId == 4 ? 'students' : ($roleId == 5 ? 'parents' : 'accountant')));
      ?>
      <a href="<?= BASE_URL ?>/<?= $msgBase ?>/messages.php"
         class="topbar-icon-btn" title="Messages">
        <i class="bx bx-envelope"></i>
        <?php if ($unreadMsgs > 0): ?>
          <span class="notif-badge"><?= $unreadMsgs > 9 ? '9+' : $unreadMsgs ?></span>
        <?php endif; ?>
      </a>
    </div>
    <?php endif; ?>

    <div style="position:relative">
      <a href="#" class="topbar-icon-btn" title="Notifications"
         data-dropdown="notifMenu">
        <i class="bx bx-bell"></i>
        <?php if ($unreadNotif > 0): ?>
          <span class="notif-badge"><?= $unreadNotif > 9 ? '9+' : $unreadNotif ?></span>
        <?php endif; ?>
      </a>
      <!-- Dropdown notifications -->
      <div id="notifMenu" class="dropdown-menu" style="right:0;top:44px;width:300px">
        <div style="padding:12px 16px;font-size:13px;font-weight:700;color:var(--text-primary);border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
          Notifications
          <?php if ($unreadNotif > 0): ?>
            <a href="<?= BASE_URL ?>/auth/mark-notif-read.php" style="font-size:12px;color:var(--primary)">Tout lire</a>
          <?php endif; ?>
        </div>
        <?php
        $notifs = dbFetchAll(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6",
            [$user['id']]
        );
        if (empty($notifs)): ?>
          <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px">
            <i class="bx bx-bell-off" style="font-size:1.5rem;display:block;margin-bottom:6px"></i>
            Aucune notification
          </div>
        <?php else: ?>
          <?php foreach ($notifs as $n): ?>
          <a href="<?= $n['link'] ? clean($n['link']) : '#' ?>" style="display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border-light);text-decoration:none;background:<?= $n['is_read']?'var(--bg-card)':'var(--primary-bg)' ?>;transition:background var(--transition)">
            <div style="width:8px;height:8px;border-radius:50%;background:var(--<?= clean($n['type']) ?>);flex-shrink:0;margin-top:6px"></div>
            <div>
              <div style="font-size:12.5px;font-weight:600;color:var(--text-primary)"><?= clean($n['title']) ?></div>
              <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px"><?= clean(mb_strimwidth($n['message'], 0, 55, '...')) ?></div>
              <div style="font-size:11px;color:var(--text-light);margin-top:4px"><?= timeAgo($n['created_at']) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Profil -->
    <div style="position:relative">
      <div class="topbar-profile" data-dropdown="profileMenu">
        <div class="avatar avatar-32" style="background:<?= $avatarBg ?>">
          <?php if (!empty($user['avatar'])): ?>
            <img src="<?= UPLOADS_URL ?>/avatars/<?= clean($user['avatar']) ?>" alt="avatar">
          <?php else: ?>
            <?= getInitials($user['first_name'], $user['last_name']) ?>
          <?php endif; ?>
        </div>
        <div class="topbar-profile-info">
          <div class="profile-name"><?= clean($user['first_name']) ?></div>
          <div class="profile-role"><?= clean(ROLE_LABELS[$roleId] ?? '') ?></div>
        </div>
        <i class="bx bx-chevron-down" style="color:var(--text-muted);font-size:1rem"></i>
      </div>

      <!-- Dropdown profil -->
      <div id="profileMenu" class="dropdown-menu" style="right:0;top:52px;min-width:200px">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border-light)">
          <div style="font-size:13.5px;font-weight:700;color:var(--text-primary)"><?= clean($user['first_name']) ?> <?= clean($user['last_name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?= clean($user['email'] ?? '') ?></div>
        </div>
        <a href="<?= BASE_URL ?>/auth/profile.php" style="display:flex;align-items:center;gap:10px;padding:10px 16px;color:var(--text-secondary);text-decoration:none;font-size:13.5px;transition:background var(--transition)">
          <i class="bx bx-user"></i> Mon profil
        </a>
        <a href="<?= BASE_URL ?>/auth/change-password.php" style="display:flex;align-items:center;gap:10px;padding:10px 16px;color:var(--text-secondary);text-decoration:none;font-size:13.5px;transition:background var(--transition)">
          <i class="bx bx-lock-alt"></i> Mot de passe
        </a>
        <div style="border-top:1px solid var(--border-light);margin-top:4px">
          <a href="<?= BASE_URL ?>/auth/logout.php" style="display:flex;align-items:center;gap:10px;padding:10px 16px;color:var(--danger);text-decoration:none;font-size:13.5px;font-weight:600;transition:background var(--transition)">
            <i class="bx bx-log-out"></i> Se déconnecter
          </a>
        </div>
      </div>
    </div>

  </div>
</header>

<!-- Style dropdown -->
<style>
.dropdown-menu {
  position:absolute;background:var(--bg-card);border:1px solid var(--border);
  border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);z-index:var(--z-dropdown);
  display:none;overflow:hidden;
}
.dropdown-menu.open { display:block;animation:fadeUp 0.18s ease; }
</style>
