<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
requireRole(['superadmin','registrar','finance']);

// ── Handle add / edit ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id             = intval($_POST['id'] ?? 0);
  $grade_level_id = intval($_POST['grade_level_id']);
  $school_year_id = intval($_POST['school_year_id']);
  $name           = trim($_POST['name']);
  $amount         = floatval($_POST['amount']);
  $fee_type       = trim($_POST['fee_type'] ?? 'tuition');
  $allowed_types  = ['tuition','miscellaneous','pta_fund','development','books','sped','other'];
  if (!in_array($fee_type, $allowed_types)) $fee_type = 'tuition';

  if (empty($name) || $grade_level_id <= 0 || $school_year_id <= 0 || $amount <= 0) {
    header("Location: fees.php?error=" . urlencode("All fields are required and amount must be greater than zero.")); exit();
  }

  if ($id > 0) {
    $stmt = $conn->prepare("UPDATE fees SET grade_level_id=?, school_year_id=?, name=?, fee_type=?, amount=? WHERE id=?");
    $stmt->bind_param("iissdi", $grade_level_id, $school_year_id, $name, $fee_type, $amount, $id);
  } else {
    $stmt = $conn->prepare("INSERT INTO fees (grade_level_id, school_year_id, name, fee_type, amount) VALUES (?,?,?,?,?)");
    $stmt->bind_param("iissd", $grade_level_id, $school_year_id, $name, $fee_type, $amount);
  }
  $stmt->execute()
    ? header("Location: fees.php?success=" . urlencode($id > 0 ? "Fee updated successfully." : "Fee added successfully."))
    : header("Location: fees.php?error=" . urlencode($conn->error));
  exit();
}

// ── Handle delete ────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
  $did = intval($_GET['delete_id']);
  $conn->query("DELETE FROM fees WHERE id=$did");
  header("Location: fees.php?success=" . urlencode("Fee deleted.")); exit();
}

