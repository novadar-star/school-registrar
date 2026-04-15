<?php
include('../mysql/db.php'); 
session_start();

if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}

// Fetch all school years for filter dropdown
$school_years = $conn->query("SELECT * FROM school_years ORDER BY label DESC");
$sy_list = [];
while ($sy = $school_years->fetch_assoc()) $sy_list[] = $sy;

// Repopulate add form values from session flash on validation error
$rf = $_SESSION['add_form'] ?? [
  'first_name'     => '',
  'middle_name'    => '',
  'last_name'      => '',
  'lrn'            => '',
  'grade_level_id' => '',
  'section_id'     => '',
  'city'           => '',
  'contact_number' => '',
  'status'         => 'old',
];
// Clear session flash after reading so it doesn't persist on next load
if (isset($_SESSION['add_form']) && empty($_GET['open_add'])) {
  unset($_SESSION['add_form']);
}
$open_add = !empty($_GET['open_add']);

// Active school year
$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();

$search    = $_GET['search'] ?? '';
$searchParam = "%$search%";
$filter_sy   = $_GET['sy'] ?? '';
$filter_grade  = $_GET['grade'] ?? '';
$filter_status = $_GET['status'] ?? '';

$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$error_message   = $_GET['error'] ?? '';
$success_message = $_GET['success'] ?? '';


//count w search filter
$where_parts = [
  "(s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)",
  "s.is_archived = 0"
];
$bind_types = "ssss";
$bind_vals  = [$searchParam, $searchParam, $searchParam, $searchParam];

if ($filter_sy) {
  $where_parts[] = "s.school_year_id = (SELECT id FROM school_years WHERE label = ?)";
  $bind_types .= "s"; $bind_vals[] = $filter_sy;
}
if ($filter_grade) {
  $where_parts[] = "g.name = ?";
  $bind_types .= "s"; $bind_vals[] = $filter_grade;
}
if ($filter_status) {
  $where_parts[] = "s.student_type = ?";
  $bind_types .= "s"; $bind_vals[] = $filter_status;
}

$where_sql = implode(" AND ", $where_parts);

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM students s
    LEFT JOIN grade_levels g ON s.grade_level_id = g.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE $where_sql");
$countStmt->bind_param($bind_types, ...$bind_vals);
$countStmt->execute();
$total_records = $countStmt->get_result()->fetch_assoc()['total'];
$total_pages   = ceil($total_records / $limit);

