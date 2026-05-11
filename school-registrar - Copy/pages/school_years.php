<?php
session_start();
include('../mysql/db.php');
if (!isset($_SESSION['name'])) { header('Location: ../index.php'); exit(); }
if ($_SESSION['role'] !== 'superadmin') { header('Location: dashboard.php'); exit(); }

// Add new SY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
  $label = trim($_POST['label'] ?? '');
  if (preg_match('/^\d{4}-\d{4}$/', $label)) {
    $stmt = $conn->prepare("INSERT INTO school_years (label, is_active) VALUES (?, 0)");
    $stmt->bind_param("s", $label);
    $stmt->execute()
      ? header("Location: school_years.php?success=School year added")
      : header("Location: school_years.php?error=" . urlencode($conn->error));
  } else {
    header("Location: school_years.php?error=" . urlencode("Format must be YYYY-YYYY e.g. 2025-2026"));
  }
  exit();
}

// Set active SY
if (isset($_GET['set_active'])) {
  $sid = intval($_GET['set_active']);
  $conn->query("UPDATE school_years SET is_active = 0");
  $conn->query("UPDATE school_years SET is_active = 1 WHERE id = $sid");
  header("Location: school_years.php?success=Active school year updated"); exit();
}

$sy_list = $conn->query("SELECT * FROM school_years ORDER BY label DESC")->fetch_all(MYSQLI_ASSOC);
$success_message = $_GET['success'] ?? '';
$error_message   = $_GET['error']   ?? '';
$active_page = 'school_years';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>School Years — COJ Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/users.css">
</head>
<body>
<?php include('includes/sidebar.php'); ?>
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">School Year Management</div>
      <div class="page-sub">Set the active enrollment period</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-add-user" id="btn-add-sy"><i class="bi bi-plus-lg"></i> Add School Year</button>
    </div>
  </div>
  <div id="page-container">
    <?php if ($success_message): ?><div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>
    <?php if ($error_message):   ?><div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>

    <div class="users-table-card">
      <table class="users-table">
        <thead><tr><th>School Year</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($sy_list as $sy): ?>
          <tr>
            <td style="font-weight:600;font-size:15px;">SY <?= htmlspecialchars($sy['label']) ?></td>
            <td>
              <?php if ($sy['is_active']): ?>
                <span class="status-badge status-active"><i class="bi bi-check-circle-fill"></i> Active</span>
              <?php else: ?>
                <span class="status-badge status-inactive">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!$sy['is_active']): ?>
                <a href="school_years.php?set_active=<?= $sy['id'] ?>"
                   class="btn-u-activate"
                   onclick="return confirm('Set SY <?= htmlspecialchars($sy['label']) ?> as the active school year?')">
                  Set Active
                </a>
              <?php else: ?>
                <span style="font-size:12px;color:var(--color-muted);">Current active year</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-sy-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add School Year</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="school_years.php">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>School Year Label</label>
          <input type="text" name="label" class="form-input" placeholder="2026-2027" pattern="\d{4}-\d{4}" required/>
          <small style="color:var(--color-muted);font-size:11px;margin-top:4px;display:block;">Format: YYYY-YYYY (e.g. 2026-2027)</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Add</button>
      </div>
    </form>
  </div>
</div>
<script src="../js/nav.js"></script>
<script>
  const m = document.getElementById('add-sy-modal');
  document.getElementById('btn-add-sy').onclick  = () => m.classList.add('open');
  document.getElementById('modal-close').onclick  = () => m.classList.remove('open');
  document.getElementById('modal-cancel').onclick = () => m.classList.remove('open');
</script>
</body>
</html>
