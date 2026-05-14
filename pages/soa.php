<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

$student_id = intval($_GET['student_id'] ?? 0);
if (!$student_id) { header('Location: payments.php'); exit(); }

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

$student = $conn->query("
  SELECT s.*, g.name as grade, sec.name as section
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id=g.id
  LEFT JOIN sections sec ON s.section_id=sec.id
  WHERE s.id=$student_id
")->fetch_assoc();

if (!$student) { header('Location: payments.php'); exit(); }

$enrollment = $conn->query("SELECT * FROM enrollments WHERE student_id=$student_id AND school_year_id=$sy_id LIMIT 1")->fetch_assoc();
$enrollment_id = $enrollment['id'] ?? 0;

// Handle installment payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_installment') {
  $sched_id   = intval($_POST['schedule_id']);
  $amount     = floatval($_POST['amount_paid']);
  $or_number  = trim($_POST['or_number'] ?? '');
  $method     = in_array($_POST['payment_method'] ?? '', ['cash','gcash','bank_transfer','maya','other']) ? $_POST['payment_method'] : 'cash';
  $paid_at_raw = $_POST['paid_at'] ?? date('Y-m-d');
  $paid_at    = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_at_raw) && strtotime($paid_at_raw) !== false)
                ? $paid_at_raw : date('Y-m-d');

  if ($amount <= 0) {
    header("Location: soa.php?student_id=$student_id&error=Amount must be greater than zero"); exit();
  }

  // Ownership check: schedule row must belong to this student
  $sched = $conn->query("SELECT * FROM payment_schedules WHERE id=$sched_id AND student_id=$student_id")->fetch_assoc();
  if ($sched) {
    $total_due = $sched['amount_due'] + $sched['penalty'];
    $new_paid  = $sched['amount_paid'] + $amount;
    $balance   = $total_due - $new_paid;
    $status    = $balance <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');

    $stmt = $conn->prepare("UPDATE payment_schedules SET amount_paid=?, status=?, or_number=?, payment_method=?, paid_at=? WHERE id=?");
    $stmt->bind_param("dssssi", $new_paid, $status, $or_number, $method, $paid_at, $sched_id);
    $stmt->execute();

    // If downpayment confirmed, mark enrollment downpayment_confirmed
    if ($sched['installment_no'] == 1 && $status === 'paid' && $enrollment_id) {
      $conn->query("UPDATE enrollments SET downpayment_confirmed=1 WHERE id=$enrollment_id");
    }

    $uid   = $_SESSION['user_id'] ?? 0;
    $uname = $conn->real_escape_string($_SESSION['name'] ?? '');
    $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id, details) VALUES ($uid, '$uname', 'confirm_installment', 'payment_schedule', $sched_id, 'Installment #".$sched['installment_no']." confirmed: ₱$amount')");
  }
  header("Location: soa.php?student_id=$student_id&success=Installment confirmed"); exit();
}

// Apply late penalties
if ($enrollment_id) apply_late_penalties($conn, $student_id, $enrollment_id);

