<?php

namespace App\Domain\Inventory;

/**
 * Explicit caller intent for AggregateSynchronizer projection writes.
 *
 * The synchronizer cannot infer whether an enclosing stock operation will
 * record its own InventoryAudit, so callers must state which path they are on.
 */
enum SynchronizationOrigin: string
{
    /**
     * Synchronization runs inside a stock operation that records its own
     * StockMovement and InventoryAudit entries through StockOperationRecorder.
     * The synchronizer must not write an audit here or the operation would be
     * audited twice.
     */
    case StockOperation = 'stock_operation';

    /**
     * Synchronization runs on its own, without an enclosing stock operation:
     * the scheduled reconciliation command, a manual reconcile, or a drift
     * correction. No other component records the resulting availability
     * change, so the synchronizer must audit it itself.
     */
    case Reconciliation = 'reconciliation';

    public function recordsOwnAudit(): bool
    {
        return $this === self::Reconciliation;
    }
}
