<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items on an invoice (one row per fee type: tuition, feeding, transport...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();
            $table->foreignId('fee_structure_id')
                ->nullable()
                ->constrained('fee_structures')
                ->nullOnDelete();
            $table->string('description', 150);             // snapshot of the fee name
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_amount', 10, 2);
            $table->decimal('amount', 12, 2);               // quantity * unit_amount
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
