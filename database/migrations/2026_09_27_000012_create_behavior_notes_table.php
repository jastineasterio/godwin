<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Character / behaviour & spiritual development notes.
 * Feeds the Senior Pastor's "Moral & Spiritual Character Development"
 * reports and the parent portal's behaviour feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('noted_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('type', [
                'behavior',      // general conduct
                'spiritual',     // moral / spiritual growth
                'character',     // character-building milestones
                'achievement',   // positive recognition
                'health',        // health & wellbeing observation
            ])->default('behavior')->index();
            $table->string('title', 150);
            $table->text('note');
            $table->boolean('is_positive')->default(true)->index();
            $table->date('occurred_on')->index();
            $table->timestamps();

            $table->index(['student_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_notes');
    }
};
