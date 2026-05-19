<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

// ── Update enrollment status ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
  $enroll_id = intval($_POST['enroll_id']);
  $status    = $_POST['status'];
  if (in_array($status, ['pending','enrolled','dropped'])) {
    $stmt = $conn->prepare("UPDATE enrollments SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $enroll_id);
    $stmt->execute();
    $enroll = $conn->query("SELECT * FROM enrollments WHERE id=$enroll_id")->fetch_assoc();
    if ($status === 'enrolled' && $enroll) {
      $sid = $enroll['student_id']; $sy_id = $enroll['school_year_id']; $gl_id = $enroll['grade_level_id'];
      $fees = $conn->query("SELECT * FROM fees WHERE grade_level_id=$gl_id AND school_year_id=$sy_id");
      while ($fee = $fees->fetch_assoc())
        $conn->query("INSERT IGNORE INTO payments (student_id,fee_id,amount_paid,balance,status) VALUES ($sid,{$fee['id']},0,{$fee['amount']},'unpaid')");
      $student_name = $conn->query("SELECT CONCAT(first_name,' ',last_name) as n FROM students WHERE id=$sid")->fetch_assoc()['n'] ?? 'Student';
      $admins = $conn->query("SELECT id FROM users WHERE is_active=1");
      while ($a = $admins->fetch_assoc())
        $conn->query("INSERT INTO notifications (user_id,type,title,body,link) VALUES ({$a['id']},'success','Student Enrolled: $student_name','Fees auto-assigned.','student_profile.php?id=$sid')");
      $parent = $conn->query("SELECT pa.id,pa.email,pa.name FROM parent_accounts pa JOIN parent_student_links psl ON psl.parent_id=pa.id WHERE psl.student_id=$sid LIMIT 1")->fetch_assoc();
      if ($parent) {
        $sy_label   = $conn->query("SELECT label FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc()['label'] ?? '';
        $grade_name = $conn->query("SELECT g.name FROM students s LEFT JOIN grade_levels g ON g.id=s.grade_level_id WHERE s.id=$sid")->fetch_assoc()['name'] ?? '';
        $p_id = $parent['id'];
        $pt = $conn->real_escape_string("Enrollment Confirmed");
        $pb = $conn->real_escape_string("$student_name is now enrolled for $grade_name — SY $sy_label.");
        $conn->query("INSERT INTO parent_notifications (parent_id,student_id,type,title,body) VALUES ($p_id,$sid,'success','$pt','$pb')");
        require_once '../mysql/email_notifications.php';
        notifyEnrollmentConfirmed($parent['email'], $parent['name'], $student_name, $grade_name, $sy_label);
      }
    }
    if ($status === 'dropped' && $enroll) {
      $sid = $enroll['student_id'];
      $sn  = $conn->query("SELECT CONCAT(first_name,' ',last_name) as n FROM students WHERE id=$sid")->fetch_assoc()['n'] ?? 'Student';
      $admins = $conn->query("SELECT id FROM users WHERE is_active=1");
      while ($a = $admins->fetch_assoc())
        $conn->query("INSERT INTO notifications (user_id,type,title,body) VALUES ({$a['id']},'warning','Enrollment Dropped: $sn','Status changed to dropped.')");
    }
    $uid = $_SESSION['user_id'] ?? 0; $uname = $conn->real_escape_string($_SESSION['name'] ?? '');
    $conn->query("INSERT INTO audit_log (user_id,user_name,action,target,target_id,details) VALUES ($uid,'$uname','update_enrollment_status','enrollment',$enroll_id,'Status changed to $status')");
  }
  header("Location: enrollment.php?success=Status updated"); exit();
}

// ── New enrollment ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enroll') {
  $_SESSION['enroll_form'] = $_POST;
  $student_type   = trim($_POST['student_type'] ?? 'new');
  $school_year_id = intval($_POST['school_year_id']);
  $grade_level_id = intval($_POST['grade_level_id']);

  if ($student_type === 'returning') {
    $lrn = trim($_POST['lrn'] ?? '');
    if (empty($lrn)) { header("Location: enrollment.php?error=".urlencode("LRN is required for returning students.")."&open_enroll=1"); exit(); }
    $row = $conn->query("SELECT id FROM students WHERE lrn='".$conn->real_escape_string($lrn)."' AND is_archived=0 LIMIT 1")->fetch_assoc();
    if (!$row) { header("Location: enrollment.php?error=".urlencode("No student found with LRN: $lrn")."&open_enroll=1"); exit(); }
    $student_id = $row['id'];
    if ($conn->query("SELECT id FROM enrollments WHERE student_id=$student_id AND school_year_id=$school_year_id LIMIT 1")->fetch_assoc())
      { header("Location: enrollment.php?error=".urlencode("Student is already enrolled for this school year.")."&open_enroll=1"); exit(); }
  } else {
    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $lrn         = trim($_POST['lrn']         ?? '');
    $birthday    = trim($_POST['birthday']    ?? '') ?: null;
    $sex         = trim($_POST['sex']         ?? '') ?: null;
    $religion    = trim($_POST['religion']    ?? '') ?: null;
    $last_school = trim($_POST['last_school'] ?? '');
    $sy_grad     = trim($_POST['school_year_graduated'] ?? '');
    $contact     = trim($_POST['contact_number'] ?? '');
    $province    = trim($_POST['province']    ?? '');
    $city_mun    = trim($_POST['city_municipality'] ?? '');
    $barangay    = trim($_POST['barangay']    ?? '');
    $p_first  = trim($_POST['p_first_name']  ?? '');
    $p_middle = trim($_POST['p_middle_name'] ?? '');
    $p_last   = trim($_POST['p_last_name']   ?? '');
    $p_mobile = trim($_POST['p_mobile']      ?? '');
    $p_email  = trim($_POST['p_email']       ?? '');
    $p_pass   = trim($_POST['p_password']    ?? '');

    $np = '/^[a-zA-ZÀ-ÿ\s\-\.]+$/u';
    if (empty($first_name)||empty($last_name)||empty($grade_level_id))
      { header("Location: enrollment.php?error=".urlencode("First name, last name, and grade level are required.")."&open_enroll=1"); exit(); }
    if (!preg_match($np,$first_name)||!preg_match($np,$last_name))
      { header("Location: enrollment.php?error=".urlencode("Names must contain letters only.")."&open_enroll=1"); exit(); }
    if (!empty($lrn)&&!preg_match('/^\d{12}$/',$lrn))
      { header("Location: enrollment.php?error=".urlencode("LRN must be exactly 12 digits.")."&open_enroll=1"); exit(); }
    if (!empty($lrn)) {
      $chk=$conn->prepare("SELECT id FROM students WHERE lrn=?"); $chk->bind_param("s",$lrn); $chk->execute();
      if ($chk->get_result()->num_rows>0) { header("Location: enrollment.php?error=".urlencode("LRN already exists.")."&open_enroll=1"); exit(); }
    }
    $stmt = $conn->prepare("INSERT INTO students (first_name,middle_name,last_name,lrn,grade_level_id,school_year_id,birthday,sex,religion,last_school,school_year_graduated,contact_number,province,city_municipality,barangay,student_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssiissssssssss",$first_name,$middle_name,$last_name,$lrn,$grade_level_id,$school_year_id,$birthday,$sex,$religion,$last_school,$sy_grad,$contact,$province,$city_mun,$barangay,$student_type);
    if (!$stmt->execute()) { header("Location: enrollment.php?error=".urlencode("Failed to create student: ".$stmt->error)."&open_enroll=1"); exit(); }
    $student_id = $conn->insert_id;
    if (!empty($p_email) && !empty($p_pass) && !empty($p_first) && !empty($p_last)) {
      $p_name = trim("$p_first $p_middle $p_last");
      $p_hash = password_hash($p_pass, PASSWORD_DEFAULT);
      $existing_pa = $conn->query("SELECT id FROM parent_accounts WHERE email='".$conn->real_escape_string($p_email)."' LIMIT 1")->fetch_assoc();
      if ($existing_pa) { $pa_id = $existing_pa['id']; }
      else {
        $ps = $conn->prepare("INSERT INTO parent_accounts (student_id,name,email,password,contact) VALUES (?,?,?,?,?)");
        $ps->bind_param("issss",$student_id,$p_name,$p_email,$p_hash,$p_mobile); $ps->execute();
        $pa_id = $conn->insert_id;
      }
      $conn->query("INSERT IGNORE INTO parent_student_links (parent_id,student_id) VALUES ($pa_id,$student_id)");
    }
  }
  $year    = date('Y');
  $count   = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE YEAR(enrolled_at)=$year")->fetch_assoc()['c'] + 1;
  $ref_num = 'ENR-'.$year.'-'.str_pad($count,4,'0',STR_PAD_LEFT);
  $stmt2   = $conn->prepare("INSERT INTO enrollments (ref_number,student_id,school_year_id,grade_level_id,status) VALUES (?,?,?,?,'pending') ON DUPLICATE KEY UPDATE grade_level_id=VALUES(grade_level_id),status='pending'");
  $stmt2->bind_param("siii",$ref_num,$student_id,$school_year_id,$grade_level_id);
  if ($stmt2->execute()) {
    unset($_SESSION['enroll_form']);
    $uid=$_SESSION['user_id']??0; $uname=$conn->real_escape_string($_SESSION['name']??'');
    $conn->query("INSERT INTO audit_log (user_id,user_name,action,target,target_id,details) VALUES ($uid,'$uname','enroll_student','enrollment',$student_id,'Walk-in ref $ref_num')");
    header("Location: enrollment.php?success=".urlencode("Student enrolled successfully (Ref: $ref_num)"));
  } else { header("Location: enrollment.php?error=".urlencode($conn->error)."&open_enroll=1"); }
  exit();
}

