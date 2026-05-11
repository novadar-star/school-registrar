<?php
/**
 * reset_admin_password.php
 * Resets the superadmin password to Admin@1234 using a fresh hash.
 * DELETE THIS FILE immediately after running.
 * Visit: http://localhost/school-registrar/reset_admin_password.php
 */
require_once './mysql/db.php';

$new_password = 'Admin@1234';
$hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password=?, failed_attempts=0, locked_at=NULL WHERE email='superadmin@school.com'");
$stmt->bind_param("s", $hash);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "<p style='font-family:sans-serif;color:green;font-size:16px;'>
        ✅ Password reset successfully.<br>
        Email: <strong>superadmin@school.com</strong><br>
        Password: <strong>Admin@1234</strong><br><br>
        <strong style='color:red;'>⚠️ Delete this file now!</strong>
        <a href='index.php' style='display:block;margin-top:12px;'>→ Go to Login</a>
    </p>";
} else {
    echo "<p style='font-family:sans-serif;color:red;'>
        ❌ No rows updated. Check that superadmin@school.com exists in the users table.<br>
        Error: " . htmlspecialchars($conn->error) . "
    </p>";
}
