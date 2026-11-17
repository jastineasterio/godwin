<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')
                ->constrained('examinations')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();
            $table->decimal('max_score', 8, 2);                     // max marks for this subject in this exam
            $table->timestamps();

            $table->unique(['examination_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_subjects');
    }
};
