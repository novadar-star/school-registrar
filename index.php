  
<?php
session_start();
require_once './mysql/db.php';  //defines conenction
//initialize variables
$email = "";
$password = "";
$email_err =  "";
$password_err = "";

//stores name in session
//for processing form submissionn
if(isset($_POST['submit']))
{
$email = trim($_POST['email']);
$password = trim($_POST['password']);

 //check for empty values
if(empty($email))
{
  $email_err = "Please enter your email";
}
elseif(empty($password))
{
    $password_err = "Please enter your password";
}
else{
          //process inputs
          $sql = "SELECT * FROM users WHERE email = ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("s", $email);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Check lockout (3 failed attempts)
            if (!empty($row['locked_at'])) {
              $locked_since = strtotime($row['locked_at']);
              $lockout_mins = 30;
              if (time() - $locked_since < $lockout_mins * 60) {
                $remaining = ceil(($lockout_mins * 60 - (time() - $locked_since)) / 60);
                $password_err = "Account locked due to too many failed attempts. Try again in $remaining minute(s). Contact superadmin to unlock.";
              } else {
                // Auto-unlock after 30 min
                $conn->query("UPDATE users SET failed_attempts=0, locked_at=NULL WHERE id={$row['id']}");
                $row['locked_at'] = null;
                $row['failed_attempts'] = 0;
              }
            }

            if (empty($password_err)) {
              if (password_verify($password, $row['password'])) {
                if (!$row['is_active']) {
                  $password_err = "This account has been deactivated. Contact your administrator.";
                } else {
                  // Reset failed attempts on success
                  $conn->query("UPDATE users SET failed_attempts=0, locked_at=NULL WHERE id={$row['id']}");
                  $_SESSION['name']    = $row['name'];
                  $_SESSION['role']    = $row['role'];
                  $_SESSION['user_id'] = $row['id'];
                  if (isset($_POST['remember'])) {
                    setcookie("cookie_email", $email, time() + 60*60*24*30, '/');
                    setcookie("cookie_remember", '1', time() + 60*60*24*30, '/');
                  }
                  header("location: pages/dashboard.php");
                  exit();
                }
              } else {
                // Increment failed attempts
                $new_attempts = ($row['failed_attempts'] ?? 0) + 1;
                if ($new_attempts >= 3) {
                  $conn->query("UPDATE users SET failed_attempts=$new_attempts, locked_at=NOW() WHERE id={$row['id']}");
                  $password_err = "Account locked after 3 failed attempts. Contact your superadmin to unlock.";
                } else {
                  $conn->query("UPDATE users SET failed_attempts=$new_attempts WHERE id={$row['id']}");
                  $remaining_tries = 3 - $new_attempts;
                  $password_err = "Incorrect password. $remaining_tries attempt(s) remaining before lockout.";
                }
              }
            }
          } else {
            $email_err = "Email is not registered";
          }
}

}

?>
  
  
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8" />
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
        <p style="text-align:center;margin-bottom:16px;"><a href="home.php" style="font-size:12px;color:var(--color-muted,#6b7280);text-decoration:none;">← Back to School Website</a></p>

        <?php
        //check if email is avaialble then display on textbox
        $disp_email = !empty($email) ? $email : (isset($_COOKIE['cookie_email']) ? $_COOKIE['cookie_email'] : "");
        $checked = !empty($remember) ? "checked" : (isset($_COOKIE['cookie_remember']) ? "checked" : "");
        ?>
       
        <form id="login-form" method="POST" action="">
          <div class="field">
            <label for="email">Email Address</label>
            <div class="input-wrap">
              <span><i class="bi bi-envelope"></i></span>
              <input id="email" value="<?=$disp_email?>"  type="email" name="email" placeholder="admin@gmail.com">
             
            </div>
             <div class="text-danger"  style="color:red;font-size:10px;" ><?= $email_err ?></div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <span><i class="bi bi-lock-fill"></i></span>
              <input id="password" type="password" name="password" placeholder="Enter your password">

            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" <?=$checked?> id="flexCheckDefault" name="remember">
              <label class="form-check-label" for="flexCheckDefault">
                Remember Me
              </label>
           </div>
            <div class="text-danger" style="color:red;font-size:10px;"><?= $password_err ?></div>

          </div>

          <a href="#" class="forgot">Contact your administrator to reset your password.</a>

          <button type="submit" class="btn-login" name="submit">Log In</button>

       
          </p>
        </form>
      </div>

    </div>

   
  </body>
  </html>
