<?php

namespace Tests\Unit;

use App\Domain\Inventory\Data\BatchMetadataData;
use App\Domain\Inventory\Data\BatchReceiptData;
use App\Domain\Inventory\Data\StockOperationContext;
use App\Domain\Inventory\Exceptions\InsufficientAvailableStock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class StockDomainDataTest extends TestCase
{
    public function test_receipt_and_metadata_dtos_preserve_typed_values(): void
    {
        $received = new BatchReceiptData(
            batchNumber: 'B-1',
            lotNumber: 'L-1',
            quantityReceived: 12,
            price: '19.95',
            expiryDate: CarbonImmutable::parse('2030-01-01'),
            coldChain: true,
        );
        $metadata = new BatchMetadataData('B-2', null, '20.00', receivedReference: 'DEL-2');

        self::assertSame(12, $received->quantityReceived);
        self::assertTrue($received->coldChain);
        self::assertSame('DEL-2', $metadata->receivedReference);
    }

    public function test_operation_context_generates_or_preserves_operation_identifier(): void
    {
        $generated = new StockOperationContext('receipt');
        $provided = new StockOperationContext('correction', operationId: 'operation-fixed');

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $generated->operationId);
        self::assertSame('operation-fixed', $provided->operationId);
    }

    public function test_insufficient_stock_exception_exposes_requested_and_available_values(): void
    {
        $exception = new InsufficientAvailableStock(10, 4);

        self::assertSame(10, $exception->requested);
        self::assertSame(4, $exception->available);
        self::assertStringContainsString('10', $exception->getMessage());
        self::assertStringContainsString('4', $exception->getMessage());
    }
}
