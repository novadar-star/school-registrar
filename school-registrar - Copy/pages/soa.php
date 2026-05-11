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

// Use centralized helper — single source of truth
$soa = get_fee_rows_with_payment($conn, $student_id, $student['grade_level_id'], $sy_id);
$fees_payments = $soa['rows'];
$total_fees    = $soa['total_fees'];
$total_paid    = $soa['total_paid'];
$total_bal     = $soa['total_bal'];
$pay_details   = $soa['pay_details'];

// Discounts
$discounts = $conn->query("SELECT * FROM discounts WHERE student_id=$student_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
$total_discount_pct = array_sum(array_column($discounts, 'percentage'));
$discount_amount    = $total_fees * ($total_discount_pct / 100);
$adjusted_total     = max(0, $total_fees - $discount_amount);
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
        <div class="soa-student-field"><span>Reference #</span><?= htmlspecialchars($enrollment['ref_number'] ?? '—') ?></div>
        <div class="soa-student-field"><span>Date Generated</span><?= date('F j, Y') ?></div>
      </div>

      <table class="soa-table">
        <thead><tr><th>Fee</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Method</th><th>Date Paid</th></tr></thead>
        <tbody>
          <?php foreach ($fees_payments as $fp): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($fp['fee_name']) ?></td>
            <td>₱<?= number_format($fp['amount'], 2) ?></td>
            <td>₱<?= number_format($fp['amount_paid'] ?? 0, 2) ?></td>
            <td style="font-weight:600;color:<?= ($fp['balance'] ?? $fp['amount']) > 0 ? '#dc2626' : '#16a34a' ?>">₱<?= number_format($fp['balance'] ?? $fp['amount'], 2) ?></td>
            <td><span class="badge-<?= $fp['status'] ?? 'unpaid' ?>"><?= ucfirst($fp['status'] ?? 'Unpaid') ?></span></td>
            <td><?= !empty($pay_details['payment_method']) ? ucfirst(str_replace('_',' ',$pay_details['payment_method'])) : '—' ?></td>
            <td><?= !empty($pay_details['paid_at']) ? date('M j, Y', strtotime($pay_details['paid_at'])) : '—' ?></td>
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
</body>
</html>
