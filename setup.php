<?php

require_once './mysql/db.php';

$statements = [
  "CREATE DATABASE IF NOT EXISTS `school_registrar` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",

  "CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('superadmin','registrar','finance') NOT NULL DEFAULT 'registrar',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `school_years` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `label` varchar(20) NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `label` (`label`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "INSERT IGNORE INTO `school_years` (`label`, `is_active`) VALUES ('2024-2025', 0), ('2025-2026', 1)",

  "CREATE TABLE IF NOT EXISTS `grade_levels` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(30) NOT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "INSERT IGNORE INTO `grade_levels` (`id`, `name`) VALUES (1,'Grade 7'),(2,'Grade 8'),(3,'Grade 9'),(4,'Grade 10')",

  "CREATE TABLE IF NOT EXISTS `sections` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `grade_level_id` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `grade_level_id` (`grade_level_id`),
    CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "INSERT IGNORE INTO `sections` (`name`, `grade_level_id`) VALUES
    ('Newton',1),('Einstein',1),('Curie',1),('Franklin',1),
    ('Newton',2),('Einstein',2),('Curie',2),('Franklin',2),
    ('Newton',3),('Einstein',3),('Curie',3),('Franklin',3),
    ('Newton',4),('Einstein',4),('Curie',4),('Franklin',4)",

  "CREATE TABLE IF NOT EXISTS `students` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `lrn` varchar(20) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `middle_name` varchar(50) DEFAULT NULL,
    `grade_level_id` int(11) NOT NULL,
    `section_id` int(11) DEFAULT NULL,
    `school_year_id` int(11) DEFAULT NULL,
    `city` varchar(100) DEFAULT NULL,
    `contact_number` varchar(20) DEFAULT NULL,
    `student_type` enum('new','old') DEFAULT 'old',
    `photo` varchar(255) DEFAULT NULL,
    `is_archived` tinyint(1) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lrn` (`lrn`),
    KEY `grade_level_id` (`grade_level_id`),
    KEY `section_id` (`section_id`),
    CONSTRAINT `students_ibfk_1` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`),
    CONSTRAINT `students_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `notes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL DEFAULT 'Untitled Note',
    `body` text DEFAULT NULL,
    `category` enum('General','Academic','Meeting','Concern') DEFAULT 'General',
    `created_at` datetime DEFAULT current_timestamp(),
    `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ref_number` varchar(20) DEFAULT NULL,
    `student_id` int(11) NOT NULL,
    `school_year_id` int(11) NOT NULL,
    `grade_level_id` int(11) NOT NULL,
    `section_id` int(11) DEFAULT NULL,
    `status` enum('pending','enrolled','dropped') DEFAULT 'pending',
    `enrolled_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_enrollment` (`student_id`,`school_year_id`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`),
    FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels`(`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `fees` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `grade_level_id` int(11) NOT NULL,
    `school_year_id` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels`(`id`),
    FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `payments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) NOT NULL,
    `fee_id` int(11) NOT NULL,
    `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
    `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
    `status` enum('paid','partial','unpaid') DEFAULT 'unpaid',
    `paid_at` date DEFAULT NULL,
    `or_number` varchar(50) DEFAULT NULL,
    `payment_method` enum('cash','check','bank_transfer') DEFAULT 'cash',
    `proof_file` varchar(255) DEFAULT NULL,
    `verified_by` int(11) DEFAULT NULL,
    `notes` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`fee_id`) REFERENCES `fees`(`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `parent_accounts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) NOT NULL,
    `name` varchar(150) NOT NULL,
    `email` varchar(150) NOT NULL,
    `password` varchar(255) NOT NULL,
    `contact` varchar(20) DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `requirements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` varchar(255) DEFAULT NULL,
    `student_type` enum('new','old','both') DEFAULT 'both',
    `is_required` tinyint(1) DEFAULT 1,
    `sort_order` int(11) DEFAULT 0,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "INSERT IGNORE INTO `requirements` (`name`,`description`,`student_type`,`sort_order`) VALUES
    ('PSA Birth Certificate','Original or certified true copy','new',1),
    ('Form 138 / Report Card','Previous school year report card','both',2),
    ('Good Moral Certificate','From previous school','new',3),
    ('2x2 ID Photo','2 pieces, white background','both',4),
    ('Baptismal Certificate','For Catholic students','new',5)",

  "CREATE TABLE IF NOT EXISTS `student_requirements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) NOT NULL,
    `requirement_id` int(11) NOT NULL,
    `school_year_id` int(11) NOT NULL,
    `file_path` varchar(255) DEFAULT NULL,
    `status` enum('missing','submitted','verified') DEFAULT 'missing',
    `submitted_at` datetime DEFAULT NULL,
    `verified_by` int(11) DEFAULT NULL,
    `verified_at` datetime DEFAULT NULL,
    `notes` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_req` (`student_id`,`requirement_id`,`school_year_id`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`requirement_id`) REFERENCES `requirements`(`id`),
    FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS `clearance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) NOT NULL,
    `school_year_id` int(11) NOT NULL,
    `library_status` enum('pending','cleared') DEFAULT 'pending',
    `library_by` int(11) DEFAULT NULL,
    `library_at` datetime DEFAULT NULL,
    `registrar_status` enum('pending','cleared') DEFAULT 'pending',
    `registrar_by` int(11) DEFAULT NULL,
    `registrar_at` datetime DEFAULT NULL,
    `finance_status` enum('pending','cleared') DEFAULT 'pending',
    `finance_by` int(11) DEFAULT NULL,
    `finance_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_clearance` (`student_id`,`school_year_id`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  // Seed superadmin — password: Admin@1234
  "INSERT IGNORE INTO `users` (`email`,`name`,`password`,`role`,`is_active`) VALUES
    ('superadmin@school.com','Super Admin','\$2y\$10\$TKh8H1.PfbuNIAHJj5HuxemtLgUgaQ4.LG5O4K5RoTGMFCGqMGnAa','superadmin',1)",
];

$errors = [];
$success = 0;

foreach ($statements as $sql) {
  $sql = trim($sql);
  if (empty($sql)) continue;
  if ($conn->query($sql) === true) {
    $success++;
  } else {
    $errors[] = $conn->error . ' — ' . substr($sql, 0, 80);
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Database Setup</title>
  <style>
    body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; }
    .ok  { color: green; }
    .err { color: red; background: #fdeaea; padding: 8px; border-radius: 4px; margin: 4px 0; font-size: 13px; }
    h2   { margin-bottom: 16px; }
  </style>
</head>
<body>
  <h2>COJ Database Setup</h2>
  <p class="ok">✅ <?= $success ?> statements executed successfully.</p>
  <?php if ($errors): ?>
    <p style="color:red;">⚠️ <?= count($errors) ?> errors (duplicates are normal and safe to ignore):</p>
    <?php foreach ($errors as $e): ?>
      <div class="err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="ok">✅ All tables created. Database is ready.</p>
  <?php endif; ?>
  <hr>
  <p><strong>⚠️ Delete setup.php from your server after this runs.</strong></p>
  <p><a href="/home.php">→ Go to Landing Page</a> &nbsp; <a href="/index.php">→ Admin Login</a></p>
</body>
</html>