$stmt = $conn->prepare("SELECT s.*, g.name as grade_name, sec.name as section_name, sy.label as school_year
        FROM students s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN school_years sy ON s.school_year_id = sy.id
        WHERE $where_sql
        ORDER BY s.last_name ASC
        LIMIT $limit OFFSET $offset");
$stmt->bind_param($bind_types, ...$bind_vals);
$stmt->execute();
$result = $stmt->get_result();


  //  message form add.php  
$error_message   = $_GET['error'] ?? '';
$success_message = $_GET['success'] ?? '';

//fetch student for edit modal
$edit_student = null;
if (!empty($_GET['edit_id'])) {
  $edit_id = intval($_GET['edit_id']);
  $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
  $stmt->bind_param("i", $edit_id);
  $stmt->execute();
  $edit_student = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Students — School Portal</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/students.css">
  <link rel="stylesheet" href="../css/add.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>

  <?php $active_page = 'students'; include('includes/sidebar.php'); ?>

  <!-- ===== MAIN ===== -->
  <div id="main">

    <div id="topbar">
      <div class="topbar-left">
        <div class="page-title">Student Records</div>
        <div class="page-sub">Manage and view all registered students</div>
      </div>
      <div class="topbar-user-chip">
        <i class="bi bi-person-circle"></i>
        <span><?= htmlspecialchars($_SESSION['name']) ?></span>
      </div>
    </div>

    <div id="page-container">
      <div id="page-students">

        <!-- Toolbar -->
        <form method="GET" action="students.php" id="filter-form">
          <input type="hidden" name="page" value="1">
          <div class="students-toolbar">
            <div class="toolbar-left">

              <!-- Search -->
              <div class="search-wrap">
                <i class="bi bi-search search-icon"></i>
                <input type="search" name="search" class="toolbar-search-input"
                       placeholder="Search name or LRN"
                       value="<?= htmlspecialchars($search) ?>"/>
              </div>

              <!-- Grade filter -->
              <div class="filter-group">
                <label class="filter-label">Grade</label>
                <select class="filter-select" name="grade" onchange="this.form.submit()">
                  <option value="">All Grades</option>
                  <option value="Grade 7"  <?= $filter_grade === 'Grade 7'  ? 'selected' : '' ?>>Grade 7</option>
                  <option value="Grade 8"  <?= $filter_grade === 'Grade 8'  ? 'selected' : '' ?>>Grade 8</option>
                  <option value="Grade 9"  <?= $filter_grade === 'Grade 9'  ? 'selected' : '' ?>>Grade 9</option>
                  <option value="Grade 10" <?= $filter_grade === 'Grade 10' ? 'selected' : '' ?>>Grade 10</option>
                </select>
              </div>

              <!-- Status filter -->
              <div class="filter-group">
                <label class="filter-label">Status</label>
                <select class="filter-select" name="status" onchange="this.form.submit()">
                  <option value="">All</option>
                  <option value="new" <?= $filter_status === 'new' ? 'selected' : '' ?>>New</option>
                  <option value="old" <?= $filter_status === 'old' ? 'selected' : '' ?>>Old</option>
                </select>
              </div>

              <!-- School Year filter -->
              <div class="filter-group">
                <label class="filter-label">School Year</label>
                <select class="filter-select" name="sy" onchange="this.form.submit()">
                  <option value="">All Years</option>
                  <?php foreach ($sy_list as $sy): ?>
                    <option value="<?= htmlspecialchars($sy['label']) ?>"
                      <?= $filter_sy === $sy['label'] ? 'selected' : '' ?>>
                      SY <?= htmlspecialchars($sy['label']) ?><?= $sy['is_active'] ? ' ✓' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Search submit -->
              <button type="submit" class="btn-search">
                <i class="bi bi-search"></i> Search
              </button>

              <?php if ($search || $filter_grade || $filter_status || $filter_sy): ?>
                <a href="students.php" class="btn-clear-filters">
                  <i class="bi bi-x-circle"></i> Clear
                </a>
              <?php endif; ?>

            </div>
            <div class="toolbar-right">
              <a href="archived.php" class="btn-export">
                <i class="bi bi-archive-fill"></i> Archived
              </a>
              <a href="export.php?search=<?= urlencode($search) ?>&grade=<?= urlencode($filter_grade) ?>&status=<?= urlencode($filter_status) ?>"
                 class="btn-export">
                <i class="bi bi-download"></i> Export
              </a>
              <button type="button" class="btn-add" id="btn-add-student">+ Add Student</button>
            </div>
          </div>
        </form>

  
      

        <!-- Table -->
        <div class="table-card">
          <table id="students-table">
            <thead>
              <tr>
                <th>Photo</th>
                <th>Name</th>
                <th>LRN</th>
                <th>Grade &amp; Section</th>
                <th>School Year</th>
                <th>City</th> 
                <th>Contact</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>

            <tbody>
            <?php
              //read data of each row; uses internal id for edit/delete links
              while($row = $result->fetch_assoc()):
                $badge = $row['student_type'] === 'new' ? 'badge_active' : 'badge_inactive';

              ?>
                <tr>
                <!-- photo of students -->
                  <td>
                    <?php if(!empty($row['photo'])): ?>
                      <img src = "uploads/<?= htmlspecialchars($row['photo'])?>" width="60" class="student-pics"/>
                      <?php endif; ?>
                  </td>
              <!-- students name -->
              <td>
                <div class="student-name">
                  <?= htmlspecialchars($row['last_name'].', '. $row['first_name'].' '. $row['middle_name']) ?>
                </div>
              </td>
              <!-- LRN -->
              <td><?= htmlspecialchars($row['lrn']) ?></td>
              <!-- Grade & Section -->
              <td><?= htmlspecialchars($row['grade_name'] . ' - ' . $row['section_name']) ?></td>
              <!-- School Year -->
              <td><?= htmlspecialchars($row['school_year'] ?? '—') ?></td>
              <!-- City -->
              <td><?= htmlspecialchars($row['city']) ?></td>
                 <!-- contact -->
              <td><?= htmlspecialchars($row['contact_number']) ?></td>
              <!-- Status -->
                <td><span class="badge <?= $badge ?>"><?= $row['student_type'] ?></span></td>

              <!-- Action -->
              <td>
                <a class="btn-view" href="student_profile.php?id=<?= $row['id'] ?>">View</a>
                <a class="btn-edit" href="students.php?edit_id=<?= $row['id'] ?>">Edit</a>
                <a class="btn-delete" href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Archive this student?')">Archive</a>
              </td>
            </tr>

              <?php endwhile; ?>
            </tbody>
          </table>

        <!-- Pagination  -->
<div class="pagination-row" id="pagination-row">
  <span id="pagination-info">
    Showing <?= min($offset + 1, $total_records) ?>–<?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> students
  </span>

  <nav>
    <ul class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="students.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&grade=<?= urlencode($filter_grade) ?>&status=<?= urlencode($filter_status) ?>&sy=<?= urlencode($filter_sy) ?>">
            <?= $i ?>
          </a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

      </div>
    </div>
  </div>
 
<!-- ADD NEW STUDENT BUTON -->
  <div class="student-modal-overlay" id="student-modal">
    <div class="student-modal-box">
      <div class="student-modal-header">
        <h2 id="student-modal-title">Add New Student</h2>
        <button class="student-modal-close" id="modal-close-btn">&times;</button>
      </div>

      <!-- MODAL FORM -->
      <form action="add.php" method="POST" enctype="multipart/form-data">
         
      <div class="student-modal-body">
             <?php // check if erro rmssg is not empty
            if(!empty($error_message)){
              echo "
              <div class='alert alert-warning alert-dismissible fade show' role='alert'>
                <strong>$error_message</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
            }
          ?>

                              <!--success message -->
          <?php
          if(!empty($success_message)){
            echo "
            <div class='alert alert-success alert-dismissible fade show' role='alert'>
              <strong>$success_message</strong>
              <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
          </div>";
          }
          ?>


          <div class="photo-upload-area">
              <label class="form-label">Student Photo</label>
              <input type="file" name="photo" class="form-input" accept="image/*" id="photo-file-input" />
            </div>


          <input type="hidden" name="id" value="<?= $edit_student['id'] ?? '' ?>">

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-input" name="first_name" id="field-firstname"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" required
                   value="<?= htmlspecialchars($rf['first_name']) ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-input" name="middle_name" id="field-middlename"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]*" title="Letters only"
                   value="<?= htmlspecialchars($rf['middle_name']) ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-input" name="last_name" id="field-lastname"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" required
                   value="<?= htmlspecialchars($rf['last_name']) ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">LRN *</label>
            <input type="text" class="form-input" name="lrn" id="field-id"
                   pattern="\d{12}" maxlength="12" inputmode="numeric"
                   title="LRN must be exactly 12 digits" required
                   value="<?= htmlspecialchars($rf['lrn']) ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Grade &amp; Section *</label>
            <select class="form-select" name="grade_level_id" id="field-grade">
              <option value="">Select Grade</option>
              <option value="1" <?= $rf['grade_level_id'] == 1 ? 'selected' : '' ?>>Grade 7</option>
              <option value="2" <?= $rf['grade_level_id'] == 2 ? 'selected' : '' ?>>Grade 8</option>
              <option value="3" <?= $rf['grade_level_id'] == 3 ? 'selected' : '' ?>>Grade 9</option>
              <option value="4" <?= $rf['grade_level_id'] == 4 ? 'selected' : '' ?>>Grade 10</option>
            </select>
            <select class="form-select" name="section_id" id="field-sectionn">
              <option value="">Select Section</option>
              <option value="1" <?= $rf['section_id'] == 1 ? 'selected' : '' ?>>Newton</option>
              <option value="2" <?= $rf['section_id'] == 2 ? 'selected' : '' ?>>Einstein</option>
              <option value="3" <?= $rf['section_id'] == 3 ? 'selected' : '' ?>>Curie</option>
              <option value="4" <?= $rf['section_id'] == 4 ? 'selected' : '' ?>>Franklin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" class="form-input" id="field-city" name="city"
                   placeholder="e.g. Quezon City"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only"
                   value="<?= htmlspecialchars($rf['city']) ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Contact</label>
            <input type="tel" class="form-input" id="field-contact" name="contact_number"
                   placeholder="e.g. 09XXXXXXXXX"
                   pattern="(09|\+639)\d{9}" maxlength="13"
                   title="Valid PH number: 09XXXXXXXXX or +639XXXXXXXXX"
                   value="<?= htmlspecialchars($rf['contact_number']) ?>"/>
          </div>
         
          <div class="form-group">
            <label class="form-label">School Year</label>
            <select class="form-select" name="school_year_id">
              <?php foreach ($sy_list as $sy): ?>
                <option value="<?= $sy['id'] ?>" <?= $sy['is_active'] ? 'selected' : '' ?>>
                  SY <?= htmlspecialchars($sy['label']) ?><?= $sy['is_active'] ? ' (Current)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" id="field-status">
              <option value="old" <?= $rf['status'] === 'old' ? 'selected' : '' ?>>Old student</option>
              <option value="new" <?= $rf['status'] === 'new' ? 'selected' : '' ?>>New student</option>
            </select>
          </div>
          
        </div>
      </div>
      
 

      <div class="student-modal-footer">
        <button type="button" class="btn-cancel" id="modal-cancel-btn">Cancel</button>
        <button type="submit" class="btn-save-student" id="modal-save-btn">Save Student</button>
      </div>
    </div>
                </form>
  </div>

  <!-- EDIT STUDENT MODAL -->
   <div class="student-modal-overlay" id="edit-modal">
  <div class="student-modal-box">
    <div class="student-modal-header">
      <h2>Edit Student</h2>
      <a href="students.php" class="student-modal-close">&times;</a>
    </div>

    <form action="edit.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $edit_student['id']    ?? '' ?>">
      <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($edit_student['photo'] ?? '') ?>">

      <div class="student-modal-body">

        <?php if (!empty($error_message) && !empty($_GET['edit_id'])): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><?= htmlspecialchars($error_message) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="photo-upload-area">
          <label class="form-label">Student Photo</label>
          <?php if (!empty($edit_student['photo'])): ?>
            <img src="uploads/<?= htmlspecialchars($edit_student['photo']) ?>" class="student-pics" style="display:block;margin-bottom:8px;"/>
          <?php endif; ?>
          <input type="file" name="photo" class="form-input" accept="image/*"/>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-input" name="first_name"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" required
                   value="<?= htmlspecialchars($edit_student['first_name'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-input" name="middle_name"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]*" title="Letters only"
                   value="<?= htmlspecialchars($edit_student['middle_name'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-input" name="last_name"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only" required
                   value="<?= htmlspecialchars($edit_student['last_name'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">LRN *</label>
            <input type="text" class="form-input" name="lrn"
                   pattern="\d{12}" maxlength="12" inputmode="numeric"
                   title="LRN must be exactly 12 digits" required
                   value="<?= htmlspecialchars($edit_student['lrn'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Grade &amp; Section *</label>
            <select class="form-select" name="grade_level_id">
              <option value="">Select Grade</option>
              <option value="1" <?= ($edit_student['grade_level_id'] ?? '') == 1 ? 'selected' : '' ?>>Grade 7</option>
              <option value="2" <?= ($edit_student['grade_level_id'] ?? '') == 2 ? 'selected' : '' ?>>Grade 8</option>
              <option value="3" <?= ($edit_student['grade_level_id'] ?? '') == 3 ? 'selected' : '' ?>>Grade 9</option>
              <option value="4" <?= ($edit_student['grade_level_id'] ?? '') == 4 ? 'selected' : '' ?>>Grade 10</option>
            </select>
            <select class="form-select" name="section_id">
              <option value="">Select Section</option>
              <option value="1" <?= ($edit_student['section_id'] ?? '') == 1 ? 'selected' : '' ?>>Newton</option>
              <option value="2" <?= ($edit_student['section_id'] ?? '') == 2 ? 'selected' : '' ?>>Einstein</option>
              <option value="3" <?= ($edit_student['section_id'] ?? '') == 3 ? 'selected' : '' ?>>Curie</option>
              <option value="4" <?= ($edit_student['section_id'] ?? '') == 4 ? 'selected' : '' ?>>Franklin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" class="form-input" name="city"
                   placeholder="e.g. Quezon City"
                   pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only"
                   value="<?= htmlspecialchars($edit_student['city'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Contact</label>
            <input type="tel" class="form-input" name="contact_number"
                   placeholder="e.g. 09XXXXXXXXX"
                   pattern="(09|\+639)\d{9}" maxlength="13"
                   title="Valid PH number: 09XXXXXXXXX or +639XXXXXXXXX"
                   value="<?= htmlspecialchars($edit_student['contact_number'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">School Year</label>
            <select class="form-select" name="school_year_id">
              <?php foreach ($sy_list as $sy): ?>
                <option value="<?= $sy['id'] ?>"
                  <?= ($edit_student['school_year_id'] ?? '') == $sy['id'] ? 'selected' : '' ?>>
                  SY <?= htmlspecialchars($sy['label']) ?><?= $sy['is_active'] ? ' (Current)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="old" <?= ($edit_student['student_type'] ?? '') === 'old' ? 'selected' : '' ?>>Old Student</option>
              <option value="new" <?= ($edit_student['student_type'] ?? '') === 'new' ? 'selected' : '' ?>>New Student</option>
            </select>
          </div>
        </div>
      </div>

      <div class="student-modal-footer">
        <a href="students.php" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-save-student">Save Changes</button>
      </div>
    </form>
  </div>
</div>

  <!-- Toast -->
  <div class="toast" id="toast"></div>
  <script  src="../js/nav.js"></script>
  <script  src="../js/students.js"></script>
  <script  src="../js/add.js"></script>



  <!-- Keep modal open only on validation error -->
  <?php if (!empty($error_message) || $open_add): ?>
  <script>
    document.getElementById('student-modal').classList.add('open');
  </script>
  <?php endif; ?>


  <!-- auto open edit modal -->
  <?php if (!empty($_GET['edit_id'])): ?>
<script>
  document.getElementById('edit-modal').classList.add('open');
</script>
<?php endif; ?>
</body>
</html>
