<?php
$active_portal = 'soa';
require_once 'includes/auth.php';
require_once '../mysql/helpers.php';

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;
$student   = $conn->query("SELECT s.*, g.name as grade FROM students s LEFT JOIN grade_levels g ON s.grade_level_id=g.id WHERE s.id=$student_id")->fetch_assoc();

if (!$student) {
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    </head><body>';
  include('includes/nav.php');
  echo '<div class="portal-container" style="text-align:center;padding:60px 20px;">
    <i class="bi bi-exclamation-circle" style="font-size:48px;color:#d97706;"></i>
    <h3 style="margin-top:16px;">No Student Linked</h3>
    <p style="color:#6b7280;font-size:14px;">Please wait for the registrar to process your enrollment application.</p>
    <a href="dashboard.php" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#494C8A;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Back to Dashboard</a>
  </div></body></html>';
  exit();
}

// Gate: require a payment scheme before showing the SOA
$enrollment = $conn->query("SELECT * FROM enrollments WHERE student_id=$student_id AND school_year_id=$sy_id LIMIT 1")->fetch_assoc();
$scheme_selected = !empty($enrollment['payment_plan']);
if (!$scheme_selected) {  echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <link rel="stylesheet" href="../css/portal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    </head><body>';
  include('includes/nav.php');
  echo '<div class="portal-container" style="text-align:center;padding:60px 20px;">
    <i class="bi bi-wallet2" style="font-size:48px;color:#494C8A;"></i>
    <h3 style="margin-top:16px;">Payment Scheme Required</h3>
    <p style="color:#6b7280;font-size:14px;max-width:380px;margin:8px auto 0;">
      You need to select a payment scheme before you can view your Statement of Account.
    </p>
    <a href="payment_scheme.php" style="display:inline-block;margin-top:24px;padding:10px 24px;background:#494C8A;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
      <i class="bi bi-arrow-right-circle-fill"></i> Choose Payment Scheme
    </a>
  </div></body></html>';
  exit();
}

$success = $error = '';

// Handle single proof upload for entire payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proof'])) {
  $pay_method = trim($_POST['pay_method'] ?? 'cash');

  // Server-side lock: check if a pending/confirmed proof already exists
  $existing_proof_check = $conn->query("
    SELECT proof_file, status FROM payments
    WHERE student_id=$student_id AND proof_file IS NOT NULL AND proof_file != ''
    ORDER BY id DESC LIMIT 1
  ")->fetch_assoc();

  $locked = $existing_proof_check &&
            !in_array($existing_proof_check['status'], ['rejected', null]);

  if ($locked) {
    $error = "A receipt is already pending verification. You cannot upload another until it is reviewed.";
  } elseif (empty($_FILES['proof']['tmp_name'])) {
    $error = "Please select a file to upload.";
  } else {
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    // Server-side MIME check using finfo
    $finfo_soa = new finfo(FILEINFO_MIME_TYPE);
    $real_mime_soa = $finfo_soa->file($_FILES['proof']['tmp_name']);
    if (!in_array($real_mime_soa, $allowed)) {
      $error = "Only JPG, PNG, WEBP, or PDF allowed.";
    } elseif ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
      $error = "File must be under 5MB.";
    } else {
      $upload_path = __DIR__ . '/../pages/uploads/';
      if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);
      // Derive extension from verified MIME type, not original filename
      $soa_mime_ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
      $fname = 'proof_' . $student_id . '_' . uniqid() . '.' . $soa_mime_ext[$real_mime_soa];
      move_uploaded_file($_FILES['proof']['tmp_name'], $upload_path . $fname);

      // Apply proof to all unpaid fees for this student
      $fees_to_pay = $conn->query("
        SELECT f.id as fee_id, f.amount FROM fees f
        WHERE f.grade_level_id = {$student['grade_level_id']} AND f.school_year_id = $sy_id
        AND f.fee_type != 'sped'
      ")->fetch_all(MYSQLI_ASSOC);

      foreach ($fees_to_pay as $f) {
        $existing = $conn->query("SELECT id FROM payments WHERE student_id=$student_id AND fee_id={$f['fee_id']} LIMIT 1")->fetch_assoc();
        if ($existing) {
          $upd = $conn->prepare("UPDATE payments SET proof_file=?, payment_method=? WHERE id=?");
          $upd->bind_param("ssi", $fname, $pay_method, $existing['id']);
          $upd->execute();
        } else {
          $stmt = $conn->prepare("INSERT INTO payments (student_id, fee_id, amount_paid, balance, status, payment_method, proof_file) VALUES (?,?,0,?,'unpaid',?,?)");
          $stmt->bind_param("iidss", $student_id, $f['fee_id'], $f['amount'], $pay_method, $fname);
          $stmt->execute();
        }
      }
      $success = "Proof of payment uploaded successfully. The finance staff will verify it shortly.";

      // Notify all admin staff
      $student_name_notif = $student['first_name'] . ' ' . $student['last_name'];
      $method_label = ucfirst(str_replace('_', ' ', $pay_method));
      notify_staff($conn, ['superadmin','registrar'], 'info',
        "Payment Receipt Uploaded: $student_name_notif",
        "$student_name_notif uploaded a proof of payment via $method_label. Please review in Payments.",
        "payments.php"
      );
    }
  }
}

