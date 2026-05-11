-- Fix parent_accounts: drop the student_id FK and make it nullable
-- The system uses parent_student_links for the parent<->student relationship instead.

ALTER TABLE `parent_accounts`
  DROP FOREIGN KEY `parent_accounts_ibfk_1`;

ALTER TABLE `parent_accounts`
  MODIFY COLUMN `student_id` INT DEFAULT NULL;
