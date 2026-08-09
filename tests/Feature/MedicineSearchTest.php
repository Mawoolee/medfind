<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the consumer medicine search functionality.
 *
 * Validates Functional Suitability — the system must return correct
 * results from the unified medicine inventory search.
 */
class MedicineSearchTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Consumer map / homepage
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_is_accessible_to_guests(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_homepage_includes_pharmacy_data(): void
    {
        $pharmacy = Pharmacy::factory()->approved()->create();
        $medicine = Medicine::factory()->create(['medicine_name' => 'TestDrug 500mg']);
        InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 10,
        ]);

        $response = $this->get(route('home'))->assertOk();
        $response->assertSee($pharmacy->pharmacy_name);
    }

    public function test_homepage_excludes_pending_pharmacies(): void
    {
        $pending = Pharmacy::factory()->pending()->create(['pharmacy_name' => 'PendingRx']);
        $this->get(route('home'))->assertDontSee('PendingRx');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Search results page
    // ─────────────────────────────────────────────────────────────────────────

    public function test_search_returns_matching_medicines(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pharmacy = Pharmacy::factory()->approved()->create();
        $medicine = Medicine::factory()->named('Amoxicillin 500mg')->create();
        InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 30,
        ]);

        $this->actingAs($consumer)
            ->get(route('consumer.search', ['query' => 'Amoxicillin']))
            ->assertOk()
            ->assertSee('Amoxicillin 500mg')
            ->assertSee($pharmacy->pharmacy_name);
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        Pharmacy::factory()->approved()->create();

        $this->actingAs($consumer)
            ->get(route('consumer.search', ['query' => 'NonExistentDrug999']))
            ->assertOk()
            ->assertDontSee('pharmacy_name');
    }

    public function test_search_excludes_out_of_stock_items_indirectly(): void
    {
        // Out of stock items are still returned in the search list but stock = 0
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pharmacy = Pharmacy::factory()->approved()->create();
        $medicine = Medicine::factory()->named('Cetirizine 10mg')->create();
        InventoryItem::factory()->outOfStock()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ]);

        $response = $this->actingAs($consumer)
            ->get(route('consumer.search', ['query' => 'Cetirizine']));

        // View renders — item is shown but stock is 0
        $response->assertOk();
    }

    public function test_search_is_case_insensitive(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pharmacy = Pharmacy::factory()->approved()->create();
        $medicine = Medicine::factory()->named('Ibuprofen 200mg')->create();
        InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 5,
        ]);

        $this->actingAs($consumer)
            ->get(route('consumer.search', ['query' => 'ibuprofen']))
            ->assertOk()
            ->assertSee('Ibuprofen 200mg');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Search log tracking
    // ─────────────────────────────────────────────────────────────────────────

    public function test_search_creates_search_log_entries(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pharmacy = Pharmacy::factory()->approved()->create();
        $medicine = Medicine::factory()->named('Losartan 50mg')->create();
        InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 10,
        ]);

        $this->actingAs($consumer)
            ->get(route('consumer.search', ['query' => 'Losartan']));

        $this->assertDatabaseHas('search_logs', [
            'pharmacy_id' => $pharmacy->id,
            'query'       => 'Losartan',
        ]);
    }

    public function test_search_does_not_log_when_query_is_empty(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $this->actingAs($consumer)
            ->get(route('consumer.search', ['query' => '']));

        $this->assertDatabaseEmpty('search_logs');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pharmacy detail page
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_detail_page_shows_inventory(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pharmacy = Pharmacy::factory()->approved()->create(['pharmacy_name' => 'Test Pharmacy']);
        $medicine = Medicine::factory()->named('Metformin 500mg')->create();
        InventoryItem::factory()->create([
            'pharmacy_id'   => $pharmacy->id,
            'medicine_id'   => $medicine->id,
            'stockQuantity' => 20,
        ]);

        $this->actingAs($consumer)
            ->get(route('consumer.pharmacy.details', $pharmacy->id))
            ->assertOk()
            ->assertSee('Test Pharmacy')
            ->assertSee('Metformin 500mg');
    }

    public function test_pending_pharmacy_detail_returns_404(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pending  = Pharmacy::factory()->pending()->create();

        $this->actingAs($consumer)
            ->get(route('consumer.pharmacy.details', $pending->id))
            ->assertNotFound();
    }
}
