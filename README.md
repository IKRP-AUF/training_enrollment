# Training Enrollment System

A multi-table PHP + PDO web application for a training organization. A single administrator manages
**courses**, **classes** (schedule, instructor, remaining slots), **students** and **enrollments**.
Enrollment and cancellation run in **database transactions** so slots and enrollment records always stay consistent.

## Features
- Course CRUD (add / edit / delete, with a guard when a course still has classes)
- Class management: classes joined with their course, schedule and remaining slots
- Student recording form: one transaction inserts the student, inserts the enrollment and decrements the slots
- Enroll an existing student (prevents full classes and duplicate active enrollments)
- Enrollments list with INNER JOINs across 4 tables, search, status/class filters, sorting and pagination
- Cancel an enrollment: one transaction updates the status and restores the slot
- Summary reports with `GROUP BY` and `LEFT JOIN`
- Server-side validation, prepared statements everywhere, output escaped with `htmlspecialchars()`

## Requirements
XAMPP (Apache + MySQL/MariaDB + PHP 8.2+) with the `pdo_mysql` extension enabled.

## Setup
1. Install and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy this folder to `C:\xampp\htdocs\training_enrollment\` (so `index.php` is directly inside it).
3. Open `http://localhost/phpmyadmin` > **SQL** tab, paste the contents of `sql/schema.sql` and click **Go**.
   (Or import `training_db.sql`.) This creates the `training_db` database, the 4 tables and sample data.
4. Open `http://localhost/training_enrollment/`.

### Database credentials
`config/db.php` uses `root` with an empty password (XAMPP default). To use other credentials, copy
`config/db.local.sample.php` to `config/db.local.php` and edit it. That file is git-ignored, so no password is
ever committed. If the connection fails on `localhost`, change the host to `127.0.0.1`.

## Demo data
`NET101-A` is created with **0 slots** so you can show the rollback case (recording into a full class saves nothing).
`DB201-A` has **1** slot so you can fill a class with a single enrollment.

## Folder structure
```
training_enrollment/
|-- config/db.php                 single PDO connection
|-- classes/                      Database, Student, Course, ClassSection, EnrollmentRepository (+ Pet example)
|-- admin/                        courses, classes, students, enroll, enrollments, reports
|-- includes/header.php, footer.php
|-- css/style.css
|-- sql/schema.sql                schema + sample data
|-- training_db.sql               database export
`-- index.php
```

## Design patterns
- **Singleton**: `Database` extends `PDO` and exposes `getInstance()`; one shared connection for the whole request,
  which is required so that a transaction and all of its statements run on the same connection.
- **Repository**: `EnrollmentRepository` is the single data-access class for enrollments. It hides the multi-table
  SQL and the transactions from the pages, which only validate input and render output.
- **Table gateway / DAO classes**: `Student`, `Course`, `ClassSection` wrap one table each and receive the shared
  connection through their constructor.

See `docs/DESIGN_PATTERNS.md` for the full explanation and the transaction steps.
