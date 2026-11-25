# God-Win Daycare & Nursery School — Database Schema (Phase 1)

> **Status:** ✅ All 30 migrations run cleanly · ✅ 44/44 relationship checks pass
> (`php database/verify_schema.php`)

## Architecture principles

1. **Students are records, not users.** There is no way for a child to log in.
   Students attach to parents through the **`parent_student`** pivot, which is
   what powers the **multi-child portal** (switch Child 1 → Child 2 without re-login).
2. **RBAC via `users.role`** — six roles: `admin`, `senior_pastor`,
   `head_of_school`, `teacher`, `accountant`, `parent` (PHP backed enums in
   `app/Enums`, cast on every enum column so MySQL, PHP and the React UI never drift).
3. **Finance is isolated from academics** — teachers can read fee data but only
   the Accountant writes `invoices / payments / expenses / fee_structures`.
4. **Immutable audit trail** — `audit_logs` records every mutation with actor,
   IP and before/after payload (`AuditLog::record(...)` helper).
5. **Soft deletes** on users, students, classes, attendance-related core records,
   invoices, payments, expenses, announcements and applications — history survives.
6. **Composite unique indexes** guard data integrity at the DB level
   (e.g. one attendance row per student per day, one parent link per child).

## Entity relationship map

```
academic_years 1───* terms
      │
classes 1───* students *───* users(parents)      [pivot: parent_student]
   │  │\                                        (relationship_type, is_primary_contact)
   │  │ └──* timetables
   │  ├──* assessments *─── subjects
   │  ├──* homeworks
   │  ├──* fee_structures
   │  └──* invoices 1───* invoice_items
   │           │  └──* payments *─── users(accountant)
   │           └──* (student)
   ├── class_subject *─── subjects (pivot carries allocated teacher)
   └── users (class teacher)

students 1───* attendance (marked_by → users)
students 1───* behavior_notes (noted_by → users)

users ───* conversations *───* users   [pivot: conversation_user]
              └──* messages (sender → users)

users ───* announcements / events / audit_logs / applications(reviewed_by)
applications ─── classes
settings (key/value), banners, contact_messages, notifications (Laravel morph)
```

## Tables (30 migrations + 4 framework)

| # | Table | Purpose |
|---|-------|---------|
| 1 | `users` | All 6 roles, phone, status, avatar, last_login_at, soft deletes |
| 2 | `password_reset_tokens`, `sessions`, `cache`, `jobs` | Framework tables |
| 3 | `settings` | CMS key/value config (admissions, contact, branding) |
| 4 | `academic_years` / `terms` | Calendar structure, `is_current` flags |
| 5 | `classes` | name, code, level (`daycare/nursery/kg1/kg2/primary`), teacher_id, capacity |
| 6 | `subjects` + `class_subject` | Subject catalogue & allocations (pivot = teacher) |
| 7 | `students` | reg_no, names, gender, dob, class_id, medical_notes, status |
| 8 | `parent_student` | **Multi-child portal pivot** + relationship + primary contact |
| 9 | `attendance` | UNIQUE(student_id, date), status, marked_by, daycare check-in/out |
| 10 | `assessments` | score/max_score/remarks, type incl. `milestone`, term, recorder |
| 11 | `homeworks` | Class activities, due dates, JSON attachments |
| 12 | `behavior_notes` | **Character & spiritual development** (Senior Pastor reports) |
| 13 | `timetables` | day + start/end time per class/subject/teacher |
| 14 | `fee_structures` | type (tuition/transport/feeding/daycare...), class_id NULL = all |
| 15 | `invoices` | invoice_number, total, amount_paid, status, due_date |
| 16 | `invoice_items` | Line items snapshotting each fee |
| 17 | `payments` | receipt_number, method, reference, paid_at, status |
| 18 | `expenses` | Category + incurred_on (revenue vs expense charts) |
| 19 | `announcements` | News/admissions — dual use: public site + in-app broadcasts |
| 20 | `events` | School calendar with audience scoping |
| 21 | `conversations` + `conversation_user` | Teacher ↔ parent messaging |
| 22 | `messages` | Chat body, attachments, read_at |
| 23 | `applications` | Public "Apply Now" form submissions (no login created) |
| 24 | `audit_logs` | Polymorphic audit trail (actor, action, before/after) |
| 25 | `banners` | Public hero slider (CMS) |
| 26 | `contact_messages` | Public contact form inbox |
| 27 | `notifications` | Laravel database notifications (absence/fee alerts) |

## Key computed attributes (used by dashboard charts)

* `Student::attendanceRate($from, $to)` → % (late counts as present, excused excluded)
* `Assessment::percentage` → 0–100 score
* `Invoice::balance` → `total_amount - amount_paid` · `Invoice::progress` → 0–100 %
* `Invoice::scopeOutstanding()` / `scopeOverdue()` → fee-collection widgets
* `Payment::scopeCompleted()` + `scopeBetween()` → monthly revenue series
* `Conversation::unreadCountFor($userId)` → unread message badges

## Switching local dev from SQLite → MySQL 8.4

