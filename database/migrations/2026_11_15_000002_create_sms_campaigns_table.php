<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');                        // Campaign name
            $table->text('message');                       // SMS message body
            $table->string('category')->default('bulk');   // announcements, notices, fee_reminders, etc.
            $table->string('sender_id')->nullable();       // Sender ID used
            $table->string('provider_type');               // 'darsms', etc.
            $table->string('provider_name');               // 'DARSMS', etc.
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('status')->default('pending');  // pending, queued, sending, completed, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
            $table->index('provider_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_campaigns');
    }
};
