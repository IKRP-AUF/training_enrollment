<?php
// admin/reports.php -- Summary reports (aggregates with GROUP BY and LEFT JOIN)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/EnrollmentRepository.php';

$repo     = new EnrollmentRepository($db);
$totals   = $repo->totals();
$byClass  = $repo->summaryByClass();
$byCourse = $repo->summaryByCourse();

$pageTitle = 'Reports';
$base = '../';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Reports</h1>

<div class="stats">
    <div class="stat"><b><?php echo (int) $totals['courses']; ?></b>Courses</div>
    <div class="stat"><b><?php echo (int) $totals['classes']; ?></b>Classes</div>
    <div class="stat"><b><?php echo (int) $totals['students']; ?></b>Students</div>
    <div class="stat"><b><?php echo (int) $totals['active_enrollments']; ?></b>Active enrollments</div>
    <div class="stat"><b><?php echo (int) $totals['cancelled_enrollments']; ?></b>Cancelled</div>
    <div class="stat"><b><?php echo (int) $totals['open_slots']; ?></b>Open slots</div>
</div>

<h2>Enrollments per course</h2>
<table>
    <thead><tr><th>Course</th><th>Classes</th><th>Active</th><th>Cancelled</th></tr></thead>
    <tbody>
    <?php foreach ($byCourse as $r): ?>
        <tr>
            <td><?php echo h($r['course_code'] . ' - ' . $r['course_name']); ?></td>
            <td><?php echo (int) $r['class_count']; ?></td>
            <td><?php echo (int) $r['active_count']; ?></td>
            <td><?php echo (int) $r['cancelled_count']; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2 style="margin-top:24px">Enrollments and remaining slots per class</h2>
<table>
    <thead><tr><th>Class</th><th>Course</th><th>Schedule</th><th>Active</th><th>Cancelled</th><th>Remaining slots</th></tr></thead>
    <tbody>
    <?php foreach ($byClass as $r): ?>
        <tr>
            <td><?php echo h($r['class_code']); ?></td>
            <td><?php echo h($r['course_name']); ?></td>
            <td><?php echo h($r['schedule']); ?></td>
            <td><?php echo (int) $r['active_count']; ?></td>
            <td><?php echo (int) $r['cancelled_count']; ?></td>
            <td><?php echo (int) $r['remaining_slots']; ?>
                <?php if ((int) $r['remaining_slots'] === 0): ?><span class="badge full">FULL</span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
