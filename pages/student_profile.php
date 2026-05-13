<?php
include('../mysql/db.php');
session_start();
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
if (empty($_GET['id'])) { header('Location: students.php'); exit(); }

$id = intval($_GET['id']);
$sy_res = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1");
$active_sy = $sy_res->fetch_assoc();
$sy_id = $active_sy['id'] ?? 0;

$stmt = $conn->prepare("SELECT s.*, g.name as grade_name, sec.name as section_name, sy.label as sy_label
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  LEFT JOIN sections sec ON s.section_id = sec.id
  LEFT JOIN school_years sy ON s.school_year_id = sy.id
  WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$student) { header('Location: students.php'); exit(); }

// Handle admin document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
  $req_id = intval($_POST['requirement_id']);
  $uid    = $_SESSION['user_id'] ?? 0;
  if (!empty($_FILES['doc_file']['tmp_name'])) {
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    if (in_array($_FILES['doc_file']['type'], $allowed) && $_FILES['doc_file']['size'] <= 25*1024*1024) {
      $filename = 'req_' . $id . '_' . $req_id . '_' . uniqid() . '_' . basename($_FILES['doc_file']['name']);
      move_uploaded_file($_FILES['doc_file']['tmp_name'], "uploads/" . $filename);
      $stmt2 = $conn->prepare("INSERT INTO student_requirements (student_id, requirement_id, school_year_id, file_path, status, submitted_at, verified_by, verified_at)
        VALUES (?,?,?,?,'verified',NOW(),?,NOW())
        ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='verified', submitted_at=NOW(), verified_by=VALUES(verified_by), verified_at=NOW()");
      $stmt2->bind_param("iiisi", $id, $req_id, $sy_id, $filename, $uid);
      $stmt2->execute();
      // Audit log
      $uname = $_SESSION['name'];
      $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id, details) VALUES ($uid, '$uname', 'upload_document', 'student', $id, 'Uploaded requirement ID $req_id')");
    }
  }
  header("Location: student_profile.php?id=$id&success=Document uploaded"); exit();
}

// Handle document verify/reject from profile
if (isset($_GET['verify_doc'])) {
  $sr_id = intval($_GET['verify_doc']);
  $uid   = $_SESSION['user_id'] ?? 0;
  $conn->query("UPDATE student_requirements SET status='verified', verified_by=$uid, verified_at=NOW() WHERE id=$sr_id");
  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id) VALUES ($uid, '{$_SESSION['name']}', 'verify_document', 'student', $id)");
  header("Location: student_profile.php?id=$id&success=Document verified"); exit();
}
if (isset($_GET['reject_doc'])) {
  $sr_id = intval($_GET['reject_doc']);
  $conn->query("UPDATE student_requirements SET status='missing', file_path=NULL, submitted_at=NULL WHERE id=$sr_id");
  header("Location: student_profile.php?id=$id&success=Document rejected"); exit();
}

