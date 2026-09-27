<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly timetable slots per class (Head of School module,
 * shown on Teacher dashboard + Parent portal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();
            $table->foreignId('teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('day', [
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ])->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 50)->nullable();
            $table->timestamps();

            $table->index(['class_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
