<?php
// ============================================================
//  SmartSchool — Header commun
//  Emplacement : includes/header.php
// ============================================================

$user        = currentUser();
$role        = currentRole();
$schoolName  = getSetting('school_name', APP_NAME);
$pageTitle   = $pageTitle  ?? APP_NAME;
$pageSection = $pageSection ?? '';

$unreadNotifs = $user ? countUnreadNotifications((int)$user['id']) : 0;
$unreadMsgs   = $user ? countUnreadMessages((int)$user['id'])      : 0;
?>
<!DOCTYPE html>
<html lang="fr" class="<?= themeClass() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= clean($pageTitle) ?> — <?= clean($schoolName) ?></title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Boxicons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <!-- CSS SmartSchool -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/variables.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/components.css">

  <!-- Variables JS globales -->
  <script>
    window.SS = {
      baseUrl   : '<?= BASE_URL ?>',
      assetsUrl : '<?= ASSETS_URL ?>',
      csrfToken : '<?= csrfToken() ?>',
      userId    : <?= $user ? (int)$user['id'] : 'null' ?>,
      userRole  : <?= (int)$role ?>,
      darkMode  : <?= isDarkMode() ? 'true' : 'false' ?>,
    };
  </script>
</head>
<body class="<?= themeClass() ?>">

<?php if ($user): ?>
<!-- ══════════════════════════════════════════
     TOPBAR
