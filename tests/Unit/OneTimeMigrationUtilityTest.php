<?php

namespace Tests\Unit;

use App\Database\Migration\OneTimeMigrationUtility;
use PDO;
use PHPUnit\Framework\TestCase;

final class OneTimeMigrationUtilityTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'medfind-transfer-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    /**
     * **Validates: Requirements 1.3, 2.3, 2.6, 3.1, 3.5, 3.6**
     */
    public function test_parameterized_transaction_preserves_ids_values_and_circular_relationships(): void
    {
        $source = $this->sourceFixture('1');
        $target = $this->targetFixture();
        $utility = new OneTimeMigrationUtility(batchSize: 1, policy: self::policy());
        $secret = 'secret-value-never-written';

        $manifest = $utility->transfer($source, $target, self::passingEvidence(), [
            'operator' => 'migration-owner',
            'db_password' => $secret,
            'connection_url' => 'postgresql://operator:'.$secret.'@database.invalid/medfind',
        ]);

        self::assertTrue($manifest['transfer_passed']);
        self::assertFalse($manifest['abort_required']);
        self::assertFalse($manifest['cutover_performed']);
        self::assertFalse($manifest['runtime_change_permitted']);
        self::assertFalse($manifest['target_application_traffic_permitted']);
        self::assertSame('pending_task_3_8_repair_and_verification', $manifest['sequence_status']['state']);

        foreach ($manifest['tables'] as $table) {
            self::assertTrue($table['equivalent']);
            self::assertSame($table['source'], $table['target']);
        }

        $owner = $target->query('SELECT id, pharmacy_id, password FROM users WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(10, (int) $owner['id']);
        self::assertSame(50, (int) $owner['pharmacy_id']);
        self::assertSame('opaque-password-hash', $owner['password']);
        self::assertSame(10, (int) $target->query('SELECT user_id FROM pharmacies WHERE id = 50')->fetchColumn());
        self::assertSame('123.40', (string) $target->query('SELECT price FROM inventory_items WHERE id = 700')->fetchColumn());
        self::assertSame('opaque-session-payload', $target->query("SELECT payload FROM sessions WHERE id = 'session-fixture-id'")->fetchColumn());
        self::assertSame(0, (int) $target->query('SELECT COUNT(*) FROM cache')->fetchColumn());
        self::assertSame(0, (int) $target->query('SELECT COUNT(*) FROM cache_locks')->fetchColumn());
        self::assertSame(1, (int) $target->query('SELECT COUNT(*) FROM migrations')->fetchColumn());

        $path = $this->directory.DIRECTORY_SEPARATOR.'transfer-manifest.json';
        $utility->writeManifest($path, $manifest);
        $written = file_get_contents($path);

        self::assertIsString($written);
        self::assertStringContainsString('[REDACTED]', $written);
        self::assertStringContainsString('[REDACTED_URL]', $written);
        self::assertStringNotContainsString($secret, $written);
        self::assertStringNotContainsString('opaque-password-hash', $written);
        self::assertStringNotContainsString('opaque-session-payload', $written);
        self::assertStringNotContainsString('database.invalid', $written);
    }

    /**
     * Property: every malformed typed source value rolls back the complete target transaction.
     *
     * **Validates: Requirements 1.3, 2.3, 3.1, 3.5**
     */
    public function test_generated_invalid_boolean_values_fail_closed_and_leave_source_and_target_unchanged(): void
    {
        foreach (['true', 'false', 'yes', '2', 2, -1] as $index => $invalidBoolean) {
            $source = $this->sourceFixture($invalidBoolean);
            $target = $this->targetFixture();
            $sourceBefore = $source->query('SELECT id, "requiresPrescription" FROM medicines ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $utility = new OneTimeMigrationUtility(batchSize: ($index % 3) + 1, policy: self::policy());

            $manifest = $utility->transfer($source, $target, self::passingEvidence());

            self::assertFalse($manifest['transfer_passed'], "Generated case {$index} must fail.");
            self::assertTrue($manifest['abort_required']);
            self::assertFalse($manifest['cutover_performed']);
            self::assertSame('invalid_boolean', $manifest['failure']['reason']);
            self::assertSame('medicines', $manifest['failure']['table']);
            self::assertSame('requiresPrescription', $manifest['failure']['column']);
            self::assertSame($sourceBefore, $source->query('SELECT id, "requiresPrescription" FROM medicines ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));

            foreach (array_keys(self::policy()) as $table) {
                self::assertSame(0, (int) $target->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn());
            }
        }
    }

    /**
     * **Validates: Requirements 1.3, 2.3, 3.1, 3.6**
     */
    public function test_missing_prerequisite_aborts_before_opening_a_target_transaction(): void
    {
        $source = $this->sourceFixture('1');
        $target = $this->targetFixture();
        $evidence = self::passingEvidence();
        $evidence['target_application_traffic_disabled'] = false;

        $manifest = (new OneTimeMigrationUtility(policy: self::policy()))->transfer($source, $target, $evidence);

        self::assertTrue($manifest['abort_required']);
        self::assertFalse($manifest['transfer_passed']);
        self::assertFalse($target->inTransaction());
        self::assertFalse($manifest['gates']['target_application_traffic_disabled']['passed']);
        self::assertSame(0, (int) $target->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    /**
     * @return array<string, array{primary_key: string, columns: array<string, array<string, mixed>>}>
     */
    private static function policy(): array
    {
        return [
            'users' => [
                'primary_key' => 'id',
                'columns' => [
                    'id' => ['type' => 'integer', 'nullable' => false],
                    'name' => ['type' => 'string', 'nullable' => false],
                    'pharmacy_id' => ['type' => 'integer', 'nullable' => true],
                    'password' => ['type' => 'opaque', 'nullable' => false],
                ],
            ],
            'pharmacies' => [
                'primary_key' => 'id',
                'columns' => [
                    'id' => ['type' => 'integer', 'nullable' => false],
                    'pharmacy_name' => ['type' => 'string', 'nullable' => false],
                    'user_id' => ['type' => 'integer', 'nullable' => true],
                    'requirements' => ['type' => 'json', 'nullable' => true],
                ],
            ],
            'medicines' => [
                'primary_key' => 'id',
                'columns' => [
                    'id' => ['type' => 'integer', 'nullable' => false],
                    'medicine_name' => ['type' => 'string', 'nullable' => false],
                    'requiresPrescription' => ['type' => 'boolean', 'nullable' => false],
                ],
            ],
            'inventory_items' => [
                'primary_key' => 'id',
                'columns' => [
                    'id' => ['type' => 'integer', 'nullable' => false],
                    'pharmacy_id' => ['type' => 'integer', 'nullable' => false],
                    'medicine_id' => ['type' => 'integer', 'nullable' => false],
                    'price' => ['type' => 'decimal', 'nullable' => false, 'precision' => 10, 'scale' => 2],
                    'expiry_date' => ['type' => 'date', 'nullable' => true],
                ],
            ],
            'notifications' => [
                'primary_key' => 'id',
                'columns' => [
                    'id' => ['type' => 'uuid', 'nullable' => false],
                    'data' => ['type' => 'opaque', 'nullable' => false],
                ],
            ],
            'sessions' => [
                'primary_key' => 'id',
                'columns' => [
                    'id' => ['type' => 'string', 'nullable' => false],
                    'payload' => ['type' => 'opaque', 'nullable' => false],
                    'last_activity' => ['type' => 'integer', 'nullable' => false],
                ],
            ],
        ];
    }

    private function sourceFixture(mixed $boolean): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, self::pdoOptions());
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, pharmacy_id INTEGER NULL, password TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE pharmacies (id INTEGER PRIMARY KEY, pharmacy_name TEXT NOT NULL, user_id INTEGER NULL, requirements TEXT NULL)');
        $pdo->exec('CREATE TABLE medicines (id INTEGER PRIMARY KEY, medicine_name TEXT NOT NULL, "requiresPrescription")');
        $pdo->exec('CREATE TABLE inventory_items (id INTEGER PRIMARY KEY, pharmacy_id INTEGER NOT NULL, medicine_id INTEGER NOT NULL, price TEXT NOT NULL, expiry_date TEXT NULL)');
        $pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, data TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY, payload TEXT NOT NULL, last_activity INTEGER NOT NULL)');

        $insertUser = $pdo->prepare('INSERT INTO users (id, name, pharmacy_id, password) VALUES (:id, :name, :pharmacy_id, :password)');
        $insertUser->execute(['id' => 10, 'name' => 'Owner — José 薬', 'pharmacy_id' => 50, 'password' => 'opaque-password-hash']);
        $pdo->prepare('INSERT INTO pharmacies (id, pharmacy_name, user_id, requirements) VALUES (:id, :name, :user_id, :requirements)')->execute([
            'id' => 50,
            'name' => 'Farmácia Fixture',
            'user_id' => 10,
            'requirements' => '{"z":1,"a":{"unicode":"✓"}}',
        ]);
        $medicine = $pdo->prepare('INSERT INTO medicines (id, medicine_name, "requiresPrescription") VALUES (:id, :name, :required)');
        $medicine->bindValue(':id', 300, PDO::PARAM_INT);
        $medicine->bindValue(':name', 'Medicine Fixture', PDO::PARAM_STR);
        $medicine->bindValue(':required', $boolean);
        $medicine->execute();
        $pdo->prepare('INSERT INTO inventory_items (id, pharmacy_id, medicine_id, price, expiry_date) VALUES (?, ?, ?, ?, ?)')
            ->execute([700, 50, 300, '123.40', '2030-02-28']);
        $pdo->prepare('INSERT INTO notifications (id, data) VALUES (?, ?)')
            ->execute(['12345678-1234-4abc-8def-1234567890ab', '{"opaque":"notification-data"}']);
        $pdo->prepare('INSERT INTO sessions (id, payload, last_activity) VALUES (?, ?, ?)')
            ->execute(['session-fixture-id', 'opaque-session-payload', 1_700_000_000]);
        $pdo->exec('PRAGMA query_only = ON');

        return $pdo;
    }

    private function targetFixture(): PDO
    {
        $pdo = new PostgreSqlTestPdo('sqlite::memory:', null, null, self::pdoOptions());
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, pharmacy_id INTEGER NULL, password TEXT NOT NULL, FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id))');
        $pdo->exec('CREATE TABLE pharmacies (id INTEGER PRIMARY KEY, pharmacy_name TEXT NOT NULL, user_id INTEGER NULL, requirements TEXT NULL, FOREIGN KEY (user_id) REFERENCES users(id))');
        $pdo->exec('CREATE TABLE medicines (id INTEGER PRIMARY KEY, medicine_name TEXT NOT NULL, "requiresPrescription" INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE inventory_items (id INTEGER PRIMARY KEY, pharmacy_id INTEGER NOT NULL, medicine_id INTEGER NOT NULL, price TEXT NOT NULL, expiry_date TEXT NULL, FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id), FOREIGN KEY (medicine_id) REFERENCES medicines(id))');
        $pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, data TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY, payload TEXT NOT NULL, last_activity INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT NOT NULL, batch INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE cache_locks (key TEXT PRIMARY KEY, owner TEXT NOT NULL, expiration INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO migrations (id, migration, batch) VALUES (1, 'canonical_fixture', 1)");

        return $pdo;
    }

    /**
     * @return array<int, mixed>
     */
    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function passingEvidence(): array
    {
        $checksum = str_repeat('a', 64);

        return [
            'preflight_passed' => true,
            'source_preparation_passed' => true,
            'backup_is_restorable' => true,
            'canonical_schema_created' => true,
            'canonical_migrations_verified' => true,
            'target_is_unaccepted' => true,
            'target_application_traffic_disabled' => true,
            'source_checksum_before' => $checksum,
            'source_checksum_after_preparation' => $checksum,
            'backup_reference' => 'vault://restricted/migration-backup',
            'backup_checksum_sha256' => str_repeat('b', 64),
            'session_mode' => 'transfer',
            'session_continuity_approved' => true,
            'app_key_retained' => true,
        ];
    }
}

final class PostgreSqlTestPdo extends PDO
{
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'pgsql';
        }

        return parent::getAttribute($attribute);
    }
}
