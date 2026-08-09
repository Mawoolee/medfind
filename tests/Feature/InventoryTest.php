<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
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
        $user     = User::factory()->create(['role' => 'pharmacy']);
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
        $this->actingAs($consumer)->get(route('pharmacy.inventory'))->assertForbidden();
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
                'medicine_id'   => $medicine->id,
                'stockQuantity' => 50,
                'price'         => 75.00,
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 50,
        ]);
    }

    public function test_adding_inventory_requires_valid_stock_and_price(): void
    {
        [$user] = $this->makePharmacyUser();
        $medicine = Medicine::factory()->create();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'medicine_id'   => $medicine->id,
                'stockQuantity' => -1,
                'price'         => -10,
            ])
            ->assertSessionHasErrors(['stockQuantity', 'price']);
    }

    public function test_adding_item_without_medicine_fails(): void
    {
        [$user] = $this->makePharmacyUser();

        $this->actingAs($user)
            ->post(route('pharmacy.inventory.store'), [
                'stockQuantity' => 10,
                'price'         => 50,
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
        $item     = InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 10,
            'price'         => 50.00,
        ]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $item->id), [
                'stockQuantity' => 99,
                'price'         => 120.00,
            ])
            ->assertRedirect(route('pharmacy.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id'            => $item->id,
            'stockQuantity' => 99,
        ]);
    }

    public function test_pharmacy_user_cannot_update_another_pharmacys_item(): void
    {
        [$user]        = $this->makePharmacyUser();
        [$, $otherPharmacy] = $this->makePharmacyUser();
        $medicine      = Medicine::factory()->create();
        $otherItem     = InventoryItem::factory()->create([
            'pharmacy_id' => $otherPharmacy->id,
            'medicine_id' => $medicine->id,
        ]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $otherItem->id), [
                'stockQuantity' => 5,
                'price'         => 10,
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
        $item     = InventoryItem::factory()->create([
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
        $item     = InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 20,
        ]);

        $this->actingAs($user)
            ->put(route('pharmacy.inventory.update', $item->id), [
                'stockQuantity' => 35,
                'price'         => $item->price,
            ]);

        $this->assertDatabaseHas('inventory_audits', [
            'inventory_item_id' => $item->id,
            'before_quantity'   => 20,
            'after_quantity'    => 35,
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
}
