<?php
session_start();
include('../mysql/db.php');
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
// Finance and registrar/superadmin can access payments
if (!in_array($_SESSION['role'] ?? '', ['finance','registrar','superadmin'])) {
  header('Location: dashboard.php'); exit();
}

// Handle payment save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $student_id  = intval($_POST['student_id']);
  $fee_id      = intval($_POST['fee_id']);
  $amount_paid = floatval($_POST['amount_paid']);
  $or_number   = trim($_POST['or_number'] ?? '');
  $notes       = trim($_POST['notes'] ?? '');
  $paid_at     = $_POST['paid_at'] ?? date('Y-m-d');
  $pay_method  = in_array($_POST['payment_method'] ?? '', ['cash','check','bank_transfer','gcash','maya','other'])
                 ? $_POST['payment_method'] : 'cash';
  $payment_plan = in_array($_POST['payment_plan'] ?? '', ['annual','semi_annual','quarterly','monthly'])
                  ? $_POST['payment_plan'] : 'annual';
  $surcharge   = floatval($_POST['surcharge'] ?? 0);

  $fee        = $conn->query("SELECT amount FROM fees WHERE id=$fee_id")->fetch_assoc();
  $fee_amount = $fee ? floatval($fee['amount']) : 0;
  $balance    = max(0, $fee_amount - $amount_paid);
  $status     = $balance <= 0 ? 'paid' : ($amount_paid > 0 ? 'partial' : 'unpaid');

  // Proof of payment upload (3MB limit)
  $proof_file = '';
  if (!empty($_FILES['proof_file']['tmp_name'])) {
    $allowed  = ['image/jpeg','image/png','image/webp','application/pdf'];
    $max_size = 3 * 1024 * 1024;
    if (in_array($_FILES['proof_file']['type'], $allowed) && $_FILES['proof_file']['size'] <= $max_size) {
      $proof_file = 'proof_' . uniqid() . '_' . basename($_FILES['proof_file']['name']);
      move_uploaded_file($_FILES['proof_file']['tmp_name'], "uploads/" . $proof_file);
    }
  }

  $stmt = $conn->prepare("INSERT INTO payments (student_id, fee_id, amount_paid, balance, status, paid_at, or_number, payment_method, payment_plan, surcharge, proof_file, notes)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE amount_paid=VALUES(amount_paid), balance=VALUES(balance),
      status=VALUES(status), paid_at=VALUES(paid_at), or_number=VALUES(or_number),
      payment_method=VALUES(payment_method), payment_plan=VALUES(payment_plan),
      surcharge=VALUES(surcharge),
      proof_file=IF(VALUES(proof_file)!='',VALUES(proof_file),proof_file),
      notes=VALUES(notes)");
  $stmt->bind_param("iiddsssssdss", $student_id, $fee_id, $amount_paid, $balance, $status, $paid_at, $or_number, $pay_method, $payment_plan, $surcharge, $proof_file, $notes);
  $stmt->execute()
    ? header("Location: payments.php?success=Payment recorded")
    : header("Location: payments.php?error=" . urlencode($conn->error));

  if ($stmt->affected_rows >= 0 && $or_number) {
    require_once '../mysql/email_notifications.php';
    $parent_pay = $conn->query("SELECT pa.email, pa.name, s.first_name, s.last_name FROM parent_accounts pa JOIN students s ON s.id=pa.student_id WHERE pa.student_id=$student_id LIMIT 1")->fetch_assoc();
    if ($parent_pay) {
      notifyPaymentReceived($parent_pay['email'], $parent_pay['name'], $parent_pay['first_name'].' '.$parent_pay['last_name'], $amount_paid, $or_number);
    }
  }
  exit();
}

$active_sy  = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id      = $active_sy['id'] ?? 0;
$filter_status = $_GET['status'] ?? '';
$search     = $_GET['search'] ?? '';
$searchParam = "%$search%";

// Students with payment summary
$where = "s.is_archived = 0";
if ($search) $where .= " AND (s.first_name LIKE '$searchParam' OR s.last_name LIKE '$searchParam' OR s.lrn LIKE '$searchParam')";

