<?php
include('../mysql/db.php');
session_start();
require_once '../mysql/helpers.php';

if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}
requireRole(['superadmin','registrar']);

$search       = $_GET['search'] ?? '';
$filter_grade  = $_GET['grade']  ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_enrollment_status = $_GET['enrollment_status'] ?? '';
$searchParam   = "%$search%";

// Build WHERE dynamically — same logic as students.php
$where_parts = [
  "(s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)",
  "s.is_archived = 0"
];
$bind_types = "ssss";
$bind_vals  = [$searchParam, $searchParam, $searchParam, $searchParam];

if ($filter_grade) {
  $where_parts[] = "g.name = ?";
  $bind_types .= "s"; $bind_vals[] = $filter_grade;
}
if ($filter_status) {
  $where_parts[] = "s.student_type = ?";
  $bind_types .= "s"; $bind_vals[] = $filter_status;
}

$join_enrollment = '';
if ($filter_enrollment_status) {
  $active_sy = $conn->query("SELECT id FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
  $sy_id = $active_sy['id'] ?? 0;
  $join_enrollment = "JOIN enrollments e ON e.student_id = s.id AND e.school_year_id = $sy_id";
  $where_parts[] = "e.status = ?";
  $bind_types .= "s"; $bind_vals[] = $filter_enrollment_status;
}

$where_sql = implode(" AND ", $where_parts);

$stmt = $conn->prepare("
  SELECT s.last_name, s.first_name, s.middle_name, s.lrn,
         g.name as grade_name, sec.name as section_name,
         sy.label as school_year, s.city, s.contact_number, s.student_type
  FROM students s
  LEFT JOIN grade_levels g  ON s.grade_level_id = g.id
  LEFT JOIN sections sec    ON s.section_id = sec.id
  LEFT JOIN school_years sy ON s.school_year_id = sy.id
  $join_enrollment
  WHERE $where_sql
  ORDER BY s.last_name ASC
");
$stmt->bind_param($bind_types, ...$bind_vals);
$stmt->execute();
$result = $stmt->get_result();

$rows   = [];
$rows[] = ['Last Name','First Name','Middle Name','LRN','Grade','Section','School Year','City','Contact','Status'];

while ($row = $result->fetch_assoc()) {
  $rows[] = [
    $row['last_name'],
    $row['first_name'],
    $row['middle_name'] ?? '',
    $row['lrn'],
    $row['grade_name']   ?? '',
    $row['section_name'] ?? '',
    $row['school_year']  ?? '',
    $row['city']         ?? '',
    $row['contact_number'] ?? '',
    $row['student_type'],
  ];
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_export.csv"');

$output = fopen('php://output', 'w');
foreach ($rows as $row) {
  fputcsv($output, $row);
}
fclose($output);
exit();
?>