// Use centralized helper — single source of truth
$soa = get_fee_rows_with_payment($conn, $student_id, $student['grade_level_id'], $sy_id);
$fees_payments   = $soa['rows'];
$total_fees      = $soa['total_fees'];
$total_discount  = $soa['total_discount'];
$net_fees        = $soa['net_fees'];
$total_paid      = $soa['total_paid'];
$total_bal       = $soa['total_bal'];   // can be negative (overpayment)
$discounts       = $soa['discounts'];
$existing_proof  = $soa['pay_details']['proof_file'] ?? null;
$existing_method = $soa['pay_details']['payment_method'] ?? null;
// Determine if upload is locked: locked when proof exists and payment is pending/confirmed (not rejected)
$proof_status = null;
if ($existing_proof) {
  $ps = $conn->query("SELECT status FROM payments WHERE student_id=$student_id AND proof_file IS NOT NULL AND proof_file != '' ORDER BY id DESC LIMIT 1");
  if ($ps) $proof_status = $ps->fetch_assoc()['status'] ?? null;
}
// Allow re-upload only if no proof yet, or if status is 'rejected'
$can_upload = !$existing_proof || $proof_status === 'rejected';

// Discounts
$discounts = $conn->query("
  SELECT d.type, d.percentage, COALESCE(d.fixed_amount,0) as fixed_amount, d.label
  FROM discounts d WHERE d.student_id = $student_id AND d.school_year_id = $sy_id
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Statement of Account — Parent Portal</title>
  <link rel="icon" type="image/x-icon" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal.css">
</head>
<body>
<?php include('includes/nav.php'); ?>
<div class="portal-container">

  <div class="portal-page-header">
    <h2>Statement of Account</h2>
    <p><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
       · <?= htmlspecialchars($student['grade'] ?? '') ?>
       · SY <?= htmlspecialchars($active_sy['label'] ?? '') ?></p>
  </div>

  <?php if ($success): ?>
  <div style="background:#f0fdf4;border-left:3px solid #16a34a;border-radius:6px;padding:12px 16px;font-size:13px;font-weight:500;color:#166534;margin-bottom:16px;">
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div style="background:#fff5f5;border-left:3px solid #f87171;border-radius:6px;padding:12px 16px;font-size:13px;font-weight:500;color:#b91c1c;margin-bottom:16px;">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Summary cards -->
  <div class="soa-summary">
    <div class="soa-sum-item">
      <div class="soa-sum-label">Total Fees</div>
      <div class="soa-sum-val">₱<?= number_format($net_fees, 2) ?></div>
    </div>
    <div class="soa-sum-item soa-paid">
      <div class="soa-sum-label">Total Paid</div>
      <div class="soa-sum-val">₱<?= number_format($total_paid, 2) ?></div>
    </div>
    <div class="soa-sum-item <?= $total_bal < 0 ? 'soa-paid' : 'soa-balance' ?>">
      <div class="soa-sum-label"><?= $total_bal < 0 ? 'Overpayment' : 'Balance' ?></div>
      <div class="soa-sum-val" style="color:<?= $total_bal < 0 ? 'var(--portal-success)' : 'var(--portal-danger)' ?>;">
        <?= $total_bal < 0 ? '-' : '' ?>₱<?= number_format(abs($total_bal), 2) ?>
        <?php if ($total_bal < 0): ?><div style="font-size:11px;font-weight:500;margin-top:2px;">School owes you this amount</div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Fee Breakdown Table -->
  <div class="soa-table-card" style="margin-bottom:24px;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700;">
      Fee Breakdown
    </div>
    <table class="soa-table">
      <thead>
        <tr>
          <th>Fee</th>
          <th>Original</th>
          <th>After Discount</th>
          <th>Paid</th>
          <th>Balance</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fees_payments as $fp): ?>
        <tr>
          <td style="font-weight:600;"><?= htmlspecialchars($fp['fee_name']) ?></td>
          <td>₱<?= number_format($fp['amount'], 2) ?></td>
          <td style="font-weight:600;">₱<?= number_format($fp['net_amount'] ?? $fp['amount'], 2) ?></td>
          <td style="color:var(--portal-success);font-weight:600;">₱<?= number_format($fp['amount_paid'] ?? 0, 2) ?></td>
          <td style="color:<?= ($fp['balance'] ?? 0) > 0 ? 'var(--portal-danger)' : 'var(--portal-success)' ?>;font-weight:600;">
            ₱<?= number_format($fp['balance'] ?? 0, 2) ?>
          </td>
          <td>
            <span class="soa-badge soa-<?= $fp['status'] ?? 'unpaid' ?>"><?= ucfirst($fp['status'] ?? 'Unpaid') ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!empty($discounts)): ?>
          <tr style="background:#f0fdf4;">
            <td colspan="6" style="padding:10px 16px;">
              <strong style="color:#16a34a;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">Discounts Applied</strong>
            </td>
          </tr>
          <?php foreach ($discounts as $d): ?>
          <tr style="background:#f0fdf4;">
            <td style="color:#16a34a;font-weight:600;padding-left:24px;"><?= htmlspecialchars($d['label'] ?: ucfirst($d['type'])) ?></td>
            <td colspan="3"></td>
            <td style="color:#16a34a;font-weight:700;">
              -<?= !empty($d['fixed_amount']) && $d['fixed_amount'] > 0 ? '₱'.number_format($d['fixed_amount'],2) : $d['percentage'].'%' ?>
            </td>
            <td></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (empty($fees_payments)): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:#6b7280;">No fee records found for this school year.</td></tr>
        <?php endif; ?>
        <!-- Totals row -->
        <?php if (!empty($fees_payments)): ?>
        <tr style="background:#f9fafb;font-weight:700;border-top:2px solid var(--border);">
          <td>Total</td>
          <td>₱<?= number_format($total_fees, 2) ?></td>
          <td style="color:var(--portal-success);">₱<?= number_format($total_paid, 2) ?></td>
          <td style="color:<?= $total_bal > 0 ? 'var(--portal-danger)' : 'var(--portal-success)' ?>;">₱<?= number_format($total_bal, 2) ?></td>
          <td></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Payment Instructions -->
  <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:16px 20px;margin-bottom:24px;">
    <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:8px;"><i class="bi bi-bank"></i> Payment Instructions</div>
    <div style="font-size:13px;color:#374151;line-height:1.9;">
      <strong>GCash:</strong> 09XX-XXX-XXXX (COJ Catholic Progressive School)<br>
      <strong>Bank Transfer:</strong> BDO Savings — Account Name: COJ Catholic Progressive School · Account No: 1234-5678-90<br>
      <strong>Cash:</strong> Pay at the Finance Office, present this SOA as reference.
    </div>
  </div>

  <!-- Single Upload Section -->
  <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:24px;">
    <div style="font-size:15px;font-weight:700;margin-bottom:6px;">Upload Proof of Payment</div>
    <div style="font-size:13px;color:#6b7280;margin-bottom:12px;">
      Upload one receipt/screenshot that covers your payment. The finance staff will verify and update your balance.
    </div>

    <!-- File requirements notice -->
    <div style="background:#eef0f8;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#374151;display:flex;align-items:flex-start;gap:10px;">
      <i class="bi bi-info-circle-fill" style="color:#494C8A;flex-shrink:0;margin-top:2px;"></i>
      <div>
        <strong>Accepted formats:</strong> JPG, PNG, WEBP, PDF<br>
        <strong>Maximum file size:</strong> 5MB<br>
        <strong>Tip:</strong> Make sure the receipt clearly shows the amount, date, and reference number.
      </div>
    </div>

    <?php if ($existing_proof): ?>
    <div style="background:#f0fdf4;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <div style="font-size:13px;color:#166534;font-weight:600;">
        <i class="bi bi-check-circle-fill"></i>
        Receipt uploaded
        <?php if ($existing_method): ?>
          via <strong><?= ucfirst(str_replace('_',' ',$existing_method)) ?></strong>
        <?php endif; ?>
      </div>
      <a href="../pages/uploads/<?= htmlspecialchars($existing_proof) ?>" target="_blank" class="portal-btn-view">
        <i class="bi bi-eye-fill"></i> View Receipt
      </a>
    </div>
    <?php if ($can_upload): ?>
    <div style="font-size:12px;color:#b91c1c;font-weight:600;margin-bottom:12px;"><i class="bi bi-exclamation-triangle-fill"></i> Your receipt was rejected. Please upload a new one.</div>
    <?php else: ?>
    <div style="font-size:12px;color:#6b7280;margin-bottom:12px;"><i class="bi bi-clock"></i> Your receipt is pending verification by the finance staff. You cannot re-upload until it is reviewed.</div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($can_upload): ?>
    <form method="POST" action="soa.php" enctype="multipart/form-data">
      <input type="hidden" name="submit_proof" value="1">

      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Payment Method</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php
          $methods = ['gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer', 'cash' => 'Cash'];
          foreach ($methods as $val => $label):
            $checked = ($existing_method === $val) ? 'checked' : ($val === 'gcash' && !$existing_method ? 'checked' : '');
          ?>
          <label style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid <?= $checked ? '#494C8A' : 'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;background:<?= $checked ? '#eef0f8' : '#fff' ?>;">
            <input type="radio" name="pay_method" value="<?= $val ?>" <?= $checked ?> style="accent-color:#494C8A;"/>
            <?= $label ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">
          Receipt / Screenshot <span style="font-weight:400;color:#6b7280;">(JPG, PNG, PDF · max 5MB)</span>
        </label>
        <input type="file" name="proof" accept="image/*,.pdf"
               style="display:block;border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;width:100%;max-width:400px;background:#fafafa;"/>
      </div>

      <button type="submit" style="padding:11px 28px;background:#494C8A;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:var(--font);">
        <i class="bi bi-upload"></i> Submit Proof of Payment
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div class="portal-req-note">
    <i class="bi bi-info-circle-fill"></i>
    For payment concerns, contact the Finance Office directly.
    Accepted formats: JPG, PNG, PDF · Max 5MB.
  </div>
</div>
</body>
</html>
