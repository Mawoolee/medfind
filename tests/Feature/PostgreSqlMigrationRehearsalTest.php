<?php

namespace Tests\Feature;

use App\Database\Migration\LegacyInventoryBackfill;
use App\Database\Migration\MigrationPreflight;
use App\Database\Migration\OneTimeMigrationUtility;
use App\Database\Migration\SourcePreparation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class PostgreSqlMigrationRehearsalTest extends TestCase
{
    private const CONNECTION = 'rehearsal_pgsql';

    private string $host;

    private int $port;

    private string $username;

    private string $pgBin;

    private string $workDirectory;

    private PDO $admin;

    /** @var array<int, string> */
    private array $databases = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('MEDFIND_REHEARSAL_DISPOSABLE') !== '1') {
            self::markTestSkipped('Set MEDFIND_REHEARSAL_DISPOSABLE=1 only for a proven disposable PostgreSQL cluster.');
        }

        $this->host = $this->requiredEnvironment('MEDFIND_REHEARSAL_HOST');
        $this->port = (int) $this->requiredEnvironment('MEDFIND_REHEARSAL_PORT');
        $this->username = $this->requiredEnvironment('MEDFIND_REHEARSAL_USERNAME');
        $this->pgBin = rtrim($this->requiredEnvironment('MEDFIND_REHEARSAL_PG_BIN'), '\\/');
        $this->workDirectory = $this->requiredEnvironment('MEDFIND_REHEARSAL_WORKDIR');

        self::assertSame('127.0.0.1', $this->host, 'The rehearsal accepts only an IPv4 loopback server.');
        self::assertNotSame(5432, $this->port, 'The default PostgreSQL port is never accepted for this rehearsal.');
        self::assertDirectoryExists($this->workDirectory);
        self::assertFileExists($this->pgBin.DIRECTORY_SEPARATOR.'pg_dump.exe');
        self::assertFileExists($this->pgBin.DIRECTORY_SEPARATOR.'pg_restore.exe');

        $this->admin = $this->connect('postgres');
        $identity = $this->admin->query(
            "SELECT current_setting('data_directory') AS data_directory, current_setting('port') AS port, inet_server_addr()::text AS address"
        )->fetch();

        self::assertIsArray($identity);
        self::assertStringContainsString('medfind-pg-rehearsal-', str_replace('\\', '/', (string) $identity['data_directory']));
        self::assertSame((string) $this->port, $identity['port']);
        self::assertSame('127.0.0.1/32', $identity['address']);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        if (isset($this->admin)) {
            foreach (array_reverse($this->databases) as $database) {
                try {
                    $this->admin->exec('DROP DATABASE IF EXISTS '.$this->quoteIdentifier($database).' WITH (FORCE)');
                } catch (Throwable) {
                    // The entire proven-disposable cluster is discarded by the caller.
                }
            }
        }

        foreach (glob(($this->workDirectory ?? '').DIRECTORY_SEPARATOR.'rehearsal-*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * Rehearses task 3.5 against real, isolated PostgreSQL without changing runtime files.
     *
     * **Validates: Requirements 2.3, 2.6, 3.1, 3.3, 3.4, 3.5, 3.6**
     */
    public function test_complete_sanitized_migration_rehearsal_and_required_abort_paths(): void
    {
        $reverbBefore = $this->reverbDefinitionHashes();
        $sourcePath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-source.sqlite';
        $this->createCanonicalSqliteFixture($sourcePath);
        $sourceChecksum = $this->checksum($sourcePath);
        $secretValues = [
            'sanitized-password-hash-never-log',
            'opaque-session-payload-never-log',
            'private/prescriptions/sanitized-fixture.enc',
            'consumer asks about sanitized medicine',
        ];

        $successDatabase = $this->createDatabase('success');
        $successTarget = $this->connect($successDatabase);
        self::assertSame(0, (int) $successTarget->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
        )->fetchColumn());

        $preflight = new MigrationPreflight;
        $preflightManifest = $preflight->evaluate($this->passingPreflightEvidence($sourceChecksum), [
            'operator' => 'sanitized-rehearsal-operator',
            'database_url' => 'postgresql://rehearsal_admin:never-log@127.0.0.1/rehearsal',
            'db_password' => 'preflight-secret-never-log',
        ]);
        self::assertTrue($preflightManifest['preflight_passed']);
        self::assertFalse($preflightManifest['cutover_performed']);

        $backupPath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-source.backup.sqlite';
        $restoredSourcePath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-source.restored.sqlite';
        $sourcePreparation = new SourcePreparation;
        $preparationManifest = $sourcePreparation->prepare(
            $sourcePath,
            $backupPath,
            $restoredSourcePath,
            $this->quiescenceEvidence(),
            ['mode' => 'transfer', 'continuity_approved' => true, 'app_key_retained' => true],
            ['db_password' => 'source-preparation-secret-never-log']
        );
        self::assertTrue($preparationManifest['preparation_passed']);
        self::assertTrue($preparationManifest['backup']['restoration_test']['passed']);
        self::assertTrue($preparationManifest['inventory']['schema_fully_classified']);
        self::assertSame($sourceChecksum, $this->checksum($sourcePath));
        self::assertSame($sourceChecksum, $this->checksum($backupPath));
        self::assertSame($sourceChecksum, $this->checksum($restoredSourcePath));

        $this->migratePostgreSql($successDatabase);
        $successTarget = $this->connect($successDatabase);
        $expectedMigrationCount = count(glob(database_path('migrations/*.php')) ?: []);
        self::assertSame($expectedMigrationCount, (int) $successTarget->query('SELECT COUNT(*) FROM migrations')->fetchColumn());

        $source = $this->openReadOnlySource($sourcePath);
        $utility = new OneTimeMigrationUtility(batchSize: 2);
        $transferManifest = $utility->transfer(
            $source,
            $successTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $sourceChecksum),
            [
                'database_url' => 'postgresql://rehearsal_admin:never-log@127.0.0.1/'.$successDatabase,
                'credential' => 'transfer-secret-never-log',
            ]
        );

        self::assertTrue(
            $transferManifest['transfer_passed'],
            json_encode($transferManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
        self::assertFalse($transferManifest['abort_required']);
        self::assertFalse($transferManifest['cutover_performed']);
        self::assertSame(array_keys(OneTimeMigrationUtility::medFindTransferPolicy()), array_keys($transferManifest['tables']));
        foreach ($transferManifest['tables'] as $tableEvidence) {
            self::assertTrue($tableEvidence['equivalent']);
            self::assertSame($tableEvidence['source'], $tableEvidence['target']);
        }

        $sequenceEvidence = $this->repairAndProbeSequences($successTarget);
        self::assertNotEmpty($sequenceEvidence);
        foreach ($sequenceEvidence as $evidence) {
            self::assertGreaterThan($evidence['imported_maximum'], $evidence['probe_next_value']);
        }

        $this->assertPostTransferVerification($successTarget, $transferManifest);
        $this->assertBackfillIdempotenceAndRollback($successDatabase);
        $this->assertProvisionalCutoverAndPreWriteRollback($successDatabase);
        self::assertSame($sourceChecksum, $this->checksum($sourcePath));

        $dumpPath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-target.dump';
        $dumpCommand = [
            $this->pgBin.DIRECTORY_SEPARATOR.'pg_dump.exe', '--host='.$this->host, '--port='.(string) $this->port,
            '--username='.$this->username, '--dbname='.$successDatabase, '--format=custom', '--no-password', '--file='.$dumpPath,
        ];
        $dumpResult = $this->runCommand($dumpCommand);
        self::assertSame(0, $dumpResult['exit_code'], $dumpResult['safe_diagnostic']);
        self::assertFileExists($dumpPath);
        self::assertGreaterThan(0, filesize($dumpPath));

        $restoreDatabase = $this->createDatabase('restore');
        $restoreCommand = [
            $this->pgBin.DIRECTORY_SEPARATOR.'pg_restore.exe', '--host='.$this->host, '--port='.(string) $this->port,
            '--username='.$this->username, '--dbname='.$restoreDatabase, '--no-owner', '--no-privileges', '--exit-on-error',
            '--no-password', $dumpPath,
        ];
        $restoreResult = $this->runCommand($restoreCommand);
        self::assertSame(0, $restoreResult['exit_code'], $restoreResult['safe_diagnostic']);
        $restoredTarget = $this->connect($restoreDatabase);
        $this->assertPostTransferVerification($restoredTarget, $transferManifest);

        $failureManifests = [];

        $missingDriverEvidence = $this->passingPreflightEvidence($sourceChecksum);
        $missingDriverEvidence['cli_pdo_drivers'] = ['sqlite'];
        $failureManifests['missing_driver'] = $preflight->evaluate($missingDriverEvidence);
        self::assertFalse($failureManifests['missing_driver']['preflight_passed']);
        self::assertFalse($failureManifests['missing_driver']['gates']['cli_pdo_drivers']['passed']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['missing_driver'], $sourcePath, $sourceChecksum);

        $unreachableObserved = false;
        try {
            new PDO('pgsql:host=127.0.0.1;port=1;dbname=postgres;connect_timeout=1', $this->username, '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException) {
            $unreachableObserved = true;
        }
        self::assertTrue($unreachableObserved);
        $unreachableEvidence = $this->passingPreflightEvidence($sourceChecksum);
        $unreachableEvidence['postgresql_reachable'] = false;
        $failureManifests['unreachable_target'] = $preflight->evaluate($unreachableEvidence);
        self::assertFalse($failureManifests['unreachable_target']['gates']['postgresql_reachable']['passed']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['unreachable_target'], $sourcePath, $sourceChecksum);

        $nonEmptyDatabase = $this->createMigratedDatabase('nonempty');
        $nonEmptyTarget = $this->connect($nonEmptyDatabase);
        $nonEmptyTarget->exec("INSERT INTO cache (key, value, expiration) VALUES ('injected', 'sanitized', 1)");
        $failureManifests['non_empty_target'] = $utility->transfer(
            $this->openReadOnlySource($sourcePath),
            $nonEmptyTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $sourceChecksum)
        );
        self::assertFalse($failureManifests['non_empty_target']['gates']['target_transfer_tables_empty']['passed']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['non_empty_target'], $sourcePath, $sourceChecksum);

        [$malformedPath, $malformedChecksum] = $this->mutatedSource('malformed', $sourcePath, static function (PDO $pdo): void {
            $pdo->exec('UPDATE medicines SET "requiresPrescription" = 2 WHERE id = 300');
        });
        $malformedTarget = $this->connect($this->createMigratedDatabase('malformed'));
        $failureManifests['malformed_value'] = $utility->transfer(
            $this->openReadOnlySource($malformedPath),
            $malformedTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $malformedChecksum)
        );
        self::assertSame('invalid_boolean', $failureManifests['malformed_value']['failure']['reason']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['malformed_value'], $malformedPath, $malformedChecksum);
        $this->assertAuthoritativeTargetEmpty($malformedTarget);

        [$orphanPath, $orphanChecksum] = $this->mutatedSource('orphan', $sourcePath, static function (PDO $pdo): void {
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->exec('UPDATE inventory_items SET pharmacy_id = 999999 WHERE id = 700');
        });
        $orphanTarget = $this->connect($this->createMigratedDatabase('orphan'));
        $failureManifests['orphan'] = $utility->transfer(
            $this->openReadOnlySource($orphanPath),
            $orphanTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $orphanChecksum)
        );
        self::assertSame('target_insert_rejected', $failureManifests['orphan']['failure']['reason']);
        self::assertSame('inventory_items', $failureManifests['orphan']['failure']['table']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['orphan'], $orphanPath, $orphanChecksum);
        $this->assertAuthoritativeTargetEmpty($orphanTarget);

        [$duplicatePath, $duplicateChecksum] = $this->mutatedSource('duplicate', $sourcePath, static function (PDO $pdo): void {
            $pdo->exec('DROP INDEX users_email_unique');
            $pdo->exec("INSERT INTO users (id, name, email, email_verified_at, password, role, pharmacy_id, remember_token, created_at, updated_at)
                SELECT 999, 'Duplicate Sanitized', email, NULL, password, 'consumer', NULL, NULL, created_at, updated_at FROM users WHERE id = 20");
        });
        $duplicateTarget = $this->connect($this->createMigratedDatabase('duplicate'));
        $failureManifests['duplicate_key'] = $utility->transfer(
            $this->openReadOnlySource($duplicatePath),
            $duplicateTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $duplicateChecksum)
        );
        self::assertSame('target_insert_rejected', $failureManifests['duplicate_key']['failure']['reason']);
        self::assertSame('users', $failureManifests['duplicate_key']['failure']['table']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['duplicate_key'], $duplicatePath, $duplicateChecksum);
        $this->assertAuthoritativeTargetEmpty($duplicateTarget);

        [$unknownPath, $unknownChecksum] = $this->mutatedSource('unknown', $sourcePath, static function (PDO $pdo): void {
            $pdo->exec('CREATE TABLE unexpected_schema_artifact (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        });
        $unknownPreparation = $sourcePreparation->prepare(
            $unknownPath,
            $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-unknown.backup.sqlite',
            $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-unknown.restored.sqlite',
            $this->quiescenceEvidence(),
            ['mode' => 'transfer', 'continuity_approved' => true, 'app_key_retained' => true]
        );
        $failureManifests['unknown_schema'] = $unknownPreparation;
        self::assertFalse($unknownPreparation['inventory']['schema_fully_classified']);
        self::assertSame(['unexpected_schema_artifact'], $unknownPreparation['inventory']['unknown_tables']);
        $this->assertAbortedWithoutSourceMutation($unknownPreparation, $unknownPath, $unknownChecksum);

        $interruptionTarget = $this->connect($this->createMigratedDatabase('interruption'));
        $interruptionTarget->exec(<<<'SQL'
CREATE FUNCTION rehearsal_interrupt_transfer() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'sanitized injected transfer interruption';
END
$$;
CREATE TRIGGER rehearsal_interrupt_transfer BEFORE INSERT ON medicines
FOR EACH ROW EXECUTE FUNCTION rehearsal_interrupt_transfer();
SQL);
        $failureManifests['transfer_interruption'] = $utility->transfer(
            $this->openReadOnlySource($sourcePath),
            $interruptionTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $sourceChecksum)
        );
        self::assertSame('target_insert_rejected', $failureManifests['transfer_interruption']['failure']['reason']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['transfer_interruption'], $sourcePath, $sourceChecksum);
        $this->assertAuthoritativeTargetEmpty($interruptionTarget);

        $verificationTarget = $this->connect($this->createMigratedDatabase('verification'));
        $verificationTarget->exec(<<<'SQL'
CREATE FUNCTION rehearsal_corrupt_verification() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    NEW.medicine_name := NEW.medicine_name || ' changed';
    RETURN NEW;
END
$$;
CREATE TRIGGER rehearsal_corrupt_verification BEFORE INSERT ON medicines
FOR EACH ROW EXECUTE FUNCTION rehearsal_corrupt_verification();
SQL);
        $failureManifests['failed_verification'] = $utility->transfer(
            $this->openReadOnlySource($sourcePath),
            $verificationTarget,
            $this->passingTransferEvidence($preflightManifest, $preparationManifest, $sourceChecksum)
        );
        self::assertSame('source_target_equivalence_failed', $failureManifests['failed_verification']['failure']['reason']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['failed_verification'], $sourcePath, $sourceChecksum);
        $this->assertAuthoritativeTargetEmpty($verificationTarget);

        try {
            $preflight->assertSafeTestEnvironment([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => $successDatabase,
                'DB_URL' => '',
            ]);
            self::fail('Unsafe test database configuration must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Unsafe test database configuration', $exception->getMessage());
        }
        $unsafeEvidence = $this->passingPreflightEvidence($sourceChecksum);
        $unsafeEvidence['test_environment'] = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $successDatabase, 'DB_URL' => '',
        ];
        $failureManifests['unsafe_test_database'] = $preflight->evaluate($unsafeEvidence);
        self::assertFalse($failureManifests['unsafe_test_database']['gates']['test_database_isolated']['passed']);
        $this->assertAbortedWithoutSourceMutation($failureManifests['unsafe_test_database'], $sourcePath, $sourceChecksum);

        $preflightPath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-preflight-manifest.json';
        $sourceManifestPath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-source-manifest.json';
        $transferPath = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-transfer-manifest.json';
        $preflight->writeManifest($preflightPath, $preflightManifest);
        $sourcePreparation->writeManifest($sourceManifestPath, $preparationManifest);
        $utility->writeManifest($transferPath, $transferManifest);

        $redactionEvidence = implode("\n", [
            file_get_contents($preflightPath) ?: '',
            file_get_contents($sourceManifestPath) ?: '',
            file_get_contents($transferPath) ?: '',
            json_encode($failureManifests, JSON_THROW_ON_ERROR),
            $dumpResult['safe_command'], $dumpResult['safe_diagnostic'],
            $restoreResult['safe_command'], $restoreResult['safe_diagnostic'],
        ]);
        self::assertStringContainsString('[REDACTED', $redactionEvidence);
        foreach (array_merge($secretValues, [$this->username, 'preflight-secret-never-log', 'source-preparation-secret-never-log', 'transfer-secret-never-log', 'never-log@']) as $secret) {
            self::assertStringNotContainsString($secret, $redactionEvidence);
        }
        self::assertStringContainsString('medicines', $redactionEvidence);
        self::assertStringContainsString('requiresPrescription', $redactionEvidence);

        self::assertSame($sourceChecksum, $this->checksum($sourcePath));
        self::assertSame($reverbBefore, $this->reverbDefinitionHashes());
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function createCanonicalSqliteFixture(string $path): void
    {
        self::assertFileDoesNotExist($path);
        touch($path);
        $connection = 'rehearsal_sqlite_fixture';
        config(["database.connections.{$connection}" => [
            'driver' => 'sqlite', 'url' => null, 'database' => $path, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => null, 'journal_mode' => 'DELETE',
            'synchronous' => null, 'transaction_mode' => 'DEFERRED',
        ]]);
        DB::purge($connection);
        $exitCode = Artisan::call('migrate', ['--database' => $connection, '--force' => true, '--no-interaction' => true]);
        self::assertSame(0, $exitCode, Artisan::output());
        DB::purge($connection);

        $pdo = new PDO('sqlite:'.$path, null, null, $this->pdoOptions());
        $pdo->exec('PRAGMA foreign_keys = ON');
        $timestamp = '2024-02-29 23:59:59';
        $this->insert($pdo, 'users', [
            'id' => 10, 'name' => 'Owner — José 薬', 'email' => 'owner@example.test', 'email_verified_at' => $timestamp,
            'password' => 'sanitized-password-hash-never-log', 'role' => 'pharmacy', 'pharmacy_id' => null,
            'remember_token' => '', 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'users', [
            'id' => 20, 'name' => 'Consumer Fixture', 'email' => 'consumer@example.test', 'email_verified_at' => null,
            'password' => 'sanitized-consumer-hash', 'role' => 'consumer', 'pharmacy_id' => null,
            'remember_token' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'pharmacies', [
            'id' => 50, 'pharmacy_name' => 'Farmácia Fixture — 薬局', 'pharmacyAddress' => '', 'latitude' => '14.5995123',
            'longitude' => '120.9842222', 'contactNumber' => null, 'operating_hours' => 'Mon-Fri 08:00-17:00',
            'status' => 'approved', 'user_id' => 10, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            'logo_path' => 'private/logos/sanitized.enc', 'requirements' => '{"nested":{"z":1,"a":null},"unicode":"✓"}',
        ]);
        $pdo->exec('UPDATE users SET pharmacy_id = 50 WHERE id = 10');
        $this->insert($pdo, 'medicines', [
            'id' => 300, 'medicine_name' => 'Amoxicillin — β', 'brand_name' => 'Fixture Brand',
            'dosage' => '500mg', 'manufacturer' => 'Fixture Maker',
            'requiresPrescription' => 1, 'cold_chain_required' => 0, 'category' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'suppliers', [
            'id' => 400, 'name' => 'Supplier Fixture', 'contact_person' => null, 'phone' => '', 'email' => null,
            'address' => 'Sanitized warehouse', 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'inventory_items', [
            'id' => 700, 'pharmacy_id' => 50, 'medicine_id' => 300, 'stockQuantity' => 17, 'price' => '123.40',
            'status' => 'available', 'created_at' => $timestamp, 'updated_at' => $timestamp, 'expiry_date' => '2030-02-28',
            'batch_number' => null, 'lot_number' => null, 'cold_chain' => 0, 'par_level' => 3, 'supplier_id' => 400,
        ]);
        $this->insert($pdo, 'inventory_batches', [
            'id' => 800, 'inventory_item_id' => 700, 'legacy_source_inventory_item_id' => 700,
            'batch_number' => 'LEGACY-700', 'lot_number' => null, 'identity_key' => 'legacy:700',
            'quantity_received' => 17, 'current_quantity' => 17, 'price' => '123.40',
            'supplier_id' => 400, 'supplier_name' => 'Supplier Fixture', 'expiry_date' => '2030-02-28',
            'cold_chain' => 0, 'received_date' => '2024-02-29', 'received_reference' => 'legacy-inventory:700',
            'created_by' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'stock_movements', [
            'id' => 850, 'operation_id' => 'legacy-backfill:700', 'inventory_item_id' => 700,
            'inventory_batch_id' => 800, 'type' => 'backfill', 'before_quantity' => 0,
            'after_quantity' => 17, 'quantity_delta' => 17, 'reason' => 'Legacy inventory migration',
            'reference_type' => 'inventory_item', 'reference_id' => '700',
            'received_reference' => 'legacy-inventory:700', 'user_id' => null, 'created_at' => $timestamp,
        ]);
        $this->insert($pdo, 'messages', [
            'id' => 900, 'consumer_id' => 20, 'pharmacy_id' => 50, 'message' => 'consumer asks about sanitized medicine',
            'prescription_image' => 'private/prescriptions/sanitized-fixture.enc',
            'reply' => null, 'replied_at' => null, 'is_read' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            'verified_by' => null, 'verification_status' => null, 'verification_notes' => null, 'verified_at' => null,
            'attachments' => '[{"path":"private/attachments/sanitized.enc","name":"résumé.pdf"},{"empty":""}]', 'sender' => 'consumer',
        ]);
        $this->insert($pdo, 'cycle_counts', [
            'id' => 1000, 'pharmacy_id' => 50, 'name' => 'Fixture count', 'notes' => null, 'scheduled_at' => $timestamp,
            'completed_at' => null, 'conducted_by' => 10, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'cycle_count_items', [
            'id' => 1100, 'cycle_count_id' => 1000, 'inventory_item_id' => 700, 'expected_quantity' => 17,
            'counted_quantity' => 16, 'notes' => '', 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'controlled_substance_logs', [
            'id' => 1200, 'inventory_item_id' => 700, 'user_id' => 10, 'action' => 'audited', 'quantity' => 1,
            'notes' => null, 'logged_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'returns_recalls', [
            'id' => 1300, 'inventory_item_id' => 700, 'type' => 'recall', 'quantity' => 1, 'reason' => 'sanitized reason',
            'status' => 'pending', 'requested_by' => 10, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'inventory_audits', [
            'id' => 1400, 'inventory_item_id' => 700, 'user_id' => 10, 'before_quantity' => 18,
            'after_quantity' => 17, 'notes' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'search_logs', [
            'id' => 1500, 'pharmacy_id' => 50, 'query' => '', 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'notifications', [
            'id' => '12345678-1234-4abc-8def-1234567890ab', 'type' => 'SanitizedNotification',
            'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => 20, 'data' => '{"nullable":null,"unicode":"✓"}',
            'read_at' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'activity_logs', [
            'id' => 1600, 'user_id' => 10, 'action' => 'audited', 'entity_type' => 'InventoryItem', 'entity_id' => 700,
            'details' => 'sanitized activity detail', 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'survey_responses', [
            'id' => 1700, 'user_id' => 20, 'respondent_type' => 'consumer', 'respondent_name' => null,
            'fs_completeness' => 5, 'fs_correctness' => 4, 'fs_appropriateness' => null, 'us_recognisability' => 5,
            'us_learnability' => 4, 'us_operability' => 5, 'us_error_protection' => 4, 'us_aesthetics' => 5,
            'se_confidentiality' => 4, 'se_integrity' => 5, 'se_accountability' => 4, 'comments' => '',
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $this->insert($pdo, 'sessions', [
            'id' => 'sanitized-session-id', 'user_id' => 20, 'ip_address' => '127.0.0.1',
            'user_agent' => null, 'payload' => 'opaque-session-payload-never-log', 'last_activity' => 1700000000,
        ]);
        $pdo = null;
    }

    /** @param array<string, mixed> $row */
    private function insert(PDO $pdo, string $table, array $row): void
    {
        $columns = array_keys($row);
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map($this->quoteIdentifier(...), $columns)),
            implode(', ', array_map(static fn (string $column): string => ':'.$column, $columns))
        ));
        $statement->execute($row);
    }

    /** @return array<string, mixed> */
    private function passingPreflightEvidence(string $checksum): array
    {
        $extensions = get_loaded_extensions();

        return [
            'php_version_supported' => PHP_VERSION_ID >= 80200,
            'postgresql_version_supported' => (int) $this->admin->query("SELECT current_setting('server_version_num')::integer")->fetchColumn() >= 140000,
            'cli_extensions' => $extensions,
            'web_extensions' => $extensions,
            'cli_pdo_drivers' => PDO::getAvailableDrivers(),
            'web_pdo_drivers' => PDO::getAvailableDrivers(),
            'postgresql_reachable' => true,
            'target_credentials_authorized' => true,
            'tls_policy_satisfied' => true,
            'encoding_policy_satisfied' => true,
            'timezone_policy_satisfied' => true,
            'backup_tools' => ['psql', 'pg_dump', 'pg_restore'],
            'target_is_dedicated' => true,
            'target_is_empty' => true,
            'target_backup_verified' => false,
            'target_replacement_approved' => false,
            'database_environment_unambiguous' => true,
            'source_readable' => true,
            'source_opened_read_only' => true,
            'sqlite_integrity_valid' => true,
            'source_checksum_before' => $checksum,
            'source_checksum_after' => $checksum,
            'disk_space_sufficient' => true,
            'migration_role_privileges_sufficient' => true,
            'source_constraints_compatible' => true,
            'canonical_migrations_available' => true,
            'test_environment' => [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function quiescenceEvidence(): array
    {
        return [
            'writable_http_stopped' => true,
            'queue_workers_stopped' => true,
            'scheduler_stopped' => true,
            'persisting_event_consumers_stopped' => true,
            'manual_writers_stopped' => true,
            'active_sqlite_write_transactions' => 0,
            'deployment_configuration_preserved' => true,
            'configuration_cache_preserved' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @param  array<string, mixed>  $preparation
     * @return array<string, mixed>
     */
    private function passingTransferEvidence(array $preflight, array $preparation, string $checksum): array
    {
        return [
            'preflight_passed' => $preflight['preflight_passed'] === true,
            'source_preparation_passed' => $preparation['preparation_passed'] === true,
            'backup_is_restorable' => $preparation['backup']['restoration_test']['passed'] === true,
            'canonical_schema_created' => true,
            'canonical_migrations_verified' => true,
            'target_is_unaccepted' => true,
            'target_application_traffic_disabled' => true,
            'source_checksum_before' => $checksum,
            'source_checksum_after_preparation' => $checksum,
            'backup_reference' => 'temp://sanitized-rehearsal-source-backup',
            'backup_checksum_sha256' => $preparation['backup']['checksum_sha256'],
            'session_mode' => 'transfer',
            'session_continuity_approved' => true,
            'app_key_retained' => true,
        ];
    }

    private function migratePostgreSql(string $database): void
    {
        $this->configurePostgreSqlConnection($database);
        $exitCode = Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true, '--no-interaction' => true]);
        self::assertSame(0, $exitCode, Artisan::output());
        DB::purge(self::CONNECTION);
    }

    private function createMigratedDatabase(string $purpose): string
    {
        $database = $this->createDatabase($purpose);
        $this->migratePostgreSql($database);

        return $database;
    }

    private function configurePostgreSqlConnection(string $database): void
    {
        DB::purge(self::CONNECTION);
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'pgsql', 'url' => null, 'host' => $this->host, 'port' => (string) $this->port,
            'database' => $database, 'username' => $this->username, 'password' => '', 'charset' => 'utf8',
            'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'disable',
        ]]);
    }

    private function createDatabase(string $purpose): string
    {
        $database = 'medfind_rehearsal_'.preg_replace('/[^a-z0-9_]/', '_', strtolower($purpose)).'_'.bin2hex(random_bytes(4));
        $this->admin->exec('CREATE DATABASE '.$this->quoteIdentifier($database).' TEMPLATE template0 ENCODING \'UTF8\'');
        $this->databases[] = $database;

        return $database;
    }

    private function connect(string $database): PDO
    {
        return new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=5', $this->host ?? '127.0.0.1', $this->port ?? 0, $database),
            $this->username ?? $this->requiredEnvironment('MEDFIND_REHEARSAL_USERNAME'),
            '',
            $this->pdoOptions()
        );
    }

    private function openReadOnlySource(string $path): PDO
    {
        $pdo = new PDO('sqlite:'.$path, null, null, $this->pdoOptions());
        $pdo->exec('PRAGMA query_only = ON');
        self::assertSame(1, (int) $pdo->query('PRAGMA query_only')->fetchColumn());

        return $pdo;
    }

    /** @return array<int, int> */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }

    /** @return array<string, array{imported_maximum: int, probe_next_value: int}> */
    private function repairAndProbeSequences(PDO $target): array
    {
        $evidence = [];
        foreach (OneTimeMigrationUtility::medFindTransferPolicy() as $table => $definition) {
            $primaryKey = $definition['primary_key'];
            if (($definition['columns'][$primaryKey]['type'] ?? null) !== 'integer') {
                continue;
            }

            $sequenceStatement = $target->prepare('SELECT pg_get_serial_sequence(:table, :column)');
            $sequenceStatement->execute(['table' => $table, 'column' => $primaryKey]);
            $sequence = $sequenceStatement->fetchColumn();
            self::assertIsString($sequence, "Missing sequence for {$table}.{$primaryKey}");
            $maximum = (int) $target->query(
                'SELECT COALESCE(MAX('.$this->quoteIdentifier($primaryKey).'), 0) FROM '.$this->quoteIdentifier($table)
            )->fetchColumn();
            $sequenceLiteral = $target->quote($sequence);
            $target->exec("SELECT setval({$sequenceLiteral}::regclass, {$maximum}, true)");
            $nextValue = (int) $target->query("SELECT nextval({$sequenceLiteral}::regclass)")->fetchColumn();
            $evidence[$table] = ['imported_maximum' => $maximum, 'probe_next_value' => $nextValue];
        }

        return $evidence;
    }

    /** @param array<string, mixed> $manifest */
    private function assertPostTransferVerification(PDO $target, array $manifest): void
    {
        foreach ($manifest['tables'] as $table => $evidence) {
            self::assertSame(
                $evidence['source']['row_count'],
                (int) $target->query('SELECT COUNT(*) FROM '.$this->quoteIdentifier($table))->fetchColumn(),
                "Restored row count differs for {$table}."
            );
        }
        self::assertSame(0, (int) $target->query(
            "SELECT COUNT(*) FROM pg_constraint WHERE contype = 'f' AND NOT convalidated"
        )->fetchColumn());
        self::assertSame(1, (int) $target->query(
            'SELECT COUNT(*) FROM users u JOIN pharmacies p ON p.id = u.pharmacy_id AND p.user_id = u.id WHERE u.id = 10'
        )->fetchColumn());
        self::assertSame('Farmácia Fixture — 薬局', $target->query('SELECT pharmacy_name FROM pharmacies WHERE id = 50')->fetchColumn());
        self::assertSame('123.40', (string) $target->query('SELECT price FROM inventory_items WHERE id = 700')->fetchColumn());
        self::assertSame(1, (int) $target->query('SELECT COUNT(*) FROM inventory_batches WHERE legacy_source_inventory_item_id = 700')->fetchColumn());
        self::assertSame('legacy:700', $target->query('SELECT identity_key FROM inventory_batches WHERE id = 800')->fetchColumn());
        self::assertSame(1, (int) $target->query("SELECT COUNT(*) FROM stock_movements WHERE operation_id = 'legacy-backfill:700'")->fetchColumn());
        self::assertSame('opaque-session-payload-never-log', $target->query("SELECT payload FROM sessions WHERE id = 'sanitized-session-id'")->fetchColumn());
        self::assertSame(0, (int) $target->query('SELECT COUNT(*) FROM cache')->fetchColumn());
        self::assertSame(0, (int) $target->query('SELECT COUNT(*) FROM cache_locks')->fetchColumn());
    }

    private function assertBackfillIdempotenceAndRollback(string $database): void
    {
        $this->configurePostgreSqlConnection($database);
        $connection = DB::connection(self::CONNECTION);
        $before = [
            'batches' => (int) $connection->table('inventory_batches')->count(),
            'movements' => (int) $connection->table('stock_movements')->count(),
            'stock' => (int) $connection->table('inventory_items')->where('id', 700)->value('stockQuantity'),
        ];
        $backfill = new LegacyInventoryBackfill;
        $backfill->run($connection, CarbonImmutable::parse('2029-06-15'));
        self::assertSame($before['batches'], (int) $connection->table('inventory_batches')->count());
        self::assertSame($before['movements'], (int) $connection->table('stock_movements')->count());
        self::assertSame($before['stock'], (int) $connection->table('inventory_items')->where('id', 700)->value('stockQuantity'));

        try {
            $backfill->run($connection, CarbonImmutable::parse('2029-06-15'), static function (): void {
                throw new RuntimeException('Injected PostgreSQL backfill verification failure.');
            });
            self::fail('Injected PostgreSQL verification failure must roll back.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected PostgreSQL backfill verification failure.', $exception->getMessage());
        }

        self::assertSame($before['batches'], (int) $connection->table('inventory_batches')->count());
        self::assertSame($before['movements'], (int) $connection->table('stock_movements')->count());
        DB::purge(self::CONNECTION);
    }

    private function assertProvisionalCutoverAndPreWriteRollback(string $database): void
    {
        $originalDefault = config('database.default');
        $this->configurePostgreSqlConnection($database);
        config(['database.default' => self::CONNECTION]);
        try {
            self::assertSame(2, (int) DB::connection()->table('users')->count());
            self::assertSame(1, (int) DB::connection()->table('inventory_items')->count());
            self::assertSame(1, (int) DB::connection()->table('survey_responses')->count());
        } finally {
            config(['database.default' => $originalDefault]);
            DB::purge(self::CONNECTION);
        }
        self::assertSame($originalDefault, config('database.default'));
    }

    /** @param array<string, mixed> $manifest */
    private function assertAbortedWithoutSourceMutation(array $manifest, string $sourcePath, string $checksum): void
    {
        self::assertTrue($manifest['abort_required']);
        self::assertFalse($manifest['cutover_performed']);
        self::assertSame($checksum, $this->checksum($sourcePath));
    }

    private function assertAuthoritativeTargetEmpty(PDO $target): void
    {
        foreach (array_keys(OneTimeMigrationUtility::medFindTransferPolicy()) as $table) {
            self::assertSame(0, (int) $target->query('SELECT COUNT(*) FROM '.$this->quoteIdentifier($table))->fetchColumn(), $table);
        }
    }

    /**
     * @param  callable(PDO): void  $mutation
     * @return array{0: string, 1: string}
     */
    private function mutatedSource(string $purpose, string $sourcePath, callable $mutation): array
    {
        $path = $this->workDirectory.DIRECTORY_SEPARATOR.'rehearsal-'.$purpose.'.sqlite';
        self::assertTrue(copy($sourcePath, $path));
        $pdo = new PDO('sqlite:'.$path, null, null, $this->pdoOptions());
        $mutation($pdo);
        $pdo = null;

        return [$path, $this->checksum($path)];
    }

    /** @param array<int, string> $command @return array{exit_code: int, safe_command: string, safe_diagnostic: string} */
    private function runCommand(array $command): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $this->workDirectory);
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start a PostgreSQL rehearsal tool.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'safe_command' => $this->redactDiagnostic(implode(' ', array_map($this->quoteCommandArgument(...), $command))),
            'safe_diagnostic' => $this->redactDiagnostic(trim(($stdout ?: '')."\n".($stderr ?: ''))),
        ];
    }

    private function redactDiagnostic(string $value): string
    {
        $value = preg_replace('#\b[a-z][a-z0-9+.-]*://[^\s]+#i', '[REDACTED_URL]', $value) ?? '[REDACTED]';
        $value = preg_replace('/(?i)(password|passwd|secret|token|credential|username|user)=\S+/', '$1=[REDACTED]', $value) ?? '[REDACTED]';

        return $value;
    }

    private function quoteCommandArgument(string $argument): string
    {
        return '"'.str_replace('"', '\\"', $argument).'"';
    }

    /** @return array<string, string> */
    private function reverbDefinitionHashes(): array
    {
        $paths = array_filter([
            config_path('reverb.php'), config_path('broadcasting.php'), base_path('routes/channels.php'),
        ], 'is_file');
        foreach (glob(app_path('Events/*.php')) ?: [] as $path) {
            $paths[] = $path;
        }
        sort($paths);
        $hashes = [];
        foreach ($paths as $path) {
            $hashes[str_replace('\\', '/', $path)] = $this->checksum($path);
        }

        return $hashes;
    }

    private function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum)) {
            throw new AssertionFailedError("Unable to checksum {$path}.");
        }

        return $checksum;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new RuntimeException('Unsafe rehearsal identifier.');
        }

        return '"'.$identifier.'"';
    }

    private function requiredEnvironment(string $name): string
    {
        $value = getenv($name);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing required rehearsal environment variable {$name}.");
        }

        return $value;
    }
}
