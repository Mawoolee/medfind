<?php

namespace App\Domain\Inventory\Data;

use Carbon\CarbonImmutable;

final readonly class BatchReceiptData
{
    public function __construct(
        public string $batchNumber,
        public ?string $lotNumber,
        public int $quantityReceived,
        public string $price,
        public ?string $supplierName = null,
        public ?int $supplierId = null,
        public ?CarbonImmutable $expiryDate = null,
        public bool $coldChain = false,
        public ?CarbonImmutable $receivedDate = null,
        public ?string $receivedReference = null,
        public ?int $createdBy = null,
    ) {}
}
