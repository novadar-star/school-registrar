<?php
// Lightweight endpoint — returns unread notification count for the logged-in admin
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['count' => 0]);
  exit();
}

include('../mysql/db.php');
$uid = intval($_SESSION['user_id']);
$r   = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
$count = $r ? intval($r->fetch_assoc()['c']) : 0;
echo json_encode(['count' => $count]);
