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

// Check if scheme already locked
$existing_schedule = $conn->query("SELECT COUNT(*) as c FROM payment_schedules WHERE enrollment_id=$enrollment_id")->fetch_assoc()['c'];
$scheme_locked = $existing_schedule > 0;
$current_scheme = $enrollment['payment_plan'] ?? null;

$success = $error = '';

// Handle scheme selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_scheme'])) {
  if ($scheme_locked) {
    $error = "Your payment scheme is already set and cannot be changed.";
  } else {
    $scheme = $_POST['scheme'] ?? '';
    $valid = ['annual','semi_annual','quarterly','monthly'];
    if (!in_array($scheme, $valid)) {
      $error = "Please select a valid payment scheme.";
    } else {
      $ok = generate_payment_schedule($conn, $student_id, $enrollment_id, $sy_id, $scheme);
      if ($ok) {
        // Notify finance staff
        $sname = $student['first_name'] . ' ' . $student['last_name'];
        $slabel = get_payment_scheme_config($scheme)['label'] ?? $scheme;
        notify_staff($conn, ['superadmin','finance'], 'info',
          "Payment Scheme Selected: $sname",
          "$sname selected the $slabel payment scheme. Downpayment is now due.",
          "payments.php"
        );
        $success = "Payment scheme set successfully. Your payment schedule is now active.";
        $scheme_locked = true;
        $current_scheme = $scheme;
      } else {
        $error = "Could not set payment scheme. Please try again.";
      }
    }
  }
}

// Apply penalties on load and repair any bad due dates
if ($enrollment_id) {
  repair_payment_schedule_dates($conn, $enrollment_id, $current_scheme ?? '');
  apply_late_penalties($conn, $student_id, $enrollment_id);
}

// Load schedule
$schedule = $conn->query("
  SELECT * FROM payment_schedules
  WHERE enrollment_id=$enrollment_id
  ORDER BY installment_no ASC
")->fetch_all(MYSQLI_ASSOC);

// Build dynamic scheme options from actual fees + discounts for this student
$scheme_keys = ['annual','semi_annual','quarterly','monthly'];
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
    'label'       => $cfg['label'],
    'downpayment' => $cfg['downpayment'],
    'net_total'   => $cfg['net_total'],
    'desc'        => $descs[$key],
    'installments'=> $cfg['installments'],
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

  <?php if (!$scheme_locked): ?>
  <!-- Scheme Selection -->
  <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:24px;">
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Select Your Payment Scheme</div>
    <div style="font-size:13px;color:#6b7280;margin-bottom:20px;">
      Choose how you want to pay your tuition. <strong>This cannot be changed once selected.</strong>
    </div>

    <form method="POST" action="payment_scheme.php">
      <input type="hidden" name="select_scheme" value="1">
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
        <?php foreach ($schemes_info as $key => $info): ?>
        <label style="display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:.15s;" onclick="this.style.borderColor='#494C8A';this.style.background='#eef0f8';">
          <input type="radio" name="scheme" value="<?= $key ?>" required style="margin-top:3px;accent-color:#494C8A;flex-shrink:0;"/>
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
        <strong>Important:</strong> Your payment scheme is locked once confirmed. A ₱500 penalty is automatically added for late payments past the due date.
      </div>

      <button type="submit" onclick="return confirm('Confirm your payment scheme selection? This cannot be changed later.')"
        style="padding:11px 28px;background:#494C8A;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:var(--font);">
        <i class="bi bi-check-circle-fill"></i> Confirm Payment Scheme
      </button>
    </form>
  </div>

  <?php else: ?>
  <!-- Scheme Locked — Show Schedule -->
  <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
    <i class="bi bi-lock-fill" style="color:#16a34a;font-size:18px;"></i>
    <div>
      <div style="font-size:13px;font-weight:700;color:#166534;">
        Payment Scheme: <?= htmlspecialchars($schemes_info[$current_scheme]['label'] ?? ucfirst($current_scheme)) ?>
      </div>
      <div style="font-size:12px;color:#166534;margin-top:2px;">Your scheme is locked and cannot be changed.</div>
    </div>
  </div>

  <!-- Payment Schedule Table -->
  <div style="background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:24px;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700;">
      Payment Schedule
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
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
        <?php foreach ($schedule as $row):
          $total_due = $row['amount_due'] + $row['penalty'];
          $status_colors = [
            'paid'    => '#16a34a',
            'partial' => '#d97706',
            'unpaid'  => '#dc2626',
            'overdue' => '#dc2626',
          ];
          $status_bg = [
            'paid'    => '#dcfce7',
            'partial' => '#fef9c3',
            'unpaid'  => '#fdeaea',
            'overdue' => '#fdeaea',
          ];
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
        <?php if (empty($schedule)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--muted);">No schedule found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="font-size:12px;color:#6b7280;background:#fef9c3;border-radius:6px;padding:10px 14px;">
    <i class="bi bi-info-circle-fill"></i>
    A <strong>₱500 penalty</strong> is automatically added to any installment not paid by its due date.
    Upload your proof of payment in the <a href="soa.php" style="color:#494C8A;font-weight:600;">Statement of Account</a> page.
  </div>

  <?php endif; ?>

</div>
</body>
</html>
