<?php

namespace Tests\Unit;

use App\Database\Migration\SourcePreparation;
use PDO;
use PHPUnit\Framework\TestCase;

final class SourcePreparationTest extends TestCase
{
    private string $directory;

    /** @var array<string, array{classification: string, columns: array<int, string>}> */
    private array $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'medfind-source-preparation-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
        $this->policy = [
            'users' => [
                'classification' => 'authoritative',
                'columns' => ['id', 'name'],
            ],
            'sessions' => [
                'classification' => 'authoritative',
                'columns' => ['id', 'user_id', 'payload'],
            ],
            'migrations' => [
                'classification' => 'operational',
                'columns' => ['id', 'migration', 'batch'],
            ],
            'cache' => [
                'classification' => 'operational',
                'columns' => ['key', 'value', 'expiration'],
            ],
            'cache_locks' => [
                'classification' => 'operational',
                'columns' => ['key', 'owner', 'expiration'],
            ],
        ];
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
     * **Validates: Requirements 1.3, 2.3, 2.6, 3.1, 3.5**
     */
    public function test_quiesced_fixture_is_backed_up_restored_and_inventoried_without_source_mutation(): void
    {
        $source = $this->createFixture();
        $checksumBefore = hash_file('sha256', $source);
        $backup = $this->path('backup.sqlite');
        $restored = $this->path('restored.sqlite');
        $preparation = new SourcePreparation($this->policy);

        $manifest = $preparation->prepare(
            $source,
            $backup,
            $restored,
            self::quiescenceEvidence(),
            self::sessionTransferDecision()
        );

        self::assertTrue($manifest['preparation_passed']);
        self::assertFalse($manifest['abort_required']);
        self::assertFalse($manifest['cutover_performed']);
        self::assertFalse($manifest['runtime_change_permitted']);
        self::assertFalse($manifest['source_mutation_permitted']);
        self::assertSame($checksumBefore, hash_file('sha256', $source));
        self::assertSame($checksumBefore, hash_file('sha256', $backup));
        self::assertSame($checksumBefore, hash_file('sha256', $restored));
        self::assertTrue($manifest['backup']['restoration_test']['passed']);
        self::assertTrue($manifest['inventory']['schema_fully_classified']);
        self::assertSame(2, $manifest['inventory']['tables']['users']['row_count']);
        self::assertSame(
            ['column' => 'id', 'minimum' => 3, 'maximum' => 10],
            $manifest['inventory']['tables']['users']['primary_key_range']
        );
        self::assertSame(
            ['2026_01_01_000000_create_fixture'],
            $manifest['inventory']['migration_names']
        );
        self::assertSame('authoritative', $manifest['inventory']['tables']['sessions']['classification']);
        self::assertTrue($manifest['inventory']['sessions']['included']);

        $serialized = json_encode($manifest, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('sensitive-session-id', $serialized);
        self::assertStringNotContainsString('sensitive-session-payload', $serialized);
    }

    /**
     * Generated safety property: no required writer may be unproven while a backup is created.
     *
     * **Validates: Requirements 1.3, 2.3, 3.1**
     */
    public function test_each_unquiesced_writer_state_fails_closed_before_any_copy(): void
    {
        $source = $this->createFixture();
        $preparation = new SourcePreparation($this->policy);
        $writerKeys = [
            'writable_http_stopped',
            'queue_workers_stopped',
            'scheduler_stopped',
            'persisting_event_consumers_stopped',
            'manual_writers_stopped',
        ];

        foreach ($writerKeys as $index => $key) {
            $evidence = self::quiescenceEvidence();
            $evidence[$key] = false;
            $backup = $this->path("blocked-{$index}.sqlite");
            $manifest = $preparation->prepare(
                $source,
                $backup,
                $this->path("blocked-restore-{$index}.sqlite"),
                $evidence,
                self::sessionTransferDecision()
            );

            self::assertTrue($manifest['abort_required'], "{$key} must fail closed.");
            self::assertSame('writers_not_proven_stopped', $manifest['gates']['quiescence']['reason']);
            self::assertFileDoesNotExist($backup);
        }

        $evidence = self::quiescenceEvidence();
        $evidence['active_sqlite_write_transactions'] = 1;
        $backup = $this->path('active-write-blocked.sqlite');
        $manifest = $preparation->prepare(
            $source,
            $backup,
            $this->path('active-write-restore.sqlite'),
            $evidence,
            self::sessionTransferDecision()
        );

        self::assertTrue($manifest['abort_required']);
        self::assertFileDoesNotExist($backup);
    }

    /**
     * **Validates: Requirements 1.3, 2.3, 3.1**
     */
    public function test_unknown_table_and_column_abort_after_preserving_a_restorable_backup(): void
    {
        $source = $this->createFixture();
        $pdo = new PDO('sqlite:'.$source);
        $pdo->exec('ALTER TABLE users ADD COLUMN unexpected_secret TEXT');
        $pdo->exec('CREATE TABLE mystery_records (id INTEGER PRIMARY KEY, payload TEXT)');
        $pdo = null;
        $checksumBefore = hash_file('sha256', $source);
        $backup = $this->path('unknown-schema-backup.sqlite');
        $restored = $this->path('unknown-schema-restored.sqlite');

        $manifest = (new SourcePreparation($this->policy))->prepare(
            $source,
            $backup,
            $restored,
            self::quiescenceEvidence(),
            self::sessionTransferDecision()
        );

        self::assertTrue($manifest['abort_required']);
        self::assertFalse($manifest['preparation_passed']);
        self::assertSame('unknown_or_missing_schema', $manifest['gates']['schema_fully_classified']['reason']);
        self::assertSame(['mystery_records'], $manifest['inventory']['unknown_tables']);
        self::assertSame(['unexpected_secret'], $manifest['inventory']['unknown_columns']['users']);
        self::assertTrue($manifest['backup']['restoration_test']['passed']);
        self::assertSame($checksumBefore, hash_file('sha256', $source));
        self::assertSame($checksumBefore, hash_file('sha256', $backup));
    }

    /**
     * **Validates: Requirements 2.3, 3.1, 3.5**
     */
    public function test_sessions_require_approved_continuity_or_an_approved_forced_logout(): void
    {
        $source = $this->createFixture();
        $preparation = new SourcePreparation($this->policy);
        $unapprovedBackup = $this->path('unapproved-session.sqlite');
        $unapproved = $preparation->prepare(
            $source,
            $unapprovedBackup,
            $this->path('unapproved-session-restore.sqlite'),
            self::quiescenceEvidence(),
            ['mode' => 'transfer', 'continuity_approved' => true, 'app_key_retained' => false]
        );

        self::assertTrue($unapproved['abort_required']);
        self::assertSame('session_transfer_requirements_incomplete', $unapproved['gates']['session_decision']['reason']);
        self::assertFileDoesNotExist($unapprovedBackup);

        $forcedLogout = $preparation->prepare(
            $source,
            $this->path('forced-logout.sqlite'),
            $this->path('forced-logout-restore.sqlite'),
            self::quiescenceEvidence(),
            ['mode' => 'forced_logout', 'forced_logout_approved' => true]
        );

        self::assertTrue($forcedLogout['preparation_passed']);
        self::assertFalse($forcedLogout['inventory']['sessions']['included']);
        self::assertSame(
            'explicitly_excluded',
            $forcedLogout['inventory']['tables']['sessions']['classification']
        );
    }

    /**
     * **Validates: Requirements 2.6, 3.1, 3.6**
     */
    public function test_manifest_is_redacted_and_existing_artifacts_are_never_replaced(): void
    {
        $source = $this->createFixture();
        $secret = 'do-not-persist-this-secret';
        $backup = $this->path('redacted-backup.sqlite');
        $manifest = (new SourcePreparation($this->policy))->prepare(
            $source,
            $backup,
            $this->path('redacted-restored.sqlite'),
            self::quiescenceEvidence(),
            self::sessionTransferDecision(),
            [
                'operator' => 'migration-owner',
                'database_password' => $secret,
                'environment_backup_reference' => 'vault://'.$secret.'@production/config',
            ]
        );
        $manifestPath = $this->path('source-preparation.json');
        (new SourcePreparation($this->policy))->writeManifest($manifestPath, $manifest);
        $written = file_get_contents($manifestPath);

        self::assertIsString($written);
        self::assertStringContainsString('[REDACTED]', $written);
        self::assertStringContainsString('[REDACTED_URL]', $written);
        self::assertStringNotContainsString($secret, $written);

        $replacement = (new SourcePreparation($this->policy))->prepare(
            $source,
            $backup,
            $this->path('replacement-restored.sqlite'),
            self::quiescenceEvidence(),
            self::sessionTransferDecision()
        );

        self::assertTrue($replacement['abort_required']);
        self::assertSame('backup_creation_failed', $replacement['gates']['backup_created']['reason']);
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $backup));
    }

    private function createFixture(): string
    {
        $path = $this->path('source.sqlite');
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec('CREATE UNIQUE INDEX users_name_unique ON users (name)');
        $pdo->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY, user_id INTEGER, payload TEXT NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id))');
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT NOT NULL, batch INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE cache_locks (key TEXT PRIMARY KEY, owner TEXT NOT NULL, expiration INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO users (id, name) VALUES (3, 'Alpha'), (10, 'Zulu')");
        $pdo->exec("INSERT INTO sessions (id, user_id, payload) VALUES ('sensitive-session-id', 3, 'sensitive-session-payload')");
        $pdo->exec("INSERT INTO migrations (id, migration, batch) VALUES (1, '2026_01_01_000000_create_fixture', 1)");
        $pdo = null;

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private static function quiescenceEvidence(): array
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
     * @return array<string, mixed>
     */
    private static function sessionTransferDecision(): array
    {
        return [
            'mode' => 'transfer',
            'continuity_approved' => true,
            'app_key_retained' => true,
        ];
    }

    private function path(string $name): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$name;
    }
}
