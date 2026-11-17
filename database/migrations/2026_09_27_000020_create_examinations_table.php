<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);                            // e.g. "Mid Term Exam 2026"
            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnDelete();
            $table->foreignId('term_id')
                ->constrained('terms')
                ->cascadeOnDelete();
            $table->enum('status', ['draft', 'published', 'closed'])
                ->default('draft')->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examinations');
    }
};
