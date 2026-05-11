<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

// ── Handle add ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $student_id = intval($_POST['student_id']);
  $type       = trim($_POST['type'] ?? '');
  $percentage = floatval($_POST['percentage'] ?? 0);
  $fixed_amount = floatval($_POST['fixed_amount'] ?? 0);
  $child_number = intval($_POST['child_number'] ?? 1);
  $label      = trim($_POST['label'] ?? '');
  $notes      = trim($_POST['notes'] ?? '');
  $allowed_types = ['employee','sibling','scholarship','reservation','other'];

  if ($student_id > 0 && in_array($type, $allowed_types)) {
    // Employee 1st child = 100% tuition (stored as percentage=100 but applied only to tuition)
    if ($type === 'employee' && $child_number == 1) {
      $percentage = 100;
      $label = $label ?: 'Employee Discount (1st Child — 100% Tuition)';
    }
    // Reservation fee = fixed 5000
    if ($type === 'reservation') {
      $fixed_amount = 5000;
      $label = $label ?: 'Reservation Fee (₱5,000)';
    }
    $stmt = $conn->prepare("INSERT INTO discounts (student_id, school_year_id, type, child_number, percentage, fixed_amount, label, notes) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("iisiddss", $student_id, $sy_id, $type, $child_number, $percentage, $fixed_amount, $label, $notes);
    $stmt->execute()
      ? header("Location: discounts.php?success=Discount added")
      : header("Location: discounts.php?error=" . urlencode($conn->error));
  } else {
    header("Location: discounts.php?error=" . urlencode("Invalid input."));
  }
  exit();
}

// ── Handle delete ────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
  $did = intval($_GET['delete_id']);
  $conn->query("DELETE FROM discounts WHERE id=$did");
  header("Location: discounts.php?success=Discount removed"); exit();
}

