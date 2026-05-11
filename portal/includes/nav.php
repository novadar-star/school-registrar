<?php
$active_portal = $active_portal ?? '';
// Unread parent notifications count
$notif_count = 0;
if (isset($parent_id)) {
  $nr = $conn->query("SELECT COUNT(*) as c FROM parent_notifications WHERE parent_id=$parent_id AND is_read=0");
  if ($nr) $notif_count = $nr->fetch_assoc()['c'];
}
?>
<nav class="portal-nav">
  <div class="portal-nav-brand">
    <img src="../images/COJ.png" alt="COJ"/>
    <span>Parent Portal</span>
  </div>
  <div class="portal-nav-links">
    <a href="dashboard.php" class="<?= $active_portal==='dashboard'?'active':'' ?>"><i class="bi bi-grid-fill"></i> Dashboard</a>
    <a href="requirements.php" class="<?= $active_portal==='requirements'?'active':'' ?>"><i class="bi bi-folder2-open"></i> Requirements</a>
    <a href="soa.php" class="<?= $active_portal==='soa'?'active':'' ?>"><i class="bi bi-receipt"></i> Statement of Account</a>
    <a href="notifications.php" class="<?= $active_portal==='notifications'?'active':'' ?>" style="position:relative;">
      <i class="bi bi-bell-fill"></i> Notifications
      <span class="notif-live-badge" style="position:absolute;top:-4px;right:-8px;background:#dc2626;color:#fff;border-radius:999px;font-size:9px;padding:1px 5px;font-weight:700;<?= $notif_count > 0 ? '' : 'display:none;' ?>"><?= $notif_count ?></span>
    </a>
  </div>
  <div class="portal-nav-user">
    <i class="bi bi-person-circle"></i>
    <span><?= htmlspecialchars($parent_name) ?></span>
    <a href="logout.php" class="portal-logout"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</nav>

<script>
// ── Live notification badge polling (every 30s) ────────────
(function () {
  function updateBadge(count) {
    document.querySelectorAll('.notif-live-badge').forEach(function (el) {
      if (count > 0) {
        el.textContent = count;
        el.style.display = 'inline';
      } else {
        el.style.display = 'none';
      }
    });
  }

  function poll() {
    fetch('notif_count.php')
      .then(function (r) { return r.json(); })
      .then(function (data) { updateBadge(data.count || 0); })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 30000);
})();
</script>
