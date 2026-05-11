<?php
session_start();
include('../mysql/db.php');
require_once '../mysql/helpers.php';
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id             = intval($_POST['id'] ?? 0);
  $grade_level_id = intval($_POST['grade_level_id']);
  $school_year_id = intval($_POST['school_year_id']);
  $name           = trim($_POST['name']);
  $amount         = floatval($_POST['amount']);
  $fee_type       = trim($_POST['fee_type'] ?? 'tuition');
  $allowed_types  = ['tuition','miscellaneous','pta_fund','development','books','sped','other'];
  if (!in_array($fee_type, $allowed_types)) $fee_type = 'tuition';

  if (empty($name) || $grade_level_id <= 0 || $school_year_id <= 0) {
    header("Location: fees.php?error=" . urlencode("All fields are required.")); exit();
  }

  if ($id > 0) {
    $stmt = $conn->prepare("UPDATE fees SET grade_level_id=?, school_year_id=?, name=?, fee_type=?, amount=? WHERE id=?");
    $stmt->bind_param("iissdi", $grade_level_id, $school_year_id, $name, $fee_type, $amount, $id);
  } else {
    $stmt = $conn->prepare("INSERT INTO fees (grade_level_id, school_year_id, name, fee_type, amount) VALUES (?,?,?,?,?)");
    $stmt->bind_param("iissd", $grade_level_id, $school_year_id, $name, $fee_type, $amount);
  }
  $stmt->execute()
    ? header("Location: fees.php?success=" . ($id > 0 ? "Fee updated" : "Fee added"))
    : header("Location: fees.php?error=" . urlencode($conn->error));
  exit();
}

// Handle delete
if (isset($_GET['delete_id'])) {
  $did = intval($_GET['delete_id']);
  $conn->query("DELETE FROM fees WHERE id=$did");
  header("Location: fees.php?success=Fee deleted"); exit();
}

