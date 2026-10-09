<?php
// index.php
$pageTitle = 'Training Enrollment System';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Training Enrollment System</h1>
<div class="card">
    <p>Welcome, Administrator. Use this system to manage the training catalog, record and
       enroll participants, cancel enrollments and review summary reports. Every enrollment or
       cancellation runs in a database transaction so slots and enrollment records always stay
       consistent.</p>
    <p class="links">
        <a class="btn" href="admin/courses.php">Manage Courses</a>
        <a class="btn" href="admin/classes.php">Manage Classes</a>
        <a class="btn" href="admin/students.php">Record Student</a>
        <a class="btn" href="admin/enroll.php">Enroll Existing Student</a>
        <a class="btn" href="admin/enrollments.php">View Enrollments</a>
        <a class="btn secondary" href="admin/reports.php">Reports</a>
    </p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
