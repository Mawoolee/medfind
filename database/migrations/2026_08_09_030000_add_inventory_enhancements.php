<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('price');
            }
            if (!Schema::hasColumn('inventory_items', 'batch_number')) {
                $table->string('batch_number')->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('inventory_items', 'cold_chain')) {
                $table->boolean('cold_chain')->default(false)->after('batch_number');
            }
            if (!Schema::hasColumn('inventory_items', 'par_level')) {
                $table->integer('par_level')->default(0)->after('cold_chain');
            }
            if (!Schema::hasColumn('inventory_items', 'supplier_id')) {
                // Add supplier_id as nullable unsignedBigInteger. FK constraint managed by separate migration if desired.
                $table->unsignedBigInteger('supplier_id')->nullable()->after('par_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
            $columns = ['par_level','cold_chain','batch_number','expiry_date'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('inventory_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
