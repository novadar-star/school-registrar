<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
if ($_SESSION['role'] !== 'superadmin') { header('Location: dashboard.php'); exit(); }

$uid   = $_SESSION['user_id'] ?? 0;
$uname = $conn->real_escape_string($_SESSION['name'] ?? '');

// ── Helper: generate SQL dump in pure PHP ─────────────────────
function generate_sql_dump(mysqli $conn, string $dbname): string {
  $out  = "-- COJ School Registrar — Full Database Backup\n";
  $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
  $out .= "-- Database: $dbname\n\n";
  $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
  $out .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
  $out .= "SET NAMES utf8mb4;\n\n";

  $tables = $conn->query("SHOW TABLES");
  while ($trow = $tables->fetch_row()) {
    $table = $trow[0];

    // DROP + CREATE
    $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
    $out .= "-- Table: $table\n";
    $out .= "DROP TABLE IF EXISTS `$table`;\n";
    $out .= $create[1] . ";\n\n";

    // Data
    $rows = $conn->query("SELECT * FROM `$table`");
    if (!$rows || $rows->num_rows === 0) { $out .= "\n"; continue; }

    $cols = [];
    $fi   = $rows->fetch_fields();
    foreach ($fi as $f) $cols[] = "`{$f->name}`";
    $col_list = implode(', ', $cols);

    $out .= "INSERT INTO `$table` ($col_list) VALUES\n";
    $values = [];
    while ($r = $rows->fetch_row()) {
      $escaped = array_map(function($v) use ($conn) {
        if ($v === null) return 'NULL';
        return "'" . $conn->real_escape_string($v) . "'";
      }, $r);
      $values[] = '(' . implode(', ', $escaped) . ')';
    }
    $out .= implode(",\n", $values) . ";\n\n";
  }

  $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
  return $out;
}

// ── SQL Dump download ─────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'sql') {
  $dbname   = getenv('MYSQLDATABASE') ?: 'school_registrar';
  $filename = 'coj_backup_' . date('Y-m-d_His') . '.sql';

  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');

  echo generate_sql_dump($conn, $dbname);

  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, details) VALUES ($uid, '$uname', 'export_sql', 'database', 'Full SQL backup downloaded')");
  exit();
}

// ── CSV: Students export ──────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'students') {
  $filename = 'coj_students_' . date('Y-m-d') . '.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');

  $out = fopen('php://output', 'w');
  fputs($out, "\xEF\xBB\xBF");
  fputcsv($out, ['LRN','Last Name','First Name','Middle Name','Grade','School Year',
                 'Student Type','Sex','Birthday','Religion','Province','City/Municipality',
                 'Barangay','Contact Number','Enrollment Status','Reference #','Is SPED']);

  $rows = $conn->query("
    SELECT s.lrn, s.last_name, s.first_name, s.middle_name,
           g.name as grade, sy.label as school_year,
           s.student_type, s.sex, s.birthday, s.religion,
           s.province, s.city_municipality, s.barangay, s.contact_number,
           e.status as enroll_status, e.ref_number, s.is_sped
    FROM students s
    LEFT JOIN grade_levels g ON g.id = s.grade_level_id
    LEFT JOIN school_years sy ON sy.id = s.school_year_id
    LEFT JOIN enrollments e ON e.student_id = s.id AND e.school_year_id = sy.id
    WHERE s.is_archived = 0
    ORDER BY s.last_name, s.first_name
  ");
  while ($r = $rows->fetch_assoc()) {
    fputcsv($out, [
      $r['lrn'], $r['last_name'], $r['first_name'], $r['middle_name'],
      $r['grade'], $r['school_year'], $r['student_type'], $r['sex'],
      $r['birthday'], $r['religion'], $r['province'], $r['city_municipality'],
      $r['barangay'], $r['contact_number'], $r['enroll_status'] ?? 'N/A',
      $r['ref_number'] ?? 'N/A', $r['is_sped'] ? 'Yes' : 'No'
    ]);
  }
  fclose($out);
  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, details) VALUES ($uid, '$uname', 'export_csv', 'students', 'Students CSV exported')");
  exit();
}

