<?php
// includes/header.php
// $base is the path prefix back to the project root: '' for index.php, '../' for pages in admin/.
// (Small change to the provided header so links and CSS also work from admin/ pages.)
$base = $base ?? '';
if (!function_exists('h')) {
    // Escape output for HTML.
    function h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($pageTitle ?? 'Training Enrollment System'); ?></title>
    <link rel="stylesheet" href="<?php echo $base; ?>css/style.css">
</head>
<body>
<nav>
    <a href="<?php echo $base; ?>index.php">Home</a> |
    <a href="<?php echo $base; ?>admin/courses.php">Courses</a> |
    <a href="<?php echo $base; ?>admin/classes.php">Classes</a> |
    <a href="<?php echo $base; ?>admin/students.php">Record Student</a> |
    <a href="<?php echo $base; ?>admin/enroll.php">Enroll</a> |
    <a href="<?php echo $base; ?>admin/enrollments.php">Enrollments</a> |
    <a href="<?php echo $base; ?>admin/reports.php">Reports</a>
    <span class="who">Signed in as: Administrator</span>
</nav>
<main>
