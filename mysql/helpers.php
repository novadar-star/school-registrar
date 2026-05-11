<?php
/**
 * helpers.php — Shared utility functions
 */

/**
 * Enforce role-based access. Call at the top of any admin page.
 * Redirects to dashboard if the logged-in user's role is not in $allowed.
 * Pass an empty array to allow any authenticated user.
 *
 * @param array $allowed  e.g. ['superadmin','registrar']
 */
function requireRole(array $allowed): void {
  if (empty($allowed)) return; // any authenticated user is fine
  $role = $_SESSION['role'] ?? '';
  if (!in_array($role, $allowed, true)) {
    header('Location: dashboard.php');
    exit();
  }
}

/**
 * Get deduplicated fees for a grade level + school year.
 */
function get_fees($conn, int $grade_level_id, int $sy_id): array {
  $raw = $conn->query("
    SELECT id as fee_id, name as fee_name, amount, fee_type
    FROM fees
    WHERE grade_level_id = $grade_level_id AND school_year_id = $sy_id
    AND fee_type != 'sped'
    ORDER BY FIELD(fee_type,'tuition','miscellaneous','pta_fund','development','books','reservation','other'), name
  ")->fetch_all(MYSQLI_ASSOC);

  $seen = []; $fees = [];
  foreach ($raw as $f) {
    if (!isset($seen[$f['fee_name']])) {
      $seen[$f['fee_name']] = true;
      $fees[] = $f;
    }
  }
  return ['fees' => $fees, 'total' => array_sum(array_column($fees, 'amount'))];
}

/**
 * Get total amount paid for a student.
 */
function get_total_paid($conn, int $student_id): float {
  $row = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as paid FROM payments WHERE student_id=$student_id")->fetch_assoc();
  return floatval($row['paid'] ?? 0);
}

/**
 * Get student balance = total_fees - total_paid.
 */
function get_balance($conn, int $student_id, int $grade_level_id, int $sy_id): float {
  $fee_data = get_fees($conn, $grade_level_id, $sy_id);
  $paid     = get_total_paid($conn, $student_id);
  return max(0, $fee_data['total'] - $paid);
}

/**
 * Get fee rows with paid/balance/status distributed proportionally.
 * Single source of truth used by admin SOA, parent SOA, and student profile.
 */
function get_fee_rows_with_payment($conn, int $student_id, int $grade_level_id, int $sy_id): array {
  $fee_data   = get_fees($conn, $grade_level_id, $sy_id);
  $total_paid = get_total_paid($conn, $student_id);

  $pay_details = $conn->query("
    SELECT payment_method, or_number, paid_at, proof_file
    FROM payments WHERE student_id=$student_id AND amount_paid > 0
    ORDER BY paid_at DESC LIMIT 1
  ")->fetch_assoc();

  $remaining = $total_paid;
  $rows = [];
  foreach ($fee_data['fees'] as $fp) {
    if ($remaining >= $fp['amount']) {
      $fp['amount_paid'] = $fp['amount'];
      $fp['balance']     = 0;
      $fp['status']      = 'paid';
      $remaining        -= $fp['amount'];
    } elseif ($remaining > 0) {
      $fp['amount_paid'] = $remaining;
      $fp['balance']     = $fp['amount'] - $remaining;
      $fp['status']      = 'partial';
      $remaining         = 0;
    } else {
      $fp['amount_paid'] = 0;
      $fp['balance']     = $fp['amount'];
      $fp['status']      = 'unpaid';
    }
    $fp['payment_method'] = $pay_details['payment_method'] ?? null;
    $fp['paid_at']        = $pay_details['paid_at'] ?? null;
    $fp['proof_file']     = $pay_details['proof_file'] ?? null;
    $rows[] = $fp;
  }

  return [
    'rows'        => $rows,
    'total_fees'  => $fee_data['total'],
    'total_paid'  => $total_paid,
    'total_bal'   => max(0, $fee_data['total'] - $total_paid),
    'pay_details' => $pay_details,
  ];
}

/**
 * Send a notification to all staff users of given roles.
 */
function notify_staff($conn, array $roles, string $type, string $title, string $body = '', string $link = '') {
  $roles_esc = implode("','", array_map(fn($r) => $conn->real_escape_string($r), $roles));
  $admins = $conn->query("SELECT id FROM users WHERE is_active=1 AND role IN ('$roles_esc')");
  if (!$admins) return;
  $title_esc = $conn->real_escape_string($title);
  $body_esc  = $conn->real_escape_string($body);
  $link_esc  = $conn->real_escape_string($link);
  while ($a = $admins->fetch_assoc()) {
    $conn->query("INSERT INTO notifications (user_id, type, title, body, link) VALUES ({$a['id']}, '$type', '$title_esc', '$body_esc', '$link_esc')");
  }
}

/**
 * Send a notification to a parent account.
 */
function notify_parent($conn, int $parent_id, int $student_id, string $type, string $title, string $body = '') {
  $title_esc = $conn->real_escape_string($title);
  $body_esc  = $conn->real_escape_string($body);
  $conn->query("INSERT INTO parent_notifications (parent_id, student_id, type, title, body) VALUES ($parent_id, $student_id, '$type', '$title_esc', '$body_esc')");
}

/**
 * Log an audit entry.
 */
function audit_log($conn, int $user_id, string $user_name, string $action, string $target = '', int $target_id = 0, string $details = '') {
  $uname   = $conn->real_escape_string($user_name);
  $act     = $conn->real_escape_string($action);
  $tgt     = $conn->real_escape_string($target);
  $det     = $conn->real_escape_string($details);
  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id, details) VALUES ($user_id, '$uname', '$act', '$tgt', $target_id, '$det')");
}

/**
 * Get tagged filename for download.
 */
function tagged_filename(string $student_name, string $doc_name, string $file_path): string {
  $ext  = pathinfo($file_path, PATHINFO_EXTENSION);
  $safe = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $student_name . ' - ' . $doc_name);
  return trim($safe) . '.' . $ext;
}

