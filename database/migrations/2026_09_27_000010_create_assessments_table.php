<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessment / exam marks. For Nursery & Daycare the same table stores
 * developmental milestones (type = milestone) alongside homework/tests/exams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->nullable()
                ->constrained('subjects')
                ->nullOnDelete();
            $table->foreignId('term_id')
                ->nullable()
                ->constrained('terms')
                ->nullOnDelete();
            $table->string('title', 150);                    // e.g. "Week 4 Phonics Test"
            $table->enum('type', [
                'homework', 'test', 'exam', 'project', 'participation', 'milestone',
            ])->default('test')->index();
            $table->decimal('score', 8, 2);                  // marks obtained
            $table->decimal('max_score', 8, 2)->default(100); // total marks
            $table->text('remarks')->nullable();             // teacher / pastoral remarks
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('obtained_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Dashboard/report queries: "all assessments for a class in a term"
            $table->index(['class_id', 'term_id', 'obtained_at']);
            $table->index(['student_id', 'obtained_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
