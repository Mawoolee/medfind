<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->onDelete('cascade');
            $table->foreignId('medicine_id')->constrained()->onDelete('cascade');
            $table->string('dosage')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->decimal('price', 8, 2)->default(0.00);
            $table->enum('status', ['in_stock', 'low_stock', 'out_of_stock'])->default('out_of_stock');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
