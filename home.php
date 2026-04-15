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
      <a href="#mission">Mission & Vision</a>
      <a href="#enroll">Enrollment</a>
      <a href="index.php" class="nav-btn">Admin Login</a>
      <a href="portal/login.php" class="nav-btn" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);">Parent Portal</a>
    </div>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero" id="home">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <div class="hero-badge">SY 2026–2027 Enrollment Now Open</div>
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
        <p>We utilize the progressive method of education, nurturing each child's unique potential through hands-on, student-centered learning.</p>
      </div>
      <div class="about-card">
        <div class="about-icon"><i class="bi bi-heart-fill"></i></div>
        <h3>Christian Formation</h3>
        <p>Rooted in Catholic values, we form children in authentic Christian character — responsible, compassionate, and patriotic citizens.</p>
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
<section class="section section-alt" id="mission">
  <div class="container">
    <div class="section-label">Our Purpose</div>
    <h2 class="section-title">Mission & Vision</h2>
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
<section class="section" id="enroll">
  <div class="container">
    <div class="section-label">Admissions</div>
    <h2 class="section-title">Online Enrollment Form</h2>
    <p class="section-sub">Fill out the form below to begin your enrollment for SY 2026–2027. Our registrar will review your submission and contact you within 2–3 school days.</p>

    <?php
    // Handle enrollment form submission
    $enroll_success = false;
    $enroll_error   = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_submit'])) {
      require_once './mysql/db.php';

      $first_name     = trim($_POST['first_name']     ?? '');
      $middle_name    = trim($_POST['middle_name']     ?? '');
      $last_name      = trim($_POST['last_name']       ?? '');
      $grade_level_id = intval($_POST['grade_level_id'] ?? 0);
      $city           = trim($_POST['city']            ?? '');
      $contact_number = trim($_POST['contact_number']  ?? '');
      $guardian_name  = trim($_POST['guardian_name']   ?? '');
      $guardian_contact = trim($_POST['guardian_contact'] ?? '');
      $student_type   = trim($_POST['student_type']    ?? 'new');

      if (empty($first_name) || empty($last_name) || empty($grade_level_id)) {
        $enroll_error = "Please fill in all required fields.";
      } else {
          // Get active school year
          $sy = $conn->query("SELECT id FROM school_years WHERE is_active=1 LIMIT 1")->fetch_assoc();
          $school_year_id = $sy['id'] ?? null;

          // Placeholder LRN — will be assigned by registrar
          $temp_lrn = 'PENDING-' . time() . rand(100,999);

          $stmt = $conn->prepare("INSERT INTO students (first_name, middle_name, last_name, lrn, grade_level_id, city, contact_number, student_type, school_year_id) VALUES (?,?,?,?,?,?,?,?,?)");
          $stmt->bind_param("ssssisssi", $first_name, $middle_name, $last_name, $temp_lrn, $grade_level_id, $city, $contact_number, $student_type, $school_year_id);

          if ($stmt->execute()) {
            $student_id = $conn->insert_id;
            // Generate reference number and create pending enrollment
            if ($school_year_id) {
              $year    = date('Y');
              $count   = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE YEAR(enrolled_at) = $year")->fetch_assoc()['c'] + 1;
              $ref_num = 'ENR-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
              $conn->query("INSERT INTO enrollments (ref_number, student_id, school_year_id, grade_level_id, status) VALUES ('$ref_num', $student_id, $school_year_id, $grade_level_id, 'pending')");
              $enroll_ref = $ref_num;
            }
            $enroll_success = true;
          } else {
            $enroll_error = "Something went wrong. Please try again.";
          }
      }
    }
    ?>

    <?php if ($enroll_success): ?>
    <div class="enroll-success">
      <i class="bi bi-check-circle-fill"></i>
      <h3>Enrollment Submitted!</h3>
      <p>Thank you for applying. Your reference number is:</p>
      <div style="font-size:22px;font-weight:800;color:var(--primary);letter-spacing:.05em;margin:12px 0;"><?= htmlspecialchars($enroll_ref ?? '') ?></div>
      <p>Please <strong>save this reference number</strong>. Our registrar will review your application and contact you within 2–3 school days.</p>
    </div>
    <?php else: ?>

    <?php if ($enroll_error): ?>
    <div class="enroll-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($enroll_error) ?></div>
    <?php endif; ?>

    <form class="enroll-form" method="POST" action="home.php#enroll">
      <input type="hidden" name="enroll_submit" value="1">

      <div class="enroll-section-title">Student Information</div>
      <div class="enroll-grid">
        <div class="enroll-field">
          <label>First Name *</label>
          <input type="text" name="first_name" required placeholder="Juan"
                 pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only"
                 value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"/>
        </div>
        <div class="enroll-field">
          <label>Middle Name</label>
          <input type="text" name="middle_name" placeholder="Santos"
                 value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"/>
        </div>
        <div class="enroll-field">
          <label>Last Name *</label>
          <input type="text" name="last_name" required placeholder="Dela Cruz"
                 pattern="[a-zA-ZÀ-ÿ\s\-\.]+" title="Letters only"
                 value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"/>
        </div>
        <div class="enroll-field">
          <label>Grade Level Applying For *</label>
          <select name="grade_level_id" required>
            <option value="">Select Grade</option>
            <option value="1" <?= ($_POST['grade_level_id'] ?? '') == 1 ? 'selected':'' ?>>Grade 7</option>
            <option value="2" <?= ($_POST['grade_level_id'] ?? '') == 2 ? 'selected':'' ?>>Grade 8</option>
            <option value="3" <?= ($_POST['grade_level_id'] ?? '') == 3 ? 'selected':'' ?>>Grade 9</option>
            <option value="4" <?= ($_POST['grade_level_id'] ?? '') == 4 ? 'selected':'' ?>>Grade 10</option>
          </select>
        </div>
        <div class="enroll-field">
          <label>Student Type *</label>
          <select name="student_type">
            <option value="new" <?= ($_POST['student_type'] ?? 'new') === 'new' ? 'selected':'' ?>>New Student</option>
            <option value="old" <?= ($_POST['student_type'] ?? '') === 'old' ? 'selected':'' ?>>Returning Student</option>
          </select>
        </div>
        <div class="enroll-field">
          <label>City / Municipality</label>
          <input type="text" name="city" placeholder="e.g. Quezon City"
                 value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"/>
        </div>
        <div class="enroll-field">
          <label>Student Contact Number</label>
          <input type="tel" name="contact_number" placeholder="09XXXXXXXXX"
                 pattern="(09|\+639)\d{9}" maxlength="13"
                 value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"/>
        </div>
      </div>

      <div class="enroll-section-title" style="margin-top:24px;">Guardian Information</div>
      <div class="enroll-grid">
        <div class="enroll-field">
          <label>Guardian / Parent Name</label>
          <input type="text" name="guardian_name" placeholder="Maria Dela Cruz"
                 value="<?= htmlspecialchars($_POST['guardian_name'] ?? '') ?>"/>
        </div>
        <div class="enroll-field">
          <label>Guardian Contact Number</label>
          <input type="tel" name="guardian_contact" placeholder="09XXXXXXXXX"
                 value="<?= htmlspecialchars($_POST['guardian_contact'] ?? '') ?>"/>
        </div>
      </div>

      <div class="enroll-note">
        <i class="bi bi-info-circle-fill"></i>
        <div>
          <strong>Note:</strong>
          After submitting, please prepare: <strong>PSA Birth Certificate, Good Moral Certificate, Report Card (Form 138), and 2x2 ID photo</strong> for document verification.
        </div>
      </div>

      <button type="submit" class="btn-enroll-submit">
        <i class="bi bi-send-fill"></i> Submit Enrollment Application
      </button>
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
          <a href="#mission">Mission & Vision</a>
          <a href="#enroll">Enrollment</a>
        </div>
        <div class="footer-link-group">
          <div class="footer-link-title">Portals</div>
          <a href="portal/login.php">Parent Portal</a>
          <a href="index.php">Admin Login</a>
        </div>
        <div class="footer-link-group">
          <div class="footer-link-title">Contact</div>
          <a href="https://cradleofjoy.edu.ph" target="_blank">cradleofjoy.edu.ph</a>
          <a href="https://www.facebook.com/COJCatholicProgressiveSchool" target="_blank">Facebook Page</a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>© <?= date('Y') ?> COJ Catholic Progressive School. All rights reserved.</span>
      <span>Enrollment Management System v2.0</span>
    </div>
  </div>
</footer>

<script>
  // Smooth scroll for nav links
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
  });
</script>
</body>
</html>
