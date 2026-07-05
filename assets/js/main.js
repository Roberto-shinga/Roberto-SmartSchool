/* ============================================================
   SmartSchool — JavaScript Principal
   Fichier : assets/js/main.js
============================================================ */
'use strict';

/* ════════════════════════════════════════════════
   1. SIDEBAR
════════════════════════════════════════════════ */
const Sidebar = (() => {
  const KEY = 'ss_sidebar_sm';

  function init() {
    const toggle  = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    if (!toggle) return;

    // Restaurer etat desktop
    if (window.innerWidth > 768 && localStorage.getItem(KEY) === '1') {
      document.body.classList.add('sidebar-sm');
    }

    toggle.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        openMobile();
      } else {
        toggleDesktop();
      }
    });

    if (overlay) {
      overlay.addEventListener('click', closeMobile);
    }

    window.addEventListener('resize', () => {
      if (window.innerWidth > 768) closeMobile();
    });
  }

  function toggleDesktop() {
    const collapsed = document.body.classList.toggle('sidebar-sm');
    localStorage.setItem(KEY, collapsed ? '1' : '0');
  }

  function openMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.style.display = '';
    document.body.style.overflow = '';
  }

  return { init };
})();

/* ════════════════════════════════════════════════
   2. DARK MODE
════════════════════════════════════════════════ */
const DarkMode = (() => {
  function init() {
    const btn = document.getElementById('darkModeBtn');
    if (!btn) return;

    btn.addEventListener('click', toggle);
  }

  function toggle() {
    const dark = document.body.classList.toggle('dark-mode');
    document.documentElement.classList.toggle('dark-mode', dark);

    // Mettre a jour icone
    const btn  = document.getElementById('darkModeBtn');
    if (btn) {
      const icon = btn.querySelector('i');
      if (icon) icon.className = dark ? 'bx bx-sun' : 'bx bx-moon';
    }

    // Sauvegarder cookie
    const exp = new Date(Date.now() + 365 * 86400000).toUTCString();
    document.cookie = 'ss_dark_mode=' + (dark ? '1' : '0')
                    + ';expires=' + exp + ';path=/';

    // Mettre a jour graphiques
    Charts.updateTheme(dark);
  }

  return { init, toggle };
})();

/* ════════════════════════════════════════════════
   3. DROPDOWNS TOPBAR
════════════════════════════════════════════════ */
const Dropdowns = (() => {
  const panels = [
    { btn: 'notifBtn',   panel: 'notifPanel'   },
    { btn: 'msgBtn',     panel: 'msgPanel'     },
    { btn: 'profileBtn', panel: 'profilePanel' },
  ];

  function init() {
    panels.forEach(({ btn, panel }) => {
      const b = document.getElementById(btn);
      const p = document.getElementById(panel);
      if (!b || !p) return;

      b.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = p.style.display === 'block';
        closeAll();
        if (!isOpen) p.style.display = 'block';
      });
    });

    // Fermer en cliquant ailleurs
    document.addEventListener('click', closeAll);

    // Fermer avec Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAll();
    });
  }

  function closeAll() {
    panels.forEach(({ panel }) => {
      const p = document.getElementById(panel);
      if (p) p.style.display = '';
    });
  }

  return { init, closeAll };
})();

