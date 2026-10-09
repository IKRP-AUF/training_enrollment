<?php
// classes/ClassSection.php
class ClassSection {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function allWithCourse() {
        // JOIN classes with courses so each row shows the course name
        $sql = "SELECT c.*, co.course_code, co.course_name
                  FROM classes c
                  JOIN courses co ON c.course_id = co.course_id
              ORDER BY co.course_name, c.class_code";
        return $this->db->query($sql)->fetchAll();
    }

    public function find($class_id) {
        $stmt = $this->db->prepare("SELECT * FROM classes WHERE class_id = :id");
        $stmt->execute([':id' => $class_id]);
        return $stmt->fetch();
    }

    public function create($course_id, $code, $schedule, $instructor, $slots) {
        $stmt = $this->db->prepare(
            "INSERT INTO classes (course_id, class_code, schedule, instructor, slots)
             VALUES (:course_id, :code, :schedule, :instructor, :slots)");
        $stmt->execute([
            ':course_id' => $course_id, ':code' => $code, ':schedule' => $schedule,
            ':instructor' => $instructor, ':slots' => $slots
        ]);
        return $this->db->lastInsertId();
    }

    // Edit a class (workflow: "add, edit, schedule, slots").
    public function update($class_id, $course_id, $code, $schedule, $instructor, $slots) {
        $stmt = $this->db->prepare(
            "UPDATE classes
                SET course_id = :course_id, class_code = :code, schedule = :schedule,
                    instructor = :instructor, slots = :slots
              WHERE class_id = :id");
        return $stmt->execute([
            ':course_id' => $course_id, ':code' => $code, ':schedule' => $schedule,
            ':instructor' => $instructor, ':slots' => $slots, ':id' => $class_id
        ]);
    }

    public function getSlots($class_id) {
        // Remaining slots for a class (null when the class does not exist).
        $stmt = $this->db->prepare("SELECT slots FROM classes WHERE class_id = :id");
        $stmt->execute([':id' => $class_id]);
        $row = $stmt->fetch();
        return $row ? (int) $row['slots'] : null;
    }
}
