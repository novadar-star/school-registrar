<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
requireRole(['superadmin','registrar','finance']);

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

// ── Handle add ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $student_id    = intval($_POST['student_id']);
  $type          = trim($_POST['type'] ?? '');
  $discount_mode = trim($_POST['discount_mode'] ?? 'percentage'); // 'percentage' or 'fixed'
  $percentage    = min(100, max(0, floatval($_POST['percentage']   ?? 0)));
  $fixed_amount  = max(0, floatval($_POST['fixed_amount'] ?? 0));
  $label         = trim($_POST['label'] ?? '');
  $allowed_types = ['employee','sibling','scholarship','other'];

  if ($student_id > 0 && in_array($type, $allowed_types)) {
    if ($type === 'employee') {
      // Employee discount = 100% of tuition fee only
      $percentage   = 100;
      $fixed_amount = 0;
      $label        = $label ?: 'Employee Discount (100% Tuition)';
      $discount_mode = 'percentage';
    } elseif ($discount_mode === 'fixed') {
      $percentage = 0;
    } else {
      $fixed_amount = 0;
    }

    $stmt = $conn->prepare("INSERT INTO discounts (student_id, school_year_id, type, percentage, fixed_amount, label) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iisdds", $student_id, $sy_id, $type, $percentage, $fixed_amount, $label);
    $stmt->execute()
      ? header("Location: discounts.php?success=" . urlencode("Discount added successfully."))
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
  header("Location: discounts.php?success=" . urlencode("Discount removed.")); exit();
}

// ── Filters ──────────────────────────────────────────────────
$search       = trim($_GET['search']      ?? '');
$filter_grade = trim($_GET['grade']       ?? '');
$filter_type  = trim($_GET['type']        ?? '');
$searchParam  = "%$search%";

$where_parts = ["d.school_year_id = $sy_id"];
$bind_types  = '';
$bind_vals   = [];

