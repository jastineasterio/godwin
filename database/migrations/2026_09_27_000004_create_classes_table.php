<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School classes / rooms (Daycare, Nursery, KG1, KG2, ...).
 * `teacher_id` is the class teacher (a users row with role = teacher).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                       // e.g. "KG1 - Sunshine"
            $table->string('code', 20)->unique();              // e.g. "KG1-A"
            $table->enum('level', [                            // academic level grouping
                'daycare', 'nursery', 'kg1', 'kg2', 'primary',
            ])->default('nursery')->index();
            $table->foreignId('teacher_id')                    // class teacher (nullable at first)
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedTinyInteger('capacity')->default(30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
