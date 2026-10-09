-- training_db.sql: starter export (schema + sample data).
-- REPLACE this file with your own phpMyAdmin export (Export tab -> training_db -> Go) after testing.
CREATE DATABASE IF NOT EXISTS training_db;
USE training_db;

CREATE TABLE students (
   student_id INT AUTO_INCREMENT PRIMARY KEY,
   full_name  VARCHAR(100) NOT NULL,
   email      VARCHAR(100),
   phone      VARCHAR(30),
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE courses (
   course_id   INT AUTO_INCREMENT PRIMARY KEY,
   course_code VARCHAR(20) NOT NULL UNIQUE,
   course_name VARCHAR(100) NOT NULL,
   description TEXT
) ENGINE=InnoDB;

CREATE TABLE classes (
   class_id   INT AUTO_INCREMENT PRIMARY KEY,
   course_id  INT NOT NULL,
   class_code VARCHAR(20) NOT NULL,
   schedule   VARCHAR(100),
   instructor VARCHAR(100),
   slots      INT NOT NULL DEFAULT 0,
   FOREIGN KEY (course_id) REFERENCES courses(course_id)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
   enrollment_id   INT AUTO_INCREMENT PRIMARY KEY,
   student_id      INT NOT NULL,
   class_id        INT NOT NULL,
   enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   status          VARCHAR(20) DEFAULT 'active',
   FOREIGN KEY (student_id) REFERENCES students(student_id),
   FOREIGN KEY (class_id)   REFERENCES classes(class_id)
) ENGINE=InnoDB;

-- ---------- Sample data ----------
INSERT INTO courses (course_code, course_name, description) VALUES
 ('WD101',  'Web Development Fundamentals', 'HTML, CSS and an introduction to PHP.'),
 ('DB201',  'Database Programming',         'SQL, PDO, transactions and JOINs.'),
 ('NET101', 'Networking Basics',            'TCP/IP, routing and switching essentials.');

-- slots = REMAINING slots. NET101-A is deliberately full (0) to demonstrate the rollback case.
INSERT INTO classes (course_id, class_code, schedule, instructor, slots) VALUES
 (1, 'WD101-A',  'Mon/Wed 9:00-11:00 AM',  'Ms. Reyes',   9),
 (1, 'WD101-B',  'Sat 1:00-5:00 PM',       'Mr. Cruz',    5),
 (2, 'DB201-A',  'Tue/Thu 1:00-3:00 PM',   'Mr. Santos',  1),
 (3, 'NET101-A', 'Fri 8:00 AM-12:00 NN',   'Ms. Lopez',   0);

INSERT INTO students (full_name, email, phone) VALUES
 ('Maria Santos',  'maria@example.com', '09171234567'),
 ('Juan Dela Cruz','juan@example.com',  '09181234567');

-- Two sample enrollments (slots above already account for them)
INSERT INTO enrollments (student_id, class_id, status) VALUES
 (1, 1, 'active'),
 (2, 2, 'active');
