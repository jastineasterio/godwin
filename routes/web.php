<?php

use App\Http\Controllers\Academics\AssessmentController;
use App\Http\Controllers\Academics\ReportCardController;
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

    /* ---- 3. Assessments & marks (Teacher, HoS, Admin) -------------- */
    Route::middleware('role:teacher,head_of_school,admin')->group(function () {
        Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/{assessment}/edit', [AssessmentController::class, 'edit'])->name('assessments.edit');
        Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
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

    /* ---- 7. Direct messaging (every signed-in role) ----------------- */
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'start'])->name('messages.start');
    Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
});
