<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $enroll_id = intval($_POST['enroll_id']);
  $status    = $_POST['status'];
  $allowed   = ['pending','enrolled','dropped'];
  if (in_array($status, $allowed)) {
    $stmt = $conn->prepare("UPDATE enrollments SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $enroll_id);
    $stmt->execute();

    $enroll = $conn->query("SELECT * FROM enrollments WHERE id=$enroll_id")->fetch_assoc();

    if ($status === 'enrolled' && $enroll) {
      $sid   = $enroll['student_id'];
      $sy_id = $enroll['school_year_id'];
      $gl_id = $enroll['grade_level_id'];

      // Auto-assign fees: insert payment records for all fees of this grade/SY
      $fees = $conn->query("SELECT * FROM fees WHERE grade_level_id=$gl_id AND school_year_id=$sy_id");
      while ($fee = $fees->fetch_assoc()) {
        $conn->query("INSERT IGNORE INTO payments (student_id, fee_id, amount_paid, balance, status)
          VALUES ($sid, {$fee['id']}, 0, {$fee['amount']}, 'unpaid')");
      }

      // Notify all admin users
      $admins = $conn->query("SELECT id FROM users WHERE is_active=1");
      $student_name = $conn->query("SELECT CONCAT(first_name,' ',last_name) as n FROM students WHERE id=$sid")->fetch_assoc()['n'] ?? 'Student';
      while ($admin = $admins->fetch_assoc()) {
        $title = "Student Enrolled: $student_name";
        $body  = "Enrollment approved. Fees have been auto-assigned.";
        $link  = "student_profile.php?id=$sid";
        $conn->query("INSERT INTO notifications (user_id, type, title, body, link) VALUES ({$admin['id']}, 'success', '$title', '$body', '$link')");
      }

      // Notify parent if exists
      $parent = $conn->query("
        SELECT pa.id, pa.email, pa.name
        FROM parent_accounts pa
        JOIN parent_student_links psl ON psl.parent_id = pa.id
        WHERE psl.student_id = $sid LIMIT 1
      ")->fetch_assoc();

      if ($parent) {
        $active_sy_row = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
        $sy_label   = $active_sy_row['label'] ?? '';
        $grade_name = $conn->query("SELECT g.name FROM students s LEFT JOIN grade_levels g ON g.id=s.grade_level_id WHERE s.id=$sid")->fetch_assoc()['name'] ?? '';

        // In-portal notification
        $p_id = $parent['id'];
        $p_title = $conn->real_escape_string("🎉 Congratulations! Enrollment Confirmed");
        $p_body  = $conn->real_escape_string("$student_name is now officially enrolled for $grade_name — SY $sy_label. Please log in to select your payment scheme and view your Statement of Account.");
        $conn->query("INSERT INTO parent_notifications (parent_id, student_id, type, title, body) VALUES ($p_id, $sid, 'success', '$p_title', '$p_body')");

        // Email notification
        require_once '../mysql/email_notifications.php';
        notifyEnrollmentConfirmed($parent['email'], $parent['name'], $student_name, $grade_name, $sy_label);
      }
    }

    if ($status === 'dropped' && $enroll) {
      $sid = $enroll['student_id'];
      $student_name = $conn->query("SELECT CONCAT(first_name,' ',last_name) as n FROM students WHERE id=$sid")->fetch_assoc()['n'] ?? 'Student';
      $admins = $conn->query("SELECT id FROM users WHERE is_active=1");
      while ($admin = $admins->fetch_assoc()) {
        $title = "Enrollment Dropped: $student_name";
        $conn->query("INSERT INTO notifications (user_id, type, title, body) VALUES ({$admin['id']}, 'warning', '$title', 'Enrollment status changed to dropped.')");
      }
    }

    // Audit log
    $uid   = $_SESSION['user_id'] ?? 0;
    $uname = $conn->real_escape_string($_SESSION['name'] ?? '');
    $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id, details) VALUES ($uid, '$uname', 'update_enrollment_status', 'enrollment', $enroll_id, 'Status changed to $status')");
  }
  header("Location: enrollment.php?success=Status updated"); exit();
}

// Handle new enrollment (walk-in: registrar enrolls student manually)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
  $student_id     = intval($_POST['student_id']);
  $school_year_id = intval($_POST['school_year_id']);
  $grade_level_id = intval($_POST['grade_level_id']);
  $status         = 'pending';

  // Generate reference number: ENR-YYYY-NNNN
  $year    = date('Y');
  $count   = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE YEAR(enrolled_at) = $year")->fetch_assoc()['c'] + 1;
  $ref_num = 'ENR-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

  $stmt = $conn->prepare("INSERT INTO enrollments (ref_number, student_id, school_year_id, grade_level_id, status)
    VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE grade_level_id=VALUES(grade_level_id), status=VALUES(status)");
  $stmt->bind_param("siiis", $ref_num, $student_id, $school_year_id, $grade_level_id, $status);
  $stmt->execute()
    ? header("Location: enrollment.php?success=Student enrolled successfully")
    : header("Location: enrollment.php?error=" . urlencode($conn->error));
  exit();
}

$active_sy   = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id       = $active_sy['id'] ?? 0;
$filter_status = in_array($_GET['status'] ?? '', ['pending','enrolled','dropped']) ? $_GET['status'] : '';
$search      = trim($_GET['search'] ?? '');
$sy_list     = $conn->query("SELECT * FROM school_years ORDER BY label DESC")->fetch_all(MYSQLI_ASSOC);
$grade_list  = $conn->query("SELECT * FROM grade_levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Students not yet enrolled this SY (for walk-in enroll modal)
$unenrolled = $conn->query("
  SELECT s.id, s.first_name, s.last_name, s.lrn, g.name as grade
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  WHERE s.is_archived = 0
    AND s.id NOT IN (SELECT student_id FROM enrollments WHERE school_year_id = $sy_id)
  ORDER BY s.last_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Enrolled list with parameterized search
$base_sql = "
  SELECT e.id, e.ref_number, e.status, e.enrolled_at,
         s.first_name, s.last_name, s.lrn, s.photo, s.id as student_id,
         g.name as grade, sec.name as section
  FROM enrollments e
  JOIN students s ON e.student_id = s.id
  LEFT JOIN grade_levels g ON e.grade_level_id = g.id
  LEFT JOIN sections sec ON e.section_id = sec.id
  WHERE e.school_year_id = ?";

$bind_types = "i";
$bind_vals  = [$sy_id];

if ($filter_status) {
  $base_sql   .= " AND e.status = ?";
  $bind_types .= "s";
  $bind_vals[] = $filter_status;
}
if ($search !== '') {
  $sp = "%$search%";
  $base_sql   .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ? OR e.ref_number LIKE ?)";
  $bind_types .= "ssss";
  $bind_vals[] = $sp; $bind_vals[] = $sp; $bind_vals[] = $sp; $bind_vals[] = $sp;
}
$base_sql .= " ORDER BY e.enrolled_at DESC";

$enroll_stmt = $conn->prepare($base_sql);
$enroll_stmt->bind_param($bind_types, ...$bind_vals);
$enroll_stmt->execute();
$enrollments = $enroll_stmt->get_result();

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page = 'enrollment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Enrollment — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/enrollment.css">
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Enrollment</div>
      <div class="page-sub">SY <?= htmlspecialchars($active_sy['label'] ?? 'N/A') ?></div>
    </div>
    <div class="topbar-actions">
      <button class="btn-topbar" id="btn-enroll"><i class="bi bi-plus-lg"></i> Enroll Student</button>
    </div>
  </div>

  <div id="page-container">

    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <!-- Filter + Search bar -->
    <div class="enroll-toolbar">
      <div class="enroll-tabs">
        <a href="enrollment.php<?= $search ? '?search='.urlencode($search) : '' ?>" class="enroll-tab <?= !$filter_status ? 'active' : '' ?>">All</a>
        <a href="enrollment.php?status=pending<?= $search ? '&search='.urlencode($search) : '' ?>"  class="enroll-tab <?= $filter_status==='pending'  ? 'active' : '' ?>">Pending</a>
        <a href="enrollment.php?status=enrolled<?= $search ? '&search='.urlencode($search) : '' ?>" class="enroll-tab <?= $filter_status==='enrolled' ? 'active' : '' ?>">Enrolled</a>
        <a href="enrollment.php?status=dropped<?= $search ? '&search='.urlencode($search) : '' ?>"  class="enroll-tab <?= $filter_status==='dropped'  ? 'active' : '' ?>">Dropped</a>
      </div>
      <form method="GET" action="enrollment.php" class="enroll-search-form">
        <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"/><?php endif; ?>
        <div class="search-wrap">
          <i class="bi bi-search search-icon"></i>
          <input type="search" name="search" class="toolbar-search-input" placeholder="Search name, LRN, or ref #" value="<?= htmlspecialchars($search) ?>"/>
        </div>
        <button type="submit" class="btn-search">Search</button>
        <?php if ($search): ?>
          <a href="enrollment.php<?= $filter_status ? '?status='.$filter_status : '' ?>" class="btn-clear-filters">
            <i class="bi bi-x-circle"></i> Clear
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="enroll-table-card">
      <table class="enroll-list-table">
        <thead>
          <tr><th>Ref #</th><th>Student</th><th>LRN</th><th>Grade</th><th>Status</th><th>Date</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php $count = 0; while ($e = $enrollments->fetch_assoc()): $count++; ?>
          <tr>
            <td class="td-muted" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($e['ref_number'] ?? '—') ?></td>
            <td>
              <div class="name-cell">
                <?php if (!empty($e['photo'])): ?>
                  <img src="uploads/<?= htmlspecialchars($e['photo']) ?>" class="mini-pic"/>
                <?php else: ?>
                  <div class="mini-avatar"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
                <span><?= htmlspecialchars($e['last_name'] . ', ' . $e['first_name']) ?></span>
              </div>
            </td>
            <td class="td-muted"><?= htmlspecialchars($e['lrn']) ?></td>
            <td><?= htmlspecialchars($e['grade'] ?? '—') ?></td>
            <td>
              <span class="enroll-status-badge status-<?= htmlspecialchars($e['status']) ?>"><?= htmlspecialchars(ucfirst($e['status'])) ?></span>
            </td>
            <td class="td-muted"><?= date('M j, Y', strtotime($e['enrolled_at'])) ?></td>
            <td>
              <form method="POST" action="enrollment.php" style="display:inline-flex;gap:4px;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="enroll_id" value="<?= $e['id'] ?>">
                <select name="status" class="status-select" onchange="if(confirm('Change enrollment status to ' + this.options[this.selectedIndex].text + '?')){this.form.submit();}else{this.value='<?= $e['status'] ?>';}">
                  <option value="pending"  <?= $e['status']==='pending'  ? 'selected':'' ?>>Pending</option>
                  <option value="enrolled" <?= $e['status']==='enrolled' ? 'selected':'' ?>>Enrolled</option>
                  <option value="dropped"  <?= $e['status']==='dropped'  ? 'selected':'' ?>>Dropped</option>
                </select>
              </form>
              <a href="student_profile.php?id=<?= $e['student_id'] ?>" class="btn-view-sm">View</a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($count === 0): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">No enrollment records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Enroll Modal -->
<div class="modal-overlay" id="enroll-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Enroll Student</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="enrollment.php">
      <input type="hidden" name="action" value="enroll">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr;">
          <div class="form-group">
            <label>Student *</label>
            <select name="student_id" class="form-input" required>
              <option value="">Select student</option>
              <?php foreach ($unenrolled as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['last_name'] . ', ' . $u['first_name'] . ' — ' . $u['lrn']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>School Year *</label>
            <select name="school_year_id" class="form-input" required>
              <?php foreach ($sy_list as $sy): ?>
                <option value="<?= $sy['id'] ?>" <?= $sy['is_active'] ? 'selected':'' ?>>SY <?= htmlspecialchars($sy['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Grade Level *</label>
            <select name="grade_level_id" class="form-input" required>
              <option value="">Select grade</option>
              <?php foreach ($grade_list as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Enroll</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/nav.js"></script>
<script>
  const modal = document.getElementById('enroll-modal');
  document.getElementById('btn-enroll').onclick  = () => modal.classList.add('open');
  document.getElementById('modal-close').onclick  = () => modal.classList.remove('open');
  document.getElementById('modal-cancel').onclick = () => modal.classList.remove('open');
</script>
</body>
</html>
