<?php
/**
 * helpers.php — Shared utility functions
 */

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
  $action  = $conn->real_escape_string($action);
  $target  = $conn->real_escape_string($target);
  $details = $conn->real_escape_string($details);
  $conn->query("INSERT INTO audit_log (user_id, user_name, action, target, target_id, details) VALUES ($user_id, '$uname', '$action', '$target', $target_id, '$details')");
}

/**
 * Get tagged filename for download: "StudentName - DocumentName.ext"
 */
function tagged_filename(string $student_name, string $doc_name, string $file_path): string {
  $ext  = pathinfo($file_path, PATHINFO_EXTENSION);
  $safe = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $student_name . ' - ' . $doc_name);
  return trim($safe) . '.' . $ext;
}

/**
 * Compute fee breakdown with discounts and payment plan.
 * Returns array: tuition, misc, pta, dev, books, other, subtotal, discount_amount, total, per_plan
 */
function compute_fees($conn, int $student_id, int $grade_level_id, int $sy_id): array {
  $fees = $conn->query("SELECT * FROM fees WHERE grade_level_id=$grade_level_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
  $breakdown = ['tuition'=>0,'miscellaneous'=>0,'pta_fund'=>0,'development'=>0,'books'=>0,'sped'=>0,'reservation'=>0,'other'=>0];
  foreach ($fees as $f) {
    $type = $f['fee_type'] ?? 'other';
    $breakdown[$type] = ($breakdown[$type] ?? 0) + floatval($f['amount']);
  }
  $subtotal = array_sum($breakdown);

  // Discounts
  $discounts = $conn->query("SELECT * FROM discounts WHERE student_id=$student_id AND school_year_id=$sy_id")->fetch_all(MYSQLI_ASSOC);
  $discount_amount = 0;
  foreach ($discounts as $d) {
    if ($d['type'] === 'employee' && ($d['child_number'] ?? 1) == 1) {
      // 100% tuition only
      $discount_amount += $breakdown['tuition'];
    } elseif (!empty($d['fixed_amount'])) {
      $discount_amount += floatval($d['fixed_amount']);
    } else {
      $discount_amount += $subtotal * (floatval($d['percentage']) / 100);
    }
  }
  $discount_amount = min($discount_amount, $subtotal);
  $total = max(0, $subtotal - $discount_amount);

  // Payment plan breakdown (reservation 5000 deducted from first payment)
  $reservation = $breakdown['reservation'] ?? 5000;
  $plans = [
    'annual'      => ['installments'=>1, 'label'=>'Annual',      'amount'=>$total],
    'semi_annual' => ['installments'=>2, 'label'=>'Semi-Annual', 'amount'=>round($total/2,2)],
    'quarterly'   => ['installments'=>4, 'label'=>'Quarterly',   'amount'=>round($total/4,2)],
    'monthly'     => ['installments'=>10,'label'=>'Monthly',     'amount'=>round($total/10,2)],
  ];

  return compact('breakdown','subtotal','discount_amount','total','plans','reservation','discounts');
}
