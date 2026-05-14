<?php
// ── AJAX: check if email already has a parent account ──────
if (isset($_GET['check_email'])) {
  require_once './mysql/db.php';
  $email = trim($_GET['check_email']);
  $stmt  = $conn->prepare("SELECT id FROM parent_accounts WHERE email=? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  header('Content-Type: application/json');
  echo json_encode(['exists' => (bool)$row]);
  exit();
}

// Load DB for grade levels and form processing
require_once './mysql/db.php';

// Fetch grade levels grouped for the form
$grade_groups = [
  'Preschool'        => [],
  'Elementary'       => [],
  'Junior High School' => [],
];
$gl_res = $conn->query("SELECT id, name FROM grade_levels ORDER BY id");
while ($gl = $gl_res->fetch_assoc()) {
  $name = $gl['name'];
  if (in_array($name, ['Nursery','Kinder 1','Kinder 2'])) {
    $grade_groups['Preschool'][] = $gl;
  } elseif (in_array($name, ['Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6'])) {
    $grade_groups['Elementary'][] = $gl;
  } else {
    $grade_groups['Junior High School'][] = $gl;
  }
}

function gradeOptions(array $groups): string {
  $html = '<option value="">Select Grade</option>';
  foreach ($groups as $label => $grades) {
    if (empty($grades)) continue;
    $html .= '<optgroup label="' . htmlspecialchars($label) . '">';
    foreach ($grades as $g) {
      $html .= '<option value="' . $g['id'] . '">' . htmlspecialchars($g['name']) . '</option>';
    }
    $html .= '</optgroup>';
  }
  return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>COJ Catholic Progressive School</title>
  <link rel="icon" type="image/x-icon" href="./images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;1,700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/home.css">
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
  <div class="nav-container">
    <div class="nav-brand">
      <img src="./images/COJ.png" alt="COJ Logo"/>
      <div>
        <div class="nav-school">COJ Catholic Progressive School</div>
        <div class="nav-tagline">Forming Christian Servant Leaders</div>
      </div>
    </div>
    <div class="nav-links">
      <a href="#home">Home</a>
      <a href="#about">About</a>
      <a href="#enroll">Enrollment</a>
      <a href="portal/login.php" class="nav-btn">Parent Portal</a>
    </div>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero" id="home">
  <!-- Slideshow backgrounds -->
  <div class="hero-slides">
    <div class="hero-slide active" style="background-image:url('./images/school1.jpg')"></div>
    <div class="hero-slide" style="background-image:url('./images/school2.jpg')"></div>
    <div class="hero-slide" style="background-image:url('./images/school3.jpg')"></div>
    <div class="hero-slide" style="background-image:url('./images/school4.jpg')"></div>
    <div class="hero-slide" style="background-image:url('./images/school5.jpg')"></div>
  </div>
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <div class="hero-badge">SY 2026&#8211;2027 Enrollment Now Open</div>
    <h1>Welcome to<br><span>COJ Catholic<br>Progressive School</span></h1>
    <p>Developing Christian Servant Leaders through character formation and academic excellence.</p>
    <div class="hero-actions">
      <a href="#enroll" class="btn-hero-primary">Enroll Now</a>
      <a href="#about" class="btn-hero-secondary">Learn More</a>
    </div>
  </div>
</section>

<!-- ── ABOUT ── -->
<section class="section" id="about">
  <div class="container">
    <div class="section-label">About Us</div>
    <h2 class="section-title">A School Built on Faith and Excellence</h2>
    <div class="about-grid">
      <div class="about-card">
        <div class="about-icon"><i class="bi bi-book-fill"></i></div>
        <h3>Progressive Education</h3>
        <p>We utilize the progressive method of education, nurturing each child&#39;s unique potential through hands-on, student-centered learning.</p>
      </div>
      <div class="about-card">
        <div class="about-icon"><i class="bi bi-heart-fill"></i></div>
        <h3>Christian Formation</h3>
        <p>Rooted in Catholic values, we form children in authentic Christian character &#8212; responsible, compassionate, and patriotic citizens.</p>
      </div>
      <div class="about-card">
        <div class="about-icon"><i class="bi bi-people-fill"></i></div>
        <h3>Community Partnership</h3>
        <p>We work closely with parents and teachers to bring up children in the Christian faith and awareness of the less fortunate.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── MISSION & VISION ── -->
<section class="section" id="mission">
  <div class="container">
    <div class="section-label">Our Purpose</div>
    <h2 class="section-title">Mission and Vision</h2>
    <div class="mv-grid">
      <div class="mv-card">
        <h3>Vision</h3>
        <p>COJ is a Catholic school utilizing the progressive method of education, with a mission of developing <strong>Christian Servant Leaders</strong> through character formation and academic excellence.</p>
      </div>
      <div class="mv-card">
        <h3>Mission</h3>
        <ul>
          <li>To form children in authentic Christian character and academic excellence, training them to be responsible, patriotic citizens.</li>
          <li>To work closely with teachers and parents in bringing up children in the Christian faith, offering opportunities to come to a personal relationship with Christ.</li>
          <li>To help children grow in their awareness of the plight of the poor and the less fortunate.</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ── ONLINE ENROLLMENT ── -->
<section class="section section-alt" id="enroll">
  <div class="container">
    <div class="section-label">Admissions</div>
    <h2 class="section-title">Online Enrollment Form</h2>
    <p class="section-sub">Fill out the form below to begin your enrollment for SY 2026&#8211;2027. Our registrar will review your submission and contact you within 2&#8211;3 school days.</p>

<?php
// ============================================================
//  ENROLLMENT FORM PROCESSING
// ============================================================
$enroll_success = false;
$enroll_error   = '';
$enroll_results = []; // array of ['ref'=>..., 'child_name'=>...]

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_submit'])) {

  // ── Parent fields ──
  $p_first      = trim($_POST['p_first']      ?? '');
  $p_middle     = trim($_POST['p_middle']      ?? '');
  $p_last       = trim($_POST['p_last']        ?? '');
  $p_email      = trim($_POST['p_email']       ?? '');
  $p_mobile     = trim($_POST['p_mobile']      ?? '');
  $p_contact    = trim($_POST['p_contact']     ?? '');
  $p_bday_m     = trim($_POST['p_bday_m']      ?? '');
  $p_bday_d     = trim($_POST['p_bday_d']      ?? '');
  $p_bday_y     = trim($_POST['p_bday_y']      ?? '');
  $p_sex        = trim($_POST['p_sex']         ?? '');
  $p_civil      = trim($_POST['p_civil']       ?? '');
  $p_religion   = trim($_POST['p_religion']    ?? '');
  $p_province   = trim($_POST['p_province_text'] ?? $_POST['p_province'] ?? '');
  $p_city       = trim($_POST['p_city_text']     ?? $_POST['p_city']     ?? '');
  $p_barangay   = trim($_POST['p_barangay_text'] ?? $_POST['p_barangay'] ?? '');
  $p_house      = trim($_POST['p_house']       ?? '');
  $p_street     = trim($_POST['p_street']      ?? '');

  // ── Children ──
  $children = $_POST['children'] ?? [];

  $p_password         = $_POST['p_password']         ?? '';
  $p_password_confirm = $_POST['p_password_confirm'] ?? '';

  // Check if this is a new or existing parent account
  $stmt_pre = $conn->prepare("SELECT id FROM parent_accounts WHERE email=? LIMIT 1");
  $stmt_pre->bind_param("s", $p_email);
  $stmt_pre->execute();
  $pre_existing = $stmt_pre->get_result()->fetch_assoc();

  if (empty($p_first) || empty($p_last) || empty($p_email) || empty($p_mobile)) {
    $enroll_error = "Please fill in all required parent fields (First Name, Last Name, Mobile, Email).";
  } elseif (!filter_var($p_email, FILTER_VALIDATE_EMAIL)) {
    $enroll_error = "Please enter a valid email address.";
  } elseif (!$pre_existing && strlen($p_password) < 8) {
    $enroll_error = "Portal password must be at least 8 characters.";
  } elseif (!$pre_existing && !preg_match('/[0-9]/', $p_password)) {
    $enroll_error = "Portal password must include at least one number.";
  } elseif (!$pre_existing && !preg_match('/[@#!$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $p_password)) {
    $enroll_error = "Portal password must include at least one special character (e.g. @, #, !).";
  } elseif (!$pre_existing && $p_password !== $p_password_confirm) {
    $enroll_error = "Passwords do not match. Please re-enter.";
  } elseif (empty($children)) {
    $enroll_error = "Please add at least one child.";
  } elseif (count($children) > 10) {
    $enroll_error = "Too many children submitted. Please contact the registrar directly.";
  } else {
    $p_name     = trim("$p_first $p_middle $p_last");
    $p_birthday = (!empty($p_bday_y) && !empty($p_bday_m) && !empty($p_bday_d))
                  ? "$p_bday_y-" . str_pad($p_bday_m,2,'0',STR_PAD_LEFT) . "-" . str_pad($p_bday_d,2,'0',STR_PAD_LEFT)
                  : null;
    $p_house_full = trim("$p_house $p_street");

    // Check if parent email already exists
    $existing_parent = $pre_existing;

    if ($existing_parent) {
      $parent_id = $existing_parent['id'];
    } else {
      $hashed = password_hash($p_password, PASSWORD_DEFAULT);
      $stmt_p = $conn->prepare("INSERT INTO parent_accounts
        (name, email, password, contact, province, city_municipality, barangay, house_address, birthday, sex, civil_status, religion, is_active)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)");
      $stmt_p->bind_param("ssssssssssss",
        $p_name, $p_email, $hashed, $p_mobile,
        $p_province, $p_city, $p_barangay, $p_house_full,
        $p_birthday, $p_sex, $p_civil, $p_religion
      );
      $stmt_p->execute();
      $parent_id = $conn->insert_id;
    }

    // Get active school year
    $sy = $conn->query("SELECT id FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
    $school_year_id = $sy ? intval($sy['id']) : 0;
    if (!$school_year_id) {
      $enroll_error = "No active school year is set. Please contact the school registrar.";
    } else {
    $year = date('Y');

    foreach ($children as $idx => $child) {
      $c_first   = trim($child['first_name']   ?? '');
      $c_middle  = trim($child['middle_name']  ?? '');
      $c_last    = trim($child['last_name']    ?? '');
      $c_grade   = intval($child['grade_level_id'] ?? 0);
      $c_type    = in_array($child['student_type'] ?? '', ['new','old']) ? $child['student_type'] : 'new';
      $c_sex     = trim($child['sex']          ?? '');
      $c_birthday_raw = trim($child['birthday'] ?? '');
      $c_religion= trim($child['religion']     ?? '');
      $c_last_school = trim($child['last_school'] ?? '');
      $c_sy_grad = trim($child['school_year_graduated'] ?? '');
      $c_school_addr = trim($child['school_address'] ?? '');

      if (empty($c_first) || empty($c_last) || !$c_grade) continue;

      // Accept either a single date field (YYYY-MM-DD) or legacy split fields
      if (!empty($c_birthday_raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c_birthday_raw)) {
        $c_birthday = $c_birthday_raw;
      } else {
        $c_bday_m = trim($child['bday_m'] ?? '');
        $c_bday_d = trim($child['bday_d'] ?? '');
        $c_bday_y = trim($child['bday_y'] ?? '');
        $c_birthday = (!empty($c_bday_y) && !empty($c_bday_m) && !empty($c_bday_d))
                      ? "$c_bday_y-" . str_pad($c_bday_m,2,'0',STR_PAD_LEFT) . "-" . str_pad($c_bday_d,2,'0',STR_PAD_LEFT)
                      : null;
      }

      // Temp LRN: "P-" + 6-char hex = 8 chars, well within varchar(20), collision-proof
      $temp_lrn = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));

      $stmt_s = $conn->prepare("INSERT INTO students
        (first_name, middle_name, last_name, lrn, grade_level_id, student_type, school_year_id,
         sex, birthday, religion, province, city_municipality, barangay,
         last_school, school_year_graduated, school_address)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $stmt_s->bind_param("ssssisisssssssss",
        $c_first, $c_middle, $c_last, $temp_lrn,
        $c_grade, $c_type, $school_year_id,
        $c_sex, $c_birthday, $c_religion,
        $p_province, $p_city, $p_barangay,
        $c_last_school, $c_sy_grad, $c_school_addr
      );
      $stmt_s->execute();
      $student_id = $conn->insert_id;

      // Notify all admin users of new enrollment application
      if ($student_id) {
        $child_name_esc = $conn->real_escape_string("$c_first $c_last");
        $admins = $conn->query("SELECT id FROM users WHERE is_active=1");
        while ($admin = $admins->fetch_assoc()) {
          $conn->query("INSERT INTO notifications (user_id, type, title, body, link) VALUES ({$admin['id']}, 'info', 'New Enrollment Application: $child_name_esc', 'A parent submitted an online enrollment form. Review in Enrollment page.', 'enrollment.php')");
        }
      }

      // Link student to parent
      $stmt_lnk = $conn->prepare("INSERT IGNORE INTO parent_student_links (parent_id, student_id) VALUES (?,?)");
      $stmt_lnk->bind_param("ii", $parent_id, $student_id);
      $stmt_lnk->execute();

      // Create enrollment record
      if ($school_year_id && $student_id) {
        $count   = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE YEAR(enrolled_at)=$year")->fetch_assoc()['c'] + 1;
        $ref_num = 'ENR-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $conn->query("INSERT INTO enrollments (ref_number, student_id, school_year_id, grade_level_id, status) VALUES ('$ref_num', $student_id, $school_year_id, $c_grade, 'pending')");
        $enroll_results[] = ['ref' => $ref_num, 'child_name' => "$c_first $c_last"];
      }

      // Save uploaded documents into student_requirements
      $upload_dir = __DIR__ . '/pages/uploads/';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
      $allowed_types = ['image/jpeg','image/png','image/webp','application/pdf'];
      $doc_map = [
        'doc_form138'  => 'Form 138 / Report Card',
        'doc_goodmoral' => 'Good Moral Certificate',
      ];
      foreach ($doc_map as $field => $req_name) {
        if (!empty($_FILES[$field]['tmp_name'])) {
          // Server-side MIME check using finfo
          $finfo_doc = new finfo(FILEINFO_MIME_TYPE);
          $real_doc_mime = $finfo_doc->file($_FILES[$field]['tmp_name']);
          $allowed_doc_mime_ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
          if (!isset($allowed_doc_mime_ext[$real_doc_mime])) continue;
          if ($_FILES[$field]['size'] > 5 * 1024 * 1024) continue; // 5MB max
          // Find requirement ID by name
          $req_stmt = $conn->prepare("SELECT id FROM requirements WHERE name = ? LIMIT 1");
          $req_stmt->bind_param("s", $req_name);
          $req_stmt->execute();
          $req_row = $req_stmt->get_result()->fetch_assoc();
          if (!$req_row) continue;

          $req_id  = $req_row['id'];
          $ext     = $allowed_doc_mime_ext[$real_doc_mime];
          $fname   = 'req_' . $student_id . '_' . $req_id . '_' . uniqid() . '.' . $ext;
          if (move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $fname)) {
            $ins = $conn->prepare("INSERT INTO student_requirements
              (student_id, requirement_id, school_year_id, file_path, status, submitted_at)
              VALUES (?,?,?,?,'submitted',NOW())
              ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), status='submitted', submitted_at=NOW()");
            $ins->bind_param("iiis", $student_id, $req_id, $school_year_id, $fname);
            $ins->execute();
          }
        }
      }
    } // end foreach children

    if (!empty($enroll_results)) {
      $enroll_success = true;
      // Send enrollment confirmation email to parent
      require_once './mysql/email_notifications.php';
      $guardian_email = trim($p_email ?? '');
      $guardian_name  = trim(($p_first ?? '') . ' ' . ($p_last ?? ''));
      $plain_pass_for_email = empty($existing_parent) ? $p_password : null;
      if ($guardian_email) {
        foreach ($enroll_results as $er) {
          notifyEnrollmentReceived($guardian_email, $guardian_name, $er['child_name'], $er['ref'], $plain_pass_for_email);
        }
      }
    } else {
      $enroll_error = "No valid children were submitted. Please check the form.";
    }
    } // end else (school year exists)
  }
}
?>

<?php if ($enroll_success): ?>
<!-- ── SUCCESS SCREEN ── -->
<div class="enroll-success" style="max-width:640px;margin:0 auto;text-align:center;">
  <i class="bi bi-check-circle-fill"></i>
  <h3>Enrollment Submitted!</h3>
  <p>Thank you for applying. Here are your enrollment details:</p>

  <div style="margin:20px 0;text-align:left;">
    <?php foreach ($enroll_results as $r): ?>
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px 18px;margin-bottom:10px;">
      <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Reference Number</div>
      <div style="font-size:22px;font-weight:800;color:#494C8A;letter-spacing:.05em;"><?= htmlspecialchars($r['ref']) ?></div>
      <div style="font-size:13px;color:#374151;margin-top:4px;">Child: <strong><?= htmlspecialchars($r['child_name']) ?></strong></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="background:#eef0f8;border-radius:8px;padding:16px 18px;text-align:left;margin-bottom:20px;">
    <div style="font-size:12px;font-weight:700;color:#494C8A;margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em;">Parent Portal Access</div>
    <div style="font-size:13px;color:#374151;line-height:1.8;">
      <strong>Portal URL:</strong> <a href="portal/login.php" style="color:#494C8A;">portal/login.php</a><br>
      <strong>Email:</strong> <?= htmlspecialchars($p_email ?? '') ?><br>
      <?php if (empty($existing_parent)): ?>
      <strong>Password:</strong> <span style="color:#374151;">The password you created in this form.</span><br>
      <span style="color:#16a34a;font-size:12px;"><i class="bi bi-check-circle-fill"></i> You can now log in with your email and that password.</span>
      <?php else: ?>
      <span style="color:#6b7280;font-size:12px;">
        <i class="bi bi-info-circle-fill"></i>
        Your existing account was used. Log in with your previous password.
        Forgot it? Use the <a href="portal/login.php?mode=reset" style="color:#494C8A;font-weight:600;">Forgot Password</a> link.
      </span>
      <?php endif; ?>
    </div>
  </div>

  <a href="portal/login.php" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#494C8A;color:#fff;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;">
    <i class="bi bi-box-arrow-in-right"></i> Login to Parent Portal
  </a>
  <p style="margin-top:16px;font-size:13px;">Please <strong>save your reference number(s)</strong>. Our registrar will review your application and contact you within 2&#8211;3 school days.</p>
</div>

<?php else: ?>

<?php if ($enroll_error): ?>
<div class="enroll-error"><?= htmlspecialchars($enroll_error) ?></div>
<?php endif; ?>

<form class="enroll-form" method="POST" action="home.php#enroll" id="enrollForm" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="enroll_submit" value="1">

  <!-- ── Privacy Consent ── -->
  <div class="ef-section">
    <div class="ef-section-header">Privacy Consent</div>
    <div class="ef-section-body">
      <p style="font-size:13px;color:#374151;line-height:1.8;margin-bottom:20px;">
        I understand and agree that by filling out this form, I am allowing COJ Catholic Progressive School to use, collect, and disclose my child's personal information for Enrollment Application and to store it as long as necessary for the fulfillment of the stated purpose in accordance with the applicable laws, including the Data Privacy Act of 2012 and its Implementing Rules and Regulations.
      </p>
      <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;">
        <input type="checkbox" name="privacy_consent" id="privacy_consent" required style="width:16px;height:16px;margin-top:2px;accent-color:#494C8A;flex-shrink:0;"/>
        I have read and agree to the Privacy Consent above. *
      </label>
    </div>
  </div>

  <div class="ef-notice">
    <i class="bi bi-info-circle-fill"></i>
    Please accomplish the form properly by giving complete and correct information (do not abbreviate). Please put N/A if not applicable
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION A — Student's Basic Information
  ══════════════════════════════════════════════════════ -->
  <div class="ef-section">
    <div class="ef-section-header">Student's Basic Information</div>
    <div class="ef-section-body">

      <!-- Student type toggle -->
      <div class="ef-row" style="margin-bottom:18px;">
        <label class="ef-radio-label">
          <input type="radio" name="children[0][student_type]" value="new" id="stype-new" checked onchange="toggleEduSection(this.value)" style="accent-color:#494C8A;"/>
          New Student
        </label>
        <label class="ef-radio-label" style="margin-left:32px;">
          <input type="radio" name="children[0][student_type]" value="old" id="stype-old" onchange="toggleEduSection(this.value)" style="accent-color:#494C8A;"/>
          Returning Student
        </label>
      </div>

      <div class="ef-grid ef-3col">
        <div class="ef-field">
          <label>First Name *</label>
          <input type="text" name="children[0][first_name]" class="ef-input" required/>
        </div>
        <div class="ef-field">
          <label>Middle Name</label>
          <input type="text" name="children[0][middle_name]" class="ef-input" placeholder="N/A if none"/>
        </div>
        <div class="ef-field">
          <label>Last Name *</label>
          <input type="text" name="children[0][last_name]" class="ef-input" required/>
        </div>
      </div>

      <div class="ef-grid ef-3col" style="margin-top:14px;">
        <div class="ef-field">
          <label>Incoming Grade Level *</label>
          <select name="children[0][grade_level_id]" class="ef-input" required>
            <?= gradeOptions($grade_groups) ?>
          </select>
        </div>
        <div class="ef-field">
          <label>Sex *</label>
          <select name="children[0][sex]" class="ef-input" required>
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
        <div class="ef-field">
          <label>Religion</label>
          <input type="text" name="children[0][religion]" class="ef-input"/>
        </div>
      </div>

      <div class="ef-grid ef-2col" style="margin-top:14px;">
        <div class="ef-field">
          <label>Date of Birth *</label>
          <input type="date" name="children[0][birthday]" class="ef-input" required/>
        </div>
        <div class="ef-field">
          <label>Place of Birth</label>
          <input type="text" name="children[0][birth_place]" class="ef-input"/>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION B — Education (New students only)
  ══════════════════════════════════════════════════════ -->
  <div class="ef-section" id="edu-section">
    <div class="ef-section-header">Education</div>
    <div class="ef-section-body">
      <div class="ef-grid ef-2col">
        <div class="ef-field" style="grid-column:1/-1;">
          <label>School Last Attended *</label>
          <input type="text" name="children[0][last_school]" class="ef-input"/>
        </div>
        <div class="ef-field" style="grid-column:1/-1;">
          <label>School Address</label>
          <input type="text" name="children[0][school_address]" class="ef-input"/>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION C — Parent / Guardian Information
  ══════════════════════════════════════════════════════ -->
  <div class="ef-section">
    <div class="ef-section-header">Parent / Guardian Information</div>
    <div class="ef-section-body">

      <div class="ef-grid ef-3col">
        <div class="ef-field">
          <label>Guardian First Name *</label>
          <input type="text" name="p_first" class="ef-input" required/>
        </div>
        <div class="ef-field">
          <label>Middle Name</label>
          <input type="text" name="p_middle" class="ef-input"/>
        </div>
        <div class="ef-field">
          <label>Last Name *</label>
          <input type="text" name="p_last" class="ef-input" required/>
        </div>
      </div>

      <div class="ef-grid ef-3col" style="margin-top:14px;">
        <div class="ef-field">
          <label>Mobile Number * <span style="font-size:11px;color:#6b7280;">(09XXXXXXXXX)</span></label>
          <input type="tel" name="p_mobile" class="ef-input" required/>
        </div>
        <div class="ef-field">
          <label>Email Address *</label>
          <input type="email" name="p_email" class="ef-input" required/>
        </div>
        <div class="ef-field">
          <label>Alternate Contact No.</label>
          <input type="tel" name="p_contact" class="ef-input"/>
        </div>
      </div>

      <!-- Portal password — only shown for new accounts -->
      <div id="portal-password-row" style="margin-top:22px;">
        <div style="background:#eef0f8;border-radius:8px;padding:14px 16px;margin-bottom:18px;font-size:13px;color:#374151;">
          <strong>Create your Parent Portal password.</strong>
        </div>
        <div class="ef-grid ef-2col" style="gap:20px;">
          <div class="ef-field">
            <label>Portal Password * <span style="font-size:11px;color:#d97706;">(min. 8 characters)</span></label>
            <div style="position:relative;">
              <input type="password" name="p_password" id="p_password" class="ef-input" required minlength="8" style="padding-right:42px;"/>
              <button type="button" onclick="togglePwVis('p_password','eye-pw')" tabindex="-1"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;padding:0;display:flex;align-items:center;">
                <i class="bi bi-eye" id="eye-pw" style="font-size:16px;"></i>
              </button>
            </div>
            <div style="font-size:11.5px;color:#6b7280;margin-top:5px;line-height:1.5;">
              Password must be at least 8 characters and include at least one number and one special character (e.g. @, #, !).
            </div>
          </div>
          <div class="ef-field">
            <label>Confirm Password *</label>
            <div style="position:relative;">
              <input type="password" name="p_password_confirm" id="p_password_confirm" class="ef-input" required minlength="8" style="padding-right:42px;"
                oninput="
                  const pw = document.getElementById('p_password').value;
                  const hint = document.getElementById('pw-match-hint');
                  if (this.value && pw && this.value !== pw) {
                    hint.textContent = 'Passwords do not match.';
                    hint.style.color = '#b91c1c';
                  } else if (this.value && pw && this.value === pw) {
                    hint.textContent = '✓ Passwords match.';
                    hint.style.color = '#16a34a';
                  } else {
                    hint.textContent = '';
                  }
                "/>
              <button type="button" onclick="togglePwVis('p_password_confirm','eye-pw-confirm')" tabindex="-1"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;padding:0;display:flex;align-items:center;">
                <i class="bi bi-eye" id="eye-pw-confirm" style="font-size:16px;"></i>
              </button>
            </div>
            <div id="pw-match-hint" style="font-size:12px;font-weight:500;margin-top:5px;min-height:16px;"></div>
          </div>
        </div>
      </div>

      <div class="ef-grid ef-3col" style="margin-top:22px;">
        <div class="ef-field">
          <label>Sex</label>
          <select name="p_sex" class="ef-input">
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>
        <div class="ef-field">
          <label>Civil Status</label>
          <select name="p_civil" class="ef-input">
            <option value="">Select</option>
            <option value="Single">Single</option>
            <option value="Married">Married</option>
            <option value="Widowed">Widowed</option>
            <option value="Separated">Separated</option>
          </select>
        </div>
        <div class="ef-field">
          <label>Religion</label>
          <input type="text" name="p_religion" class="ef-input"/>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION D — Address
  ══════════════════════════════════════════════════════ -->
  <div class="ef-section">
    <div class="ef-section-header">Address</div>
    <div class="ef-section-body">
      <div class="ef-grid ef-2col">
        <div class="ef-field">
          <label>Region *</label>
          <select name="p_region" id="enroll-region" class="ef-input">
            <option value="" disabled selected>Select Region</option>
          </select>
          <input type="hidden" name="p_region_text" id="enroll-region-text">
        </div>
        <div class="ef-field">
          <label>Province *</label>
          <select name="p_province" id="enroll-province" class="ef-input">
            <option value="" disabled selected>Select Province</option>
          </select>
          <input type="hidden" name="p_province_text" id="enroll-province-text">
        </div>
        <div class="ef-field">
          <label>City / Municipality *</label>
          <select name="p_city" id="enroll-city" class="ef-input">
            <option value="" disabled selected>Select City / Municipality</option>
          </select>
          <input type="hidden" name="p_city_text" id="enroll-city-text">
        </div>
        <div class="ef-field">
          <label>Barangay *</label>
          <select name="p_barangay" id="enroll-barangay" class="ef-input">
            <option value="" disabled selected>Select Barangay</option>
          </select>
          <input type="hidden" name="p_barangay_text" id="enroll-barangay-text">
        </div>
        <div class="ef-field">
          <label>House #, Block, Lot, Unit, Building *</label>
          <input type="text" name="p_house" class="ef-input" required/>
        </div>
        <div class="ef-field">
          <label>Street, Village / Subdivision *</label>
          <input type="text" name="p_street" class="ef-input" required/>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION E — Additional Children (optional)
  ══════════════════════════════════════════════════════ -->
  <div class="ef-section" id="extra-children-section" style="display:none;">
    <div class="ef-section-header">Additional Children</div>
    <div class="ef-section-body" id="extra-children-container"></div>
  </div>

  <div style="margin-bottom:24px;">
    <button type="button" onclick="addExtraChild()" class="ef-add-child-btn">
      <i class="bi bi-plus-circle"></i> Enroll Another Child
    </button>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION F — Attachments
  ══════════════════════════════════════════════════════ -->
  <div class="ef-section" id="attachments-section">
    <div class="ef-section-header">Attachments</div>
    <div class="ef-section-body">
      <div style="background:#fef9c3;border-left:4px solid #d97706;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400e;line-height:1.7;">
        <i class="bi bi-info-circle-fill" style="color:#d97706;margin-right:6px;"></i>
        <strong>Accepted formats: JPG, PNG, PDF · Max file size: 10MB per file.</strong> Attachments are optional but recommended for faster processing.
      </div>
      <div id="new-student-docs" style="background:#eef0f8;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#374151;">
        For new students, please attach a clear copy of your <strong>Form 138 / Report Card</strong> and <strong>Certificate of Good Moral Character</strong>.
      </div>
      <div id="old-student-docs" style="background:#eef0f8;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#374151;display:none;">
        For returning students, please attach your most recent <strong>Report Card (Form 138)</strong>.
      </div>
      <div class="ef-grid ef-2col">
        <div class="ef-field">
          <label>Form 138 / Report Card</label>
          <input type="file" name="doc_form138" class="ef-input" accept="image/*,.pdf" style="padding:6px;"/>
        </div>
        <div class="ef-field" id="doc-goodmoral-wrap">
          <label>Certificate of Good Moral Character</label>
          <input type="file" name="doc_goodmoral" class="ef-input" accept="image/*,.pdf" style="padding:6px;"/>
        </div>
      </div>
    </div>
  </div>

  <!-- Submit -->
  <div style="text-align:center;margin-top:8px;margin-bottom:40px;">
    <button type="submit" class="btn-enroll-submit">
      <i class="bi bi-send-fill"></i> Submit Enrollment Application
    </button>
    <div style="font-size:12px;color:#6b7280;margin-top:30px;">
      By submitting, you confirm all information provided is accurate and complete.
    </div>
  </div>

</form>
<?php endif; ?>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-top">
      <div class="footer-brand-col">
        <div class="footer-brand">
          <img src="./images/COJ.png" alt="COJ Logo"/>
          <div>
            <div class="footer-school">COJ Catholic Progressive School</div>
            <div class="footer-tagline">Forming Christian Servant Leaders</div>
          </div>
        </div>
        <p class="footer-desc">A Catholic school utilizing the progressive method of education, developing Christian Servant Leaders through character formation and academic excellence.</p>
      </div>
      <div class="footer-links-col">
        <div class="footer-link-group">
          <div class="footer-link-title">Quick Links</div>
          <a href="#home">Home</a>
          <a href="#about">About</a>
          <a href="#mission">Mission and Vision</a>
          <a href="#enroll">Enrollment</a>
        </div>
        <div class="footer-link-group">
          <div class="footer-link-title">Portals</div>
          <a href="portal/login.php">Parent Portal</a>
        </div>
        <div class="footer-link-group">
          <div class="footer-link-title">Contact</div>
          <a href="https://cradleofjoy.edu.ph" target="_blank">cradleofjoy.edu.ph</a>
          <a href="https://www.facebook.com/COJCatholicProgressiveSchool" target="_blank">Facebook Page</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> COJ Catholic Progressive School. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
// ── Smooth scroll ──────────────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
  });
});

