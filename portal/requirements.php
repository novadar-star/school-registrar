<?php
$active_portal = 'requirements';
require_once 'includes/auth.php';
require_once '../mysql/helpers.php';

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;
$student   = $conn->query("SELECT * FROM students WHERE id=$student_id")->fetch_assoc();

// Guard: no linked student found
if (!$student) {
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    </head><body>';
  include('includes/nav.php');
  echo '<div class="portal-container" style="text-align:center;padding:60px 20px;">
    <i class="bi bi-exclamation-circle" style="font-size:48px;color:#d97706;"></i>
    <h3 style="margin-top:16px;">No Student Linked</h3>
    <p style="color:#6b7280;font-size:14px;">Your account has no enrolled student linked yet.<br>
    Please wait for the registrar to process your enrollment application.</p>
    <a href="dashboard.php" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#494C8A;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
      Back to Dashboard
    </a>
  </div></body></html>';
  exit();
}

$success = $error = '';

// Handle file upload (3MB limit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $req_id = intval($_POST['requirement_id']);
  if (empty($_FILES['doc_file']['tmp_name'])) {
    $error = "Please select a file to upload.";
  } else {
    $allowed  = ['image/jpeg','image/png','image/webp','application/pdf'];
    $max_size = 3 * 1024 * 1024; // 3MB
    // Server-side MIME check using finfo (not browser-supplied type)
    $finfo_p = new finfo(FILEINFO_MIME_TYPE);
    $real_mime_p = $finfo_p->file($_FILES['doc_file']['tmp_name']);
    if (!in_array($real_mime_p, $allowed)) {
      $error = "Only JPG, PNG, WEBP, or PDF files are allowed.";
    } elseif ($_FILES['doc_file']['size'] > $max_size) {
      $error = "File must be under 3MB.";
    } else {
      // Tagged filename
      $req_name = $conn->query("SELECT name FROM requirements WHERE id=$req_id")->fetch_assoc()['name'] ?? 'Document';
      $student_name = ($student['last_name'] ?? '') . '_' . ($student['first_name'] ?? '');
      $ext      = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
      $safe     = preg_replace('/[^a-zA-Z0-9\-]/', '_', $student_name . '_' . $req_name);
      $filename = $safe . '_' . uniqid() . '.' . $ext;
      $upload_path = __DIR__ . '/../pages/uploads/';
      if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);
      move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_path . $filename);

      $stmt = $conn->prepare("INSERT INTO student_requirements (student_id, requirement_id, school_year_id, file_path, status, submitted_at)
        VALUES (?,?,?,?,'submitted',NOW())
        ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='submitted', submitted_at=NOW()");
      $stmt->bind_param("iiis", $student_id, $req_id, $sy_id, $filename);
      if ($stmt->execute()) {
        $success = "Document uploaded successfully. It is now under review.";
        // Notify registrar
        notify_staff($conn, ['superadmin','registrar'], 'info', 'Document Submitted',
          ($student['first_name'].' '.$student['last_name']) . " submitted $req_name for review.",
          "requirements.php?student_id=$student_id");
      } else {
        $error = "Upload failed. Please try again.";
      }
    }
  }
}

// Fetch requirements
$reqs = $conn->query("
  SELECT r.id as req_id, r.name, r.description,
         sr.id as sr_id, sr.status, sr.file_path, sr.submitted_at, sr.reject_reason
  FROM requirements r
  LEFT JOIN student_requirements sr ON sr.requirement_id=r.id AND sr.student_id=$student_id AND sr.school_year_id=$sy_id
  WHERE r.is_required=1 AND (r.student_type='both' OR r.student_type='{$student['student_type']}')
  ORDER BY r.sort_order
")->fetch_all(MYSQLI_ASSOC);

// Unread parent notifications
$notifs = $conn->query("SELECT * FROM parent_notifications WHERE parent_id=$parent_id AND is_read=0 ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
if (!empty($notifs)) {
  $conn->query("UPDATE parent_notifications SET is_read=1 WHERE parent_id=$parent_id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Requirements — Parent Portal</title>
  <link rel="icon" type="image/x-icon" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal.css">
  <style>
    .badge-to_follow { background:#fde8d8; color:#9a3412; }
    .badge-rejected  { background:#fdeaea; color:#dc2626; }
    .reject-reason   { font-size:12px; color:#dc2626; margin-top:4px; font-style:italic; }
    .notif-banner { background:#fef9c3; border:1px solid #fde68a; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#92400e; }
    .notif-banner.danger { background:#fef2f2; border-color:#fca5a5; color:#b91c1c; }
    .notif-banner.success { background:#f0fdf4; border-color:#86efac; color:#166534; }
  </style>
</head>
<body>
<?php include('includes/nav.php'); ?>
<div class="portal-container">
  <div class="portal-page-header">
    <h2>Document Requirements</h2>
    <p>Upload your required documents for SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></p>
  </div>

  <?php foreach ($notifs as $n): ?>
  <div class="notif-banner <?= $n['type'] === 'danger' ? 'danger' : ($n['type'] === 'success' ? 'success' : '') ?>">
    <i class="bi bi-bell-fill"></i> <strong><?= htmlspecialchars($n['title']) ?></strong>
    <?php if ($n['body']): ?> — <?= htmlspecialchars($n['body']) ?><?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if ($success): ?><div class="portal-success-msg"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="portal-error-msg"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="portal-req-list">
    <?php foreach ($reqs as $r):
      $status = $r['status'] ?? 'missing';
    ?>
    <div class="portal-req-item">
      <div class="portal-req-left">
        <div class="portal-req-dot dot-<?= $status ?>"></div>
        <div>
          <div class="portal-req-name"><?= htmlspecialchars($r['name']) ?></div>
          <?php if ($r['submitted_at']): ?><div class="portal-req-date">Submitted <?= date('M j, Y', strtotime($r['submitted_at'])) ?></div><?php endif; ?>
          <?php if ($status === 'rejected' && $r['reject_reason']): ?>
            <div class="reject-reason"><i class="bi bi-exclamation-circle"></i> Rejected: <?= htmlspecialchars($r['reject_reason']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="portal-req-right">
        <span class="portal-req-badge badge-<?= $status ?>">
          <?= match($status) {
            'verified'  => '✓ Verified',
            'submitted' => '⏳ Under Review',
            'to_follow' => '📋 To Follow',
            'rejected'  => '✗ Rejected',
            default     => '✗ Missing'
          } ?>
        </span>
        <?php if (!empty($r['file_path'])): ?>
          <a href="../pages/uploads/<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="portal-btn-view">View</a>
        <?php endif; ?>
        <?php if ($status !== 'verified'): ?>
        <form method="POST" action="requirements.php" enctype="multipart/form-data" style="display:inline-flex;align-items:center;gap:6px;">
          <input type="hidden" name="requirement_id" value="<?= $r['req_id'] ?>">
          <input type="file" name="doc_file" accept="image/*,.pdf" class="portal-file-input" required/>
          <button type="submit" class="portal-btn-upload"><i class="bi bi-upload"></i> <?= $status === 'rejected' ? 'Re-upload' : 'Upload' ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="portal-req-note">
    <i class="bi bi-info-circle-fill"></i>
   Documents marked <strong>Under Review</strong> are awaiting registrar verification.
    Documents marked <strong>To Follow</strong> must be submitted as soon as possible.
  </div>
</div>
</body>
</html>
