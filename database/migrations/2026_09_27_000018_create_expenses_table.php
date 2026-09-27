<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School operating expenses (Accountant module) —
 * feeds "Revenue vs Expense" analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->enum('category', [
                'salaries', 'utilities', 'maintenance', 'supplies',
                'food', 'transport', 'other',
            ])->default('other')->index();
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on')->index();
            $table->string('reference', 100)->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'incurred_on']);     // monthly expense charts
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
