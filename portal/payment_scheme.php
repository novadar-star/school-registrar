<?php
$active_portal = 'payment_scheme';
require_once 'includes/auth.php';
require_once '../mysql/helpers.php';

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

$student = $conn->query("SELECT s.*, g.name as grade FROM students s LEFT JOIN grade_levels g ON s.grade_level_id=g.id WHERE s.id=$student_id")->fetch_assoc();
if (!$student) { header('Location: dashboard.php'); exit(); }

$enrollment = $conn->query("SELECT * FROM enrollments WHERE student_id=$student_id AND school_year_id=$sy_id LIMIT 1")->fetch_assoc();
if (!$enrollment) { header('Location: dashboard.php'); exit(); }

$enrollment_id = $enrollment['id'];

// ── Lock logic: locked only if payment made OR proof uploaded ──
// A schedule existing alone does NOT lock — parent can still change scheme.
$payment_activity = $conn->query("
  SELECT COUNT(*) as c FROM payments
  WHERE student_id = $student_id
  AND amount_paid > 0
")->fetch_assoc()['c'] ?? 0;

$scheme_locked     = $payment_activity > 0;
$current_scheme    = $enrollment['payment_plan'] ?? null;
$existing_schedule = $conn->query("SELECT COUNT(*) as c FROM payment_schedules WHERE enrollment_id=$enrollment_id")->fetch_assoc()['c'];

$success = $error = '';

// Handle schedule regeneration (mismatch fix)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_schedule'])) {
  if ($scheme_locked) {
    $error = "Cannot regenerate schedule — a payment has already been recorded.";
  } else {
    $scheme = $enrollment['payment_plan'] ?? '';
    if ($scheme) {
      $conn->query("DELETE FROM payment_schedules WHERE enrollment_id=$enrollment_id");
      $conn->query("UPDATE enrollments SET payment_plan=NULL WHERE id=$enrollment_id");
      $ok = generate_payment_schedule($conn, $student_id, $enrollment_id, $sy_id, $scheme);
      if ($ok) {
        $success = "Payment schedule has been regenerated to match your fee total.";
        $existing_schedule = 1;
        $current_scheme    = $scheme;
      } else {
        $error = "Could not regenerate schedule. Please contact the registrar.";
      }
    }
  }
}

// Handle scheme selection / change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_scheme'])) {
  if ($scheme_locked) {
    $error = "Your payment scheme cannot be changed because a payment or proof of payment has already been recorded.";
  } else {
    $scheme = $_POST['scheme'] ?? '';
    $valid  = ['annual','semi_annual','quarterly','monthly'];
    if (!in_array($scheme, $valid)) {
      $error = "Please select a valid payment scheme.";
    } else {
      // If switching scheme, wipe old schedule first
      if ($existing_schedule > 0) {
        $conn->query("DELETE FROM payment_schedules WHERE enrollment_id=$enrollment_id");
        $conn->query("UPDATE enrollments SET payment_plan=NULL WHERE id=$enrollment_id");
      }
      $ok = generate_payment_schedule($conn, $student_id, $enrollment_id, $sy_id, $scheme);
      if ($ok) {
        $sname  = $student['first_name'] . ' ' . $student['last_name'];
        $slabel = get_payment_scheme_config($scheme)['label'] ?? $scheme;
        notify_staff($conn, ['superadmin','finance'], 'info',
          "Payment Scheme Selected: $sname",
          "$sname selected the $slabel payment scheme. Downpayment is now due.",
          "payments.php"
        );
        $success           = "Payment scheme set successfully. Your payment schedule is now active.";
        $current_scheme    = $scheme;
        $existing_schedule = 1;
      } else {
        $error = "Could not set payment scheme. Please try again.";
      }
    }
  }
}

// Apply penalties and repair dates
if ($enrollment_id) {
  repair_payment_schedule_dates($conn, $enrollment_id, $current_scheme ?? '');
  apply_late_penalties($conn, $student_id, $enrollment_id);
}

