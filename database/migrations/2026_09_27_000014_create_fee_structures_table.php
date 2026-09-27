<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee structures (Accountant module): Tuition, Transport, Feeding, Daycare...
 * `class_id` NULL = applies to every class; otherwise scoped to one class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                    // e.g. "Termly Tuition"
            $table->enum('type', [
                'tuition', 'transport', 'feeding', 'daycare', 'uniform', 'exam', 'other',
            ])->default('tuition')->index();
            $table->foreignId('class_id')                   // NULL = all classes
                ->nullable()
                ->constrained('classes')
                ->nullOnDelete();
            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();
            $table->decimal('amount', 10, 2);               // e.g. 250000.00 TZS
            $table->enum('frequency', [
                'one_time', 'monthly', 'termly', 'yearly',
            ])->default('termly');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
