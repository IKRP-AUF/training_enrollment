<?php
// admin/students.php -- Student recording form (multi-table, transactional)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/EnrollmentRepository.php';
require_once __DIR__ . '/../classes/ClassSection.php';

$repo      = new EnrollmentRepository($db);
$classes   = new ClassSection($db);

$message = '';
$msgType = 'success';
$errors  = [];
$full_name = $email = $phone = '';
$class_id  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $class_id  = (int) ($_POST['class_id'] ?? 0);

    // ---- validation ----
    if ($full_name === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100) {
        $errors['full_name'] = 'Full name must be 2-100 characters.';
    } elseif (!preg_match("/^[\p{L}][\p{L} .'-]*$/u", $full_name)) {
        $errors['full_name'] = 'Use letters, spaces, dots, apostrophes and hyphens only.';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) {
        $errors['email'] = 'Enter a valid email address (max 100 characters).';
    }
    if ($phone !== '' && (!preg_match('/^[0-9+() -]{7,30}$/', $phone))) {
        $errors['phone'] = 'Phone must be 7-30 characters: digits, spaces, + ( ) -.';
    }
    if ($class_id <= 0 || $classes->getSlots($class_id) === null) {
        $errors['class_id'] = 'Select a valid class.';
    }

    if (!$errors) {
        try {
            // ONE call: INSERT student + INSERT enrollment + decrement slots, in ONE transaction
            $student_id = $repo->recordStudent($full_name, $email, $phone, $class_id);
            $left = $classes->getSlots($class_id);
            $message = "Student recorded and enrolled successfully (Student ID #$student_id). "
                     . "Remaining slots in the class: $left.";
            $full_name = $email = $phone = '';
            $class_id = 0;
        } catch (PDOException $e) {
            error_log('recordStudent failed: ' . $e->getMessage());
            $message = 'A database error occurred. Nothing was saved.';
            $msgType = 'error';
        } catch (RuntimeException $e) {
            // e.g. "No slots available ..." -> the transaction was rolled back
            $message = $e->getMessage() . ' Nothing was saved (transaction rolled back).';
            $msgType = 'error';
        }
    }
}

$classList = $classes->allWithCourse();   // for the class dropdown (refreshed after the write)
$pageTitle = 'Record Student';
$base = '../';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Record Student</h1>

<?php if ($message): ?><div class="msg <?php echo h($msgType); ?>"><?php echo h($message); ?></div><?php endif; ?>

<div class="card">
    <p>Records a new student and enrolls them in the selected class in a single transaction.</p>
    <form method="POST" class="grid" novalidate>
        <div>
            <label for="full_name">Full Name *</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo h($full_name); ?>">
            <?php if (isset($errors['full_name'])): ?><div class="err"><?php echo h($errors['full_name']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="email">Email</label>
            <input type="text" id="email" name="email" value="<?php echo h($email); ?>" placeholder="name@example.com">
            <?php if (isset($errors['email'])): ?><div class="err"><?php echo h($errors['email']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo h($phone); ?>">
            <?php if (isset($errors['phone'])): ?><div class="err"><?php echo h($errors['phone']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="class_id">Class *</label>
            <select id="class_id" name="class_id">
                <option value="0">-- Select class --</option>
                <?php foreach ($classList as $c): ?>
                    <option value="<?php echo (int) $c['class_id']; ?>" <?php echo $class_id === (int) $c['class_id'] ? 'selected' : ''; ?>>
                        <?php echo h($c['class_code'] . ' - ' . $c['course_name'] . ' (' . $c['schedule'] . ') - '
                            . ((int) $c['slots'] > 0 ? $c['slots'] . ' slot(s) left' : 'FULL')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['class_id'])): ?><div class="err"><?php echo h($errors['class_id']); ?></div><?php endif; ?>
        </div>
        <div class="full">
            <button type="submit" class="btn">Record &amp; Enroll</button>
            <a class="btn secondary" href="enrollments.php">View Enrollments</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
