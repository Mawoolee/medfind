<?php

use App\Database\Migration\LegacyInventoryBackfill;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        (new LegacyInventoryBackfill)->run(
            DB::connection(),
            CarbonImmutable::today(config('app.timezone'))
        );
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('stock_movements')->where('type', 'backfill')->where('operation_id', 'like', 'legacy-backfill:%')->delete();
            DB::table('inventory_batches')->whereNotNull('legacy_source_inventory_item_id')->delete();
        });
    }
};
