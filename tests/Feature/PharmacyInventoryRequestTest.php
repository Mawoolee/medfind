<?php

namespace Tests\Feature;

use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Requests\Inventory\BulkAdjustInventoryRequest;
use App\Http\Requests\Inventory\CorrectInventoryBatchRequest;
use App\Http\Requests\Inventory\PharmacyInventoryRequest;
use App\Http\Requests\Inventory\ReceiveInventoryRequest;
use App\Http\Requests\Inventory\StoreMedicineRequest;
use App\Http\Requests\Inventory\UpdateInventoryBatchRequest;
use App\Http\Resolvers\PharmacyInventoryRecordResolver;
use App\Models\ControlledSubstanceLog;
use App\Models\CycleCount;
use App\Models\CycleCountItem;
use App\Models\InventoryAudit;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\ReturnRecall;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use Tests\TestCase;

final class PharmacyInventoryRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_medicine_request_accepts_master_fields_and_rejects_stock_fields_with_receiving_guidance(): void
    {
        [$user] = $this->makePharmacyUser();

        [$validRequest, $valid] = $this->validatorFor(StoreMedicineRequest::class, $user, [
            'medicine_name' => 'Amoxicillin',
            'brand_name' => 'Example Brand',
            'par_level' => 8,
            'requiresPrescription' => true,
            'cold_chain_required' => false,
        ]);

        self::assertTrue($valid->passes());
        self::assertSame('Amoxicillin', $validRequest->medicineAttributes()['medicine_name']);
        self::assertSame(8, $validRequest->parLevel());

        [, $invalid] = $this->validatorFor(StoreMedicineRequest::class, $user, [
            'medicine_name' => " \t ",
            'stockQuantity' => 12,
            'batch_number' => 'B-1',
        ]);

        self::assertTrue($invalid->fails());
        self::assertArrayHasKey('medicine_name', $invalid->errors()->toArray());
        self::assertArrayHasKey('stockQuantity', $invalid->errors()->toArray());
        self::assertStringContainsString('Add Stock/Receive Delivery', $invalid->errors()->first('stockQuantity'));
    }

    public function test_receipt_request_validates_each_row_against_the_authenticated_pharmacy_and_builds_typed_data(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $ownAggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $foreignAggregate = InventoryItem::factory()->create(['pharmacy_id' => $otherPharmacy->id]);

        [$request, $valid] = $this->validatorFor(ReceiveInventoryRequest::class, $user, [
            'supplier_name' => '  Northwind Medical  ',
            'purchase_order' => 'PO-100',
            'items' => [[
                'inventory_item_id' => $ownAggregate->id,
                'batch_number' => '  BATCH-100  ',
                'lot_number' => ' LOT-1 ',
                'quantity' => 15,
                'price' => '19.95',
                'expiry_date' => '2030-05-01',
                'cold_chain' => '1',
                'received_date' => '2029-01-15',
            ]],
        ]);

        self::assertTrue($valid->passes());
        $receipt = $request->receiptData(0);
        self::assertSame('BATCH-100', $receipt->batchNumber);
        self::assertSame('LOT-1', $receipt->lotNumber);
        self::assertSame(15, $receipt->quantityReceived);
        self::assertSame('Northwind Medical', $receipt->supplierName);
        self::assertSame('PO-100', $receipt->receivedReference);
        self::assertTrue($receipt->coldChain);

        [, $invalid] = $this->validatorFor(ReceiveInventoryRequest::class, $user, [
            'items' => [[
                'inventory_item_id' => $foreignAggregate->id,
                'batch_number' => '   ',
                'quantity' => 0,
                'price' => '2.999',
            ]],
        ]);

        self::assertTrue($invalid->fails());
        self::assertArrayHasKey('items.0.inventory_item_id', $invalid->errors()->toArray());
        self::assertArrayHasKey('items.0.batch_number', $invalid->errors()->toArray());
        self::assertArrayHasKey('items.0.quantity', $invalid->errors()->toArray());
        self::assertArrayHasKey('items.0.price', $invalid->errors()->toArray());
    }

    public function test_batch_metadata_and_correction_requests_protect_quantities_and_require_a_real_reason(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $aggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $batch = InventoryBatch::factory()->create(['inventory_item_id' => $aggregate->id]);

        [, $metadata] = $this->validatorFor(UpdateInventoryBatchRequest::class, $user, [
            'batch_number' => 'B-20',
            'price' => '25.00',
            'received_date' => '2029-01-01',
            'quantity_received' => 999,
            'current_quantity' => 999,
        ], ['batch' => $batch]);

        self::assertTrue($metadata->fails());
        self::assertArrayHasKey('quantity_received', $metadata->errors()->toArray());
        self::assertArrayHasKey('current_quantity', $metadata->errors()->toArray());

        [, $correction] = $this->validatorFor(CorrectInventoryBatchRequest::class, $user, [
            'target_quantity' => 4,
            'correction_reason' => '   ',
        ], ['batch' => $batch]);

        self::assertTrue($correction->fails());
        self::assertArrayHasKey('correction_reason', $correction->errors()->toArray());
    }

    public function test_aggregate_increases_require_traceable_batch_metadata_but_decreases_do_not(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 10,
        ]);

        [, $increase] = $this->validatorFor(AdjustInventoryRequest::class, $user, [
            'target_quantity' => 15,
        ], ['inventoryItem' => $aggregate]);

        self::assertTrue($increase->fails());
        self::assertArrayHasKey('batch_number', $increase->errors()->toArray());
        self::assertArrayHasKey('price', $increase->errors()->toArray());
        self::assertArrayHasKey('received_date', $increase->errors()->toArray());
        self::assertArrayHasKey('correction_reason', $increase->errors()->toArray());

        [, $decrease] = $this->validatorFor(AdjustInventoryRequest::class, $user, [
            'target_quantity' => 5,
        ], ['inventoryItem' => $aggregate]);

        self::assertTrue($decrease->passes());
    }

    public function test_bulk_adjustments_validate_all_ownership_and_increase_metadata_by_row(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $ownAggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 10,
        ]);
        $foreignAggregate = InventoryItem::factory()->create(['pharmacy_id' => $otherPharmacy->id]);

        [, $validator] = $this->validatorFor(BulkAdjustInventoryRequest::class, $user, [
            'adjustments' => [
                ['inventory_item_id' => $ownAggregate->id, 'target_quantity' => 12],
                ['inventory_item_id' => $foreignAggregate->id, 'target_quantity' => 1],
            ],
        ]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('adjustments.0.batch_number', $validator->errors()->toArray());
        self::assertArrayHasKey('adjustments.0.correction_reason', $validator->errors()->toArray());
        self::assertArrayHasKey('adjustments.1.inventory_item_id', $validator->errors()->toArray());
    }

    public function test_record_resolver_returns_only_records_owned_by_the_authenticated_pharmacy(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $ownAggregate = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $foreignAggregate = InventoryItem::factory()->create(['pharmacy_id' => $otherPharmacy->id]);
        $ownBatch = InventoryBatch::factory()->create(['inventory_item_id' => $ownAggregate->id]);
        $foreignBatch = InventoryBatch::factory()->create(['inventory_item_id' => $foreignAggregate->id]);
        $audit = InventoryAudit::create([
            'inventory_item_id' => $ownAggregate->id,
            'user_id' => $user->id,
            'before_quantity' => 1,
            'after_quantity' => 2,
        ]);
        $log = ControlledSubstanceLog::create([
            'inventory_item_id' => $ownAggregate->id,
            'user_id' => $user->id,
            'action' => 'audited',
            'quantity' => 1,
        ]);
        $returnRecall = ReturnRecall::create([
            'inventory_item_id' => $ownAggregate->id,
            'type' => 'return',
            'quantity' => 1,
            'requested_by' => $user->id,
        ]);
        $cycleCount = CycleCount::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Count 1',
            'conducted_by' => $user->id,
        ]);
        $cycleItem = CycleCountItem::create([
            'cycle_count_id' => $cycleCount->id,
            'inventory_item_id' => $ownAggregate->id,
            'expected_quantity' => 2,
            'counted_quantity' => 2,
        ]);
        $resolver = app(PharmacyInventoryRecordResolver::class);

        self::assertTrue($ownAggregate->is($resolver->aggregate($user, $ownAggregate)));
        self::assertTrue($ownBatch->is($resolver->batch($user, $ownBatch, $ownAggregate)));
        self::assertTrue($audit->is($resolver->audit($user, $audit)));
        self::assertTrue($log->is($resolver->controlledSubstanceLog($user, $log)));
        self::assertTrue($returnRecall->is($resolver->returnRecall($user, $returnRecall)));
        self::assertTrue($cycleCount->is($resolver->cycleCount($user, $cycleCount)));
        self::assertTrue($cycleItem->is($resolver->cycleCountItem($user, $cycleItem)));

        $this->assertModelNotFound(fn () => $resolver->aggregate($user, $foreignAggregate));
        $this->assertModelNotFound(fn () => $resolver->batch($user, $foreignBatch));
        $this->assertModelNotFound(fn () => $resolver->batch($user, $ownBatch, $foreignAggregate));
    }

    /**
     * @param  class-string<PharmacyInventoryRequest>  $requestClass
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $routeParameters
     * @return array{PharmacyInventoryRequest, LaravelValidator}
     */
    private function validatorFor(
        string $requestClass,
        User $user,
        array $data,
        array $routeParameters = [],
    ): array {
        /** @var PharmacyInventoryRequest $request */
        $request = $requestClass::create('/_inventory-request-test', 'POST', $data);
        $request->setContainer($this->app);
        $request->setUserResolver(fn (): User => $user);

        $route = new Route('POST', '/_inventory-request-test', static fn () => null);
        $route->bind($request);
        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }
        $request->setRouteResolver(fn (): Route => $route);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        if (method_exists($request, 'after')) {
            foreach ($request->after() as $callback) {
                $validator->after($callback);
            }
        }
        $request->setValidator($validator);

        return [$request, $validator];
    }

    private function makePharmacyUser(): array
    {
        $user = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($user)->create();
        $user->update(['pharmacy_id' => $pharmacy->id]);

        return [$user, $pharmacy];
    }

    private function assertModelNotFound(Closure $callback): void
    {
        try {
            $callback();
            self::fail('Expected a pharmacy-scoped lookup to return not found.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
