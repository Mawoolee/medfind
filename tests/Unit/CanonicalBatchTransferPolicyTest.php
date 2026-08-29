<?php

namespace Tests\Unit;

use App\Database\Migration\OneTimeMigrationUtility;
use App\Database\Migration\SourcePreparation;
use PHPUnit\Framework\TestCase;

final class CanonicalBatchTransferPolicyTest extends TestCase
{
    /** **Validates: Requirements 8.14, 13.3, 13.7** */
    public function test_batch_tables_and_fields_are_fully_classified_for_dependency_safe_transfer(): void
    {
        $transfer = OneTimeMigrationUtility::medFindTransferPolicy();
        $source = SourcePreparation::medFindSchemaPolicy();
        $order = array_keys($transfer);

        self::assertLessThan(array_search('inventory_batches', $order, true), array_search('inventory_items', $order, true));
        self::assertLessThan(array_search('stock_movements', $order, true), array_search('inventory_batches', $order, true));
        self::assertArrayHasKey('legacy_source_inventory_item_id', $transfer['inventory_batches']['columns']);
        self::assertArrayHasKey('quantity_delta', $transfer['stock_movements']['columns']);
        self::assertArrayHasKey('lot_number', $transfer['inventory_items']['columns']);
        self::assertArrayHasKey('brand_name', $transfer['medicines']['columns']);
        self::assertArrayHasKey('cold_chain_required', $transfer['medicines']['columns']);
        self::assertSame('authoritative', $source['inventory_batches']['classification']);
        self::assertSame('authoritative', $source['stock_movements']['classification']);
        self::assertContains('operation_id', $source['inventory_audits']['columns']);
        self::assertContains('operation_id', $source['controlled_substance_logs']['columns']);
    }
}
