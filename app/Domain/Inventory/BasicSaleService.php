<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\BasicSaleResult;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\ForeignInventoryRecord;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use App\Domain\Inventory\Exceptions\SaleLineInsufficientStock;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class BasicSaleService
{
    public function __construct(private readonly FEFOAllocator $allocator) {}

    /**
     * @param  array<int, array{inventory_item_id: int|string, quantity: int|string}>  $items
     */
    public function record(
        Pharmacy $pharmacy,
        User $actor,
        array $items,
        ?string $notes = null,
        ?string $saleReference = null,
    ): BasicSaleResult {
        $this->assertActorBelongsToPharmacy($pharmacy, $actor);
        $lines = $this->normalizeLines($items);
        $notes = $this->normalizeNotes($notes);
        $recordedAt = CarbonImmutable::now();
        $saleReference = trim((string) $saleReference);
        $saleReference = $saleReference === '' ? $this->newSaleReference($recordedAt) : $saleReference;
        $context = new StockOperationContext(
            type: 'sale',
            actorId: (int) $actor->getKey(),
            reason: $this->operationReason($saleReference, $notes),
            referenceType: 'sale',
            referenceId: $saleReference,
            receivedReference: $saleReference,
        );

        return DB::transaction(function () use (
            $pharmacy,
            $lines,
            $recordedAt,
            $saleReference,
            $context,
        ): BasicSaleResult {
            $aggregateIds = array_column($lines, 'inventory_item_id');
            $aggregates = InventoryItem::query()
                ->with('medicine')
                ->where('pharmacy_id', $pharmacy->getKey())
                ->whereKey($aggregateIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (InventoryItem $aggregate): int => (int) $aggregate->getKey());

            if ($aggregates->count() !== count($aggregateIds)) {
                throw new ForeignInventoryRecord;
            }

            $operations = new Collection;

            foreach ($lines as $lineIndex => $line) {
                /** @var InventoryItem $aggregate */
                $aggregate = $aggregates->get($line['inventory_item_id']);

                try {
                    $operations->push($this->allocator->decrease(
                        $pharmacy,
                        $aggregate,
                        $line['quantity'],
                        $context,
                    ));
                } catch (InsufficientAvailableStock $exception) {
                    throw new SaleLineInsufficientStock(
                        lineIndex: $lineIndex,
                        inventoryItemId: $line['inventory_item_id'],
                        medicineName: $aggregate->medicine?->medicine_name ?? 'Selected medicine',
                        requested: $exception->requested,
                        available: $exception->available,
                        previous: $exception,
                    );
                }
            }

            return new BasicSaleResult(
                operationId: $context->operationId,
                saleReference: $saleReference,
                recordedAt: $recordedAt,
                operations: $operations,
            );
        }, 3);
    }

    public function newSaleReference(?CarbonImmutable $at = null): string
    {
        $at ??= CarbonImmutable::now();

        return sprintf('SALE-%s-%s', $at->format('Ymd-His'), Str::upper((string) Str::ulid()));
    }

    private function assertActorBelongsToPharmacy(Pharmacy $pharmacy, User $actor): void
    {
        $actorPharmacyId = $actor->pharmacy_id;
        $isOwner = (int) $pharmacy->user_id === (int) $actor->getKey();
        $isAssignedStaff = $actorPharmacyId !== null
            && (int) $actorPharmacyId === (int) $pharmacy->getKey();

        if (! $actor->isPharmacy() || (! $isOwner && ! $isAssignedStaff)) {
            throw new ForeignInventoryRecord;
        }
    }

    /**
     * @param  array<int, array{inventory_item_id: int|string, quantity: int|string}>  $items
     * @return list<array{inventory_item_id: int, quantity: int}>
     */
    private function normalizeLines(array $items): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('A sale must contain at least one item.');
        }

        $lines = [];
        $seenAggregateIds = [];

        foreach (array_values($items) as $lineIndex => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Sale line '.($lineIndex + 1).' must be an item array.');
            }

            $aggregateId = filter_var(
                $item['inventory_item_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );
            $quantity = filter_var(
                $item['quantity'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            if ($aggregateId === false) {
                throw new InvalidArgumentException('Sale line '.($lineIndex + 1).' must select a valid inventory aggregate.');
            }

            if ($quantity === false) {
                throw new InvalidArgumentException('Sale line '.($lineIndex + 1).' quantity must be a positive integer.');
            }

            if (isset($seenAggregateIds[$aggregateId])) {
                throw new InvalidArgumentException('Sale line '.($lineIndex + 1).' duplicates an earlier medicine.');
            }

            $seenAggregateIds[$aggregateId] = true;
            $lines[] = [
                'inventory_item_id' => $aggregateId,
                'quantity' => $quantity,
            ];
        }

        return $lines;
    }

    private function normalizeNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > 1000) {
            throw new InvalidArgumentException('Sale notes may not exceed 1000 characters.');
        }

        return $notes;
    }

    private function operationReason(string $saleReference, ?string $notes): string
    {
        $reason = "Recorded basic sale {$saleReference}.";

        return $notes === null ? $reason : "{$reason} Notes: {$notes}";
    }
}