// ── CSV: Payments export ──────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'payments') {
  $filename = 'coj_payments_' . date('Y-m-d') . '.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');

  $out = fopen('php://output', 'w');
  fputs($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Student LRN','Last Name','First Name','Grade','Fee Name','Fee Type',
                 'Amount','Amount Paid','Balance','Status','OR Number','Payment Method','Date Paid']);

  $rows = $conn->query("
    SELECT s.lrn, s.last_name, s.first_name, g.name as grade,
           f.name as fee_name, f.fee_type,
           f.amount, p.amount_paid, p.balance, p.status,
           p.or_number, p.payment_method, p.paid_at
    FROM payments p
    JOIN students s ON s.id = p.student_id
    JOIN fees f ON f.id = p.fee_id
    LEFT JOIN grade_levels g ON g.id = s.grade_level_id
    WHERE s.is_archived = 0
    ORDER BY s.last_name, s.first_name, f.name
  ");
  while ($r = $rows->fetch_assoc()) {
    fputcsv($out, [
      $r['lrn'], $r['last_name'], $r['first_name'], $r['grade'],
      $r['fee_name'], $r['fee_type'], $r['amount'], $r['amount_paid'],
      $r['balance'], $r['status'], $r['or_number'] ?? '',
      $r['payment_method'] ?? '', $r['paid_at'] ?? ''
    ]);
  }
  fclose($out);
  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, details) VALUES ($uid, '$uname', 'export_csv', 'payments', 'Payments CSV exported')");
  exit();
}

// ── SQL Restore ───────────────────────────────────────────────
$restore_success = '';
$restore_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
  if (empty($_FILES['sql_file']['tmp_name'])) {
    $restore_error = "Please select a .sql backup file to restore.";
  } else {
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($_FILES['sql_file']['tmp_name']);
    $allowed_mimes = ['text/plain','application/sql','application/x-sql','application/octet-stream'];
    $ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));

    if ($ext !== 'sql' && !in_array($real_mime, $allowed_mimes)) {
      $restore_error = "Only .sql files are accepted.";
    } elseif ($_FILES['sql_file']['size'] > 50 * 1024 * 1024) {
      $restore_error = "File must be under 50MB.";
    } else {
      $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
      if (empty($sql_content)) {
        $restore_error = "The uploaded file is empty.";
      } else {
        // Execute SQL statements one by one
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->multi_query($sql_content);

        // Drain all result sets
        $errors = [];
        do {
          if ($conn->errno) $errors[] = $conn->error;
          if ($conn->more_results()) $conn->next_result();
          else break;
        } while (true);

        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        if (empty($errors)) {
          $restore_success = "Database restored successfully from: " . htmlspecialchars($_FILES['sql_file']['name']);
          $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, details) VALUES ($uid, '$uname', 'restore_sql', 'database', 'Database restored from SQL backup')");
        } else {
          $restore_error = "Restore completed with errors: " . implode('; ', array_slice($errors, 0, 3));
        }
      }
    }
  }
}

