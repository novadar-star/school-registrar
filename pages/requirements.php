<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;
$uid       = $_SESSION['user_id'] ?? 0;
$role      = $_SESSION['role'] ?? '';
$uname     = $_SESSION['name'] ?? '';

// ── Verify ──────────────────────────────────────────────────
if (isset($_GET['verify'])) {
  $rid = intval($_GET['verify']);
  $sid = intval($_GET['student_id'] ?? 0);
  $stmt = $conn->prepare("UPDATE student_requirements SET status='verified', verified_by=?, verified_at=NOW() WHERE id=?");
  $stmt->bind_param("ii", $uid, $rid);
  $stmt->execute();
  // Notify parent
  $parent = $conn->query("SELECT pa.id FROM parent_accounts pa JOIN parent_student_links psl ON psl.parent_id=pa.id WHERE psl.student_id=$sid LIMIT 1")->fetch_assoc();
  if ($parent) {
    $req_name = $conn->query("SELECT r.name FROM student_requirements sr JOIN requirements r ON r.id=sr.requirement_id WHERE sr.id=$rid")->fetch_assoc()['name'] ?? 'Document';
    notify_parent($conn, $parent['id'], $sid, 'success', 'Document Verified', "$req_name has been verified by the registrar.");
  }
  // Email notifications not active in current deployment (no SMTP configured)
  audit_log($conn, $uid, $uname, 'verify_document', 'student_requirement', $rid);
  header("Location: requirements.php?student_id=$sid&success=Document verified"); exit();
}

