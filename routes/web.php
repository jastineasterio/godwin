<?php

use App\Http\Controllers\Academics\AssessmentController;
use App\Http\Controllers\Academics\ExaminationController;
use App\Http\Controllers\Academics\ReportCardController;
use App\Http\Controllers\Academics\TeacherResultController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\Sms\SmsCreditController;
use App\Http\Controllers\Admin\Sms\SmsProviderController;
use App\Http\Controllers\HeadOfSchool\Sms\SmsBulkController;
use App\Http\Controllers\HeadOfSchool\Sms\SmsDashboardController;
use App\Http\Controllers\HeadOfSchool\Sms\SmsHistoryController;
use App\Http\Controllers\HeadOfSchool\Sms\SmsResultsController;
use App\Http\Controllers\HeadOfSchool\Sms\SmsTemplateController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Comms\AnnouncementController;
use App\Http\Controllers\Comms\MessageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Finance\FeeStatementController;
use App\Http\Controllers\Finance\FeeStructureController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Homework\HomeworkController;
use App\Http\Controllers\MyClasses\MyClassesController;
use App\Http\Controllers\Results\ResultsController;
use App\Http\Controllers\Students\StudentController;
use Illuminate\Support\Facades\Route;

/* ============ PUBLIC WEBSITE + AUTHENTICATION ========================= */

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/apply', [ApplicationController::class, 'create'])->name('apply.create');
Route::post('/apply', [ApplicationController::class, 'store'])->name('apply.store');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

