<?php

use App\Domain\Inventory\BatchIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = $this->lockedRows();
            $targets = [];
            $transformed = [];

            foreach ($rows as $row) {
                $id = (int) $row->id;

                if (str_starts_with((string) $row->identity_key, 'legacy:')) {
                    $targets[$id] = (string) $row->identity_key;

                    continue;
                }

                try {
                    $targets[$id] = BatchIdentity::key(
                        (string) $row->batch_number,
                        $row->lot_number === null ? null : (string) $row->lot_number,
                    );
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "Unable to hash inventory batch identity for row {$id}.",
                        0,
                        $exception,
                    );
                }

                $transformed[$id] = true;
            }

            $this->assertUniqueTargets($rows, $targets);
            $this->applyTargets($rows, $targets, $transformed);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $rows = $this->lockedRows();
            $targets = [];
            $transformed = [];

            foreach ($rows as $row) {
                $id = (int) $row->id;
                $identityKey = (string) $row->identity_key;

                if (! str_starts_with($identityKey, BatchIdentity::DIGEST_PREFIX)) {
                    $targets[$id] = $identityKey;

                    continue;
                }

                $batch = BatchIdentity::normalize((string) $row->batch_number);
                if ($batch === '') {
                    throw new RuntimeException("Unable to restore plaintext identity for inventory batch row {$id}: batch number is blank.");
                }

                $lot = BatchIdentity::normalize($row->lot_number === null ? '' : (string) $row->lot_number);
                $plaintext = 'batch:'.$batch.'|lot:'.$lot;

                if (mb_strlen($plaintext, 'UTF-8') > 255) {
                    throw new RuntimeException(
                        "Cannot safely roll back inventory batch row {$id}: its plaintext identity exceeds 255 characters."
                    );
                }

                $targets[$id] = $plaintext;
                $transformed[$id] = true;
            }

            $this->assertUniqueTargets($rows, $targets);
            $this->applyTargets($rows, $targets, $transformed);
        });
    }

    private function lockedRows(): Collection
    {
        return DB::table('inventory_batches')
            ->select(['id', 'inventory_item_id', 'batch_number', 'lot_number', 'identity_key'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function assertUniqueTargets(Collection $rows, array $targets): void
    {
        $owners = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $target = $targets[$id];
            $scope = (int) $row->inventory_item_id."\0".mb_strtolower($target, 'UTF-8');

            if (isset($owners[$scope]) && $owners[$scope] !== $id) {
                throw new RuntimeException(
                    "Inventory batch identity collision between rows {$owners[$scope]} and {$id}; no identities were changed."
                );
            }

            $owners[$scope] = $id;
        }
    }

    /**
     * @param  array<int, string>  $targets
     * @param  array<int, bool>  $transformed
     */
    private function applyTargets(Collection $rows, array $targets, array $transformed): void
    {
        $pending = $rows
            ->filter(function (object $row) use ($targets, $transformed): bool {
                $id = (int) $row->id;

                return isset($transformed[$id]) && (string) $row->identity_key !== $targets[$id];
            })
            ->values();

        if ($pending->isEmpty()) {
            return;
        }

        do {
            $token = bin2hex(random_bytes(12));
            $temporaryPrefix = 'rekey:'.$token.':';
        } while (DB::table('inventory_batches')->where('identity_key', 'like', $temporaryPrefix.'%')->exists());

        $temporaryKeys = [];

        foreach ($pending as $row) {
            $id = (int) $row->id;
            $temporaryKeys[$id] = $temporaryPrefix.$id;
            $updated = DB::table('inventory_batches')
                ->where('id', $id)
                ->where('identity_key', (string) $row->identity_key)
                ->update(['identity_key' => $temporaryKeys[$id]]);

            if ($updated !== 1) {
                throw new RuntimeException("Inventory batch row {$id} changed during identity rekeying.");
            }
        }

        foreach ($pending as $row) {
            $id = (int) $row->id;
            $updated = DB::table('inventory_batches')
                ->where('id', $id)
                ->where('identity_key', $temporaryKeys[$id])
                ->update(['identity_key' => $targets[$id]]);

            if ($updated !== 1) {
                throw new RuntimeException("Unable to finalize identity rekeying for inventory batch row {$id}.");
            }
        }
    }
};
