<?php

namespace App\Database\Migration;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class MigrationPreflight
{
    private const REQUIRED_EXTENSIONS = ['PDO', 'pdo_pgsql', 'pgsql', 'pdo_sqlite', 'sqlite3'];

    private const REQUIRED_PDO_DRIVERS = ['pgsql', 'sqlite'];

    private const REQUIRED_BACKUP_TOOLS = ['psql', 'pg_dump', 'pg_restore'];

    /**
     * Evaluate previously collected, non-destructive preflight evidence.
     *
     * Missing or malformed evidence fails closed. This method never changes a
     * database connection, writes to the SQLite source, or authorizes cutover.
     *
     * @param array<string, mixed> $evidence
     * @param array<string, mixed> $manifestMetadata
     * @return array<string, mixed>
     */
    public function evaluate(array $evidence, array $manifestMetadata = []): array
    {
        $testEnvironmentSafe = $this->isSafeTestEnvironment($evidence['test_environment'] ?? null);
        $targetMayBeReplaced = $this->isTrue($evidence, 'target_is_dedicated')
            && ($this->isTrue($evidence, 'target_is_empty')
                || ($this->isTrue($evidence, 'target_backup_verified')
                    && $this->isTrue($evidence, 'target_replacement_approved')));

        $gates = [
            'supported_php_version' => $this->booleanGate($evidence, 'php_version_supported'),
            'supported_postgresql_version' => $this->booleanGate($evidence, 'postgresql_version_supported'),
            'cli_runtime_extensions' => $this->containsGate($evidence, 'cli_extensions', self::REQUIRED_EXTENSIONS),
            'web_runtime_extensions' => $this->containsGate($evidence, 'web_extensions', self::REQUIRED_EXTENSIONS),
            'cli_pdo_drivers' => $this->containsGate($evidence, 'cli_pdo_drivers', self::REQUIRED_PDO_DRIVERS),
            'web_pdo_drivers' => $this->containsGate($evidence, 'web_pdo_drivers', self::REQUIRED_PDO_DRIVERS),
            'postgresql_reachable' => $this->booleanGate($evidence, 'postgresql_reachable'),
            'target_credentials_authorized' => $this->booleanGate($evidence, 'target_credentials_authorized'),
            'tls_policy_satisfied' => $this->booleanGate($evidence, 'tls_policy_satisfied'),
            'encoding_policy_satisfied' => $this->booleanGate($evidence, 'encoding_policy_satisfied'),
            'timezone_policy_satisfied' => $this->booleanGate($evidence, 'timezone_policy_satisfied'),
            'backup_tools_available' => $this->containsGate($evidence, 'backup_tools', self::REQUIRED_BACKUP_TOOLS),
            'target_safe_for_preparation' => $this->derivedGate(
                $targetMayBeReplaced,
                $this->targetFailureReason($evidence)
            ),
            'database_environment_unambiguous' => $this->booleanGate($evidence, 'database_environment_unambiguous'),
            'source_readable' => $this->booleanGate($evidence, 'source_readable'),
            'source_opened_read_only' => $this->booleanGate($evidence, 'source_opened_read_only'),
            'sqlite_integrity_valid' => $this->booleanGate($evidence, 'sqlite_integrity_valid'),
            'source_checksum_unchanged' => $this->checksumGate($evidence),
            'disk_space_sufficient' => $this->booleanGate($evidence, 'disk_space_sufficient'),
            'migration_role_privileges_sufficient' => $this->booleanGate($evidence, 'migration_role_privileges_sufficient'),
            'source_constraints_compatible' => $this->booleanGate($evidence, 'source_constraints_compatible'),
            'canonical_migrations_available' => $this->booleanGate($evidence, 'canonical_migrations_available'),
            'test_database_isolated' => $this->derivedGate(
                $testEnvironmentSafe,
                $this->testEnvironmentFailureReason($evidence['test_environment'] ?? null)
            ),
        ];

        $passed = ! in_array(false, array_column($gates, 'passed'), true);

        return [
            'manifest_version' => 1,
            'phase' => 'preflight',
            'generated_at' => gmdate(DATE_ATOM),
            'preflight_passed' => $passed,
            'abort_required' => ! $passed,
            'cutover_performed' => false,
            'runtime_change_permitted' => false,
            'source_mutation_permitted' => false,
            'gates' => $gates,
            'metadata' => $this->redact($manifestMetadata),
        ];
    }

    /**
     * Refuse unsafe test processes before they can resolve a deployment database.
     *
     * @param array<string, mixed> $environment
     */
    public function assertSafeTestEnvironment(array $environment): void
    {
        if (($environment['APP_ENV'] ?? null) !== 'testing') {
            return;
        }

        if (! $this->isSafeTestEnvironment($environment)) {
            throw new RuntimeException(
                'Unsafe test database configuration: testing requires sqlite :memory: with an empty DB_URL.'
            );
        }
    }

