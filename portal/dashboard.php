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

$enrollment = $conn->query("SELECT * FROM enrollments WHERE student_id=$student_id AND school_year_id=$sy_id LIMIT 1")->fetch_assoc();

// Timeline progress
$step1 = !empty($enrollment); // Applied
$step2 = false; $step3 = false; $step4 = false;

if ($step1) {
  $doc_check = $conn->query("SELECT COUNT(*) as c FROM student_requirements WHERE student_id=$student_id AND school_year_id=$sy_id AND status='verified'")->fetch_assoc();
  $step2 = ($doc_check['c'] ?? 0) > 0;

  $pay_check = $conn->query("SELECT COUNT(*) as c FROM payments WHERE student_id=$student_id AND status IN ('paid','partial')")->fetch_assoc();
  $step3 = ($pay_check['c'] ?? 0) > 0;

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

// Payment summary
$pay_summary = $conn->query("
  SELECT COALESCE(SUM(amount_paid),0) as paid, COALESCE(SUM(balance),0) as balance
  FROM payments WHERE student_id=$student_id
")->fetch_assoc();
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
      <?php if (!empty($student['photo'])): ?>
        <img src="../pages/uploads/<?= htmlspecialchars($student['photo']) ?>" alt="Photo"/>
      <?php else: ?>
        <i class="bi bi-person-fill"></i>
      <?php endif; ?>
    </div>
    <div class="portal-student-info">
      <div class="portal-student-name"><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')) ?></div>
      <div class="portal-student-meta">LRN: <?= htmlspecialchars($student['lrn']) ?> · <?= htmlspecialchars($student['grade'] ?? '—') ?> · <?= htmlspecialchars($student['section'] ?? '—') ?></div>
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
  <div class="portal-summary-grid">
    <a href="requirements.php" class="portal-summary-card">
      <div class="psc-icon" style="background:#eef0f8;color:var(--primary)"><i class="bi bi-folder2-open"></i></div>
      <div class="psc-body">
        <div class="psc-val"><?= ($req_summary['verified'] ?? 0) ?> / <?= (($req_summary['verified'] ?? 0) + ($req_summary['submitted'] ?? 0) + ($req_summary['missing'] ?? 0)) ?></div>
        <div class="psc-label">Documents Verified</div>
      </div>
      <?php if (($req_summary['missing'] ?? 0) > 0): ?>
        <span class="psc-alert"><?= $req_summary['missing'] ?> missing</span>
      <?php endif; ?>
    </a>
    <a href="soa.php" class="portal-summary-card">
      <div class="psc-icon" style="background:#dcfce7;color:#166534"><i class="bi bi-cash-coin"></i></div>
      <div class="psc-body">
        <div class="psc-val">₱<?= number_format($pay_summary['paid'] ?? 0, 0) ?></div>
        <div class="psc-label">Total Paid</div>
      </div>
      <?php if (($pay_summary['balance'] ?? 0) > 0): ?>
        <span class="psc-alert">₱<?= number_format($pay_summary['balance'], 0) ?> balance</span>
      <?php endif; ?>
    </a>
  </div>

  <!-- Fee Preview -->
  <?php
  require_once '../mysql/helpers.php';
  $fee_data = compute_fees($conn, $student_id, $student['grade_level_id'] ?? 0, $sy_id);
  if ($fee_data['subtotal'] > 0):
  ?>
  <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-top:20px;">
    <div style="font-size:15px;font-weight:700;margin-bottom:16px;"><i class="bi bi-cash-coin" style="color:var(--primary);"></i> Fee Breakdown</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;margin-bottom:16px;">
      <?php foreach ($fee_data['breakdown'] as $type => $amt): if ($amt <= 0) continue; ?>
      <div style="display:flex;justify-content:space-between;padding:8px 12px;background:var(--bg);border-radius:6px;">
        <span style="text-transform:capitalize;color:var(--muted);"><?= str_replace('_',' ',$type) ?></span>
        <span style="font-weight:600;">₱<?= number_format($amt,2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($fee_data['discount_amount'] > 0): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 12px;background:#f0fdf4;border-radius:6px;margin-bottom:8px;font-size:13px;">
      <span style="color:#16a34a;font-weight:600;">Discount Applied</span>
      <span style="color:#16a34a;font-weight:700;">-₱<?= number_format($fee_data['discount_amount'],2) ?></span>
    </div>
    <?php endif; ?>
    <div style="border-top:2px solid var(--border);padding-top:12px;margin-top:8px;">
      <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;">Payment Plan Options:</div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
        <?php foreach ($fee_data['plans'] as $plan_key => $plan): ?>
        <div style="border:1.5px solid var(--border);border-radius:8px;padding:12px;text-align:center;">
          <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;"><?= $plan['label'] ?></div>
          <div style="font-size:20px;font-weight:800;color:var(--primary);margin:6px 0;">₱<?= number_format($plan['amount'],2) ?></div>
          <div style="font-size:11px;color:var(--muted);">per installment × <?= $plan['installments'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:12px;font-size:12px;color:var(--muted);background:#fef9c3;border-radius:6px;padding:8px 12px;">
        <i class="bi bi-info-circle-fill"></i> Reservation fee of ₱<?= number_format($fee_data['reservation'],2) ?> is deducted from your first payment.
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
