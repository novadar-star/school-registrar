<?php
session_start();
if (!isset($_SESSION['name'])) {
  header('Location: ../index.php'); exit();
}

include('../mysql/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id             = intval($_POST['id']              ?? 0);
  $first_name     = trim($_POST['first_name']        ?? '');
  $middle_name    = trim($_POST['middle_name']        ?? '');
  $last_name      = trim($_POST['last_name']          ?? '');
  $lrn            = trim($_POST['lrn']                ?? '');
  $grade_level_id = intval($_POST['grade_level_id']   ?? 0);
  $section_id     = intval($_POST['section_id']       ?? 0);
  $school_year_id = intval($_POST['school_year_id']   ?? 0) ?: null;
  $city           = trim($_POST['city']               ?? '');
  $contact_number = trim($_POST['contact_number']     ?? '');
  $student_type   = trim($_POST['status']             ?? '');
  $existing_photo = trim($_POST['existing_photo']     ?? '');
  $is_sped        = isset($_POST['is_sped']) ? 1 : 0;
  $sped_notes     = trim($_POST['sped_notes'] ?? '');

  // ── Server-side validation ────────────────────────────────
  $name_pattern = '/^[a-zA-ZÀ-ÿ\s\-\.]+$/u';

  if ($id <= 0) {
    header("Location: students.php?error=" . urlencode("Invalid student ID.")); exit;
  }
  if (empty($first_name) || empty($last_name) || empty($lrn) ||
      empty($grade_level_id) || empty($section_id) || empty($student_type)) {
    header("Location: students.php?error=" . urlencode("All required fields must be filled.") . "&edit_id=$id");
    exit;
  }
  if (!preg_match($name_pattern, $first_name)) {
    header("Location: students.php?error=" . urlencode("First name must contain letters only.") . "&edit_id=$id");
    exit;
  }
  if (!preg_match($name_pattern, $last_name)) {
    header("Location: students.php?error=" . urlencode("Last name must contain letters only.") . "&edit_id=$id");
    exit;
  }
  if (!empty($middle_name) && !preg_match($name_pattern, $middle_name)) {
    header("Location: students.php?error=" . urlencode("Middle name must contain letters only.") . "&edit_id=$id");
    exit;
  }
  if (!preg_match('/^\d{12}$/', $lrn)) {
    header("Location: students.php?error=" . urlencode("LRN must be exactly 12 digits.") . "&edit_id=$id");
    exit;
  }
  if (!empty($contact_number) && !preg_match('/^(09|\+639)\d{9}$/', $contact_number)) {
    header("Location: students.php?error=" . urlencode("Contact number must be a valid PH number.") . "&edit_id=$id");
    exit;
  }

  // Photo upload
  if (!empty($_FILES['photo']['tmp_name'])) {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($_FILES['photo']['type'], $allowed)) {
      header("Location: students.php?error=" . urlencode("Photo must be JPG, PNG, WEBP, or GIF.") . "&edit_id=$id");
      exit;
    }
    $photo = uniqid() . '_' . basename($_FILES['photo']['name']);
    move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);
  } else {
    $photo = $existing_photo;
  }

  // Duplicate LRN check (exclude current student)
  $chk = $conn->prepare("SELECT id FROM students WHERE lrn = ? AND id != ?");
  $chk->bind_param("si", $lrn, $id);
  $chk->execute();
  if ($chk->get_result()->num_rows > 0) {
    header("Location: students.php?error=" . urlencode("LRN already exists for another student.") . "&edit_id=$id");
    exit;
  }
  $chk->close();

  $stmt = $conn->prepare(
    "UPDATE students SET
       photo=?, first_name=?, middle_name=?, last_name=?, lrn=?,
       grade_level_id=?, section_id=?, school_year_id=?,
       city=?, contact_number=?, student_type=?, is_sped=?, sped_notes=?
     WHERE id=?"
  );
  $stmt->bind_param(
    "sssssiiisssisi",
    $photo, $first_name, $middle_name, $last_name, $lrn,
    $grade_level_id, $section_id, $school_year_id,
    $city, $contact_number, $student_type, $is_sped, $sped_notes, $id
  );

  $stmt->execute()
    ? header("Location: students.php?success=" . urlencode("Student updated successfully."))
    : header("Location: students.php?error="   . urlencode("Update failed: " . $stmt->error) . "&edit_id=$id");
  $stmt->close();
  exit;
}

header("Location: students.php");
exit;
?>
