<?php
require_once './mysql/db.php';

$steps = [
  "Drop FK constraint"  => "ALTER TABLE `parent_accounts` DROP FOREIGN KEY `parent_accounts_ibfk_1`",
  "Make student_id nullable" => "ALTER TABLE `parent_accounts` MODIFY COLUMN `student_id` INT DEFAULT NULL",
];

echo "<pre style='font-family:monospace;font-size:14px;padding:20px;'>";
echo "<strong>Fixing parent_accounts FK...</strong>\n\n";

foreach ($steps as $label => $sql) {
  if ($conn->query($sql)) {
    echo "✅ $label — OK\n";
  } else {
    echo "⚠️  $label — " . $conn->error . "\n";
  }
}

// Verify final state
$res = $conn->query("SHOW COLUMNS FROM parent_accounts LIKE 'student_id'");
$col = $res->fetch_assoc();
echo "\nCurrent student_id column: Null=" . $col['Null'] . ", Default=" . $col['Default'] . "\n";

// Check no FK remains
$fk = $conn->query("
  SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA='school_registrar'
    AND TABLE_NAME='parent_accounts'
    AND CONSTRAINT_TYPE='FOREIGN KEY'
    AND CONSTRAINT_NAME='parent_accounts_ibfk_1'
");
if ($fk->num_rows === 0) {
  echo "✅ FK parent_accounts_ibfk_1 no longer exists.\n";
} else {
  echo "❌ FK still exists — check manually in phpMyAdmin.\n";
}

echo "\n<strong>Done. <a href='home.php'>Delete this file then try the form again →</a></strong>";
echo "</pre>";
?>
