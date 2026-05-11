<?php
$active_portal = 'notifications';
require_once 'includes/auth.php';

// Mark all as read
$conn->query("UPDATE parent_notifications SET is_read=1 WHERE parent_id=$parent_id");

$notifs = $conn->query("
  SELECT pn.*, s.first_name, s.last_name
  FROM parent_notifications pn
  LEFT JOIN students s ON s.id = pn.student_id
  WHERE pn.parent_id = $parent_id
  ORDER BY pn.created_at DESC
  LIMIT 100
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications — Parent Portal</title>
  <link rel="icon" type="image/x-icon" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal.css">
  <style>
    .notif-list { display:flex; flex-direction:column; gap:10px; }
    .notif-item { background:#fff; border:1px solid var(--border); border-radius:10px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start; }
    .notif-icon { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
    .icon-info    { background:#eef0f8; color:var(--primary); }
    .icon-success { background:#dcfce7; color:#16a34a; }
    .icon-warning { background:#fef9c3; color:#d97706; }
    .icon-danger  { background:#fdeaea; color:#dc2626; }
    .notif-title  { font-size:14px; font-weight:600; }
    .notif-body   { font-size:13px; color:var(--muted); margin-top:3px; }
    .notif-time   { font-size:11px; color:var(--muted); margin-top:5px; }
    .notif-child  { font-size:11px; color:var(--primary); font-weight:600; margin-top:2px; }
  </style>
</head>
<body>
<?php include('includes/nav.php'); ?>
<div class="portal-container">
  <div class="portal-page-header">
    <h2>Notifications</h2>
    <p><?= count($notifs) ?> total notifications</p>
  </div>
  <div class="notif-list">
    <?php if (empty($notifs)): ?>
      <div style="text-align:center;padding:60px;color:var(--muted);">
        <i class="bi bi-bell-slash" style="font-size:40px;display:block;margin-bottom:12px;"></i>
        No notifications yet.
      </div>
    <?php endif; ?>
    <?php foreach ($notifs as $n): ?>
    <div class="notif-item">
      <div class="notif-icon icon-<?= htmlspecialchars($n['type']) ?>">
        <?php $icons=['info'=>'bi-info-circle-fill','success'=>'bi-check-circle-fill','warning'=>'bi-exclamation-triangle-fill','danger'=>'bi-x-circle-fill'];
        echo '<i class="bi '.($icons[$n['type']]??'bi-bell-fill').'"></i>'; ?>
      </div>
      <div style="flex:1;">
        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
        <?php if ($n['body']): ?><div class="notif-body"><?= htmlspecialchars($n['body']) ?></div><?php endif; ?>
        <?php if (!empty($n['first_name'])): ?><div class="notif-child"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($n['first_name'].' '.$n['last_name']) ?></div><?php endif; ?>
        <div class="notif-time"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