    /**
     * Persist a manifest containing only redacted metadata and gate outcomes.
     *
     * @param array<string, mixed> $manifest
     */
    public function writeManifest(string $path, array $manifest): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new InvalidArgumentException('The manifest directory must already exist.');
        }

        $safeManifest = $this->redact($manifest);

        try {
            $json = json_encode($safeManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The preflight manifest is not JSON serializable.', 0, $exception);
        }

        $temporaryPath = tempnam($directory, '.preflight-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary preflight manifest.');
        }

        try {
            if (file_put_contents($temporaryPath, $json."\n", LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the preflight manifest.');
            }

            @chmod($temporaryPath, 0600);

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException('Unable to publish the preflight manifest atomically.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @param mixed $environment
     */
    private function isSafeTestEnvironment(mixed $environment): bool
    {
        if (! is_array($environment)) {
            return false;
        }

        if (($environment['APP_ENV'] ?? null) !== 'testing') {
            return true;
        }

        return ($environment['DB_CONNECTION'] ?? null) === 'sqlite'
            && ($environment['DB_DATABASE'] ?? null) === ':memory:'
            && ($environment['DB_URL'] ?? null) === '';
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array{passed: bool, reason: string}
     */
    private function booleanGate(array $evidence, string $key): array
    {
        if (! array_key_exists($key, $evidence) || ! is_bool($evidence[$key])) {
            return $this->derivedGate(false, 'evidence_missing_or_invalid');
        }

        return $this->derivedGate($evidence[$key], $evidence[$key] ? 'verified' : 'verification_failed');
    }

    /**
     * @param array<string, mixed> $evidence
     * @param array<int, string> $requiredValues
     * @return array{passed: bool, reason: string}
     */
    private function containsGate(array $evidence, string $key, array $requiredValues): array
    {
        if (! isset($evidence[$key]) || ! is_array($evidence[$key])) {
            return $this->derivedGate(false, 'evidence_missing_or_invalid');
        }

        $actual = array_values(array_filter($evidence[$key], 'is_string'));
        $missing = array_diff($requiredValues, $actual);

        return $this->derivedGate($missing === [], $missing === [] ? 'verified' : 'required_capability_missing');
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array{passed: bool, reason: string}
     */
    private function checksumGate(array $evidence): array
    {
        $before = $evidence['source_checksum_before'] ?? null;
        $after = $evidence['source_checksum_after'] ?? null;
        $valid = is_string($before) && $before !== '' && is_string($after) && hash_equals($before, $after);

        return $this->derivedGate($valid, $valid ? 'verified' : 'source_checksum_missing_or_changed');
    }

    /**
     * @return array{passed: bool, reason: string}
     */
    private function derivedGate(bool $passed, string $reason): array
    {
        return ['passed' => $passed, 'reason' => $reason];
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private function isTrue(array $evidence, string $key): bool
    {
        return array_key_exists($key, $evidence) && $evidence[$key] === true;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private function targetFailureReason(array $evidence): string
    {
        if (! $this->isTrue($evidence, 'target_is_dedicated')) {
            return 'target_not_proven_dedicated';
        }

        if ($this->isTrue($evidence, 'target_is_empty')) {
            return 'verified';
        }

        if (! $this->isTrue($evidence, 'target_backup_verified')) {
            return 'populated_target_backup_not_verified';
        }

        if (! $this->isTrue($evidence, 'target_replacement_approved')) {
            return 'populated_target_replacement_not_approved';
        }

        return 'verified';
    }

    /**
     * @param mixed $environment
     */
    private function testEnvironmentFailureReason(mixed $environment): string
    {
        if (! is_array($environment)) {
            return 'test_environment_evidence_missing';
        }

        if (($environment['APP_ENV'] ?? null) !== 'testing') {
            return 'not_a_test_process';
        }

        if ($this->isSafeTestEnvironment($environment)) {
            return 'verified';
        }

        return 'unsafe_test_database_configuration';
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(
            '/(?:^|_)(?:password|passwd|secret|token|app_key|private_key|credential|credentials|username|host|database|database_name|db_url|database_url|dsn|url)$/i',
            $key
        ) === 1;
    }

    private function redactScalar(string $value): string
    {
        $redacted = preg_replace('#\b[a-z][a-z0-9+.-]*://[^\s]+#i', '[REDACTED_URL]', $value);

        return $redacted ?? '[REDACTED]';
    }

    /**
     * @return mixed
     */
    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $nestedKey => $nestedValue) {
                $redacted[$nestedKey] = $this->redact(
                    $nestedValue,
                    is_string($nestedKey) ? $nestedKey : null
                );
            }

            return $redacted;
        }

        return is_string($value) ? $this->redactScalar($value) : $value;
    }
}
