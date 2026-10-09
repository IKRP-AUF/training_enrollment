<?php
// admin/courses.php -- Manage courses (add, edit, delete)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Course.php';

$courses = new Course($db);
$message = '';
$msgType = 'success';
$errors  = [];
$form    = ['course_id' => 0, 'course_code' => '', 'course_name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['course_id'] ?? 0);
        try {
            if ($id > 0 && $courses->find($id)) {
                $courses->delete($id);
                $message = 'Course deleted.';
            } else {
                $message = 'Course not found.';
                $msgType = 'error';
            }
        } catch (PDOException $e) {
            // SQLSTATE 23000 = integrity constraint (classes still use this course)
            $message = ($e->getCode() === '23000')
                ? 'Cannot delete this course because it still has classes. Remove or reassign its classes first.'
                : 'Could not delete the course.';
            $msgType = 'error';
            error_log('Course delete failed: ' . $e->getMessage());
        }
    }

    if ($action === 'save') {
        $form = [
            'course_id'   => (int) ($_POST['course_id'] ?? 0),
            'course_code' => trim($_POST['course_code'] ?? ''),
            'course_name' => trim($_POST['course_name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
        ];

        // ---- validation ----
        if ($form['course_code'] === '') {
            $errors['course_code'] = 'Course code is required.';
        } elseif (strlen($form['course_code']) > 20) {
            $errors['course_code'] = 'Course code must be at most 20 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $form['course_code'])) {
            $errors['course_code'] = 'Use letters, numbers, dash or underscore only.';
        }
        if ($form['course_name'] === '') {
            $errors['course_name'] = 'Course name is required.';
        } elseif (mb_strlen($form['course_name']) > 100) {
            $errors['course_name'] = 'Course name must be at most 100 characters.';
        }
        if (mb_strlen($form['description']) > 1000) {
            $errors['description'] = 'Description must be at most 1000 characters.';
        }

        if (!$errors) {
            try {
                if ($form['course_id'] > 0) {
                    if (!$courses->find($form['course_id'])) {
                        throw new RuntimeException('Course not found.');
                    }
                    $courses->update($form['course_id'], $form['course_code'],
                                     $form['course_name'], $form['description']);
                    $message = 'Course updated successfully.';
                } else {
                    $courses->create($form['course_code'], $form['course_name'], $form['description']);
                    $message = 'Course added successfully.';
                }
                $form = ['course_id' => 0, 'course_code' => '', 'course_name' => '', 'description' => ''];
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors['course_code'] = 'That course code already exists.';
                } else {
                    $message = 'Could not save the course.';
                    $msgType = 'error';
                    error_log('Course save failed: ' . $e->getMessage());
                }
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
                $msgType = 'error';
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    // GET ?edit=ID pre-fills the form for editing
    $row = $courses->find((int) $_GET['edit']);
    if ($row) {
        $form = [
            'course_id' => (int) $row['course_id'], 'course_code' => $row['course_code'],
            'course_name' => $row['course_name'], 'description' => (string) $row['description'],
        ];
    }
}

$list = $courses->all();
$pageTitle = 'Courses';
$base = '../';
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Manage Courses</h1>

<?php if ($message): ?><div class="msg <?php echo h($msgType); ?>"><?php echo h($message); ?></div><?php endif; ?>

<div class="card">
    <h2><?php echo $form['course_id'] > 0 ? 'Edit Course' : 'Add Course'; ?></h2>
    <form method="POST" class="grid" novalidate>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="course_id" value="<?php echo (int) $form['course_id']; ?>">
        <div>
            <label for="course_code">Course Code *</label>
            <input type="text" id="course_code" name="course_code" value="<?php echo h($form['course_code']); ?>">
            <?php if (isset($errors['course_code'])): ?><div class="err"><?php echo h($errors['course_code']); ?></div><?php endif; ?>
        </div>
        <div>
            <label for="course_name">Course Name *</label>
            <input type="text" id="course_name" name="course_name" value="<?php echo h($form['course_name']); ?>">
            <?php if (isset($errors['course_name'])): ?><div class="err"><?php echo h($errors['course_name']); ?></div><?php endif; ?>
        </div>
        <div class="full">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?php echo h($form['description']); ?></textarea>
            <?php if (isset($errors['description'])): ?><div class="err"><?php echo h($errors['description']); ?></div><?php endif; ?>
        </div>
        <div class="full">
            <button type="submit" class="btn"><?php echo $form['course_id'] > 0 ? 'Save Changes' : 'Add Course'; ?></button>
            <?php if ($form['course_id'] > 0): ?><a class="btn secondary" href="courses.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</div>

<h2>All Courses</h2>
<table>
    <thead><tr><th>Code</th><th>Course Name</th><th>Description</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): ?>
        <tr>
            <td><?php echo h($c['course_code']); ?></td>
            <td><?php echo h($c['course_name']); ?></td>
            <td><?php echo h($c['description']); ?></td>
            <td>
                <a class="btn small" href="courses.php?edit=<?php echo (int) $c['course_id']; ?>">Edit</a>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this course?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="course_id" value="<?php echo (int) $c['course_id']; ?>">
                    <button type="submit" class="btn small danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="4">No courses yet.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
