<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('sms_campaigns')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('to');                          // Recipient phone number
            $table->text('message');                       // SMS message body
            $table->string('category')->default('general');
            $table->string('sender_id')->nullable();
            $table->string('provider_message_id')->nullable(); // Provider's message ID
            $table->string('batch_id')->nullable();        // Batch ID for bulk sends
            $table->string('provider_type');               // 'darsms', etc.
            $table->string('provider_name');               // 'DARSMS', etc.
            $table->string('provider_status')->nullable(); // QUEUED, SENT, DELIVERED, FAILED
            $table->string('status')->default('queued');   // queued, sent, delivered, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key')->nullable(); // Idempotency key for duplicate prevention
            $table->json('metadata')->nullable();          // Additional data (student_id, parent_id, etc.)
            $table->timestamps();
            $table->softDeletes();

            $table->index('campaign_id');
            $table->index('user_id');
            $table->index('to');
            $table->index('status');
            $table->index('provider_type');
            $table->index('provider_message_id');
            $table->unique('idempotency_key'); // Multiple NULLs allowed; prevents duplicate non-null keys
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