// ── Data ─────────────────────────────────────────────────────
$grade_list = $conn->query("SELECT * FROM grade_levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$sy_list    = $conn->query("SELECT * FROM school_years ORDER BY label DESC")->fetch_all(MYSQLI_ASSOC);
$active_sy  = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
$active_sy_id = $active_sy['id'] ?? 0;

// Filters
$filter_sy    = intval($_GET['sy']     ?? $active_sy_id);
$search       = trim($_GET['search']   ?? '');
$filter_grade = intval($_GET['grade']  ?? 0);
$searchParam  = "%$search%";

// Build fees grouped by grade level
$where_parts = ["f.school_year_id = $filter_sy"];
$bind_types  = '';
$bind_vals   = [];

if ($search) {
  $where_parts[] = "f.name LIKE ?";
  $bind_types   .= 's';
  $bind_vals[]   = $searchParam;
}
if ($filter_grade) {
  $where_parts[] = "f.grade_level_id = ?";
  $bind_types   .= 'i';
  $bind_vals[]   = $filter_grade;
}

$where_sql = implode(' AND ', $where_parts);
$stmt_f = $conn->prepare("
  SELECT f.*, g.name as grade, g.id as gid
  FROM fees f
  JOIN grade_levels g ON f.grade_level_id = g.id
  WHERE $where_sql
  ORDER BY g.id, FIELD(f.fee_type,'tuition','miscellaneous','pta_fund','development','books','reservation','other','sped'), f.name
");
if ($bind_types) $stmt_f->bind_param($bind_types, ...$bind_vals);
$stmt_f->execute();
$all_fees = $stmt_f->get_result()->fetch_all(MYSQLI_ASSOC);

// Group by grade
$fees_by_grade = [];
foreach ($all_fees as $f) {
  $fees_by_grade[$f['gid']]['grade_name'] = $f['grade'];
  $fees_by_grade[$f['gid']]['fees'][]     = $f;
}

// Edit fee
$edit_fee = null;
if (!empty($_GET['edit_id'])) {
  $eid      = intval($_GET['edit_id']);
  $edit_fee = $conn->query("SELECT * FROM fees WHERE id=$eid")->fetch_assoc();
}

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page     = 'fees';

// Fee type badge colours
$type_colors = [
  'tuition'       => ['bg'=>'#eef0f8','color'=>'#494C8A','label'=>'Tuition'],
  'miscellaneous' => ['bg'=>'#fef9c3','color'=>'#92400e','label'=>'Miscellaneous'],
  'pta_fund'      => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'PTA Fund'],
  'development'   => ['bg'=>'#e0f2fe','color'=>'#0369a1','label'=>'Development'],
  'books'         => ['bg'=>'#fde8d8','color'=>'#9a3412','label'=>'Books'],
  'sped'          => ['bg'=>'#f3e8ff','color'=>'#7e22ce','label'=>'SPED'],
  'reservation'   => ['bg'=>'#f0fdf4','color'=>'#166534','label'=>'Reservation'],
  'other'         => ['bg'=>'#f3f4f6','color'=>'#374151','label'=>'Other'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fees — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/fees.css">
  <style>
    /* ── Toolbar ── */
    .fees-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:20px; }
    .fees-toolbar input, .fees-toolbar select {
      padding:8px 12px; border:1.5px solid var(--color-border);
      border-radius:8px; font-size:13px; font-family:var(--font); background:#fff;
    }
    .fees-toolbar input { min-width:220px; }
    .fees-toolbar select { min-width:160px; }
    .btn-search-sm { padding:8px 16px; background:var(--color-primary); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-clear-sm  { padding:8px 14px; background:var(--color-bg); color:var(--color-muted); border:1.5px solid var(--color-border); border-radius:8px; font-size:13px; cursor:pointer; text-decoration:none; }

    /* ── Grade accordion ── */
    .grade-accordion { margin-bottom:14px; border:1px solid var(--color-border); border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.05); }
    .grade-accordion-header {
      display:flex; align-items:center; justify-content:space-between;
      padding:14px 20px; background:var(--color-primary); color:#fff;
      cursor:pointer; user-select:none;
      transition:background .15s;
    }
    .grade-accordion-header:hover { background:var(--color-primary-dark, #3d4078); }
    .grade-accordion-title { font-size:14px; font-weight:700; display:flex; align-items:center; gap:10px; }
    .grade-accordion-meta  { font-size:12px; color:rgba(255,255,255,.75); display:flex; align-items:center; gap:14px; }
    .grade-accordion-chevron { font-size:16px; transition:transform .2s; }
    .grade-accordion-header.open .grade-accordion-chevron { transform:rotate(180deg); }
    .grade-accordion-body { display:none; }
    .grade-accordion-body.open { display:block; }

    /* ── Tuition highlight ── */
    .fee-row-tuition { background:#f8f9ff; }
    .fee-row-tuition td:first-child { font-weight:700; color:var(--color-primary); }

    /* ── Total row ── */
    .fee-total-row td { padding:10px 16px; font-weight:700; background:#f3f4f6; border-top:2px solid var(--color-border); }

    /* ── Empty state ── */
    .grade-empty { padding:24px; text-align:center; color:var(--color-muted); font-size:13px; }
  </style>
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Fee Structure</div>
      <div class="page-sub">Manage fees per grade level · SY <?= htmlspecialchars($active_sy['label'] ?? 'N/A') ?></div>
    </div>
    <div class="topbar-actions">
      <button class="btn-topbar" id="btn-add-fee"><i class="bi bi-plus-lg"></i> Add Fee</button>
    </div>
  </div>

  <div id="page-container">

    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <!-- Toolbar -->
    <form method="GET" action="fees.php" class="fees-toolbar">
      <input type="search" name="search" placeholder="Search fee name…"
             value="<?= htmlspecialchars($search) ?>"/>
      <select name="grade" onchange="this.form.submit()">
        <option value="">All Grade Levels</option>
        <?php foreach ($grade_list as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $filter_grade == $g['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($g['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <select name="sy" onchange="this.form.submit()">
        <?php foreach ($sy_list as $sy): ?>
        <option value="<?= $sy['id'] ?>" <?= $filter_sy == $sy['id'] ? 'selected' : '' ?>>
          SY <?= htmlspecialchars($sy['label']) ?><?= $sy['is_active'] ? ' ✓' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-search-sm"><i class="bi bi-search"></i> Search</button>
      <?php if ($search || $filter_grade): ?>
        <a href="fees.php?sy=<?= $filter_sy ?>" class="btn-clear-sm"><i class="bi bi-x-circle"></i> Clear</a>
      <?php endif; ?>
      <button type="button" class="btn-clear-sm" onclick="toggleAll(true)">
        <i class="bi bi-chevron-double-down"></i> Expand All
      </button>
      <button type="button" class="btn-clear-sm" onclick="toggleAll(false)">
        <i class="bi bi-chevron-double-up"></i> Collapse All
      </button>
    </form>

    <?php if (empty($fees_by_grade)): ?>
    <div style="text-align:center;padding:60px;color:var(--color-muted);">
      <i class="bi bi-receipt" style="font-size:40px;display:block;margin-bottom:12px;"></i>
      No fees found<?= ($search || $filter_grade) ? ' matching your filters' : ' for this school year' ?>.
      <?php if (!$search && !$filter_grade): ?>
        <div style="margin-top:8px;font-size:13px;">Click <strong>Add Fee</strong> to get started.</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Grade Level Accordions -->
    <?php foreach ($fees_by_grade as $gid => $group):
      $grade_fees  = $group['fees'];
      $grade_name  = $group['grade_name'];
      $grade_total = array_sum(array_column($grade_fees, 'amount'));
      $tuition_amt = array_sum(array_map(fn($f) => $f['fee_type'] === 'tuition' ? $f['amount'] : 0, $grade_fees));
      $fee_count   = count($grade_fees);
      // Auto-expand if search active or only one grade
      $auto_open   = $search || $filter_grade || count($fees_by_grade) === 1;
    ?>
    <div class="grade-accordion" id="accordion-<?= $gid ?>">
      <div class="grade-accordion-header <?= $auto_open ? 'open' : '' ?>"
           onclick="toggleAccordion(<?= $gid ?>)">
        <div class="grade-accordion-title">
          <i class="bi bi-mortarboard-fill"></i>
          <?= htmlspecialchars($grade_name) ?>
          <span style="background:rgba(255,255,255,.2);border-radius:999px;padding:1px 8px;font-size:11px;font-weight:600;">
            <?= $fee_count ?> fee<?= $fee_count !== 1 ? 's' : '' ?>
          </span>
        </div>
        <div class="grade-accordion-meta">
          <span>Tuition: <strong>₱<?= number_format($tuition_amt, 2) ?></strong></span>
          <span>Total: <strong>₱<?= number_format($grade_total, 2) ?></strong></span>
          <i class="bi bi-chevron-down grade-accordion-chevron"></i>
        </div>
      </div>

      <div class="grade-accordion-body <?= $auto_open ? 'open' : '' ?>" id="body-<?= $gid ?>">
        <table class="fees-table">
          <thead>
            <tr>
              <th>Fee Name</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($grade_fees as $f):
              $tc = $type_colors[$f['fee_type']] ?? $type_colors['other'];
              $is_tuition = $f['fee_type'] === 'tuition';
            ?>
            <tr class="<?= $is_tuition ? 'fee-row-tuition' : '' ?>">
              <td>
                <?= htmlspecialchars($f['name']) ?>
                <?php if ($is_tuition): ?>
                  <span style="font-size:10px;background:#494C8A;color:#fff;padding:1px 6px;border-radius:999px;margin-left:6px;font-weight:700;vertical-align:middle;">TUITION</span>
                <?php endif; ?>
              </td>
              <td>
                <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;font-weight:600;">
                  <?= $tc['label'] ?>
                </span>
              </td>
              <td style="font-weight:700;color:var(--color-primary);">₱<?= number_format($f['amount'], 2) ?></td>
              <td class="actions-cell">
                <a href="fees.php?edit_id=<?= $f['id'] ?>&sy=<?= $filter_sy ?>" class="btn-u-edit">Edit</a>
                <a href="fees.php?delete_id=<?= $f['id'] ?>&sy=<?= $filter_sy ?>" class="btn-u-deactivate"
                   onclick="return confirm('Delete fee: <?= htmlspecialchars(addslashes($f['name'])) ?>?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <!-- Total row -->
            <tr class="fee-total-row">
              <td colspan="2" style="text-align:right;color:var(--color-muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em;">
                Total for <?= htmlspecialchars($grade_name) ?>
              </td>
              <td style="color:var(--color-primary);">₱<?= number_format($grade_total, 2) ?></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- Add Fee Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add Fee</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="fees.php">
      <input type="hidden" name="id" value="0">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group" style="grid-column:1/-1;">
            <label>Fee Name *</label>
            <input type="text" name="name" class="form-input" required placeholder="e.g. Tuition Fee"/>
          </div>
          <div class="form-group">
            <label>Amount (₱) *</label>
            <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required placeholder="0.00"/>
          </div>
          <div class="form-group">
            <label>Fee Type</label>
            <select name="fee_type" class="form-input">
              <option value="tuition">Tuition</option>
              <option value="miscellaneous">Miscellaneous</option>
              <option value="pta_fund">PTA Fund</option>
              <option value="development">Development Fee</option>
              <option value="books">Books</option>
              <option value="sped">SPED</option>
              <option value="reservation">Reservation</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Grade Level *</label>
            <select name="grade_level_id" class="form-input" required>
              <option value="">Select grade</option>
              <?php foreach ($grade_list as $g): ?>
              <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>School Year *</label>
            <select name="school_year_id" class="form-input" required>
              <?php foreach ($sy_list as $sy): ?>
              <option value="<?= $sy['id'] ?>" <?= $sy['is_active'] ? 'selected' : '' ?>>
                SY <?= htmlspecialchars($sy['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="background:#eef0f8;border-left:3px solid var(--color-primary);border-radius:6px;padding:10px 14px;font-size:12px;color:#374151;margin-top:8px;">
          <i class="bi bi-info-circle-fill" style="color:var(--color-primary);"></i>
          Discounts and scholarships apply to <strong>Tuition</strong> fees only.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Save Fee</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Fee Modal -->
<?php if ($edit_fee): ?>
<div class="modal-overlay open" id="edit-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Edit Fee</h2>
      <a href="fees.php?sy=<?= $filter_sy ?>" class="modal-close">&times;</a>
    </div>
    <form method="POST" action="fees.php">
      <input type="hidden" name="id" value="<?= $edit_fee['id'] ?>">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group" style="grid-column:1/-1;">
            <label>Fee Name *</label>
            <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($edit_fee['name']) ?>" required/>
          </div>
          <div class="form-group">
            <label>Amount (₱) *</label>
            <input type="number" name="amount" class="form-input" step="0.01" value="<?= $edit_fee['amount'] ?>" required/>
          </div>
          <div class="form-group">
            <label>Fee Type</label>
            <select name="fee_type" class="form-input">
              <?php
              $fee_types = ['tuition'=>'Tuition','miscellaneous'=>'Miscellaneous','pta_fund'=>'PTA Fund','development'=>'Development Fee','books'=>'Books','sped'=>'SPED','reservation'=>'Reservation','other'=>'Other'];
              foreach ($fee_types as $val => $lbl):
              ?>
              <option value="<?= $val ?>" <?= ($edit_fee['fee_type'] ?? 'tuition') === $val ? 'selected' : '' ?>>
                <?= $lbl ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Grade Level *</label>
            <select name="grade_level_id" class="form-input" required>
              <?php foreach ($grade_list as $g): ?>
              <option value="<?= $g['id'] ?>" <?= $edit_fee['grade_level_id'] == $g['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($g['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>School Year *</label>
            <select name="school_year_id" class="form-input" required>
              <?php foreach ($sy_list as $sy): ?>
              <option value="<?= $sy['id'] ?>" <?= $edit_fee['school_year_id'] == $sy['id'] ? 'selected' : '' ?>>
                SY <?= htmlspecialchars($sy['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="fees.php?sy=<?= $filter_sy ?>" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="../js/nav.js"></script>
<script>
// ── Add modal ──────────────────────────────────────────────
const addModal = document.getElementById('add-modal');
document.getElementById('btn-add-fee').onclick  = () => addModal.classList.add('open');
document.getElementById('modal-close').onclick  = () => addModal.classList.remove('open');
document.getElementById('modal-cancel').onclick = () => addModal.classList.remove('open');

// ── Accordion toggle ───────────────────────────────────────
function toggleAccordion(gid) {
  const header = document.querySelector('#accordion-' + gid + ' .grade-accordion-header');
  const body   = document.getElementById('body-' + gid);
  if (!header || !body) return;
  const isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  header.classList.toggle('open', !isOpen);
}

function toggleAll(open) {
  document.querySelectorAll('.grade-accordion-body').forEach(b => b.classList.toggle('open', open));
  document.querySelectorAll('.grade-accordion-header').forEach(h => h.classList.toggle('open', open));
}

// Auto-open accordion if edit modal is active
<?php if ($edit_fee): ?>
document.querySelectorAll('.grade-accordion-body').forEach(b => b.classList.add('open'));
document.querySelectorAll('.grade-accordion-header').forEach(h => h.classList.add('open'));
<?php endif; ?>
</script>
</body>
</html>
