<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')
                ->constrained('examinations')
                ->cascadeOnDelete();
            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['examination_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_classes');
    }
};
