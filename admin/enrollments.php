<?php
// admin/enrollments.php -- List enrollments (JOINs) with search, filter, sort, pagination, cancel
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/EnrollmentRepository.php';
require_once __DIR__ . '/../classes/ClassSection.php';

$repo     = new EnrollmentRepository($db);
$sections = new ClassSection($db);
$message = '';
$msgType = 'success';

// ---- cancel (POST only; never change data from a plain link) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $enrollment_id = (int) ($_POST['enrollment_id'] ?? 0);
    if ($enrollment_id <= 0) {
        $message = 'Invalid enrollment.';
        $msgType = 'error';
    } else {
        try {
            $repo->cancel($enrollment_id);   // UPDATE status + restore slot, in ONE transaction
            $message = "Enrollment #$enrollment_id cancelled. The class slot was restored.";
        } catch (PDOException $e) {
            error_log('cancel failed: ' . $e->getMessage());
            $message = 'A database error occurred. Nothing was changed.';
            $msgType = 'error';
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
}

// ---- search / filter / sort / page (read from the query string) ----
$filters = [
    'q'        => trim($_GET['q'] ?? ''),
    'status'   => $_GET['status'] ?? '',
    'class_id' => (int) ($_GET['class_id'] ?? 0),
];
$sort    = $_GET['sort'] ?? 'date';
$dir     = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
$perPage = 10;
$total   = $repo->countWithDetails($filters);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

// fetch all enrollments with student, class and course details
$rows = $repo->allWithDetails($filters, $sort, $dir, $page, $perPage);
$classList = $sections->allWithCourse();

// helper: build a link that keeps the current filters
function qs(array $override = []) {
    $params = array_merge($_GET, $override);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === 0 || $v === '0') { unset($params[$k]); }
    }
    return '?' . http_build_query($params);
}
function sortLink($key, $label, $sort, $dir) {
    $newDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow  = ($sort === $key) ? ($dir === 'asc' ? ' &#9650;' : ' &#9660;') : '';
    return '<a href="' . h(qs(['sort' => $key, 'dir' => $newDir, 'page' => 1])) . '">' . h($label) . '</a>' . $arrow;
}

$pageTitle = 'Enrollments';
$base = '../';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Enrollments</h1>

<?php if ($message): ?><div class="msg <?php echo h($msgType); ?>"><?php echo h($message); ?></div><?php endif; ?>

<form method="GET" class="filters card">
    <div>
        <label for="q">Search</label>
        <input type="text" id="q" name="q" value="<?php echo h($filters['q']); ?>" placeholder="student, email, course, class">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
    </div>
    <div>
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id">
            <option value="0">All classes</option>
            <?php foreach ($classList as $c): ?>
                <option value="<?php echo (int) $c['class_id']; ?>" <?php echo $filters['class_id'] === (int) $c['class_id'] ? 'selected' : ''; ?>>
                    <?php echo h($c['class_code'] . ' - ' . $c['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <button type="submit" class="btn">Apply</button>
        <a class="btn secondary" href="enrollments.php">Reset</a>
    </div>
</form>

<p><?php echo (int) $total; ?> enrollment(s) found.</p>
<table>
    <thead>
        <tr>
            <th><?php echo sortLink('id', '#', $sort, $dir); ?></th>
            <th><?php echo sortLink('student', 'Student', $sort, $dir); ?></th>
            <th><?php echo sortLink('course', 'Course', $sort, $dir); ?></th>
            <th><?php echo sortLink('class', 'Class', $sort, $dir); ?></th>
            <th>Schedule / Instructor</th>
            <th>Slots left</th>
            <th><?php echo sortLink('status', 'Status', $sort, $dir); ?></th>
            <th><?php echo sortLink('date', 'Enrolled', $sort, $dir); ?></th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?php echo (int) $r['enrollment_id']; ?></td>
            <td><?php echo h($r['full_name']); ?><br><small><?php echo h($r['email']); ?></small></td>
            <td><?php echo h($r['course_code'] . ' - ' . $r['course_name']); ?></td>
            <td><?php echo h($r['class_code']); ?></td>
            <td><?php echo h($r['schedule']); ?><br><small><?php echo h($r['instructor']); ?></small></td>
            <td><?php echo (int) $r['slots']; ?></td>
            <td><span class="badge <?php echo $r['status'] === 'active' ? 'active' : 'cancelled'; ?>"><?php echo h($r['status']); ?></span></td>
            <td><?php echo h($r['enrollment_date']); ?></td>
            <td>
                <?php if ($r['status'] === 'active'): ?>
                    <form method="POST" action="<?php echo h(qs()); ?>" class="inline" onsubmit="return confirm('Cancel this enrollment?');">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="enrollment_id" value="<?php echo (int) $r['enrollment_id']; ?>">
                        <button type="submit" class="btn small danger">Cancel</button>
                    </form>
                <?php else: ?>&mdash;<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9">No enrollments match.</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?><a href="<?php echo h(qs(['page' => $page - 1])); ?>">&laquo; Prev</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="current"><?php echo $i; ?></span>
        <?php else: ?><a href="<?php echo h(qs(['page' => $i])); ?>"><?php echo $i; ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?php echo h(qs(['page' => $page + 1])); ?>">Next &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
