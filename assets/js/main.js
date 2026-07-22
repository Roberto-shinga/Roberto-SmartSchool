/* ============================================================
   SmartSchool RDC — JavaScript principal
   Fichier : assets/js/main.js
============================================================ */

'use strict';

// ════════════════════════════════════════════════
//  SIDEBAR
// ════════════════════════════════════════════════
const SS = {

  init() {
    this.sidebar();
    this.darkMode();
    this.modals();
    this.dropdowns();
    this.toasts();
    this.tabs();
    this.forms();
    this.animateOnScroll();
    this.progressBars();
    this.tableSearch();
  },

  // ── Sidebar toggle ──────────────────────────
  sidebar() {
    const toggle   = document.getElementById('sidebarToggle');
    const overlay  = document.getElementById('sidebarOverlay');
    const sidebar  = document.getElementById('sidebar');
    const body     = document.body;

    const isMobile = () => window.innerWidth < 769;

    if (toggle) {
      toggle.addEventListener('click', () => {
        if (isMobile()) {
          sidebar?.classList.toggle('open');
          overlay?.classList.toggle('open');
        } else {
          body.classList.toggle('sidebar-sm');
          localStorage.setItem('ss_sidebar_sm', body.classList.contains('sidebar-sm') ? '1' : '0');
        }
      });
    }

    // Restaurer état sidebar
    if (!isMobile() && localStorage.getItem('ss_sidebar_sm') === '1') {
      body.classList.add('sidebar-sm');
    }

    overlay?.addEventListener('click', () => {
      sidebar?.classList.remove('open');
      overlay.classList.remove('open');
    });

    // Fermer sur redimensionnement
    window.addEventListener('resize', () => {
      if (!isMobile()) {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
      }
    });
  },

  // ── Dark mode ───────────────────────────────
  darkMode() {
    const btn = document.getElementById('darkModeBtn');
    if (!btn) return;

    btn.addEventListener('click', () => {
      const dark = document.body.classList.toggle('dark-mode');
      document.documentElement.classList.toggle('dark-mode', dark);
      const icon = btn.querySelector('i');
      if (icon) icon.className = dark ? 'bx bx-sun' : 'bx bx-moon';
      const exp = new Date(Date.now() + 365 * 86400000).toUTCString();
      document.cookie = `ss_dark_mode=${dark ? '1' : '0'};expires=${exp};path=/`;
    });
  },

  // ── Modals ──────────────────────────────────
  modals() {
    // Ouvrir via data-modal="idModal"
    document.querySelectorAll('[data-modal]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const id = btn.dataset.modal;
        document.getElementById(id)?.classList.add('open');
      });
    });

    // Fermer via data-modal-close
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.closest('.modal-overlay')?.classList.remove('open');
      });
    });

    // Fermer en cliquant overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
      });
    });

    // Fermer avec Echap
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });
  },

  openModal(id)  { document.getElementById(id)?.classList.add('open'); },
  closeModal(id) { document.getElementById(id)?.classList.remove('open'); },

  // ── Dropdowns ───────────────────────────────
  dropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach(trigger => {
      const menuId = trigger.dataset.dropdown;
      const menu   = document.getElementById(menuId);
      if (!menu) return;

      trigger.addEventListener('click', e => {
        e.stopPropagation();
        const open = menu.classList.contains('open');
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
        if (!open) menu.classList.add('open');
      });
    });

    document.addEventListener('click', () => {
      document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    });
  },

  // ── Toasts ──────────────────────────────────
  toasts() {
    // Auto-dismiss les toasts existants
    document.querySelectorAll('.toast').forEach(t => this._autoClose(t));
  },

  toast(message, type = 'info', title = '', duration = 4000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const icons = { success:'bx-check-circle', danger:'bx-x-circle', warning:'bx-error', info:'bx-info-circle' };
    const titles = { success:'Succès', danger:'Erreur', warning:'Attention', info:'Info' };

    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `
      <i class="bx ${icons[type] || icons.info} toast-icon"></i>
      <div>
        <div class="toast-title">${title || titles[type] || 'Notification'}</div>
        <div class="toast-message">${message}</div>
      </div>
      <i class="bx bx-x toast-close"></i>`;

    t.querySelector('.toast-close').addEventListener('click', () => this._removeToast(t));
    container.appendChild(t);
    this._autoClose(t, duration);
  },

  _autoClose(t, duration = 4000) {
    setTimeout(() => this._removeToast(t), duration);
  },

  _removeToast(t) {
    t.style.animation = 'slideInRight 0.3s ease reverse';
    setTimeout(() => t.remove(), 280);
  },

  // ── Tabs ────────────────────────────────────
  tabs() {
    document.querySelectorAll('.tabs').forEach(tabGroup => {
      tabGroup.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const target = btn.dataset.tab;
          const parent = btn.closest('.tabs').parentElement;

          tabGroup.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');

          parent.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
          parent.querySelector(`#${target}`)?.classList.add('active');
        });
      });
    });
  },

  // ── Formulaires ─────────────────────────────
  forms() {
    // Confirm avant suppression
    document.querySelectorAll('[data-confirm]').forEach(el => {
      el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) e.preventDefault();
      });
    });

    // Spinner sur submit
    document.querySelectorAll('form[data-loading]').forEach(form => {
      form.addEventListener('submit', () => {
        const btn = form.querySelector('[type="submit"]');
        if (btn) {
          btn.disabled = true;
          const orig = btn.innerHTML;
          btn.innerHTML = `<span style="width:15px;height:15px;border:2px solid rgba(255,255,255,0.35);border-top-color:#fff;border-radius:50%;display:inline-block;animation:spin 0.7s linear infinite"></span> Patientez...`;
          // Restaurer si erreur (5s max)
          setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 8000);
        }
      });
    });

    // Toggle mot de passe
    document.querySelectorAll('[data-toggle-pwd]').forEach(btn => {
      btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePwd);
        const icon  = btn.querySelector('i');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        if (icon) icon.className = input.type === 'password' ? 'bx bx-show' : 'bx bx-hide';
      });
    });

    // Auto uppercase matricule
    document.querySelectorAll('[data-uppercase]').forEach(input => {
      input.addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
      });
    });
  },

  // ── Animations entrée viewport ──────────────
  animateOnScroll() {
    if (!window.IntersectionObserver) return;
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('.anim-scroll').forEach(el => obs.observe(el));
  },

  // ── Progress bars animées ───────────────────
  progressBars() {
    document.querySelectorAll('.progress-bar[data-value]').forEach(bar => {
      const val = bar.dataset.value || 0;
      requestAnimationFrame(() => {
        setTimeout(() => { bar.style.width = val + '%'; }, 100);
      });
    });
  },

  // ── Recherche tableau côté client ───────────
  tableSearch() {
    document.querySelectorAll('[data-table-search]').forEach(input => {
      const tableId = input.dataset.tableSearch;
      const table   = document.getElementById(tableId);
      if (!table) return;

      input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      });
    });
  },

  // ── Confirmer suppression ───────────────────
  confirmDelete(message = 'Voulez-vous vraiment supprimer cet element ?') {
    return confirm(message);
  },

  // ── Copier dans le presse-papier ─────────────
  copy(text) {
    navigator.clipboard?.writeText(text).then(() => {
      this.toast('Copie dans le presse-papier', 'success');
    });
  },

  // ── Spinner global ───────────────────────────
  showSpinner() {
    let s = document.getElementById('ss-spinner');
    if (!s) {
      s = document.createElement('div');
      s.id = 'ss-spinner';
      s.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.35);z-index:9999;display:flex;align-items:center;justify-content:center';
      s.innerHTML = '<div style="width:48px;height:48px;border:4px solid rgba(255,255,255,0.25);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite"></div>';
      document.body.appendChild(s);
    }
  },

  hideSpinner() {
    document.getElementById('ss-spinner')?.remove();
  },
};

// ── Démarrage ────────────────────────────────
document.addEventListener('DOMContentLoaded', () => SS.init());

// Exposer globalement
window.SS = SS;
