<?php

namespace Tests\Unit;

use App\Database\Migration\MigrationPreflight;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationPreflightTest extends TestCase
{
    private MigrationPreflight $preflight;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preflight = new MigrationPreflight;
    }

    /**
     * **Validates: Requirements 1.3, 2.3, 2.6, 3.1, 3.3, 3.6**
     */
    public function test_verified_evidence_allows_preparation_but_never_authorizes_cutover_or_source_mutation(): void
    {
        $manifest = $this->preflight->evaluate(self::passingEvidence());

        self::assertTrue($manifest['preflight_passed']);
        self::assertFalse($manifest['abort_required']);
        self::assertFalse($manifest['cutover_performed']);
        self::assertFalse($manifest['runtime_change_permitted']);
        self::assertFalse($manifest['source_mutation_permitted']);

        foreach ($manifest['gates'] as $gate) {
            self::assertTrue($gate['passed']);
            self::assertSame('verified', $gate['reason']);
        }
    }

    /**
     * Property: every missing or failed prerequisite forces a fail-closed abort.
     *
     * **Validates: Requirements 1.3, 2.3, 2.6, 3.1, 3.3, 3.6**
     */
    public function test_each_generated_failed_or_missing_boolean_prerequisite_fails_closed(): void
    {
        $keys = [
            'php_version_supported',
            'postgresql_version_supported',
            'postgresql_reachable',
            'target_credentials_authorized',
            'tls_policy_satisfied',
            'encoding_policy_satisfied',
            'timezone_policy_satisfied',
            'database_environment_unambiguous',
            'source_readable',
            'source_opened_read_only',
            'sqlite_integrity_valid',
            'disk_space_sufficient',
            'migration_role_privileges_sufficient',
            'source_constraints_compatible',
            'canonical_migrations_available',
        ];

        foreach ($keys as $key) {
            foreach (['failed', 'missing'] as $case) {
                $evidence = self::passingEvidence();

                if ($case === 'failed') {
                    $evidence[$key] = false;
                } else {
                    unset($evidence[$key]);
                }

                $manifest = $this->preflight->evaluate($evidence);

                self::assertFalse($manifest['preflight_passed'], "{$key} ({$case}) must fail preflight.");
                self::assertTrue($manifest['abort_required']);
                self::assertFalse($manifest['cutover_performed']);
                self::assertFalse($manifest['runtime_change_permitted']);
            }
        }
    }

    /**
     * Property: every required CLI/web capability is independently mandatory.
     *
     * **Validates: Requirements 1.3, 2.3, 3.3**
     */
    public function test_each_generated_missing_runtime_capability_fails_closed(): void
    {
        $capabilitySets = [
            'cli_extensions' => ['PDO', 'pdo_pgsql', 'pgsql', 'pdo_sqlite', 'sqlite3'],
            'web_extensions' => ['PDO', 'pdo_pgsql', 'pgsql', 'pdo_sqlite', 'sqlite3'],
            'cli_pdo_drivers' => ['pgsql', 'sqlite'],
            'web_pdo_drivers' => ['pgsql', 'sqlite'],
            'backup_tools' => ['psql', 'pg_dump', 'pg_restore'],
        ];

        foreach ($capabilitySets as $key => $capabilities) {
            foreach ($capabilities as $missingCapability) {
                $evidence = self::passingEvidence();
                $evidence[$key] = array_values(array_diff($capabilities, [$missingCapability]));

                $manifest = $this->preflight->evaluate($evidence);

                self::assertFalse(
                    $manifest['preflight_passed'],
                    "{$key} without {$missingCapability} must fail preflight."
                );
                self::assertTrue($manifest['abort_required']);
            }
        }
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool, 2: string}>
     */
    public static function targetContexts(): iterable
    {
        yield 'dedicated empty target' => [
            ['target_is_dedicated' => true, 'target_is_empty' => true],
            true,
            'verified',
        ];
        yield 'dedicated populated target with backup and approval' => [
            [
                'target_is_dedicated' => true,
                'target_is_empty' => false,
                'target_backup_verified' => true,
                'target_replacement_approved' => true,
            ],
            true,
            'verified',
        ];
        yield 'unknown target identity' => [
            ['target_is_dedicated' => false, 'target_is_empty' => true],
            false,
            'target_not_proven_dedicated',
        ];
        yield 'populated target without backup' => [
            [
                'target_is_dedicated' => true,
                'target_is_empty' => false,
                'target_backup_verified' => false,
                'target_replacement_approved' => true,
            ],
            false,
            'populated_target_backup_not_verified',
        ];
        yield 'populated target without replacement approval' => [
            [
                'target_is_dedicated' => true,
                'target_is_empty' => false,
                'target_backup_verified' => true,
                'target_replacement_approved' => false,
            ],
            false,
            'populated_target_replacement_not_approved',
        ];
    }

    /**
     * **Validates: Requirements 1.3, 2.3, 2.6**
     *
     * @param array<string, mixed> $targetEvidence
     */
    #[DataProvider('targetContexts')]
    public function test_target_must_be_dedicated_and_empty_or_explicitly_backed_up_and_approved(
        array $targetEvidence,
        bool $expectedPass,
        string $expectedReason
    ): void {
        $manifest = $this->preflight->evaluate(array_replace(self::passingEvidence(), $targetEvidence));
        $gate = $manifest['gates']['target_safe_for_preparation'];

        self::assertSame($expectedPass, $gate['passed']);
        self::assertSame($expectedReason, $gate['reason']);
        self::assertSame(! $expectedPass, $manifest['abort_required']);
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: bool}>
     */
    public static function databaseSafetyContexts(): iterable
    {
        $safe = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        yield 'isolated sqlite memory' => [$safe, true];
        yield 'postgresql test database' => [array_replace($safe, ['DB_CONNECTION' => 'pgsql']), false];
        yield 'file backed sqlite test database' => [array_replace($safe, ['DB_DATABASE' => 'database/test.sqlite']), false];
        yield 'database URL overrides isolation' => [array_replace($safe, ['DB_URL' => 'postgresql://example.invalid/test']), false];
    }

    /**
     * **Validates: Requirements 3.3, 3.6**
     *
     * @param array<string, string> $environment
     */
    #[DataProvider('databaseSafetyContexts')]
    public function test_hard_test_guard_rejects_every_non_memory_database(
        array $environment,
        bool $expectedSafe
    ): void {
        if (! $expectedSafe) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsafe test database configuration');
        }

        $this->preflight->assertSafeTestEnvironment($environment);
        self::assertTrue($expectedSafe);
    }

    /**
     * **Validates: Requirements 2.6, 3.1, 3.6**
     */
    public function test_written_manifest_contains_gate_results_but_no_secrets_or_connection_identifiers(): void
    {
        $secret = 'do-not-write-this-value';
        $manifest = $this->preflight->evaluate(self::passingEvidence(), [
            'operator' => 'migration-owner',
            'db_password' => $secret,
            'database_name' => 'production_database',
            'postgres_host' => 'db.internal.example',
            'connection_url' => 'postgresql://admin:'.$secret.'@db.internal.example/production_database',
            'note' => 'Diagnostic postgresql://admin:'.$secret.'@db.internal.example/database',
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'medfind-preflight-'.bin2hex(random_bytes(8)).'.json';

        try {
            $this->preflight->writeManifest($path, $manifest);
            $written = file_get_contents($path);

            self::assertIsString($written);
            self::assertStringContainsString('"preflight_passed": true', $written);
            self::assertStringContainsString('"target_credentials_authorized"', $written);
            self::assertStringContainsString('[REDACTED]', $written);
            self::assertStringNotContainsString($secret, $written);
            self::assertStringNotContainsString('production_database', $written);
            self::assertStringNotContainsString('db.internal.example', $written);
            self::assertStringNotContainsString('admin:', $written);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * **Validates: Requirements 3.1**
     */
    public function test_changed_source_checksum_aborts_preflight(): void
    {
        $evidence = self::passingEvidence();
        $evidence['source_checksum_after'] = str_repeat('b', 64);

        $manifest = $this->preflight->evaluate($evidence);

        self::assertFalse($manifest['gates']['source_checksum_unchanged']['passed']);
        self::assertSame('source_checksum_missing_or_changed', $manifest['gates']['source_checksum_unchanged']['reason']);
        self::assertTrue($manifest['abort_required']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function passingEvidence(): array
    {
        $checksum = str_repeat('a', 64);

        return [
            'php_version_supported' => true,
            'postgresql_version_supported' => true,
            'cli_extensions' => ['PDO', 'pdo_pgsql', 'pgsql', 'pdo_sqlite', 'sqlite3'],
            'web_extensions' => ['PDO', 'pdo_pgsql', 'pgsql', 'pdo_sqlite', 'sqlite3'],
            'cli_pdo_drivers' => ['pgsql', 'sqlite'],
            'web_pdo_drivers' => ['pgsql', 'sqlite'],
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
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'DB_URL' => '',
            ],
        ];
    }
}
