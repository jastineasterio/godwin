<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices issued to students (Accountant module).
 * `amount_paid` is denormalised for fast dashboards and is kept in sync
 * by the payment service whenever a payment is recorded/voided.
 * balance = total_amount - amount_paid (computed accessor on the model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique(); // e.g. "INV-2026-000123"
            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();
            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();
            $table->foreignId('term_id')
                ->nullable()
                ->constrained('terms')
                ->nullOnDelete();
            $table->date('issue_date')->index();
            $table->date('due_date')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);          // expected revenue
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', [
                'unpaid', 'partial', 'paid', 'overdue', 'void',
            ])->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Financial dashboards: outstanding balances by student/class/status
            $table->index(['student_id', 'status']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
