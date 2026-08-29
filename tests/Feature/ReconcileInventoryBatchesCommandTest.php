<?php

namespace Tests\Feature;

use App\Domain\Inventory\BatchIdentity;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileInventoryBatchesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_command_reconciles_in_chunks_for_the_current_application_date_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-11 00:10:00', config('app.timezone')));

        $withBatches = InventoryItem::factory()->create([
            'stockQuantity' => 99,
            'price' => '1.00',
            'status' => 'available',
            'expiry_date' => '2030-01-01',
            'par_level' => 4,
        ]);
        $empty = InventoryItem::factory()->create([
            'stockQuantity' => 8,
            'price' => '9.00',
            'status' => 'available',
            'expiry_date' => '2030-01-01',
        ]);

        $this->batch($withBatches, 'EXPIRED', 7, '25.00', '2025-01-10', '2025-01-01');
        $this->batch($withBatches, 'AVAILABLE', 3, '18.50', '2025-02-01', '2025-01-02');

        $this->artisan('inventory:reconcile-batches', ['--chunk' => 1])
            ->expectsOutput('Reconciled 2 inventory aggregates; updated 2.')
            ->assertSuccessful();

        $withBatches->refresh();
        $empty->refresh();

        $this->assertSame(3, $withBatches->stockQuantity);
        $this->assertSame('low_stock', $withBatches->status);
        $this->assertSame('18.50', number_format((float) $withBatches->price, 2, '.', ''));
        $this->assertSame('2025-02-01', $withBatches->expiry_date->toDateString());
        $this->assertSame(0, $empty->stockQuantity);
        $this->assertSame('out_of_stock', $empty->status);
        $this->assertNull($empty->expiry_date);

        $this->artisan('inventory:reconcile-batches', ['--chunk' => 2])
            ->expectsOutput('Reconciled 2 inventory aggregates; updated 0.')
            ->assertSuccessful();
    }

    public function test_command_rejects_a_non_positive_chunk_size_before_changing_inventory(): void
    {
        $aggregate = InventoryItem::factory()->create(['stockQuantity' => 12]);

        $this->artisan('inventory:reconcile-batches', ['--chunk' => 0])
            ->expectsOutputToContain('The --chunk option must be a positive integer.')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(12, $aggregate->fresh()->stockQuantity);
    }

    public function test_command_is_scheduled_once_daily_after_midnight_in_the_application_timezone(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'inventory:reconcile-batches'));

        $this->assertCount(1, $events);

        $event = $events->first();

        $this->assertSame('5 0 * * *', $event->expression);
        $this->assertSame(config('app.timezone'), $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    private function batch(
        InventoryItem $aggregate,
        string $batchNumber,
        int $quantity,
        string $price,
        ?string $expiryDate,
        string $receivedDate,
    ): InventoryBatch {
        return InventoryBatch::factory()->create([
            'inventory_item_id' => $aggregate->id,
            'batch_number' => $batchNumber,
            'lot_number' => null,
            'identity_key' => BatchIdentity::key($batchNumber, null),
            'quantity_received' => $quantity,
            'current_quantity' => $quantity,
            'price' => $price,
            'expiry_date' => $expiryDate,
            'received_date' => $receivedDate,
        ]);
    }
}
