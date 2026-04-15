<?php
session_name('parent_session');
session_start();

if (isset($_SESSION['parent_id'])) {
  header('Location: dashboard.php'); exit();
}

require_once '../mysql/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');

  $stmt = $conn->prepare("SELECT * FROM parent_accounts WHERE email = ? AND is_active = 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $parent = $stmt->get_result()->fetch_assoc();

  if ($parent && password_verify($password, $parent['password'])) {
    $_SESSION['parent_id']   = $parent['id'];
    $_SESSION['parent_name'] = $parent['name'];
    $_SESSION['student_id']  = $parent['student_id'];
    header('Location: dashboard.php'); exit();
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

    <form method="POST" action="login.php">
      <div class="portal-field">
        <label>Email Address</label>
        <div class="portal-input-wrap">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" required placeholder="parent@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
        </div>
      </div>
      <div class="portal-field">
        <label>Password</label>
        <div class="portal-input-wrap">
          <i class="bi bi-lock-fill"></i>
          <input type="password" name="password" required placeholder="Enter your password"/>
        </div>
      </div>
      <button type="submit" class="portal-btn-login">Log In</button>
    </form>

    <div class="portal-links">
      <a href="../home.php">← Back to School Website</a>
      <span>·</span>
      <a href="../home.php#enroll">New enrollment? Apply here</a>
    </div>
  </div>
</div>
</body>
</html>
