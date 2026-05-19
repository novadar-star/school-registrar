/* ==========================================================
   nav.js — Sidebar toggle + navigation for multi-page setup
   ========================================================== */

(function () {
  // ── Sidebar toggle ─────────────────────────────────────
  const sidebar   = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleBtn');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
    });
  }

  // ── Nav item click → navigate ──────────────────────────
  document.querySelectorAll('.nav-item[data-href]').forEach(item => {
    item.addEventListener('click', () => {
      const href = item.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });

  // ── Auto-toast: convert alert bars to slide-in toasts ──
  // Reads .alert-success-bar and .alert-error-bar on page load,
  // shows them as a toast then fades them out after 4 seconds.
  document.addEventListener('DOMContentLoaded', function () {
    var successBar = document.querySelector('.alert-success-bar');
    var errorBar   = document.querySelector('.alert-error-bar');
    var bar        = successBar || errorBar;
    if (!bar) return;

    var msg  = bar.textContent.trim();
    var type = successBar ? 'success' : 'danger';

    // Hide the inline bar — we'll show a toast instead
    bar.style.display = 'none';

    showToast(msg, type);
  });
})();

/**
 * showToast(message, type)
 * type: 'success' | 'danger' | 'info'
 * Creates a toast element if one doesn't exist, then shows it.
 */
function showToast(message, type) {
  type = type || 'success';

  // Reuse existing toast element or create one
  var toast = document.getElementById('global-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'global-toast';
    toast.style.cssText = [
      'position:fixed',
      'bottom:28px',
      'right:28px',
      'min-width:260px',
      'max-width:420px',
      'background:#1f2937',
      'color:#fff',
      'padding:14px 20px',
      'border-radius:10px',
      'font-size:13px',
      'font-weight:600',
      'font-family:Inter,sans-serif',
      'box-shadow:0 8px 32px rgba(0,0,0,.22)',
      'display:flex',
      'align-items:center',
      'gap:10px',
      'z-index:9999',
      'opacity:0',
      'transform:translateY(16px)',
      'transition:opacity .25s,transform .25s',
      'pointer-events:none',
    ].join(';');
    document.body.appendChild(toast);
  }

  // Icon + border colour by type
  var icons   = { success: '✓', danger: '✕', info: 'ℹ' };
  var borders = { success: '#16a34a', danger: '#dc2626', info: '#494C8A' };
  toast.style.borderLeft = '4px solid ' + (borders[type] || borders.info);
  toast.innerHTML = '<span style="font-size:16px;">' + (icons[type] || icons.info) + '</span> ' + message;

  // Show
  requestAnimationFrame(function () {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
    toast.style.pointerEvents = 'auto';
  });

  // Auto-hide after 4 seconds
  clearTimeout(toast._hideTimer);
  toast._hideTimer = setTimeout(function () {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(16px)';
    toast.style.pointerEvents = 'none';
  }, 4000);
}

/**
 * confirmAction(message)
 * Wrapper around confirm() for destructive actions.
 * Returns true if confirmed, false otherwise.
 */
function confirmAction(message) {
  return window.confirm(message || 'Are you sure?');
}
