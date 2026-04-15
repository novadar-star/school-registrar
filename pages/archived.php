<?php
session_start();
include('../mysql/db.php');

if (!isset($_SESSION['name'])) {
  header('Location: ../index.php'); exit();
}

// ── Restore student ───────────────────────────────────────
if (isset($_GET['restore_student'])) {
  $id = intval($_GET['restore_student']);
  $conn->query("UPDATE students SET is_archived = 0 WHERE id = $id");
  header("Location: archived.php?success=Student restored"); exit();
}

// ── Restore teacher ───────────────────────────────────────
if (isset($_GET['restore_teacher'])) {
  $id = intval($_GET['restore_teacher']);
  $conn->query("UPDATE teachers SET is_archived = 0 WHERE id = $id");
  header("Location: archived.php?success=Teacher restored"); exit();
}

// ── Permanent delete student ──────────────────────────────
if (isset($_GET['delete_student'])) {
  $id = intval($_GET['delete_student']);
  $conn->query("DELETE FROM students WHERE id = $id AND is_archived = 1");
  header("Location: archived.php?success=Student permanently deleted"); exit();
}

// ── Permanent delete teacher ──────────────────────────────
if (isset($_GET['delete_teacher'])) {
  $id = intval($_GET['delete_teacher']);
  $conn->query("DELETE FROM teachers WHERE id = $id AND is_archived = 1");
  header("Location: archived.php?success=Teacher permanently deleted"); exit();
}

$success_message = $_GET['success'] ?? '';
$tab = $_GET['tab'] ?? 'students';

// ── Archived students ─────────────────────────────────────
$archived_students = $conn->query("
  SELECT s.*, g.name as grade_name, sec.name as section_name, sy.label as school_year
  FROM students s
  LEFT JOIN grade_levels g   ON s.grade_level_id = g.id
  LEFT JOIN sections sec     ON s.section_id = sec.id
  LEFT JOIN school_years sy  ON s.school_year_id = sy.id
  WHERE s.is_archived = 1
  ORDER BY s.last_name ASC
");

// ── Archived teachers ─────────────────────────────────────
$teachers_exist = $conn->query("SHOW TABLES LIKE 'teachers'")->num_rows > 0;
$archived_teachers = $teachers_exist
  ? $conn->query("SELECT * FROM teachers WHERE is_archived = 1 ORDER BY last_name ASC")
  : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Archived Records — School Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/archived.css">
</head>
<body>

<?php $active_page = 'students'; include('includes/sidebar.php'); ?>

<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Archived Records</div>
      <div class="page-sub"><a href="students.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Students</a></div>
    </div>
  </div>

  <div id="page-container">

    <?php if (!empty($success_message)): ?>
      <div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="archive-tabs">
      <a href="archived.php?tab=students" class="archive-tab <?= $tab === 'students' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> Students
      </a>
      <a href="archived.php?tab=teachers" class="archive-tab <?= $tab === 'teachers' ? 'active' : '' ?>">
        <i class="bi bi-person-workspace"></i> Teachers
      </a>
    </div>

    <!-- STUDENTS TAB -->
    <?php if ($tab === 'students'): ?>
    <div class="archive-table-card">
      <table class="archive-table">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>LRN</th>
            <th>Grade &amp; Section</th>
            <th>School Year</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($archived_students->num_rows === 0): ?>
            <tr><td colspan="6" class="empty-row">No archived students.</td></tr>
          <?php else: ?>
            <?php while ($s = $archived_students->fetch_assoc()): ?>
            <tr>
              <td>
                <?php if (!empty($s['photo'])): ?>
                  <img src="uploads/<?= htmlspecialchars($s['photo']) ?>" class="arc-photo"/>
                <?php else: ?>
                  <div class="arc-avatar"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' ' . $s['middle_name']) ?></td>
              <td class="td-muted"><?= htmlspecialchars($s['lrn']) ?></td>
              <td><?= htmlspecialchars(($s['grade_name'] ?? '—') . ' - ' . ($s['section_name'] ?? '—')) ?></td>
              <td><?= htmlspecialchars($s['school_year'] ?? '—') ?></td>
              <td class="actions-cell">
                <a href="archived.php?restore_student=<?= $s['id'] ?>"
                   class="btn-arc-restore"
                   onclick="return confirm('Restore this student?')">
                  <i class="bi bi-arrow-counterclockwise"></i> Restore
                </a>
                <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
                <a href="archived.php?delete_student=<?= $s['id'] ?>"
                   class="btn-arc-delete"
                   onclick="return confirm('Permanently delete this student? This cannot be undone.')">
                  <i class="bi bi-trash-fill"></i> Delete
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- TEACHERS TAB -->
    <?php else: ?>
    <div class="archive-table-card">
      <table class="archive-table">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Subject</th>
            <th>Department</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$archived_teachers || $archived_teachers->num_rows === 0): ?>
            <tr><td colspan="5" class="empty-row">No archived teachers.</td></tr>
          <?php else: ?>
            <?php while ($t = $archived_teachers->fetch_assoc()): ?>
            <tr>
              <td>
                <?php if (!empty($t['photo'])): ?>
                  <img src="uploads/<?= htmlspecialchars($t['photo']) ?>" class="arc-photo"/>
                <?php else: ?>
                  <div class="arc-avatar"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name']) ?></td>
              <td class="td-muted"><?= htmlspecialchars($t['subject'] ?? '—') ?></td>
              <td class="td-muted"><?= htmlspecialchars($t['department'] ?? '—') ?></td>
              <td class="actions-cell">
                <a href="archived.php?restore_teacher=<?= $t['id'] ?>&tab=teachers"
                   class="btn-arc-restore"
                   onclick="return confirm('Restore this teacher?')">
                  <i class="bi bi-arrow-counterclockwise"></i> Restore
                </a>
                <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
                <a href="archived.php?delete_teacher=<?= $t['id'] ?>&tab=teachers"
                   class="btn-arc-delete"
                   onclick="return confirm('Permanently delete this teacher? This cannot be undone.')">
                  <i class="bi bi-trash-fill"></i> Delete
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="../js/nav.js"></script>
</body>
</html>
