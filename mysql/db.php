<?php

// database connection — supports Railway env vars and local XAMPP
$conn = new mysqli(
    getenv('MYSQLHOST')     ?: 'localhost',
    getenv('MYSQLUSER')     ?: 'root',
    getenv('MYSQLPASSWORD') ?: '',
    getenv('MYSQLDATABASE') ?: 'school_registrar',
    intval(getenv('MYSQLPORT') ?: 3306)
);

if($conn->connect_error) {
  error_log("DB connection failed: " . $conn->connect_error);
  http_response_code(503);
  die("Service temporarily unavailable. Please try again later.");
}

// Ensure consistent charset
$conn->set_charset("utf8mb4");
?>