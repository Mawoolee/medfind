<?php

namespace App\Database\Migration;

use InvalidArgumentException;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

final class SourcePreparation
{
    private const WRITER_GATES = [
        'writable_http_stopped',
        'queue_workers_stopped',
        'scheduler_stopped',
        'persisting_event_consumers_stopped',
        'manual_writers_stopped',
    ];

    /**
     * @param  array<string, array{classification: string, columns: array<int, string>}>|null  $schemaPolicy
     */
    public function __construct(private readonly ?array $schemaPolicy = null) {}

    /**
     * Create and restoration-test a quiesced backup, then inventory the source.
     *
     * This method never stops services, changes runtime configuration, performs
     * cutover, or writes through the source SQLite connection. All paths must
     * be explicitly supplied, and existing backup artifacts are never replaced.
     *
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $sessionDecision
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function prepare(
        string $sourcePath,
        string $backupPath,
        string $restorationPath,
        array $evidence,
        array $sessionDecision,
        array $metadata = []
    ): array {
        $manifest = $this->newManifest($metadata);
        $manifest['gates']['quiescence'] = $this->quiescenceGate($evidence);
        $manifest['gates']['deployment_configuration_preserved'] = $this->booleanGate(
            $evidence,
            'deployment_configuration_preserved'
        );
        $manifest['gates']['configuration_cache_preserved'] = $this->booleanGate(
            $evidence,
            'configuration_cache_preserved'
        );
        $manifest['gates']['session_decision'] = $this->sessionGate($sessionDecision);

        if ($this->hasFailedGate($manifest)) {
            return $this->finalize($manifest);
        }

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            $manifest['gates']['source_readable'] = $this->gate(false, 'source_missing_or_unreadable');

            return $this->finalize($manifest);
        }

        $manifest['gates']['source_readable'] = $this->gate(true, 'verified');

        if ($this->hasPendingWal($sourcePath)) {
            $manifest['gates']['source_wal_safe'] = $this->gate(false, 'unmerged_wal_detected');

            return $this->finalize($manifest);
        }

        $manifest['gates']['source_wal_safe'] = $this->gate(true, 'verified');
        $sourceChecksumBefore = hash_file('sha256', $sourcePath);

        if (! is_string($sourceChecksumBefore)) {
            $manifest['gates']['source_checksum_recorded'] = $this->gate(false, 'checksum_failed');

            return $this->finalize($manifest);
        }

        $manifest['gates']['source_checksum_recorded'] = $this->gate(true, 'verified');
        $manifest['source']['checksum_sha256'] = $sourceChecksumBefore;
        $manifest['source']['path'] = $sourcePath;

        try {
            $source = $this->openReadOnly($sourcePath);
            $manifest['gates']['source_opened_read_only'] = $this->gate(true, 'verified');
        } catch (PDOException|RuntimeException $exception) {
            $manifest['gates']['source_opened_read_only'] = $this->gate(false, 'read_only_open_failed');

            return $this->finalize($manifest);
        }

        if (! $this->integrityIsValid($source)) {
            $manifest['gates']['sqlite_integrity_valid'] = $this->gate(false, 'integrity_check_failed');

            return $this->finalize($manifest);
        }

        $manifest['gates']['sqlite_integrity_valid'] = $this->gate(true, 'verified');

        try {
            $this->copyRestricted($sourcePath, $backupPath);
            $manifest['gates']['backup_created'] = $this->gate(true, 'verified');
        } catch (RuntimeException $exception) {
            $manifest['gates']['backup_created'] = $this->gate(false, 'backup_creation_failed');

            return $this->finalize($manifest);
        }

        $backupChecksum = hash_file('sha256', $backupPath);
        $backupMatches = is_string($backupChecksum) && hash_equals($sourceChecksumBefore, $backupChecksum);
        $manifest['gates']['backup_checksum_matches'] = $this->gate(
            $backupMatches,
            $backupMatches ? 'verified' : 'backup_checksum_mismatch'
        );
        $manifest['backup'] = [
            'path' => $backupPath,
            'checksum_sha256' => $backupChecksum ?: null,
            'size_bytes' => filesize($backupPath) ?: 0,
            'created_at' => gmdate(DATE_ATOM, filemtime($backupPath) ?: time()),
            'restricted_permissions_requested' => true,
        ];

        if (! $backupMatches) {
            return $this->finalize($manifest);
        }

        try {
            $this->copyRestricted($backupPath, $restorationPath);
            $restored = $this->openReadOnly($restorationPath);
            $restorationChecksum = hash_file('sha256', $restorationPath);
            $restorationPassed = is_string($restorationChecksum)
                && hash_equals($backupChecksum, $restorationChecksum)
                && $this->integrityIsValid($restored);
            $manifest['gates']['backup_restoration_test'] = $this->gate(
                $restorationPassed,
                $restorationPassed ? 'verified' : 'restoration_verification_failed'
            );
            $manifest['backup']['restoration_test'] = [
                'passed' => $restorationPassed,
                'restored_path' => $restorationPath,
                'checksum_sha256' => $restorationChecksum ?: null,
            ];
        } catch (PDOException|RuntimeException $exception) {
            $manifest['gates']['backup_restoration_test'] = $this->gate(false, 'restoration_test_failed');
            $manifest['backup']['restoration_test'] = ['passed' => false];

            return $this->finalize($manifest);
        }

        if (! $manifest['gates']['backup_restoration_test']['passed']) {
            return $this->finalize($manifest);
        }

        $inventory = $this->inventory($source, $sessionDecision);
        $manifest['inventory'] = $inventory;
        $manifest['gates']['schema_fully_classified'] = $this->gate(
            $inventory['schema_fully_classified'],
            $inventory['schema_fully_classified'] ? 'verified' : 'unknown_or_missing_schema'
        );

        $sourceChecksumAfter = hash_file('sha256', $sourcePath);
        $sourceUnchanged = is_string($sourceChecksumAfter)
            && hash_equals($sourceChecksumBefore, $sourceChecksumAfter);
        $manifest['gates']['source_checksum_unchanged'] = $this->gate(
            $sourceUnchanged,
            $sourceUnchanged ? 'verified' : 'source_checksum_changed'
        );

        return $this->finalize($manifest);
    }

    /**
     * Persist a redacted manifest atomically without replacing an existing one.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function writeManifest(string $path, array $manifest): void
    {
        if (file_exists($path)) {
            throw new InvalidArgumentException('An existing source preparation manifest will not be replaced.');
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new InvalidArgumentException('The manifest directory must already exist.');
        }

        try {
            $json = json_encode(
                $this->redact($manifest),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The source preparation manifest is not JSON serializable.', 0, $exception);
        }

        $temporaryPath = tempnam($directory, '.source-preparation-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary source preparation manifest.');
        }

        try {
            if (file_put_contents($temporaryPath, $json."\n", LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the source preparation manifest.');
            }

            @chmod($temporaryPath, 0600);

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException('Unable to publish the source preparation manifest atomically.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @return array<string, array{classification: string, columns: array<int, string>}>
     */
    public static function medFindSchemaPolicy(): array
    {
        $authoritative = [
            'users' => ['id', 'name', 'email', 'email_verified_at', 'password', 'role', 'pharmacy_id', 'remember_token', 'created_at', 'updated_at'],
            'pharmacies' => ['id', 'pharmacy_name', 'pharmacyAddress', 'latitude', 'longitude', 'contactNumber', 'status', 'user_id', 'created_at', 'updated_at', 'logo_path', 'requirements', 'operating_hours'],
            'medicines' => ['id', 'medicine_name', 'dosage', 'manufacturer', 'requiresPrescription', 'category', 'created_at', 'updated_at'],
            'inventory_items' => ['id', 'pharmacy_id', 'medicine_id', 'stockQuantity', 'price', 'status', 'created_at', 'updated_at', 'expiry_date', 'batch_number', 'cold_chain', 'par_level', 'supplier_id'],
            'messages' => ['id', 'consumer_id', 'pharmacy_id', 'message', 'prescription_image', 'reply', 'replied_at', 'is_read', 'created_at', 'updated_at', 'verified_by', 'verification_status', 'verification_notes', 'verified_at', 'attachments', 'sender'],
            'suppliers' => ['id', 'name', 'contact_person', 'phone', 'email', 'address', 'created_at', 'updated_at'],
            'controlled_substance_logs' => ['id', 'inventory_item_id', 'user_id', 'action', 'quantity', 'notes', 'logged_at', 'created_at', 'updated_at'],
            'cycle_counts' => ['id', 'pharmacy_id', 'name', 'notes', 'scheduled_at', 'completed_at', 'conducted_by', 'created_at', 'updated_at'],
            'cycle_count_items' => ['id', 'cycle_count_id', 'inventory_item_id', 'expected_quantity', 'counted_quantity', 'notes', 'created_at', 'updated_at'],
            'returns_recalls' => ['id', 'inventory_item_id', 'type', 'quantity', 'reason', 'status', 'requested_by', 'created_at', 'updated_at'],
            'inventory_audits' => ['id', 'inventory_item_id', 'user_id', 'before_quantity', 'after_quantity', 'notes', 'created_at', 'updated_at'],
            'search_logs' => ['id', 'pharmacy_id', 'query', 'created_at', 'updated_at'],
            'notifications' => ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'],
            'activity_logs' => ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'details', 'created_at', 'updated_at'],
            'survey_responses' => ['id', 'user_id', 'respondent_type', 'respondent_name', 'fs_completeness', 'fs_correctness', 'fs_appropriateness', 'us_recognisability', 'us_learnability', 'us_operability', 'us_error_protection', 'us_aesthetics', 'se_confidentiality', 'se_integrity', 'se_accountability', 'comments', 'created_at', 'updated_at'],
            'sessions' => ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'],
        ];
        $operational = [
            'migrations' => ['id', 'migration', 'batch'],
            'cache' => ['key', 'value', 'expiration'],
            'cache_locks' => ['key', 'owner', 'expiration'],
        ];
        $policy = [];