══════════════════════════════════════════ -->
<header class="topbar" id="topbar">

  <div class="topbar-left">
    <!-- Toggle sidebar -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
      <i class="bx bx-menu"></i>
    </button>

    <!-- Titre de la page -->
    <div style="display:flex;flex-direction:column">
      <span style="font-size:15px;font-weight:700;color:var(--text-primary);line-height:1.2">
        <?= clean($pageTitle) ?>
      </span>
      <span style="font-size:11px;color:var(--text-muted)">
        <?= clean($schoolName) ?>
      </span>
    </div>
  </div>

  <div class="topbar-right">

    <!-- Dark mode -->
    <button class="topbar-icon-btn" id="darkModeBtn" title="Mode sombre">
      <i class="bx <?= isDarkMode() ? 'bx-sun' : 'bx-moon' ?>"></i>
    </button>

    <!-- Notifications -->
    <div style="position:relative">
      <button class="topbar-icon-btn" id="notifBtn" title="Notifications">
        <i class="bx bx-bell"></i>
        <?php if ($unreadNotifs > 0): ?>
          <span class="notif-badge"><?= $unreadNotifs > 9 ? '9+' : $unreadNotifs ?></span>
        <?php endif; ?>
      </button>
      <!-- Panel notifications -->
      <div id="notifPanel" style="
        display      : none;
        position     : absolute;
        top          : calc(100% + 10px);
        right        : 0;
        width        : 320px;
        background   : var(--bg-card);
        border       : 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow   : var(--shadow-lg);
        z-index      : var(--z-dropdown);
        overflow     : hidden;
      ">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-light)">
          <span style="font-size:14px;font-weight:700;color:var(--text-primary)">Notifications</span>
          <?php if ($unreadNotifs > 0): ?>
            <a href="#" onclick="markAllRead(event)"
               style="font-size:12px;color:var(--primary);font-weight:600">
              Tout lire
            </a>
          <?php endif; ?>
        </div>
        <div id="notifList" style="max-height:300px;overflow-y:auto">
          <?php
          $notifs = dbFetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8",
            [(int)$user['id']]
          );
          if (empty($notifs)): ?>
            <div style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">
              <i class="bx bx-bell-off" style="font-size:2rem;display:block;margin-bottom:8px"></i>
              Aucune notification
            </div>
          <?php else: ?>
            <?php foreach ($notifs as $n):
              $colors = ['success'=>'#10b981','danger'=>'#ef4444','warning'=>'#f59e0b','info'=>'#3b82f6'];
              $icons  = ['success'=>'bx-check-circle','danger'=>'bx-x-circle','warning'=>'bx-error','info'=>'bx-info-circle'];
              $c = $colors[$n['type']] ?? '#6366f1';
              $i = $icons[$n['type']]  ?? 'bx-bell';
            ?>
            <div style="
              display      : flex;
              align-items  : flex-start;
              gap          : 12px;
              padding      : 12px 18px;
              border-bottom: 1px solid var(--border-light);
              background   : <?= $n['is_read'] ? 'transparent' : 'var(--primary-bg)' ?>;
              cursor       : pointer;
              transition   : background 0.2s;
            " onmouseenter="this.style.background='var(--bg-hover)'"
               onmouseleave="this.style.background='<?= $n['is_read'] ? 'transparent' : 'var(--primary-bg)' ?>'">
              <div style="width:34px;height:34px;border-radius:8px;background:<?= $c ?>20;color:<?= $c ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem">
                <i class="bx <?= $i ?>"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;color:var(--text-primary)"><?= clean($n['title']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= clean($n['message']) ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:4px"><?= timeAgo($n['created_at']) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="padding:10px 18px;border-top:1px solid var(--border-light);text-align:center">
          <a href="<?= BASE_URL ?>/admin/notifications.php"
             style="font-size:13px;color:var(--primary);font-weight:600">
            Voir toutes les notifications
          </a>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <div style="position:relative">
      <button class="topbar-icon-btn" id="msgBtn" title="Messages">
        <i class="bx bx-envelope"></i>
        <?php if ($unreadMsgs > 0): ?>
          <span class="notif-badge"><?= $unreadMsgs > 9 ? '9+' : $unreadMsgs ?></span>
        <?php endif; ?>
      </button>
      <!-- Panel messages -->
      <div id="msgPanel" style="
        display      : none;
        position     : absolute;
        top          : calc(100% + 10px);
        right        : 0;
        width        : 320px;
        background   : var(--bg-card);
        border       : 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow   : var(--shadow-lg);
        z-index      : var(--z-dropdown);
        overflow     : hidden;
      ">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-light)">
          <span style="font-size:14px;font-weight:700;color:var(--text-primary)">Messages</span>
          <a href="<?= BASE_URL ?>/admin/messages.php?compose=1"
             style="font-size:12px;color:var(--primary);font-weight:600;display:flex;align-items:center;gap:4px">
            <i class="bx bx-edit"></i> Nouveau
          </a>
        </div>
        <div style="max-height:300px;overflow-y:auto">
          <?php
          $msgs = dbFetchAll(
            "SELECT m.*, u.first_name, u.last_name
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE m.receiver_id = ?
             ORDER BY m.created_at DESC LIMIT 5",
            [(int)$user['id']]
          );
          if (empty($msgs)): ?>
            <div style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">
              <i class="bx bx-envelope-open" style="font-size:2rem;display:block;margin-bottom:8px"></i>
              Aucun message
            </div>
          <?php else: ?>
            <?php foreach ($msgs as $m): ?>
            <div style="
              display      : flex;
              align-items  : flex-start;
              gap          : 12px;
              padding      : 12px 18px;
              border-bottom: 1px solid var(--border-light);
              background   : <?= $m['is_read'] ? 'transparent' : 'var(--primary-bg)' ?>;
              cursor       : pointer;
            " onclick="window.location='<?= BASE_URL ?>/admin/messages.php'">
              <div class="avatar avatar-32"
                   style="background:var(--primary);flex-shrink:0">
                <?= getInitials($m['first_name'], $m['last_name']) ?>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;color:var(--text-primary)">
                  <?= clean($m['first_name']) ?> <?= clean($m['last_name']) ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?= clean($m['subject']) ?>
                </div>
                <div style="font-size:11px;color:var(--text-light);margin-top:3px">
                  <?= timeAgo($m['created_at']) ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="padding:10px 18px;border-top:1px solid var(--border-light);text-align:center">
          <a href="<?= BASE_URL ?>/admin/messages.php"
             style="font-size:13px;color:var(--primary);font-weight:600">
            Voir tous les messages
          </a>
        </div>
      </div>
    </div>

    <!-- Profil -->
    <div style="position:relative">
      <button class="topbar-profile" id="profileBtn">
        <div class="avatar avatar-32"
             style="background:<?= ROLE_COLORS[$role] ?? '#6366f1' ?>">
          <?= getInitials($user['first_name'], $user['last_name']) ?>
        </div>
        <div class="topbar-profile-info">
          <div class="profile-name">
            <?= clean($user['first_name']) ?> <?= clean($user['last_name']) ?>
          </div>
          <div class="profile-role"><?= ROLE_LABELS[$role] ?? '' ?></div>
        </div>
        <i class="bx bx-chevron-down" style="color:var(--text-muted);font-size:1rem"></i>
      </button>

      <!-- Panel profil -->
      <div id="profilePanel" style="
        display      : none;
        position     : absolute;
        top          : calc(100% + 10px);
        right        : 0;
        width        : 240px;
        background   : var(--bg-card);
        border       : 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow   : var(--shadow-lg);
        z-index      : var(--z-dropdown);
        overflow     : hidden;
      ">
        <!-- Infos utilisateur -->
        <div style="padding:18px;background:var(--bg-hover);border-bottom:1px solid var(--border-light)">
          <div class="avatar avatar-48"
               style="background:<?= ROLE_COLORS[$role] ?? '#6366f1' ?>;margin-bottom:12px">
            <?= getInitials($user['first_name'], $user['last_name']) ?>
          </div>
          <div style="font-size:14px;font-weight:700;color:var(--text-primary)">
            <?= clean($user['first_name']) ?> <?= clean($user['last_name']) ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
            <?= clean($user['email']) ?>
          </div>
          <div style="
            display     : inline-flex;
            align-items : center;
            gap         : 5px;
            margin-top  : 8px;
            padding     : 3px 10px;
            background  : <?= ROLE_COLORS[$role] ?? '#6366f1' ?>20;
            color       : <?= ROLE_COLORS[$role] ?? '#6366f1' ?>;
            border-radius: 99px;
            font-size   : 11px;
            font-weight : 700;
          ">
            <i class="bx <?= ROLE_ICONS[$role] ?? 'bx-user' ?>"></i>
            <?= ROLE_LABELS[$role] ?? '' ?>
          </div>
        </div>

        <!-- Menu -->
        <ul style="padding:6px 0">
          <?php
          $baseRoleUrl = match($role) {
            ROLE_TEACHER    => BASE_URL . '/teachers',
            ROLE_STUDENT    => BASE_URL . '/students',
            ROLE_PARENT     => BASE_URL . '/parents',
            ROLE_ACCOUNTANT => BASE_URL . '/accountant',
            default         => BASE_URL . '/admin',
          };
          ?>
          <li>
            <a href="<?= $baseRoleUrl ?>/settings.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--text-secondary);transition:background 0.15s"
               onmouseenter="this.style.background='var(--bg-hover)';this.style.color='var(--primary)'"
               onmouseleave="this.style.background='';this.style.color='var(--text-secondary)'">
              <i class="bx bx-user-circle" style="font-size:1.1rem"></i> Mon profil
            </a>
          </li>
          <li>
            <a href="<?= $baseRoleUrl ?>/settings.php" style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--text-secondary);transition:background 0.15s"
               onmouseenter="this.style.background='var(--bg-hover)';this.style.color='var(--primary)'"
               onmouseleave="this.style.background='';this.style.color='var(--text-secondary)'">
              <i class="bx bx-cog" style="font-size:1.1rem"></i> Parametres
            </a>
          </li>
          <li style="border-top:1px solid var(--border-light);margin-top:4px;padding-top:4px">
            <a href="<?= BASE_URL ?>/auth/logout.php"
               onclick="return confirm('Voulez-vous vous deconnecter ?')"
               style="display:flex;align-items:center;gap:10px;padding:10px 18px;font-size:13px;color:var(--danger);transition:background 0.15s"
               onmouseenter="this.style.background='var(--danger-bg)'"
               onmouseleave="this.style.background=''">
              <i class="bx bx-log-out" style="font-size:1.1rem"></i> Deconnexion
            </a>
          </li>
        </ul>
      </div>
    </div>

  </div>
</header>
<?php endif; ?>
