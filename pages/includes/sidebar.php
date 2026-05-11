<?php
$active_page = $active_page ?? '';
$role        = $_SESSION['role'] ?? '';

$role_labels = [
  'superadmin' => 'Super Admin',
  'registrar'  => 'Registrar',
  'finance'    => 'Finance',
];
$role_label = $role_labels[$role] ?? ucfirst($role);

// Unread notifications count
$unread_notifs = 0;
if (isset($_SESSION['user_id'])) {
  $r2 = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id={$_SESSION['user_id']} AND is_read=0");
  if ($r2) $unread_notifs = $r2->fetch_assoc()['c'];
}
?>
<aside id="sidebar">
  <div class="sidebar-logo-box">
    <img src="../images/COJ.png" alt="School Logo"/>
    <div class="logo-text">
      <div class="school-name">Catholic<br/>Progressive School</div>
      <div class="school-sub">Enrollment System</div>
    </div>
  </div>
  <div class="sidebar-toggle">
    <button class="toggle-btn" id="toggleBtn">&#9664;</button>
  </div>
  <nav class="sidebar-nav">

    <!-- All roles -->
    <div class="nav-item <?= $active_page==='dashboard'?'active':'' ?>" data-href="dashboard.php" data-label="Dashboard">
      <span class="nav-icon"><i class="bi bi-grid-fill"></i></span><span class="nav-text">Dashboard</span>
    </div>

    <!-- Registrar + Superadmin -->
    <?php if (in_array($role, ['superadmin','registrar'])): ?>
    <div class="nav-section-label"><span class="nav-text">Enrollment</span></div>
    <div class="nav-item <?= $active_page==='students'?'active':'' ?>" data-href="students.php" data-label="Students">
      <span class="nav-icon"><i class="bi bi-people-fill"></i></span><span class="nav-text">Students</span>
    </div>
    <div class="nav-item <?= $active_page==='enrollment'?'active':'' ?>" data-href="enrollment.php" data-label="Enrollment">
      <span class="nav-icon"><i class="bi bi-person-check-fill"></i></span><span class="nav-text">Enrollment</span>
    </div>
    <div class="nav-item <?= $active_page==='requirements'?'active':'' ?>" data-href="requirements.php" data-label="Requirements">
      <span class="nav-icon"><i class="bi bi-folder2-open"></i></span><span class="nav-text">Requirements</span>
    </div>
    <?php endif; ?>

    <!-- Finance + Superadmin -->
    <?php if (in_array($role, ['superadmin','finance'])): ?>
    <div class="nav-section-label"><span class="nav-text">Finance</span></div>
    <div class="nav-item <?= $active_page==='payments'?'active':'' ?>" data-href="payments.php" data-label="Payments">
      <span class="nav-icon"><i class="bi bi-cash-coin"></i></span><span class="nav-text">Payments</span>
    </div>
    <div class="nav-item <?= $active_page==='fees'?'active':'' ?>" data-href="fees.php" data-label="Fees">
      <span class="nav-icon"><i class="bi bi-receipt"></i></span><span class="nav-text">Fees</span>
    </div>
    <div class="nav-item <?= $active_page==='discounts'?'active':'' ?>" data-href="discounts.php" data-label="Discounts">
      <span class="nav-icon"><i class="bi bi-percent"></i></span><span class="nav-text">Discounts</span>
    </div>
    <?php endif; ?>

    <!-- All roles -->
    <div class="nav-section-label"><span class="nav-text">General</span></div>
    <div class="nav-item <?= $active_page==='reports'?'active':'' ?>" data-href="reports.php" data-label="Reports">
      <span class="nav-icon"><i class="bi bi-file-earmark-text-fill"></i></span><span class="nav-text">Reports</span>
    </div>
    <div class="nav-item <?= $active_page==='notifications'?'active':'' ?>" data-href="notifications.php" data-label="Notifications">
      <span class="nav-icon"><i class="bi bi-bell-fill"></i></span>
      <span class="nav-text">Notifications
        <span class="notif-live-badge" style="background:#e53e3e;color:#fff;border-radius:999px;font-size:10px;padding:1px 6px;margin-left:4px;<?= $unread_notifs > 0 ? '' : 'display:none;' ?>"><?= $unread_notifs ?></span>
      </span>
    </div>

    <!-- Superadmin only -->
    <?php if ($role === 'superadmin'): ?>
    <div class="nav-section-label"><span class="nav-text">Admin</span></div>
    <div class="nav-item <?= $active_page==='users'?'active':'' ?>" data-href="users.php" data-label="Users">
      <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span><span class="nav-text">Users</span>
    </div>
    <div class="nav-item <?= $active_page==='school_years'?'active':'' ?>" data-href="school_years.php" data-label="School Years">
      <span class="nav-icon"><i class="bi bi-calendar2-range-fill"></i></span><span class="nav-text">School Years</span>
    </div>
    <div class="nav-item <?= $active_page==='backup'?'active':'' ?>" data-href="backup.php" data-label="Backup">
      <span class="nav-icon"><i class="bi bi-database-fill-down"></i></span><span class="nav-text">Backup</span>
    </div>
    <?php endif; ?>

  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-user-avatar">
        <i class="bi bi-person-fill"></i>
      </div>
      <div class="sidebar-user-info btn-text">
        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
        <div class="sidebar-user-role"><?= $role_label ?></div>
      </div>
    </div>
    <a href="../logout.php" class="logout-btn">
      <span class="logout-icon"><i class="bi bi-box-arrow-right"></i></span>
      <span class="btn-text">Log out</span>
    </a>
  </div>
</aside>

<script>
(function () {
  function updateBadge(count) {
    document.querySelectorAll('.notif-live-badge').forEach(function (el) {
      el.textContent = count;
      el.style.display = count > 0 ? 'inline' : 'none';
    });
  }
  function poll() {
    fetch('../pages/notif_count.php')
      .then(function (r) { return r.json(); })
      .then(function (data) { updateBadge(data.count || 0); })
      .catch(function () {});
  }
  poll();
  setInterval(poll, 30000);
})();
</script>
