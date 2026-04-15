<?php
session_start();
include('../mysql/db.php');

if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}

// Handle add/edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id          = intval($_POST['id'] ?? 0);
  $first_name  = trim($_POST['first_name'] ?? '');
  $last_name   = trim($_POST['last_name'] ?? '');
  $middle_name = trim($_POST['middle_name'] ?? '');
  $email       = trim($_POST['email'] ?? '');
  $contact     = trim($_POST['contact_number'] ?? '');
  $subject     = trim($_POST['subject'] ?? '');
  $department  = trim($_POST['department'] ?? '');

  if (empty($first_name) || empty($last_name)) {
    header("Location: teachers.php?error=First and last name are required");
    exit();
  }

  // Photo upload
  $photo = $_POST['existing_photo'] ?? '';
  if (!empty($_FILES['photo']['tmp_name'])) {
    $photo = basename($_FILES['photo']['name']);
    move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);
  }

  if ($id > 0) {
    $stmt = $conn->prepare("UPDATE teachers SET first_name=?, last_name=?, middle_name=?, email=?, contact_number=?, subject=?, department=?, photo=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $first_name, $last_name, $middle_name, $email, $contact, $subject, $department, $photo, $id);
  } else {
    $stmt = $conn->prepare("INSERT INTO teachers (first_name, last_name, middle_name, email, contact_number, subject, department, photo) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss", $first_name, $last_name, $middle_name, $email, $contact, $subject, $department, $photo);
  }

  $stmt->execute() 
    ? header("Location: teachers.php?success=" . ($id > 0 ? "Teacher updated" : "Teacher added"))
    : header("Location: teachers.php?error=" . urlencode($conn->error));
  exit();
}

// Archive teacher
if (isset($_GET['archive_id'])) {
  $aid = intval($_GET['archive_id']);
  $conn->query("UPDATE teachers SET is_archived = 1 WHERE id = $aid");
  header("Location: teachers.php?success=Teacher archived");
  exit();
}

$error_message   = $_GET['error']   ?? '';
$success_message = $_GET['success'] ?? '';

