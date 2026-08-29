<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Services\MedicineMasterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MedicineMasterServiceTest extends TestCase
{
    use RefreshDatabase;

    private MedicineMasterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MedicineMasterService::class);
    }

    public function test_it_creates_a_medicine_master_and_zero_stock_aggregate(): void
    {
        $pharmacy = Pharmacy::factory()->create();

        $aggregate = $this->service->createForPharmacy($pharmacy, [
            'medicine_name' => '  Insulin glargine  ',
            'brand_name' => 'Lantus',
            'dosage' => '100 units/mL',
            'category' => 'Insulin',
            'manufacturer' => 'Example Pharma',
            'requiresPrescription' => true,
            'cold_chain_required' => true,
        ], 12);

        $this->assertDatabaseHas('medicines', [
            'id' => $aggregate->medicine_id,
            'medicine_name' => 'Insulin glargine',
            'brand_name' => 'Lantus',
            'dosage' => '100 units/mL',
            'category' => 'Insulin',
            'manufacturer' => 'Example Pharma',
            'requiresPrescription' => true,
            'cold_chain_required' => true,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $aggregate->id,
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $aggregate->medicine_id,
            'stockQuantity' => 0,
            'price' => 0,
            'status' => 'out_of_stock',
            'par_level' => 12,
        ]);
        self::assertTrue($aggregate->medicine->requiresPrescription);
        self::assertTrue($aggregate->medicine->cold_chain_required);
    }

    public function test_it_retains_one_aggregate_when_an_existing_master_is_added_again(): void
    {
        $pharmacy = Pharmacy::factory()->create();
        $medicine = Medicine::factory()->prescription()->create();
        $existing = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 17,
            'price' => 49.95,
            'par_level' => 4,
        ]);

        $aggregate = $this->service->createForPharmacy($pharmacy, [
            'medicine_id' => $medicine->id,
            'medicine_name' => 'Updated generic name',
        ], 9);

        self::assertSame($existing->id, $aggregate->id);
        self::assertSame(1, InventoryItem::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->count());
        self::assertSame(17, (int) $aggregate->stockQuantity);
        self::assertSame('49.95', (string) $aggregate->price);
        self::assertSame(9, (int) $aggregate->par_level);
        self::assertTrue($aggregate->medicine->requiresPrescription);
    }

    public function test_update_changes_only_master_fields_and_par_level(): void
    {
        $medicine = Medicine::factory()->prescription()->coldChainRequired()->create([
            'medicine_name' => 'Original generic',
            'brand_name' => 'Original brand',
        ]);
        $aggregate = InventoryItem::factory()->withBatch()->create([
            'medicine_id' => $medicine->id,
            'stockQuantity' => 23,
            'price' => 18.75,
            'par_level' => 5,
            'batch_number' => 'BATCH-KEEP',
            'lot_number' => 'LOT-KEEP',
        ]);
        $batchBefore = $aggregate->batches()->firstOrFail()->only([
            'id',
            'batch_number',
            'lot_number',
            'quantity_received',
            'current_quantity',
        ]);

        $updated = $this->service->updateForPharmacy($aggregate, [
            'medicine_name' => 'Updated generic',
            'brand_name' => 'Updated brand',
            'dosage' => '20mg',
            'category' => 'Controlled',
            'manufacturer' => 'Updated manufacturer',
        ], 11);

        self::assertSame('Updated generic', $updated->medicine->medicine_name);
        self::assertSame('Updated brand', $updated->medicine->brand_name);
        self::assertTrue($updated->medicine->requiresPrescription);
        self::assertTrue($updated->medicine->cold_chain_required);
        self::assertSame(11, (int) $updated->par_level);
        self::assertSame(23, (int) $updated->stockQuantity);
        self::assertSame('18.75', (string) $updated->price);
        self::assertSame($batchBefore, $updated->batches()->firstOrFail()->only(array_keys($batchBefore)));
    }

    public function test_it_rejects_stock_fields_and_leaves_state_unchanged(): void
    {
        $pharmacy = Pharmacy::factory()->create();

        try {
            $this->service->createForPharmacy($pharmacy, [
                'medicine_name' => 'Paracetamol',
                'stockQuantity' => 10,
                'price' => 25,
                'batch_number' => 'B-1',
            ], 5);
            self::fail('Expected stock fields to be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame(['stockQuantity', 'price', 'batch_number'], array_keys($exception->errors()));
            self::assertStringContainsString('Receive Delivery', $exception->errors()['stockQuantity'][0]);
        }

        self::assertSame(0, Medicine::query()->count());
        self::assertSame(0, InventoryItem::query()->count());
    }

    public function test_it_rejects_a_whitespace_generic_name_and_negative_par_level(): void
    {
        $pharmacy = Pharmacy::factory()->create();

        try {
            $this->service->createForPharmacy($pharmacy, ['medicine_name' => " \t\n "], 0);
            self::fail('Expected a whitespace-only generic name to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('medicine_name', $exception->errors());
        }

        try {
            $this->service->createForPharmacy($pharmacy, ['medicine_name' => 'Valid name'], -1);
            self::fail('Expected a negative par level to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('par_level', $exception->errors());
        }

        self::assertSame(0, Medicine::query()->count());
        self::assertSame(0, InventoryItem::query()->count());
    }
}
