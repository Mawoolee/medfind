<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DatabaseMigrationPreservationPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 2: Preservation - Existing Data, Behavior, and Test Isolation.
     *
     * This observation-first property uses only PHPUnit's SQLite :memory:
     * connection and a second disposable SQLite :memory: PDO. It establishes
     * the semantic baseline that the future PostgreSQL transfer must preserve.
     *
     * **Validates: Requirements 3.1, 3.3, 3.5, 3.6**
     *
     * @param  array<string, mixed>  $fixture
     */
    #[DataProvider('generatedAuthoritativeGraphs')]
    public function test_generated_authoritative_graphs_survive_a_disposable_round_trip(array $fixture): void
    {
        self::assertSame('testing', config('app.env'));
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        self::assertSame('', (string) (config('database.connections.sqlite.url') ?? ''));
        self::assertSame('sqlite', DB::connection()->getDriverName());
        self::assertNotContains('pgsql', array_keys(DB::getConnections()));

        $this->insertSourceGraph($fixture);

        $sourceBefore = $this->readSourceGraph();
        $sourceHashBefore = $this->canonicalGraphHash($sourceBefore);
        $target = $this->newDisposableTarget();

        $this->copyGraphWithParameterizedStatements($target, $sourceBefore);

        $targetGraph = $this->readTargetGraph($target);
        $sourceAfter = $this->readSourceGraph();

        self::assertSame($sourceHashBefore, $this->canonicalGraphHash($sourceAfter));
        self::assertSame($sourceHashBefore, $this->canonicalGraphHash($targetGraph));
        self::assertSame($this->primaryKeys($sourceBefore), $this->primaryKeys($targetGraph));

        $sourceUser = User::query()->findOrFail($fixture['users'][0]['id']);
        $sourcePharmacy = Pharmacy::query()->findOrFail($fixture['pharmacies'][0]['id']);
        self::assertSame($sourcePharmacy->id, $sourceUser->pharmacy?->id);
        self::assertSame($sourceUser->id, $sourcePharmacy->user?->id);
        self::assertSame('private/prescriptions/'.$fixture['token'].'.enc', $sourceBefore['messages'][0]['prescription_image']);
        self::assertTrue(password_verify('baseline-password', $sourceBefore['users'][0]['password']));
        self::assertTrue(password_verify('baseline-password', $targetGraph['users'][0]['password']));
        self::assertSame($sourceUser->isPharmacy(), (new User($targetGraph['users'][0]))->isPharmacy());

        self::assertSame(
            [
                'user_pharmacy' => $fixture['pharmacies'][0]['id'],
                'pharmacy_owner' => $fixture['users'][0]['id'],
                'inventory_pharmacy' => $fixture['pharmacies'][0]['id'],
                'inventory_medicine' => $fixture['medicines'][0]['id'],
                'inventory_supplier' => $fixture['suppliers'][0]['id'],
                'batch_inventory' => $fixture['inventory_items'][0]['id'],
                'batch_supplier' => $fixture['suppliers'][0]['id'],
                'movement_inventory' => $fixture['inventory_items'][0]['id'],
                'movement_batch' => $fixture['inventory_batches'][0]['id'],
                'message_consumer' => $fixture['users'][1]['id'],
                'message_pharmacy' => $fixture['pharmacies'][0]['id'],
            ],
            $this->targetRelationships($target)
        );

        $foreignKeyFailures = $target->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([], $foreignKeyFailures);
        self::assertNotContains('pgsql', array_keys(DB::getConnections()));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function generatedAuthoritativeGraphs(): iterable
    {
        for ($sample = 0; $sample < 12; $sample++) {
            $base = 100 + ($sample * 101);
            $createdAt = $sample % 2 === 0 ? '2024-02-29 23:59:59' : '2026-12-31 00:00:01';
            $nullable = $sample % 3 === 0 ? null : "optional-{$sample}";
            $requirements = $sample % 4 === 0
                ? null
                : json_encode([
                    'licenses' => ['FDA', "Unicode-✓-{$sample}"],
                    'cold_chain' => $sample % 2 === 0,
                    'nested' => ['z' => $sample, 'a' => null],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $attachments = $sample % 3 === 1
                ? null
                : json_encode([
                    ['path' => "private/attachments/{$sample}.enc", 'name' => "résumé-{$sample}.pdf"],
                    ['empty' => ''],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $password = password_hash('baseline-password', PASSWORD_BCRYPT, ['cost' => 4]);
            $token = hash('sha256', "sanitized-fixture-{$sample}");
            $uuid = sprintf('12345678-1234-4%03x-8%03x-%012x', $sample, $sample, $sample + 1);
            $inventoryId = $base + 3000;
            $batchId = $base + 3500;
            $stock = ($sample * 7) % 101;
            $price = $sample % 2 === 0 ? '0.00' : '999999.99';
            $batchNumber = $nullable ?? 'LEGACY-'.$inventoryId;
            $identityKey = $nullable === null
                ? 'legacy:'.$inventoryId
                : 'batch:'.mb_strtolower(trim($nullable), 'UTF-8').'|lot:';

            yield "graph with sparse ids {$sample}" => [[
                'token' => $token,
                'users' => [
                    [
                        'id' => $base,
                        'name' => "Pharmacist {$sample} — José 薬",
                        'email' => "pharmacist{$sample}@example.test",
                        'email_verified_at' => $createdAt,
                        'password' => $password,
                        'role' => 'pharmacy',
                        'pharmacy_id' => $base + 1000,
                        'remember_token' => $nullable,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                    [
                        'id' => $base + 17,
                        'name' => "Consumer {$sample}",
                        'email' => "consumer{$sample}@example.test",
                        'email_verified_at' => null,
                        'password' => $password,
                        'role' => 'consumer',
                        'pharmacy_id' => null,
                        'remember_token' => '',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ],
                ],
                'pharmacies' => [[
                    'id' => $base + 1000,
                    'pharmacy_name' => "Farmácia {$sample} — 薬局",
                    'pharmacyAddress' => $sample % 2 === 0 ? '' : "Address {$sample}",
                    'latitude' => '14.5995123',
                    'longitude' => '120.9842222',
                    'contactNumber' => "+63-555-{$sample}",
                    'status' => 'approved',
                    'logo_path' => $nullable === null ? null : "private/logos/{$token}.enc",
                    'requirements' => $requirements,
                    'user_id' => $base,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
                'medicines' => [[
                    'id' => $base + 2000,
                    'medicine_name' => "Amoxicillin {$sample} — β",
                    'brand_name' => $nullable,
                    'dosage' => $sample % 2 === 0 ? '0mg' : '500mg',
                    'manufacturer' => "Maker {$sample}",
                    'requiresPrescription' => $sample % 2,
                    'cold_chain_required' => $sample % 2,
                    'category' => $nullable,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
                'suppliers' => [[
                    'id' => $base + 2500,
                    'name' => "Supplier {$sample}",
                    'contact_person' => $nullable,
                    'phone' => '',
                    'email' => null,
                    'address' => "Warehouse {$sample}",
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
                'inventory_items' => [[
                    'id' => $inventoryId,
                    'pharmacy_id' => $base + 1000,
                    'medicine_id' => $base + 2000,
                    'stockQuantity' => $stock,
                    'price' => $price,
                    'expiry_date' => $nullable === null ? null : '2030-02-28',
                    'batch_number' => $nullable,
                    'lot_number' => $nullable === null ? null : "LOT-{$sample}",
                    'cold_chain' => $sample % 2,
                    'par_level' => $sample,
                    'supplier_id' => $base + 2500,
                    'status' => $sample % 2 === 0 ? 'available' : 'low_stock',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
                'inventory_batches' => [[
                    'id' => $batchId,
                    'inventory_item_id' => $inventoryId,
                    'legacy_source_inventory_item_id' => $inventoryId,
                    'batch_number' => $batchNumber,
                    'lot_number' => $nullable === null ? null : "LOT-{$sample}",
                    'identity_key' => $identityKey,
                    'quantity_received' => $stock,
                    'current_quantity' => $stock,
                    'price' => $price,
                    'supplier_id' => $base + 2500,
                    'supplier_name' => "Supplier {$sample}",
                    'expiry_date' => $nullable === null ? null : '2030-02-28',
                    'cold_chain' => $sample % 2,
                    'received_date' => substr($createdAt, 0, 10),
                    'received_reference' => 'legacy-inventory:'.$inventoryId,
                    'created_by' => $base,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
                'stock_movements' => [[
                    'id' => $base + 3600,
                    'operation_id' => 'legacy-backfill:'.$inventoryId,
                    'inventory_item_id' => $inventoryId,
                    'inventory_batch_id' => $batchId,
                    'type' => 'backfill',
                    'before_quantity' => 0,
                    'after_quantity' => $stock,
                    'quantity_delta' => $stock,
                    'reason' => 'Legacy inventory migration',
                    'reference_type' => 'inventory_item',
                    'reference_id' => (string) $inventoryId,
                    'received_reference' => 'legacy-inventory:'.$inventoryId,
                    'user_id' => $base,
                    'created_at' => $createdAt,
                ]],
                'messages' => [[
                    'id' => $base + 4000,
                    'consumer_id' => $base + 17,
                    'pharmacy_id' => $base + 1000,
                    'sender' => 'consumer',
                    'message' => "Need medicine {$sample}\nUnicode ✓",
                    'prescription_image' => "private/prescriptions/{$token}.enc",
                    'attachments' => $attachments,
                    'reply' => $nullable,
                    'replied_at' => $nullable === null ? null : $createdAt,
                    'is_read' => $sample % 2,
                    'verified_by' => $nullable === null ? null : $base,
                    'verification_status' => $nullable === null ? null : 'verified',
                    'verification_notes' => $nullable,
                    'verified_at' => $nullable === null ? null : $createdAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
                'notifications' => [[
                    'id' => $uuid,
                    'type' => 'SanitizedBaselineNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $base + 17,
                    'data' => json_encode(['sample' => $sample, 'nullable' => null, 'unicode' => '✓'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'read_at' => $nullable === null ? null : $createdAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]],
            ]];
        }
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function insertSourceGraph(array $fixture): void
    {
        $users = $fixture['users'];
        $ownerPharmacyId = $users[0]['pharmacy_id'];
        $users[0]['pharmacy_id'] = null;

        DB::table('users')->insert($users);
        DB::table('pharmacies')->insert($fixture['pharmacies']);
        DB::table('users')->where('id', $users[0]['id'])->update(['pharmacy_id' => $ownerPharmacyId]);
        DB::table('medicines')->insert($fixture['medicines']);
        DB::table('suppliers')->insert($fixture['suppliers']);
        DB::table('inventory_items')->insert($fixture['inventory_items']);
        DB::table('inventory_batches')->insert($fixture['inventory_batches']);
        DB::table('stock_movements')->insert($fixture['stock_movements']);
        DB::table('messages')->insert($fixture['messages']);
        DB::table('notifications')->insert($fixture['notifications']);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function readSourceGraph(): array
    {
        $graph = [];

        foreach (array_keys(self::tableColumns()) as $table) {
            $graph[$table] = DB::table($table)
                ->orderBy(self::primaryKeyFor($table))
                ->get(self::tableColumns()[$table])
                ->map(static fn ($row): array => (array) $row)
                ->all();
        }

        return $graph;
    }

    private function newDisposableTarget(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, email_verified_at TEXT NULL, password TEXT NOT NULL, role TEXT NOT NULL, pharmacy_id INTEGER NULL, remember_token TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL, FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id))');
        $pdo->exec('CREATE TABLE pharmacies (id INTEGER PRIMARY KEY, pharmacy_name TEXT NOT NULL, pharmacyAddress TEXT NOT NULL, latitude NUMERIC NOT NULL, longitude NUMERIC NOT NULL, contactNumber TEXT NOT NULL, status TEXT NOT NULL, logo_path TEXT NULL, requirements TEXT NULL, user_id INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL, FOREIGN KEY (user_id) REFERENCES users(id))');
        $pdo->exec('CREATE TABLE medicines (id INTEGER PRIMARY KEY, medicine_name TEXT NOT NULL, brand_name TEXT NULL, dosage TEXT NOT NULL, manufacturer TEXT NOT NULL, requiresPrescription INTEGER NOT NULL, cold_chain_required INTEGER NOT NULL, category TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec('CREATE TABLE suppliers (id INTEGER PRIMARY KEY, name TEXT NOT NULL, contact_person TEXT NULL, phone TEXT NULL, email TEXT NULL, address TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec('CREATE TABLE inventory_items (id INTEGER PRIMARY KEY, pharmacy_id INTEGER NOT NULL, medicine_id INTEGER NOT NULL, stockQuantity INTEGER NOT NULL, price NUMERIC NOT NULL, expiry_date TEXT NULL, batch_number TEXT NULL, lot_number TEXT NULL, cold_chain INTEGER NOT NULL, par_level INTEGER NOT NULL, supplier_id INTEGER NULL, status TEXT NOT NULL, created_at TEXT NULL, updated_at TEXT NULL, FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id), FOREIGN KEY (medicine_id) REFERENCES medicines(id), FOREIGN KEY (supplier_id) REFERENCES suppliers(id), UNIQUE (pharmacy_id, medicine_id))');
        $pdo->exec('CREATE TABLE inventory_batches (id INTEGER PRIMARY KEY, inventory_item_id INTEGER NOT NULL, legacy_source_inventory_item_id INTEGER NULL UNIQUE, batch_number TEXT NOT NULL, lot_number TEXT NULL, identity_key TEXT NOT NULL, quantity_received INTEGER NOT NULL, current_quantity INTEGER NOT NULL, price NUMERIC NOT NULL, supplier_id INTEGER NULL, supplier_name TEXT NULL, expiry_date TEXT NULL, cold_chain INTEGER NOT NULL, received_date TEXT NOT NULL, received_reference TEXT NULL, created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL, FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id), FOREIGN KEY (legacy_source_inventory_item_id) REFERENCES inventory_items(id), FOREIGN KEY (supplier_id) REFERENCES suppliers(id), FOREIGN KEY (created_by) REFERENCES users(id), UNIQUE (inventory_item_id, identity_key))');
        $pdo->exec('CREATE TABLE stock_movements (id INTEGER PRIMARY KEY, operation_id TEXT NOT NULL, inventory_item_id INTEGER NOT NULL, inventory_batch_id INTEGER NOT NULL, type TEXT NOT NULL, before_quantity INTEGER NOT NULL, after_quantity INTEGER NOT NULL, quantity_delta INTEGER NOT NULL, reason TEXT NULL, reference_type TEXT NULL, reference_id TEXT NULL, received_reference TEXT NULL, user_id INTEGER NULL, created_at TEXT NULL, FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id), FOREIGN KEY (inventory_batch_id) REFERENCES inventory_batches(id), FOREIGN KEY (user_id) REFERENCES users(id))');
        $pdo->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY, consumer_id INTEGER NOT NULL, pharmacy_id INTEGER NOT NULL, sender TEXT NULL, message TEXT NOT NULL, prescription_image TEXT NULL, attachments TEXT NULL, reply TEXT NULL, replied_at TEXT NULL, is_read INTEGER NOT NULL, verified_by INTEGER NULL, verification_status TEXT NULL, verification_notes TEXT NULL, verified_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL, FOREIGN KEY (consumer_id) REFERENCES users(id), FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id), FOREIGN KEY (verified_by) REFERENCES users(id))');
        $pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT NOT NULL, notifiable_type TEXT NOT NULL, notifiable_id INTEGER NOT NULL, data TEXT NOT NULL, read_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        return $pdo;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $graph
     */
    private function copyGraphWithParameterizedStatements(PDO $target, array $graph): void
    {
        $target->beginTransaction();

        try {
            $ownerPharmacyIds = [];
            foreach ($graph['users'] as $user) {
                $ownerPharmacyIds[$user['id']] = $user['pharmacy_id'];
                $user['pharmacy_id'] = null;
                $this->insertTargetRow($target, 'users', $user);
            }

            foreach (['pharmacies', 'medicines', 'suppliers'] as $table) {
                foreach ($graph[$table] as $row) {
                    $this->insertTargetRow($target, $table, $row);
                }
            }

            $restore = $target->prepare('UPDATE users SET pharmacy_id = :pharmacy_id WHERE id = :id');
            foreach ($ownerPharmacyIds as $userId => $pharmacyId) {
                $restore->execute(['pharmacy_id' => $pharmacyId, 'id' => $userId]);
            }

            foreach (['inventory_items', 'inventory_batches', 'stock_movements', 'messages', 'notifications'] as $table) {
                foreach ($graph[$table] as $row) {
                    $this->insertTargetRow($target, $table, $row);
                }
            }

            $target->commit();
        } catch (\Throwable $exception) {
            $target->rollBack();
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertTargetRow(PDO $target, string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_map(static fn (string $column): string => ':'.$column, $columns);
        $statement = $target->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));
        $statement->execute($row);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function readTargetGraph(PDO $target): array
    {
        $graph = [];

        foreach (array_keys(self::tableColumns()) as $table) {
            $columns = implode(', ', self::tableColumns()[$table]);
            $key = self::primaryKeyFor($table);
            $graph[$table] = $target->query("SELECT {$columns} FROM {$table} ORDER BY {$key}")->fetchAll();
        }

        return $graph;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $graph
     */
    private function canonicalGraphHash(array $graph): string
    {
        $canonical = [];

        foreach ($graph as $table => $rows) {
            foreach ($rows as $row) {
                $canonical[$table][] = $this->canonicalRow($table, $row);
            }
        }

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array{type: string, value?: mixed}>
     */
    private function canonicalRow(string $table, array $row): array
    {
        $canonical = [];

        foreach (self::columnTypes()[$table] as $column => $type) {
            $value = $row[$column];

            if ($value === null) {
                $canonical[$column] = ['type' => 'null'];

                continue;
            }

            $canonical[$column] = ['type' => $type, 'value' => match ($type) {
                'integer' => (string) (int) $value,
                'boolean' => in_array($value, [true, 1, '1'], true),
                'decimal:2' => number_format((float) $value, 2, '.', ''),
                'decimal:7' => number_format((float) $value, 7, '.', ''),
                'json' => $this->canonicalJson((string) $value),
                default => (string) $value,
            }];
        }

        return $canonical;
    }

    private function canonicalJson(string $json): mixed
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $this->sortJson($decoded);
    }

    private function sortJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->sortJson($item), $value);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $graph
     * @return array<string, array<int, int|string>>
     */
    private function primaryKeys(array $graph): array
    {
        $keys = [];

        foreach ($graph as $table => $rows) {
            $primaryKey = self::primaryKeyFor($table);
            $keys[$table] = array_map(static fn (array $row): int|string => $row[$primaryKey], $rows);
        }

        return $keys;
    }

    /**
     * @return array<string, int>
     */
    private function targetRelationships(PDO $target): array
    {
        $user = $target->query("SELECT id, pharmacy_id FROM users WHERE role = 'pharmacy'")->fetch();
        $consumer = $target->query("SELECT id FROM users WHERE role = 'consumer'")->fetch();
        $pharmacy = $target->query('SELECT id, user_id FROM pharmacies')->fetch();
        $inventory = $target->query('SELECT pharmacy_id, medicine_id, supplier_id FROM inventory_items')->fetch();
        $batch = $target->query('SELECT inventory_item_id, supplier_id FROM inventory_batches')->fetch();
        $movement = $target->query('SELECT inventory_item_id, inventory_batch_id FROM stock_movements')->fetch();
        $message = $target->query('SELECT consumer_id, pharmacy_id FROM messages')->fetch();

        return [
            'user_pharmacy' => (int) $user['pharmacy_id'],
            'pharmacy_owner' => (int) $pharmacy['user_id'],
            'inventory_pharmacy' => (int) $inventory['pharmacy_id'],
            'inventory_medicine' => (int) $inventory['medicine_id'],
            'inventory_supplier' => (int) $inventory['supplier_id'],
            'batch_inventory' => (int) $batch['inventory_item_id'],
            'batch_supplier' => (int) $batch['supplier_id'],
            'movement_inventory' => (int) $movement['inventory_item_id'],
            'movement_batch' => (int) $movement['inventory_batch_id'],
            'message_consumer' => (int) $message['consumer_id'],
            'message_pharmacy' => (int) $message['pharmacy_id'],
        ];
    }

    private static function primaryKeyFor(string $table): string
    {
        return 'id';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function tableColumns(): array
    {
        return array_map(static fn (array $types): array => array_keys($types), self::columnTypes());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function columnTypes(): array
    {
        return [
            'users' => [
                'id' => 'integer', 'name' => 'string', 'email' => 'string', 'email_verified_at' => 'timestamp',
                'password' => 'opaque', 'role' => 'string', 'pharmacy_id' => 'integer', 'remember_token' => 'opaque',
                'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
            'pharmacies' => [
                'id' => 'integer', 'pharmacy_name' => 'string', 'pharmacyAddress' => 'string', 'latitude' => 'decimal:7',
                'longitude' => 'decimal:7', 'contactNumber' => 'string', 'status' => 'string', 'logo_path' => 'path',
                'requirements' => 'json', 'user_id' => 'integer', 'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
            'medicines' => [
                'id' => 'integer', 'medicine_name' => 'string', 'brand_name' => 'string', 'dosage' => 'string', 'manufacturer' => 'string',
                'requiresPrescription' => 'boolean', 'cold_chain_required' => 'boolean', 'category' => 'string', 'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
            'suppliers' => [
                'id' => 'integer', 'name' => 'string', 'contact_person' => 'string', 'phone' => 'string',
                'email' => 'string', 'address' => 'string', 'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
            'inventory_items' => [
                'id' => 'integer', 'pharmacy_id' => 'integer', 'medicine_id' => 'integer', 'stockQuantity' => 'integer',
                'price' => 'decimal:2', 'expiry_date' => 'date', 'batch_number' => 'string', 'lot_number' => 'string', 'cold_chain' => 'boolean',
                'par_level' => 'integer', 'supplier_id' => 'integer', 'status' => 'string', 'created_at' => 'timestamp',
                'updated_at' => 'timestamp',
            ],
            'inventory_batches' => [
                'id' => 'integer', 'inventory_item_id' => 'integer', 'legacy_source_inventory_item_id' => 'integer',
                'batch_number' => 'string', 'lot_number' => 'string', 'identity_key' => 'string',
                'quantity_received' => 'integer', 'current_quantity' => 'integer', 'price' => 'decimal:2',
                'supplier_id' => 'integer', 'supplier_name' => 'string', 'expiry_date' => 'date', 'cold_chain' => 'boolean',
                'received_date' => 'date', 'received_reference' => 'string', 'created_by' => 'integer',
                'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
            'stock_movements' => [
                'id' => 'integer', 'operation_id' => 'string', 'inventory_item_id' => 'integer',
                'inventory_batch_id' => 'integer', 'type' => 'string', 'before_quantity' => 'integer',
                'after_quantity' => 'integer', 'quantity_delta' => 'integer', 'reason' => 'string',
                'reference_type' => 'string', 'reference_id' => 'string', 'received_reference' => 'string',
                'user_id' => 'integer', 'created_at' => 'timestamp',
            ],
            'messages' => [
                'id' => 'integer', 'consumer_id' => 'integer', 'pharmacy_id' => 'integer', 'sender' => 'string',
                'message' => 'opaque', 'prescription_image' => 'path', 'attachments' => 'json', 'reply' => 'opaque',
                'replied_at' => 'timestamp', 'is_read' => 'boolean', 'verified_by' => 'integer',
                'verification_status' => 'string', 'verification_notes' => 'opaque', 'verified_at' => 'timestamp',
                'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
            'notifications' => [
                'id' => 'uuid', 'type' => 'string', 'notifiable_type' => 'string', 'notifiable_id' => 'integer',
                'data' => 'json', 'read_at' => 'timestamp', 'created_at' => 'timestamp', 'updated_at' => 'timestamp',
            ],
        ];
    }
}
