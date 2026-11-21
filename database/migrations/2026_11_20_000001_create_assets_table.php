<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);                            // e.g. "Office Chair", "Desktop Computer"
            $table->string('category', 60);                         // furniture, electronics, teaching, sports, kitchen, other
            $table->string('asset_code', 60)->unique()->nullable(); // inventory / barcode number
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);        // number of units
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();   // cost at purchase
            $table->string('supplier', 150)->nullable();
            $table->string('location', 150)->nullable();            // room / class / department
            $table->enum('condition', ['new', 'good', 'fair', 'damaged', 'missing'])
                ->default('good')->index();
            $table->enum('status', ['active', 'in_use', 'maintenance', 'disposed', 'missing'])
                ->default('active')->index();
            $table->string('image_path')->nullable();               // supporting photo / document
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status', 'condition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
