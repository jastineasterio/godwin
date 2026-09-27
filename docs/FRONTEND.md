# Phase 3 — Functional Modules, Workflows & Printable Output

> ✅ 41/41 tests pass (261 assertions) · ✅ Pint clean (124 files) · ✅ 49 routes

Phase 3 turns every Phase-2 "Soon" placeholder into a working module with
server-side validation, RBAC, audit trail and print-ready output.

## 1. Module map

| # | Module | Routes | Roles |
|---|---|---|---|
| 1 | **Student Management & Parent Linking** | `/students` (full CRUD) | Admin, Head of School |
| 2 | **Attendance Marking** | `/attendance` | Teacher, HoS, Admin |
| 3 | **Assessments & Progress Reports** | `/assessments`, `/students/{id}/report-card` | Teacher, HoS, Admin + parents (read) |
| 4 | **Finance & Invoicing** | `/invoices`, `/invoices/create`, `/payments/{id}/receipt`, `/fee-structures`, `/students/{id}/fee-statement` | Accountant, Admin, HoS |
| 5 | **Announcements & Messaging** | `/announcements`, `/messages` | Broadcast: Admin/HoS/Senior Pastor · Messages: everyone |

## 2. Business rules enforced server-side

**Students & parents**
* Registration numbers are unique and auto-generated (`GWD-2026-0001`).
* Registering a child can create **and link a parent account in one step**
  (re-uses an existing parent with the same phone number).
* Only **one primary contact** per child — linking a new primary demotes the old.
* Teachers have **no** access to student management (403 → their dashboard).
* Deleting is a soft delete; the audit log keeps a before/after snapshot.

**Attendance**
* Marking is an **upsert keyed on (student_id, date)** — the unique index is the
  real duplicate guard, so saving twice is harmless.
* Teachers may only mark **their own classes** (403 otherwise).
* A register containing students from another class is rejected.

**Assessments**
* `score` can never exceed `max_score` (custom validation message).
* Teachers only see/record assessments for their own classes.
* Report cards render attendance summary, subject averages, class position,
  grade band and character remarks.

**Finance**
* Invoices are generated from the student's **applicable fee structures**
  (their class fees + universal fees) and saved atomically with line items.
* Payments can never exceed the outstanding balance; the invoice status moves
  `unpaid → partial → paid` automatically and a receipt number is issued.
* Refunds reverse the balance and reopen the invoice.
* An invoice with payments **cannot be voided** (422) — financial records are kept.
* Fee structures referenced by past invoices are deactivated, not deleted.

**Comms**
* Publishing an announcement **notifies the chosen audience** (database notification).
* Messaging is scoped: a parent may only message the teachers of their own
  children; a teacher only the parents of their own students; leadership anyone.
* Non-participants get 403 on any thread.

## 3. Printable documents (server-rendered Blade, no PDF dependency)

| Document | Path |
|---|---|
| Student Progress Report / Report Card | `resources/views/print/report-card.blade.php` |
| Payment Receipt | `resources/views/print/receipt.blade.php` |
| Fee Statement | `resources/views/print/fee-statement.blade.php` |

All three are A4 print-styled (hidden toolbar + `window.print()`), so parents
can "Print / Save as PDF" straight from the browser on any device.

## 4. Frontend additions

```
resources/js/Components/     form.jsx · modal.jsx · page.jsx · StudentForm.jsx
resources/js/Pages/
  Students/     Index · Create · Edit · Show
  Attendance/   Index            (quick-toggle register, sticky save bar)
  Assessments/  Index · Create   (create + edit, live % calculator)
  Finance/      Invoices · InvoiceCreate · InvoiceShow · FeeStructures
  Comms/        Announcements · Messages
```

Every screen is mobile-first: tables collapse to stacked cards below `sm`,
modals become bottom sheets, the attendance register uses large tap targets and
a sticky save bar.

## 5. Running it

```bash
composer install
npm install
php artisan migrate:fresh --seed     # demo data for all six roles
npm run build                         # requires ~2 GB free RAM
php artisan serve
```

### Demo accounts (all use the password `password`)

| Role | Email |
|---|---|
| Administrator | `admin@godwin.ac.tz` |
| Senior Pastor | `pastor@godwin.ac.tz` |
| Head of School | `head@godwin.ac.tz` |
| Teacher | `teacherNeema@godwin.ac.tz` (also Peter, Ruth) |
| Accountant | `accounts@godwin.ac.tz` |
| Parent | `parentJuma@example.com` (also Moshi, Kileo) |

> ⚠️ **Build note.** `npm run build` needs roughly 2 GB of free memory. On a
> machine that is memory-starved the Rollup transform can stall indefinitely —
> close Chrome/IDE windows and re-run, or use `npm run dev` (Vite dev server)
> during development. The application is written to degrade gracefully: if
> `public/build/manifest.json` is absent, the root view renders the
> server-rendered page plus a gold banner telling the operator to build,
> instead of throwing a 500.

> ⚠️ **Seed note.** The demo dataset writes ~1,400 rows. Attendance and
> assessments are bulk-inserted and the demo password is hashed once, so
> seeding is fast on normal hardware. On a heavily CPU-starved machine every
> Eloquent `create()` can cost ~1 s, which makes the run take minutes — that
> is a property of the host, not the seeder.

## 6. Verification

```bash
php artisan test        # 41 passed (261 assertions)
vendor/bin/pint --test  # 124 files clean
node scripts/check-imports.js   # 33 files, all relative imports resolve
npx esbuild resources/js/**/*.jsx --loader:.jsx=jsx --outdir=/tmp/check
```


