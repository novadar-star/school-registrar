<?php
// Lightweight endpoint — returns unread notification count for the logged-in parent
session_name('parent_session');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
  echo json_encode(['count' => 0]);
  exit();
}

include('../mysql/db.php');
$pid   = intval($_SESSION['parent_id']);
$r     = $conn->query("SELECT COUNT(*) as c FROM parent_notifications WHERE parent_id=$pid AND is_read=0");
$count = $r ? intval($r->fetch_assoc()['c']) : 0;
echo json_encode(['count' => $count]);
