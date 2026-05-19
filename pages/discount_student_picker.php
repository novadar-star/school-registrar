<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
requireRole(['superadmin','registrar','finance']);

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

$search       = trim($_GET['search'] ?? '');
$filter_grade = trim($_GET['grade']  ?? '');
$searchParam  = "%$search%";

$where_parts = ["s.is_archived = 0"];
$bind_types  = '';
$bind_vals   = [];

if ($search) {
  $where_parts[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
  $bind_types   .= 'sss';
  $bind_vals[]   = $searchParam;
  $bind_vals[]   = $searchParam;
  $bind_vals[]   = $searchParam;
}
if ($filter_grade) {
  $where_parts[] = "g.name = ?";
  $bind_types   .= 's';
  $bind_vals[]   = $filter_grade;
}

$where_sql = implode(' AND ', $where_parts);

$stmt = $conn->prepare("
  SELECT s.id, s.first_name, s.last_name, s.lrn, g.name as grade,
         (SELECT COUNT(*) FROM discounts d WHERE d.student_id=s.id AND d.school_year_id=$sy_id) as discount_count
  FROM students s
  LEFT JOIN grade_levels g ON g.id = s.grade_level_id
  WHERE $where_sql
  ORDER BY s.last_name, s.first_name
  LIMIT 100
");
if ($bind_types) $stmt->bind_param($bind_types, ...$bind_vals);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$grade_levels = $conn->query("SELECT id, name FROM grade_levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$active_page  = 'discounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Select Student — Discounts</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/fees.css">
  <style>
    .picker-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:18px; }
    .picker-toolbar input, .picker-toolbar select { padding:8px 12px; border:1.5px solid var(--color-border); border-radius:8px; font-size:13px; font-family:var(--font); background:#fff; }
    .picker-toolbar input { min-width:240px; }
    .btn-pick { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; background:var(--color-primary); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
    .btn-pick:hover { opacity:.88; }
    .btn-back { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:var(--color-bg); color:var(--color-text); border:1.5px solid var(--color-border); border-radius:8px; font-size:13px; font-weight:500; text-decoration:none; }
    .discount-badge { font-size:11px; background:#dcfce7; color:#166534; padding:2px 8px; border-radius:999px; font-weight:600; }
  </style>
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Select Student for Discount</div>
      <div class="page-sub">Choose a student to assign a discount or scholarship</div>
    </div>
    <div class="topbar-actions">
      <a href="discounts.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Discounts</a>
    </div>
  </div>

  <div id="page-container">

    <!-- Search & Filter -->
    <form method="GET" action="discount_student_picker.php" class="picker-toolbar">
      <input type="search" name="search" placeholder="Search name or LRN…"
             value="<?= htmlspecialchars($search) ?>"/>
      <select name="grade" onchange="this.form.submit()">
        <option value="">All Grades</option>
        <?php foreach ($grade_levels as $gl): ?>
        <option value="<?= htmlspecialchars($gl['name']) ?>" <?= $filter_grade === $gl['name'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($gl['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-pick"><i class="bi bi-search"></i> Search</button>
      <?php if ($search || $filter_grade): ?>
        <a href="discount_student_picker.php" style="padding:8px 14px;background:var(--color-bg);color:var(--color-muted);border:1.5px solid var(--color-border);border-radius:8px;font-size:13px;text-decoration:none;">
          <i class="bi bi-x-circle"></i> Clear
        </a>
      <?php endif; ?>
    </form>

    <div class="fees-table-card">
      <table class="fees-table">
        <thead>
          <tr>
            <th>Student Name</th>
            <th>LRN</th>
            <th>Grade</th>
            <th>Existing Discounts</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($students)): ?>
          <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--color-muted);">No students found.</td></tr>
          <?php endif; ?>
          <?php foreach ($students as $s): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
            <td style="font-size:12px;color:var(--color-muted);"><?= htmlspecialchars($s['lrn']) ?></td>
            <td><?= htmlspecialchars($s['grade'] ?? '—') ?></td>
            <td>
              <?php if ($s['discount_count'] > 0): ?>
                <span class="discount-badge"><?= $s['discount_count'] ?> discount<?= $s['discount_count'] > 1 ? 's' : '' ?></span>
              <?php else: ?>
                <span style="font-size:12px;color:var(--color-muted);">None</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="discounts.php?student_id=<?= $s['id'] ?>&add=1" class="btn-pick">
                <i class="bi bi-plus-circle"></i> Add Discount
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script src="../js/nav.js"></script>
</body>
</html>
