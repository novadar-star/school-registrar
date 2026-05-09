<?php
session_start();
include('../mysql/db.php');
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

$uid = $_SESSION['user_id'] ?? 0;

// Mark all as read
if (isset($_GET['mark_all'])) {
  $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
  header("Location: notifications.php"); exit();
}

// Mark single as read
if (isset($_GET['read'])) {
  $nid = intval($_GET['read']);
  $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid AND user_id=$uid");
  $notif = $conn->query("SELECT link FROM notifications WHERE id=$nid")->fetch_assoc();
  if (!empty($notif['link'])) { header("Location: " . $notif['link']); exit(); }
  header("Location: notifications.php"); exit();
}

$notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$unread_count = count(array_filter($notifs, fn($n) => !$n['is_read']));
$active_page = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .notif-list { display:flex; flex-direction:column; gap:8px; max-width:760px; }
    .notif-item { background:#fff; border:1px solid var(--color-border); border-radius:10px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start; transition:.2s; }
    .notif-item.unread { border-left:4px solid var(--color-primary); background:#fafbff; }
    .notif-icon { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
    .icon-info    { background:#eef0f8; color:var(--color-primary); }
    .icon-success { background:#dcfce7; color:#16a34a; }
    .icon-warning { background:#fef9c3; color:#d97706; }
    .icon-danger  { background:#fdeaea; color:#dc2626; }
    .notif-title  { font-size:14px; font-weight:600; }
    .notif-body   { font-size:13px; color:var(--color-muted); margin-top:3px; }
    .notif-time   { font-size:11px; color:var(--color-muted); margin-top:5px; }
    .notif-unread-dot { width:8px; height:8px; border-radius:50%; background:var(--color-primary); flex-shrink:0; margin-top:6px; }
  </style>
</head>
<body>
<?php include('includes/sidebar.php'); ?>
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Notifications</div>
      <div class="page-sub"><?= $unread_count ?> unread</div>
    </div>
    <?php if ($unread_count > 0): ?>
    <div class="topbar-actions">
      <a href="notifications.php?mark_all=1" class="btn-topbar" style="font-size:13px;">Mark all as read</a>
    </div>
    <?php endif; ?>
  </div>
  <div id="page-container">
    <div class="notif-list">
      <?php if (empty($notifs)): ?>
        <div style="text-align:center;padding:60px;color:var(--color-muted);">
          <i class="bi bi-bell-slash" style="font-size:40px;display:block;margin-bottom:12px;"></i>
          No notifications yet.
        </div>
      <?php endif; ?>
      <?php foreach ($notifs as $n): ?>
      <a href="notifications.php?read=<?= $n['id'] ?>" style="text-decoration:none;color:inherit;">
        <div class="notif-item <?= !$n['is_read']?'unread':'' ?>">
          <div class="notif-icon icon-<?= htmlspecialchars($n['type']) ?>">
            <?php
            $icons = ['info'=>'bi-info-circle-fill','success'=>'bi-check-circle-fill','warning'=>'bi-exclamation-triangle-fill','danger'=>'bi-x-circle-fill'];
            echo '<i class="bi ' . ($icons[$n['type']] ?? 'bi-bell-fill') . '"></i>';
            ?>
          </div>
          <div style="flex:1;">
            <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
            <?php if ($n['body']): ?><div class="notif-body"><?= htmlspecialchars($n['body']) ?></div><?php endif; ?>
            <div class="notif-time"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
          </div>
          <?php if (!$n['is_read']): ?><div class="notif-unread-dot"></div><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script src="../js/nav.js"></script>
</body>
</html>