// Fetch for edit modal
$edit_teacher = null;
if (!empty($_GET['edit_id'])) {
  $eid  = intval($_GET['edit_id']);
  $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
  $stmt->bind_param("i", $eid);
  $stmt->execute();
  $edit_teacher = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$teachers = $conn->query("SELECT * FROM teachers WHERE is_archived = 0 ORDER BY last_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Teacher</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/teachers.css">
</head>
<body>

  <?php $active_page = 'students'; include('includes/sidebar.php'); ?>

  <div id="main">
    <div id="topbar">
      <div class="topbar-left">
        <div class="page-title">Teachers</div>
        <div class="page-sub"><a href="attendance.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Attendance</a></div>
      </div>
      <div class="topbar-actions">
        <a href="archived.php?tab=teachers" class="btn-add-teacher" style="background:rgba(255,255,255,0.15);color:#fff;">
          <i class="bi bi-archive-fill"></i> Archived
        </a>
        <button class="btn-add-teacher" id="btn-add-teacher"><i class="bi bi-plus-lg"></i> Add Teacher</button>
      </div>
    </div>

    <div id="page-container">

      <?php if (!empty($success_message)): ?>
        <div class="alert-success-bar"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert-error-bar"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <div class="teachers-grid">
        <?php while ($t = $teachers->fetch_assoc()): ?>
        <div class="teacher-card">
          <div class="teacher-card-photo">
            <?php if (!empty($t['photo'])): ?>
              <img src="uploads/<?= htmlspecialchars($t['photo']) ?>" alt="Photo"/>
            <?php else: ?>
              <div class="teacher-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
            <?php endif; ?>
          </div>
          <div class="teacher-card-info">
            <div class="teacher-card-name"><?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name']) ?></div>
            <div class="teacher-card-subject"><?= htmlspecialchars($t['subject'] ?? '—') ?></div>
            <div class="teacher-card-dept"><?= htmlspecialchars($t['department'] ?? '') ?></div>
          </div>
          <div class="teacher-card-actions">
            <a href="teacher_profile.php?id=<?= $t['id'] ?>" class="btn-tc-view">View</a>
            <a href="teachers.php?edit_id=<?= $t['id'] ?>" class="btn-tc-edit">Edit</a>
            <a href="teachers.php?archive_id=<?= $t['id'] ?>" class="btn-tc-archive"
               onclick="return confirm('Archive this teacher?')">Archive</a>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

    </div>
  </div>

  <!-- ADD MODAL -->
  <div class="modal-overlay" id="add-modal">
    <div class="modal-box">
      <div class="modal-header">
        <h2>Add Teacher</h2>
        <button class="modal-close" id="modal-close">&times;</button>
      </div>
      <form action="teachers.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="0">
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-input" pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" required/></div>
            <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" class="form-input" pattern="[a-zA-ZÀ-ÿ\s\-\.]*" title="Letters only"/></div>
            <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-input" pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" required/></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-input"/></div>
            <div class="form-group"><label>Contact</label><input type="tel" name="contact_number" class="form-input" placeholder="09XXXXXXXXX" pattern="(09|\+639)\d{9}" maxlength="13" title="Valid PH number: 09XXXXXXXXX"/></div>
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-input"/></div>
            <div class="form-group"><label>Department</label><input type="text" name="department" class="form-input"/></div>
            <div class="form-group"><label>Photo</label><input type="file" name="photo" class="form-input" accept="image/*"/></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" id="modal-cancel">Cancel</button>
          <button type="submit" class="btn-save">Save Teacher</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT MODAL -->
  <?php if ($edit_teacher): ?>
  <div class="modal-overlay open" id="edit-modal">
    <div class="modal-box">
      <div class="modal-header">
        <h2>Edit Teacher</h2>
        <a href="teachers.php" class="modal-close">&times;</a>
      </div>
      <form action="teachers.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $edit_teacher['id'] ?>">
        <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($edit_teacher['photo'] ?? '') ?>">
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-input" pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" value="<?= htmlspecialchars($edit_teacher['first_name']) ?>" required/></div>
            <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" class="form-input" pattern="[a-zA-ZÀ-ÿ\s\-\.]*" title="Letters only" value="<?= htmlspecialchars($edit_teacher['middle_name'] ?? '') ?>"/></div>
            <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-input" pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" value="<?= htmlspecialchars($edit_teacher['last_name']) ?>" required/></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($edit_teacher['email'] ?? '') ?>"/></div>
            <div class="form-group"><label>Contact</label><input type="tel" name="contact_number" class="form-input" placeholder="09XXXXXXXXX" pattern="(09|\+639)\d{9}" maxlength="13" title="Valid PH number: 09XXXXXXXXX" value="<?= htmlspecialchars($edit_teacher['contact_number'] ?? '') ?>"/></div>
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-input" value="<?= htmlspecialchars($edit_teacher['subject'] ?? '') ?>"/></div>
            <div class="form-group"><label>Department</label><input type="text" name="department" class="form-input" value="<?= htmlspecialchars($edit_teacher['department'] ?? '') ?>"/></div>
            <div class="form-group"><label>Photo</label><input type="file" name="photo" class="form-input" accept="image/*"/></div>
          </div>
        </div>
        <div class="modal-footer">
          <a href="teachers.php" class="btn-cancel">Cancel</a>
          <button type="submit" class="btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script src="../js/nav.js"></script>
  <script>
    const addModal    = document.getElementById('add-modal');
    document.getElementById('btn-add-teacher').onclick = () => addModal.classList.add('open');
    document.getElementById('modal-close').onclick     = () => addModal.classList.remove('open');
    document.getElementById('modal-cancel').onclick    = () => addModal.classList.remove('open');
  </script>
</body>
</html>