$grade_list = $conn->query("SELECT * FROM grade_levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$sy_list    = $conn->query("SELECT * FROM school_years ORDER BY label DESC")->fetch_all(MYSQLI_ASSOC);
$active_sy  = $conn->query("SELECT * FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();

// Edit fee
$edit_fee = null;
if (!empty($_GET['edit_id'])) {
  $eid = intval($_GET['edit_id']);
  $edit_fee = $conn->query("SELECT * FROM fees WHERE id=$eid")->fetch_assoc();
}

$fees = $conn->query("
  SELECT f.*, g.name as grade, sy.label as school_year
  FROM fees f
  JOIN grade_levels g ON f.grade_level_id = g.id
  JOIN school_years sy ON f.school_year_id = sy.id
  ORDER BY sy.label DESC, g.id, f.name
");

$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page = 'fees';
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
</head>
<body>
<?php include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Fee Structure</div>
      <div class="page-sub">Manage fees per grade level and school year</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-topbar" id="btn-add-fee"><i class="bi bi-plus-lg"></i> Add Fee</button>
    </div>
  </div>

  <div id="page-container">

    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <div class="fees-table-card">
      <table class="fees-table">
        <thead>
          <tr><th>Fee Name</th><th>Fee Type</th><th>Grade Level</th><th>School Year</th><th>Amount</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php $count = 0; while ($f = $fees->fetch_assoc()): $count++; ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($f['name']) ?></td>
            <td><span style="font-size:11px;padding:2px 8px;border-radius:999px;background:var(--color-bg);border:1px solid var(--color-border);color:var(--color-muted);font-weight:600;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$f['fee_type'] ?? 'tuition')) ?></span></td>
            <td><?= htmlspecialchars($f['grade']) ?></td>
            <td class="td-muted">SY <?= htmlspecialchars($f['school_year']) ?></td>
            <td style="font-weight:700;color:var(--color-primary);">₱<?= number_format($f['amount'], 2) ?></td>
            <td class="actions-cell">
              <a href="fees.php?edit_id=<?= $f['id'] ?>" class="btn-u-edit">Edit</a>
              <a href="fees.php?delete_id=<?= $f['id'] ?>" class="btn-u-deactivate"
                 onclick="return confirm('Delete this fee?')">Delete</a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if ($count === 0): ?>
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--color-muted);">No fees defined yet. Add one to get started.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add Fee</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="fees.php">
      <input type="hidden" name="id" value="0">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;">
          <div class="form-group"><label>Fee Name *</label><input type="text" name="name" class="form-input" required/></div>
          <div class="form-group"><label>Amount (₱) *</label><input type="number" name="amount" class="form-input" step="0.01" min="0" required/></div>
          <div class="form-group">
            <label>Fee Type</label>
            <select name="fee_type" class="form-input">
              <option value="tuition">Tuition</option>
              <option value="miscellaneous">Miscellaneous</option>
              <option value="pta_fund">PTA Fund</option>
              <option value="development">Development Fee</option>
              <option value="books">Books</option>
              <option value="sped">SPED</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Grade Level *</label>
            <select name="grade_level_id" class="form-input" required>
              <option value="">Select grade</option>
              <?php foreach ($grade_list as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>School Year *</label>
            <select name="school_year_id" class="form-input" required>
              <?php foreach ($sy_list as $sy): ?><option value="<?= $sy['id'] ?>" <?= $sy['is_active']?'selected':'' ?>>SY <?= htmlspecialchars($sy['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Save Fee</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($edit_fee): ?>
<div class="modal-overlay open" id="edit-modal">
  <div class="modal-box">
    <div class="modal-header"><h2>Edit Fee</h2><a href="fees.php" class="modal-close">&times;</a></div>
    <form method="POST" action="fees.php">
      <input type="hidden" name="id" value="<?= $edit_fee['id'] ?>">
      <div class="modal-body">
        <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;">
          <div class="form-group"><label>Fee Name *</label><input type="text" name="name" class="form-input" value="<?= htmlspecialchars($edit_fee['name']) ?>" required/></div>
          <div class="form-group"><label>Amount (₱) *</label><input type="number" name="amount" class="form-input" step="0.01" value="<?= $edit_fee['amount'] ?>" required/></div>
          <div class="form-group">
            <label>Fee Type</label>
            <select name="fee_type" class="form-input">
              <option value="tuition"       <?= ($edit_fee['fee_type']??'tuition')==='tuition'       ?'selected':'' ?>>Tuition</option>
              <option value="miscellaneous" <?= ($edit_fee['fee_type']??'')==='miscellaneous' ?'selected':'' ?>>Miscellaneous</option>
              <option value="pta_fund"      <?= ($edit_fee['fee_type']??'')==='pta_fund'      ?'selected':'' ?>>PTA Fund</option>
              <option value="development"   <?= ($edit_fee['fee_type']??'')==='development'   ?'selected':'' ?>>Development Fee</option>
              <option value="books"         <?= ($edit_fee['fee_type']??'')==='books'         ?'selected':'' ?>>Books</option>
              <option value="sped"          <?= ($edit_fee['fee_type']??'')==='sped'          ?'selected':'' ?>>SPED</option>
              <option value="other"         <?= ($edit_fee['fee_type']??'')==='other'         ?'selected':'' ?>>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Grade Level *</label>
            <select name="grade_level_id" class="form-input" required>
              <?php foreach ($grade_list as $g): ?><option value="<?= $g['id'] ?>" <?= $edit_fee['grade_level_id']==$g['id']?'selected':'' ?>><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>School Year *</label>
            <select name="school_year_id" class="form-input" required>
              <?php foreach ($sy_list as $sy): ?><option value="<?= $sy['id'] ?>" <?= $edit_fee['school_year_id']==$sy['id']?'selected':'' ?>>SY <?= htmlspecialchars($sy['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="fees.php" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="../js/nav.js"></script>
<script>
  const modal = document.getElementById('add-modal');
  document.getElementById('btn-add-fee').onclick  = () => modal.classList.add('open');
  document.getElementById('modal-close').onclick   = () => modal.classList.remove('open');
  document.getElementById('modal-cancel').onclick  = () => modal.classList.remove('open');
</script>
</body>
</html>
