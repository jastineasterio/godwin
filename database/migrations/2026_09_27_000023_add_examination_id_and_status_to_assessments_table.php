<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('examination_id')
                ->nullable()
                ->after('term_id')
                ->constrained('examinations')
                ->nullOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'returned'])
                ->default('draft')
                ->after('examination_id')
                ->index();
        });

        // One result per student per examination per subject
        Schema::table('assessments', function (Blueprint $table) {
            $table->unique(['student_id', 'examination_id', 'subject_id'])
                ->whereNotNull('examination_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique('assessments_student_id_examination_id_subject_id_unique');
            $table->dropForeign(['examination_id']);
            $table->dropColumn(['examination_id', 'status']);
        });
    }
};
