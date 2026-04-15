<?php
session_start();
include('../mysql/db.php');

if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}

// Handle attendance save (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
  $teacher_id = intval($_POST['teacher_id']);
  $date       = $_POST['date'];
  $status     = $_POST['status'];
  $remarks    = $conn->real_escape_string($_POST['remarks'] ?? '');

  $stmt = $conn->prepare("
    INSERT INTO teacher_attendance (teacher_id, date, status, remarks)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
  ");
  $stmt->bind_param("isss", $teacher_id, $date, $status, $remarks);
  echo $stmt->execute() ? 'ok' : 'error';
  exit();
}

// Selected date (default today)
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Fetch all active teachers with their attendance for selected date
$result = $conn->query("
  SELECT t.id, t.first_name, t.last_name, t.subject, t.photo,
         a.status, a.remarks
  FROM teachers t
  LEFT JOIN teacher_attendance a ON a.teacher_id = t.id AND a.date = '$selected_date'
  WHERE t.is_archived = 0
  ORDER BY t.last_name ASC
");

// Summary counts for selected date
$summary = $conn->query("
  SELECT
    SUM(a.status = 'present') as present,
    SUM(a.status = 'absent')  as absent,
    SUM(a.status = 'late')    as late,
    COUNT(t.id)               as total
  FROM teachers t
  LEFT JOIN teacher_attendance a ON a.teacher_id = t.id AND a.date = '$selected_date'
  WHERE t.is_archived = 0
")->fetch_assoc();

$present = $summary['present'] ?? 0;
$absent  = $summary['absent']  ?? 0;
$late    = $summary['late']    ?? 0;
$total   = $summary['total']   ?? 0;
$rate    = $total > 0 ? round(($present / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Attendance</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/attendance.css">
</head>
<body>

  <?php $active_page = 'attendance'; include('includes/sidebar.php'); ?>

  <div id="main">
    <div id="topbar">
      <div class="topbar-left">
        <div class="page-title">Attendance</div>
        <div class="page-sub">Track daily faculty attendance</div>
      </div>
      <div class="topbar-actions">
        <a href="teachers.php" class="btn-manage-teachers"><i class="bi bi-people-fill"></i> Manage Teachers</a>
      </div>
    </div>

    <div id="page-container">

      <!-- Date picker -->
      <div class="att-date-row">
        <form method="GET" action="attendance.php" class="date-form">
          <label for="att-date">Date:</label>
          <input type="date" id="att-date" name="date" value="<?= htmlspecialchars($selected_date) ?>" onchange="this.form.submit()"/>
        </form>
        <div class="att-date-label"><?= date('l, F j, Y', strtotime($selected_date)) ?></div>
      </div>

      <!-- Summary cards -->
      <div class="att-stats">
        <div class="att-stat-card">
          <div class="att-stat-label">Present</div>
          <div class="att-stat-value" style="color:var(--color-success)"><?= $present ?></div>
          <div class="att-stat-sub">teachers</div>
        </div>
        <div class="att-stat-card">
          <div class="att-stat-label">Absent</div>
          <div class="att-stat-value" style="color:var(--color-danger)"><?= $absent ?></div>
          <div class="att-stat-sub">teachers</div>
        </div>
        <div class="att-stat-card">
          <div class="att-stat-label">Late</div>
          <div class="att-stat-value" style="color:var(--color-warning)"><?= $late ?></div>
          <div class="att-stat-sub">teachers</div>
        </div>
        <div class="att-stat-card">
          <div class="att-stat-label">Rate</div>
          <div class="att-stat-value"><?= $rate ?>%</div>
          <div class="att-stat-sub">attendance</div>
        </div>
      </div>

      <!-- Attendance table -->
      <?php if ($total == 0): ?>
        <div class="att-empty">No teachers found. <a href="teachers.php">Add teachers first.</a></div>
      <?php else: ?>
      <div class="att-table-card">
        <table class="att-table">
          <thead>
            <tr>
              <th>Photo</th>
              <th>Name</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Remarks</th>
              <th>Save</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()):
              $tid    = $row['id'];
              $status = $row['status'] ?? '';
              $rem    = htmlspecialchars($row['remarks'] ?? '');
            ?>
            <tr id="row-<?= $tid ?>">
              <td>
                <?php if (!empty($row['photo'])): ?>
                  <img src="uploads/<?= htmlspecialchars($row['photo']) ?>" class="att-photo"/>
                <?php else: ?>
                  <div class="att-avatar"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
              </td>
              <td class="att-name"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></td>
              <td class="att-subject"><?= htmlspecialchars($row['subject'] ?? '—') ?></td>
              <td>
                <select class="att-status-select" data-id="<?= $tid ?>">
                  <option value="">— Select —</option>
                  <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Present</option>
                  <option value="absent"  <?= $status === 'absent'  ? 'selected' : '' ?>>Absent</option>
                  <option value="late"    <?= $status === 'late'    ? 'selected' : '' ?>>Late</option>
                </select>
              </td>
              <td>
                <input type="text" class="att-remarks-input" data-id="<?= $tid ?>"
                       placeholder="Optional remarks" value="<?= $rem ?>"/>
              </td>
              <td>
                <button class="btn-att-save" data-id="<?= $tid ?>" onclick="saveAttendance(<?= $tid ?>)">
                  <i class="bi bi-check-lg"></i>
                </button>
                <span class="att-saved-msg" id="saved-<?= $tid ?>"></span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <script src="../js/nav.js"></script>
  <script>
    const selectedDate = '<?= $selected_date ?>';

    function saveAttendance(tid) {
      const status  = document.querySelector(`.att-status-select[data-id="${tid}"]`).value;
      const remarks = document.querySelector(`.att-remarks-input[data-id="${tid}"]`).value;

      if (!status) {
        alert('Please select a status first.');
        return;
      }

      const fd = new FormData();
      fd.append('action', 'save');
      fd.append('teacher_id', tid);
      fd.append('date', selectedDate);
      fd.append('status', status);
      fd.append('remarks', remarks);

      fetch('attendance.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(res => {
          const msg = document.getElementById(`saved-${tid}`);
          if (res === 'ok') {
            msg.textContent = 'Saved!';
            msg.className = 'att-saved-msg saved-ok';
          } else {
            msg.textContent = 'Error';
            msg.className = 'att-saved-msg saved-err';
          }
          setTimeout(() => { msg.textContent = ''; }, 2000);
          // refresh stats
          location.reload();
        });
    }
  </script>
</body>
</html>
