<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students are PURE DATABASE RECORDS (entities) — they never receive
 * login credentials. They link to parents/guardians via `parent_student`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('reg_no', 30)->unique();       // e.g. "GWD-2026-0001"
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('other_name', 100)->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('dob')->nullable();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->restrictOnDelete();                     // never orphan a class roster
            $table->string('photo_path')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->text('medical_notes')->nullable();    // allergies / special needs
            $table->date('admission_date')->nullable();
            $table->string('previous_school')->nullable();
            $table->enum('status', [
                'active', 'transferred', 'graduated', 'withdrawn', 'inactive',
            ])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Frequently filtered combinations (class roster views, status dashboards)
            $table->index(['class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