/**
 * compute_fees — used by portal/dashboard.php fee preview widget.
 */
function compute_fees($conn, int $student_id, int $grade_level_id, int $sy_id): array {
  $fees = $conn->query("SELECT * FROM fees WHERE grade_level_id=$grade_level_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
  $breakdown = ['tuition'=>0,'miscellaneous'=>0,'pta_fund'=>0,'development'=>0,'books'=>0,'sped'=>0,'reservation'=>0,'other'=>0];
  foreach ($fees as $f) {
    $type = $f['fee_type'] ?? 'other';
    $breakdown[$type] = ($breakdown[$type] ?? 0) + floatval($f['amount']);
  }
  $subtotal = array_sum($breakdown);
  $discounts = $conn->query("SELECT * FROM discounts WHERE student_id=$student_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
  $discount_amount = 0;
  foreach ($discounts as $d) {
    if (!empty($d['fixed_amount'])) $discount_amount += floatval($d['fixed_amount']);
    else $discount_amount += $subtotal * (floatval($d['percentage']) / 100);
  }
  $discount_amount = min($discount_amount, $subtotal);
  $total       = max(0, $subtotal - $discount_amount);
  $reservation = $breakdown['reservation'] ?? 5000;
  $plans = [
    'annual'      => ['installments'=>1,  'label'=>'Annual',      'amount'=>$total],
    'semi_annual' => ['installments'=>2,  'label'=>'Semi-Annual', 'amount'=>round($total/2,2)],
    'quarterly'   => ['installments'=>4,  'label'=>'Quarterly',   'amount'=>round($total/4,2)],
    'monthly'     => ['installments'=>10, 'label'=>'Monthly',     'amount'=>round($total/10,2)],
  ];
  return compact('breakdown','subtotal','discount_amount','total','plans','reservation','discounts');
}