// ── Fetch discounts for current SY ──────────────────────────
$discounts = $conn->query("
  SELECT d.*, s.first_name, s.last_name, s.lrn, g.name as grade
  FROM discounts d
  JOIN students s ON s.id = d.student_id
  LEFT JOIN grade_levels g ON g.id = s.grade_level_id
  WHERE d.school_year_id = $sy_id
  ORDER BY s.last_name, s.first_name
")->fetch_all(MYSQLI_ASSOC);

// ── Students list for modal ──────────────────────────────────
$students_list = $conn->query("
  SELECT s.id, s.first_name, s.last_name, s.lrn
  FROM students s
  WHERE s.is_archived = 0
  ORDER BY s.last_name, s.first_name
")->fetch_all(MYSQLI_ASSOC);

// ── Discount summary per student ─────────────────────────────
$summary = [];
foreach ($discounts as $d) {
  $key = $d['student_id'];
  if (!isset($summary[$key])) {
    $summary[$key] = [
      'name'       => $d['last_name'] . ', ' . $d['first_name'],
      'lrn'        => $d['lrn'],
      'grade'      => $d['grade'],
      'total_pct'  => 0,
      'count'      => 0,
    ];
  }
  $summary[$key]['total_pct'] += $d['percentage'];
  $summary[$key]['count']++;
}

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page = 'discounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Discounts — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/fees.css">
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Discounts &amp; Scholarships</div>
      <div class="page-sub">SY <?= htmlspecialchars($active_sy['label'] ?? 'N/A') ?></div>
    </div>
    <div class="topbar-actions">
      <button class="btn-topbar" id="btn-add-discount"><i class="bi bi-plus-lg"></i> Add Discount</button>
    </div>
  </div>

  <div id="page-container">

    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <?php if (!empty($summary)): ?>
    <!-- Summary cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px;">
      <?php foreach ($summary as $sid => $s): ?>
      <div style="background:var(--color-card);border:1px solid var(--color-border);border-radius:var(--radius);padding:16px;">
        <div style="font-size:13px;font-weight:700;color:var(--color-text);"><?= htmlspecialchars($s['name']) ?></div>
        <div style="font-size:11px;color:var(--color-muted);margin-top:2px;"><?= htmlspecialchars($s['grade'] ?? '—') ?> · LRN <?= htmlspecialchars($s['lrn']) ?></div>
        <div style="margin-top:10px;font-size:22px;font-weight:800;color:#16a34a;"><?= number_format($s['total_pct'],2) ?>%</div>
        <div style="font-size:11px;color:var(--color-muted);"><?= $s['count'] ?> discount<?= $s['count']>1?'s':'' ?> applied</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Discounts table -->
    <div class="fees-table-card">
      <table class="fees-table">
        <thead>
          <tr><th>Student</th><th>Grade</th><th>Type</th><th>Label</th><th>Percentage</th><th>Notes</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php $count = 0; foreach ($discounts as $d): $count++; ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($d['last_name'] . ', ' . $d['first_name']) ?></div>
              <div style="font-size:11px;color:var(--color-muted);">LRN <?= htmlspecialchars($d['lrn']) ?></div>
            </td>
            <td><?= htmlspecialchars($d['grade'] ?? '—') ?></td>
            <td><span style="text-transform:capitalize;font-size:12px;padding:2px 8px;border-radius:999px;background:var(--color-bg);border:1px solid var(--color-border);"><?= htmlspecialchars(str_replace('_',' ',$d['type'])) ?></span></td>
            <td><?= htmlspecialchars($d['label'] ?? '—') ?></td>
            <td style="font-weight:700;color:#16a34a;"><?= number_format($d['percentage'],2) ?>%</td>
            <td class="td-muted"><?= htmlspecialchars($d['notes'] ?? '—') ?></td>
            <td class="actions-cell">
              <a href="discounts.php?delete_id=<?= $d['id'] ?>" class="btn-u-deactivate"
                 onclick="return confirm('Remove this discount?')">Remove</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if ($count === 0): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">No discounts for this school year.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Discount Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add Discount / Scholarship</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="discounts.php">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group" style="grid-column:1/-1;">
            <label>Student *</label>
            <select name="student_id" class="form-input" required>
              <option value="">Select student</option>
              <?php foreach ($students_list as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' — ' . $s['lrn']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Discount Type *</label>
            <select name="type" class="form-input" required id="discount-type-sel" onchange="toggleDiscountFields()">
              <option value="">Select type</option>
              <option value="employee">Employee (Staff Child)</option>
              <option value="sibling">Sibling Discount</option>
              <option value="scholarship">Scholarship</option>
              <option value="reservation">Reservation Fee (₱5,000)</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group" id="child-number-field" style="display:none;">
            <label>Child Number</label>
            <select name="child_number" class="form-input">
              <option value="1">1st Child (100% Tuition Free)</option>
              <option value="2">2nd Child (Sibling Discount)</option>
              <option value="3">3rd Child+</option>
            </select>
          </div>
          <div class="form-group" id="percentage-field">
            <label>Percentage (%) <span id="pct-note" style="font-size:11px;color:var(--color-muted);"></span></label>
            <input type="number" name="percentage" class="form-input" step="0.01" min="0" max="100" placeholder="e.g. 25.00"/>
          </div>
          <div class="form-group">
            <label>Label</label>
            <input type="text" name="label" class="form-input" placeholder="e.g. Full Scholarship"/>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes" class="form-input" placeholder="Optional notes"/>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Save Discount</button>
      </div>
    </form>
  </div>
</div>

<script src="../js/nav.js"></script>
<script>
  const modal = document.getElementById('add-modal');
  document.getElementById('btn-add-discount').onclick = () => modal.classList.add('open');
  document.getElementById('modal-close').onclick      = () => modal.classList.remove('open');
  document.getElementById('modal-cancel').onclick     = () => modal.classList.remove('open');

  function toggleDiscountFields() {
    const type = document.getElementById('discount-type-sel').value;
    const childField = document.getElementById('child-number-field');
    const pctField   = document.getElementById('percentage-field');
    const pctNote    = document.getElementById('pct-note');
    childField.style.display = (type === 'employee') ? 'block' : 'none';
    if (type === 'employee') {
      pctNote.textContent = '(auto: 100% for 1st child tuition)';
      pctField.style.display = 'none';
    } else if (type === 'reservation') {
      pctNote.textContent = '(fixed ₱5,000)';
      pctField.style.display = 'none';
    } else {
      pctNote.textContent = '';
      pctField.style.display = 'block';
    }
  }
</script>
</body>
</html>
