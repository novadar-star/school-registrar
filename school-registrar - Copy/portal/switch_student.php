<?php
session_name('parent_session');
session_start();
if (!isset($_SESSION['parent_id'])) { header('Location: login.php'); exit(); }
require_once '../mysql/db.php';

$parent_id  = $_SESSION['parent_id'];
$student_id = intval($_GET['id'] ?? 0);

// Verify this student belongs to this parent
$check = $conn->query("SELECT student_id FROM parent_student_links WHERE parent_id=$parent_id AND student_id=$student_id LIMIT 1")->fetch_assoc();
if ($check) {
  $_SESSION['student_id'] = $student_id;
}
header('Location: dashboard.php'); exit();