The application has been migrated from SQLite to MySQL 8.4.3 as the primary
development database. The original SQLite database (`database/database.sqlite`)
remains untouched as a permanent fallback, along with its verified backup
(`database/backups/godwin_backup_20261122_011109.sqlite`).

### Target database

- **Host:** 127.0.0.1
- **Port:** 3306
- **Database:** `godwin_local_mysql`
- **Username:** root
- **Password:** (empty in local dev)

> **Never** use the existing `godwin` MySQL database — it contains unrelated
> records and must not be modified.

### Environment variables

Required in `.env`:

| Variable | Value | Purpose |
|---|---|---|
| `DB_CONNECTION` | `mysql` | Default connection |
| `DB_HOST` | `127.0.0.1` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `godwin_local_mysql` | Target database name |
| `DB_USERNAME` | `root` | MySQL username |
| `DB_PASSWORD` | `` | MySQL password |

Isolated import connection (used for one-time data migration only):

| Variable | Value |
|---|---|
| `MYSQL_IMPORT_HOST` | `127.0.0.1` |
| `MYSQL_IMPORT_PORT` | `3306` |
| `MYSQL_IMPORT_DATABASE` | `godwin_local_mysql` |
| `MYSQL_IMPORT_USERNAME` | `root` |
| `MYSQL_IMPORT_PASSWORD` | `` |

### Running migrations

```powershell
# Run all migrations against MySQL (default connection)
php artisan migrate

# Check migration status
php artisan migrate:status

# Rollback last batch
php artisan migrate:rollback
```

> **Never** run `php artisan migrate:fresh` or `php artisan db:wipe` — these
> would destroy existing data.

### One-time SQLite → MySQL data migration

A one-time data migration command was created to transfer all application data
from SQLite to MySQL:

```powershell
php artisan migrate:sqlite-to-mysql
```

This command:
- Reads directly from `database/database.sqlite`
- Writes to `godwin_local_mysql` via the `mysql_import` connection
- Preserves all primary keys, foreign keys, timestamps, nullable values, JSON, booleans, and soft deletes
- Uses foreign-key-safe insertion order
- Disables FK checks only within the controlled import transaction
- Does **not** copy the `migrations` table (MySQL maintains its own migration history)
- Does **not** use `INSERT IGNORE` or silently skip records

### Backing up MySQL

```powershell
# Full backup
mysqldump -u root -h 127.0.0.1 -P 3306 godwin_local_mysql > backup_godwin_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql

# Schema only
mysqldump -u root -h 127.0.0.1 -P 3306 --no-data godwin_local_mysql > schema_only.sql

# Data only
mysqldump -u root -h 127.0.0.1 -P 3306 --no-create-info godwin_local_mysql > data_only.sql
```

### Restoring MySQL from backup

```powershell
# Restore full backup
mysql -u root -h 127.0.0.1 -P 3306 godwin_local_mysql < backup_file.sql

# Restore into a fresh database
mysql -u root -h 127.0.0.1 -P 3306 -e "CREATE DATABASE godwin_local_mysql CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u root -h 127.0.0.1 -P 3306 godwin_local_mysql < backup_file.sql
```

### Using SQLite as fallback

To fall back to SQLite, update `.env`:

```powershell
DB_CONNECTION=sqlite
# DB_DATABASE is not needed for SQLite (defaults to database/database.sqlite)
```

Then run `php artisan config:clear`. The SQLite database and backup remain
untouched in `database/` as a permanent fallback.

### Running tests

```powershell
php vendor/bin/phpunit
```

Tests run against an in-memory SQLite database (see `phpunit.xml`) and do not
affect the MySQL development database.

### Production database configuration

For production, set the following environment variables:

| Variable | Description |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | Production MySQL host |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | Production database name |
| `DB_USERNAME` | Production MySQL username |
| `DB_PASSWORD` | Production MySQL password |
| `DB_CHARSET` | `utf8mb4` |
| `DB_COLLATION` | `utf8mb4_0900_ai_ci` |

Never commit `.env` or database backups to Git (see `.gitignore`).

### Migration compatibility fixes

The following SQLite→MySQL compatibility issues were fixed:

1. **`sms_provider_configs`** — Replaced partial unique index
   `$table->unique('is_active')->where('is_active', true)` (MySQL-incompatible)
   with a nullable `active_slot` column + unique index. Model events maintain
   `active_slot = 1` when active, `NULL` otherwise. This enforces "only one
   active provider at a time" at the database level on both MySQL and SQLite.

2. **`sms_messages`** — Replaced partial unique index
   `$table->unique('idempotency_key')->whereNotNull('idempotency_key')`
   with a regular `$table->unique('idempotency_key')`. MySQL allows multiple
   NULLs in a unique index, preserving the idempotency semantics.

3. **`assessments`** — Replaced partial unique index
   `$table->unique([...])->whereNotNull('examination_id')` with a regular
   composite `$table->unique(['student_id', 'examination_id', 'subject_id'])`.
   Nullable `examination_id` behaves correctly: multiple NULLs allowed,
   non-null combinations are unique.

### Verification

After migration, all 37 application tables were verified to have matching row
counts between SQLite and MySQL (1,481 total rows). Foreign key integrity,
soft deletes, encrypted credentials, JSON data, financial totals, and
auto-increment counters were all validated.
