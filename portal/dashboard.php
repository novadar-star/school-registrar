<?php
$active_portal = 'dashboard';
require_once 'includes/auth.php';
require_once '../mysql/helpers.php';

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

// Support multi-child: get all linked students
$linked_students = $conn->query("
  SELECT s.id, s.first_name, s.last_name, s.lrn, s.photo, s.student_type,
         g.name as grade, sec.name as section
  FROM parent_student_links psl
  JOIN students s ON s.id = psl.student_id
  LEFT JOIN grade_levels g ON g.id = s.grade_level_id
  LEFT JOIN sections sec ON sec.id = s.section_id
  WHERE psl.parent_id = $parent_id
  ORDER BY s.last_name
")->fetch_all(MYSQLI_ASSOC);

// Default to first linked student if session student_id not in links
$valid_ids = array_column($linked_students, 'id');
if (!in_array($student_id, $valid_ids) && !empty($valid_ids)) {
  $student_id = $valid_ids[0];
  $_SESSION['student_id'] = $student_id;
}

$student = $conn->query("
  SELECT s.*, g.name as grade, sec.name as section
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  LEFT JOIN sections sec ON s.section_id = sec.id
  WHERE s.id = $student_id
")->fetch_assoc();

// Guard: no student found (deleted test data or orphaned session)
if (!$student) {
  // Clear the bad student_id from session
  $_SESSION['student_id'] = null;
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    </head><body>';
  include('includes/nav.php');
  echo '<div class="portal-container" style="text-align:center;padding:60px 20px;">
    <i class="bi bi-exclamation-circle" style="font-size:48px;color:#d97706;"></i>
    <h3 style="margin-top:16px;">No Student Record Found</h3>
    <p style="color:#6b7280;font-size:14px;max-width:400px;margin:0 auto;">
      Your enrollment application is being processed.<br>
      Please wait for the registrar to review your submission.<br><br>
      If you believe this is an error, please contact the school registrar.
    </p>
    <a href="logout.php" style="display:inline-block;margin-top:24px;padding:10px 24px;background:#494C8A;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
      Log Out
    </a>
  </div></body></html>';
  exit();
}

$enrollment = $conn->query("SELECT * FROM enrollments WHERE student_id=$student_id AND school_year_id=$sy_id LIMIT 1")->fetch_assoc();

// Timeline progress
$step1 = !empty($enrollment); // Applied
$step2 = false; $step3 = false; $step4 = false;

if ($step1) {
  // Documents step: ALL required docs must be verified
  $doc_total = $conn->query("
    SELECT COUNT(*) as c FROM requirements r
    WHERE r.is_required=1
    AND (r.student_type='both' OR r.student_type='{$student['student_type']}')
  ")->fetch_assoc()['c'] ?? 0;

  $doc_verified = $conn->query("
    SELECT COUNT(*) as c FROM student_requirements sr
    JOIN requirements r ON r.id = sr.requirement_id
    WHERE sr.student_id=$student_id AND sr.school_year_id=$sy_id
    AND sr.status='verified'
    AND r.is_required=1
    AND (r.student_type='both' OR r.student_type='{$student['student_type']}')
  ")->fetch_assoc()['c'] ?? 0;

  $step2 = ($doc_total > 0 && $doc_verified >= $doc_total);

  $pay_check = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM payments WHERE student_id=$student_id")->fetch_assoc();
  $step3 = ($pay_check['total'] ?? 0) > 0;

  $step4 = ($enrollment['status'] ?? '') === 'enrolled';
}

// Requirements summary
$req_summary = $conn->query("
  SELECT
    SUM(sr.status='verified') as verified,
    SUM(sr.status='submitted') as submitted,
    SUM(sr.status='missing' OR sr.id IS NULL) as missing,
    COUNT(r.id) as total
  FROM requirements r
  LEFT JOIN student_requirements sr ON sr.requirement_id=r.id AND sr.student_id=$student_id AND sr.school_year_id=$sy_id
  WHERE r.is_required=1 AND (r.student_type='both' OR r.student_type='{$student['student_type']}')
")->fetch_assoc();

// Payment summary — read directly from payments table
$pay_summary = $conn->query("
  SELECT COALESCE(SUM(amount_paid),0) as paid
  FROM payments WHERE student_id=$student_id
")->fetch_assoc();

// Compute total fees (deduplicated) for balance — only if fees exist for this grade
$fees_for_balance = $conn->query("
  SELECT name, amount FROM fees
  WHERE grade_level_id = {$student['grade_level_id']} AND school_year_id = $sy_id AND fee_type != 'sped'
  ORDER BY name
")->fetch_all(MYSQLI_ASSOC);
$fees_assessed = count($fees_for_balance) > 0;
$seen_bal = []; $total_fees_bal = 0;
foreach ($fees_for_balance as $f) {
  if (!isset($seen_bal[$f['name']])) { $seen_bal[$f['name']] = true; $total_fees_bal += $f['amount']; }
}
$pay_summary['balance'] = $fees_assessed ? max(0, $total_fees_bal - $pay_summary['paid']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — Parent Portal</title>
  <link rel="icon" type="image/x-icon" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal.css">
</head>
<body>
<?php include('includes/nav.php'); ?>

<div class="portal-container">
  <div class="portal-page-header">
    <h2>Welcome, <?= htmlspecialchars(explode(' ', $parent_name)[0]) ?>!</h2>
    <p>SY <?= htmlspecialchars($active_sy['label'] ?? '') ?> — Enrollment Overview</p>
  </div>

  <!-- Multi-child switcher -->
  <?php if (count($linked_students) > 1): ?>
  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach ($linked_students as $ls): ?>
    <a href="switch_student.php?id=<?= $ls['id'] ?>"
       style="padding:7px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid <?= $ls['id']==$student_id?'var(--primary)':'var(--border)' ?>;background:<?= $ls['id']==$student_id?'var(--primary)':'#fff' ?>;color:<?= $ls['id']==$student_id?'#fff':'var(--text)' ?>;">
      <?= htmlspecialchars($ls['first_name'] . ' ' . $ls['last_name']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Student card -->
  <div class="portal-student-card">
    <div class="portal-student-avatar">
      <i class="bi bi-person-fill"></i>
    </div>
    <div class="portal-student-info">
      <div class="portal-student-name"><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')) ?></div>
      <div class="portal-student-meta" style="font-size:13px;color:var(--muted);margin-top:3px;"><?= htmlspecialchars($student['grade'] ?? '—') ?></div>
    </div>
    <div>
      <?php $es = $enrollment['status'] ?? 'not enrolled'; ?>
      <span class="portal-enroll-badge badge-<?= str_replace(' ','-',$es) ?>">
        <?= ucfirst($es) ?>
      </span>
    </div>
  </div>

  <!-- Enrollment Progress Timeline -->
  <div class="portal-timeline">
    <div class="timeline-header">
      <h3>Enrollment Progress</h3>
      <span class="timeline-sy">SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></span>
    </div>
    <div class="timeline-steps">
      <?php
      $steps = [
        ['label' => 'Applied', 'icon' => 'bi-send-fill', 'done' => $step1, 'desc' => 'Application submitted'],
        ['label' => 'Documents', 'icon' => 'bi-folder-check', 'done' => $step2, 'desc' => 'Documents verified'],
        ['label' => 'Payment', 'icon' => 'bi-cash-coin', 'done' => $step3, 'desc' => 'Payment recorded'],
        ['label' => 'Enrolled', 'icon' => 'bi-patch-check-fill', 'done' => $step4, 'desc' => 'Enrollment confirmed'],
      ];
      foreach ($steps as $i => $s):
        $active = !$s['done'] && ($i === 0 || $steps[$i-1]['done']);
      ?>
      <div class="timeline-step <?= $s['done'] ? 'done' : ($active ? 'active' : 'pending') ?>">
        <div class="ts-icon"><i class="bi <?= $s['icon'] ?>"></i></div>
        <div class="ts-label"><?= $s['label'] ?></div>
        <div class="ts-desc"><?= $s['desc'] ?></div>
      </div>
      <?php if ($i < 3): ?>
      <div class="timeline-connector <?= $s['done'] ? 'done' : '' ?>"></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Quick links -->
  <div class="portal-summary-grid" style="margin-top:20px;">
    <a href="requirements.php" class="portal-summary-card">
      <div class="psc-icon" style="background:#eef0f8;color:var(--primary)"><i class="bi bi-folder2-open"></i></div>
      <div class="psc-body">
        <div class="psc-val"><?= ($req_summary['verified'] ?? 0) ?> / <?= (($req_summary['verified'] ?? 0) + ($req_summary['submitted'] ?? 0) + ($req_summary['missing'] ?? 0)) ?></div>
        <div class="psc-label">Step 1 · Requirements</div>
      </div>
      <?php if (($req_summary['missing'] ?? 0) > 0): ?>
        <span class="psc-alert"><?= $req_summary['missing'] ?> missing</span>
      <?php endif; ?>
    </a>
    <a href="payment_scheme.php" class="portal-summary-card">
      <div class="psc-icon" style="background:#fef9c3;color:#92400e"><i class="bi bi-wallet2"></i></div>
      <div class="psc-body">
        <div class="psc-val"><i class="bi bi-arrow-right-circle" style="font-size:20px;"></i></div>
        <div class="psc-label">Step 2 · Payment Scheme</div>
      </div>
    </a>
    <a href="soa.php" class="portal-summary-card">
      <div class="psc-icon" style="background:#dcfce7;color:#166534"><i class="bi bi-cash-coin"></i></div>
      <div class="psc-body">
        <?php if ($fees_assessed): ?>
        <div class="psc-val">₱<?= number_format($pay_summary['paid'] ?? 0, 0) ?></div>
        <div class="psc-label">Step 3 · Statement of Account</div>
        <?php else: ?>
        <div class="psc-val" style="font-size:13px;color:var(--muted);">Pending</div>
        <div class="psc-label">Step 3 · Fees not yet assessed</div>
        <?php endif; ?>
      </div>
      <?php if ($fees_assessed && ($pay_summary['balance'] ?? 0) > 0): ?>
        <span class="psc-alert">₱<?= number_format($pay_summary['balance'], 0) ?> balance</span>
      <?php endif; ?>
    </a>
  </div>

</div>
</body>
</html>
