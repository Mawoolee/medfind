<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->boolean('cold_chain_required')->default(false)->after('requiresPrescription');
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('legacy_source_inventory_item_id')->nullable()->unique()->constrained('inventory_items')->cascadeOnDelete();
            $table->string('batch_number');
            $table->string('lot_number')->nullable();
            $table->string('identity_key');
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('current_quantity');
            $table->decimal('price', 10, 2);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('cold_chain')->default(false);
            $table->date('received_date')->index();
            $table->string('received_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'identity_key']);
            $table->index(['inventory_item_id', 'expiry_date', 'received_date', 'id'], 'inventory_batches_fefo_index');
            $table->index(['inventory_item_id', 'current_quantity']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('operation_id')->index();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->constrained('inventory_batches')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('before_quantity');
            $table->unsignedInteger('after_quantity');
            $table->integer('quantity_delta');
            $table->text('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('received_reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['operation_id', 'inventory_batch_id', 'type'], 'stock_movements_operation_batch_type_unique');
        });

        Schema::table('inventory_audits', function (Blueprint $table) {
            $table->string('operation_id')->nullable()->index();
        });

        Schema::table('controlled_substance_logs', function (Blueprint $table) {
            $table->string('operation_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('controlled_substance_logs', function (Blueprint $table) {
            $table->dropColumn('operation_id');
        });

        Schema::table('inventory_audits', function (Blueprint $table) {
            $table->dropColumn('operation_id');
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_batches');

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('cold_chain_required');
        });
    }
};
