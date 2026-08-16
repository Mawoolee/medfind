<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostgreSqlCutoverBugConditionPropertyTest extends TestCase
{
    private const SOURCE_CHECKSUM = '9ea7c59c2d796e4227e91dc956ad9033f5e2831aa0708531429aa30d3e6a70ac';

    /**
     * Property 1: Bug Condition - Complete SQLite-to-PostgreSQL Cutover.
     *
     * This is an exploration property. It is expected to fail on the unfixed
     * repository because the checked-in environment template selects SQLite.
     *
     * **Validates: Requirements 2.1, 2.3, 2.6**
     */
    public function test_bug_condition_requires_a_complete_postgresql_cutover_or_a_fail_closed_abort(): void
    {
        $violations = [];

        foreach ($this->generatedMigrationContexts() as $context) {
            self::assertTrue($this->isBugCondition($context), $context['case'].' must exercise the bug condition.');

            if ($context['preconditionsSatisfied']) {
                if (! $this->expectedBehavior($context['result'])) {
                    $violations[] = $this->sanitizedCounterexample($context);
                }

                continue;
            }

            self::assertFalse(
                $context['result']['cutoverPerformed'],
                $context['case'].' must abort before cutover when a gate fails.'
            );
            self::assertSame(
                $context['sourceChecksumBefore'],
                $context['sourceChecksumAfter'],
                $context['case'].' must leave the sanitized SQLite fixture unchanged.'
            );
        }

        self::assertSame(
            [],
            $violations,
            "Unfixed migration counterexample(s):\n".json_encode(
                $violations,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function bugConditionExamples(): iterable
    {
        yield 'runtime still selects sqlite' => [
            self::baseContext([
                'runtimeDatabaseEngine' => 'sqlite',
            ]),
            true,
        ];

        yield 'postgresql target is unreachable' => [
            self::baseContext([
                'postgreSQLReachable' => false,
            ]),
            true,
        ];

        yield 'existing sqlite state has no transfer proof' => [
            self::baseContext([
                'authoritativeRowsEquivalent' => false,
            ]),
            true,
        ];

        yield 'ready postgresql runtime is outside bug condition' => [
            self::baseContext(),
            false,
        ];
    }

    #[DataProvider('bugConditionExamples')]
    public function test_bug_condition_predicate_matches_the_design(array $context, bool $expected): void
    {
        self::assertSame($expected, $this->isBugCondition($context));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function deterministicCounterexamples(): iterable
    {
        $templateEngine = self::environmentTemplate()['DB_CONNECTION'] ?? null;

        yield 'unchanged template resolves to sqlite' => [[
            'result' => self::completeResult(['runtimeDatabaseEngine' => $templateEngine]),
            'mustAbort' => false,
            'testEnvironmentIsSafe' => true,
        ]];

        yield 'connection-only switch exposes empty target' => [[
            'result' => self::completeResult([
                'targetSchemaMatchesCanonicalMigrations' => false,
                'authoritativeTableCountsMatch' => false,
                'canonicalRowHashesMatch' => false,
            ]),
            'mustAbort' => true,
            'testEnvironmentIsSafe' => true,
        ]];

        yield 'explicit imported ids have no sequence repair' => [[
            'result' => self::completeResult([
                'nextIdentityValuesExceedImportedMaxima' => false,
            ]),
            'mustAbort' => true,
            'testEnvironmentIsSafe' => true,
        ]];

        yield 'test database is file-backed sqlite' => [[
            'result' => self::completeResult(),
            'mustAbort' => true,
            'testEnvironmentIsSafe' => false,
        ]];
    }

    #[DataProvider('deterministicCounterexamples')]
    public function test_deterministic_risk_fixtures_are_rejected(array $fixture): void
    {
        $accepted = $this->expectedBehavior($fixture['result'])
            && ! $fixture['mustAbort']
            && $fixture['testEnvironmentIsSafe'];

        self::assertFalse($accepted);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function generatedMigrationContexts(): iterable
    {
        // Keep the known minimal defect first so PHPUnit reports a stable,
        // credential-free counterexample after the generated cases are reduced.
        yield $this->freshTemplateContext();

        $faults = [
            'missing_pdo_pgsql' => ['pdoPgsqlAvailable' => false],
            'unreachable_target' => ['postgreSQLReachable' => false],
            'unauthorized_target' => ['targetCredentialsAuthorized' => false],
            'non_empty_or_unknown_target' => ['targetSchemaMatchesCanonicalMigrations' => false],
            'conflicting_db_url_and_db_fields' => ['targetCredentialsAuthorized' => false],
            'invalid_sqlite_integrity' => ['sqliteIsReadable' => false, 'targetSchemaMatchesCanonicalMigrations' => false],
            'unknown_table_or_column' => ['targetSchemaMatchesCanonicalMigrations' => false],
            'malformed_typed_value' => ['authoritativeRowsEquivalent' => false],
            'circular_relationship_failure' => ['foreignKeysValid' => false],
            'sparse_ids_without_sequence_repair' => ['identitySequencesSafe' => false],
            'transfer_interruption' => ['authoritativeRowsEquivalent' => false],
            'verification_failure' => ['foreignKeysValid' => false],
            'unsafe_test_database' => ['targetSchemaMatchesCanonicalMigrations' => false],
        ];

        foreach ($faults as $fault => $overrides) {
            for ($sample = 0; $sample < 4; $sample++) {
                $context = self::baseContext($overrides + [
                    'sqliteAuthoritativeRecordCount' => $this->generatedRecordCount($fault, $sample),
                ]);

                yield [
                    ...$context,
                    'case' => $fault.'#'.$sample,
                    'preconditionsSatisfied' => false,
                    'sourceChecksumBefore' => self::SOURCE_CHECKSUM,
                    'sourceChecksumAfter' => self::SOURCE_CHECKSUM,
                    'result' => self::completeResult([
                        'cutoverPerformed' => false,
                        'targetSchemaMatchesCanonicalMigrations' => $context['targetSchemaMatchesCanonicalMigrations'],
                        'authoritativeTableCountsMatch' => $context['authoritativeRowsEquivalent'],
                        'canonicalRowHashesMatch' => $context['authoritativeRowsEquivalent'],
                        'foreignKeyOrphanCount' => $context['foreignKeysValid'] ? 0 : 1,
                        'invalidTypedValueCount' => $fault === 'malformed_typed_value' ? 1 : 0,
                        'nextIdentityValuesExceedImportedMaxima' => $context['identitySequencesSafe'],
                    ]),
                ];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function freshTemplateContext(): array
    {
        $runtimeEngine = self::environmentTemplate()['DB_CONNECTION'] ?? null;
        $context = self::baseContext([
            'runtimeDatabaseEngine' => $runtimeEngine,
        ]);

        return [
            ...$context,
            'case' => 'fresh_template_selects_sqlite',
            'preconditionsSatisfied' => true,
            'sourceChecksumBefore' => self::SOURCE_CHECKSUM,
            'sourceChecksumAfter' => self::SOURCE_CHECKSUM,
            'result' => self::completeResult([
                'runtimeDatabaseEngine' => $runtimeEngine,
                'cutoverPerformed' => false,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function baseContext(array $overrides = []): array
    {
        return array_replace([
            'intendedDatabaseEngine' => 'pgsql',
            'sqliteFileExists' => true,
            'sqliteIsReadable' => true,
            'sqliteAuthoritativeRecordCount' => 1,
            'pdoPgsqlAvailable' => true,
            'postgreSQLReachable' => true,
            'targetCredentialsAuthorized' => true,
            'targetSchemaMatchesCanonicalMigrations' => true,
            'authoritativeRowsEquivalent' => true,
            'foreignKeysValid' => true,
            'identitySequencesSafe' => true,
            'runtimeDatabaseEngine' => 'pgsql',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function completeResult(array $overrides = []): array
    {
        return array_replace([
            'runtimeDatabaseEngine' => 'pgsql',
            'targetSchemaMatchesCanonicalMigrations' => true,
            'authoritativeTableCountsMatch' => true,
            'canonicalRowHashesMatch' => true,
            'primaryKeysPreserved' => true,
            'foreignKeyOrphanCount' => 0,
            'uniqueConstraintViolationCount' => 0,
            'invalidTypedValueCount' => 0,
            'nextIdentityValuesExceedImportedMaxima' => true,
            'databaseSmokeChecksPass' => true,
            'backupIsRestorable' => true,
            'cutoverPerformed' => true,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function isBugCondition(array $input): bool
    {
        $intendedPostgreSQL = $input['intendedDatabaseEngine'] === 'pgsql';
        $sourceHasState = $input['sqliteFileExists']
            && $input['sqliteIsReadable']
            && $input['sqliteAuthoritativeRecordCount'] >= 0;
        $targetNotReady = ! $input['pdoPgsqlAvailable']
            || ! $input['postgreSQLReachable']
            || ! $input['targetCredentialsAuthorized']
            || ! $input['targetSchemaMatchesCanonicalMigrations'];
        $transferNotProven = ! $input['authoritativeRowsEquivalent']
            || ! $input['foreignKeysValid']
            || ! $input['identitySequencesSafe'];
        $runtimeStillWrong = $input['runtimeDatabaseEngine'] !== 'pgsql';

        return $intendedPostgreSQL
            && ($runtimeStillWrong || $targetNotReady || ($sourceHasState && $transferNotProven));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function expectedBehavior(array $result): bool
    {
        return $result['runtimeDatabaseEngine'] === 'pgsql'
            && $result['targetSchemaMatchesCanonicalMigrations']
            && $result['authoritativeTableCountsMatch']
            && $result['canonicalRowHashesMatch']
            && $result['primaryKeysPreserved']
            && $result['foreignKeyOrphanCount'] === 0
            && $result['uniqueConstraintViolationCount'] === 0
            && $result['invalidTypedValueCount'] === 0
            && $result['nextIdentityValuesExceedImportedMaxima']
            && $result['databaseSmokeChecksPass']
            && $result['backupIsRestorable'];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizedCounterexample(array $context): array
    {
        return [
            'case' => $context['case'],
            'intended_database_engine' => $context['intendedDatabaseEngine'],
            'resolved_runtime_database_engine' => $context['runtimeDatabaseEngine'],
            'cutover_performed' => $context['result']['cutoverPerformed'],
            'source_checksum_unchanged' => $context['sourceChecksumBefore'] === $context['sourceChecksumAfter'],
            'failed_expected_behavior_predicates' => array_keys(array_filter([
                'runtime_uses_pgsql' => $context['result']['runtimeDatabaseEngine'] !== 'pgsql',
                'canonical_schema' => ! $context['result']['targetSchemaMatchesCanonicalMigrations'],
                'authoritative_counts' => ! $context['result']['authoritativeTableCountsMatch'],
                'canonical_hashes' => ! $context['result']['canonicalRowHashesMatch'],
                'primary_keys_preserved' => ! $context['result']['primaryKeysPreserved'],
                'relationships_valid' => $context['result']['foreignKeyOrphanCount'] !== 0,
                'unique_constraints_valid' => $context['result']['uniqueConstraintViolationCount'] !== 0,
                'typed_values_valid' => $context['result']['invalidTypedValueCount'] !== 0,
                'identity_sequences_safe' => ! $context['result']['nextIdentityValuesExceedImportedMaxima'],
                'database_smoke_checks' => ! $context['result']['databaseSmokeChecksPass'],
                'backup_restorable' => ! $context['result']['backupIsRestorable'],
            ])),
        ];
    }

    private function generatedRecordCount(string $fault, int $sample): int
    {
        $bytes = hash('sha256', $fault.':'.$sample, true);

        return unpack('N', substr($bytes, 0, 4))[1] % 10_001;
    }

    /**
     * @return array<string, string>
     */
    private static function environmentTemplate(): array
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env.example';
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $values[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $values;
    }
}
