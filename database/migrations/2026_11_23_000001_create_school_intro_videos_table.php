<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_intro_videos', function (Blueprint $table) {
            $table->id();
            $table->string('path');                 // relative path on the public disk
            $table->string('original_name');        // original file name
            $table->string('mime_type');            // validated MIME type
            $table->unsignedBigInteger('file_size'); // bytes
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_intro_videos');
    }
};
