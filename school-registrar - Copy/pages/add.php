<?php
session_start();
if (!isset($_SESSION['name'])) {
  header('Location: ../index.php'); exit();
}

include('../mysql/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name     = trim($_POST['first_name']      ?? '');
  $middle_name    = trim($_POST['middle_name']      ?? '');
  $last_name      = trim($_POST['last_name']        ?? '');
  $lrn            = trim($_POST['lrn']              ?? '');
  $grade_level_id = intval($_POST['grade_level_id'] ?? 0);
  $section_id     = intval($_POST['section_id']     ?? 0);
  $city           = trim($_POST['city']             ?? '');
  $contact_number = trim($_POST['contact_number']   ?? '');
  $student_type   = trim($_POST['status']           ?? '');
  $is_sped        = isset($_POST['is_sped']) ? 1 : 0;
  $sped_notes     = trim($_POST['sped_notes'] ?? '');

  // Store form data in session so it survives the redirect
  $_SESSION['add_form'] = [
    'first_name'     => $first_name,
    'middle_name'    => $middle_name,
    'last_name'      => $last_name,
    'lrn'            => $lrn,
    'grade_level_id' => $grade_level_id,
    'section_id'     => $section_id,
    'city'           => $city,
    'contact_number' => $contact_number,
    'status'         => $student_type,
  ];

  $name_pattern = '/^[a-zA-ZÀ-ÿ\s\-\.]+$/u';

  // Validation
  if (empty($first_name) || empty($last_name) || empty($lrn) ||
      empty($grade_level_id) || empty($section_id) || empty($student_type)) {
    header("Location: students.php?error=" . urlencode("All required fields must be filled.") . "&open_add=1");
    exit;
  }
  if (!preg_match($name_pattern, $first_name)) {
    header("Location: students.php?error=" . urlencode("First name must contain letters only.") . "&open_add=1");
    exit;
  }
  if (!preg_match($name_pattern, $last_name)) {
    header("Location: students.php?error=" . urlencode("Last name must contain letters only.") . "&open_add=1");
    exit;
  }
  if (!empty($middle_name) && !preg_match($name_pattern, $middle_name)) {
    header("Location: students.php?error=" . urlencode("Middle name must contain letters only.") . "&open_add=1");
    exit;
  }
  if (!preg_match('/^\d{12}$/', $lrn)) {
    header("Location: students.php?error=" . urlencode("LRN must be exactly 12 digits.") . "&open_add=1");
    exit;
  }
  if (!empty($contact_number) && !preg_match('/^(09|\+639)\d{9}$/', $contact_number)) {
    header("Location: students.php?error=" . urlencode("Contact number must be a valid PH number (e.g. 09XXXXXXXXX).") . "&open_add=1");
    exit;
  }

  // Get active school year
  $sy = $conn->query("SELECT id FROM school_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
  $school_year_id = $sy ? $sy['id'] : null;

  // Photo upload
  $photo = '';
  if (!empty($_FILES['photo']['tmp_name'])) {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($_FILES['photo']['type'], $allowed)) {
      header("Location: students.php?error=" . urlencode("Photo must be JPG, PNG, WEBP, or GIF.") . "&open_add=1");
      exit;
    }
    $photo = uniqid() . '_' . basename($_FILES['photo']['name']);
    move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);
  }

  // Duplicate LRN check
  $chk = $conn->prepare("SELECT id FROM students WHERE lrn = ?");
  $chk->bind_param("s", $lrn);
  $chk->execute();
  if ($chk->get_result()->num_rows > 0) {
    header("Location: students.php?error=" . urlencode("LRN already exists in the system.") . "&open_add=1");
    exit;
  }
  $chk->close();

  $stmt = $conn->prepare(
    "INSERT INTO students
       (photo, first_name, middle_name, last_name, lrn,
        grade_level_id, section_id, school_year_id, city, contact_number, student_type, is_sped, sped_notes)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
  );
  $stmt->bind_param(
    "sssssiiisssiss",
    $photo, $first_name, $middle_name, $last_name, $lrn,
    $grade_level_id, $section_id, $school_year_id, $city, $contact_number, $student_type, $is_sped, $sped_notes
  );

  if ($stmt->execute()) {
    // Clear flash data on success
    unset($_SESSION['add_form']);
    header("Location: students.php?success=" . urlencode("Student added successfully."));
  } else {
    header("Location: students.php?error=" . urlencode($stmt->error) . "&open_add=1");
  }
  $stmt->close();
  exit;
}

header("Location: students.php");
exit;
?>
