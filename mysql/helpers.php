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
 * Discounts apply to TUITION FEE ONLY (not miscellaneous or other fees).
 * If total_paid > net_fees, balance is negative (school owes parent).
 */
function get_fee_rows_with_payment($conn, int $student_id, int $grade_level_id, int $sy_id): array {
  $fee_data   = get_fees($conn, $grade_level_id, $sy_id);
  $total_paid = get_total_paid($conn, $student_id);

  // Get tuition fee total (discount base)
  $tuition_total = 0;
  foreach ($fee_data['fees'] as $f) {
    if (($f['fee_type'] ?? '') === 'tuition') {
      $tuition_total += floatval($f['amount']);
    }
  }

  // Fetch discounts and compute total discount amount against tuition only
  $discounts_raw  = $conn->query("SELECT * FROM discounts WHERE student_id=$student_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
  $total_discount = compute_tuition_discount($discounts_raw, $tuition_total);
  $net_fees = $fee_data['total'] - $total_discount;

  $pay_details = $conn->query("
    SELECT payment_method, or_number, paid_at, proof_file
    FROM payments WHERE student_id=$student_id AND amount_paid > 0
    ORDER BY paid_at DESC LIMIT 1
  ")->fetch_assoc();

  $remaining = $total_paid;
  $rows = [];
  foreach ($fee_data['fees'] as $fp) {
    // Apply discount only to tuition rows
    $fee_discount = 0;
    if (($fp['fee_type'] ?? '') === 'tuition' && $tuition_total > 0) {
      $fee_discount = ($fp['amount'] / $tuition_total) * $total_discount;
    }
    $net_amount = round($fp['amount'] - $fee_discount, 2);

    if ($remaining >= $net_amount) {
      $fp['amount_paid'] = $net_amount;
      $fp['balance']     = 0;
      $fp['status']      = 'paid';
      $remaining        -= $net_amount;
    } elseif ($remaining > 0) {
      $fp['amount_paid'] = $remaining;
      $fp['balance']     = $net_amount - $remaining;
      $fp['status']      = 'partial';
      $remaining         = 0;
    } else {
      $fp['amount_paid'] = 0;
      $fp['balance']     = $net_amount;
      $fp['status']      = 'unpaid';
    }
    $fp['net_amount']     = $net_amount;
    $fp['payment_method'] = $pay_details['payment_method'] ?? null;
    $fp['paid_at']        = $pay_details['paid_at'] ?? null;
    $fp['proof_file']     = $pay_details['proof_file'] ?? null;
    $rows[] = $fp;
  }

  $total_bal = $net_fees - $total_paid;

  return [
    'rows'           => $rows,
    'total_fees'     => $fee_data['total'],
    'total_discount' => $total_discount,
    'net_fees'       => $net_fees,
    'total_paid'     => $total_paid,
    'total_bal'      => $total_bal,
    'discounts'      => $discounts_raw,
    'pay_details'    => $pay_details,
  ];
}

/**
 * Compute the effective tuition discount for a student.
 *
 * Rules:
 * 1. All discounts are grouped per student.
 * 2. Percentage-only discounts are summed first, capped at 100%.
 * 3. If total percentage == 100%, fixed amounts are ignored (tuition is fully discounted).
 * 4. If total percentage < 100%, apply the percentage first, then subtract fixed amounts
 *    from the remaining tuition. If the result goes negative, clamp to 0.
 *
 * Returns the total discount amount (in pesos) to subtract from tuition.
 */
function compute_tuition_discount(array $discounts_raw, float $tuition_total): float {
  if ($tuition_total <= 0) return 0.0;

  // Separate percentage and fixed discounts
  $total_pct   = 0.0;
  $total_fixed = 0.0;

  foreach ($discounts_raw as $d) {
    $pct   = floatval($d['percentage']   ?? 0);
    $fixed = floatval($d['fixed_amount'] ?? 0);
    if ($fixed > 0) {
      $total_fixed += $fixed;
    } else {
      $total_pct += $pct;
    }
  }

  // Rule 2: cap percentage at 100%
  $total_pct = min($total_pct, 100.0);

  // Rule 3: if percentage == 100%, fixed amounts are ignored
  if ($total_pct >= 100.0) {
    return $tuition_total; // full tuition discount
  }

  // Apply percentage discount first
  $after_pct = $tuition_total * (1.0 - $total_pct / 100.0);

  // Then subtract fixed amounts from the remaining balance
  $after_fixed = $after_pct - $total_fixed;

  // Clamp to 0 (cannot discount more than tuition)
  $after_fixed = max(0.0, $after_fixed);

  // Total discount = original tuition minus what's left
  return round($tuition_total - $after_fixed, 2);
}
function last_day_of(int $month, int $year): string {
  return $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . date('t', mktime(0, 0, 0, $month, 1, $year));
}

/**
 * Compute the net total fees for a student (fees minus discounts on tuition only).
 * Used as the basis for payment scheme amounts.
 */
function get_net_total_for_student($conn, int $student_id, int $grade_level_id, int $sy_id): float {
  $fee_data = get_fees($conn, $grade_level_id, $sy_id);
  $total    = $fee_data['total'];

  // Tuition-only base for discounts
  $tuition_total = 0;
  foreach ($fee_data['fees'] as $f) {
    if (($f['fee_type'] ?? '') === 'tuition') {
      $tuition_total += floatval($f['amount']);
    }
  }

  $discounts_raw = $conn->query("SELECT * FROM discounts WHERE student_id=$student_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
  $discount = compute_tuition_discount($discounts_raw, $tuition_total);
  return max(0, $total - $discount);
}

/**
 * Build payment scheme installment config dynamically from the student's net total.
 * Downpayment and installment amounts are derived from actual fees + discounts.
 *
 * Scheme breakdown ratios (COJ business rules):
 *   Annual:      100% upfront
 *   Semi-Annual: ~58.25% down, ~44.25% in November
 *   Quarterly:   ~36.5% down, ~21.17% × 3 installments
 *   Monthly:     ~24.4% down, ~7.87% × 8 monthly payments
 *
 * We round installments to 2 decimal places and adjust the last installment
 * to absorb any rounding difference so the total always equals net_total.
 */
function get_payment_scheme_config_for_student($conn, int $student_id, int $grade_level_id, int $sy_id, string $scheme): array {
  $net = get_net_total_for_student($conn, $student_id, $grade_level_id, $sy_id);
  return build_scheme_config($scheme, $net);
}

/**
 * Build scheme config from a given net total amount.
 * Used both for display (before selection) and for generating the schedule.
 */
function build_scheme_config(string $scheme, float $net_total): array {
  $y  = (int) date('Y');
  $y1 = $y + 1;

  // Compute amounts based on net_total
  switch ($scheme) {
    case 'annual':
      $dp = round($net_total, 2);
      $installments = [];
      break;

    case 'semi_annual':
      // ~58.25% down, remainder in November
      $dp  = round($net_total * 0.5825, 2);
      $rem = round($net_total - $dp, 2);
      $installments = [
        ['label' => '2nd Payment (November)', 'amount' => $rem, 'due_date' => last_day_of(11, $y)],
      ];
      break;

    case 'quarterly':
      // ~36.5% down, 3 equal installments
      $dp   = round($net_total * 0.365, 2);
      $inst = round(($net_total - $dp) / 3, 2);
      // Absorb rounding in last installment
      $last = round($net_total - $dp - ($inst * 2), 2);
      $installments = [
        ['label' => '2nd Payment (August)',   'amount' => $inst, 'due_date' => last_day_of(8,  $y)],
        ['label' => '3rd Payment (November)', 'amount' => $inst, 'due_date' => last_day_of(11, $y)],
        ['label' => '4th Payment (February)', 'amount' => $last, 'due_date' => last_day_of(2,  $y1)],
      ];
      break;

    case 'monthly':
    default:
      // ~24.4% down, 8 equal monthly payments
      $dp   = round($net_total * 0.244, 2);
      $inst = round(($net_total - $dp) / 8, 2);
      $last = round($net_total - $dp - ($inst * 7), 2);
      $installments = [
        ['label' => 'July Payment',      'amount' => $inst, 'due_date' => last_day_of(7,  $y)],
        ['label' => 'August Payment',    'amount' => $inst, 'due_date' => last_day_of(8,  $y)],
        ['label' => 'September Payment', 'amount' => $inst, 'due_date' => last_day_of(9,  $y)],
        ['label' => 'October Payment',   'amount' => $inst, 'due_date' => last_day_of(10, $y)],
        ['label' => 'November Payment',  'amount' => $inst, 'due_date' => last_day_of(11, $y)],
        ['label' => 'December Payment',  'amount' => $inst, 'due_date' => last_day_of(12, $y)],
        ['label' => 'January Payment',   'amount' => $inst, 'due_date' => last_day_of(1,  $y1)],
        ['label' => 'February Payment',  'amount' => $last, 'due_date' => last_day_of(2,  $y1)],
      ];
      break;
  }

  $labels = [
    'annual'      => 'Annual',
    'semi_annual' => 'Semi-Annual',
    'quarterly'   => 'Quarterly',
    'monthly'     => 'Monthly',
  ];

  return [
    'label'        => $labels[$scheme] ?? ucfirst($scheme),
    'downpayment'  => $dp,
    'net_total'    => $net_total,
    'installments' => $installments,
  ];
}

/**
 * Payment scheme definitions — kept for backward compatibility.
 * New code should use get_payment_scheme_config_for_student() instead.
 * @deprecated Use build_scheme_config() with the student's actual net total.
 */
function get_payment_scheme_config(string $scheme): array {
  $y  = (int) date('Y');
  $y1 = $y + 1;

  $schemes = [
    'annual' => [
      'label'        => 'Annual',
      'downpayment'  => 95890.00,
      'installments' => [],
    ],
    'semi_annual' => [
      'label'        => 'Semi-Annual',
      'downpayment'  => 55855.00,
      'installments' => [
        ['label' => '2nd Payment (November)', 'amount' => 42515.00, 'due_date' => last_day_of(11, $y)],
      ],
    ],
    'quarterly' => [
      'label'        => 'Quarterly',
      'downpayment'  => 35010.00,
      'installments' => [
        ['label' => '2nd Payment (August)',   'amount' => 21670.00, 'due_date' => last_day_of(8,  $y)],
        ['label' => '3rd Payment (November)', 'amount' => 21670.00, 'due_date' => last_day_of(11, $y)],
        ['label' => '4th Payment (February)', 'amount' => 21670.00, 'due_date' => last_day_of(2,  $y1)],
      ],
    ],
    'monthly' => [
      'label'        => 'Monthly',
      'downpayment'  => 23430.00,
      'installments' => [
        ['label' => 'July Payment',      'amount' => 10090.00, 'due_date' => last_day_of(7,  $y)],
        ['label' => 'August Payment',    'amount' => 10090.00, 'due_date' => last_day_of(8,  $y)],
        ['label' => 'September Payment', 'amount' => 10090.00, 'due_date' => last_day_of(9,  $y)],
        ['label' => 'October Payment',   'amount' => 10090.00, 'due_date' => last_day_of(10, $y)],
        ['label' => 'November Payment',  'amount' => 10090.00, 'due_date' => last_day_of(11, $y)],
        ['label' => 'December Payment',  'amount' => 10090.00, 'due_date' => last_day_of(12, $y)],
        ['label' => 'January Payment',   'amount' => 10090.00, 'due_date' => last_day_of(1,  $y1)],
        ['label' => 'February Payment',  'amount' => 10090.00, 'due_date' => last_day_of(2,  $y1)],
      ],
    ],
  ];
  return $schemes[$scheme] ?? [];
}

/**
 * Generate payment schedule rows for a student enrollment.
 * Inserts downpayment + installments into payment_schedules.
 * Scheme is locked once set — will not regenerate if rows exist.
 */
function generate_payment_schedule($conn, int $student_id, int $enrollment_id, int $sy_id, string $scheme): bool {
  // Check if schedule already exists (locked)
  $existing = $conn->query("SELECT COUNT(*) as c FROM payment_schedules WHERE enrollment_id=$enrollment_id")->fetch_assoc()['c'];
  if ($existing > 0) return false; // already set, locked

  // Get student's grade level for fee lookup
  $student_row = $conn->query("SELECT grade_level_id FROM students WHERE id=$student_id LIMIT 1")->fetch_assoc();
  $grade_level_id = intval($student_row['grade_level_id'] ?? 0);
  if (!$grade_level_id) return false;

  // Build config dynamically from actual fees + discounts
  $config = get_payment_scheme_config_for_student($conn, $student_id, $grade_level_id, $sy_id, $scheme);
  if (empty($config) || $config['net_total'] <= 0) return false;

  // Insert downpayment row — due June 30 of the current school year
  $dp_label  = 'Downpayment (' . $config['label'] . ')';
  $dp_amount = $config['downpayment'];
  $dp_due    = last_day_of(6, (int) date('Y'));  // June 30
  $stmt = $conn->prepare("INSERT INTO payment_schedules (student_id, enrollment_id, school_year_id, installment_no, label, amount_due, due_date) VALUES (?,?,?,1,?,?,?)");
  $stmt->bind_param("iiisss", $student_id, $enrollment_id, $sy_id, $dp_label, $dp_amount, $dp_due);
  $stmt->execute();

  // Insert subsequent installments
  foreach ($config['installments'] as $i => $inst) {
    $no = $i + 2;
    $stmt2 = $conn->prepare("INSERT INTO payment_schedules (student_id, enrollment_id, school_year_id, installment_no, label, amount_due, due_date) VALUES (?,?,?,?,?,?,?)");
    $stmt2->bind_param("iiissss", $student_id, $enrollment_id, $sy_id, $no, $inst['label'], $inst['amount'], $inst['due_date']);
    $stmt2->execute();
  }

  // Save scheme on enrollment record
  $conn->query("UPDATE enrollments SET payment_plan='$scheme' WHERE id=$enrollment_id");
  return true;
}

/**
 * Repair bad due_date values in payment_schedules for an enrollment.
 * Fixes rows that were stored with invalid dates (e.g. 0000-00-00 or year -0001).
 * Uses label-to-date mapping — amounts are not changed, only dates.
 */
function repair_payment_schedule_dates($conn, int $enrollment_id, string $scheme): void {
  if (empty($scheme)) return;

  // Build label → due_date map using the static config (dates are the same regardless of amount)
  $y  = (int) date('Y');
  $y1 = $y + 1;
  $date_map = [
    '2nd Payment (November)'  => last_day_of(11, $y),
    '2nd Payment (August)'    => last_day_of(8,  $y),
    '3rd Payment (November)'  => last_day_of(11, $y),
    '4th Payment (February)'  => last_day_of(2,  $y1),
    'July Payment'            => last_day_of(7,  $y),
    'August Payment'          => last_day_of(8,  $y),
    'September Payment'       => last_day_of(9,  $y),
    'October Payment'         => last_day_of(10, $y),
    'November Payment'        => last_day_of(11, $y),
    'December Payment'        => last_day_of(12, $y),
    'January Payment'         => last_day_of(1,  $y1),
    'February Payment'        => last_day_of(2,  $y1),
  ];

  // Fetch all non-downpayment rows for this enrollment
  $rows = $conn->query("
    SELECT id, label, due_date FROM payment_schedules
    WHERE enrollment_id=$enrollment_id AND installment_no > 1
  ");
  if (!$rows) return;

  while ($row = $rows->fetch_assoc()) {
    $correct = $date_map[$row['label']] ?? null;
    if (!$correct) continue;
    $stored_year = (int) substr($row['due_date'] ?? '', 0, 4);
    if ($stored_year < 2000) {
      $conn->query("UPDATE payment_schedules SET due_date='$correct', penalty=0, status='unpaid' WHERE id={$row['id']}");
    }
  }

  // Fix downpayment row if it has a bad date — due date is June 30
  $dp = $conn->query("SELECT id, due_date FROM payment_schedules WHERE enrollment_id=$enrollment_id AND installment_no=1 LIMIT 1")->fetch_assoc();
  if ($dp) {
    $dp_year = (int) substr($dp['due_date'] ?? '', 0, 4);
    if ($dp_year < 2000) {
      $june30 = last_day_of(6, $y);
      $conn->query("UPDATE payment_schedules SET due_date='$june30', penalty=0, status='unpaid' WHERE id={$dp['id']}");
    }
  }
}

/**
 * Check and apply ₱500 late penalty to overdue payment schedule rows.
 * Call this when loading the SOA or payment schedule.
 */
function apply_late_penalties($conn, int $student_id, int $enrollment_id): void {
  $today = date('Y-m-d');
  $overdue = $conn->query("
    SELECT id FROM payment_schedules
    WHERE enrollment_id=$enrollment_id
    AND status IN ('unpaid','partial')
    AND due_date IS NOT NULL
    AND due_date < '$today'
    AND penalty = 0
  ");
  if (!$overdue) return;
  while ($row = $overdue->fetch_assoc()) {
    $conn->query("UPDATE payment_schedules SET penalty=500.00, status='overdue' WHERE id={$row['id']}");
  }
}


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
 * Send a notification to a parent account (portal bell + automatic email).
 */
function notify_parent($conn, int $parent_id, int $student_id, string $type, string $title, string $body = '') {
  $title_esc = $conn->real_escape_string($title);
  $body_esc  = $conn->real_escape_string($body);
  $conn->query("INSERT INTO parent_notifications (parent_id, student_id, type, title, body) VALUES ($parent_id, $student_id, '$type', '$title_esc', '$body_esc')");

  // Also send to parent's email automatically
  $parent = $conn->query("SELECT pa.email, pa.name FROM parent_accounts pa WHERE pa.id = $parent_id LIMIT 1")->fetch_assoc();
  $student = $conn->query("SELECT CONCAT(first_name,' ',last_name) as n FROM students WHERE id = $student_id LIMIT 1")->fetch_assoc();
  if ($parent && !empty($parent['email'])) {
    $email_file = __DIR__ . '/email_notifications.php';
    if (file_exists($email_file)) {
      require_once $email_file;
      notifyParentCustom($parent['email'], $parent['name'], $student['n'] ?? '', $title, $body);
    }
  }
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
