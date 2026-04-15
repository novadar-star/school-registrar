<?php
include('../mysql/db.php');
session_start();

if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}

if (empty($_GET['id'])) {
  header('Location: students.php');
  exit();
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
  SELECT s.*, g.name as grade_name, sec.name as section_name
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  LEFT JOIN sections sec ON s.section_id = sec.id
  WHERE s.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
  header('Location: students.php');
  exit();
}

$fullname = htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']);
$grade    = htmlspecialchars($student['grade_name'] ?? 'N/A');
$section  = htmlspecialchars($student['section_name'] ?? 'N/A');
$lrn      = htmlspecialchars($student['lrn']);
$city     = htmlspecialchars($student['city'] ?? '—');
$contact  = htmlspecialchars($student['contact_number'] ?? '—');
$type     = $student['student_type'];
$photo    = !empty($student['photo']) ? 'uploads/' . htmlspecialchars($student['photo']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $fullname ?> — Profile</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

  <?php $active_page = 'students'; include('includes/sidebar.php'); ?>

  <!-- MAIN -->
  <div id="main">
    <div id="topbar">
      <div class="topbar-left">
        <div class="page-title">Student Profile</div>
        <div class="page-sub"><a href="students.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Students</a></div>
      </div>
      <div class="topbar-actions">
        <a href="students.php?edit_id=<?= $student['id'] ?>" class="btn-profile-edit"><i class="bi bi-pencil-fill"></i> Edit</a>
        <button onclick="window.print()" class="btn-profile-print"><i class="bi bi-printer-fill"></i> Print</button>
      </div>
    </div>

    <div id="page-container">
      <div class="profile-layout">

        <!-- LEFT: Avatar + quick info -->
        <div class="profile-card">
          <div class="profile-avatar">
            <?php if ($photo): ?>
              <img src="<?= $photo ?>" alt="Student Photo"/>
            <?php else: ?>
              <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
            <?php endif; ?>
          </div>
          <div class="profile-name"><?= $fullname ?></div>
          <div class="profile-lrn">LRN: <?= $lrn ?></div>
          <span class="profile-badge <?= $type === 'new' ? 'badge-new' : 'badge-old' ?>">
            <?= ucfirst($type) ?> Student
          </span>

          <div class="profile-quick">
            <div class="quick-item">
              <span class="quick-icon"><i class="bi bi-mortarboard-fill"></i></span>
              <div>
                <div class="quick-label">Grade</div>
                <div class="quick-value"><?= $grade ?></div>
              </div>
            </div>
            <div class="quick-item">
              <span class="quick-icon"><i class="bi bi-grid-fill"></i></span>
              <div>
                <div class="quick-label">Section</div>
                <div class="quick-value"><?= $section ?></div>
              </div>
            </div>
            <div class="quick-item">
              <span class="quick-icon"><i class="bi bi-geo-alt-fill"></i></span>
              <div>
                <div class="quick-label">City</div>
                <div class="quick-value"><?= $city ?></div>
              </div>
            </div>
            <div class="quick-item">
              <span class="quick-icon"><i class="bi bi-telephone-fill"></i></span>
              <div>
                <div class="quick-label">Contact</div>
                <div class="quick-value"><?= $contact ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: Details -->
        <div class="profile-details">

          <div class="detail-section">
            <div class="detail-section-title"><i class="bi bi-person-lines-fill"></i> Personal Information</div>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">First Name</div>
                <div class="detail-value"><?= htmlspecialchars($student['first_name']) ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Middle Name</div>
                <div class="detail-value"><?= htmlspecialchars($student['middle_name'] ?: '—') ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Last Name</div>
                <div class="detail-value"><?= htmlspecialchars($student['last_name']) ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">LRN</div>
                <div class="detail-value"><?= $lrn ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">City / Address</div>
                <div class="detail-value"><?= $city ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Contact Number</div>
                <div class="detail-value"><?= $contact ?></div>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <div class="detail-section-title"><i class="bi bi-mortarboard-fill"></i> Academic Information</div>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Grade Level</div>
                <div class="detail-value"><?= $grade ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Section</div>
                <div class="detail-value"><?= $section ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Student Type</div>
                <div class="detail-value"><?= ucfirst($type) ?></div>
              </div>
            </div>
          </div>

          <?php
          // Parent account
          $parent_acct = $conn->query("SELECT * FROM parent_accounts WHERE student_id={$student['id']} LIMIT 1")->fetch_assoc();
          $profile_success = $_GET['success'] ?? '';
          $profile_error   = $_GET['error']   ?? '';
          ?>

          <?php if ($profile_success): ?>
            <div style="background:#e8f5e9;border:1px solid #c8e6c9;border-radius:8px;padding:10px 16px;font-size:13px;color:#27ae60;margin-bottom:12px;"><?= htmlspecialchars($profile_success) ?></div>
          <?php endif; ?>
          <?php if ($profile_error): ?>
            <div style="background:#fdeaea;border:1px solid #f5c6c6;border-radius:8px;padding:10px 16px;font-size:13px;color:var(--color-danger);margin-bottom:12px;"><?= htmlspecialchars($profile_error) ?></div>
          <?php endif; ?>

          <div class="detail-section">
            <div class="detail-section-title"><i class="bi bi-person-heart"></i> Parent / Guardian Portal Access</div>
            <div style="padding:20px;">
              <?php if ($parent_acct): ?>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                  <div>
                    <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($parent_acct['name']) ?></div>
                    <div style="font-size:12px;color:var(--color-muted);"><?= htmlspecialchars($parent_acct['email']) ?></div>
                  </div>
                  <span style="padding:3px 12px;border-radius:999px;font-size:11px;font-weight:700;background:<?= $parent_acct['is_active'] ? '#dcfce7' : '#fdeaea' ?>;color:<?= $parent_acct['is_active'] ? '#166534' : 'var(--color-danger)' ?>;">
                    <?= $parent_acct['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                  <span style="font-size:12px;color:var(--color-muted);">Portal: <a href="../portal/login.php" target="_blank" style="color:var(--color-primary);">portal/login.php</a></span>
                </div>
              <?php else: ?>
                <p style="font-size:13px;color:var(--color-muted);margin-bottom:16px;">No parent account yet. Create one to give the parent access to the portal.</p>
                <form method="POST" action="create_parent.php" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:560px;">
                  <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                  <div><label style="font-size:11px;font-weight:700;color:var(--color-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Parent Name *</label>
                    <input type="text" name="parent_name" class="form-input" required style="width:100%;"/></div>
                  <div><label style="font-size:11px;font-weight:700;color:var(--color-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Email *</label>
                    <input type="email" name="parent_email" class="form-input" required style="width:100%;"/></div>
                  <div><label style="font-size:11px;font-weight:700;color:var(--color-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Contact</label>
                    <input type="text" name="parent_contact" class="form-input" style="width:100%;"/></div>
                  <div><label style="font-size:11px;font-weight:700;color:var(--color-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Password *</label>
                    <input type="password" name="parent_password" class="form-input" required style="width:100%;"/></div>
                  <div style="grid-column:1/-1;">
                    <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-sm);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;">
                      <i class="bi bi-person-plus-fill"></i> Create Parent Account
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="../js/nav.js"></script>
</body>
</html>
