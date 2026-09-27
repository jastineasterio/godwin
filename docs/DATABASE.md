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

## Switching local dev from SQLite → MySQL 8.0

This machine's MySQL listens on **port 3307** (see `C:\ProgramData\MySQL\MySQL Server 8.0\my.ini`).

```powershell
# 1. Create the database (replace the password prompt as needed)
mysql -u root -P 3307 -h 127.0.0.1 -p -e "CREATE DATABASE godwin_sms CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

# 2. Edit .env  →  copy the block from .env.example (already MySQL-ready):
#    DB_CONNECTION=mysql
#    DB_PORT=3307
#    DB_DATABASE=godwin_sms
#    DB_USERNAME=root
#    DB_PASSWORD=<your-password>

# 3. Migrate & verify
php artisan migrate
php database/verify_schema.php
```

## Verification

```powershell
php artisan migrate:fresh --force   # 30 migrations
php database/verify_schema.php      # 44 relationship/integrity assertions
vendor\bin\pint --test app database # code style
```

The verification script builds a realistic graph inside a DB transaction
(every role, two children under one parent, attendance, invoices, payments,
messaging, CMS) and **rolls it back**, so it is safe to run repeatedly.
