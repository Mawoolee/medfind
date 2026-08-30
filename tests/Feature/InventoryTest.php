<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\MedicineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Feature tests for the pharmacy medicine-master and batch-stock workflows.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function makePharmacyUser(): array
    {
        $user = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($user)->create();
        $user->update(['pharmacy_id' => $pharmacy->id]);

        return [$user, $pharmacy];
    }

    public function test_guests_cannot_access_inventory(): void
    {
        $this->get(route('pharmacy.inventory'))->assertRedirect(route('login'));
    }

    public function test_consumers_cannot_access_inventory(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $this->actingAs($consumer)->get(route('pharmacy.inventory'))->assertRedirect();
    }

    public function test_pharmacy_user_can_view_inventory_list_without_aggregate_batch_count(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ]);
        InventoryBatch::factory()->count(3)->depleted()->create([
            'inventory_item_id' => $item->id,
        ]);

        $this->actingAs($user)
            ->get(route('pharmacy.inventory'))
            ->assertOk()
            ->assertSee($medicine->medicine_name)
            ->assertSee('View Stock Batches')
            ->assertSee('View Batches')
            ->assertSee(route('pharmacy.inventory.batches'), false)
            ->assertSee(route('pharmacy.inventory.batches', ['inventory_item_id' => $item->id]), false)
            ->assertSee('href="'.route('pharmacy.dashboard').'"', false)
            ->assertSee('aria-label="Back to Pharmacy Dashboard"', false)
            ->assertDontSee('<th class="px-4 py-3">Batches</th>', false)
            ->assertDontSee('<span class="font-semibold">3</span>', false)
            ->assertViewHas('inventory', fn ($inventory): bool => ! array_key_exists(
                'batches_count',
                $inventory->first()->getAttributes(),
            ));
    }

    public function test_inventory_list_is_paginated(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        foreach (Medicine::factory()->count(20)->create() as $medicine) {
            InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id]);
        }

        $this->actingAs($user)->get(route('pharmacy.inventory'))->assertOk();
    }

    public function test_inventory_can_be_filtered_by_stock_status(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        InventoryItem::factory()->outOfStock()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ]);

        $this->actingAs($user)
            ->get(route('pharmacy.inventory', ['stock' => 'out']))
            ->assertOk()
            ->assertSee($medicine->medicine_name);
    }

    public function test_inventory_list_hides_cold_chain_filter_and_keeps_other_controls(): void
    {
        [$user] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->get(route('pharmacy.inventory'))
            ->assertOk()
            ->assertViewMissing('coldChain')
            ->assertDontSee('name="cold_chain"', false)
            ->assertDontSee('Cold Chain')
            ->assertSee('name="q"', false)
            ->assertSee('name="category"', false)
            ->assertSee('name="stock"', false)
            ->assertSee('name="sort"', false)
            ->assertSee('>Filter</button>', false);
    }

    public function test_legacy_cold_chain_query_does_not_filter_inventory(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $coldChainMedicine = Medicine::factory()->create([
            'medicine_name' => 'Cold Storage Medicine',
            'cold_chain_required' => true,
        ]);
        $standardMedicine = Medicine::factory()->create([
            'medicine_name' => 'Standard Storage Medicine',
            'cold_chain_required' => false,
        ]);

        foreach ([$coldChainMedicine, $standardMedicine] as $medicine) {
            InventoryItem::factory()->create([
                'pharmacy_id' => $pharmacy->id,
                'medicine_id' => $medicine->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('pharmacy.inventory', ['cold_chain' => '1']))
            ->assertOk()
            ->assertSee('Cold Storage Medicine')
            ->assertSee('Standard Storage Medicine');
    }

    public function test_pharmacy_user_can_view_create_form(): void
    {
        [$user] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->get(route('pharmacy.inventory.create'))
            ->assertOk()
            ->assertSee('Create the product identity only.');
    }

    public function test_pharmacy_user_can_add_new_inventory_item(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'medicine_name' => $medicine->medicine_name,
                'brand_name' => 'Updated Brand',
                'par_level' => 10,
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 0,
            'par_level' => 10,
        ]);
        $this->assertSame(0, InventoryBatch::query()->count());
    }

    public function test_adding_inventory_requires_valid_stock_and_price(): void
    {
        [$user] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'medicine_name' => $medicine->medicine_name,
                'stockQuantity' => -1,
                'price' => -10,
            ])
            ->assertSessionHasErrors(['stockQuantity', 'price']);
    }

    public function test_adding_item_without_medicine_fails(): void
    {
        [$user] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [])
            ->assertSessionHasErrors('medicine_name');
    }

    public function test_pharmacy_user_can_update_medicine_details_without_changing_stock(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $item = InventoryItem::factory()->withBatch()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 10,
            'par_level' => 2,
        ]);
        $batch = $item->batches()->firstOrFail();

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $item->id), [
                'medicine_name' => 'Updated Generic',
                'brand_name' => 'Updated Brand',
                'dosage' => '20 mg',
                'category' => 'antibiotic',
                'manufacturer' => 'Updated Manufacturer',
                'par_level' => 7,
                'requiresPrescription' => 1,
                'cold_chain_required' => 1,
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $item->refresh();
        $this->assertSame(7, (int) $item->par_level);
        $this->assertSame(10, (int) $item->stockQuantity);
        $this->assertSame((int) $batch->current_quantity, (int) $batch->fresh()->current_quantity);
        $this->assertSame('Updated Generic', $medicine->fresh()->medicine_name);
    }

    public function test_direct_stock_and_price_edit_is_rejected(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $item = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $item->id), [
                'medicine_name' => $item->medicine->medicine_name,
                'stockQuantity' => 99,
                'price' => 120,
            ])
            ->assertSessionHasErrors(['stockQuantity', 'price']);

        $this->assertNotSame(99, (int) $item->fresh()->stockQuantity);
    }

    public function test_pharmacy_user_cannot_update_another_pharmacys_item(): void
    {
        [$user] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $otherItem = InventoryItem::factory()->create(['pharmacy_id' => $otherPharmacy->id]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $otherItem->id), [
                'medicine_name' => $otherItem->medicine->medicine_name,
                'par_level' => 5,
            ])
            ->assertNotFound();
    }

    public function test_pharmacy_user_can_delete_inventory_item_without_history(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $item = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);

        $this->actingAs($user)
            ->delete(route('pharmacy.inventory.destroy', $item->id))
            ->assertRedirect(route('pharmacy.inventory'));

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_receiving_stock_creates_batch_movement_and_audit_record(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'stockQuantity' => 0,
            'price' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('pharmacy.receiving.store'), [
                'purchase_order' => 'PO-100',
                'items' => [[
                    'inventory_item_id' => $item->id,
                    'batch_number' => 'BATCH-100',
                    'lot_number' => 'LOT-1',
                    'quantity' => 20,
                    'price' => '15.25',
                    'expiry_date' => now()->addYear()->format('Y-m-d'),
                    'received_date' => now()->format('Y-m-d'),
                ]],
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_batches', [
            'inventory_item_id' => $item->id,
            'batch_number' => 'BATCH-100',
            'lot_number' => 'LOT-1',
            'quantity_received' => 20,
            'current_quantity' => 20,
        ]);
        $this->assertDatabaseHas('inventory_audits', [
            'inventory_item_id' => $item->id,
            'before_quantity' => 0,
            'after_quantity' => 20,
        ]);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(20, (int) $item->fresh()->stockQuantity);
    }

    public function test_pharmacy_user_can_export_inventory_as_csv(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $item = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);
        InventoryBatch::factory()->count(2)->depleted()->create([
            'inventory_item_id' => $item->id,
        ]);

        $response = $this->actingAs($user)->get(route('pharmacy.inventory.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $lines = preg_split('/\r\n|\r|\n/', trim($response->streamedContent()));
        $header = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);
        $batchCountIndex = array_search('Batch Count', $header, true);

        $this->assertNotFalse($batchCountIndex);
        $this->assertSame('2', $row[$batchCountIndex]);
    }

    public function test_new_medicine_and_complete_batch_fields_are_persisted_in_separate_steps(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_name' => 'Acetaminophen',
                'brand_name' => 'Relief Plus',
                'dosage' => '500 mg',
                'par_level' => 12,
                'category' => 'analgesic',
                'manufacturer' => 'Example Laboratories',
                'cold_chain_required' => 1,
            ])
            ->assertRedirect(route('pharmacy.inventory'));

        $medicine = Medicine::query()->where('medicine_name', 'Acetaminophen')->firstOrFail();
        $item = InventoryItem::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->firstOrFail();
        $supplier = Supplier::query()->create(['name' => 'Northwind Medical']);

        $this->actingAs($user)
            ->post(route('pharmacy.receiving.store'), [
                'supplier_id' => $supplier->id,
                'purchase_order' => 'PO-200',
                'items' => [[
                    'inventory_item_id' => $item->id,
                    'batch_number' => 'BATCH-NEW-1',
                    'lot_number' => 'LOT-NEW-1',
                    'quantity' => 48,
                    'price' => '19.95',
                    'expiry_date' => '2028-06-30',
                    'received_date' => now()->format('Y-m-d'),
                    'cold_chain' => 1,
                ]],
            ])
            ->assertRedirect(route('pharmacy.inventory'));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stockQuantity' => 48,
            'par_level' => 12,
        ]);
        $this->assertDatabaseHas('inventory_batches', [
            'inventory_item_id' => $item->id,
            'batch_number' => 'BATCH-NEW-1',
            'lot_number' => 'LOT-NEW-1',
            'supplier_id' => $supplier->id,
            'current_quantity' => 48,
            'cold_chain' => 1,
        ]);
    }

    public function test_selected_medicine_updates_master_and_only_current_pharmacy_par_level(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create(['medicine_name' => 'Old Generic']);
        $ownItem = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 10,
            'par_level' => 2,
        ]);
        $otherItem = InventoryItem::factory()->create([
            'pharmacy_id' => $otherPharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 99,
            'par_level' => 3,
        ]);

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'medicine_name' => 'Updated Generic',
                'brand_name' => 'Updated Brand',
                'dosage' => '20 mg',
                'par_level' => 7,
                'category' => 'antibiotic',
                'manufacturer' => 'Updated Manufacturer',
            ])
            ->assertRedirect(route('pharmacy.inventory'));

        $this->assertSame('Updated Generic', $medicine->fresh()->medicine_name);
        $this->assertSame(10, (int) $ownItem->fresh()->stockQuantity);
        $this->assertSame(7, (int) $ownItem->fresh()->par_level);
        $this->assertSame(99, (int) $otherItem->fresh()->stockQuantity);
        $this->assertSame(3, (int) $otherItem->fresh()->par_level);
    }

    public function test_blank_receipt_supplier_does_not_delete_existing_supplier(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $supplier = Supplier::query()->create(['name' => 'Existing Supplier']);
        $item = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);

        $this->actingAs($user)->post(route('pharmacy.receiving.store'), [
            'supplier_name' => '   ',
            'items' => [[
                'inventory_item_id' => $item->id,
                'batch_number' => 'NO-SUPPLIER',
                'quantity' => 5,
                'price' => 10,
                'received_date' => now()->format('Y-m-d'),
            ]],
        ])->assertRedirect(route('pharmacy.inventory'));

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Existing Supplier']);
        $this->assertDatabaseHas('inventory_batches', [
            'inventory_item_id' => $item->id,
            'batch_number' => 'NO-SUPPLIER',
            'supplier_id' => null,
            'supplier_name' => null,
        ]);
    }

    public function test_receiving_accepts_supplier_id(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $supplier = Supplier::query()->create(['name' => 'Delivery Supplier']);
        $item = InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id]);

        $this->actingAs($user)->post(route('pharmacy.receiving.store'), [
            'supplier_id' => $supplier->id,
            'items' => [[
                'inventory_item_id' => $item->id,
                'batch_number' => 'SUPPLIER-BATCH',
                'quantity' => 8,
                'price' => 25,
                'received_date' => now()->format('Y-m-d'),
            ]],
        ])->assertRedirect(route('pharmacy.inventory'));

        $this->assertDatabaseHas('inventory_batches', [
            'inventory_item_id' => $item->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => 'Delivery Supplier',
        ]);
    }

    public function test_create_form_contains_only_medicine_master_fields_and_safe_autofill(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $unsafeBrand = '</script><script>alert("xss")</script>';
        $medicine = Medicine::factory()->create([
            'medicine_name' => 'Insulin Human',
            'brand_name' => $unsafeBrand,
            'dosage' => '100 units/mL',
            'manufacturer' => 'Safe Manufacturer',
            'category' => 'other',
            'cold_chain_required' => true,
        ]);
        InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'par_level' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('pharmacy.inventory.create'));
        $response->assertOk()->assertViewHas('medicineAutofill', function ($payload) use ($medicine): bool {
            $values = $payload->get((string) $medicine->id) ?? $payload->get($medicine->id);

            return $values['medicine_name'] === 'Insulin Human'
                && $values['par_level'] === 5
                && $values['cold_chain_required'] === true
                && ! array_key_exists('batch_number', $values)
                && ! array_key_exists('stock_quantity', $values);
        });

        $content = $response->getContent();
        $this->assertStringNotContainsString($unsafeBrand, $content);
        foreach (['medicine_id', 'medicine_name', 'brand_name', 'dosage', 'category', 'manufacturer', 'par_level', 'requiresPrescription', 'cold_chain_required'] as $name) {
            $this->assertStringContainsString('name="'.$name.'"', $content);
        }
        foreach (['batch_number', 'lot_number', 'price', 'stockQuantity', 'supplier_name', 'expiry_date', 'cold_chain'] as $name) {
            $this->assertStringNotContainsString('name="'.$name.'"', $content);
        }
    }

    public function test_validation_preserves_selected_medicine_and_old_master_values(): void
    {
        [$user] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();

        $this->actingAs($user)
            ->from(route('pharmacy.inventory.create'))
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'medicine_name' => 'User Edited Generic',
                'brand_name' => 'User Edited Brand',
                'dosage' => '125 mg',
                'par_level' => -1,
                'category' => 'analgesic',
                'manufacturer' => 'Preserved Manufacturer',
                'cold_chain_required' => 1,
            ])
            ->assertRedirect(route('pharmacy.inventory.create'))
            ->assertSessionHasErrors('par_level')
            ->assertSessionHasInput('medicine_id', $medicine->id);

        $this->get(route('pharmacy.inventory.create'))
            ->assertOk()
            ->assertSee('value="'.$medicine->id.'" selected', false)
            ->assertSee('value="User Edited Generic"', false)
            ->assertSee('value="User Edited Brand"', false)
            ->assertSee('value="Preserved Manufacturer"', false)
            ->assertSee('name="cold_chain_required" value="1" checked', false);
    }

    public function test_inventory_category_filter_has_the_complete_catalog_and_only_current_pharmacy_custom_values(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();

        foreach (['Analgesic', 'Antibiotic', 'Legacy Care', 'legacy care', '   '] as $index => $category) {
            $medicine = Medicine::factory()->create([
                'medicine_name' => 'Own Medicine '.$index,
                'category' => $category,
            ]);
            InventoryItem::factory()->create([
                'pharmacy_id' => $pharmacy->id,
                'medicine_id' => $medicine->id,
            ]);
        }

        $otherMedicine = Medicine::factory()->create([
            'medicine_name' => 'Other Pharmacy Medicine',
            'category' => 'Other Pharmacy Secret',
        ]);
        InventoryItem::factory()->create([
            'pharmacy_id' => $otherPharmacy->id,
            'medicine_id' => $otherMedicine->id,
        ]);

        $response = $this->actingAs($user)->get(route('pharmacy.inventory'));

        $response->assertOk()
            ->assertViewHas('categoryOptions', function (array $options): bool {
                $customMatches = collect($options)
                    ->filter(fn (string $label, string $value): bool => mb_strtolower($value) === 'legacy care');

                return array_slice($options, 0, 9, true) === MedicineCategory::canonicalOptions()
                    && $customMatches->count() === 1
                    && ! collect(array_keys($options))->contains(
                        fn (string $value): bool => mb_strtolower($value) === 'other pharmacy secret'
                    );
            });

        foreach (MedicineCategory::canonicalOptions() as $value => $label) {
            $this->assertSame(1, preg_match(
                '/<option value="'.preg_quote($value, '/').'"\s*>'.preg_quote($label, '/').'<\/option>/',
                $response->getContent(),
            ));
        }

        $this->assertSame(1, preg_match_all('/<option value="legacy care"\s*>/i', $response->getContent()));
        $response->assertDontSee('Other Pharmacy Secret');
    }

    public function test_inventory_category_filter_matches_legacy_casing_and_custom_categories(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();

        $legacyCanonical = Medicine::factory()->create([
            'medicine_name' => 'Legacy Title Analgesic',
            'category' => 'Analgesic',
        ]);
        $custom = Medicine::factory()->create([
            'medicine_name' => 'Custom Category Medicine',
            'category' => 'Specialty Care',
        ]);
        $unmatched = Medicine::factory()->create([
            'medicine_name' => 'Unmatched Antibiotic',
            'category' => 'Antibiotic',
        ]);

        foreach ([$legacyCanonical, $custom, $unmatched] as $medicine) {
            InventoryItem::factory()->create([
                'pharmacy_id' => $pharmacy->id,
                'medicine_id' => $medicine->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('pharmacy.inventory', ['category' => 'analgesic']))
            ->assertOk()
            ->assertSee('Legacy Title Analgesic')
            ->assertDontSee('Custom Category Medicine')
            ->assertDontSee('Unmatched Antibiotic');

        $this->actingAs($user)
            ->get(route('pharmacy.inventory', ['category' => 'specialty care']))
            ->assertOk()
            ->assertSee('Custom Category Medicine')
            ->assertDontSee('Legacy Title Analgesic')
            ->assertDontSee('Unmatched Antibiotic');
    }

    public function test_add_and_edit_forms_share_the_catalog_and_preserve_old_or_current_custom_selection(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create([
            'medicine_name' => 'Legacy Custom Medicine',
            'category' => 'Legacy Compound',
        ]);
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ]);

        $editResponse = $this->actingAs($user)->get(route('pharmacy.inventory.edit', $item->id));
        $createResponse = $this->actingAs($user)
            ->withSession(['_old_input' => ['category' => 'Old Custom Category']])
            ->get(route('pharmacy.inventory.create'));

        foreach ([$createResponse, $editResponse] as $response) {
            $response->assertOk()
                ->assertViewHas('categoryOptions', fn (array $options): bool => array_slice($options, 0, 9, true) === MedicineCategory::canonicalOptions()
                );
        }

        $createResponse->assertSee(
            '<option value="Old Custom Category" selected>Old Custom Category</option>',
            false,
        );
        $editResponse->assertSee(
            '<option value="Legacy Compound" selected>Legacy Compound</option>',
            false,
        );
    }
}
