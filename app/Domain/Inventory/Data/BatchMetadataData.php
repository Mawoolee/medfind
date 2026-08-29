<?php

namespace App\Domain\Inventory\Data;

use Carbon\CarbonImmutable;

final readonly class BatchMetadataData
{
    public function __construct(
        public string $batchNumber,
        public ?string $lotNumber,
        public string $price,
        public ?string $supplierName = null,
        public ?int $supplierId = null,
        public ?CarbonImmutable $expiryDate = null,
        public bool $coldChain = false,
        public ?CarbonImmutable $receivedDate = null,
        public ?string $receivedReference = null,
    ) {}
}