// Grade options HTML for extra child blocks (built from DB)
const GRADE_OPTIONS_HTML = <?= json_encode('<option value="">Select Grade</option>' . implode('', array_map(function($label, $grades) {
  if (empty($grades)) return '';
  $html = '<optgroup label="' . htmlspecialchars($label) . '">';
  foreach ($grades as $g) {
    $html .= '<option value="' . $g['id'] . '">' . htmlspecialchars($g['name']) . '</option>';
  }
  $html .= '</optgroup>';
  return $html;
}, array_keys($grade_groups), array_values($grade_groups)))) ?>;

// ── Hide password row if email already has an account ──────
(function() {
  const emailField = document.querySelector('input[name="p_email"]');
  const passRow    = document.getElementById('portal-password-row');
  const passInput  = document.getElementById('p_password');
  const confInput  = document.getElementById('p_password_confirm');
  if (!emailField || !passRow) return;

  let debounceTimer = null;

  function checkEmail() {
    const email = emailField.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      // Not a valid email yet — show password row
      passRow.style.display = 'block';
      if (passInput) passInput.setAttribute('required', '');
      if (confInput) confInput.setAttribute('required', '');
      return;
    }

    fetch('home.php?check_email=' + encodeURIComponent(email))
      .then(r => r.json())
      .then(data => {
        if (data.exists) {
          passRow.style.display = 'none';
          if (passInput) passInput.removeAttribute('required');
          if (confInput) confInput.removeAttribute('required');
          // Show a clear message so the parent knows why
          let msg = document.getElementById('existing-account-msg');
          if (!msg) {
            msg = document.createElement('div');
            msg.id = 'existing-account-msg';
            msg.style.cssText = 'background:#eef0f8;border-radius:8px;padding:12px 16px;font-size:13px;color:#374151;margin-top:8px;';
            passRow.parentNode.insertBefore(msg, passRow.nextSibling);
          }
          msg.innerHTML = '<i class="bi bi-info-circle-fill" style="color:#494C8A;"></i> <strong>This email already has a portal account.</strong> Log in with your existing password. <a href="portal/login.php" style="color:#494C8A;font-weight:600;">Go to login →</a>';
          msg.style.display = 'block';
        } else {
          passRow.style.display = 'block';
          if (passInput) passInput.setAttribute('required', '');
          if (confInput) confInput.setAttribute('required', '');
          const msg = document.getElementById('existing-account-msg');
          if (msg) msg.style.display = 'none';
        }
      })
      .catch(() => {}); // silently fail — server will validate
  }

  // Debounce: wait 600ms after user stops typing before checking
  emailField.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(checkEmail, 600);
  });
  emailField.addEventListener('blur', checkEmail);
})();

