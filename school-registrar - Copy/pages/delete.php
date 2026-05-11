<?php
session_start();
if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}

include('../mysql/db.php');

if (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("UPDATE students SET is_archived = 1 WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: students.php?success=Student archived successfully");
  exit();
}

header("Location: students.php");
exit();
?>
