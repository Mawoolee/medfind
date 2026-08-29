<?php

namespace Database\Seeders\Concerns;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;

trait SeedsBatchInventory
{
    /** @param array<string, mixed> $batchAttributes */
    private function seedBatchInventory(
        Pharmacy $pharmacy,
        Medicine $medicine,
        int $quantity,
        int|float|string $price,
        array $batchAttributes = []
    ): InventoryBatch {
        $aggregate = InventoryItem::firstOrCreate(
            ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
            ['stockQuantity' => 0, 'price' => $price, 'status' => 'out_of_stock', 'par_level' => 0]
        );
        $batchNumber = (string) ($batchAttributes['batch_number'] ?? 'SEED-'.$pharmacy->id.'-'.$medicine->id);
        $lotNumber = $batchAttributes['lot_number'] ?? null;
        $identityKey = BatchIdentity::key($batchNumber, $lotNumber);
        $batch = InventoryBatch::updateOrCreate(
            ['inventory_item_id' => $aggregate->id, 'identity_key' => $identityKey],
            [
                'batch_number' => $batchNumber,
                'lot_number' => $lotNumber,
                'quantity_received' => $quantity,
                'current_quantity' => $quantity,
                'price' => $price,
                'supplier_id' => $batchAttributes['supplier_id'] ?? null,
                'supplier_name' => $batchAttributes['supplier_name'] ?? null,
                'expiry_date' => $batchAttributes['expiry_date'] ?? null,
                'cold_chain' => (bool) ($batchAttributes['cold_chain'] ?? false),
                'received_date' => $batchAttributes['received_date'] ?? now()->toDateString(),
                'received_reference' => $batchAttributes['received_reference'] ?? 'seed:'.$identityKey,
                'created_by' => $batchAttributes['created_by'] ?? null,
            ]
        );
        $operationId = 'seed-receipt:'.hash('sha256', $aggregate->id.'|'.$identityKey);
        StockMovement::query()->firstOrCreate(
            ['operation_id' => $operationId, 'inventory_batch_id' => $batch->id, 'type' => 'receipt'],
            [
                'inventory_item_id' => $aggregate->id,
                'before_quantity' => 0,
                'after_quantity' => $quantity,
                'quantity_delta' => $quantity,
                'reason' => 'Seeder receipt',
                'received_reference' => $batch->received_reference,
                'user_id' => $batch->created_by,
            ]
        );

        $available = (int) $aggregate->batches()->available()->sum('current_quantity');
        $aggregate->forceFill([
            'stockQuantity' => $available,
            'price' => $batch->price,
            'status' => $available === 0 ? 'out_of_stock' : (($aggregate->par_level > 0 && $available <= $aggregate->par_level) ? 'low_stock' : 'available'),
            'batch_number' => $batch->batch_number,
            'lot_number' => $batch->lot_number,
            'expiry_date' => $batch->expiry_date,
            'cold_chain' => $batch->cold_chain,
            'supplier_id' => $batch->supplier_id,
        ])->save();

        return $batch;
    }
}
