<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily attendance (Present / Absent / Late / Excused).
 * One row per student per date — enforced by a unique composite index.
 * Daycare check-in / check-out times support the daycare workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->date('date')->index();
            $table->enum('status', ['present', 'absent', 'late', 'excused'])
                ->default('present')->index();
            $table->foreignId('marked_by')                  // teacher who marked the roll
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->time('check_in_time')->nullable();       // daycare drop-off
            $table->time('check_out_time')->nullable();      // daycare pick-up
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'date']);          // no double-marking
            $table->index(['date', 'status']);               // daily rate calculations
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