> Inertia.js + React 19 + Tailwind CSS 3 + Recharts + Lucide + Framer Motion.
> Status: ✅ 9 pages compile · ✅ 15/15 feature tests pass · ✅ charts fed by live Laravel queries.

## 1. Stack & entry point

| Concern | Choice |
|---|---|
| Server adapter | `inertiajs/inertia-laravel` v3 |
| Client | `@inertiajs/react` v3 + React 19 |
| Bundler | Vite 5 (`resources/js/app.jsx`, React plugin) |
| Styling | Tailwind 3 (`tailwind.config.js`, `postcss.config.js`) |
| Charts | Recharts 3 (`TrendChart`, `BarChartCard`, `GroupedBarChart`, `DonutChart`, `RadarChartCard`) |
| Icons | `lucide-react` |
| Motion | `framer-motion` (page transitions, stat cards, drawers) |

`app.jsx` resolves pages from `resources/js/Pages/**/*.jsx` with
`resolvePageComponent` → every page is code-split (see `public/build/manifest.json`).

## 2. Brand palette (tailwind.config.js)

| Token | Hex | Used for |
|---|---|---|
| `primary` | `#D81B60` | buttons, badges, sidebar active state, charts |
| `secondary` | `#0288D1` | footer, secondary accents, charts |
| `accent` | `#FBC02D` | admission banners, hero dots, callouts |
| `canvas` | `#F8F9FA` | page background |
| `ink` | `#1E293B` | headings/body |

Also: `.glass-card`, `.stat-card`, `.btn-primary|secondary|accent|ghost`, `.input`,
`.badge`, `.section-title`, `.chart-box` (fixed height → no layout shift while
Recharts boots).

## 3. File map

```
resources/js/
├─ app.jsx                     Inertia bootstrap + progress bar (#D81B60)
├─ Components/
│  ├─ ui.jsx                   BRAND palette, StatCard, Card, Badge, Flash,
│  │                           SectionHeading, EmptyState, ProgressBar
│  └─ charts.jsx               ChartCard shell + 5 Recharts wrappers
├─ Layouts/
│  ├─ PublicLayout.jsx         topbar → sticky glass nav → mobile drawer → footer
│  ├─ DashboardLayout.jsx      collapsible sidebar + header + page transitions
│  └─ navigation.js            role-aware menus (NAV_BY_ROLE) + ROLE_LABELS
└─ Pages/
   ├─ Public/Home.jsx          hero slider, vision/mission/motto, programs,
   │                           news & events, contact + Google Maps
   ├─ Public/Apply.jsx         3-step admission wizard
   ├─ Auth/Login.jsx           staff/parent sign-in
   └─ Dashboards/              Admin · SeniorPastor · HeadOfSchool ·
                               Teacher · Accountant · Parent
```

## 4. Routing & RBAC

| Route | Method | Purpose |
|---|---|---|
| `/` | GET | Public marketing site |
| `/apply` | GET, POST | Multi-step application → `applications` table |
| `/contact` | POST | Contact form → `contact_messages` |
| `/login` `/logout` | GET/POST | Session auth (throttled 10/min) |
| `/dashboard` | GET | Dispatches by `user.role` to one of six controllers |

`DashboardController` maps the role to a dedicated controller
(`Admin`→`AdminDashboardController`, …) so each role gets its **own page and its
own live datasets**. `EnsureRole` middleware (`role:admin,head_of_school`) is
registered as an alias for the module routes coming in Phase 3.

Suspended/inactive accounts are rejected at login **and** at `/dashboard`.

## 5. Live chart data (computed in PHP, passed as Inertia props)

| Role | Props |
|---|---|
| Admin | `charts.enrollmentGrowth` (8-month line), `charts.userActivity` (role bars), `systemHealth`, `recentRegistrations`, `recentLogs`, `recentNews` |
| Senior Pastor | `charts.monthlyAttendance` (6-month bars), `charts.spiritualRadar` (5 NoteType dimensions), `broadcasts` |
| Head of School | `charts.classAttendance` (per-class bars), `classes` (roster + capacity) |
| Teacher | `charts.weeklyTrend` (7-day line), `classes`, `schedule` (today's lessons), `today.marked/total` |
| Accountant | `charts.incomeVsExpense` (grouped bars), `charts.collection` (doughnut), `transactions` |
| Parent | `charts.subjectGrades` (bars), `charts.attendanceDonut`, `children`, `profile`, `invoices` |

## 6. Multi-child parent portal

`ParentDashboardController` reads `?child={id}` (validated against the children
linked through `parent_student`). The React switcher calls
`router.get('/dashboard?child=…', {}, { preserveState: true, preserveScroll: true })`
→ instant switch, **no re-login, no page reload**. A parent with no linked
children gets a friendly empty state (no 403).

## 7. Demo accounts (after `php artisan migrate:fresh --seed`)

| Role | Email | Password |
|---|---|---|
| Administrator | admin@godwin.ac.tz | password |
| Senior Pastor | pastor@godwin.ac.tz | password |
| Head of School | head@godwin.ac.tz | password |
| Teacher | teacherNeema@godwin.ac.tz | password |
| Accountant | accounts@godwin.ac.tz | password |
| Parent (2 children) | parentJuma@example.com | password |

Seed data: 10 users, 4 classes, 5 subjects, 32 students, 1,024 attendance rows,
96 assessments, 32 invoices, 24 payments, 24 expenses, 60 timetable slots,
22 character notes, news + events + banner.

## 8. Run & verify

```bash
npm install
npm run build          # or: npm run dev   (HMR)
php artisan migrate:fresh --seed
php artisan serve      # http://127.0.0.1:8000
php artisan test       # 15 tests / 131 assertions
vendor/bin/pint --test app database routes tests config
```
