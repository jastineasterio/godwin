<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('provider_type');               // 'darsms', etc.
            $table->string('provider_name');               // 'DARSMS', etc.
            $table->string('provider_purchase_id')->nullable(); // Provider's purchase ID
            $table->string('transaction_ref')->nullable(); // Transaction reference
            $table->integer('quantity_sms');               // Number of SMS credits purchased
            $table->decimal('amount', 12, 2)->nullable();  // Amount charged
            $table->decimal('unit_price', 12, 4)->nullable(); // Price per SMS
            $table->string('provider_status')->default('PENDING'); // PENDING, SUCCESS, FAILED, CANCELLED
            $table->timestamp('expires_at')->nullable();
            $table->text('efd_receipt')->nullable();       // EFD receipt from provider
            $table->string('payer_phone')->nullable();     // Phone used for payment
            $table->json('raw_response')->nullable();      // Raw provider response
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('provider_type');
            $table->index('provider_status');
            $table->index('transaction_ref');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_purchases');
    }
};
