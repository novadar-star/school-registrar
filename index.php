<?php
session_start();
require_once './mysql/db.php';

$email       = '';
$email_err   = '';
$password_err = '';

if (isset($_POST['submit'])) {
  $email    = trim($_POST['email']    ?? '');
  $password = trim($_POST['password'] ?? '');

  if (empty($email)) {
    $email_err = "Please enter your email.";
  } elseif (empty($password)) {
    $password_err = "Please enter your password.";
  } else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
      $email_err = "Email is not registered.";
    } elseif (!$row['is_active']) {
      $password_err = "This account has been deactivated. Contact your administrator.";
    } elseif (!empty($row['locked_at']) && strtotime($row['locked_at']) > time() - 900) {
      // Locked for 15 minutes
      $password_err = "Account temporarily locked due to too many failed attempts. Try again in 15 minutes.";
    } elseif (!password_verify($password, $row['password'])) {
      // Increment failed attempts
      $attempts = intval($row['failed_attempts'] ?? 0) + 1;
      if ($attempts >= 5) {
        $conn->query("UPDATE users SET failed_attempts=$attempts, locked_at=NOW() WHERE id={$row['id']}");
        $password_err = "Too many failed attempts. Account locked for 15 minutes.";
      } else {
        $conn->query("UPDATE users SET failed_attempts=$attempts WHERE id={$row['id']}");
        $password_err = "Incorrect password. " . (5 - $attempts) . " attempt(s) remaining.";
      }
    } else {
      // Successful login — reset failed attempts
      $conn->query("UPDATE users SET failed_attempts=0, locked_at=NULL WHERE id={$row['id']}");
      $_SESSION['name']    = $row['name'];
      $_SESSION['role']    = $row['role'];
      $_SESSION['user_id'] = $row['id'];
      session_regenerate_id(true);
      header("Location: pages/dashboard.php");
      exit();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>COJ Registrar Portal — Login</title>
  <link rel="icon" type="image/x-icon" href="./images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;1,700;1,800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/login.css">
</head>
<body>

<div class="card">

  <!-- LEFT PANEL -->
  <div class="left">
    <h1>Manage your<br>school with</h1>
    <h1 class="accent">ease</h1>
    <p>Access your student's enrollment<br>records in an organized and secured way.</p>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right">
    <img src="./images/COJ.png" class="logo" alt="COJ Logo">
    <h2>Welcome back, Admin!</h2>
    <p class="subtitle">REGISTRAR PORTAL</p>
    <p style="text-align:center;margin-bottom:16px;">
      <a href="home.php" style="font-size:12px;color:var(--color-muted,#6b7280);text-decoration:none;">← Back to School Website</a>
    </p>

    <form id="login-form" method="POST" action="">
      <!-- Email -->
      <div class="field">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <span><i class="bi bi-envelope"></i></span>
          <input id="email" type="email" name="email" placeholder="admin@gmail.com"
                 value="<?= htmlspecialchars($email) ?>" required/>
        </div>
        <?php if ($email_err): ?>
          <div style="color:#dc2626;font-size:11px;margin-top:4px;"><?= htmlspecialchars($email_err) ?></div>
        <?php endif; ?>
      </div>

      <!-- Password + show/hide toggle -->
      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap" style="position:relative;">
          <span><i class="bi bi-lock-fill"></i></span>
          <input id="password" type="password" name="password" placeholder="Enter your password" required/>
          <button type="button" id="toggle-pw"
            onclick="togglePassword()"
            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;font-size:16px;padding:0;line-height:1;">
            <i class="bi bi-eye" id="toggle-pw-icon"></i>
          </button>
        </div>
        <?php if ($password_err): ?>
          <div style="color:#dc2626;font-size:11px;margin-top:4px;"><?= htmlspecialchars($password_err) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-login" name="submit">Log In</button>
    </form>
  </div>

</div>

<script>
function togglePassword() {
  const input = document.getElementById('password');
  const icon  = document.getElementById('toggle-pw-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
