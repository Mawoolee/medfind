<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Feature tests for Pharmacy Inventory CRUD.
 *
 * Covers the ISO/IEC 25010 Functional Suitability characteristic:
 * verifies that core inventory management functions behave correctly.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makePharmacyUser(): array
    {
        $user = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($user)->create();
        $user->update(['pharmacy_id' => $pharmacy->id]);

        return [$user, $pharmacy];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Authorization
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guests_cannot_access_inventory(): void
    {
        $this->get(route('pharmacy.inventory'))->assertRedirect(route('login'));
    }

    public function test_consumers_cannot_access_inventory(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        // CheckRole redirects wrong-role users rather than returning 403
        $this->actingAs($consumer)->get(route('pharmacy.inventory'))->assertRedirect();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Read
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_user_can_view_inventory_list(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id]);

        $this->actingAs($user)
            ->get(route('pharmacy.inventory'))
            ->assertOk()
            ->assertSee($medicine->medicine_name);
    }

    public function test_inventory_list_is_paginated(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicines = Medicine::factory()->count(20)->create();
        foreach ($medicines as $med) {
            InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id, 'medicine_id' => $med->id]);
        }

        $this->actingAs($user)
            ->get(route('pharmacy.inventory'))
            ->assertOk();
    }

    public function test_inventory_can_be_filtered_by_stock_status(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        InventoryItem::factory()->outOfStock()->create(['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id]);

        $this->actingAs($user)
            ->get(route('pharmacy.inventory', ['stock' => 'out']))
            ->assertOk()
            ->assertSee($medicine->medicine_name);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_user_can_view_create_form(): void
    {
        [$user] = $this->makePharmacyUser();
        $this->actingAs($user)
            ->get(route('pharmacy.inventory.create'))
            ->assertOk();
    }

    public function test_pharmacy_user_can_add_new_inventory_item(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'stockQuantity' => 50,
                'price' => 75.00,
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 50,
        ]);
    }

    public function test_adding_inventory_requires_valid_stock_and_price(): void
    {
        [$user] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'stockQuantity' => -1,
                'price' => -10,
            ])
            ->assertSessionHasErrors(['stockQuantity', 'price']);
    }

    public function test_adding_item_without_medicine_fails(): void
    {
        [$user] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'stockQuantity' => 10,
                'price' => 50,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_user_can_update_stock_and_price(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 10,
            'price' => 50.00,
        ]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $item->id), [
                'stockQuantity' => 99,
                'price' => 120.00,
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stockQuantity' => 99,
        ]);
    }

    public function test_pharmacy_user_cannot_update_another_pharmacys_item(): void
    {
        [$user] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $otherItem = InventoryItem::factory()->create([
            'pharmacy_id' => $otherPharmacy->id,
            'medicine_id' => $medicine->id,
        ]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $otherItem->id), [
                'stockQuantity' => 5,
                'price' => 10,
            ])
            ->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delete
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_user_can_delete_inventory_item(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ]);

        $this->actingAs($user)
            ->delete(route('pharmacy.inventory.destroy', $item->id))
            ->assertRedirect(route('pharmacy.inventory'));

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Audit trail
    // ─────────────────────────────────────────────────────────────────────────

    public function test_stock_change_creates_audit_record(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 20,
        ]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $item->id), [
                'stockQuantity' => 35,
                'price' => $item->price,
            ]);

        $this->assertDatabaseHas('inventory_audits', [
            'inventory_item_id' => $item->id,
            'before_quantity' => 20,
            'after_quantity' => 35,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSV export
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_user_can_export_inventory_as_csv(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        InventoryItem::factory()->create(['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id]);

        $response = $this->actingAs($user)->get(route('pharmacy.inventory.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_new_medicine_and_complete_inventory_fields_are_persisted(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_name' => 'Acetaminophen',
                'brand_name' => 'Relief Plus',
                'dosage' => '500 mg',
                'batch_number' => 'BATCH-NEW-1',
                'lot_number' => 'LOT-NEW-1',
                'price' => '19.95',
                'stockQuantity' => 48,
                'par_level' => 12,
                'category' => 'analgesic',
                'supplier_name' => '  Northwind Medical  ',
                'manufacturer' => 'Example Laboratories',
                'expiry_date' => '2028-06-30',
                'cold_chain' => '1',
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $medicine = Medicine::where('medicine_name', 'Acetaminophen')->firstOrFail();
        $supplier = Supplier::where('name', 'Northwind Medical')->firstOrFail();
        $this->assertSame('Relief Plus', $medicine->brand_name);
        $this->assertSame('500 mg', $medicine->dosage);
        $this->assertSame('analgesic', $medicine->category);
        $this->assertSame('Example Laboratories', $medicine->manufacturer);

        $this->assertDatabaseHas('inventory_items', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-NEW-1',
            'lot_number' => 'LOT-NEW-1',
            'stockQuantity' => 48,
            'par_level' => 12,
            'supplier_id' => $supplier->id,
            'cold_chain' => 1,
        ]);
    }

    public function test_selected_medicine_edits_and_only_current_pharmacy_inventory_are_updated(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create([
            'medicine_name' => 'Old Generic',
            'brand_name' => 'Old Brand',
            'dosage' => '10 mg',
            'manufacturer' => 'Old Manufacturer',
            'category' => 'other',
        ]);
        $supplier = Supplier::create(['name' => 'Updated Supplier']);
        $ownItem = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 10,
            'batch_number' => 'OWN-OLD',
            'lot_number' => 'OWN-LOT-OLD',
        ]);
        $otherItem = InventoryItem::factory()->create([
            'pharmacy_id' => $otherPharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 99,
            'batch_number' => 'OTHER-BATCH',
            'lot_number' => 'OTHER-LOT',
            'cold_chain' => false,
        ]);

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'medicine_name' => 'Updated Generic',
                'brand_name' => 'Updated Brand',
                'dosage' => '20 mg',
                'batch_number' => 'OWN-NEW',
                'lot_number' => 'OWN-LOT-NEW',
                'price' => '33.50',
                'stockQuantity' => 25,
                'par_level' => 7,
                'category' => 'antibiotic',
                'supplier_name' => '  updated supplier ',
                'manufacturer' => 'Updated Manufacturer',
                'expiry_date' => '2029-02-01',
                'cold_chain' => '1',
            ])
            ->assertRedirect(route('pharmacy.inventory'));

        $medicine->refresh();
        $this->assertSame('Updated Generic', $medicine->medicine_name);
        $this->assertSame('Updated Brand', $medicine->brand_name);
        $this->assertSame('20 mg', $medicine->dosage);
        $this->assertSame('antibiotic', $medicine->category);
        $this->assertSame('Updated Manufacturer', $medicine->manufacturer);

        $ownItem->refresh();
        $this->assertSame(25, $ownItem->stockQuantity);
        $this->assertSame('OWN-NEW', $ownItem->batch_number);
        $this->assertSame('OWN-LOT-NEW', $ownItem->lot_number);
        $this->assertSame($supplier->id, $ownItem->supplier_id);
        $this->assertTrue($ownItem->cold_chain);
        $this->assertSame(1, Supplier::whereRaw('LOWER(TRIM(name)) = ?', ['updated supplier'])->count());
        $this->assertSame('Updated Supplier', $supplier->fresh()->name);

        $otherItem->refresh();
        $this->assertSame(99, $otherItem->stockQuantity);
        $this->assertSame('OTHER-BATCH', $otherItem->batch_number);
        $this->assertSame('OTHER-LOT', $otherItem->lot_number);
        $this->assertFalse($otherItem->cold_chain);
    }

    public function test_blank_supplier_name_clears_existing_supplier_without_deleting_it(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $supplier = Supplier::create(['name' => 'Existing Supplier']);
        $item = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'stockQuantity' => 12,
                'price' => 45,
                'supplier_name' => '   ',
            ])
            ->assertRedirect(route('pharmacy.inventory'));

        $this->assertNull($item->fresh()->supplier_id);
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Existing Supplier',
        ]);
    }

    public function test_store_accepts_legacy_supplier_id_when_supplier_name_is_absent(): void
    {
        Event::fake();
        [$user, $pharmacy] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();
        $supplier = Supplier::create(['name' => 'Legacy Supplier']);

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id' => $medicine->id,
                'stockQuantity' => 8,
                'price' => 25,
                'supplier_id' => $supplier->id,
            ])
            ->assertRedirect(route('pharmacy.inventory'));

        $this->assertDatabaseHas('inventory_items', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_create_form_has_required_field_order_and_scoped_autofill_payload(): void
    {
        [$user, $pharmacy] = $this->makePharmacyUser();
        [, $otherPharmacy] = $this->makePharmacyUser();
        $supplier = Supplier::create(['name' => 'Cold Chain Supplier']);
        $unsafeBrand = '</script><script>alert("xss")</script>';
        $medicine = Medicine::factory()->create([
            'medicine_name' => 'Insulin Human',
            'brand_name' => $unsafeBrand,
            'dosage' => '100 units/mL',
            'manufacturer' => 'Safe Manufacturer',
            'category' => 'other',
        ]);
        InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 15,
            'price' => 250.75,
            'batch_number' => 'CURRENT-BATCH',
            'lot_number' => 'CURRENT-LOT',
            'par_level' => 5,
            'supplier_id' => $supplier->id,
            'expiry_date' => '2028-12-31',
            'cold_chain' => true,
        ]);
        InventoryItem::factory()->create([
            'pharmacy_id' => $otherPharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 777,
            'batch_number' => 'PRIVATE-OTHER-BATCH',
        ]);

        $response = $this->actingAs($user)->get(route('pharmacy.inventory.create'));
        $response->assertOk()
            ->assertViewHas('medicineAutofill', function ($payload) use ($medicine) {
                $values = $payload->get($medicine->id);

                return $values['generic_name'] === 'Insulin Human'
                    && $values['batch_number'] === 'CURRENT-BATCH'
                    && $values['lot_number'] === 'CURRENT-LOT'
                    && $values['stock_quantity'] === 15
                    && $values['supplier_name'] === 'Cold Chain Supplier'
                    && $values['cold_chain'] === true;
            });

        $content = $response->getContent();
        $this->assertStringNotContainsString('PRIVATE-OTHER-BATCH', $content);
        $this->assertStringNotContainsString($unsafeBrand, $content);
        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-2', $content);
        $this->assertStringContainsString('type="text" name="supplier_name"', $content);
        $this->assertStringNotContainsString('name="supplier_id"', $content);
        $this->assertStringNotContainsString('<select id="supplier_name"', $content);

        $orderedNames = [
            'medicine_id',
            'medicine_name',
            'brand_name',
            'dosage',
            'batch_number',
            'lot_number',
            'price',
            'stockQuantity',
            'par_level',
            'category',
            'supplier_name',
            'manufacturer',
            'expiry_date',
            'cold_chain',
        ];
        $lastPosition = -1;
        foreach ($orderedNames as $name) {
            $position = strpos($content, 'name="'.$name.'"');
            $this->assertNotFalse($position, "Missing form field {$name}.");
            $this->assertGreaterThan($lastPosition, $position, "Field {$name} is out of order.");
            $lastPosition = $position;
        }
    }

    public function test_validation_preserves_selected_medicine_and_old_editable_values(): void
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
                'batch_number' => 'PRESERVED-BATCH',
                'lot_number' => 'PRESERVED-LOT',
                'price' => -1,
                'stockQuantity' => 8,
                'par_level' => 2,
                'category' => 'analgesic',
                'supplier_name' => 'User Entered & Supplier',
                'manufacturer' => 'Preserved Manufacturer',
                'expiry_date' => '2028-01-15',
                'cold_chain' => '1',
            ])
            ->assertRedirect(route('pharmacy.inventory.create'))
            ->assertSessionHasErrors('price')
            ->assertSessionHasInput('medicine_id', $medicine->id)
            ->assertSessionHasInput('lot_number', 'PRESERVED-LOT')
            ->assertSessionHasInput('supplier_name', 'User Entered & Supplier');

        $this->get(route('pharmacy.inventory.create'))
            ->assertOk()
            ->assertSee('value="'.$medicine->id.'" selected', false)
            ->assertSee('value="User Edited Generic"', false)
            ->assertSee('value="User Edited Brand"', false)
            ->assertSee('value="PRESERVED-LOT"', false)
            ->assertSee('name="supplier_name" value="User Entered &amp; Supplier"', false)
            ->assertSee('name="cold_chain" value="1" checked', false);
    }
}
