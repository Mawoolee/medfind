<?php

namespace Tests\Feature;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RecordSaleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_sale_routes_require_pharmacy_authentication(): void
    {
        $this->get(route('pharmacy.sales.create'))
            ->assertRedirect(route('login'));

        $this->post(route('pharmacy.sales.store'), ['items' => []])
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_orders_all_actions_in_a_four_column_desktop_grid_and_has_conventional_sale_routes(): void
    {
        [$owner] = $this->ownerAndPharmacy();

        self::assertTrue(Route::has('pharmacy.sales.create'));
        self::assertTrue(Route::has('pharmacy.sales.store'));
        self::assertSame('/pharmacy/sales/create', parse_url(route('pharmacy.sales.create'), PHP_URL_PATH));
        self::assertSame('/pharmacy/sales', parse_url(route('pharmacy.sales.store'), PHP_URL_PATH));

        $response = $this->actingAs($owner)->get(route('pharmacy.dashboard'));
        $html = (string) $response->getContent();
        $dashboardSource = file_get_contents(resource_path('views/pharmacy/dashboard.blade.php'));
        $sharedActionClasses = 'inline-flex items-center justify-center gap-2 text-center font-semibold px-6 py-3 rounded-lg transition duration-200 w-full min-h-12 whitespace-normal break-words leading-tight';

        $response->assertOk()
            ->assertSee('grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5', false)
            ->assertDontSee('xl:grid-cols-5', false)
            ->assertSeeInOrder([
                'Record Sale',
                'Manage Inventory',
                'Add New Medicine',
                'Add Stock / Receive Delivery',
                'View Stock Batches',
                'Messages',
                'Audit Log',
                'Controlled Substances',
            ])
            ->assertSee('Record sold medicines and deduct eligible stock automatically by FEFO.');

        self::assertSame(8, substr_count($html, $sharedActionClasses));

        foreach ([
            'pharmacy.sales.create',
            'pharmacy.inventory',
            'pharmacy.inventory.create',
            'pharmacy.receiving.create',
            'pharmacy.inventory.batches',
            'pharmacy.messages',
            'pharmacy.audit-log',
            'pharmacy.controlled-substances.create',
        ] as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }

        foreach ([
            ['text-blue-600', 'text-blue-200'],
            ['text-green-600', 'text-green-200'],
            ['text-purple-600', 'text-purple-200'],
            ['text-red-600', 'text-red-200'],
            ['text-cyan-600', 'text-cyan-200'],
            ['text-indigo-600', 'text-indigo-200'],
        ] as [$valueClass, $iconClass]) {
            self::assertStringContainsString("'value_class' => '{$valueClass}'", $dashboardSource);
            self::assertStringContainsString("'icon_class' => '{$iconClass}'", $dashboardSource);
        }

        self::assertStringNotContainsString("text-{{ \$stat['color'] }}", $dashboardSource);
    }

    public function test_sale_form_is_scoped_to_saleable_inventory_and_has_no_manual_batch_or_pos_inputs(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 09:00:00');
        [, $pharmacy] = $this->ownerAndPharmacy();
        [, $otherPharmacy] = $this->ownerAndPharmacy();
        $staff = User::factory()->create([
            'role' => 'pharmacy_operator',
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Alex Staff',
        ]);
        $own = $this->aggregate($pharmacy, 'Own Available Medicine', 5);
        $foreign = $this->aggregate($otherPharmacy, 'Foreign Secret Medicine', 8);
        $expiredOnly = $this->aggregate($pharmacy, 'Expired Only Medicine', 0);
        $this->batch($own, 'OWN', 5, '2025-07-01');
        $this->batch($foreign, 'FOREIGN', 8, '2025-07-01');
        $this->batch($expiredOnly, 'EXPIRED', 9, '2025-06-09');

        $response = $this->actingAs($staff)->get(route('pharmacy.sales.create'));

        $response->assertOk()
            ->assertSee('Own Available Medicine')
            ->assertDontSee('Foreign Secret Medicine')
            ->assertDontSee('Expired Only Medicine')
            ->assertSee('SALE-20250610-090000-')
            ->assertSee('Jun 10, 2025 9:00 AM')
            ->assertSee('href="'.route('pharmacy.dashboard').'"', false)
            ->assertSee('aria-label="Back to Pharmacy Dashboard"', false)
            ->assertSee('name="items[0][inventory_item_id]"', false)
            ->assertSee('name="items[0][quantity]"', false)
            ->assertSee('name="notes"', false)
            ->assertDontSee('name="sale_reference"', false)
            ->assertDontSee('name="server_timestamp"', false)
            ->assertDontSee('batch_number', false)
            ->assertDontSee('name="payment"', false)
            ->assertDontSee('name="discount"', false)
            ->assertDontSee('name="customer"', false);
    }

    public function test_sale_medicine_selectors_render_searchable_combobox_contract(): void
    {
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $medicine = Medicine::factory()->create([
            'medicine_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
            'dosage' => '500 mg',
        ]);
        $aggregate = InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'stockQuantity' => 12,
            'price' => '10.00',
        ]);
        $this->batch($aggregate, 'SEARCHABLE', 12, '2099-07-01');

        $response = $this->actingAs($owner)->get(route('pharmacy.sales.create'));
        $html = (string) $response->getContent();
        $expectedLabel = 'Amoxicillin — Amoxil — 500 mg (Available: 12)';

        $response->assertOk()
            ->assertSee($expectedLabel)
            ->assertSee('data-search="'.$expectedLabel.'"', false)
            ->assertSee('name="items[0][inventory_item_id]"', false)
            ->assertSee('name="items[__INDEX__][inventory_item_id]"', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('aria-controls="items_0_inventory_item_listbox"', false)
            ->assertSee('role="listbox"', false)
            ->assertSee('role="option"', false)
            ->assertSee('aria-activedescendant=""', false)
            ->assertSee('No medicines match your search.')
            ->assertSee("initializeMedicineCombobox(addedRow.querySelector('[data-medicine-combobox]'))", false)
            ->assertDontSee('name="items[0][inventory_batch_id]"', false)
            ->assertDontSee('name="items[__INDEX__][inventory_batch_id]"', false);

        preg_match_all('/<input\\b[^>]*data-medicine-combobox-input[^>]*>/i', $html, $searchInputs);
        self::assertCount(2, $searchInputs[0], 'The initial and template combobox search inputs should both render.');
        foreach ($searchInputs[0] as $searchInput) {
            self::assertSame(0, preg_match('/\\sname\\s*=/i', $searchInput), 'Visible search text must never be submitted as an inventory ID.');
        }
    }

    public function test_arbitrary_combobox_text_is_not_accepted_as_an_inventory_identifier(): void
    {
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $aggregate = $this->aggregate($pharmacy, 'Selection Required Medicine', 5);
        $batch = $this->batch($aggregate, 'SELECTION-REQUIRED', 5, '2099-07-01');

        $this->actingAs($owner)
            ->from(route('pharmacy.sales.create'))
            ->post(route('pharmacy.sales.store'), [
                'items' => [[
                    'inventory_item_id' => 'Selection Required Medicine',
                    'quantity' => 1,
                ]],
            ])
            ->assertRedirect(route('pharmacy.sales.create'))
            ->assertSessionHasErrors('items.0.inventory_item_id');

        self::assertSame(5, $batch->fresh()->current_quantity);
        self::assertSame(5, $aggregate->fresh()->stockQuantity);
        self::assertDatabaseCount('stock_movements', 0);
        self::assertDatabaseCount('inventory_audits', 0);
    }

    public function test_submitted_batch_selection_is_rejected_without_changing_stock(): void
    {
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $aggregate = $this->aggregate($pharmacy, 'Automatic FEFO Medicine', 5);
        $batch = $this->batch($aggregate, 'AUTOMATIC', 5, '2025-07-01');
        $items = [[
            'inventory_item_id' => $aggregate->id,
            'quantity' => 2,
            'inventory_batch_id' => $batch->id,
        ]];

        $this->actingAs($owner)
            ->from(route('pharmacy.sales.create'))
            ->post(route('pharmacy.sales.store'), ['items' => $items])
            ->assertRedirect(route('pharmacy.sales.create'))
            ->assertSessionHasErrors('items.0.inventory_batch_id')
            ->assertSessionHasInput('items', $items);

        self::assertSame(5, $batch->fresh()->current_quantity);
        self::assertSame(5, $aggregate->fresh()->stockQuantity);
        self::assertDatabaseCount('stock_movements', 0);
        self::assertDatabaseCount('inventory_audits', 0);
    }

    public function test_valid_multi_item_post_records_the_sale_and_returns_its_reference(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 10:00:00');
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $first = $this->aggregate($pharmacy, 'First Medicine', 4);
        $second = $this->aggregate($pharmacy, 'Second Medicine', 6);
        $firstBatch = $this->batch($first, 'FIRST', 4, '2025-07-01');
        $secondBatch = $this->batch($second, 'SECOND', 6, '2025-08-01');

        $response = $this->actingAs($owner)->post(route('pharmacy.sales.store'), [
            'items' => [
                ['inventory_item_id' => $first->id, 'quantity' => 1],
                ['inventory_item_id' => $second->id, 'quantity' => 2],
            ],
            'notes' => 'Walk-in stock deduction',
        ]);

        $response->assertRedirect(route('pharmacy.sales.create'))
            ->assertSessionHas('success')
            ->assertSessionHas('sale_reference');
        self::assertSame(3, $firstBatch->fresh()->current_quantity);
        self::assertSame(4, $secondBatch->fresh()->current_quantity);
        self::assertSame(3, $first->fresh()->stockQuantity);
        self::assertSame(4, $second->fresh()->stockQuantity);
        self::assertSame(2, StockMovement::query()->count());
    }

    public function test_duplicate_and_invalid_fields_return_indexed_errors_and_restore_all_rows(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 09:00:00');
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $aggregate = $this->aggregate($pharmacy, 'Recoverable Medicine', 5);
        $this->batch($aggregate, 'RECOVER', 5, '2025-07-01');
        $payload = [
            'items' => [
                2 => ['inventory_item_id' => $aggregate->id, 'quantity' => 0],
                7 => ['inventory_item_id' => $aggregate->id, 'quantity' => 2],
            ],
            'notes' => 'Keep this note',
        ];

        $response = $this->actingAs($owner)
            ->from(route('pharmacy.sales.create'))
            ->post(route('pharmacy.sales.store'), $payload);

        $response->assertRedirect(route('pharmacy.sales.create'))
            ->assertSessionHasErrors([
                'items.0.quantity',
                'items.1.inventory_item_id',
            ])
            ->assertSessionHasInput('items', $payload['items'])
            ->assertSessionHasInput('notes', 'Keep this note');

        $restoredResponse = $this->get(route('pharmacy.sales.create'));
        $restoredHtml = (string) $restoredResponse->getContent();

        $restoredResponse->assertOk()
            ->assertSee('name="items[0][inventory_item_id]"', false)
            ->assertSee('name="items[1][inventory_item_id]"', false)
            ->assertSee('id="items_0_inventory_item_search"', false)
            ->assertSee('id="items_1_inventory_item_search"', false)
            ->assertSee('name="items[0][quantity]"', false)
            ->assertSee('value="0"', false)
            ->assertSee('name="items[1][quantity]"', false)
            ->assertSee('value="2"', false)
            ->assertSee('Keep this note');

        self::assertSame(2, substr_count($restoredHtml, 'value="'.$aggregate->id.'" selected'));
        self::assertMatchesRegularExpression(
            '/id="items_1_inventory_item_search"[^>]*border-red-500/',
            $restoredHtml,
            'The duplicate row combobox should retain its indexed validation styling.',
        );
    }

    public function test_foreign_aggregate_is_not_found_and_rolls_back_an_earlier_owned_line(): void
    {
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        [, $otherPharmacy] = $this->ownerAndPharmacy();
        $own = $this->aggregate($pharmacy, 'Owned Medicine', 5);
        $foreign = $this->aggregate($otherPharmacy, 'Foreign Medicine', 5);
        $ownBatch = $this->batch($own, 'OWNED', 5, '2025-07-01');
        $foreignBatch = $this->batch($foreign, 'FOREIGN', 5, '2025-07-01');

        $this->actingAs($owner)->post(route('pharmacy.sales.store'), [
            'items' => [
                ['inventory_item_id' => $own->id, 'quantity' => 2],
                ['inventory_item_id' => $foreign->id, 'quantity' => 1],
            ],
        ])->assertNotFound();

        self::assertSame(5, $ownBatch->fresh()->current_quantity);
        self::assertSame(5, $foreignBatch->fresh()->current_quantity);
        self::assertSame(5, $own->fresh()->stockQuantity);
        self::assertDatabaseCount('stock_movements', 0);
        self::assertDatabaseCount('inventory_audits', 0);
    }

    public function test_insufficient_later_line_returns_requested_and_available_values_with_old_input(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 09:00:00');
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $first = $this->aggregate($pharmacy, 'Enough Medicine', 4);
        $second = $this->aggregate($pharmacy, 'Short Medicine', 1);
        $firstBatch = $this->batch($first, 'ENOUGH', 4, '2025-07-01');
        $secondBatch = $this->batch($second, 'SHORT', 1, '2025-07-01');
        $items = [
            ['inventory_item_id' => $first->id, 'quantity' => 3],
            ['inventory_item_id' => $second->id, 'quantity' => 3],
        ];

        $response = $this->actingAs($owner)
            ->from(route('pharmacy.sales.create'))
            ->post(route('pharmacy.sales.store'), ['items' => $items, 'notes' => 'Retry me']);

        $response->assertRedirect(route('pharmacy.sales.create'))
            ->assertSessionHasErrors('items.1.quantity')
            ->assertSessionHasInput('items', $items)
            ->assertSessionHasInput('notes', 'Retry me');
        self::assertStringContainsString(
            'requested 3, but only 1 is currently available',
            (string) session('errors')->first('items.1.quantity'),
        );
        self::assertSame(4, $firstBatch->fresh()->current_quantity);
        self::assertSame(1, $secondBatch->fresh()->current_quantity);
        self::assertSame(4, $first->fresh()->stockQuantity);
        self::assertSame(1, $second->fresh()->stockQuantity);
        self::assertDatabaseCount('stock_movements', 0);
        self::assertDatabaseCount('inventory_audits', 0);
    }

    public function test_empty_or_expired_catalog_has_a_clear_add_stock_path(): void
    {
        CarbonImmutable::setTestNow('2025-06-10 09:00:00');
        [$owner, $pharmacy] = $this->ownerAndPharmacy();
        $expired = $this->aggregate($pharmacy, 'Expired Medicine', 0);
        $this->batch($expired, 'EXPIRED-ONLY', 5, '2025-06-09');

        $this->actingAs($owner)
            ->get(route('pharmacy.sales.create'))
            ->assertOk()
            ->assertSee('No medicines are currently available to sell.')
            ->assertSee('Receive a non-expired stock batch before recording a sale.')
            ->assertSee('href="'.route('pharmacy.receiving.create').'"', false)
            ->assertDontSee('name="items[0][inventory_item_id]"', false);
    }

    /** @return array{0: User, 1: Pharmacy} */
    private function ownerAndPharmacy(): array
    {
        $owner = User::factory()->create(['role' => 'pharmacy']);
        $pharmacy = Pharmacy::factory()->withOwner($owner)->create();
        $owner->update(['pharmacy_id' => $pharmacy->id]);

        return [$owner, $pharmacy];
    }

    private function aggregate(Pharmacy $pharmacy, string $name, int $available): InventoryItem
    {
        return InventoryItem::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => Medicine::factory()->create(['medicine_name' => $name])->id,
            'stockQuantity' => $available,
            'price' => '10.00',
        ]);
    }

    private function batch(
        InventoryItem $aggregate,
        string $batchNumber,
        int $quantity,
        ?string $expiryDate,
    ): InventoryBatch {
        return InventoryBatch::factory()->create([
            'inventory_item_id' => $aggregate->id,
            'batch_number' => $batchNumber,
            'lot_number' => null,
            'identity_key' => BatchIdentity::key($batchNumber, null),
            'quantity_received' => $quantity,
            'current_quantity' => $quantity,
            'price' => '10.00',
            'expiry_date' => $expiryDate,
            'received_date' => '2025-05-01',
        ]);
    }
}
