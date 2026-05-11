/* ==========================================================
   nav.js — Sidebar toggle + navigation for multi-page setup

   ========================================================== */

// Initialization for ES Users

(function () {
//for sidebar toggle
  const sidebar   = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleBtn');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
    });
  }

  // ── Nav item click → navigate to the target page ────────
  document.querySelectorAll('.nav-item[data-href]').forEach(item => {
    item.addEventListener('click', () => {
      const href = item.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });
})();