// ── Backup history (last 10 audit entries) ────────────────────
$backup_log = $conn->query("
  SELECT * FROM audit_log
  WHERE action IN ('export_sql','export_csv','restore_sql')
  ORDER BY created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$active_page = 'backup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Data Recovery — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .recovery-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; max-width:900px; }
    @media(max-width:700px){ .recovery-grid { grid-template-columns:1fr; } }
    .recovery-card { background:#fff; border:1px solid var(--color-border); border-radius:12px; padding:28px; }
    .recovery-card-icon { font-size:32px; margin-bottom:12px; }
    .recovery-card h3 { font-size:15px; font-weight:700; margin-bottom:6px; }
    .recovery-card p  { font-size:13px; color:var(--color-muted); line-height:1.6; margin-bottom:16px; }
    .btn-dl { display:inline-flex; align-items:center; gap:8px; padding:10px 22px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; border:none; font-family:var(--font); }
    .btn-dl-primary { background:var(--color-primary); color:#fff; }
    .btn-dl-green   { background:#16a34a; color:#fff; }
    .btn-dl-orange  { background:#d97706; color:#fff; }
    .btn-dl-red     { background:#dc2626; color:#fff; }
    .btn-dl:hover   { opacity:.88; }
    .restore-zone { border:2px dashed var(--color-border); border-radius:10px; padding:24px; text-align:center; background:#fafafa; }
    .restore-zone input[type=file] { display:block; margin:12px auto 0; font-size:13px; }
    .log-table { width:100%; border-collapse:collapse; font-size:12px; }
    .log-table th { padding:8px 12px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--color-muted); border-bottom:1px solid var(--color-border); }
    .log-table td { padding:8px 12px; border-bottom:1px solid var(--color-border); }
    .log-table tr:last-child td { border-bottom:none; }
    .action-badge { padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; }
    .badge-export  { background:#dcfce7; color:#166534; }
    .badge-restore { background:#fdeaea; color:#dc2626; }
  </style>
</head>
<body>
<?php include('includes/sidebar.php'); ?>
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Data Recovery &amp; Backup</div>
      <div class="page-sub">Export full database backups and restore from SQL files</div>
    </div>
  </div>
  <div id="page-container">

    <?php if ($restore_success): ?>
    <div class="alert-success-bar"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($restore_success) ?></div>
    <?php endif; ?>
    <?php if ($restore_error): ?>
    <div class="alert-error-bar"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($restore_error) ?></div>
    <?php endif; ?>

    <div class="recovery-grid">

      <!-- Full SQL Backup -->
      <div class="recovery-card">
        <div class="recovery-card-icon" style="color:var(--color-primary);"><i class="bi bi-database-fill-down"></i></div>
        <h3>Full Database Backup (SQL)</h3>
        <p>Downloads a complete SQL dump of the entire database — all tables, data, and structure.
           Use this for full data recovery. File is timestamped.</p>
        <a href="backup.php?export=sql" class="btn-dl btn-dl-primary"
           onclick="return confirm('Download a full SQL backup of the database?')">
          <i class="bi bi-download"></i> Download SQL Backup
        </a>
      </div>

      <!-- Restore -->
      <div class="recovery-card">
        <div class="recovery-card-icon" style="color:#dc2626;"><i class="bi bi-database-fill-up"></i></div>
        <h3>Restore from SQL Backup</h3>
        <p>Upload a previously downloaded <code>.sql</code> backup file to restore the database.
           <strong style="color:#dc2626;">This will overwrite all current data.</strong> Max 50MB.</p>
        <form method="POST" action="backup.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="restore">
          <div class="restore-zone">
            <i class="bi bi-cloud-upload" style="font-size:28px;color:var(--color-muted);"></i>
            <div style="font-size:13px;color:var(--color-muted);margin-top:8px;">Select a .sql backup file</div>
            <input type="file" name="sql_file" accept=".sql,text/plain" required/>
          </div>
          <button type="submit" class="btn-dl btn-dl-red" style="margin-top:14px;width:100%;justify-content:center;"
                  onclick="return confirm('⚠️ WARNING: This will OVERWRITE all current database data with the backup file. This cannot be undone. Are you absolutely sure?')">
            <i class="bi bi-arrow-counterclockwise"></i> Restore Database
          </button>
        </form>
      </div>

      <!-- Students CSV -->
      <div class="recovery-card">
        <div class="recovery-card-icon" style="color:#494C8A;"><i class="bi bi-people-fill"></i></div>
        <h3>Student Records (CSV)</h3>
        <p>Exports all active students with grade, enrollment status, address, and personal details.
           Useful for spreadsheet analysis.</p>
        <a href="backup.php?export=students" class="btn-dl btn-dl-primary">
          <i class="bi bi-download"></i> Download Students CSV
        </a>
      </div>

      <!-- Payments CSV -->
      <div class="recovery-card">
        <div class="recovery-card-icon" style="color:#16a34a;"><i class="bi bi-cash-coin"></i></div>
        <h3>Payment Records (CSV)</h3>
        <p>Exports all payment transactions per student — OR numbers, amounts paid, balances, and payment methods.</p>
        <a href="backup.php?export=payments" class="btn-dl btn-dl-green">
          <i class="bi bi-download"></i> Download Payments CSV
        </a>
      </div>

    </div>

    <!-- Reminder -->
    <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;font-size:13px;color:#92400e;margin-top:20px;max-width:900px;">
      <i class="bi bi-calendar-week-fill"></i>
      <strong>Weekly Backup Reminder:</strong> Download the SQL backup every Friday and save it to a USB drive or Google Drive.
      The SQL backup can fully restore the database if data is lost.
    </div>

    <!-- Backup / Restore Log -->
    <?php if (!empty($backup_log)): ?>
    <div style="max-width:900px;margin-top:28px;">
      <div style="font-size:14px;font-weight:700;margin-bottom:12px;">Recent Backup Activity</div>
      <div style="background:#fff;border:1px solid var(--color-border);border-radius:10px;overflow:hidden;">
        <table class="log-table">
          <thead>
            <tr>
              <th>Action</th>
              <th>Target</th>
              <th>Details</th>
              <th>By</th>
              <th>Date &amp; Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($backup_log as $log): ?>
            <tr>
              <td>
                <span class="action-badge <?= str_starts_with($log['action'], 'restore') ? 'badge-restore' : 'badge-export' ?>">
                  <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($log['target'] ?? '—') ?></td>
              <td><?= htmlspecialchars($log['details'] ?? '—') ?></td>
              <td><?= htmlspecialchars($log['user_name'] ?? '—') ?></td>
              <td style="color:var(--color-muted);"><?= $log['created_at'] ? date('M j, Y g:i A', strtotime($log['created_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<script src="../js/nav.js"></script>
</body>
</html>
