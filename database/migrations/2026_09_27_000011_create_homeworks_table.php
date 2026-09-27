<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homework & class activities posted by teachers (visible to parents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->nullable()
                ->constrained('subjects')
                ->nullOnDelete();
            $table->foreignId('teacher_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('type', ['homework', 'activity'])->default('homework');
            $table->string('title', 150);
            $table->text('description');
            $table->date('assigned_on')->index();
            $table->date('due_on')->nullable()->index();
            $table->json('attachments')->nullable();         // file paths (relative to storage)
            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('published')->index();
            $table->timestamps();

            $table->index(['class_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeworks');
    }
};
