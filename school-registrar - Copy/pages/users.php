<?php
session_start();
include('../mysql/db.php');

// Superadmin only
if (!isset($_SESSION['name'])) {
  header('Location: ../index.php'); exit();
}
if ($_SESSION['role'] !== 'superadmin') {
  header('Location: dashboard.php'); exit();
}

$error_message   = '';
$success_message = '';

// ── Handle Add / Edit ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id       = intval($_POST['id'] ?? 0);
  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $role     = $_POST['role'] ?? 'registrar';
  $password = trim($_POST['password'] ?? '');

  if (empty($name) || empty($email)) {
    $error_message = "Name and email are required.";
  } elseif ($id === 0 && empty($password)) {
    $error_message = "Password is required for new users.";
  } else {
    if ($id > 0) {
      // Edit — only update password if provided
      if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $email, $role, $hashed, $id);
      } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
      }
      $stmt->execute() ? $success_message = "User updated." : $error_message = $conn->error;
    } else {
      // Add new
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
      $stmt->bind_param("ssss", $name, $email, $hashed, $role);
      $stmt->execute() ? $success_message = "User added." : $error_message = $conn->error;
    }
  }
}

// ── Handle toggle active ───────────────────────────────────
if (isset($_GET['toggle_id'])) {
  $tid = intval($_GET['toggle_id']);
  $conn->query("UPDATE users SET is_active = NOT is_active WHERE id = $tid");
  header("Location: users.php?success=User status updated"); exit();
}

// ── Unlock locked account ──────────────────────────────────
if (isset($_GET['unlock_id'])) {
  $uid2 = intval($_GET['unlock_id']);
  $conn->query("UPDATE users SET failed_attempts=0, locked_at=NULL WHERE id=$uid2");
  header("Location: users.php?success=Account unlocked successfully"); exit();
}

if (!empty($_GET['success'])) $success_message = $_GET['success'];

// ── Fetch for edit modal ───────────────────────────────────
$edit_user = null;
if (!empty($_GET['edit_id'])) {
  $eid  = intval($_GET['edit_id']);
  $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->bind_param("i", $eid);
  $stmt->execute();
  $edit_user = $stmt->get_result()->fetch_assoc();
}

$users = $conn->query("SELECT * FROM users ORDER BY role ASC, name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Management — School Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/users.css">
</head>
<body>

<?php $active_page = 'users'; include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">User Management</div>
      <div class="page-sub">Control registrar access accounts</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-add-user" id="btn-add-user"><i class="bi bi-plus-lg"></i> Add User</button>
    </div>
  </div>

  <div id="page-container">

    <?php if (!empty($success_message)): ?>
      <div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
      <div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="users-table-card">
      <table class="users-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($u = $users->fetch_assoc()): ?>
          <tr>
            <td class="user-name-cell">
              <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
              <span><?= htmlspecialchars($u['name']) ?></span>
            </td>
            <td class="td-muted"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="role-badge <?= $u['role'] === 'superadmin' ? 'role-super' : 'role-registrar' ?>">
                <?= $u['role'] === 'superadmin' ? 'Super Admin' : 'Registrar' ?>
              </span>
            </td>
            <td>
              <span class="status-badge <?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td class="actions-cell">
              <a href="users.php?edit_id=<?= $u['id'] ?>" class="btn-u-edit">Edit</a>
              <?php if ($u['id'] != $_SESSION['user_id'] ?? 0): ?>
                <a href="users.php?toggle_id=<?= $u['id'] ?>"
                   class="<?= $u['is_active'] ? 'btn-u-deactivate' : 'btn-u-activate' ?>"
                   onclick="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?')">
                  <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                </a>
                <?php if (!empty($u['locked_at'])): ?>
                  <a href="users.php?unlock_id=<?= $u['id'] ?>" class="btn-u-activate" onclick="return confirm('Unlock this account?')">
                    <i class="bi bi-unlock-fill"></i> Unlock
                  </a>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add User</h2>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form method="POST" action="users.php">
      <input type="hidden" name="id" value="0">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" class="form-input" required/></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-input" required/></div>
          <div class="form-group"><label>Password *</label><input type="password" name="password" class="form-input" required/></div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-input">
              <option value="registrar">Registrar</option>
              <option value="superadmin">Super Admin</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn-save">Save User</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<?php if ($edit_user): ?>
<div class="modal-overlay open" id="edit-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Edit User</h2>
      <a href="users.php" class="modal-close">&times;</a>
    </div>
    <form method="POST" action="users.php">
      <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" class="form-input" value="<?= htmlspecialchars($edit_user['name']) ?>" required/></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($edit_user['email']) ?>" required/></div>
          <div class="form-group"><label>New Password <span style="font-weight:400;color:var(--color-muted)">(leave blank to keep)</span></label><input type="password" name="password" class="form-input"/></div>
          <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-input">
              <option value="registrar" <?= $edit_user['role'] === 'registrar' ? 'selected' : '' ?>>Registrar</option>
              <option value="superadmin" <?= $edit_user['role'] === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="users.php" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-save">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="../js/nav.js"></script>
<script>
  const addModal = document.getElementById('add-modal');
  document.getElementById('btn-add-user').onclick  = () => addModal.classList.add('open');
  document.getElementById('modal-close').onclick   = () => addModal.classList.remove('open');
  document.getElementById('modal-cancel').onclick  = () => addModal.classList.remove('open');
</script>
</body>
</html>
