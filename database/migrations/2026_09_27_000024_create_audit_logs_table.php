<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System-wide audit log (Administrator module): who did what, when, from where.
 * `auditable_*` polymorphically points at the affected record
 * (a student, invoice, user, settings row, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')                    // NULL = system action
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 60)->index();          // e.g. "login", "student.updated"
            $table->string('description')->nullable();
            $table->nullableMorphs('auditable');            // auditable_type + auditable_id
            $table->json('properties')->nullable();         // ["before" => ..., "after" => ...]
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();  // audit rows are immutable

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