// ── Page data ─────────────────────────────────────────────────────────────────
$active_sy     = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id         = $active_sy['id'] ?? 0;
$filter_status = in_array($_GET['status']??'',['pending','enrolled','dropped']) ? $_GET['status'] : '';
$search        = trim($_GET['search'] ?? '');
$sy_list       = $conn->query("SELECT * FROM school_years ORDER BY label DESC")->fetch_all(MYSQLI_ASSOC);
$grade_list    = $conn->query("SELECT * FROM grade_levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$base_sql   = "SELECT e.id,e.ref_number,e.status,e.enrolled_at,s.first_name,s.last_name,s.lrn,s.photo,s.id as student_id,g.name as grade FROM enrollments e JOIN students s ON e.student_id=s.id LEFT JOIN grade_levels g ON e.grade_level_id=g.id LEFT JOIN sections sec ON e.section_id=sec.id WHERE e.school_year_id=?";
$bind_types = "i"; $bind_vals = [$sy_id];
if ($filter_status) { $base_sql.=" AND e.status=?"; $bind_types.="s"; $bind_vals[]=$filter_status; }
if ($search!=='') { $sp="%$search%"; $base_sql.=" AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ? OR e.ref_number LIKE ?)"; $bind_types.="ssss"; $bind_vals[]=$sp;$bind_vals[]=$sp;$bind_vals[]=$sp;$bind_vals[]=$sp; }
$base_sql .= " ORDER BY e.enrolled_at DESC";
$es = $conn->prepare($base_sql); $es->bind_param($bind_types,...$bind_vals); $es->execute();
$enrollments = $es->get_result();

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$open_enroll     = !empty($_GET['open_enroll']);
$ef = $_SESSION['enroll_form'] ?? [];
if (isset($_SESSION['enroll_form']) && !$open_enroll) unset($_SESSION['enroll_form']);
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
  <style>
    /* ── Enrollment modal — fully self-contained ── */
    #enroll-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    #enroll-modal.open { display: flex; }
    #enroll-box {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 40px rgba(0,0,0,.22);
      width: calc(100% - 32px);
      max-width: 700px;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
      overflow-x: hidden;
    }
    /* Progress timeline */
    .em-step { display:flex;flex-direction:column;align-items:center;text-align:center;flex:1;min-width:0; }
    .em-dot { width:40px;height:40px;border-radius:50%;background:#e5e7eb;color:#9ca3af;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:6px;transition:.25s; }
    .em-lbl { font-size:11px;font-weight:700;color:#9ca3af;white-space:nowrap;transition:.25s; }
    .em-step.active .em-dot { background:var(--color-primary);color:#fff;box-shadow:0 0 0 4px rgba(73,76,138,.15); }
    .em-step.active .em-lbl { color:var(--color-primary); }
    .em-step.done .em-dot { background:#16a34a;color:#fff; }
    .em-step.done .em-lbl { color:#16a34a; }
    .em-line { flex:1;height:2px;background:#e5e7eb;margin:20px 4px 0;transition:.25s; }
    .em-line.done { background:#16a34a; }
    /* Form helpers */
    .ef-type-btn { display:inline-flex;align-items:center;gap:8px;padding:9px 22px;border-radius:999px;border:2px solid var(--color-border);font-size:13px;font-weight:600;cursor:pointer;color:var(--color-muted);background:#fff;transition:.15s;user-select:none; }
    .ef-type-btn.active { border-color:var(--color-primary);background:var(--color-primary-bg);color:var(--color-primary); }
    .ef-lbl { font-size:11px;font-weight:700;color:var(--color-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;display:block; }
    .ef-g3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px; }
    .ef-g2 { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
    .ef-g1 { display:grid;grid-template-columns:1fr;gap:14px; }
    .ef-span2 { grid-column:span 2; }
    .ef-span3 { grid-column:1/-1; }
    .ef-panel { padding:20px 24px; }
    .ef-footer { display:flex;justify-content:space-between;margin-top:20px; }
    .ef-footer-end { justify-content:flex-end; }
    .ef-note { background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:14px; }
    .ef-portal-note { background:#f0f1f8;border-radius:8px;padding:12px 16px;font-size:13px;font-weight:600;color:var(--color-primary);margin:14px 0 12px; }
    .ef-pw-wrap { position:relative; }
    .ef-pw-wrap input { padding-right:38px; }
    .ef-pw-btn { position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-muted);font-size:15px; }
  </style>
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
    <div class="enroll-toolbar">
      <div class="enroll-tabs">
        <a href="enrollment.php<?= $search?'?search='.urlencode($search):'' ?>" class="enroll-tab <?= !$filter_status?'active':'' ?>">All</a>
        <a href="enrollment.php?status=pending<?= $search?'&search='.urlencode($search):'' ?>"  class="enroll-tab <?= $filter_status==='pending' ?'active':'' ?>">Pending</a>
        <a href="enrollment.php?status=enrolled<?= $search?'&search='.urlencode($search):'' ?>" class="enroll-tab <?= $filter_status==='enrolled'?'active':'' ?>">Enrolled</a>
        <a href="enrollment.php?status=dropped<?= $search?'&search='.urlencode($search):'' ?>"  class="enroll-tab <?= $filter_status==='dropped' ?'active':'' ?>">Dropped</a>
      </div>
      <form method="GET" action="enrollment.php" class="enroll-search-form">
        <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"/><?php endif; ?>
        <div class="search-wrap"><i class="bi bi-search search-icon"></i>
          <input type="search" name="search" class="toolbar-search-input" placeholder="Search name, LRN, or ref #" value="<?= htmlspecialchars($search) ?>"/>
        </div>
        <button type="submit" class="btn-search">Search</button>
        <?php if ($search): ?><a href="enrollment.php<?= $filter_status?'?status='.$filter_status:'' ?>" class="btn-clear-filters"><i class="bi bi-x-circle"></i> Clear</a><?php endif; ?>
      </form>
    </div>
    <div class="enroll-table-card">
      <table class="enroll-list-table">
        <thead><tr><th>Ref #</th><th>Student</th><th>LRN</th><th>Grade</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
          <?php $cnt=0; while ($e=$enrollments->fetch_assoc()): $cnt++; ?>
          <tr>
            <td class="td-muted" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($e['ref_number']??'—') ?></td>
            <td><div class="name-cell">
              <?php if (!empty($e['photo'])): ?><img src="uploads/<?= htmlspecialchars($e['photo']) ?>" class="mini-pic"/>
              <?php else: ?><div class="mini-avatar"><i class="bi bi-person-fill"></i></div><?php endif; ?>
              <span><?= htmlspecialchars($e['last_name'].', '.$e['first_name']) ?></span>
            </div></td>
            <td class="td-muted"><?= htmlspecialchars($e['lrn']) ?></td>
            <td><?= htmlspecialchars($e['grade']??'—') ?></td>
            <td><span class="enroll-status-badge status-<?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
            <td class="td-muted"><?= date('M j, Y',strtotime($e['enrolled_at'])) ?></td>
            <td>
              <form method="POST" action="enrollment.php" style="display:inline-flex;gap:4px;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="enroll_id" value="<?= $e['id'] ?>">
                <select name="status" class="status-select" onchange="if(confirm('Change status to '+this.options[this.selectedIndex].text+'?')){this.form.submit();}else{this.value='<?= $e['status'] ?>';}">
                  <option value="pending"  <?= $e['status']==='pending' ?'selected':'' ?>>Pending</option>
                  <option value="enrolled" <?= $e['status']==='enrolled'?'selected':'' ?>>Enrolled</option>
                  <option value="dropped"  <?= $e['status']==='dropped' ?'selected':'' ?>>Dropped</option>
                </select>
              </form>
              <a href="student_profile.php?id=<?= $e['student_id'] ?>" class="btn-view-sm">View</a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($cnt===0): ?><tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">No enrollment records found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ ENROLLMENT MODAL ═══════════════ -->
<div id="enroll-modal" <?= $open_enroll?'class="open"':'' ?>>
<div id="enroll-box">

  <!-- Sticky header -->
  <div style="background:var(--color-primary);color:#fff;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;border-radius:12px 12px 0 0;position:sticky;top:0;z-index:10;">
    <span style="font-size:15px;font-weight:700;"><i class="bi bi-person-plus-fill" style="margin-right:6px;"></i>Enroll Student</span>
    <button id="modal-close" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
  </div>

  <!-- Progress timeline -->
  <div style="padding:16px 24px 0;background:#fff;">
    <div style="display:flex;align-items:flex-start;">
      <div class="em-step active" id="em-s1"><div class="em-dot"><i class="bi bi-person-fill"></i></div><div class="em-lbl">Student</div></div>
      <div class="em-line" id="em-l1"></div>
      <div class="em-step" id="em-s2"><div class="em-dot"><i class="bi bi-book-fill"></i></div><div class="em-lbl">Education</div></div>
      <div class="em-line" id="em-l2"></div>
      <div class="em-step" id="em-s3"><div class="em-dot"><i class="bi bi-house-fill"></i></div><div class="em-lbl">Address</div></div>
      <div class="em-line" id="em-l3"></div>
      <div class="em-step" id="em-s4"><div class="em-dot"><i class="bi bi-people-fill"></i></div><div class="em-lbl">Parent</div></div>
      <div class="em-line" id="em-l4"></div>
      <div class="em-step" id="em-s5"><div class="em-dot"><i class="bi bi-journal-check"></i></div><div class="em-lbl">Confirm</div></div>
    </div>
  </div>

  <form method="POST" action="enrollment.php" id="enroll-form">
    <input type="hidden" name="action" value="enroll">

    <!-- PANEL 1: Student Info -->
    <div class="ef-panel" id="em-p1">
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
        <label class="ef-type-btn <?= ($ef['student_type']??'new')==='new'?'active':'' ?>" id="lbl-new">
          <input type="radio" name="student_type" value="new" id="type-new" <?= ($ef['student_type']??'new')==='new'?'checked':'' ?> style="display:none;">
          <i class="bi bi-person-plus"></i> New Student
        </label>
        <label class="ef-type-btn <?= ($ef['student_type']??'')==='returning'?'active':'' ?>" id="lbl-ret">
          <input type="radio" name="student_type" value="returning" id="type-ret" <?= ($ef['student_type']??'')==='returning'?'checked':'' ?> style="display:none;">
          <i class="bi bi-arrow-return-left"></i> Returning Student
        </label>
      </div>
      <div id="ret-note" class="ef-note" style="display:<?= ($ef['student_type']??'')==='returning'?'block':'none' ?>;">
        <i class="bi bi-info-circle-fill"></i> Enter the student's 12-digit LRN to look up their existing record.
      </div>
      <div class="ef-g3">
        <div class="new-only"><label class="ef-lbl">First Name *</label><input type="text" name="first_name" class="form-input" placeholder="First name" value="<?= htmlspecialchars($ef['first_name']??'') ?>"></div>
        <div class="new-only"><label class="ef-lbl">Middle Name</label><input type="text" name="middle_name" class="form-input" placeholder="N/A if none" value="<?= htmlspecialchars($ef['middle_name']??'') ?>"></div>
        <div class="new-only"><label class="ef-lbl">Last Name *</label><input type="text" name="last_name" class="form-input" placeholder="Last name" value="<?= htmlspecialchars($ef['last_name']??'') ?>"></div>
        <div><label class="ef-lbl">Grade Level *</label>
          <select name="grade_level_id" id="enroll-grade" class="form-input" required>
            <option value="">Select Grade</option>
            <?php foreach ($grade_list as $g): ?><option value="<?= $g['id'] ?>" <?= ($ef['grade_level_id']??'')==$g['id']?'selected':'' ?>><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="new-only"><label class="ef-lbl">Sex</label>
          <select name="sex" class="form-input"><option value="">Select</option><option value="male" <?= ($ef['sex']??'')==='male'?'selected':'' ?>>Male</option><option value="female" <?= ($ef['sex']??'')==='female'?'selected':'' ?>>Female</option></select>
        </div>
        <div class="new-only"><label class="ef-lbl">Religion</label>
          <select name="religion" class="form-input"><option value="">Select Religion</option><?php foreach (['Roman Catholic','Islam','Iglesia ni Cristo','Born Again Christian','Seventh Day Adventist','Other'] as $rel): ?><option value="<?= $rel ?>" <?= ($ef['religion']??'')===$rel?'selected':'' ?>><?= $rel ?></option><?php endforeach; ?></select>
        </div>
        <div class="new-only"><label class="ef-lbl">Date of Birth</label><input type="date" name="birthday" class="form-input" value="<?= htmlspecialchars($ef['birthday']??'') ?>"></div>
        <div class="new-only ef-span2"><label class="ef-lbl">Place of Birth</label><input type="text" name="place_birth" class="form-input" placeholder="City / Municipality" value="<?= htmlspecialchars($ef['place_birth']??'') ?>"></div>
        <div class="ef-span3"><label class="ef-lbl">LRN (Learner Reference Number)</label>
          <input type="text" name="lrn" id="enroll-lrn" class="form-input" placeholder="12-digit LRN" maxlength="12" value="<?= htmlspecialchars($ef['lrn']??'') ?>">
          <div style="font-size:11px;color:var(--color-muted);margin-top:3px;" id="lrn-hint">Optional for new students.</div>
        </div>
      </div>
      <div class="ef-footer ef-footer-end"><button type="button" class="btn-save" onclick="emNext(1)">Next <i class="bi bi-arrow-right"></i></button></div>
    </div>

    <!-- PANEL 2: Education -->
    <div class="ef-panel" id="em-p2" style="display:none;">
      <div class="ef-g1">
        <div><label class="ef-lbl">School Last Attended</label><input type="text" name="last_school" class="form-input" placeholder="Name of previous school" value="<?= htmlspecialchars($ef['last_school']??'') ?>"></div>
        <div><label class="ef-lbl">School Address</label><input type="text" name="school_address" class="form-input" placeholder="School address" value="<?= htmlspecialchars($ef['school_address']??'') ?>"></div>
      </div>
      <div class="ef-g2" style="margin-top:14px;">
        <div><label class="ef-lbl">School Year Last Attended</label><input type="text" name="school_year_graduated" class="form-input" placeholder="e.g. 2023-2024" value="<?= htmlspecialchars($ef['school_year_graduated']??'') ?>"></div>
        <div><label class="ef-lbl">Contact Number</label><input type="text" name="contact_number" class="form-input" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($ef['contact_number']??'') ?>"></div>
      </div>
      <div class="ef-footer">
        <button type="button" class="btn-cancel" onclick="emGo(1)"><i class="bi bi-arrow-left"></i> Back</button>
        <button type="button" class="btn-save" onclick="emNext(2)">Next <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- PANEL 3: Address -->
    <div class="ef-panel" id="em-p3" style="display:none;">
      <div class="ef-g3">
        <div><label class="ef-lbl">Province</label><input type="text" name="province" class="form-input" placeholder="Province" value="<?= htmlspecialchars($ef['province']??'') ?>"></div>
        <div><label class="ef-lbl">City / Municipality</label><input type="text" name="city_municipality" class="form-input" placeholder="City / Municipality" value="<?= htmlspecialchars($ef['city_municipality']??'') ?>"></div>
        <div><label class="ef-lbl">Barangay</label><input type="text" name="barangay" class="form-input" placeholder="Barangay" value="<?= htmlspecialchars($ef['barangay']??'') ?>"></div>
      </div>
      <div class="ef-footer">
        <button type="button" class="btn-cancel" onclick="emGo(2)"><i class="bi bi-arrow-left"></i> Back</button>
        <button type="button" class="btn-save" onclick="emNext(3)">Next <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- PANEL 4: Parent/Guardian -->
    <div class="ef-panel" id="em-p4" style="display:none;">
      <div class="ef-g3">
        <div><label class="ef-lbl">Guardian First Name</label><input type="text" name="p_first_name" class="form-input" placeholder="First name" value="<?= htmlspecialchars($ef['p_first_name']??'') ?>"></div>
        <div><label class="ef-lbl">Middle Name</label><input type="text" name="p_middle_name" class="form-input" placeholder="Middle name" value="<?= htmlspecialchars($ef['p_middle_name']??'') ?>"></div>
        <div><label class="ef-lbl">Last Name</label><input type="text" name="p_last_name" class="form-input" placeholder="Last name" value="<?= htmlspecialchars($ef['p_last_name']??'') ?>"></div>
        <div><label class="ef-lbl">Mobile Number (09XXXXXXXXX)</label><input type="text" name="p_mobile" class="form-input" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($ef['p_mobile']??'') ?>"></div>
        <div><label class="ef-lbl">Email Address</label><input type="email" name="p_email" class="form-input" placeholder="parent@email.com" value="<?= htmlspecialchars($ef['p_email']??'') ?>"></div>
        <div><label class="ef-lbl">Alternate Contact No.</label><input type="text" name="p_alt_contact" class="form-input" placeholder="Optional" value="<?= htmlspecialchars($ef['p_alt_contact']??'') ?>"></div>
      </div>
      <div class="ef-portal-note"><i class="bi bi-shield-lock-fill"></i> Create your Parent Portal password.</div>
      <div class="ef-g2">
        <div><label class="ef-lbl">Portal Password <span style="color:#dc2626;">(MIN. 8 CHARACTERS)</span></label>
          <div class="ef-pw-wrap"><input type="password" name="p_password" id="p_password" class="form-input" placeholder="Min. 8 characters">
          <button type="button" class="ef-pw-btn" onclick="togglePass('p_password','eye1')"><i class="bi bi-eye" id="eye1"></i></button></div>
        </div>
        <div><label class="ef-lbl">Confirm Password</label>
          <div class="ef-pw-wrap"><input type="password" name="p_confirm" id="p_confirm" class="form-input" placeholder="Re-enter password">
          <button type="button" class="ef-pw-btn" onclick="togglePass('p_confirm','eye2')"><i class="bi bi-eye" id="eye2"></i></button></div>
        </div>
      </div>
      <div style="font-size:11px;color:var(--color-muted);margin-top:4px;">Leave blank to skip parent portal account creation.</div>
      <div class="ef-footer">
        <button type="button" class="btn-cancel" onclick="emGo(3)"><i class="bi bi-arrow-left"></i> Back</button>
        <button type="button" class="btn-save" onclick="emNext(4)">Next <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- PANEL 5: Confirm Enrollment -->
    <div class="ef-panel" id="em-p5" style="display:none;">
      <div class="ef-g2">
        <div><label class="ef-lbl">School Year *</label>
          <select name="school_year_id" class="form-input" required>
            <?php foreach ($sy_list as $sy): ?><option value="<?= $sy['id'] ?>" <?= ($ef['school_year_id']??($active_sy['id']??''))==$sy['id']?'selected':'' ?>>SY <?= htmlspecialchars($sy['label']) ?><?= $sy['is_active']?' (Active)':'' ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="ef-lbl">Initial Status</label><input type="text" class="form-input" value="Pending" disabled style="background:#f3f4f6;color:var(--color-muted);"></div>
      </div>
      <div class="ef-note" style="margin-top:14px;">
        <i class="bi bi-info-circle-fill"></i> Enrollment will be set to <strong>Pending</strong>. Change it to <strong>Enrolled</strong> from the list after review.
      </div>
      <div class="ef-footer">
        <button type="button" class="btn-cancel" onclick="emGo(4)"><i class="bi bi-arrow-left"></i> Back</button>
        <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Confirm Enrollment</button>
      </div>
    </div>

  </form>
</div><!-- #enroll-box -->
</div><!-- #enroll-modal -->

<script src="../js/nav.js"></script>
<script>
const modal = document.getElementById('enroll-modal');
document.getElementById('btn-enroll').onclick  = () => { emGo(1); modal.classList.add('open'); };
document.getElementById('modal-close').onclick  = () => modal.classList.remove('open');
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });

const typeNew = document.getElementById('type-new');
const typeRet = document.getElementById('type-ret');
const lrnIn   = document.getElementById('enroll-lrn');
const lrnHint = document.getElementById('lrn-hint');

function applyType(t) {
  const isNew = t === 'new';
  document.getElementById('lbl-new').classList.toggle('active', isNew);
  document.getElementById('lbl-ret').classList.toggle('active', !isNew);
  document.getElementById('ret-note').style.display = isNew ? 'none' : 'block';
  lrnIn.required = !isNew;
  lrnHint.textContent = isNew ? 'Optional for new students.' : 'Required — used to look up the existing student record.';
  document.querySelectorAll('.new-only').forEach(el => el.style.display = isNew ? '' : 'none');
}
typeNew.addEventListener('change', () => applyType('new'));
typeRet.addEventListener('change', () => applyType('returning'));
applyType(typeNew.checked ? 'new' : 'returning');

lrnIn.addEventListener('input', function() { this.value = this.value.replace(/\D/g,'').slice(0,12); });

function togglePass(id, iconId) {
  const inp = document.getElementById(id);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}

let curPanel = 1;
function emGo(n) {
  for (let i = 1; i <= 5; i++) {
    const p = document.getElementById('em-p' + i);
    if (p) p.style.display = i === n ? 'block' : 'none';
    const s = document.getElementById('em-s' + i);
    if (s) { s.classList.remove('active','done'); if (i === n) s.classList.add('active'); else if (i < n) s.classList.add('done'); }
    const l = document.getElementById('em-l' + i);
    if (l) l.classList.toggle('done', i < n);
  }
  curPanel = n;
  document.getElementById('enroll-box').scrollTop = 0;
}

function emNext(from) {
  const type = document.querySelector('input[name="student_type"]:checked')?.value;
  if (from === 1) {
    const grade = document.getElementById('enroll-grade');
    if (!grade.value) { grade.style.borderColor='#dc2626'; grade.focus(); return; }
    grade.style.borderColor = '';
    if (type === 'returning') {
      if (lrnIn.value.length !== 12) { lrnIn.style.borderColor='#dc2626'; lrnIn.focus(); return; }
      lrnIn.style.borderColor = '';
      emGo(5); return;
    }
    const fn = document.querySelector('input[name="first_name"]');
    const ln = document.querySelector('input[name="last_name"]');
    if (!fn.value.trim()) { fn.style.borderColor='#dc2626'; fn.focus(); return; }
    if (!ln.value.trim()) { ln.style.borderColor='#dc2626'; ln.focus(); return; }
    fn.style.borderColor = ln.style.borderColor = '';
  }
  if (from === 4) {
    const pw = document.getElementById('p_password');
    const pc = document.getElementById('p_confirm');
    if (pw.value && pw.value.length < 8) { pw.style.borderColor='#dc2626'; pw.focus(); return; }
    if (pw.value && pw.value !== pc.value) { pc.style.borderColor='#dc2626'; alert('Passwords do not match.'); return; }
    pw.style.borderColor = pc.style.borderColor = '';
  }
  emGo(from + 1);
}

emGo(<?= ($ef && ($ef['student_type']??'new')==='returning') ? 5 : 1 ?>);
</script>
</body>
</html>