// ── Mark received ────────────────────────────────────────────
if (isset($_GET['mark_received'])) {
  $req_id = intval($_GET['req_id']);
  $sid    = intval($_GET['student_id'] ?? 0);
  $stmt = $conn->prepare("INSERT INTO student_requirements (student_id, requirement_id, school_year_id, status, submitted_at, verified_by, verified_at, uploaded_by, uploaded_by_role)
    VALUES (?,?,?,'verified',NOW(),?,NOW(),?,?)
    ON DUPLICATE KEY UPDATE status='verified', submitted_at=NOW(), verified_by=VALUES(verified_by), verified_at=NOW(), uploaded_by=VALUES(uploaded_by), uploaded_by_role=VALUES(uploaded_by_role)");
  $stmt->bind_param("iiiiii", $sid, $req_id, $sy_id, $uid, $uid, $role);
  $stmt->execute();
  audit_log($conn, $uid, $uname, 'mark_received', 'student_requirement', $req_id, "Student $sid");
  header("Location: requirements.php?student_id=$sid&success=Document marked as received and verified"); exit();
}

// ── Admin upload ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_upload') {
  $req_id = intval($_POST['req_id']);
  $sid    = intval($_POST['student_id'] ?? 0);
  if (!empty($_FILES['doc_file']['tmp_name'])) {
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    $max_size = 3 * 1024 * 1024; // 3MB
    // Server-side MIME check
    $finfo_adm = new finfo(FILEINFO_MIME_TYPE);
    $real_mime_adm = $finfo_adm->file($_FILES['doc_file']['tmp_name']);
    if (!in_array($real_mime_adm, $allowed)) {
      header("Location: requirements.php?student_id=$sid&error=Only JPG, PNG, WEBP, PDF allowed"); exit();
    }
    if ($_FILES['doc_file']['size'] > $max_size) {
      header("Location: requirements.php?student_id=$sid&error=File must be under 3MB"); exit();
    }
    // Get student name and doc name for tagged filename
    $student_row = $conn->query("SELECT CONCAT(last_name,', ',first_name) as n FROM students WHERE id=$sid")->fetch_assoc();
    $req_row     = $conn->query("SELECT name FROM requirements WHERE id=$req_id")->fetch_assoc();
    $student_name = $student_row['n'] ?? 'Student';
    $doc_name     = $req_row['name'] ?? 'Document';
    $ext      = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
    $safe     = preg_replace('/[^a-zA-Z0-9\-]/', '_', $student_name . '_' . $doc_name);
    $filename = $safe . '_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['doc_file']['tmp_name'], "uploads/" . $filename);
    $stmt = $conn->prepare("INSERT INTO student_requirements (student_id, requirement_id, school_year_id, file_path, status, submitted_at, verified_by, verified_at, uploaded_by, uploaded_by_role)
      VALUES (?,?,?,?,'verified',NOW(),?,NOW(),?,?)
      ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='verified', submitted_at=NOW(), verified_by=VALUES(verified_by), verified_at=NOW(), uploaded_by=VALUES(uploaded_by), uploaded_by_role=VALUES(uploaded_by_role)");
    $stmt->bind_param("iiisiis", $sid, $req_id, $sy_id, $filename, $uid, $uid, $role);
    $stmt->execute();
    // Notify parent
    $parent = $conn->query("SELECT pa.id FROM parent_accounts pa JOIN parent_student_links psl ON psl.parent_id=pa.id WHERE psl.student_id=$sid LIMIT 1")->fetch_assoc();
    if ($parent) notify_parent($conn, $parent['id'], $sid, 'success', 'Document Uploaded', "$doc_name has been uploaded and verified by the $role.");
    audit_log($conn, $uid, $uname, 'admin_upload_document', 'student', $sid, "Req $req_id: $doc_name");
  }
  header("Location: requirements.php?student_id=$sid&success=Document uploaded and verified"); exit();
}

// ── Reject with reason ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_doc') {
  $sr_id  = intval($_POST['sr_id']);
  $sid    = intval($_POST['student_id'] ?? 0);
  $reason = trim($_POST['reject_reason'] ?? '');
  $stmt = $conn->prepare("UPDATE student_requirements SET status='rejected', reject_reason=?, verified_by=NULL, verified_at=NULL WHERE id=?");
  $stmt->bind_param("si", $reason, $sr_id);
  $stmt->execute();
  // Notify parent
  $sr_row = $conn->query("SELECT sr.student_id, r.name as doc_name, pa.id as parent_id
    FROM student_requirements sr
    JOIN requirements r ON r.id=sr.requirement_id
    LEFT JOIN parent_student_links psl ON psl.student_id=sr.student_id
    LEFT JOIN parent_accounts pa ON pa.id=psl.parent_id
    WHERE sr.id=$sr_id LIMIT 1")->fetch_assoc();
  if ($sr_row && $sr_row['parent_id']) {
    $msg = "Your submitted {$sr_row['doc_name']} was rejected." . ($reason ? " Reason: $reason" : '');
    notify_parent($conn, $sr_row['parent_id'], $sr_row['student_id'], 'danger', 'Document Rejected', $msg);
  }
  // Email notifications not active in current deployment (no SMTP configured)
  audit_log($conn, $uid, $uname, 'reject_document', 'student_requirement', $sr_id, $reason);
  header("Location: requirements.php?student_id=$sid&success=Document rejected"); exit();
}

// ── Mark as to_follow ────────────────────────────────────────
if (isset($_GET['to_follow'])) {
  $req_id = intval($_GET['req_id']);
  $sid    = intval($_GET['student_id'] ?? 0);
  $stmt = $conn->prepare("INSERT INTO student_requirements (student_id, requirement_id, school_year_id, status)
    VALUES (?,?,?,'to_follow')
    ON DUPLICATE KEY UPDATE status='to_follow'");
  $stmt->bind_param("iii", $sid, $req_id, $sy_id);
  $stmt->execute();
  audit_log($conn, $uid, $uname, 'mark_to_follow', 'student_requirement', $req_id, "Student $sid");
  header("Location: requirements.php?student_id=$sid&success=Marked as To Follow"); exit();
}

// ── Notify parent about missing docs ────────────────────────
if (isset($_GET['notify_missing'])) {
  $sid = intval($_GET['notify_missing']);
  $parent = $conn->query("SELECT pa.id FROM parent_accounts pa JOIN parent_student_links psl ON psl.parent_id=pa.id WHERE psl.student_id=$sid LIMIT 1")->fetch_assoc();
  if ($parent) {
    $missing = $conn->query("
      SELECT r.name FROM requirements r
      LEFT JOIN student_requirements sr ON sr.requirement_id=r.id AND sr.student_id=$sid AND sr.school_year_id=$sy_id
      WHERE r.is_required=1 AND (sr.id IS NULL OR sr.status IN ('missing','rejected'))
    ")->fetch_all(MYSQLI_ASSOC);
    $list = implode(', ', array_column($missing, 'name'));
    if ($list) {
      notify_parent($conn, $parent['id'], $sid, 'warning', 'Missing Documents', "The following documents are still missing or need to be resubmitted: $list. Please upload them in the portal.");
      audit_log($conn, $uid, $uname, 'notify_missing_docs', 'student', $sid, $list);
      header("Location: requirements.php?student_id=$sid&success=Parent notified about missing documents"); exit();
    }
  }
  header("Location: requirements.php?student_id=$sid&success=No missing documents to notify"); exit();
}

$search = trim($_GET['search'] ?? '');

// Parameterized search for student list
$req_list_sql = "
  SELECT s.id, s.first_name, s.last_name, s.lrn, g.name as grade,
    COUNT(r.id) as total_req,
    SUM(sr.status='verified') as verified,
    SUM(sr.status='submitted') as submitted,
    SUM(sr.status='to_follow') as to_follow,
    SUM(sr.status='rejected') as rejected,
    SUM(sr.status='missing' OR sr.id IS NULL) as missing
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  LEFT JOIN requirements r ON (r.student_type = 'both' OR r.student_type = s.student_type) AND r.is_required = 1
  LEFT JOIN student_requirements sr ON sr.student_id = s.id AND sr.requirement_id = r.id AND sr.school_year_id = ?
  WHERE s.is_archived = 0";

$req_bind_types = "i";
$req_bind_vals  = [$sy_id];
if ($search !== '') {
  $sp = "%$search%";
  $req_list_sql   .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
  $req_bind_types .= "sss";
  $req_bind_vals[] = $sp; $req_bind_vals[] = $sp; $req_bind_vals[] = $sp;
}
$req_list_sql .= " GROUP BY s.id ORDER BY s.last_name ASC";

$req_list_stmt = $conn->prepare($req_list_sql);
$req_list_stmt->bind_param($req_bind_types, ...$req_bind_vals);
$req_list_stmt->execute();
$students_req = $req_list_stmt->get_result();

$detail_student = null;
$detail_reqs    = [];
if (!empty($_GET['student_id'])) {
  $sid = intval($_GET['student_id']);
  $detail_student = $conn->query("SELECT s.*, g.name as grade FROM students s LEFT JOIN grade_levels g ON s.grade_level_id=g.id WHERE s.id=$sid")->fetch_assoc();
  $req_res = $conn->query("
    SELECT r.id as req_id, r.name, r.description,
           sr.id as sr_id, sr.status, sr.file_path, sr.submitted_at, sr.notes, sr.reject_reason, sr.uploaded_by_role
    FROM requirements r
    LEFT JOIN student_requirements sr ON sr.requirement_id = r.id AND sr.student_id = $sid AND sr.school_year_id = $sy_id
    WHERE r.is_required = 1 AND (r.student_type = 'both' OR r.student_type = '{$detail_student['student_type']}')
    ORDER BY r.sort_order
  ");
  while ($row = $req_res->fetch_assoc()) $detail_reqs[] = $row;
}

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page = 'requirements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Requirements — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/requirements.css">
  <style>
    .req-badge-to_follow { background:#fde8d8; color:#9a3412; }
    .req-badge-rejected  { background:#fdeaea; color:#dc2626; }
    .dot-to_follow { background:#f97316; }
    .dot-rejected  { background:#dc2626; }
    .reject-reason { font-size:11px; color:#dc2626; margin-top:3px; font-style:italic; }
    .uploaded-by   { font-size:10px; color:var(--color-muted); margin-top:2px; }
  </style>
</head>
<body>
<?php include('includes/sidebar.php'); ?>
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Requirements Tracker</div>
      <div class="page-sub">SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></div>
    </div>
  </div>
  <div id="page-container">
    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <?php if ($detail_student): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
      <a href="requirements.php" class="back-link" style="color:var(--color-primary);font-weight:600;"><i class="bi bi-arrow-left"></i> Back</a>
      <div style="display:flex;gap:8px;">
        <a href="requirements.php?notify_missing=<?= $detail_student['id'] ?>" class="btn-topbar" style="font-size:12px;padding:7px 14px;" onclick="return confirm('Notify parent about missing documents?')">
          <i class="bi bi-bell-fill"></i> Notify Parent
        </a>
      </div>
    </div>
    <div class="req-detail-header">
      <div>
        <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($detail_student['last_name'] . ', ' . $detail_student['first_name']) ?></div>
        <div style="font-size:13px;color:var(--color-muted);">LRN: <?= htmlspecialchars($detail_student['lrn']) ?> · <?= htmlspecialchars($detail_student['grade'] ?? '') ?> · <?= ucfirst($detail_student['student_type']) ?> student</div>
      </div>
    </div>

    <div class="req-list">
      <?php foreach ($detail_reqs as $req):
        $status = $req['status'] ?? 'missing';
      ?>
      <div class="req-item req-<?= htmlspecialchars($status) ?>">
        <div class="req-item-left">
          <div class="req-status-dot dot-<?= htmlspecialchars($status) ?>"></div>
          <div>
            <div class="req-name"><?= htmlspecialchars($req['name']) ?></div>
            <?php if ($req['submitted_at']): ?><div class="req-date">Submitted: <?= date('M j, Y', strtotime($req['submitted_at'])) ?></div><?php endif; ?>
            <?php if ($status === 'rejected' && $req['reject_reason']): ?><div class="reject-reason"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($req['reject_reason']) ?></div><?php endif; ?>
            <?php if (!empty($req['uploaded_by_role'])): ?><div class="uploaded-by">Uploaded by: <?= ucfirst($req['uploaded_by_role']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="req-item-right" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span class="req-badge req-badge-<?= $status ?>"><?= ucfirst(str_replace('_',' ',$status)) ?></span>

          <?php if (!empty($req['file_path'])): ?>
            <a href="uploads/<?= htmlspecialchars($req['file_path']) ?>" target="_blank" class="btn-view-sm"><i class="bi bi-eye-fill"></i> View</a>
            <?php if (!empty($req['sr_id'])): ?>
              <a href="doc_download.php?sr_id=<?= $req['sr_id'] ?>" class="btn-view-sm" style="background:#eef0f8;color:var(--color-primary);"><i class="bi bi-download"></i> Download</a>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($status === 'submitted'): ?>
            <a href="requirements.php?verify=<?= $req['sr_id'] ?>&student_id=<?= $detail_student['id'] ?>" class="btn-verify" onclick="return confirm('Mark as verified?')"><i class="bi bi-check-lg"></i> Verify</a>
            <button class="btn-reject" onclick="openRejectModal(<?= $req['sr_id'] ?>, <?= $detail_student['id'] ?>)"><i class="bi bi-x-lg"></i> Reject</button>

          <?php elseif ($status === 'missing' || $status === 'rejected'): ?>
            <form method="POST" action="requirements.php?student_id=<?= $detail_student['id'] ?>" enctype="multipart/form-data" style="display:inline-flex;align-items:center;gap:5px;">
              <input type="hidden" name="action" value="admin_upload">
              <input type="hidden" name="req_id" value="<?= $req['req_id'] ?>">
              <input type="hidden" name="student_id" value="<?= $detail_student['id'] ?>">
              <input type="file" name="doc_file" accept="image/*,.pdf" style="font-size:11px;max-width:130px;border:1px solid var(--color-border);border-radius:4px;padding:3px 5px;" required/>
              <button type="submit" class="btn-verify"><i class="bi bi-upload"></i> Upload</button>
            </form>
            <a href="requirements.php?mark_received=1&req_id=<?= $req['req_id'] ?>&student_id=<?= $detail_student['id'] ?>" class="btn-verify" onclick="return confirm('Mark as received and verified?')"><i class="bi bi-check2-circle"></i> Received</a>
            <a href="requirements.php?to_follow=1&req_id=<?= $req['req_id'] ?>&student_id=<?= $detail_student['id'] ?>" class="btn-reject" style="background:#fde8d8;color:#9a3412;" onclick="return confirm('Mark as To Follow?')"><i class="bi bi-clock"></i> To Follow</a>

          <?php elseif ($status === 'to_follow'): ?>
            <form method="POST" action="requirements.php?student_id=<?= $detail_student['id'] ?>" enctype="multipart/form-data" style="display:inline-flex;align-items:center;gap:5px;">
              <input type="hidden" name="action" value="admin_upload">
              <input type="hidden" name="req_id" value="<?= $req['req_id'] ?>">
              <input type="hidden" name="student_id" value="<?= $detail_student['id'] ?>">
              <input type="file" name="doc_file" accept="image/*,.pdf" style="font-size:11px;max-width:130px;border:1px solid var(--color-border);border-radius:4px;padding:3px 5px;" required/>
              <button type="submit" class="btn-verify"><i class="bi bi-upload"></i> Upload</button>
            </form>

          <?php elseif ($status === 'verified'): ?>
            <a href="requirements.php?to_follow=1&req_id=<?= $req['req_id'] ?>&student_id=<?= $detail_student['id'] ?>" class="btn-reject" style="font-size:11px;padding:3px 10px;" onclick="return confirm('Revert to To Follow?')"><i class="bi bi-arrow-counterclockwise"></i> Undo</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <form method="GET" action="requirements.php" style="display:flex;gap:8px;margin-bottom:16px;">
      <div class="search-wrap"><i class="bi bi-search search-icon"></i>
        <input type="search" name="search" class="toolbar-search-input" placeholder="Search student name or LRN" value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <button type="submit" class="btn-search"><i class="bi bi-search"></i> Search</button>
      <?php if ($search): ?><a href="requirements.php" class="btn-clear-filters"><i class="bi bi-x-circle"></i> Clear</a><?php endif; ?>
    </form>
    <div class="req-table-card">
      <table class="req-table">
        <thead><tr><th>Student</th><th>LRN</th><th>Grade</th><th>Verified</th><th>Submitted</th><th>To Follow</th><th>Rejected</th><th>Missing</th><th>Action</th></tr></thead>
        <tbody>
          <?php $count = 0; while ($s = $students_req->fetch_assoc()): $count++; ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($s['lrn']) ?></td>
            <td><?= htmlspecialchars($s['grade'] ?? '—') ?></td>
            <td style="color:var(--color-success);font-weight:600;"><?= $s['verified'] ?></td>
            <td style="color:var(--color-warning);font-weight:600;"><?= $s['submitted'] ?></td>
            <td style="color:#f97316;font-weight:600;"><?= $s['to_follow'] ?></td>
            <td style="color:var(--color-danger);font-weight:600;"><?= $s['rejected'] ?></td>
            <td style="color:var(--color-danger);font-weight:600;"><?= $s['missing'] ?></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="requirements.php?student_id=<?= $s['id'] ?>" class="btn-view-sm"><i class="bi bi-folder2-open"></i> View</a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($count === 0): ?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--color-muted);">No students found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="reject-modal">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-header"><h2>Reject Document</h2><button class="modal-close" onclick="document.getElementById('reject-modal').classList.remove('open')">&times;</button></div>
    <form method="POST" action="requirements.php">
      <input type="hidden" name="action" value="reject_doc">
      <input type="hidden" name="sr_id" id="reject-sr-id" value="">
      <input type="hidden" name="student_id" id="reject-student-id" value="">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for rejection <span style="color:var(--color-muted);font-weight:400;">(optional)</span></label>
          <input type="text" name="reject_reason" class="form-input" placeholder="e.g. Blurry image, wrong document"/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('reject-modal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn-save" style="background:#dc2626;">Reject</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/nav.js"></script>
<script>
function openRejectModal(srId, studentId) {
  document.getElementById('reject-sr-id').value = srId;
  document.getElementById('reject-student-id').value = studentId;
  document.getElementById('reject-modal').classList.add('open');
}
</script>
</body>
</html>
