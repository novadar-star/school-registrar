<?php
session_name('parent_session');
session_start();
if (!isset($_SESSION['parent_id'])) {
  header('Location: ../portal/login.php'); exit();
}
require_once '../mysql/db.php';

$parent_id   = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'];

// Fetch linked students using parameterized query — no direct table reference in session
if (empty($_SESSION['student_id'])) {
  $lnk_stmt = $conn->prepare("SELECT student_id FROM parent_student_links WHERE parent_id = ? LIMIT 1");
  $lnk_stmt->bind_param("i", $parent_id);
  $lnk_stmt->execute();
  $first = $lnk_stmt->get_result()->fetch_assoc();
  if ($first) {
    $_SESSION['student_id'] = $first['student_id'];
  } else {
    $_SESSION['student_id'] = 0;
  }
}
$student_id = intval($_SESSION['student_id']);

// Security: re-validate that the session student_id actually belongs to this parent
if ($student_id > 0) {
  $own_check = $conn->prepare("SELECT 1 FROM parent_student_links WHERE parent_id = ? AND student_id = ? LIMIT 1");
  $own_check->bind_param("ii", $parent_id, $student_id);
  $own_check->execute();
  if (!$own_check->get_result()->fetch_assoc()) {
    // Student doesn't belong to this parent — reset to first valid student
    $_SESSION['student_id'] = 0;
    $student_id = 0;
    $lnk_stmt2 = $conn->prepare("SELECT student_id FROM parent_student_links WHERE parent_id = ? LIMIT 1");
    $lnk_stmt2->bind_param("i", $parent_id);
    $lnk_stmt2->execute();
    $first2 = $lnk_stmt2->get_result()->fetch_assoc();
    if ($first2) {
      $_SESSION['student_id'] = $first2['student_id'];
      $student_id = intval($first2['student_id']);
    }
  }
}
