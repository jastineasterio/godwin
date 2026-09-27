<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value system settings used by the Admin CMS
 * (school profile, admission status, contact info, feature flags, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();           // e.g. "school.name", "admissions.open"
            $table->text('value')->nullable();          // stored value (string / JSON encoded)
            $table->string('group', 50)->default('general')->index(); // general | admissions | contact | branding
            $table->string('type', 20)->default('string'); // string | boolean | number | json
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
