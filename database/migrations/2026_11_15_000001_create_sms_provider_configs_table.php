<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // Display name for the provider config
            $table->string('provider_type');               // 'darsms', 'twilio', etc.
            $table->string('base_url')->nullable();        // API base URL
            $table->string('auth_header_name')->default('X-API-Key'); // Auth header name
            $table->text('api_key_encrypted')->nullable(); // Encrypted API key
            $table->text('api_secret_encrypted')->nullable(); // Encrypted API secret (optional)
            $table->string('username')->nullable();        // For future providers
            $table->text('password_encrypted')->nullable(); // For future providers
            $table->string('sender_id')->nullable();       // Default sender ID
            $table->boolean('is_active')->default(false);  // Only one active at a time
            $table->integer('active_slot')->nullable();    // DB-level single-active enforcement (1 when active, NULL otherwise)
            $table->integer('timeout_seconds')->default(30);
            $table->json('capabilities')->nullable();      // Capability flags
            $table->json('endpoint_config')->nullable();   // Endpoint configuration
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result')->nullable(); // success/failed/reason
            $table->text('last_test_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_type');
            $table->index('is_active');
            $table->unique('active_slot'); // Multiple NULLs allowed; only one non-null active slot
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_provider_configs');
    }
};