// Load payment schedule
$schedule = $conn->query("
  SELECT * FROM payment_schedules WHERE enrollment_id=$enrollment_id ORDER BY installment_no ASC
")->fetch_all(MYSQLI_ASSOC);

// Use centralized helper — single source of truth
$soa = get_fee_rows_with_payment($conn, $student_id, $student['grade_level_id'], $sy_id);
$fees_payments   = $soa['rows'];
$total_fees      = $soa['total_fees'];
$net_fees        = $soa['net_fees'];
$total_paid      = $soa['total_paid'];
$total_bal       = $soa['total_bal'];
$pay_details     = $soa['pay_details'];
$discounts       = $soa['discounts'];

$success_msg = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SOA — <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .soa-wrap { max-width: 760px; margin: 32px auto; background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius); overflow: hidden; }
    .soa-header { background: var(--color-primary); color: #fff; padding: 24px 32px; display: flex; align-items: center; gap: 16px; }
    .soa-header img { width: 52px; height: 52px; object-fit: contain; }
    .soa-header-text h2 { font-size: 16px; font-weight: 700; }
    .soa-header-text p  { font-size: 12px; opacity: .75; margin-top: 2px; }
    .soa-student { padding: 20px 32px; border-bottom: 1px solid var(--color-border); display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .soa-student-field { font-size: 13px; }
    .soa-student-field span { color: var(--color-muted); font-size: 11px; display: block; }
    .soa-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .soa-table th { padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--color-muted); background: var(--color-bg); border-bottom: 1px solid var(--color-border); }
    .soa-table td { padding: 11px 16px; border-bottom: 1px solid var(--color-border); }
    .soa-table tr:last-child td { border-bottom: none; }
    .soa-totals { padding: 20px 32px; background: var(--color-bg); display: flex; justify-content: flex-end; gap: 32px; }
    .soa-total-item { text-align: right; }
    .soa-total-label { font-size: 11px; color: var(--color-muted); font-weight: 700; text-transform: uppercase; }
    .soa-total-val { font-size: 18px; font-weight: 700; margin-top: 2px; }
    .soa-actions { padding: 16px 32px; display: flex; gap: 10px; border-top: 1px solid var(--color-border); }
    .badge-paid    { background: #dcfce7; color: #166534; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .badge-partial { background: #fef9c3; color: #92400e; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .badge-unpaid  { background: #fdeaea; color: #dc2626; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    @media print {
      .soa-actions, #sidebar, #topbar { display: none !important; }
      body { background: #fff; }
      #main { margin: 0; }
      .soa-wrap { border: none; box-shadow: none; margin: 0; }
    }
  </style>
</head>
<body>
<?php
$active_page = 'payments';
include('includes/sidebar.php');
?>
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Statement of Account</div>
      <div class="page-sub"><a href="payments.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Payments</a></div>
    </div>
  </div>
  <div id="page-container">
    <div class="soa-wrap">
      <div class="soa-header">
        <img src="../images/COJ.png" alt="COJ"/>
        <div class="soa-header-text">
          <h2>COJ Catholic Progressive School</h2>
          <p>Statement of Account — SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></p>
        </div>
      </div>

      <div class="soa-student">
        <div class="soa-student-field"><span>Student Name</span><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')) ?></div>
        <div class="soa-student-field"><span>LRN</span><?= htmlspecialchars($student['lrn']) ?></div>
        <div class="soa-student-field"><span>Grade & Section</span><?= htmlspecialchars(($student['grade'] ?? '—') . ' — ' . ($student['section'] ?? '—')) ?></div>
        <div class="soa-student-field"><span>Enrollment Status</span><?= ucfirst($enrollment['status'] ?? 'Not enrolled') ?></div>
        <div class="soa-student-field"><span>Payment Scheme</span><?= $enrollment['payment_plan'] ? ucfirst(str_replace('_',' ',$enrollment['payment_plan'])) : '<em style="color:#d97706;">Not yet selected</em>' ?></div>
        <div class="soa-student-field"><span>Reference #</span><?= htmlspecialchars($enrollment['ref_number'] ?? '—') ?></div>
      </div>

      <?php if ($success_msg): ?>
      <div style="margin:16px 24px;background:#f0fdf4;border-left:3px solid #16a34a;border-radius:6px;padding:10px 14px;font-size:13px;color:#166534;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_msg) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($schedule)): ?>
      <!-- Payment Schedule -->
      <div style="padding:16px 24px 0;">
        <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-muted);margin-bottom:10px;">Payment Schedule</div>
      </div>
      <table class="soa-table">
        <thead>
          <tr>
            <th>#</th><th>Description</th><th>Due Date</th>
            <th>Amount Due</th><th>Penalty</th><th>Paid</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($schedule as $srow):
            $total_due = $srow['amount_due'] + $srow['penalty'];
            $sc = $srow['status'];
            $sc_colors = ['paid'=>'#166534','partial'=>'#92400e','unpaid'=>'#dc2626','overdue'=>'#dc2626'];
            $sc_bg     = ['paid'=>'#dcfce7','partial'=>'#fef9c3','unpaid'=>'#fdeaea','overdue'=>'#fdeaea'];
          ?>
          <tr>
            <td><?= $srow['installment_no'] ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($srow['label']) ?></td>
            <td><?= $srow['due_date'] ? date('M j, Y', strtotime($srow['due_date'])) : '—' ?></td>
            <td>₱<?= number_format($srow['amount_due'], 2) ?></td>
            <td style="color:<?= $srow['penalty']>0?'#dc2626':'var(--color-muted)' ?>;font-weight:<?= $srow['penalty']>0?'700':'400' ?>;">
              <?= $srow['penalty']>0 ? '+₱'.number_format($srow['penalty'],2) : '—' ?>
            </td>
            <td style="color:#16a34a;font-weight:600;">₱<?= number_format($srow['amount_paid'], 2) ?></td>
            <td>
              <span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:<?= $sc_bg[$sc]??'#f3f4f6' ?>;color:<?= $sc_colors[$sc]??'#374151' ?>;">
                <?= ucfirst($sc) ?>
              </span>
            </td>
            <td>
              <?php if ($sc !== 'paid'): ?>
              <button class="btn-view-sm" style="background:#eef0f8;color:var(--color-primary);"
                onclick="openInstModal(<?= $srow['id'] ?>, '<?= htmlspecialchars(addslashes($srow['label'])) ?>', <?= $total_due ?>, <?= $srow['amount_paid'] ?>)">
                <i class="bi bi-cash"></i> Confirm
              </button>
              <?php else: ?>
              <span style="font-size:12px;color:#16a34a;font-weight:600;"><i class="bi bi-check-circle-fill"></i> Paid</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="height:1px;background:var(--color-border);margin:0 24px;"></div>
      <?php endif; ?>

      <table class="soa-table">
        <thead><tr><th>Fee</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Method</th></tr></thead>
        <tbody>
          <?php foreach ($fees_payments as $fp): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($fp['fee_name']) ?></td>
            <td>₱<?= number_format($fp['amount'], 2) ?></td>
            <td>₱<?= number_format($fp['amount_paid'] ?? 0, 2) ?></td>
            <td style="font-weight:600;color:<?= ($fp['balance'] ?? $fp['amount']) > 0 ? '#dc2626' : '#16a34a' ?>">₱<?= number_format($fp['balance'] ?? $fp['amount'], 2) ?></td>
            <td><span class="badge-<?= $fp['status'] ?? 'unpaid' ?>"><?= ucfirst($fp['status'] ?? 'Unpaid') ?></span></td>
            <td><?= !empty($pay_details['payment_method']) ? ucfirst(str_replace('_',' ',$pay_details['payment_method'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($fees_payments)): ?>
          <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--color-muted);">No fee records for this school year.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php
      $proof_file   = $pay_details['proof_file'] ?? null;
      $proof_method = $pay_details['payment_method'] ?? null;
      if ($proof_file):
      ?>
      <div style="padding:16px 24px;border-top:1px solid var(--color-border);display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div style="font-size:13px;font-weight:600;color:var(--color-text);">
          <i class="bi bi-receipt" style="color:var(--color-primary);"></i>
          Proof of Payment
          <?php if ($proof_method): ?>
            <span style="font-weight:400;color:var(--color-muted);">via <?= ucfirst(str_replace('_',' ',$proof_method)) ?></span>
          <?php endif; ?>
        </div>
        <a href="uploads/<?= htmlspecialchars($proof_file) ?>" target="_blank"
           style="padding:6px 16px;background:var(--color-primary);color:#fff;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
          <i class="bi bi-eye-fill"></i> View Receipt
        </a>
      </div>
      <?php endif; ?>

      <?php if (!empty($discounts)): ?>
      <div style="padding:16px 32px;border-top:1px solid var(--color-border);">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-muted);margin-bottom:10px;">Applied Discounts / Scholarships</div>
        <table class="soa-table">
          <thead><tr><th>Type</th><th>Label</th><th>Percentage</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($discounts as $d): ?>
            <tr>
              <td style="text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$d['type'])) ?></td>
              <td><?= htmlspecialchars($d['label'] ?? '—') ?></td>
              <td style="font-weight:700;color:#16a34a;"><?= number_format($d['percentage'],2) ?>%</td>
              <td><?= htmlspecialchars($d['notes'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:12px;text-align:right;font-size:13px;color:var(--color-muted);">
          Total Discount: <strong style="color:#16a34a;"><?= number_format($total_discount_pct,2) ?>% (₱<?= number_format($discount_amount,2) ?>)</strong>
          &nbsp;|&nbsp; Adjusted Total: <strong style="color:var(--color-primary);">₱<?= number_format($adjusted_total,2) ?></strong>
        </div>
      </div>
      <?php endif; ?>

      <div class="soa-totals">
        <div class="soa-total-item"><div class="soa-total-label">Total Fees</div><div class="soa-total-val">₱<?= number_format($total_fees, 2) ?></div></div>
        <div class="soa-total-item"><div class="soa-total-label">Total Paid</div><div class="soa-total-val" style="color:#16a34a;">₱<?= number_format($total_paid, 2) ?></div></div>
        <div class="soa-total-item"><div class="soa-total-label">Balance Due</div><div class="soa-total-val" style="color:<?= $total_bal > 0 ? '#dc2626' : '#16a34a' ?>;">₱<?= number_format($total_bal, 2) ?></div></div>
      </div>

      <div class="soa-actions">
        <button onclick="window.print()" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-sm);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
          <i class="bi bi-printer-fill"></i> Print SOA
        </button>
        <a href="payments.php" style="padding:9px 20px;background:var(--color-bg);color:var(--color-muted);border:1.5px solid var(--color-border);border-radius:var(--radius-sm);font-family:var(--font);font-size:13px;font-weight:600;text-decoration:none;">
          Back
        </a>
      </div>
    </div>
  </div>
</div>
<script src="../js/nav.js"></script>

<!-- Installment Confirm Modal -->
<div class="modal-overlay" id="inst-modal">
  <div class="modal-box" style="max-width:440px;">
    <div class="modal-header">
      <h2>Confirm Installment Payment</h2>
      <button class="modal-close" onclick="document.getElementById('inst-modal').classList.remove('open')">&times;</button>
    </div>
    <form method="POST" action="soa.php?student_id=<?= $student_id ?>">
      <input type="hidden" name="action" value="confirm_installment">
      <input type="hidden" name="schedule_id" id="inst-sched-id">
      <div class="modal-body">
        <div style="font-size:14px;font-weight:700;margin-bottom:12px;" id="inst-label"></div>
        <div style="background:#f9fafb;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="color:var(--color-muted);">Total Due (incl. penalty)</span>
            <span id="inst-total-due" style="font-weight:600;"></span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span style="color:var(--color-muted);">Already Paid</span>
            <span id="inst-already-paid" style="font-weight:600;color:var(--color-success);"></span>
          </div>
        </div>
        <div class="form-grid" style="grid-template-columns:1fr;">
          <div class="form-group">
            <label>Amount Paid *</label>
            <input type="number" name="amount_paid" id="inst-amount" class="form-input" step="0.01" min="0" required/>
          </div>
          <div class="form-group">
            <label>Payment Method *</label>
            <select name="payment_method" class="form-input" required>
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="maya">Maya</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>OR Number</label>
            <input type="text" name="or_number" class="form-input" placeholder="Official Receipt No."/>
          </div>
          <div class="form-group">
            <label>Date Paid</label>
            <input type="date" name="paid_at" class="form-input" value="<?= date('Y-m-d') ?>"/>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('inst-modal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn-save">Confirm Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
function openInstModal(schedId, label, totalDue, alreadyPaid) {
  document.getElementById('inst-sched-id').value = schedId;
  document.getElementById('inst-label').textContent = label;
  document.getElementById('inst-total-due').textContent = '₱' + totalDue.toLocaleString('en-PH', {minimumFractionDigits:2});
  document.getElementById('inst-already-paid').textContent = '₱' + alreadyPaid.toLocaleString('en-PH', {minimumFractionDigits:2});
  document.getElementById('inst-amount').value = (totalDue - alreadyPaid).toFixed(2);
  document.getElementById('inst-modal').classList.add('open');
}
</script>
</body>
</html>
