<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_message_id')->constrained('sms_messages')->onDelete('cascade');
            $table->string('status');                      // queued, sent, delivered, failed
            $table->string('provider_status')->nullable(); // QUEUED, SENT, DELIVERED, FAILED
            $table->timestamp('occurred_at');
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();          // Raw provider response for this event
            $table->timestamps();

            $table->index('sms_message_id');
            $table->index('status');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_events');
    }
};
