<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('medicine_name');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('lot_number');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('brand_name');
        });
    }
};
