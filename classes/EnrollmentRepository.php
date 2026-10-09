<?php
// classes/EnrollmentRepository.php
// Data-access class that records students, enrollments and slot updates
// inside a single database transaction (Repository pattern).
//
// Domain problems (no slots, duplicate enrollment, ...) are signalled with a
// RuntimeException whose message is safe to show to the administrator.
// Database errors surface as PDOException (a subclass of RuntimeException), so
// pages must catch PDOException FIRST.
class EnrollmentRepository {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }

    /* ---------------------------------------------------------------
     * Internal helpers (all use prepared statements)
     * ------------------------------------------------------------- */

    // Lock the class row for this transaction and return its remaining slots.
    private function lockClassSlots($class_id) {
        $stmt = $this->db->prepare("SELECT slots FROM classes WHERE class_id = :id FOR UPDATE");
        $stmt->execute([':id' => $class_id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('The selected class does not exist.');
        }
        return (int) $row['slots'];
    }

    // Atomic guard: only decrements when a slot is still left.
    private function decrementSlot($class_id) {
        $stmt = $this->db->prepare(
            "UPDATE classes SET slots = slots - 1 WHERE class_id = :id AND slots > 0");
        $stmt->execute([':id' => $class_id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No slots available for the selected class.');
        }
    }

    private function insertEnrollment($student_id, $class_id) {
        $stmt = $this->db->prepare(
            "INSERT INTO enrollments (student_id, class_id) VALUES (:student_id, :class_id)");
        $stmt->execute([':student_id' => $student_id, ':class_id' => $class_id]);
        return $this->db->lastInsertId();
    }

    /* ---------------------------------------------------------------
     * Transactional operations
     * ------------------------------------------------------------- */

    // Record a NEW student AND enroll them into a class.
    // Returns the new student_id. Throws when there are no slots left.
    public function recordStudent($full_name, $email, $phone, $class_id) {
        $this->db->beginTransaction();                                   // 1
        try {
            if ($this->lockClassSlots($class_id) < 1) {                  // 2
                throw new RuntimeException('No slots available for the selected class.');
            }
            $stmt = $this->db->prepare(                                  // 3
                "INSERT INTO students (full_name, email, phone)
                 VALUES (:full_name, :email, :phone)");
            $stmt->execute([
                ':full_name' => $full_name,
                ':email'     => ($email !== '' ? $email : null),
                ':phone'     => ($phone !== '' ? $phone : null),
            ]);
            $student_id = $this->db->lastInsertId();                     // 4
            $this->insertEnrollment($student_id, $class_id);             // 5
            $this->decrementSlot($class_id);                             // 6
            $this->db->commit();                                         // 7
            return (int) $student_id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();                                   // 7 (otherwise)
            }
            throw $e;
        }
    }

    // Enroll an EXISTING student into a class.
    public function enroll($student_id, $class_id) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT student_id FROM students WHERE student_id = :id");
            $stmt->execute([':id' => $student_id]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('The selected student does not exist.');
            }
            if ($this->lockClassSlots($class_id) < 1) {                  // 1
                throw new RuntimeException('No slots available for the selected class.');
            }
            $stmt = $this->db->prepare(                                  // no duplicates
                "SELECT enrollment_id FROM enrollments
                  WHERE student_id = :student_id AND class_id = :class_id AND status = 'active'
                  LIMIT 1");
            $stmt->execute([':student_id' => $student_id, ':class_id' => $class_id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('This student is already actively enrolled in that class.');
            }
            $enrollment_id = $this->insertEnrollment($student_id, $class_id);   // 2
            $this->decrementSlot($class_id);                                    // 3
            $this->db->commit();
            return (int) $enrollment_id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // Cancel an enrollment and give the slot back.
    public function cancel($enrollment_id) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT enrollment_id, class_id, status FROM enrollments
                  WHERE enrollment_id = :id FOR UPDATE");
            $stmt->execute([':id' => $enrollment_id]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('Enrollment not found.');
            }
            if ($row['status'] !== 'active') {
                // Guard: cancelling twice would restore the slot twice.
                throw new RuntimeException('This enrollment is already cancelled.');
            }
            $stmt = $this->db->prepare(                                   // 1
                "UPDATE enrollments SET status = 'cancelled' WHERE enrollment_id = :id");
            $stmt->execute([':id' => $enrollment_id]);
            $stmt = $this->db->prepare(                                   // 2
                "UPDATE classes SET slots = slots + 1 WHERE class_id = :class_id");
            $stmt->execute([':class_id' => $row['class_id']]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /* ---------------------------------------------------------------
     * Reading: multi-table JOINs with search / filter / sort / pagination
     * ------------------------------------------------------------- */

    // Builds the WHERE clause + params from user filters (all values are bound).
    private function buildFilter(array $filters) {
        $where = [];
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'cancelled'], true)) {
            $where[] = "e.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['class_id'])) {
            $where[] = "e.class_id = :class_id";
            $params[':class_id'] = (int) $filters['class_id'];
        }
        if (isset($filters['q']) && trim($filters['q']) !== '') {
            // a named placeholder may be used only once, so each LIKE gets its own
            $where[] = "(s.full_name LIKE :q1 OR s.email LIKE :q2
                         OR co.course_name LIKE :q3 OR c.class_code LIKE :q4)";
            $like = '%' . trim($filters['q']) . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function bindAll(PDOStatement $stmt, array $params) {
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    // JOIN enrollments, students, classes and courses.
    // Called with no arguments it returns every enrollment (newest first).
    public function allWithDetails(array $filters = [], $sort = 'date', $dir = 'desc',
                                   $page = 1, $perPage = 0) {
        $sortable = [   // whitelist: user input never goes into ORDER BY directly
            'id'      => 'e.enrollment_id',
            'student' => 's.full_name',
            'course'  => 'co.course_name',
            'class'   => 'c.class_code',
            'status'  => 'e.status',
            'date'    => 'e.enrollment_date',
        ];
        $col = $sortable[$sort] ?? $sortable['date'];
        $dir = (strtolower($dir) === 'asc') ? 'ASC' : 'DESC';

        [$whereSql, $params] = $this->buildFilter($filters);
        $sql = "SELECT e.enrollment_id, e.enrollment_date, e.status,
                       s.student_id, s.full_name, s.email,
                       c.class_id, c.class_code, c.schedule, c.instructor, c.slots,
                       co.course_code, co.course_name
                  FROM enrollments e
            INNER JOIN students s  ON e.student_id = s.student_id
            INNER JOIN classes  c  ON e.class_id   = c.class_id
            INNER JOIN courses  co ON c.course_id  = co.course_id
                $whereSql
              ORDER BY $col $dir, e.enrollment_id DESC";

        if ($perPage > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit']  = (int) $perPage;
            $params[':offset'] = (int) (max(1, (int) $page) - 1) * (int) $perPage;
        }
        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countWithDetails(array $filters = []) {
        [$whereSql, $params] = $this->buildFilter($filters);
        $sql = "SELECT COUNT(*) AS total
                  FROM enrollments e
            INNER JOIN students s  ON e.student_id = s.student_id
            INNER JOIN classes  c  ON e.class_id   = c.class_id
            INNER JOIN courses  co ON c.course_id  = co.course_id
                $whereSql";
        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /* ---------------------------------------------------------------
     * Summative reports
     * ------------------------------------------------------------- */

    public function totals() {
        $sql = "SELECT
                  (SELECT COUNT(*) FROM courses)                               AS courses,
                  (SELECT COUNT(*) FROM classes)                               AS classes,
                  (SELECT COUNT(*) FROM students)                              AS students,
                  (SELECT COUNT(*) FROM enrollments WHERE status = 'active')    AS active_enrollments,
                  (SELECT COUNT(*) FROM enrollments WHERE status = 'cancelled') AS cancelled_enrollments,
                  (SELECT COALESCE(SUM(slots), 0) FROM classes)                AS open_slots";
        return $this->db->query($sql)->fetch();
    }

    // LEFT JOIN so classes with no enrollments still appear (with zeros).
    public function summaryByClass() {
        $sql = "SELECT c.class_id, c.class_code, co.course_name, c.schedule,
                       c.slots AS remaining_slots,
                       COALESCE(SUM(e.status = 'active'), 0)    AS active_count,
                       COALESCE(SUM(e.status = 'cancelled'), 0) AS cancelled_count
                  FROM classes c
            INNER JOIN courses co ON c.course_id = co.course_id
             LEFT JOIN enrollments e ON e.class_id = c.class_id
              GROUP BY c.class_id, c.class_code, co.course_name, c.schedule, c.slots
              ORDER BY co.course_name, c.class_code";
        return $this->db->query($sql)->fetchAll();
    }

    public function summaryByCourse() {
        $sql = "SELECT co.course_id, co.course_code, co.course_name,
                       COUNT(DISTINCT c.class_id)               AS class_count,
                       COALESCE(SUM(e.status = 'active'), 0)    AS active_count,
                       COALESCE(SUM(e.status = 'cancelled'), 0) AS cancelled_count
                  FROM courses co
             LEFT JOIN classes c ON c.course_id = co.course_id
             LEFT JOIN enrollments e ON e.class_id = c.class_id
              GROUP BY co.course_id, co.course_code, co.course_name
              ORDER BY co.course_name";
        return $this->db->query($sql)->fetchAll();
    }
}
