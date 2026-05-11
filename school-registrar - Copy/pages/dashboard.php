<?php
include('../mysql/db.php');
if (session_id() == "") session_start();

if (!isset($_SESSION['name'])) {
  header('location: ../index.php');
  exit();
}

$active_sy = $conn->query("SELECT * FROM school_years WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$sy_id     = $active_sy['id'] ?? 0;

// ── Enrollment stats ───────────────────────────────────────
$total_students = $conn->query("SELECT COUNT(*) as c FROM students WHERE is_archived=0")->fetch_assoc()['c'];

$enrollments_exist = $conn->query("SHOW TABLES LIKE 'enrollments'")->num_rows > 0;
$total_enrolled = $total_pending = $total_dropped = 0;
if ($enrollments_exist && $sy_id) {
  $total_enrolled = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE school_year_id=$sy_id AND status='enrolled'")->fetch_assoc()['c'];
  $total_pending  = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE school_year_id=$sy_id AND status='pending'")->fetch_assoc()['c'];
  $total_dropped  = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE school_year_id=$sy_id AND status='dropped'")->fetch_assoc()['c'];
}

// ── Payment stats ──────────────────────────────────────────
$payments_exist = $conn->query("SHOW TABLES LIKE 'payments'")->num_rows > 0;
$total_paid = $total_unpaid = $total_collection = $total_partial = 0;
if ($payments_exist) {
  $total_paid       = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM payments WHERE status='paid'")->fetch_assoc()['c'];
  $total_partial    = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM payments WHERE status='partial'")->fetch_assoc()['c'];
  $total_unpaid     = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM payments WHERE status='unpaid'")->fetch_assoc()['c'];
  $total_collection = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as c FROM payments")->fetch_assoc()['c'];
}

// ── Students per grade (Grade 7–10 only for JHS focus) ────
$grade_labels = [];
$grade_counts = [];
$grade_res = $conn->query("
  SELECT g.name as grade, COUNT(s.id) as total
  FROM grade_levels g
  LEFT JOIN students s ON s.grade_level_id = g.id AND s.is_archived = 0
  GROUP BY g.id ORDER BY g.id
");
while ($g = $grade_res->fetch_assoc()) {
  $grade_labels[] = $g['grade'];
  $grade_counts[] = (int)$g['total'];
}

// ── Recent enrollments ─────────────────────────────────────
$recent = $conn->query("
  SELECT s.first_name, s.last_name, s.photo, g.name as grade, s.student_type, s.id
  FROM students s
  LEFT JOIN grade_levels g ON s.grade_level_id = g.id
  WHERE s.is_archived = 0
  ORDER BY s.id DESC LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <!-- IMPORTANT: styles.css MUST come before dashboard.css -->
  <link rel="stylesheet" href="../css/styles.css?v=<?= time() ?>">
  <link rel="stylesheet" href="../css/dashboard.css?v=<?= time() ?>">
</head>
<body>

<?php $active_page = 'dashboard'; include('includes/sidebar.php'); ?>

<!-- ===== MAIN ===== -->
<div id="main">

  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Enrollment Overview — SY <?= htmlspecialchars($active_sy['label'] ?? 'N/A') ?></div>
    </div>
    <div class="topbar-user-chip">
      <i class="bi bi-person-circle"></i>
      <span><?= htmlspecialchars($_SESSION['name']) ?></span>
    </div>
  </div>

  <div id="page-container">

    <div class="stat-grid">
      <a href="students.php" class="stat-card">
        <div class="stat-icon-wrap"><i class="bi bi-people-fill"></i></div>
        <div class="stat-body"><div class="stat-value"><?= $total_students ?></div><div class="stat-label">Total Students</div></div>
      </a>
      <a href="enrollment.php" class="stat-card">
        <div class="stat-icon-wrap"><i class="bi bi-person-check-fill"></i></div>
        <div class="stat-body"><div class="stat-value"><?= $total_enrolled ?></div><div class="stat-label">Enrolled</div></div>
      </a>
      <a href="enrollment.php?status=pending" class="stat-card">
        <div class="stat-icon-wrap"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-body"><div class="stat-value"><?= $total_pending ?></div><div class="stat-label">Pending</div></div>
      </a>
      <a href="payments.php" class="stat-card">
        <div class="stat-icon-wrap"><i class="bi bi-cash-stack"></i></div>
        <div class="stat-body"><div class="stat-value">₱<?= number_format($total_collection, 0) ?></div><div class="stat-label">Total Collection</div></div>
      </a>
    </div>

    <div class="dash-row">
      <div class="dash-panel" style="flex:1;">
        <div class="panel-header"><div class="panel-title"><i class="bi bi-bar-chart-fill"></i> Students per Grade Level</div></div>
        <div class="panel-body"><div class="chart-wrap"><canvas id="gradeChart"></canvas></div></div>
      </div>
    </div>

    <div class="dash-row">
      <div class="dash-panel">
        <div class="panel-header">
          <div class="panel-title"><i class="bi bi-clock-history"></i> Recent Registrations</div>
          <a href="students.php" class="panel-link">View all →</a>
        </div>
        <div class="panel-body panel-body-flush">
          <table class="dash-table">
            <thead><tr><th>Student</th><th>Grade</th><th>Type</th><th></th></tr></thead>
            <tbody>
              <?php while ($r = $recent->fetch_assoc()): ?>
              <tr>
                <td><div class="name-cell">
                  <?php if (!empty($r['photo'])): ?><img src="uploads/<?= htmlspecialchars($r['photo']) ?>" class="mini-pic"/>
                  <?php else: ?><div class="mini-avatar"><i class="bi bi-person-fill"></i></div><?php endif; ?>
                  <span><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></span>
                </div></td>
                <td class="td-muted"><?= htmlspecialchars($r['grade'] ?? '—') ?></td>
                <td><span class="type-badge <?= $r['student_type'] === 'new' ? 'badge-new' : 'badge-old' ?>"><?= ucfirst($r['student_type']) ?></span></td>
                <td><a href="student_profile.php?id=<?= $r['id'] ?>" class="row-link"><i class="bi bi-arrow-right"></i></a></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="dash-panel">
        <div class="panel-header">
          <div class="panel-title"><i class="bi bi-cash-coin"></i> Payment Summary</div>
          <a href="payments.php" class="panel-link">View all →</a>
        </div>
        <div class="panel-body panel-body-flush">
          <div class="att-summary">
            <div class="att-sum-item att-present"><span class="att-sum-val"><?= $total_paid ?></span><span class="att-sum-lbl">Fully Paid</span></div>
            <div class="att-sum-item att-late"><span class="att-sum-val"><?= $total_partial ?></span><span class="att-sum-lbl">Partial</span></div>
            <div class="att-sum-item att-absent"><span class="att-sum-val"><?= $total_unpaid ?></span><span class="att-sum-lbl">Unpaid</span></div>
          </div>
          <div style="padding:20px;">
            <div style="font-size:11px;color:var(--color-muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Total Collection</div>
            <div style="font-size:26px;font-weight:700;color:var(--color-primary);">₱<?= number_format($total_collection, 2) ?></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="../js/nav.js"></script>
<script>
  Chart.defaults.font.family = 'Inter, sans-serif';
  Chart.defaults.color = '#6b7280';
  new Chart(document.getElementById('gradeChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($grade_labels) ?>,
      datasets: [{ 
        label: 'Students', 
        data: <?= json_encode($grade_counts) ?>, 
        backgroundColor: [
          '#818cf8','#a78bfa','#c084fc',
          '#60a5fa','#34d399','#4ade80',
          '#86efac','#fbbf24','#fb923c',
          '#6366f1','#22c55e','#f59e0b','#ef4444'
        ],
        borderRadius: 6, 
        borderSkipped: false 
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' students' } } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#9ca3af', font: { size: 11 } }, grid: { color: 'rgba(0,0,0,0.05)' }, border: { display: false } },
        x: { ticks: { color: '#6b7280', font: { size: 11, weight: '600' }, maxRotation: 45 }, grid: { display: false }, border: { display: false } }
      }
    }
  });
</script>
</body>
</html>