        foreach ($authoritative as $table => $columns) {
            $policy[$table] = ['classification' => 'authoritative', 'columns' => $columns];
        }

        foreach ($operational as $table => $columns) {
            $policy[$table] = ['classification' => 'operational', 'columns' => $columns];
        }

        return $policy;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function newManifest(array $metadata): array
    {
        return [
            'manifest_version' => 1,
            'phase' => 'backup_and_source_inventory',
            'generated_at' => gmdate(DATE_ATOM),
            'preparation_passed' => false,
            'abort_required' => true,
            'cutover_performed' => false,
            'runtime_change_permitted' => false,
            'source_mutation_permitted' => false,
            'gates' => [],
            'source' => [],
            'backup' => [],
            'inventory' => [],
            'metadata' => $this->redact($metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{passed: bool, reason: string, checks?: array<string, bool>}
     */
    private function quiescenceGate(array $evidence): array
    {
        $checks = [];

        foreach (self::WRITER_GATES as $key) {
            $checks[$key] = ($evidence[$key] ?? null) === true;
        }

        $activeWrites = $evidence['active_sqlite_write_transactions'] ?? null;
        $checks['no_active_sqlite_write_transactions'] = is_int($activeWrites) && $activeWrites === 0;
        $passed = ! in_array(false, $checks, true);

        return [
            'passed' => $passed,
            'reason' => $passed ? 'verified' : 'writers_not_proven_stopped',
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array{passed: bool, reason: string, mode?: string}
     */
    private function sessionGate(array $decision): array
    {
        $mode = $decision['mode'] ?? null;

        if ($mode === 'transfer') {
            $passed = ($decision['continuity_approved'] ?? null) === true
                && ($decision['app_key_retained'] ?? null) === true;

            return [
                'passed' => $passed,
                'reason' => $passed ? 'session_transfer_approved' : 'session_transfer_requirements_incomplete',
                'mode' => $mode,
            ];
        }

        if ($mode === 'forced_logout') {
            $passed = ($decision['forced_logout_approved'] ?? null) === true;

            return [
                'passed' => $passed,
                'reason' => $passed ? 'forced_logout_approved' : 'forced_logout_not_approved',
                'mode' => $mode,
            ];
        }

        return ['passed' => false, 'reason' => 'session_decision_missing_or_invalid'];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{passed: bool, reason: string}
     */
    private function booleanGate(array $evidence, string $key): array
    {
        if (! array_key_exists($key, $evidence) || ! is_bool($evidence[$key])) {
            return $this->gate(false, 'evidence_missing_or_invalid');
        }

        return $this->gate($evidence[$key], $evidence[$key] ? 'verified' : 'verification_failed');
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function hasFailedGate(array $manifest): bool
    {
        foreach ($manifest['gates'] as $gate) {
            if (($gate['passed'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    private function hasPendingWal(string $sourcePath): bool
    {
        $walPath = $sourcePath.'-wal';

        return is_file($walPath) && (filesize($walPath) ?: 0) > 0;
    }

    private function openReadOnly(string $path): PDO
    {
        $absolutePath = realpath($path);

        if ($absolutePath === false) {
            throw new RuntimeException('SQLite source path cannot be resolved.');
        }

        $normalized = str_replace('\\', '/', $absolutePath);
        $encoded = str_replace(['%2F', '%3A'], ['/', ':'], rawurlencode($normalized));
        $pdo = new PDO('sqlite:file:'.$encoded.'?mode=ro', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA query_only = ON');
        $queryOnly = $pdo->query('PRAGMA query_only')->fetchColumn();

        if ((int) $queryOnly !== 1) {
            throw new RuntimeException('SQLite query-only mode was not enabled.');
        }

        return $pdo;
    }

    private function integrityIsValid(PDO $pdo): bool
    {
        $result = $pdo->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);

        return $result === ['ok'];
    }

    private function copyRestricted(string $from, string $to): void
    {
        if (file_exists($to)) {
            throw new RuntimeException('Existing artifacts are never replaced.');
        }

        $directory = dirname($to);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('Artifact directory is unavailable.');
        }

        $temporaryPath = tempnam($directory, '.migration-artifact-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary artifact.');
        }

        try {
            if (! copy($from, $temporaryPath)) {
                throw new RuntimeException('Unable to copy the migration artifact.');
            }

            if (! @chmod($temporaryPath, 0600)) {
                throw new RuntimeException('Unable to request restricted artifact permissions.');
            }

            if (! rename($temporaryPath, $to)) {
                throw new RuntimeException('Unable to publish the migration artifact atomically.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sessionDecision
     * @return array<string, mixed>
     */
    private function inventory(PDO $pdo, array $sessionDecision): array
    {
        $policy = $this->schemaPolicy ?? self::medFindSchemaPolicy();
        $tableRows = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll();
        $actualTables = array_column($tableRows, 'name');
        $expectedTables = array_keys($policy);
        $unknownTables = array_values(array_diff($actualTables, $expectedTables));
        $missingTables = array_values(array_diff($expectedTables, $actualTables));
        $unknownColumns = [];
        $missingColumns = [];
        $tables = [];

        foreach ($tableRows as $tableRow) {
            $table = $tableRow['name'];

            if (! isset($policy[$table])) {
                continue;
            }

            $quotedTable = $this->quoteIdentifier($table);
            $columnRows = $pdo->query("PRAGMA table_info({$quotedTable})")->fetchAll();
            $actualColumns = array_column($columnRows, 'name');
            $expectedColumns = $policy[$table]['columns'];
            $extra = array_values(array_diff($actualColumns, $expectedColumns));
            $missing = array_values(array_diff($expectedColumns, $actualColumns));

            if ($extra !== []) {
                $unknownColumns[$table] = $extra;
            }

            if ($missing !== []) {
                $missingColumns[$table] = $missing;
            }

            $classification = $policy[$table]['classification'];

            if ($table === 'sessions' && ($sessionDecision['mode'] ?? null) === 'forced_logout') {
                $classification = 'explicitly_excluded';
            }

            $tables[$table] = [
                'classification' => $classification,
                'columns' => array_map(
                    static fn (array $column): array => [
                        'name' => $column['name'],
                        'declared_type' => $column['type'],
                        'nullable' => (int) $column['notnull'] === 0,
                        'primary_key_position' => (int) $column['pk'],
                    ],
                    $columnRows
                ),
                'indexes' => $this->indexes($pdo, $table),
                'foreign_keys' => $this->foreignKeys($pdo, $table),
                'row_count' => (int) $pdo->query("SELECT COUNT(*) FROM {$quotedTable}")->fetchColumn(),
                'primary_key_range' => $this->primaryKeyRange($pdo, $table, $columnRows),
            ];
        }

        $migrationNames = [];

        if (isset($tables['migrations']) && ! isset($unknownColumns['migrations']) && ! isset($missingColumns['migrations'])) {
            $migrationNames = $pdo->query('SELECT migration FROM "migrations" ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        }

        $fullyClassified = $unknownTables === []
            && $missingTables === []
            && $unknownColumns === []
            && $missingColumns === [];

        return [
            'schema_fully_classified' => $fullyClassified,
            'unknown_tables' => $unknownTables,
            'missing_tables' => $missingTables,
            'unknown_columns' => $unknownColumns,
            'missing_columns' => $missingColumns,
            'migration_names' => $migrationNames,
            'sessions' => [
                'decision' => $sessionDecision['mode'],
                'included' => ($sessionDecision['mode'] ?? null) === 'transfer',
            ],
            'tables' => $tables,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexes(PDO $pdo, string $table): array
    {
        $rows = $pdo->query('PRAGMA index_list('.$this->quoteIdentifier($table).')')->fetchAll();
        $indexes = [];

        foreach ($rows as $row) {
            $name = $row['name'];
            $columnRows = $pdo->query('PRAGMA index_info('.$this->quoteIdentifier($name).')')->fetchAll();
            $indexes[] = [
                'name' => $name,
                'unique' => (int) $row['unique'] === 1,
                'columns' => array_column($columnRows, 'name'),
            ];
        }

        return $indexes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function foreignKeys(PDO $pdo, string $table): array
    {
        return array_map(
            static fn (array $row): array => [
                'from' => $row['from'],
                'to_table' => $row['table'],
                'to_column' => $row['to'],
                'on_update' => $row['on_update'],
                'on_delete' => $row['on_delete'],
            ],
            $pdo->query('PRAGMA foreign_key_list('.$this->quoteIdentifier($table).')')->fetchAll()
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $columnRows
     * @return array<string, mixed>|null
     */
    private function primaryKeyRange(PDO $pdo, string $table, array $columnRows): ?array
    {
        $primaryColumns = array_values(array_filter(
            $columnRows,
            static fn (array $column): bool => (int) $column['pk'] > 0
        ));

        if (count($primaryColumns) !== 1) {
            return null;
        }

        $column = $primaryColumns[0];
        $quotedTable = $this->quoteIdentifier($table);
        $quotedColumn = $this->quoteIdentifier($column['name']);
        $range = $pdo->query(
            "SELECT MIN({$quotedColumn}) AS minimum, MAX({$quotedColumn}) AS maximum FROM {$quotedTable}"
        )->fetch();

        if ($range['minimum'] === null) {
            return ['column' => $column['name'], 'minimum' => null, 'maximum' => null];
        }

        if (str_contains(strtoupper((string) $column['type']), 'INT')) {
            return [
                'column' => $column['name'],
                'minimum' => (int) $range['minimum'],
                'maximum' => (int) $range['maximum'],
            ];
        }

        return [
            'column' => $column['name'],
            'minimum_sha256' => hash('sha256', (string) $range['minimum']),
            'maximum_sha256' => hash('sha256', (string) $range['maximum']),
            'values_redacted' => true,
        ];
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException('Unsafe SQLite schema identifier.');
        }

        return '"'.$identifier.'"';
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function finalize(array $manifest): array
    {
        $passed = $manifest['gates'] !== [] && ! $this->hasFailedGate($manifest);
        $manifest['preparation_passed'] = $passed;
        $manifest['abort_required'] = ! $passed;

        return $this->redact($manifest);
    }

    /**
     * @return array{passed: bool, reason: string}
     */
    private function gate(bool $passed, string $reason): array
    {
        return ['passed' => $passed, 'reason' => $reason];
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
