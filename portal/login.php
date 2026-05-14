<?php
session_name('parent_session');
session_start();

if (isset($_SESSION['parent_id'])) {
  header('Location: dashboard.php'); exit();
}

require_once '../mysql/db.php';

$error   = '';
$success = '';
$mode    = $_GET['mode'] ?? 'login'; // 'login' or 'reset'

// ── Password Reset ──────────────────────────────────────────
if ($mode === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email']    ?? '');
  $new_pass = trim($_POST['new_pass'] ?? '');
  $confirm  = trim($_POST['confirm']  ?? '');

  if (empty($email) || empty($new_pass) || empty($confirm)) {
    $error = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Enter a valid email address.";
  } elseif (strlen($new_pass) < 8) {
    $error = "Password must be at least 8 characters.";
  } elseif ($new_pass !== $confirm) {
    $error = "Passwords do not match.";
  } else {
    $stmt = $conn->prepare("SELECT id FROM parent_accounts WHERE email=? AND is_active=1 LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();

    if (!$found) {
      $error = "No active account found with that email address.";
    } else {
      $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
      $stmt2  = $conn->prepare("UPDATE parent_accounts SET password=? WHERE id=?");
      $stmt2->bind_param("si", $hashed, $found['id']);
      $stmt2->execute();
      $success = "Password updated successfully. You can now log in.";
      $mode = 'login';
    }
  }
}

// ── Login ───────────────────────────────────────────────────
if ($mode === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($success)) {
  $email    = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');

  $stmt = $conn->prepare("SELECT * FROM parent_accounts WHERE email = ? AND is_active = 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $parent = $stmt->get_result()->fetch_assoc();

  if ($parent) {
    // Check lockout (5 attempts, 15-minute window)
    if (!empty($parent['locked_at']) && strtotime($parent['locked_at']) > time() - 900) {
      $error = "Account temporarily locked due to too many failed attempts. Try again in 15 minutes.";
    } elseif (password_verify($password, $parent['password'])) {
      // Reset failed attempts on success
      $conn->query("UPDATE parent_accounts SET failed_attempts=0, locked_at=NULL WHERE id={$parent['id']}");
      $_SESSION['parent_id']   = $parent['id'];
      $_SESSION['parent_name'] = $parent['name'];
      $_SESSION['student_id']  = null;
      session_regenerate_id(true);
      header('Location: dashboard.php'); exit();
    } else {
      $attempts = intval($parent['failed_attempts'] ?? 0) + 1;
      if ($attempts >= 5) {
        $conn->query("UPDATE parent_accounts SET failed_attempts=$attempts, locked_at=NOW() WHERE id={$parent['id']}");
        $error = "Too many failed attempts. Account locked for 15 minutes.";
      } else {
        $conn->query("UPDATE parent_accounts SET failed_attempts=$attempts WHERE id={$parent['id']}");
        $error = "Invalid email or password.";
      }
    }
  } else {
    $error = "Invalid email or password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Parent Portal</title>
  <link rel="icon" type="image/x-icon" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/portal.css">
</head>
<body>
<div class="portal-login-wrap">
  <div class="portal-login-card">
    <img src="../images/COJ.png" alt="COJ Logo" class="portal-logo"/>
    <h1>Parent Portal</h1>
    <p class="portal-sub">COJ Catholic Progressive School</p>

    <?php if ($error): ?>
    <div class="portal-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:#dcfce7;color:#166534;border-radius:8px;padding:12px 14px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
      <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($mode === 'reset'): ?>
    <!-- ── RESET PASSWORD FORM ── -->
    <form method="POST" action="login.php?mode=reset">
      <div class="portal-field">
        <label>Email Address</label>
        <div class="portal-input-wrap">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" required placeholder="parent@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
        </div>
      </div>
      <div class="portal-field">
        <label>New Password</label>
        <div class="portal-input-wrap">
          <i class="bi bi-lock-fill"></i>
          <input type="password" name="new_pass" required placeholder="Min. 6 characters"/>
        </div>
      </div>
      <div class="portal-field">
        <label>Confirm New Password</label>
        <div class="portal-input-wrap">
          <i class="bi bi-lock-fill"></i>
          <input type="password" name="confirm" required placeholder="Repeat password"/>
        </div>
      </div>
      <button type="submit" class="portal-btn-login">Set New Password</button>
    </form>
    <div class="portal-links">
      <a href="login.php">← Back to Login</a>
    </div>

    <?php else: ?>
    <!-- ── LOGIN FORM ── -->
    <form method="POST" action="login.php">
      <div class="portal-field">
        <label>Email Address</label>
        <div class="portal-input-wrap">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
        </div>
      </div>
      <div class="portal-field">
        <label>Password</label>
        <div class="portal-input-wrap" style="position:relative;">
          <i class="bi bi-lock-fill"></i>
          <input type="password" name="password" id="portal-pw" required/>
          <button type="button" onclick="togglePortalPw()"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;font-size:16px;padding:0;line-height:1;">
            <i class="bi bi-eye" id="portal-pw-icon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="portal-btn-login">Log In</button>
    </form>
    <div style="text-align:center;margin-top:10px;">
      <a href="login.php?mode=reset" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:500;">
 Forgot your password?
      </a>
    </div>
    <div class="portal-links">
      <a href="../home.php"> Back to School Website</a>
     
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
function togglePortalPw() {
  const inp  = document.getElementById('portal-pw');
  const icon = document.getElementById('portal-pw-icon');
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash'; }
  else { inp.type = 'password'; icon.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
