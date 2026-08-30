<?php

namespace App\Domain\Inventory\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class BasicSaleResult
{
    /**
     * @param  Collection<int, StockOperationResult>  $operations
     */
    public function __construct(
        public string $operationId,
        public string $saleReference,
        public CarbonImmutable $recordedAt,
        public Collection $operations,
    ) {}
}
