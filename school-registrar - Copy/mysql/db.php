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
  die("Connection failed: " . $conn->connect_error);
}

// Ensure consistent charset
$conn->set_charset("utf8mb4");
?>