/* ════════════════════════════════════════════════
   4. TOASTS
════════════════════════════════════════════════ */
const Toast = (() => {
  const icons = {
    success: 'bx-check-circle',
    danger : 'bx-x-circle',
    warning: 'bx-error',
    info   : 'bx-info-circle',
  };

  function show(title, message = '', type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <i class="bx ${icons[type] || icons.info} toast-icon"></i>
      <div class="toast-body">
        <div class="toast-title">${esc(title)}</div>
        ${message ? `<div class="toast-message">${esc(message)}</div>` : ''}
      </div>
      <i class="bx bx-x toast-close" onclick="this.parentElement.remove()"></i>`;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity   = '0';
      toast.style.transform = 'translateX(110%)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  return {
    show,
    success: (t, m) => show(t, m, 'success'),
    error  : (t, m) => show(t, m, 'danger'),
    warning: (t, m) => show(t, m, 'warning'),
    info   : (t, m) => show(t, m, 'info'),
  };
})();

/* ════════════════════════════════════════════════
   5. MODALS
════════════════════════════════════════════════ */
const Modal = (() => {
  function init() {
    // Ouvrir via data-modal
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-modal]');
      if (btn) open(btn.dataset.modal);

      const close = e.target.closest('[data-modal-close], .modal-close');
      if (close) {
        const overlay = close.closest('.modal-overlay');
        if (overlay) closeEl(overlay);
      }

      if (e.target.classList.contains('modal-overlay')) {
        closeEl(e.target);
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const open = document.querySelector('.modal-overlay.open');
        if (open) closeEl(open);
      }
    });
  }

  function open(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function close(id) {
    const el = document.getElementById(id);
    if (el) closeEl(el);
  }

  function closeEl(el) {
    el.classList.remove('open');
    document.body.style.overflow = '';
  }

  return { init, open, close };
})();

/* ════════════════════════════════════════════════
   6. TABS
════════════════════════════════════════════════ */
const Tabs = (() => {
  function init() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.tab-btn');
      if (!btn) return;

      const target  = btn.dataset.tab;
      const wrapper = btn.closest('.tabs-wrapper') || document;

      wrapper.querySelectorAll('.tab-btn')
        .forEach(b => b.classList.remove('active'));
      wrapper.querySelectorAll('.tab-panel')
        .forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const panel = document.getElementById(target);
      if (panel) panel.classList.add('active');
    });
  }

  return { init };
})();

/* ════════════════════════════════════════════════
   7. COMPTEURS ANIMES
════════════════════════════════════════════════ */
const Counters = (() => {
  function init() {
    const els = document.querySelectorAll('[data-count]');
    if (!els.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animate(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });

    els.forEach(el => observer.observe(el));
  }

  function animate(el) {
    const target   = parseFloat(el.dataset.count);
    const duration = 1400;
    const start    = performance.now();

    function step(now) {
      const p = Math.min((now - start) / duration, 1);
      const e = 1 - Math.pow(1 - p, 3); // ease-out cubic
      el.textContent = Math.round(target * e).toLocaleString('fr-FR');
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  return { init };
})();

/* ════════════════════════════════════════════════
   8. GRAPHIQUES CHART.JS
════════════════════════════════════════════════ */
const Charts = (() => {
  const instances = {};

  const COLORS = {
    primary: '#6366f1',
    success: '#10b981',
    warning: '#f59e0b',
    danger : '#ef4444',
    cyan   : '#06b6d4',
    purple : '#a855f7',
    blue   : '#3b82f6',
    pink   : '#ec4899',
  };

  function defaults(dark) {
    return {
      text: dark ? '#cbd5e1' : '#64748b',
      grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)',
      bg  : dark ? '#1e293b' : '#ffffff',
    };
  }

  function globalDefaults(dark) {
    if (typeof Chart === 'undefined') return;
    const d = defaults(dark);
    Chart.defaults.color       = d.text;
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size   = 12;
  }

  function tooltip(dark) {
    const d = defaults(dark);
    return {
      backgroundColor: d.bg,
      titleColor     : dark ? '#f1f5f9' : '#0f172a',
      bodyColor      : d.text,
      borderColor    : dark ? '#334155' : '#e2e8f0',
      borderWidth    : 1,
      padding        : 12,
      cornerRadius   : 8,
    };
  }

  function make(id, config) {
    if (typeof Chart === 'undefined') return null;
    const canvas = document.getElementById(id);
    if (!canvas) return null;
    if (instances[id]) instances[id].destroy();
    instances[id] = new Chart(canvas, config);
    return instances[id];
  }

  // Graphique ligne
  function line(id, labels, datasets, opts = {}) {
    const dark = document.body.classList.contains('dark-mode');
    const d    = defaults(dark);
    return make(id, {
      type: 'line',
      data: {
        labels,
        datasets: datasets.map((ds, i) => ({
          tension             : 0.4,
          fill                : ds.fill ?? false,
          borderWidth         : 2.5,
          pointRadius         : 4,
          pointHoverRadius    : 6,
          borderColor         : ds.color || Object.values(COLORS)[i],
          backgroundColor     : ds.fill
            ? alpha(ds.color || Object.values(COLORS)[i], 0.10)
            : 'transparent',
          pointBackgroundColor: ds.color || Object.values(COLORS)[i],
          ...ds,
        })),
      },
      options: {
        responsive         : true,
        maintainAspectRatio: false,
        interaction        : { mode: 'index', intersect: false },
        plugins: {
          legend : { position: 'top', labels: { usePointStyle: true, padding: 16 } },
          tooltip: tooltip(dark),
        },
        scales: {
          x: { grid: { color: d.grid }, ticks: { color: d.text } },
          y: { grid: { color: d.grid }, ticks: { color: d.text }, beginAtZero: true },
        },
        ...opts,
      },
    });
  }

  // Graphique barres
  function bar(id, labels, datasets, opts = {}) {
    const dark = document.body.classList.contains('dark-mode');
    const d    = defaults(dark);
    return make(id, {
      type: 'bar',
      data: {
        labels,
        datasets: datasets.map((ds, i) => ({
          borderRadius   : 6,
          borderWidth    : 0,
          backgroundColor: ds.color || Object.values(COLORS)[i],
          ...ds,
        })),
      },
      options: {
        responsive         : true,
        maintainAspectRatio: false,
        interaction        : { mode: 'index', intersect: false },
        plugins: {
          legend : { position: 'top', labels: { usePointStyle: true, padding: 16 } },
          tooltip: tooltip(dark),
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: d.text } },
          y: { grid: { color: d.grid }, ticks: { color: d.text }, beginAtZero: true },
        },
        ...opts,
      },
    });
  }

  // Graphique donut
  function donut(id, labels, data, colors, opts = {}) {
    const dark = document.body.classList.contains('dark-mode');
    return make(id, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data, backgroundColor: colors, hoverOffset: 6, borderWidth: 0 }],
      },
      options: {
        responsive         : true,
        maintainAspectRatio: false,
        cutout             : opts.cutout || '68%',
        plugins: {
          legend : { position: 'bottom', labels: { usePointStyle: true, padding: 14 } },
          tooltip: tooltip(dark),
        },
        ...opts,
      },
    });
  }

  // Mettre a jour le theme de tous les graphiques
  function updateTheme(dark) {
    globalDefaults(dark);
    Object.values(instances).forEach(chart => {
      if (!chart) return;
      const d = defaults(dark);
      if (chart.options.scales) {
        Object.values(chart.options.scales).forEach(scale => {
          if (scale.grid) scale.grid.color = d.grid;
          scale.ticks = { ...scale.ticks, color: d.text };
        });
      }
      chart.update();
    });
  }

  function alpha(hex, a) {
    const r = parseInt(hex.slice(1,3), 16);
    const g = parseInt(hex.slice(3,5), 16);
    const b = parseInt(hex.slice(5,7), 16);
    return `rgba(${r},${g},${b},${a})`;
  }

  function init() {
    globalDefaults(document.body.classList.contains('dark-mode'));
  }

  return { init, line, bar, donut, updateTheme, COLORS };
})();

/* ════════════════════════════════════════════════
   9. FORMULAIRES
════════════════════════════════════════════════ */
const Forms = (() => {
  function init() {
    // Toggle mot de passe
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-toggle-pwd]');
      if (!btn) return;
      const input = document.getElementById(btn.dataset.togglePwd);
      const icon  = btn.querySelector('i');
      if (!input) return;
      if (input.type === 'password') {
        input.type    = 'text';
        if (icon) icon.className = 'bx bx-hide';
      } else {
        input.type    = 'password';
        if (icon) icon.className = 'bx bx-show';
      }
    });

    // Confirmation suppression
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-confirm]');
      if (!btn) return;
      if (!confirm(btn.dataset.confirm || 'Confirmer cette action ?')) {
        e.preventDefault();
      }
    });

    // Filtre tableau en temps reel
    document.querySelectorAll('[data-search]').forEach(input => {
      input.addEventListener('input', () => {
        const tableId = input.dataset.search;
        const table   = document.getElementById(tableId);
        if (!table) return;
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      });
    });
  }

  return { init };
})();

/* ════════════════════════════════════════════════
   10. UTILITAIRES
════════════════════════════════════════════════ */
function esc(str) {
  return String(str || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function debounce(fn, ms = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Marquer toutes les notifications comme lues
async function markAllRead(e) {
  e.preventDefault();
  try {
    await fetch(window.SS.baseUrl + '/includes/ajax/mark_notif_read.php', {
      method : 'POST',
      headers: { 'X-CSRF-Token': window.SS.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
    });
    document.querySelectorAll('.notif-badge').forEach(b => b.remove());
    Toast.success('Notifications', 'Toutes les notifications ont ete lues.');
    Dropdowns.closeAll();
  } catch { Toast.error('Erreur', 'Impossible de marquer comme lues.'); }
}

/* ════════════════════════════════════════════════
   EXPOSITION GLOBALE
════════════════════════════════════════════════ */
window.SS_Toast  = Toast;
window.SS_Modal  = Modal;
window.SS_Charts = Charts;

/* ════════════════════════════════════════════════
   INITIALISATION
════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  Sidebar.init();
  DarkMode.init();
  Dropdowns.init();
  Modal.init();
  Tabs.init();
  Counters.init();
  Charts.init();
  Forms.init();

  console.log('%c🎓 SmartSchool chargé !', 'color:#6366f1;font-weight:800;font-size:14px');
});
