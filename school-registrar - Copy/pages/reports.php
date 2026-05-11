<?php
session_start();
include('../mysql/db.php');
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;
$filter_sy = $_GET['sy'] ?? $sy_id;
$sy_list   = $conn->query("SELECT * FROM school_years ORDER BY label DESC")->fetch_all(MYSQLI_ASSOC);

// Enrollment by grade
$enrollment = $conn->query("
  SELECT g.name as grade,
    COUNT(s.id) as total,
    SUM(s.student_type='new') as new_s,
    SUM(s.student_type='old') as old_s,
    SUM(e.status='enrolled') as enrolled,
    SUM(e.status='pending')  as pending,
    SUM(e.status='dropped')  as dropped
  FROM grade_levels g
  LEFT JOIN students s ON s.grade_level_id = g.id AND s.is_archived = 0
  LEFT JOIN enrollments e ON e.student_id = s.id AND e.school_year_id = $filter_sy
  GROUP BY g.id ORDER BY g.id
");
$total_students = $conn->query("SELECT COUNT(*) as c FROM students WHERE is_archived=0")->fetch_assoc()['c'];

// Payment summary by grade
$payment_report = $conn->query("
  SELECT g.name as grade,
    COUNT(DISTINCT s.id) as total_students,
    COALESCE(SUM(p.amount_paid),0) as total_paid,
    COALESCE(SUM(p.balance),0) as total_balance,
    SUM(p.status='paid') as fully_paid,
    SUM(p.status='partial') as partial,
    SUM(p.status='unpaid') as unpaid
  FROM grade_levels g
  LEFT JOIN students s ON s.grade_level_id = g.id AND s.is_archived = 0
  LEFT JOIN payments p ON p.student_id = s.id
  GROUP BY g.id ORDER BY g.id
")->fetch_all(MYSQLI_ASSOC);

$total_collection = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as c FROM payments")->fetch_assoc()['c'];
$active_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/reports.css">
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Reports</div>
      <div class="page-sub">Enrollment and payment summaries</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-export-report" onclick="exportTable('enrollment-table','enrollment_report')"><i class="bi bi-download"></i> Export Enrollment</button>
      <button class="btn-export-report" onclick="exportTable('payment-table','payment_report')"><i class="bi bi-download"></i> Export Payments</button>
    </div>
  </div>

  <div id="page-container">

    <!-- Enrollment Report -->
    <div class="report-section">
      <div class="report-section-header">
        <span><i class="bi bi-people-fill"></i> Student Enrollment by Grade</span>
        <div style="display:flex;align-items:center;gap:10px;">
          <span class="report-total">Total: <?= $total_students ?> students</span>
          <form method="GET" action="reports.php" style="display:flex;align-items:center;gap:6px;">
            <label style="font-size:12px;">SY:</label>
            <select name="sy" onchange="this.form.submit()" style="border:none;border-radius:4px;padding:3px 8px;font-size:12px;cursor:pointer;">
              <?php foreach ($sy_list as $sy): ?>
                <option value="<?= $sy['id'] ?>" <?= $filter_sy == $sy['id'] ? 'selected':'' ?>>SY <?= htmlspecialchars($sy['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>
      <table class="report-table" id="enrollment-table">
        <thead>
          <tr><th>Grade</th><th>New</th><th>Old</th><th>Total</th><th>Enrolled</th><th>Pending</th><th>Dropped</th></tr>
        </thead>
        <tbody>
          <?php while ($e = $enrollment->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($e['grade']) ?></td>
            <td><?= $e['new_s'] ?></td>
            <td><?= $e['old_s'] ?></td>
            <td><strong><?= $e['total'] ?></strong></td>
            <td style="color:var(--color-success);font-weight:600"><?= $e['enrolled'] ?></td>
            <td style="color:var(--color-warning);font-weight:600"><?= $e['pending'] ?></td>
            <td style="color:var(--color-danger);font-weight:600"><?= $e['dropped'] ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
        <tfoot><tr><td><strong>Total</strong></td><td colspan="5"></td><td><strong><?= $total_students ?></strong></td></tr></tfoot>
      </table>
    </div>

    <!-- Payment Report -->
    <div class="report-section" style="margin-top:24px;">
      <div class="report-section-header">
        <span><i class="bi bi-cash-coin"></i> Payment Summary by Grade</span>
        <span class="report-total">Total Collection: ₱<?= number_format($total_collection, 2) ?></span>
      </div>
      <table class="report-table" id="payment-table">
        <thead>
          <tr><th>Grade</th><th>Students</th><th>Total Paid</th><th>Balance</th><th>Fully Paid</th><th>Partial</th><th>Unpaid</th></tr>
        </thead>
        <tbody>
          <?php foreach ($payment_report as $pr): ?>
          <tr>
            <td><?= htmlspecialchars($pr['grade']) ?></td>
            <td><?= $pr['total_students'] ?></td>
            <td style="color:var(--color-success);font-weight:600">₱<?= number_format($pr['total_paid'],2) ?></td>
            <td style="color:var(--color-danger);font-weight:600">₱<?= number_format($pr['total_balance'],2) ?></td>
            <td><?= $pr['fully_paid'] ?></td>
            <td><?= $pr['partial'] ?></td>
            <td><?= $pr['unpaid'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script src="../js/nav.js"></script>
<script>
  function exportTable(tableId, filename) {
    const tbl = document.getElementById(tableId);
    if (!tbl) return;
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(tbl);
    XLSX.utils.book_append_sheet(wb, ws, filename);
    XLSX.writeFile(wb, filename + '.xlsx');
  }
</script>
</body>
</html>
