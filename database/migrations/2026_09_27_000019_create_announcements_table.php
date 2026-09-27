<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements & News — dual purpose:
 *  • Published rows power the PUBLIC website news/events feed.
 *  • `audience` scopes rows inside the authenticated dashboards
 *    (parents / staff / teachers) for targeted broadcasts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('type', [
                'news', 'event', 'announcement', 'admission',
            ])->default('announcement')->index();
            $table->enum('audience', [
                'everyone', 'parents', 'staff', 'teachers',
            ])->default('everyone')->index();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->text('body');
            $table->string('cover_image')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