// ── Single-page enrollment form JS ────────────────────────

// Show/hide Education section and attachment notes based on student type
function toggleEduSection(val) {
  const edu       = document.getElementById('edu-section');
  const newDocs   = document.getElementById('new-student-docs');
  const oldDocs   = document.getElementById('old-student-docs');
  const goodMoral = document.getElementById('doc-goodmoral-wrap');

  if (val === 'old') {
    if (edu)       edu.style.display      = 'none';
    if (newDocs)   newDocs.style.display  = 'none';
    if (oldDocs)   oldDocs.style.display  = 'block';
    if (goodMoral) goodMoral.style.display = 'none';
  } else {
    if (edu)       edu.style.display      = 'block';
    if (newDocs)   newDocs.style.display  = 'block';
    if (oldDocs)   oldDocs.style.display  = 'none';
    if (goodMoral) goodMoral.style.display = 'block';
  }
}

// Add an extra child block
function addExtraChild() {
  const container = document.getElementById('extra-children-container');
  const section   = document.getElementById('extra-children-section');
  if (section) section.style.display = 'block';

  const existing = container.querySelectorAll('.ef-child-block').length;
  const idx = existing + 1; // children[1], children[2], ...
  const num = existing + 2; // display number (child 1 is the main form)

  const block = document.createElement('div');
  block.className = 'ef-child-block';
  block.style.cssText = 'border:1.5px solid #e0e4f0;border-radius:10px;padding:20px;margin-bottom:16px;background:#fafbff;';
  block.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div style="font-size:13px;font-weight:700;color:#494C8A;">Child #${num}</div>
      <button type="button" onclick="this.closest('.ef-child-block').remove(); if(!document.querySelector('.ef-child-block')) document.getElementById('extra-children-section').style.display='none';"
        style="background:#dc2626;border:none;color:#fff;border-radius:6px;padding:6px 14px;font-size:13px;font-weight:700;cursor:pointer;">Remove</button>
    </div>
    <div class="ef-grid ef-3col">
      <div class="ef-field"><label>First Name *</label><input type="text" name="children[${idx}][first_name]" class="ef-input" required placeholder="Juan"/></div>
      <div class="ef-field"><label>Middle Name</label><input type="text" name="children[${idx}][middle_name]" class="ef-input" placeholder="Santos"/></div>
      <div class="ef-field"><label>Last Name *</label><input type="text" name="children[${idx}][last_name]" class="ef-input" required placeholder="Dela Cruz"/></div>
    </div>
    <div class="ef-grid ef-3col" style="margin-top:12px;">
      <div class="ef-field">
        <label>Grade Level *</label>
        <select name="children[${idx}][grade_level_id]" class="ef-input" required>
          ${GRADE_OPTIONS_HTML}
        </select>
      </div>
      <div class="ef-field">
        <label>Sex</label>
        <select name="children[${idx}][sex]" class="ef-input">
          <option value="">Select</option>
          <option value="male">Male</option><option value="female">Female</option>
        </select>
      </div>
      <div class="ef-field">
        <label>Student Type</label>
        <select name="children[${idx}][student_type]" class="ef-input">
          <option value="new">New Student</option>
          <option value="old">Returning Student</option>
        </select>
      </div>
    </div>
  `;
  container.appendChild(block);
}

// Show/hide password toggle helper
function togglePwVis(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('bi-eye', 'bi-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('bi-eye-slash', 'bi-eye');
  }
}

// Form validation on submit
document.getElementById('enrollForm').addEventListener('submit', function(e) {
  // Clear previous errors
  this.querySelectorAll('.ef-error').forEach(el => el.remove());
  this.querySelectorAll('.ef-input-error').forEach(el => {
    el.classList.remove('ef-input-error');
    el.style.borderColor = '';
  });

  let valid = true;
  let firstError = null;

  function markErr(field, msg) {
    field.classList.add('ef-input-error');
    field.style.borderColor = '#f87171';
    field.style.boxShadow = '0 0 0 3px rgba(248,113,113,.15)';
    const err = document.createElement('div');
    err.className = 'ef-error';
    err.style.cssText = 'color:#b91c1c;font-size:12px;font-weight:500;margin-top:5px;';
    err.textContent = msg;
    field.parentElement.appendChild(err);
    field.addEventListener('input', function fix() {
      field.classList.remove('ef-input-error');
      field.style.borderColor = '';
      field.style.boxShadow = '';
      const e = field.parentElement.querySelector('.ef-error');
      if (e) e.remove();
      field.removeEventListener('input', fix);
    }, { once: true });
    field.addEventListener('change', function fix() {
      field.classList.remove('ef-input-error');
      field.style.borderColor = '';
      field.style.boxShadow = '';
      const e = field.parentElement.querySelector('.ef-error');
      if (e) e.remove();
      field.removeEventListener('change', fix);
    }, { once: true });
    if (!firstError) firstError = field;
    valid = false;
  }

  // Privacy consent
  const consent = document.getElementById('privacy_consent');
  if (consent && !consent.checked) {
    markErr(consent, 'You must agree to the Privacy Consent to proceed.');
  }

  // Required fields
  this.querySelectorAll('[required]').forEach(function(field) {
    if (field.type === 'hidden' || !field.offsetParent) return;
    if (!field.value.trim()) {
      markErr(field, 'This field is required.');
    } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
      markErr(field, 'Enter a valid email address.');
    } else if (field.name === 'p_mobile' && !/^(09|\+639)\d{9}$/.test(field.value.replace(/\s/g,''))) {
      markErr(field, 'Enter a valid PH mobile number (09XXXXXXXXX).');
    }
  });

  // Address: check hidden text inputs
  [
    ['enroll-region-text',   'enroll-region',   'Region'],
    ['enroll-province-text', 'enroll-province', 'Province'],
    ['enroll-city-text',     'enroll-city',     'City / Municipality'],
    ['enroll-barangay-text', 'enroll-barangay', 'Barangay'],
  ].forEach(function([hidId, selId, label]) {
    const hid = document.getElementById(hidId);
    const sel = document.getElementById(selId);
    if (hid && sel && !hid.value.trim()) {
      markErr(sel, label + ' is required.');
    }
  });

  // Password validation — client-side so the form never reloads on mismatch
  const pwField   = document.getElementById('p_password');
  const confField = document.getElementById('p_password_confirm');
  const pwRow     = document.getElementById('portal-password-row');

  if (pwRow && pwRow.style.display !== 'none' && pwField && confField) {
    if (pwField.value.length > 0 && pwField.value.length < 8) {
      markErr(pwField, 'Password must be at least 8 characters.');
    } else if (pwField.value && !/[0-9]/.test(pwField.value)) {
      markErr(pwField, 'Password must include at least one number.');
    } else if (pwField.value && !/[@#!$%^&*()\-_=+\[\]{};:'"\\|,.<>/?]/.test(pwField.value)) {
      markErr(pwField, 'Password must include at least one special character (e.g. @, #, !).');
    } else if (pwField.value && confField.value && pwField.value !== confField.value) {
      markErr(confField, 'Passwords do not match.');
    }
  }

  if (!valid) {
    e.preventDefault();
    if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});

</script>
<!-- jQuery (required by ph-address-selector) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<!-- Philippine Address Selector -->
<script src="./js/ph-address-selector.js"></script>

<script>
// ── Hero Slideshow ─────────────────────────────────────────
(function () {
  const slides = document.querySelectorAll('.hero-slide');
  if (!slides.length) return;
  let current = 0;

  setInterval(function () {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
  }, 3000); // 3 seconds per slide (1.2s fade + 1.8s display)
})();
</script>
</body>
</html>
