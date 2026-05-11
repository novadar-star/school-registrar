<?php
session_start();
include('../mysql/db.php');
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
if (!in_array($_SESSION['role'] ?? '', ['finance','superadmin'])) {
  header('Location: dashboard.php'); exit();
}

// Handle payment reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_payment') {
  $sid = intval($_POST['student_id']);
  // Reset all payment records for this student back to unpaid
  $conn->query("
    UPDATE payments p
    JOIN fees f ON f.id = p.fee_id
    SET p.amount_paid = 0, p.balance = f.amount, p.status = 'unpaid',
        p.or_number = NULL, p.paid_at = NULL
    WHERE p.student_id = $sid
  ");
  $uid   = $_SESSION['user_id'] ?? 0;
  $uname = $conn->real_escape_string($_SESSION['name'] ?? '');
  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id, details) VALUES ($uid, '$uname', 'reset_payment', 'student', $sid, 'Payment reset to unpaid by admin')");
  header("Location: payments.php?success=Payment reset successfully"); exit();
}

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
  $sid         = intval($_POST['student_id']);
  $amount_paid = floatval($_POST['amount_paid']);
  $or_number   = trim($_POST['or_number'] ?? '');
  $pay_method  = in_array($_POST['payment_method'] ?? '', ['cash','bank_transfer','gcash','maya','other'])
                 ? $_POST['payment_method'] : 'cash';
  $paid_at     = $_POST['paid_at'] ?? date('Y-m-d');
  $sy_id_pay   = intval($_POST['school_year_id']);

  // Get all fees for this student's grade
  $student_grade = $conn->query("SELECT grade_level_id FROM students WHERE id=$sid")->fetch_assoc()['grade_level_id'] ?? 0;

  // Deduplicate fees by name
  $fees_raw = $conn->query("
    SELECT f.id, f.name, f.amount FROM fees f
    WHERE f.grade_level_id = $student_grade AND f.school_year_id = $sy_id_pay
    AND f.fee_type != 'sped'
    ORDER BY f.name
  ")->fetch_all(MYSQLI_ASSOC);

  $seen = [];
  $fees = [];
  foreach ($fees_raw as $f) {
    if (!isset($seen[$f['name']])) { $seen[$f['name']] = true; $fees[] = $f; }
  }

  $total_fees = array_sum(array_column($fees, 'amount'));
  $balance_remaining = max(0, $total_fees - $amount_paid);
  $status = $balance_remaining <= 0 ? 'paid' : ($amount_paid > 0 ? 'partial' : 'unpaid');

  // Distribute payment proportionally across fees
  $remaining = $amount_paid;
  foreach ($fees as $fee) {
    $fee_paid = min($remaining, $fee['amount']);
    $fee_bal  = max(0, $fee['amount'] - $fee_paid);
    $fee_status = $fee_bal <= 0 ? 'paid' : ($fee_paid > 0 ? 'partial' : 'unpaid');
    $remaining -= $fee_paid;

    $stmt = $conn->prepare("INSERT INTO payments (student_id, fee_id, amount_paid, balance, status, paid_at, or_number, payment_method)
      VALUES (?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE amount_paid=VALUES(amount_paid), balance=VALUES(balance),
        status=VALUES(status), paid_at=VALUES(paid_at), or_number=VALUES(or_number),
        payment_method=VALUES(payment_method)");
    $stmt->bind_param("iiddssss", $sid, $fee['id'], $fee_paid, $fee_bal, $fee_status, $paid_at, $or_number, $pay_method);
    $stmt->execute();
  }

  // Send email notification
  if ($or_number) {
    require_once '../mysql/email_notifications.php';
    $parent_pay = $conn->query("
      SELECT pa.email, pa.name, s.first_name, s.last_name
      FROM parent_accounts pa
      JOIN parent_student_links psl ON psl.parent_id = pa.id
      JOIN students s ON s.id = psl.student_id
      WHERE psl.student_id = $sid LIMIT 1
    ")->fetch_assoc();
    if ($parent_pay) {
      notifyPaymentReceived($parent_pay['email'], $parent_pay['name'],
        $parent_pay['first_name'].' '.$parent_pay['last_name'], $amount_paid, $or_number);
    }
  }

  header("Location: payments.php?success=Payment recorded successfully"); exit();
}

$active_sy  = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id      = $active_sy['id'] ?? 0;
$search     = $_GET['search'] ?? '';
$searchParam = "%$search%";

$where = "s.is_archived = 0";
if ($search) $where .= " AND (s.first_name LIKE '$searchParam' OR s.last_name LIKE '$searchParam' OR s.lrn LIKE '$searchParam')";

// Students with payment summary
$students_raw = $conn->query("
  SELECT s.id, s.first_name, s.last_name, s.lrn, g.name as grade, s.grade_level_id,
    COALESCE(SUM(p.amount_paid),0) as total_paid,
    (SELECT p2.proof_file FROM payments p2
     WHERE p2.student_id = s.id AND p2.proof_file IS NOT NULL AND p2.proof_file != ''
     LIMIT 1) as proof_file,
    (SELECT p2.payment_method FROM payments p2
     WHERE p2.student_id = s.id AND p2.proof_file IS NOT NULL AND p2.proof_file != ''
     LIMIT 1) as proof_method
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  LEFT JOIN payments p ON p.student_id = s.id
  WHERE $where
  GROUP BY s.id
  ORDER BY s.last_name ASC
")->fetch_all(MYSQLI_ASSOC);

// For each student, compute deduplicated total fees
$students_payments = [];
foreach ($students_raw as $s) {
  $fees_raw = $conn->query("
    SELECT name, amount FROM fees
    WHERE grade_level_id = {$s['grade_level_id']} AND school_year_id = $sy_id AND fee_type != 'sped'
    ORDER BY name
  ")->fetch_all(MYSQLI_ASSOC);

  // Deduplicate by name
  $seen = []; $total_fees = 0;
  foreach ($fees_raw as $f) {
    if (!isset($seen[$f['name']])) { $seen[$f['name']] = true; $total_fees += $f['amount']; }
  }

  $total_balance = max(0, $total_fees - $s['total_paid']);
  $pay_status = $total_fees == 0 ? 'unpaid'
    : ($total_balance <= 0 ? 'paid' : ($s['total_paid'] > 0 ? 'partial' : 'unpaid'));

  $students_payments[] = array_merge($s, [
    'total_fees'    => $total_fees,
    'total_balance' => $total_balance,
    'pay_status'    => $pay_status,
  ]);
}

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payments — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/payments.css">
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Payments</div>
      <div class="page-sub">SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></div>
    </div>
  </div>

  <div id="page-container">
    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <form method="GET" action="payments.php" class="payment-search-form">
      <div class="search-wrap">
        <i class="bi bi-search search-icon"></i>
        <input type="search" name="search" class="toolbar-search-input" placeholder="Search student name or LRN" value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <button type="submit" class="btn-search"><i class="bi bi-search"></i> Search</button>
      <?php if ($search): ?><a href="payments.php" class="btn-clear-filters"><i class="bi bi-x-circle"></i> Clear</a><?php endif; ?>
    </form>

    <div class="payments-table-card">
      <table class="payments-table">
        <thead>
          <tr><th>Student</th><th>LRN</th><th>Grade</th><th>Total Fees</th><th>Paid</th><th>Balance</th><th>Status</th><th>Receipt</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php $count = 0; foreach ($students_payments as $p): $count++; ?>
          <tr>
            <td><div class="name-cell">
              <div class="mini-avatar"><i class="bi bi-person-fill"></i></div>
              <span><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></span>
            </div></td>
            <td class="td-muted"><?= htmlspecialchars($p['lrn']) ?></td>
            <td><?= htmlspecialchars($p['grade'] ?? '—') ?></td>
            <td>₱<?= number_format($p['total_fees'], 2) ?></td>
            <td style="color:var(--color-success);font-weight:600;">₱<?= number_format($p['total_paid'], 2) ?></td>
            <td style="color:var(--color-danger);font-weight:600;">₱<?= number_format($p['total_balance'], 2) ?></td>
            <td><span class="pay-badge pay-<?= $p['pay_status'] ?>"><?= ucfirst($p['pay_status']) ?></span></td>
            <td>
              <?php if (!empty($p['proof_file'])): ?>
                <a href="uploads/<?= htmlspecialchars($p['proof_file']) ?>" target="_blank"
                   style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#dcfce7;color:#166534;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
                  <i class="bi bi-eye-fill"></i> View
                </a>
                <div style="font-size:10px;color:var(--color-muted);margin-top:2px;"><?= ucfirst(str_replace('_',' ',$p['proof_method'] ?? '')) ?></div>
              <?php else: ?>
                <span style="font-size:12px;color:var(--color-muted);">No receipt</span>
              <?php endif; ?>
            </td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="soa.php?student_id=<?= $p['id'] ?>" class="btn-view-sm" style="background:#eef0f8;color:var(--color-primary);">SOA</a>
              <button class="btn-pay-sm" onclick="openPayModal(
                <?= $p['id'] ?>,
                '<?= htmlspecialchars(addslashes($p['last_name'] . ', ' . $p['first_name'])) ?>',
                <?= $p['total_fees'] ?>,
                <?= $p['total_paid'] ?>,
                '<?= htmlspecialchars(addslashes($p['proof_method'] ?? 'cash')) ?>'
              )">
                <i class="bi bi-cash"></i> Record Payment
              </button>
              <?php if ($p['total_paid'] > 0): ?>
              <form method="POST" action="payments.php" style="display:inline;">
                <input type="hidden" name="action" value="reset_payment">
                <input type="hidden" name="student_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn-view-sm" style="background:#fdeaea;color:#dc2626;border:none;cursor:pointer;font-family:var(--font);"
                  onclick="return confirm('Reset all payments for this student to unpaid? This cannot be undone.')">
                  <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if ($count === 0): ?>
          <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--color-muted);">No students found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Payment Confirmation Modal -->
<div class="modal-overlay" id="payment-modal">
  <div class="modal-box" style="max-width:480px;">
    <div class="modal-header">
      <h2>Record Payment</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="payments.php">
      <input type="hidden" name="action" value="confirm_payment">
      <input type="hidden" name="student_id" id="modal-student-id">
      <input type="hidden" name="school_year_id" value="<?= $sy_id ?>">
      <div class="modal-body">
        <div style="font-size:14px;font-weight:700;margin-bottom:16px;" id="modal-student-name"></div>

        <div style="background:#f9fafb;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="color:var(--color-muted);">Total Fees</span>
            <span id="modal-total-fees" style="font-weight:600;"></span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span style="color:var(--color-muted);">Already Paid</span>
            <span id="modal-already-paid" style="font-weight:600;color:var(--color-success);"></span>
          </div>
        </div>

        <div class="form-grid" style="grid-template-columns:1fr;">
          <div class="form-group">
            <label>Amount Paid *</label>
            <input type="number" name="amount_paid" id="modal-amount" class="form-input" step="0.01" min="0" required placeholder="0.00"/>
          </div>
          <div class="form-group">
            <label>Payment Method *</label>
            <select name="payment_method" id="modal-method" class="form-input" required>
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="maya">Maya</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>OR Number <span style="font-weight:400;color:var(--color-muted);">(from receipt)</span></label>
            <input type="text" name="or_number" class="form-input" placeholder="Official Receipt No."/>
          </div>
          <div class="form-group">
            <label>Date Paid</label>
            <input type="date" name="paid_at" class="form-input" value="<?= date('Y-m-d') ?>"/>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Confirm Payment</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/nav.js"></script>
<script>
  const modal = document.getElementById('payment-modal');
  document.getElementById('modal-close').onclick  = () => modal.classList.remove('open');
  document.getElementById('modal-cancel').onclick = () => modal.classList.remove('open');

  function openPayModal(studentId, name, totalFees, alreadyPaid, method) {
    document.getElementById('modal-student-id').value   = studentId;
    document.getElementById('modal-student-name').textContent = name;
    document.getElementById('modal-total-fees').textContent   = '₱' + totalFees.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('modal-already-paid').textContent = '₱' + alreadyPaid.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('modal-amount').value = (totalFees - alreadyPaid).toFixed(2);

    // Pre-select payment method from uploaded receipt
    const sel = document.getElementById('modal-method');
    if (method && sel.querySelector('option[value="' + method + '"]')) {
      sel.value = method;
    }

    modal.classList.add('open');
  }
</script>
</body>
</html>
