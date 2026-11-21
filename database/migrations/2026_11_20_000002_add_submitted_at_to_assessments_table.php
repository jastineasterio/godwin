<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'submitted_at')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable()->after('updated_at');
                $table->index(['examination_id', 'status', 'submitted_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessments', 'submitted_at')) {
            Schema::table('assessments', function (Blueprint $table) {
                $table->dropIndex(['examination_id', 'status', 'submitted_at']);
                $table->dropColumn(['submitted_at']);
            });
        }
    }
};