if ($search) {
  $where_parts[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
  $bind_types   .= 'sss';
  $bind_vals[]   = $searchParam;
  $bind_vals[]   = $searchParam;
  $bind_vals[]   = $searchParam;
}
if ($filter_grade) {
  $where_parts[] = "g.name = ?";
  $bind_types   .= 's';
  $bind_vals[]   = $filter_grade;
}
if ($filter_type) {
  $where_parts[] = "d.type = ?";
  $bind_types   .= 's';
  $bind_vals[]   = $filter_type;
}

$where_sql = implode(' AND ', $where_parts);

$stmt_d = $conn->prepare("
  SELECT d.*, s.first_name, s.last_name, s.lrn, g.name as grade
  FROM discounts d
  JOIN students s ON s.id = d.student_id
  LEFT JOIN grade_levels g ON g.id = s.grade_level_id
  WHERE $where_sql
  ORDER BY s.last_name, s.first_name
");
if ($bind_types) {
  $stmt_d->bind_param($bind_types, ...$bind_vals);
}
$stmt_d->execute();
$discounts_raw = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);

// Group discounts by student — one row per student showing all their discounts
$discounts_grouped = [];
foreach ($discounts_raw as $d) {
  $sid = $d['student_id'];
  if (!isset($discounts_grouped[$sid])) {
    $discounts_grouped[$sid] = [
      'student_id' => $sid,
      'first_name' => $d['first_name'],
      'last_name'  => $d['last_name'],
      'lrn'        => $d['lrn'],
      'grade'      => $d['grade'],
      'items'      => [],
    ];
  }
  $discounts_grouped[$sid]['items'][] = $d;
}

// ── Grade levels for filter ───────────────────────────────────
$grade_levels = $conn->query("SELECT id, name FROM grade_levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page     = 'discounts';

// Pre-fill student if coming from student picker
$prefill_student_id   = intval($_GET['student_id'] ?? 0);
$prefill_student_name = '';
if ($prefill_student_id) {
  $ps = $conn->query("SELECT first_name, last_name, lrn FROM students WHERE id=$prefill_student_id LIMIT 1")->fetch_assoc();
  if ($ps) $prefill_student_name = $ps['last_name'] . ', ' . $ps['first_name'] . ' — LRN: ' . $ps['lrn'];
}
$open_modal = $prefill_student_id > 0 || !empty($_GET['add']);
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
  <style>
    .discount-search-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:18px; }
    .discount-search-bar input, .discount-search-bar select { padding:8px 12px; border:1.5px solid var(--color-border); border-radius:8px; font-size:13px; font-family:var(--font); background:#fff; }
    .discount-search-bar input { min-width:220px; }
    .discount-search-bar select { min-width:140px; }
    .btn-search-sm { padding:8px 16px; background:var(--color-primary); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-clear-sm  { padding:8px 14px; background:var(--color-bg); color:var(--color-muted); border:1.5px solid var(--color-border); border-radius:8px; font-size:13px; cursor:pointer; text-decoration:none; }
    .discount-mode-toggle { display:flex; gap:8px; margin-bottom:10px; }
    .discount-mode-toggle label { display:flex; align-items:center; gap:6px; padding:7px 14px; border:1.5px solid var(--color-border); border-radius:8px; cursor:pointer; font-size:13px; font-weight:500; }
    .discount-mode-toggle label:has(input:checked) { border-color:var(--color-primary); background:#eef0f8; color:var(--color-primary); }
    .student-picker-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; background:#eef0f8; color:var(--color-primary); border:1.5px solid var(--color-primary); border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
    .student-picker-btn:hover { background:var(--color-primary); color:#fff; }
  </style>
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Discounts &amp; Scholarships</div>
      <div class="page-sub">SY <?= htmlspecialchars($active_sy['label'] ?? 'N/A') ?> · Applies to tuition fee only</div>
    </div>
    <div class="topbar-actions">
      <a href="discount_student_picker.php" class="student-picker-btn">
        <i class="bi bi-person-plus-fill"></i> Select Student &amp; Add Discount
      </a>
    </div>
  </div>

  <div id="page-container">

    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <!-- Search & Filter Bar -->
    <form method="GET" action="discounts.php" class="discount-search-bar">
      <input type="search" name="search" placeholder="Search name or LRN…"
             value="<?= htmlspecialchars($search) ?>"/>
      <select name="grade" onchange="this.form.submit()">
        <option value="">All Grades</option>
        <?php foreach ($grade_levels as $gl): ?>
        <option value="<?= htmlspecialchars($gl['name']) ?>" <?= $filter_grade === $gl['name'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($gl['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <select name="type" onchange="this.form.submit()">
        <option value="">All Types</option>
        <option value="employee"    <?= $filter_type === 'employee'    ? 'selected' : '' ?>>Employee</option>
        <option value="sibling"     <?= $filter_type === 'sibling'     ? 'selected' : '' ?>>Sibling</option>
        <option value="scholarship" <?= $filter_type === 'scholarship' ? 'selected' : '' ?>>Scholarship</option>
        <option value="other"       <?= $filter_type === 'other'       ? 'selected' : '' ?>>Other</option>
      </select>
      <button type="submit" class="btn-search-sm"><i class="bi bi-search"></i> Search</button>
      <?php if ($search || $filter_grade || $filter_type): ?>
        <a href="discounts.php" class="btn-clear-sm"><i class="bi bi-x-circle"></i> Clear</a>
      <?php endif; ?>
    </form>

    <!-- Discounts Table -->
    <div class="fees-table-card">
      <table class="fees-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>LRN</th>
            <th>Grade</th>
            <th>Discounts Applied</th>
            <th>Effective Discount</th>
            <th>Applies To</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($discounts_grouped)): ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--color-muted);">
            No discounts found<?= ($search || $filter_grade || $filter_type) ? ' matching your filters' : ' for this school year' ?>.
          </td></tr>
          <?php endif; ?>
          <?php foreach ($discounts_grouped as $sid => $grp):
            $items = $grp['items'];
            // Compute effective discount summary for display
            $total_pct   = 0; $total_fixed = 0;
            foreach ($items as $it) {
              if (!empty($it['fixed_amount']) && $it['fixed_amount'] > 0) $total_fixed += $it['fixed_amount'];
              else $total_pct += $it['percentage'];
            }
            $total_pct = min($total_pct, 100);
          ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($grp['last_name'] . ', ' . $grp['first_name']) ?></div>
            </td>
            <td style="font-size:12px;color:var(--color-muted);"><?= htmlspecialchars($grp['lrn']) ?></td>
            <td><?= htmlspecialchars($grp['grade'] ?? '—') ?></td>
            <td>
              <?php foreach ($items as $it): ?>
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;flex-wrap:wrap;">
                <span style="text-transform:capitalize;font-size:11px;padding:1px 7px;border-radius:999px;background:var(--color-bg);border:1px solid var(--color-border);">
                  <?= htmlspecialchars(str_replace('_',' ',$it['type'])) ?>
                </span>
                <span style="font-size:12px;color:var(--color-muted);"><?= htmlspecialchars($it['label'] ?? '') ?></span>
                <span style="font-size:12px;font-weight:700;color:#16a34a;">
                  <?php if (!empty($it['fixed_amount']) && $it['fixed_amount'] > 0): ?>
                    -₱<?= number_format($it['fixed_amount'], 2) ?>
                  <?php else: ?>
                    <?= number_format($it['percentage'], 2) ?>%
                  <?php endif; ?>
                </span>
                <a href="discounts.php?delete_id=<?= $it['id'] ?>" class="btn-u-deactivate" style="padding:2px 8px;font-size:11px;"
                   onclick="return confirm('Remove this discount for <?= htmlspecialchars(addslashes($grp['first_name'] . ' ' . $grp['last_name'])) ?>?')">
                  ✕
                </a>
              </div>
              <?php endforeach; ?>
            </td>
            <td style="font-weight:700;color:#16a34a;">
              <?php if ($total_pct >= 100): ?>
                <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:999px;font-size:12px;">100% (Full)</span>
              <?php else: ?>
                <?php if ($total_pct > 0): ?>
                  <div style="font-size:12px;"><?= number_format($total_pct, 2) ?>% off tuition</div>
                <?php endif; ?>
                <?php if ($total_fixed > 0): ?>
                  <div style="font-size:12px;">-₱<?= number_format($total_fixed, 2) ?> fixed</div>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 8px;border-radius:999px;font-weight:600;">
                Tuition Only
              </span>
            </td>
            <td class="actions-cell">
              <a href="discount_student_picker.php?highlight=<?= $sid ?>" class="btn-u-edit" style="font-size:11px;">
                + Add More
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- Add Discount Modal (opened when coming from student picker) -->
<div class="modal-overlay" id="add-modal" <?= $open_modal ? 'style="display:flex;"' : '' ?>>
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add Discount / Scholarship</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="discounts.php">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">

        <?php if ($prefill_student_id): ?>
        <!-- Student pre-filled from picker -->
        <input type="hidden" name="student_id" value="<?= $prefill_student_id ?>">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Student</label>
          <div style="padding:10px 14px;background:var(--color-bg);border:1.5px solid var(--color-border);border-radius:8px;font-size:13px;font-weight:600;">
            <?= htmlspecialchars($prefill_student_name) ?>
          </div>
        </div>
        <?php else: ?>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Student *</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="student_id" id="modal-student-id" required/>
            <input type="text" id="modal-student-display" class="form-input" readonly
                   placeholder="Click 'Pick Student' to select"
                   style="flex:1;background:var(--color-bg);cursor:not-allowed;"/>
            <a href="discount_student_picker.php" class="student-picker-btn" style="white-space:nowrap;">
              <i class="bi bi-person-search"></i> Pick Student
            </a>
          </div>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:14px;">
          <label>Discount Type *</label>
          <select name="type" class="form-input" required id="discount-type-sel" onchange="toggleDiscountFields()">
            <option value="">Select type</option>
            <option value="employee">Employee (Staff Child)</option>
            <option value="sibling">Sibling Discount</option>
            <option value="scholarship">Scholarship</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div id="discount-amount-section" class="form-group" style="margin-bottom:14px;">
          <label>Discount Amount *</label>
          <div class="discount-mode-toggle" id="mode-toggle">
            <label>
              <input type="radio" name="discount_mode" value="percentage" checked onchange="switchMode('percentage')"/>
              Percentage (%)
            </label>
            <label>
              <input type="radio" name="discount_mode" value="fixed" onchange="switchMode('fixed')"/>
              Fixed Amount (₱)
            </label>
          </div>
          <div id="pct-input-wrap">
            <input type="number" name="percentage" id="pct-input" class="form-input" step="0.01" min="0" max="100"
                   placeholder="e.g. 25.00" style="max-width:200px;"/>
            <div style="font-size:11px;color:var(--color-muted);margin-top:4px;">
              Percentage of the tuition fee only.
            </div>
          </div>
          <div id="fixed-input-wrap" style="display:none;">
            <input type="number" name="fixed_amount" id="fixed-input" class="form-input" step="0.01" min="0"
                   placeholder="e.g. 5000.00" style="max-width:200px;"/>
            <div style="font-size:11px;color:var(--color-muted);margin-top:4px;">
              Fixed peso amount deducted from tuition fee only.
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:14px;">
          <label>Label / Description</label>
          <input type="text" name="label" class="form-input" placeholder="e.g. Full Scholarship, 25% Sibling Discount"/>
        </div>

        <div style="background:#dcfce7;border-left:3px solid #16a34a;border-radius:6px;padding:10px 14px;font-size:12px;color:#166534;">
          <i class="bi bi-info-circle-fill"></i>
          <strong>Note:</strong> Discounts and scholarships apply to the <strong>tuition fee only</strong>, not to miscellaneous or other fees.
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
document.getElementById('modal-close').onclick  = () => modal.style.display = 'none';
document.getElementById('modal-cancel').onclick = () => modal.style.display = 'none';

function toggleDiscountFields() {
  const type = document.getElementById('discount-type-sel').value;
  const amtSection  = document.getElementById('discount-amount-section');
  const modeToggle  = document.getElementById('mode-toggle');
  if (type === 'employee') {
    amtSection.style.display = 'none'; // auto 100%
  } else {
    amtSection.style.display = 'block';
    modeToggle.style.display = 'flex';
  }
}

function switchMode(mode) {
  const pctWrap   = document.getElementById('pct-input-wrap');
  const fixedWrap = document.getElementById('fixed-input-wrap');
  const pctInput  = document.getElementById('pct-input');
  const fixedInput = document.getElementById('fixed-input');
  if (mode === 'fixed') {
    pctWrap.style.display   = 'none';
    fixedWrap.style.display = 'block';
    pctInput.removeAttribute('required');
    fixedInput.setAttribute('required', '');
  } else {
    pctWrap.style.display   = 'block';
    fixedWrap.style.display = 'none';
    fixedInput.removeAttribute('required');
    pctInput.setAttribute('required', '');
  }
}
</script>
</body>
</html>
