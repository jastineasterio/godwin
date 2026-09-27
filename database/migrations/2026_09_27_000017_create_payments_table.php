<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments against invoices + printable receipts.
 * A payment may be linked to a specific invoice (normal case) or, as a
 * legacy/harbour payment, to a student only (invoice_id NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique(); // e.g. "RCT-2026-000456"
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('method', [
                'cash', 'bank_transfer', 'mobile_money', 'card', 'cheque',
            ])->default('cash')->index();
            $table->string('reference', 100)->nullable();   // transaction / till number
            $table->dateTime('paid_at')->index();
            $table->enum('status', [
                'pending', 'completed', 'failed', 'refunded',
            ])->default('completed')->index();
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