/* ============ AUTHENTICATED AREA — PHASE 3 MODULES ==================== */

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /* ---- 1. Students & parent linking (Admin, Head of School) ------ */
    Route::middleware('role:admin,head_of_school')->group(function () {
        Route::resource('students', StudentController::class);
        Route::post('students/{student}/parents', [StudentController::class, 'linkParent'])
            ->name('students.parents.link');
        Route::delete('students/{student}/parents/{parent}', [StudentController::class, 'unlinkParent'])
            ->name('students.parents.unlink');
    });

    /* ---- 2. Attendance marking (Teacher, HoS, Admin) --------------- */
    Route::middleware('role:teacher,head_of_school,admin')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    });

    /* ---- 3. Assessments & marks (Teacher records marks for own classes) -- */
    Route::middleware('role:teacher,head_of_school,admin')->group(function () {
        Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/{assessment}/edit', [AssessmentController::class, 'edit'])->name('assessments.edit');
        Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
    });

    /* ---- Examination management (Head of School + Admin only) ------------ */
    Route::middleware('role:head_of_school,admin')->group(function () {
        Route::get('/examinations', [ExaminationController::class, 'index'])->name('examinations.index');
        Route::get('/examinations/create', [ExaminationController::class, 'create'])->name('examinations.create');
        Route::post('/examinations', [ExaminationController::class, 'store'])->name('examinations.store');
        Route::get('/examinations/{examination}/edit', [ExaminationController::class, 'edit'])->name('examinations.edit');
        Route::put('/examinations/{examination}', [ExaminationController::class, 'update'])->name('examinations.update');
        Route::delete('/examinations/{examination}', [ExaminationController::class, 'destroy'])->name('examinations.destroy');
        Route::get('/examinations/{examination}/progress', [ExaminationController::class, 'progress'])->name('examinations.progress');
        Route::post('/examinations/{examination}/approve', [ExaminationController::class, 'approve'])->name('examinations.approve');
        Route::post('/examinations/{examination}/return', [ExaminationController::class, 'returnResults'])->name('examinations.return');
        Route::post('/examinations/{examination}/publish', [ExaminationController::class, 'publish'])->name('examinations.publish');
        Route::post('/examinations/{examination}/close', [ExaminationController::class, 'close'])->name('examinations.close');
    });

    /* ---- Teacher result entry (examinations assigned to teacher's classes) */
    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/examinations', [TeacherResultController::class, 'index'])->name('teacher.examinations');
        Route::get('/teacher/examinations/{examination}/results', [TeacherResultController::class, 'results'])->name('teacher.results');
        Route::put('/teacher/examinations/{examination}/results', [TeacherResultController::class, 'saveResults'])->name('teacher.results.save');
        Route::post('/teacher/examinations/{examination}/results/submit', [TeacherResultController::class, 'submitResults'])->name('teacher.results.submit');
    });

    /* ---- Homework (Teacher creates for own classes; HoS/Admin view all) -- */
    Route::middleware('role:teacher,head_of_school,admin')->group(function () {
        Route::get('/homework', [HomeworkController::class, 'index'])->name('homework.index');
        Route::post('/homework', [HomeworkController::class, 'store'])->name('homework.store');
        Route::put('/homework/{homework}', [HomeworkController::class, 'update'])->name('homework.update');
        Route::delete('/homework/{homework}', [HomeworkController::class, 'destroy'])->name('homework.destroy');
    });

    /* ---- My Classes (Teacher views assigned classes) --------------------- */
    Route::middleware('role:teacher')->group(function () {
        Route::get('/my-classes', [MyClassesController::class, 'index'])->name('my-classes.index');
        Route::get('/my-classes/students', [MyClassesController::class, 'students'])->name('my-classes.students');
        Route::get('/my-classes/{class}', [MyClassesController::class, 'show'])->name('my-classes.show');
    });

    /* ---- Results & Analytics (Teacher) ----------------------------------- */
    Route::middleware('role:teacher')->group(function () {
        Route::get('/results-analytics', [ResultsController::class, 'index'])->name('results-analytics.index');
    });

    /* ---- 4. Printable outputs (report card + fee statement) -------- */
    Route::middleware('role:admin,head_of_school,teacher,accountant,parent')->group(function () {
        Route::get('/students/{student}/report-card', ReportCardController::class)
            ->name('students.report-card');
        Route::get('/students/{student}/fee-statement', FeeStatementController::class)
            ->name('students.fee-statement');
    });

    /* ---- 5. Finance & invoicing (Accountant, Admin, HoS) ----------- */
    Route::middleware('role:accountant,admin,head_of_school')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])
            ->name('payments.store');
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->name('payments.refund');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])
            ->name('payments.receipt');

        Route::resource('fee-structures', FeeStructureController::class)->except(['show']);
    });

    /* ---- 6. Announcements & broadcasts ------------------------------ */
    Route::middleware('role:admin,head_of_school,senior_pastor')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::post('/announcements/{announcement}/toggle-publish', [AnnouncementController::class, 'togglePublish'])
            ->name('announcements.toggle');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
            ->name('announcements.destroy');
    });

    /* ---- 7. Home page poster management (Administrator only) ---------- */
    Route::middleware('role:admin')->group(function () {
        Route::get('/banners', [App\Http\Controllers\BannerController::class, 'index'])->name('banners.index');
        Route::post('/banners', [App\Http\Controllers\BannerController::class, 'store'])->name('banners.store');
        Route::delete('/banners/{banner}', [App\Http\Controllers\BannerController::class, 'destroy'])->name('banners.destroy');
    });

    /* ---- 9. Staff & Users management (Administrator only) ------------- */
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
        Route::patch('/users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password');
    });

    /* ---- 10. Settings (Administrator only) ---------------------------- */
    Route::middleware('role:admin')->prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/{group}', [SettingsController::class, 'update'])->name('settings.update');
    });

    /* ---- 11. Audit Logs (Administrator only) -------------------------- */
    Route::middleware('role:admin')->prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });

    /* ---- 8. Direct messaging (every signed-in role) ----------------- */
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'start'])->name('messages.start');
    Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');

    /* ---- 12. Timetable (view: staff + parents; manage: admin, HoS) -- */
    Route::middleware('role:admin,head_of_school,teacher,parent')->group(function () {
        Route::get('/timetable', [App\Http\Controllers\TimetableController::class, 'index'])->name('timetable.index');
    });

    Route::middleware('role:admin,head_of_school')->group(function () {
        Route::get('/timetable/create', [App\Http\Controllers\TimetableController::class, 'create'])->name('timetable.create');
        Route::post('/timetable', [App\Http\Controllers\TimetableController::class, 'store'])->name('timetable.store');
        Route::get('/timetable/{timetable}/edit', [App\Http\Controllers\TimetableController::class, 'edit'])->name('timetable.edit');
        Route::put('/timetable/{timetable}', [App\Http\Controllers\TimetableController::class, 'update'])->name('timetable.update');
        Route::delete('/timetable/{timetable}', [App\Http\Controllers\TimetableController::class, 'destroy'])->name('timetable.destroy');
    });

    /* ---- 13. Events (view: all authenticated; manage: admin, HoS, senior pastor) */
    Route::middleware('auth')->group(function () {
        Route::get('/events', [App\Http\Controllers\SchoolEventController::class, 'index'])->name('events.index');
    });

    Route::middleware('role:admin,head_of_school,senior_pastor')->group(function () {
        Route::get('/events/create', [App\Http\Controllers\SchoolEventController::class, 'create'])->name('events.create');
        Route::post('/events', [App\Http\Controllers\SchoolEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [App\Http\Controllers\SchoolEventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [App\Http\Controllers\SchoolEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [App\Http\Controllers\SchoolEventController::class, 'destroy'])->name('events.destroy');
    });

    /* ---- SMS: Admin technical configuration (Admin only) ----------- */
    Route::middleware('role:admin')->prefix('sms/admin')->group(function () {
        Route::get('/providers', [SmsProviderController::class, 'index'])->name('sms.admin.providers');
        Route::post('/providers', [SmsProviderController::class, 'store'])->name('sms.admin.providers.store');
        Route::put('/providers/{config}', [SmsProviderController::class, 'update'])->name('sms.admin.providers.update');
        Route::patch('/providers/{config}/status', [SmsProviderController::class, 'toggleStatus'])->name('sms.admin.providers.status');
        Route::delete('/providers/{config}', [SmsProviderController::class, 'destroy'])->name('sms.admin.providers.destroy');
        Route::post('/providers/test-connection', [SmsProviderController::class, 'testConnection'])->name('sms.admin.test-connection');
        Route::get('/providers/sender-ids', [SmsProviderController::class, 'senderIds'])->name('sms.admin.sender-ids');
        Route::get('/providers/balance', [SmsProviderController::class, 'balance'])->name('sms.admin.balance');

        Route::get('/credits', [SmsCreditController::class, 'index'])->name('sms.admin.credits');
        Route::post('/credits/purchase', [SmsCreditController::class, 'purchase'])->name('sms.admin.credits.purchase');
        Route::get('/credits/history', [SmsCreditController::class, 'history'])->name('sms.admin.credits.history');
        Route::post('/credits/{purchase}/retry', [SmsCreditController::class, 'retry'])->name('sms.admin.credits.retry');
    });

    /* ---- SMS: Head of School dashboard (HoS + Admin) --------------- */
    Route::middleware('role:head_of_school,admin')->prefix('sms')->group(function () {
        Route::get('/dashboard', [SmsDashboardController::class, 'index'])->name('sms.dashboard');

        Route::get('/bulk', [SmsBulkController::class, 'index'])->name('sms.bulk');
        Route::post('/bulk/preview-recipients', [SmsBulkController::class, 'previewRecipients'])->name('sms.bulk.preview');
        Route::post('/bulk/send', [SmsBulkController::class, 'send'])->name('sms.bulk.send');

        Route::get('/templates', [SmsTemplateController::class, 'index'])->name('sms.templates');
        Route::post('/templates', [SmsTemplateController::class, 'store'])->name('sms.templates.store');
        Route::put('/templates/{template}', [SmsTemplateController::class, 'update'])->name('sms.templates.update');
        Route::patch('/templates/{template}/toggle', [SmsTemplateController::class, 'toggle'])->name('sms.templates.toggle');
        Route::delete('/templates/{template}', [SmsTemplateController::class, 'destroy'])->name('sms.templates.destroy');
        Route::post('/templates/preview', [SmsTemplateController::class, 'preview'])->name('sms.templates.preview');

        Route::get('/history', [SmsHistoryController::class, 'index'])->name('sms.history');
        Route::get('/history/{message}', [SmsHistoryController::class, 'show'])->name('sms.history.show');
        Route::post('/history/{message}/resend', [SmsHistoryController::class, 'resend'])->name('sms.history.resend');

        Route::get('/results', [SmsResultsController::class, 'index'])->name('sms.results');
        Route::get('/results/academic-years', [SmsResultsController::class, 'academicYears'])->name('sms.results.academic-years');
        Route::post('/results/preview', [SmsResultsController::class, 'preview'])->name('sms.results.preview');
        Route::post('/results/send', [SmsResultsController::class, 'send'])->name('sms.results.send');
        Route::get('/results/history', [SmsResultsController::class, 'history'])->name('sms.results.history');
    });
});
