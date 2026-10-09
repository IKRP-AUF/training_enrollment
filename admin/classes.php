<?php
// admin/classes.php -- Manage class schedules and slots
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Course.php';
require_once __DIR__ . '/../classes/ClassSection.php';

$courses  = new Course($db);
$sections = new ClassSection($db);
$message = '';
$msgType = 'success';
$errors  = [];
$form    = ['class_id' => 0, 'course_id' => 0, 'class_code' => '', 'schedule' => '', 'instructor' => '', 'slots' => ''];
$courseList = $courses->all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'class_id'   => (int) ($_POST['class_id'] ?? 0),
        'course_id'  => (int) ($_POST['course_id'] ?? 0),
        'class_code' => trim($_POST['class_code'] ?? ''),
        'schedule'   => trim($_POST['schedule'] ?? ''),
        'instructor' => trim($_POST['instructor'] ?? ''),
        'slots'      => trim($_POST['slots'] ?? ''),
    ];

    // ---- validation ----
    if ($form['course_id'] <= 0 || !$courses->find($form['course_id'])) {
        $errors['course_id'] = 'Select a valid course.';
    }
    if ($form['class_code'] === '') {
        $errors['class_code'] = 'Class code is required.';
    } elseif (strlen($form['class_code']) > 20) {
        $errors['class_code'] = 'Class code must be at most 20 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $form['class_code'])) {
        $errors['class_code'] = 'Use letters, numbers, dash or underscore only.';
    }
    if ($form['schedule'] === '') {
        $errors['schedule'] = 'Schedule is required.';
    } elseif (mb_strlen($form['schedule']) > 100) {
        $errors['schedule'] = 'Schedule must be at most 100 characters.';
    }
    if ($form['instructor'] === '') {
        $errors['instructor'] = 'Instructor is required.';
    } elseif (mb_strlen($form['instructor']) > 100) {
        $errors['instructor'] = 'Instructor must be at most 100 characters.';
    }
    $slots = filter_var($form['slots'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000]]);
    if ($slots === false) {
        $errors['slots'] = 'Slots must be a whole number from 0 to 1000.';
    }

    if (!$errors) {
        try {
            if ($form['class_id'] > 0) {
                if (!$sections->find($form['class_id'])) {
                    throw new RuntimeException('Class not found.');
                }
                $sections->update($form['class_id'], $form['course_id'], $form['class_code'],
                                  $form['schedule'], $form['instructor'], $slots);
                $message = 'Class updated successfully.';
            } else {
                $sections->create($form['course_id'], $form['class_code'], $form['schedule'],
                                  $form['instructor'], $slots);
                $message = 'Class added successfully.';
            }
            $form = ['class_id' => 0, 'course_id' => 0, 'class_code' => '', 'schedule' => '', 'instructor' => '', 'slots' => ''];
        } catch (PDOException $e) {
            $message = 'Could not save the class.';
            $msgType = 'error';
            error_log('Class save failed: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
} elseif (isset($_GET['edit'])) {
    $row = $sections->find((int) $_GET['edit']);
    if ($row) {
        $form = [
            'class_id' => (int) $row['class_id'], 'course_id' => (int) $row['course_id'],
            'class_code' => $row['class_code'], 'schedule' => (string) $row['schedule'],
            'instructor' => (string) $row['instructor'], 'slots' => (string) $row['slots'],
        ];
    }
}

$list = $sections->allWithCourse();   // classes JOIN courses
$pageTitle = 'Classes';
$base = '../';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Manage Classes</h1>

<?php if ($message): ?><div class="msg <?php echo h($msgType); ?>"><?php echo h($message); ?></div><?php endif; ?>

<div class="card">
    <h2><?php echo $form['class_id'] > 0 ? 'Edit Class' : 'Add Class'; ?></h2>
    <form method="POST" class="grid" novalidate>
        <input type="hidden" name="class_id" value="<?php echo (int) $form['class_id']; ?>">
        <div>
            <label for="course_id">Course *</label>
            <select id="course_id" name="course_id">
                <option value="0">-- Select course --</option>
                <?php foreach ($courseList as $c): ?>
                    <option value="<?php echo (int) $c['course_id']; ?>" <?php echo $form['course_id'] === (int) $c['course_id'] ? 'selected' : ''; ?>>
                        <?php echo h($c['course_code'] . ' - ' . $c['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['course_id'])): ?><div class="err"><?php echo h($errors['course_id']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="class_code">Class Code *</label>
            <input type="text" id="class_code" name="class_code" value="<?php echo h($form['class_code']); ?>">
            <?php if (isset($errors['class_code'])): ?><div class="err"><?php echo h($errors['class_code']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="schedule">Schedule *</label>
            <input type="text" id="schedule" name="schedule" value="<?php echo h($form['schedule']); ?>" placeholder="e.g. Mon/Wed 9:00-11:00 AM">
            <?php if (isset($errors['schedule'])): ?><div class="err"><?php echo h($errors['schedule']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="instructor">Instructor *</label>
            <input type="text" id="instructor" name="instructor" value="<?php echo h($form['instructor']); ?>">
            <?php if (isset($errors['instructor'])): ?><div class="err"><?php echo h($errors['instructor']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="slots">Available Slots *</label>
            <input type="number" id="slots" name="slots" min="0" value="<?php echo h($form['slots']); ?>">
            <?php if (isset($errors['slots'])): ?><div class="err"><?php echo h($errors['slots']); ?></div><?php endif; ?>
        </div>
        <div class="full">
            <button type="submit" class="btn"><?php echo $form['class_id'] > 0 ? 'Save Changes' : 'Add Class'; ?></button>
            <?php if ($form['class_id'] > 0): ?><a class="btn secondary" href="classes.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</div>

<h2>All Classes</h2>
<table>
    <thead><tr><th>Course</th><th>Class Code</th><th>Schedule</th><th>Instructor</th><th>Remaining Slots</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): ?>
        <tr>
            <td><?php echo h($c['course_name']); ?></td>
            <td><?php echo h($c['class_code']); ?></td>
            <td><?php echo h($c['schedule']); ?></td>
            <td><?php echo h($c['instructor']); ?></td>
            <td>
                <?php echo (int) $c['slots']; ?>
                <?php if ((int) $c['slots'] === 0): ?><span class="badge full">FULL</span><?php endif; ?>
            </td>
            <td><a class="btn small" href="classes.php?edit=<?php echo (int) $c['class_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6">No classes yet.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
