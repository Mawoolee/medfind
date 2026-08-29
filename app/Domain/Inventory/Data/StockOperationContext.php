<?php

namespace App\Domain\Inventory\Data;

use Illuminate\Support\Str;

final readonly class StockOperationContext
{
    public string $operationId;

    public function __construct(
        public string $type,
        public ?int $actorId = null,
        public ?string $reason = null,
        public ?string $referenceType = null,
        public int|string|null $referenceId = null,
        public ?string $receivedReference = null,
        ?string $operationId = null,
    ) {
        $this->operationId = $operationId ?? (string) Str::uuid();
    }
}
