<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Admin panel CRUD operations.
 *
 * Covers ISO/IEC 25010: Functional Suitability — verifies that the
 * admin panel correctly manages users, pharmacies, and medicines.
 */
class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Access control
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_consumer_cannot_access_admin_dashboard(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $this->actingAs($consumer)->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_pharmacy_user_cannot_access_admin_dashboard(): void
    {
        $pharmacyUser = User::factory()->create(['role' => 'pharmacy']);
        $this->actingAs($pharmacyUser)->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User management
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->count(3)->create(['role' => 'consumer']);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin  = $this->makeAdmin();
        $target = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'name'  => $target->name,
                'email' => $target->email,
                'role'  => 'pharmacy',
            ])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'pharmacy']);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->delete(route('admin.user.delete', $admin))
            ->assertForbidden();
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin  = $this->makeAdmin();
        $target = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($admin)
            ->delete(route('admin.user.delete', $target))
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_user_role_must_be_valid(): void
    {
        $admin  = $this->makeAdmin();
        $target = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($admin)
            ->put(route('admin.user.update', $target), [
                'name'  => $target->name,
                'email' => $target->email,
                'role'  => 'superuser',
            ])
            ->assertSessionHasErrors(['role']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pharmacy management
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_list_pharmacies(): void
    {
        $admin = $this->makeAdmin();
        Pharmacy::factory()->count(3)->create();

        $this->actingAs($admin)->get(route('admin.pharmacies'))->assertOk();
    }

    public function test_admin_can_add_pharmacy(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.pharmacy.store'), [
                'pharmacy_name'   => 'Test Pharmacy',
                'pharmacyAddress' => '123 Rizal St, Legazpi City',
                'latitude'        => 13.1391,
                'longitude'       => 123.7438,
                'contactNumber'   => '09123456789',
            ])
            ->assertRedirect(route('admin.pharmacies'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pharmacies', ['pharmacy_name' => 'Test Pharmacy']);
    }

    public function test_admin_can_approve_pharmacy(): void
    {
        $admin    = $this->makeAdmin();
        $pharmacy = Pharmacy::factory()->pending()->create();

        $this->actingAs($admin)
            ->post(route('admin.pharmacy.approve', $pharmacy))
            ->assertRedirect(route('admin.pharmacies'));

        $this->assertDatabaseHas('pharmacies', ['id' => $pharmacy->id, 'status' => 'approved']);
    }

    public function test_admin_can_delete_pharmacy(): void
    {
        $admin    = $this->makeAdmin();
        $pharmacy = Pharmacy::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.pharmacy.delete', $pharmacy))
            ->assertRedirect(route('admin.pharmacies'));

        $this->assertDatabaseMissing('pharmacies', ['id' => $pharmacy->id]);
    }

    public function test_pharmacy_requires_name_and_address(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.pharmacy.store'), [])
            ->assertSessionHasErrors(['pharmacy_name', 'pharmacyAddress']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Medicine master list management
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_list_medicines(): void
    {
        $admin = $this->makeAdmin();
        Medicine::factory()->count(5)->create();

        $this->actingAs($admin)->get(route('admin.medicines'))->assertOk();
    }

    public function test_admin_can_add_medicine(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.medicine.store'), [
                'medicine_name'        => 'Paracetamol 500mg',
                'dosage'               => '500mg',
                'manufacturer'         => 'Generic Pharma',
                'category'             => 'Analgesic',
                'requiresPrescription' => false,
            ])
            ->assertRedirect(route('admin.medicines'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('medicines', ['medicine_name' => 'Paracetamol 500mg']);
    }

    public function test_admin_can_edit_medicine(): void
    {
        $admin    = $this->makeAdmin();
        $medicine = Medicine::factory()->create(['medicine_name' => 'OldName 100mg']);

        $this->actingAs($admin)
            ->put(route('admin.medicine.update', $medicine), [
                'medicine_name'        => 'NewName 200mg',
                'dosage'               => '200mg',
                'manufacturer'         => 'Test Pharma',
                'category'             => 'Analgesic',
                'requiresPrescription' => false,
            ])
            ->assertRedirect(route('admin.medicines'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('medicines', ['id' => $medicine->id, 'medicine_name' => 'NewName 200mg']);
    }

    public function test_admin_can_delete_medicine(): void
    {
        $admin    = $this->makeAdmin();
        $medicine = Medicine::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.medicine.delete', $medicine))
            ->assertRedirect(route('admin.medicines'));

        $this->assertDatabaseMissing('medicines', ['id' => $medicine->id]);
    }

    public function test_medicine_name_is_required(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.medicine.store'), ['dosage' => '100mg'])
            ->assertSessionHasErrors(['medicine_name']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inventory overview (admin)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_view_inventory_overview(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.inventory'))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Activity log
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_actions_are_logged(): void
    {
        $admin    = $this->makeAdmin();
        $medicine = Medicine::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.medicine.delete', $medicine));

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $admin->id,
            'action'      => 'deleted',
            'entity_type' => 'Medicine',
        ]);
    }
}
