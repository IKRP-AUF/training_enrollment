# Design patterns used

## 1. Singleton: `Database`
`Database` extends `PDO`, has a **private constructor**, and exposes `Database::getInstance($dsn, $user, $pass)`.
The first call creates the connection; every later call returns the same object.

**Why:** the whole request should share one connection, and one configuration (exception error mode,
`FETCH_ASSOC`). `config/db.php` creates it once (`$db`), and every class receives that same `$db`, so
the transaction opened in `EnrollmentRepository` runs on the same connection as every statement inside it.
Two connections would break transactions, because a rollback on one would not undo work done on the other.

## 2. Repository: `EnrollmentRepository`
`EnrollmentRepository` is the single place that knows how enrollments are stored and read:
`recordStudent()`, `enroll()`, `cancel()`, `allWithDetails()` and the report queries.

**Why:** recording a student touches three tables (`students`, `enrollments`, `classes`) and must succeed or
fail as a unit. Putting that in one repository method keeps the transaction logic out of the pages. The pages in
`admin/` only validate the request and display the result; they never write SQL. It also keeps the SQL
JOINs in one place, so a change to the schema means a change in one class.

## 3. Simple data-access (table gateway) classes: `Student`, `Course`, `ClassSection`
Each class wraps the queries for one table and receives the shared `PDO` through its constructor (dependency injection).

**Why:** one class per table matches the class diagram, keeps queries reusable, and makes each class easy to
test or replace without touching the pages.

## 4. Transaction (Unit of Work) in the repository
Every multi-step write uses `beginTransaction()` ... `commit()` with `rollBack()` in the `catch` block:

| Operation | Steps inside one transaction |
|---|---|
| `recordStudent()` | check slots (row locked with `FOR UPDATE`) > INSERT student > `lastInsertId()` > INSERT enrollment > `slots = slots - 1` > commit |
| `enroll()` | check student exists > check slots > check no active duplicate > INSERT enrollment > `slots = slots - 1` > commit |
| `cancel()` | check enrollment is active > `status = 'cancelled'` > `slots = slots + 1` > commit |

The decrement uses `UPDATE ... SET slots = slots - 1 WHERE class_id = :id AND slots > 0` and checks `rowCount()`,
so two administrators can never take the last slot at the same time.
