<?php
// admin/enroll.php -- Enroll an EXISTING student into a class (transactional)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/EnrollmentRepository.php';
require_once __DIR__ . '/../classes/Student.php';
require_once __DIR__ . '/../classes/ClassSection.php';

$repo     = new EnrollmentRepository($db);
$students = new Student($db);
$sections = new ClassSection($db);

$message = '';
$msgType = 'success';
$errors  = [];
$student_id = 0;
$class_id   = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $class_id   = (int) ($_POST['class_id'] ?? 0);

    // ---- validate the ids ----
    if ($student_id <= 0 || !$students->find($student_id)) {
        $errors['student_id'] = 'Select a valid student.';
    }
    if ($class_id <= 0 || $sections->getSlots($class_id) === null) {
        $errors['class_id'] = 'Select a valid class.';
    }

    if (!$errors) {
        try {
            $repo->enroll($student_id, $class_id);
            $message = 'Student enrolled successfully. Remaining slots in the class: '
                     . $sections->getSlots($class_id) . '.';
            $student_id = 0;
            $class_id = 0;
        } catch (PDOException $e) {
            error_log('enroll failed: ' . $e->getMessage());
            $message = 'A database error occurred. Nothing was saved.';
            $msgType = 'error';
        } catch (RuntimeException $e) {
            $message = $e->getMessage() . ' Nothing was saved (transaction rolled back).';
            $msgType = 'error';
        }
    }
}

$studentList = $students->all();
$classList   = $sections->allWithCourse();
$pageTitle = 'Enroll Student';
$base = '../';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Enroll Existing Student</h1>

<?php if ($message): ?><div class="msg <?php echo h($msgType); ?>"><?php echo h($message); ?></div><?php endif; ?>

<div class="card">
    <form method="POST" class="grid" novalidate>
        <div>
            <label for="student_id">Student *</label>
            <select id="student_id" name="student_id">
                <option value="0">-- Select student --</option>
                <?php foreach ($studentList as $s): ?>
                    <option value="<?php echo (int) $s['student_id']; ?>" <?php echo $student_id === (int) $s['student_id'] ? 'selected' : ''; ?>>
                        <?php echo h($s['full_name'] . ($s['email'] ? ' <' . $s['email'] . '>' : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['student_id'])): ?><div class="err"><?php echo h($errors['student_id']); ?></div><?php endif; ?>
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
            <button type="submit" class="btn">Enroll</button>
            <a class="btn secondary" href="enrollments.php">View Enrollments</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