// Reload schedule after any changes
$schedule = $conn->query("
  SELECT * FROM payment_schedules
  WHERE enrollment_id=$enrollment_id
  ORDER BY installment_no ASC
")->fetch_all(MYSQLI_ASSOC);

// Build scheme options
$scheme_keys  = ['annual','semi_annual','quarterly','monthly'];
$schemes_info = [];
foreach ($scheme_keys as $key) {
  $cfg = get_payment_scheme_config_for_student($conn, $student_id, $student['grade_level_id'], $sy_id, $key);
  $descs = [
    'annual'      => 'Pay everything upfront. No further payments required.',
    'semi_annual' => 'Downpayment now, one more payment in November.',
    'quarterly'   => 'Downpayment now, then 3 payments: August, November, February.',
    'monthly'     => 'Downpayment now, then monthly payments July through February.',
  ];
  $schemes_info[$key] = [
    'label'        => $cfg['label'],
    'downpayment'  => $cfg['downpayment'],
    'net_total'    => $cfg['net_total'],
    'desc'         => $descs[$key],
    'installments' => $cfg['installments'],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payment Scheme — Parent Portal</title>
  <link rel="icon" type="image/x-icon" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal.css">
</head>
<body>
<?php include('includes/nav.php'); ?>
<div class="portal-container">

  <div class="portal-page-header">
    <h2>Payment Scheme</h2>
    <p><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
       · <?= htmlspecialchars($student['grade'] ?? '') ?>
       · SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></p>
  </div>

  <?php if ($success): ?>
  <div style="background:#f0fdf4;border-left:3px solid #16a34a;border-radius:6px;padding:12px 16px;font-size:13px;font-weight:500;color:#166534;margin-bottom:16px;">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div style="background:#fff5f5;border-left:3px solid #f87171;border-radius:6px;padding:12px 16px;font-size:13px;font-weight:500;color:#b91c1c;margin-bottom:16px;">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($scheme_locked): ?>
  <!-- ── LOCKED: payment made ── -->
  <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
    <i class="bi bi-lock-fill" style="color:#16a34a;font-size:18px;"></i>
    <div>
      <div style="font-size:13px;font-weight:700;color:#166534;">
        Payment Scheme: <?= htmlspecialchars($schemes_info[$current_scheme]['label'] ?? ucfirst($current_scheme ?? '—')) ?>
      </div>
      <div style="font-size:12px;color:#166534;margin-top:2px;">
        Scheme is locked — a payment or proof of payment has been recorded.
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ── UNLOCKED: can select or change scheme ── -->

  <?php if ($existing_schedule > 0 && $current_scheme): ?>
  <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
    <i class="bi bi-arrow-repeat" style="color:#d97706;font-size:18px;"></i>
    <div>
      <div style="font-size:13px;font-weight:700;color:#92400e;">
        Current Scheme: <?= htmlspecialchars($schemes_info[$current_scheme]['label'] ?? ucfirst($current_scheme)) ?>
      </div>
      <div style="font-size:12px;color:#92400e;margin-top:2px;">
        No payment recorded yet — you can still change your scheme below.
        Once you pay or upload a receipt, the scheme will be locked permanently.
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Scheme Selection Form -->
  <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:24px;">
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;">
      <?= $existing_schedule > 0 ? 'Change Your Payment Scheme' : 'Select Your Payment Scheme' ?>
    </div>
    <div style="font-size:13px;color:#6b7280;margin-bottom:20px;">
      Choose how you want to pay your tuition.
      <strong>This will be locked once you make a payment or upload a receipt.</strong>
    </div>

    <?php
    $fee_summary       = get_fee_rows_with_payment($conn, $student_id, $student['grade_level_id'], $sy_id);
    $net_total_display = $fee_summary['net_fees'];
    ?>
    <div style="background:#f8f9ff;border:1px solid #c7d2fe;border-radius:10px;padding:14px 18px;margin-bottom:20px;">
      <div style="font-size:12px;font-weight:700;color:#494C8A;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
        <i class="bi bi-receipt"></i> Your Fee Summary
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
        <span style="color:#6b7280;">Total Fees</span>
        <span style="font-weight:600;">₱<?= number_format($fee_summary['total_fees'], 2) ?></span>
      </div>
      <?php if ($fee_summary['total_discount'] > 0): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
        <span style="color:#16a34a;">Discount Applied</span>
        <span style="font-weight:600;color:#16a34a;">-₱<?= number_format($fee_summary['total_discount'], 2) ?></span>
      </div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;border-top:1px solid #c7d2fe;padding-top:8px;margin-top:4px;">
        <span style="color:#494C8A;">Net Amount to Pay</span>
        <span style="color:#494C8A;">₱<?= number_format($net_total_display, 2) ?></span>
      </div>
    </div>

    <form method="POST" action="payment_scheme.php">
      <input type="hidden" name="select_scheme" value="1">
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
        <?php foreach ($schemes_info as $key => $info): ?>
        <label style="display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border:1.5px solid <?= $current_scheme === $key ? '#494C8A' : 'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= $current_scheme === $key ? '#eef0f8' : '#fff' ?>;transition:.15s;"
               onclick="this.style.borderColor='#494C8A';this.style.background='#eef0f8';">
          <input type="radio" name="scheme" value="<?= $key ?>" required
                 <?= $current_scheme === $key ? 'checked' : '' ?>
                 style="margin-top:3px;accent-color:#494C8A;flex-shrink:0;"/>
          <div style="flex:1;">
            <div style="font-size:14px;font-weight:700;color:#1a1a2e;"><?= $info['label'] ?></div>
            <div style="font-size:13px;color:#6b7280;margin-top:2px;"><?= $info['desc'] ?></div>
            <?php if (!empty($info['installments'])): ?>
            <div style="margin-top:8px;font-size:12px;color:#374151;">
              <?php foreach ($info['installments'] as $inst): ?>
              <span style="display:inline-block;background:#f3f4f6;border-radius:4px;padding:2px 8px;margin:2px 4px 2px 0;">
                <?= htmlspecialchars($inst['label']) ?>: <strong>₱<?= number_format($inst['amount'], 2) ?></strong>
              </span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Total</div>
            <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:4px;">₱<?= number_format($info['net_total'], 2) ?></div>
            <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Downpayment</div>
            <div style="font-size:18px;font-weight:800;color:#494C8A;">₱<?= number_format($info['downpayment'], 2) ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:13px;color:#92400e;margin-bottom:20px;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Important:</strong> Once you make a payment or upload a receipt, your scheme is permanently locked.
        A ₱500 penalty is automatically added for late payments past the due date.
      </div>

      <button type="submit"
        onclick="return confirm('<?= $existing_schedule > 0 ? 'Change your payment scheme? Your old schedule will be replaced.' : 'Confirm your payment scheme selection?' ?>')"
        style="padding:11px 28px;background:#494C8A;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:var(--font);">
        <i class="bi bi-check-circle-fill"></i>
        <?= $existing_schedule > 0 ? 'Change Payment Scheme' : 'Confirm Payment Scheme' ?>
      </button>
    </form>
  </div>

  <?php endif; // scheme_locked ?>

  <?php if ($existing_schedule > 0 && !empty($schedule)): ?>
  <!-- ── Payment Schedule Table ── -->
  <div style="background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:24px;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700;">
      Payment Schedule
    </div>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:520px;">
      <thead>
        <tr style="background:var(--bg);">
          <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">#</th>
          <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Description</th>
          <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Due Date</th>
          <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Amount Due</th>
          <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Penalty</th>
          <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Paid</th>
          <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $status_colors = ['paid'=>'#16a34a','partial'=>'#d97706','unpaid'=>'#dc2626','overdue'=>'#dc2626'];
        $status_bg     = ['paid'=>'#dcfce7','partial'=>'#fef9c3','unpaid'=>'#fdeaea','overdue'=>'#fdeaea'];
        foreach ($schedule as $row):
          $sc = $row['status'];
        ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:12px 16px;color:var(--muted);"><?= $row['installment_no'] ?></td>
          <td style="padding:12px 16px;font-weight:600;"><?= htmlspecialchars($row['label']) ?></td>
          <td style="padding:12px 16px;color:var(--muted);">
            <?= $row['due_date'] ? date('M j, Y', strtotime($row['due_date'])) : '—' ?>
          </td>
          <td style="padding:12px 16px;text-align:right;font-weight:600;">₱<?= number_format($row['amount_due'], 2) ?></td>
          <td style="padding:12px 16px;text-align:right;color:<?= $row['penalty'] > 0 ? '#dc2626' : 'var(--muted)' ?>;font-weight:<?= $row['penalty'] > 0 ? '700' : '400' ?>;">
            <?= $row['penalty'] > 0 ? '+₱'.number_format($row['penalty'],2) : '—' ?>
          </td>
          <td style="padding:12px 16px;text-align:right;color:#16a34a;font-weight:600;">₱<?= number_format($row['amount_paid'], 2) ?></td>
          <td style="padding:12px 16px;">
            <span style="padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;background:<?= $status_bg[$sc] ?? '#f3f4f6' ?>;color:<?= $status_colors[$sc] ?? '#374151' ?>;">
              <?= ucfirst($sc) ?>
              <?php if ($sc === 'overdue'): ?><i class="bi bi-exclamation-circle-fill" style="margin-left:3px;"></i><?php endif; ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div style="font-size:12px;color:#6b7280;background:#fef9c3;border-radius:6px;padding:10px 14px;margin-bottom:12px;">
    <i class="bi bi-info-circle-fill"></i>
    A <strong>₱500 penalty</strong> is automatically added to any installment not paid by its due date.
    Upload your proof of payment in the <a href="soa.php" style="color:#494C8A;font-weight:600;">Statement of Account</a> page.
  </div>

  <?php
  $sched_total = array_sum(array_column($schedule, 'amount_due'));
  $soa_check   = get_fee_rows_with_payment($conn, $student_id, $student['grade_level_id'], $sy_id);
  $soa_net     = $soa_check['net_fees'];
  $match       = abs($sched_total - $soa_net) < 0.02;
  ?>
  <div style="background:<?= $match ? '#f0fdf4' : '#fff5f5' ?>;border:1px solid <?= $match ? '#86efac' : '#fca5a5' ?>;border-radius:8px;padding:12px 16px;font-size:13px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
      <span style="color:#6b7280;">Schedule Total</span>
      <span style="font-weight:700;">₱<?= number_format($sched_total, 2) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;">
      <span style="color:#6b7280;">SOA Net Total</span>
      <span style="font-weight:700;">₱<?= number_format($soa_net, 2) ?></span>
    </div>
    <?php if (!$match): ?>
    <div style="margin-top:8px;font-size:12px;color:#b91c1c;font-weight:600;">
      <i class="bi bi-exclamation-triangle-fill"></i>
      Schedule total does not match your fee total.
      <?php if (!$scheme_locked): ?>
      <form method="POST" action="payment_scheme.php" style="display:inline;margin-left:8px;">
        <input type="hidden" name="fix_schedule" value="1">
        <button type="submit" onclick="return confirm('Regenerate your payment schedule to match your current fee total?')"
          style="padding:3px 12px;background:#b91c1c;color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;">
          <i class="bi bi-arrow-repeat"></i> Fix Now
        </button>
      </form>
      <?php else: ?>
      Please contact the registrar.
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; // existing_schedule ?>

</div>
</body>
</html>