$students_payments = $conn->query("
  SELECT s.id, s.first_name, s.last_name, s.lrn, s.photo, g.name as grade,
    COALESCE(SUM(p.amount_paid),0) as total_paid,
    COALESCE(SUM(p.balance),0) as total_balance,
    COALESCE(SUM(f.amount),0) as total_fees,
    CASE
      WHEN COUNT(p.id) = 0 THEN 'unpaid'
      WHEN SUM(p.balance) <= 0 THEN 'paid'
      ELSE 'partial'
    END as pay_status
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  LEFT JOIN payments p ON p.student_id = s.id
  LEFT JOIN fees f ON f.id = p.fee_id
  WHERE $where
  GROUP BY s.id
  ORDER BY s.last_name ASC
");

// For modal — students and fees
$all_students = $conn->query("SELECT s.id, s.first_name, s.last_name, s.lrn FROM students s WHERE s.is_archived=0 ORDER BY s.last_name")->fetch_all(MYSQLI_ASSOC);
$all_fees     = $conn->query("SELECT f.id, f.name, f.amount, g.name as grade FROM fees f JOIN grade_levels g ON f.grade_level_id=g.id ORDER BY g.id, f.name")->fetch_all(MYSQLI_ASSOC);

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
      <div class="page-sub">Track student fees and collections</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-topbar" id="btn-add-payment"><i class="bi bi-plus-lg"></i> Record Payment</button>
    </div>
  </div>

  <div id="page-container">

    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <!-- Search -->
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
          <tr><th>Student</th><th>LRN</th><th>Grade</th><th>Total Fees</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php $count = 0; while ($p = $students_payments->fetch_assoc()): $count++; ?>
          <tr>
            <td><div class="name-cell">
              <?php if (!empty($p['photo'])): ?><img src="uploads/<?= htmlspecialchars($p['photo']) ?>" class="mini-pic"/>
              <?php else: ?><div class="mini-avatar"><i class="bi bi-person-fill"></i></div><?php endif; ?>
              <span><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></span>
            </div></td>
            <td class="td-muted"><?= htmlspecialchars($p['lrn']) ?></td>
            <td><?= htmlspecialchars($p['grade'] ?? '—') ?></td>
            <td>₱<?= number_format($p['total_fees'], 2) ?></td>
            <td style="color:var(--color-success);font-weight:600;">₱<?= number_format($p['total_paid'], 2) ?></td>
            <td style="color:var(--color-danger);font-weight:600;">₱<?= number_format($p['total_balance'], 2) ?></td>
            <td><span class="pay-badge pay-<?= $p['pay_status'] ?>"><?= ucfirst($p['pay_status']) ?></span></td>
            <td>
              <a href="student_profile.php?id=<?= $p['id'] ?>" class="btn-view-sm">View</a>
              <a href="soa.php?student_id=<?= $p['id'] ?>" class="btn-view-sm" style="background:#dcfce7;color:#166534;">SOA</a>
              <button class="btn-pay-sm" onclick="openPayModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['last_name'] . ', ' . $p['first_name'])) ?>')">
                <i class="bi bi-cash"></i> Pay
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($count === 0): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--color-muted);">No students found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payment-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Record Payment</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="payments.php" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr;">
          <div class="form-group">
            <label>Student *</label>
            <select name="student_id" id="modal-student" class="form-input" required>
              <option value="">Select student</option>
              <?php foreach ($all_students as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' — ' . $s['lrn']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Fee *</label>
            <select name="fee_id" class="form-input" required>
              <option value="">Select fee</option>
              <?php foreach ($all_fees as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['grade'] . ' — ' . $f['name'] . ' (₱' . number_format($f['amount'],2) . ')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Method *</label>
            <select name="payment_method" class="form-input" required>
              <option value="cash">Cash</option>
              <option value="check">Check</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Plan</label>
            <select name="payment_plan" class="form-input">
              <option value="annual">Annual (no surcharge)</option>
              <option value="semi_annual">Semi-Annual</option>
              <option value="quarterly">Quarterly</option>
              <option value="monthly">Monthly (with surcharge)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Surcharge / Penalty (₱)</label>
            <input type="number" name="surcharge" class="form-input" step="0.01" min="0" value="0.00" placeholder="0.00"/>
          </div>
          <div class="form-group">
            <label>Amount Paid *</label>
            <input type="number" name="amount_paid" class="form-input" step="0.01" min="0" required placeholder="0.00"/>
          </div>
          <div class="form-group">
            <label>Date Paid</label>
            <input type="date" name="paid_at" class="form-input" value="<?= date('Y-m-d') ?>"/>
          </div>
          <div class="form-group">
            <label>OR Number</label>
            <input type="text" name="or_number" class="form-input" placeholder="Official Receipt No."/>
          </div>
          <div class="form-group">
            <label>Proof of Payment <span style="font-weight:400;color:var(--color-muted)">(image or PDF)</span></label>
            <input type="file" name="proof_file" class="form-input" accept="image/*,.pdf"/>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes" class="form-input" placeholder="Optional notes"/>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Save Payment</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/nav.js"></script>
<script>
  const modal = document.getElementById('payment-modal');
  document.getElementById('btn-add-payment').onclick = () => modal.classList.add('open');
  document.getElementById('modal-close').onclick     = () => modal.classList.remove('open');
  document.getElementById('modal-cancel').onclick    = () => modal.classList.remove('open');

  function openPayModal(studentId, name) {
    modal.classList.add('open');
    const sel = document.getElementById('modal-student');
    sel.value = studentId;
  }
</script>
</body>
</html>
