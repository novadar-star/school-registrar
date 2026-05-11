<?php
session_name('parent_session');
session_start();
if (!isset($_SESSION['parent_id'])) {
  header('Location: ../portal/login.php'); exit();
}
require_once '../mysql/db.php';

$parent_id   = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'];

// If student_id not set, pick first linked student
if (empty($_SESSION['student_id'])) {
  $first = $conn->query("SELECT student_id FROM parent_student_links WHERE parent_id=$parent_id LIMIT 1")->fetch_assoc();
  if ($first) {
    $_SESSION['student_id'] = $first['student_id'];
  } else {
    // Fallback: old single-student link
    $fallback = $conn->query("SELECT id FROM students WHERE id=(SELECT student_id FROM parent_accounts WHERE id=$parent_id LIMIT 1) LIMIT 1")->fetch_assoc();
    $_SESSION['student_id'] = $fallback['id'] ?? 0;
  }
}
$student_id = $_SESSION['student_id'];