// Fetch requirements for this student
$reqs = $conn->query("
  SELECT r.id as req_id, r.name, r.description,
         sr.id as sr_id, sr.status, sr.file_path, sr.submitted_at
  FROM requirements r
  LEFT JOIN student_requirements sr ON sr.requirement_id=r.id AND sr.student_id=$id AND sr.school_year_id=$sy_id
  WHERE r.is_required=1 AND (r.student_type='both' OR r.student_type='{$student['student_type']}')
  ORDER BY r.sort_order
")->fetch_all(MYSQLI_ASSOC);

// Enrollment record
$enrollment = $conn->query("SELECT * FROM enrollments WHERE student_id=$id AND school_year_id=$sy_id LIMIT 1")->fetch_assoc();

// Payment summary — compute correctly using deduplicated fees
$pay_raw = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as paid FROM payments WHERE student_id=$id")->fetch_assoc();
$fees_for_bal = $conn->query("
  SELECT name, amount FROM fees
  WHERE grade_level_id = {$student['grade_level_id']} AND school_year_id = $sy_id AND fee_type != 'sped'
  ORDER BY name
")->fetch_all(MYSQLI_ASSOC);
$seen_bal = []; $total_fees_bal = 0;
foreach ($fees_for_bal as $f) {
  if (!isset($seen_bal[$f['name']])) { $seen_bal[$f['name']] = true; $total_fees_bal += $f['amount']; }
}
$pay = [
  'paid' => $pay_raw['paid'],
  'bal'  => max(0, $total_fees_bal - $pay_raw['paid']),
];

$profile_success = $_GET['success'] ?? '';
$profile_error   = $_GET['error']   ?? '';
$fullname = htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $fullname ?> — Profile</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/profile.css">
  <style>
    .tab-bar { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--color-border); }
    .tab-btn { padding:9px 18px; font-size:13px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--color-muted); border-bottom:2px solid transparent; margin-bottom:-2px; font-family:var(--font); transition:.2s; }
    .tab-btn.active { color:var(--color-primary); border-bottom-color:var(--color-primary); }
    .tab-pane { display:none; } .tab-pane.active { display:block; }
    .req-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid var(--color-border); border-radius:8px; margin-bottom:10px; background:#fff; flex-wrap:wrap; }
    .req-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .dot-verified { background:#16a34a; } .dot-submitted { background:#d97706; } .dot-missing { background:#dc2626; }
    .req-badge { padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .req-badge-verified  { background:#dcfce7; color:#166534; }
    .req-badge-submitted { background:#fef9c3; color:#92400e; }
    .req-badge-missing   { background:#fdeaea; color:#dc2626; }
    .btn-verify { padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; background:#dcfce7; color:#166534; text-decoration:none; border:none; cursor:pointer; font-family:var(--font); }
    .btn-reject { padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; background:#fdeaea; color:#dc2626; text-decoration:none; border:none; cursor:pointer; font-family:var(--font); }
    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .info-item .lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--color-muted); margin-bottom:3px; }
    .info-item .val { font-size:14px; color:var(--color-text); }
    .section-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--color-primary); margin:20px 0 12px; display:flex; align-items:center; gap:8px; }
    .section-title::after { content:''; flex:1; height:1px; background:var(--color-border); }
  </style>
</head>
<body>
<?php $active_page = 'students'; include('includes/sidebar.php'); ?>
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Student Profile</div>
      <div class="page-sub"><a href="students.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Students</a></div>
    </div>
    <div class="topbar-actions">
    </div>
  </div>

  <div id="page-container">
    <?php if ($profile_success): ?><div class="alert-success-bar"><?= htmlspecialchars($profile_success) ?></div><?php endif; ?>
    <?php if ($profile_error):   ?><div class="alert-error-bar"><?= htmlspecialchars($profile_error) ?></div><?php endif; ?>

    <div class="profile-layout">
      <!-- LEFT card -->
      <div class="profile-card">
        <div class="profile-avatar">
          <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
        </div>
        <div class="profile-name"><?= $fullname ?></div>
        <div class="profile-lrn">LRN: <?= htmlspecialchars($student['lrn']) ?></div>
        <span class="profile-badge <?= $student['student_type']==='new'?'badge-new':'badge-old' ?>">
          <?= ucfirst($student['student_type']) ?> Student
        </span>
        <?php if (!empty($student['is_sped'])): ?>
          <span class="profile-badge" style="background:#fef9c3;color:#92400e;margin-top:4px;">SPED</span>
          <?php if (!empty($student['sped_notes'])): ?>
            <div style="font-size:12px;color:var(--color-muted);margin-top:4px;"><?= htmlspecialchars($student['sped_notes']) ?></div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Quick stats -->
        <div style="margin-top:20px;display:flex;flex-direction:column;gap:10px;width:100%;">
          <div style="background:var(--color-bg);border-radius:8px;padding:12px 14px;">
            <div style="font-size:11px;color:var(--color-muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Status</div>
            <div style="font-size:14px;font-weight:600;margin-top:4px;">
              <?php if ($enrollment): ?>
                <span style="color:<?= $enrollment['status']==='enrolled'?'#16a34a':($enrollment['status']==='pending'?'#d97706':'#dc2626') ?>;">
                  <?= ucfirst($enrollment['status']) ?>
                </span>
                <div style="font-size:11px;color:var(--color-muted);margin-top:2px;"><?= htmlspecialchars($enrollment['ref_number'] ?? '') ?></div>
              <?php else: ?>
                <span style="color:var(--color-muted);">Not enrolled</span>
              <?php endif; ?>
            </div>
          </div>
          <div style="background:var(--color-bg);border-radius:8px;padding:12px 14px;">
            <div style="font-size:11px;color:var(--color-muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Balance</div>
            <div style="font-size:16px;font-weight:700;color:<?= ($pay['bal']??0)>0?'#dc2626':'#16a34a' ?>;margin-top:4px;">
              ₱<?= number_format($pay['bal']??0, 2) ?>
            </div>
          </div>
        </div>

        <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;width:100%;">
          <a href="soa.php?student_id=<?= $id ?>" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;background:var(--color-primary);color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            <i class="bi bi-receipt"></i> View SOA
          </a>
        </div>
      </div>

      <!-- RIGHT tabs -->
      <div class="profile-details">
        <div class="tab-bar">
          <button class="tab-btn active" onclick="switchTab('personal',this)">Personal Info</button>
          <button class="tab-btn" onclick="switchTab('academic',this)">Academic</button>
          <button class="tab-btn" onclick="switchTab('documents',this)">Documents</button>
        </div>

        <!-- TAB: Personal -->
        <div class="tab-pane active" id="tab-personal">
          <div class="section-title"> Basic Information</div>
          <div class="info-grid">
            <div class="info-item"><div class="lbl">First Name</div><div class="val"><?= htmlspecialchars($student['first_name']) ?></div></div>
            <div class="info-item"><div class="lbl">Middle Name</div><div class="val"><?= htmlspecialchars($student['middle_name'] ?: '—') ?></div></div>
            <div class="info-item"><div class="lbl">Last Name</div><div class="val"><?= htmlspecialchars($student['last_name']) ?></div></div>
            <div class="info-item"><div class="lbl">LRN</div><div class="val"><?= htmlspecialchars($student['lrn']) ?></div></div>
            <div class="info-item"><div class="lbl">Birthday</div><div class="val"><?= !empty($student['birthday']) ? date('F j, Y', strtotime($student['birthday'])) : '—' ?></div></div>
            <div class="info-item"><div class="lbl">Sex</div><div class="val"><?= ucfirst($student['sex'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">Religion</div><div class="val"><?= htmlspecialchars($student['religion'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">Contact Number</div><div class="val"><?= htmlspecialchars($student['contact_number'] ?? '—') ?></div></div>
          </div>

          <div class="section-title"> Address</div>
          <div class="info-grid">
            <div class="info-item"><div class="lbl">Province</div><div class="val"><?= htmlspecialchars($student['province'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">City / Municipality</div><div class="val"><?= htmlspecialchars($student['city_municipality'] ?? $student['city'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">Barangay</div><div class="val"><?= htmlspecialchars($student['barangay'] ?? '—') ?></div></div>
          </div>
        </div>

        <!-- TAB: Academic -->
        <div class="tab-pane" id="tab-academic">
          <div class="section-title"> Current Enrollment</div>
          <div class="info-grid">
            <div class="info-item"><div class="lbl">Grade Level</div><div class="val"><?= htmlspecialchars($student['grade_name'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">Section</div><div class="val"><?= htmlspecialchars($student['section_name'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">School Year</div><div class="val"><?= htmlspecialchars($student['sy_label'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">Student Type</div><div class="val"><?= ucfirst($student['student_type']) ?></div></div>
          </div>

          <div class="section-title"> Education History</div>
          <div class="info-grid">
            <div class="info-item"><div class="lbl">Last School Attended</div><div class="val"><?= htmlspecialchars($student['last_school'] ?? '—') ?></div></div>
            <div class="info-item"><div class="lbl">School Year Graduated</div><div class="val"><?= htmlspecialchars($student['school_year_graduated'] ?? '—') ?></div></div>
            <div class="info-item" style="grid-column:1/-1;"><div class="lbl">School Address</div><div class="val"><?= htmlspecialchars($student['school_address'] ?? '—') ?></div></div>
          </div>
        </div>

        <!-- TAB: Documents -->
        <div class="tab-pane" id="tab-documents">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div style="font-size:14px;font-weight:600;">Required Documents — SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></div>
          </div>
          <?php foreach ($reqs as $req):
            $status = $req['status'] ?? 'missing';
          ?>
          <div class="req-item">
            <div style="display:flex;align-items:flex-start;gap:10px;flex:1;">
              <div class="req-dot dot-<?= $status ?>" style="margin-top:5px;"></div>
              <div>
                <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($req['name']) ?></div>
                <?php if ($req['description']): ?><div style="font-size:12px;color:var(--color-muted);"><?= htmlspecialchars($req['description']) ?></div><?php endif; ?>
                <?php if ($req['submitted_at']): ?><div style="font-size:11px;color:var(--color-muted);margin-top:2px;">Submitted: <?= date('M j, Y', strtotime($req['submitted_at'])) ?></div><?php endif; ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <span class="req-badge req-badge-<?= $status ?>"><?= ucfirst($status) ?></span>
              <?php if (!empty($req['file_path'])): ?>
                <a href="uploads/<?= htmlspecialchars($req['file_path']) ?>" target="_blank" class="btn-view-sm"><i class="bi bi-eye-fill"></i> View</a>
              <?php endif; ?>
              <?php if ($status === 'submitted'): ?>
                <a href="student_profile.php?id=<?= $id ?>&verify_doc=<?= $req['sr_id'] ?>" class="btn-verify" onclick="return confirm('Mark as verified?')"><i class="bi bi-check-lg"></i> Verify</a>
                <a href="student_profile.php?id=<?= $id ?>&reject_doc=<?= $req['sr_id'] ?>" class="btn-reject" onclick="return confirm('Reject?')"><i class="bi bi-x-lg"></i> Reject</a>
              <?php elseif ($status === 'verified'): ?>
                <a href="student_profile.php?id=<?= $id ?>&reject_doc=<?= $req['sr_id'] ?>" class="btn-reject" style="font-size:11px;padding:3px 10px;" onclick="return confirm('Remove verification?')"><i class="bi bi-arrow-counterclockwise"></i> Undo</a>
              <?php endif; ?>
              <!-- Admin upload -->
              <?php if ($status !== 'verified'): ?>
              <form method="POST" action="student_profile.php?id=<?= $id ?>" enctype="multipart/form-data" style="display:inline-flex;align-items:center;gap:6px;">
                <input type="hidden" name="action" value="upload_doc">
                <input type="hidden" name="requirement_id" value="<?= $req['req_id'] ?>">
                <input type="file" name="doc_file" accept="image/*,.pdf" style="font-size:11px;max-width:140px;border:1px solid var(--color-border);border-radius:4px;padding:3px 6px;" required/>
                <button type="submit" style="padding:5px 10px;background:var(--color-primary);color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;font-family:var(--font);">
                  <i class="bi bi-upload"></i> Upload
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($reqs)): ?>
            <div style="text-align:center;padding:32px;color:var(--color-muted);">No requirements defined for this student type.</div>
          <?php endif; ?>
        </div>

      </div><!-- end profile-details -->
    </div><!-- end profile-layout -->
  </div>
</div>

<script src="../js/nav.js"></script>
<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}
// Auto-open tab from URL param
(function() {
  const tab = new URLSearchParams(window.location.search).get('tab');
  if (tab) {
    const btn = document.querySelector(`.tab-btn[onclick*="'${tab}'"]`);
    if (btn) switchTab(tab, btn);
  }
})();
</script>
</body>
</html>
