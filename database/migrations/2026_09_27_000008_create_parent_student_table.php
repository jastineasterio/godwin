<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-CHILD PARENT PORTAL foundation.
 * One parent user account -> many students (children), so a parent can
 * switch between Child 1 (KG1) and Child 2 (Nursery) without re-login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->enum('relationship_type', [
                'father', 'mother', 'guardian', 'other',
            ])->default('guardian');
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();

            // A parent can only be linked to the same child once
            $table->unique(['parent_id', 'student_id']);
            // Fast "children of parent" lookups (portal child switcher)
            $table->index(['parent_id', 'is_primary_contact']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_student');
    }
};
