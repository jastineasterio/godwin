<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ONLINE APPLICATION FORM (public website "Apply Now").
 * Prospective parents submit these; the Head of School reviews and,
 * on acceptance, converts the row into a `students` record.
 * NOTE: applicants do NOT get login accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();   // e.g. "APP-2026-0001"

            // --- Applicant (parent/guardian) details ---------------------------
            $table->string('parent_name', 150);
            $table->string('parent_email', 150)->nullable()->index();
            $table->string('parent_phone', 20)->index();
            $table->enum('relationship', [
                'father', 'mother', 'guardian', 'other',
            ])->default('guardian');

            // --- Prospective child details -------------------------------------
            $table->string('child_first_name', 100);
            $table->string('child_last_name', 100);
            $table->enum('gender', ['male', 'female']);
            $table->date('dob')->nullable();
            $table->foreignId('class_id')                   // class applied for
                ->constrained('classes')
                ->restrictOnDelete();

            $table->text('address')->nullable();
            $table->text('message')->nullable();            // extra notes from parent
            $table->enum('status', [
                'submitted', 'under_review', 'accepted', 'rejected', 'enrolled',
            ])->default('submitted')->index();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
