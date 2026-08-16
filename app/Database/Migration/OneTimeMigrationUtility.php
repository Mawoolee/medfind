<?php

namespace App\Database\Migration;

use InvalidArgumentException;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class OneTimeMigrationUtility
{
    private const TRANSFER_ORDER = [
        'users',
        'pharmacies',
        'medicines',
        'suppliers',
        'inventory_items',
        'messages',
        'cycle_counts',
        'cycle_count_items',
        'controlled_substance_logs',
        'returns_recalls',
        'inventory_audits',
        'search_logs',
        'notifications',
        'activity_logs',
        'survey_responses',
        'sessions',
    ];

    private MigrationValueNormalizer $normalizer;

    /**
     * @param  array<string, array{primary_key: string, columns: array<string, array{type: string, nullable: bool, precision?: int, scale?: int}>}>|null  $policy
     */
    public function __construct(
        private readonly int $batchSize = 500,
        private readonly ?array $policy = null,
        ?MigrationValueNormalizer $normalizer = null
    ) {
        if ($batchSize < 1 || $batchSize > 10_000) {
            throw new InvalidArgumentException('The transfer batch size must be between 1 and 10000.');
        }

        $this->normalizer = $normalizer ?? new MigrationValueNormalizer;
    }

    /**
     * Transfer an immutable SQLite source into an unaccepted PostgreSQL schema.
     *
     * The caller must provide successful task 3.1/3.2 evidence. This method
     * never creates schema, changes runtime configuration, authorizes traffic,
     * repairs sequences, invokes Eloquent, or runs seeders.
     *
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function transfer(PDO $source, PDO $target, array $evidence, array $metadata = []): array
    {
        $manifest = $this->newManifest($evidence, $metadata);
        $manifest['gates'] = $this->evaluateGates($source, $target, $evidence);

        if ($this->hasFailedGate($manifest['gates'])) {
            return $this->finalize($manifest);
        }

        $tables = $this->selectedTables($evidence);

        try {
            $this->assertSchemaAvailable($source, $target, $tables);
            $this->assertTargetEmpty($target, $tables);
            $manifest['gates']['source_and_target_schema_available'] = $this->gate(true, 'verified');
            $manifest['gates']['target_transfer_tables_empty'] = $this->gate(true, 'verified');
        } catch (Throwable) {
            $manifest['gates']['source_and_target_schema_available'] = $this->gate(false, 'schema_missing_or_incompatible');
            $manifest['gates']['target_transfer_tables_empty'] = $this->gate(false, 'target_not_proven_empty');

            return $this->finalize($manifest);
        }

        try {
            if (! $target->beginTransaction()) {
                throw new RuntimeException('Unable to start the target transaction.');
            }

            $sourceEvidence = [];
            $deferredPharmacyIds = [];

            foreach ($tables as $table) {
                $sourceEvidence[$table] = $this->transferTable(
                    $source,
                    $target,
                    $table,
                    $deferredPharmacyIds
                );

                if ($table === 'pharmacies') {
                    $this->restoreUserPharmacyLinks($target, $deferredPharmacyIds);
                }
            }

            $targetEvidence = $this->collectTargetEvidence($target, $tables);
            $this->assertEquivalent($sourceEvidence, $targetEvidence);

            if (! $target->commit()) {
                throw new RuntimeException('Unable to commit the target transaction.');
            }

            $manifest['gates']['transactional_transfer'] = $this->gate(true, 'committed_to_unaccepted_target');
            $manifest['gates']['authoritative_equivalence'] = $this->gate(true, 'verified');
            $manifest['tables'] = $this->combineEvidence($sourceEvidence, $targetEvidence);
            $manifest['normalization'] = [
                'invalid_value_count' => 0,
                'timezone_interpretation' => 'UTC for timezone-naive SQLite timestamps',
                'json_semantics_validated' => true,
                'binary_float_decimal_conversion_used' => false,
                'sql_null_preserved_distinctly' => true,
            ];
            $manifest['sequence_status'] = [
                'state' => 'pending_task_3_8_repair_and_verification',
                'cutover_blocked_until_verified' => true,
            ];
            $manifest['transfer_passed'] = true;
            $manifest['abort_required'] = false;
        } catch (Throwable $exception) {
            if ($target->inTransaction()) {
                try {
                    $target->rollBack();
                } catch (Throwable) {
                    // The manifest remains fail-closed even if the disposable
                    // target must be discarded after a rollback failure.
                }
            }

            $manifest['gates']['transactional_transfer'] = $this->gate(false, 'rolled_back_or_target_must_be_discarded');
            $manifest['gates']['authoritative_equivalence'] = $this->gate(false, 'not_proven');
            $manifest['failure'] = $exception instanceof MigrationTransferException
                ? $exception->safeContext()
                : ['reason' => 'unexpected_transfer_failure'];
        }

        return $this->finalize($manifest);
    }

    /**
     * Persist a redacted manifest atomically without replacing prior evidence.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function writeManifest(string $path, array $manifest): void
    {
        if (file_exists($path)) {
            throw new InvalidArgumentException('An existing transfer manifest will not be replaced.');
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
            throw new InvalidArgumentException('The transfer manifest is not JSON serializable.', 0, $exception);
        }

        $temporaryPath = tempnam($directory, '.transfer-manifest-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary transfer manifest.');
        }

        try {
            if (file_put_contents($temporaryPath, $json."\n", LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the transfer manifest.');
            }

            @chmod($temporaryPath, 0600);

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException('Unable to publish the transfer manifest atomically.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * @return array<string, array{primary_key: string, columns: array<string, array{type: string, nullable: bool, precision?: int, scale?: int}>}>
     */
    public static function medFindTransferPolicy(): array
    {
        $integer = ['type' => 'integer', 'nullable' => false];
        $nullableInteger = ['type' => 'integer', 'nullable' => true];
        $string = ['type' => 'string', 'nullable' => false];
        $nullableString = ['type' => 'string', 'nullable' => true];
        $timestamp = ['type' => 'timestamp', 'nullable' => true];
        $opaque = ['type' => 'opaque', 'nullable' => false];
        $nullableOpaque = ['type' => 'opaque', 'nullable' => true];

        return [
            'users' => self::table('id', [
                'id' => $integer, 'name' => $string, 'email' => $string, 'email_verified_at' => $timestamp,
                'password' => $opaque, 'role' => $string, 'pharmacy_id' => $nullableInteger,
                'remember_token' => $nullableOpaque, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'pharmacies' => self::table('id', [
                'id' => $integer, 'pharmacy_name' => $string, 'pharmacyAddress' => $string,
                'latitude' => ['type' => 'decimal', 'nullable' => true, 'precision' => 10, 'scale' => 7],
                'longitude' => ['type' => 'decimal', 'nullable' => true, 'precision' => 10, 'scale' => 7],
                'contactNumber' => $nullableString, 'operating_hours' => $nullableString, 'status' => $string,
                'user_id' => $nullableInteger, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                'logo_path' => $nullableOpaque, 'requirements' => ['type' => 'json', 'nullable' => true],
            ]),
            'medicines' => self::table('id', [
                'id' => $integer, 'medicine_name' => $string, 'dosage' => $string, 'manufacturer' => $string,
                'requiresPrescription' => ['type' => 'boolean', 'nullable' => false], 'category' => $nullableString,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'suppliers' => self::table('id', [
                'id' => $integer, 'name' => $string, 'contact_person' => $nullableString, 'phone' => $nullableString,
                'email' => $nullableString, 'address' => $nullableString, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'inventory_items' => self::table('id', [
                'id' => $integer, 'pharmacy_id' => $integer, 'medicine_id' => $integer,
                'stockQuantity' => $integer, 'price' => ['type' => 'decimal', 'nullable' => false, 'precision' => 10, 'scale' => 2],
                'status' => $string, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                'expiry_date' => ['type' => 'date', 'nullable' => true], 'batch_number' => $nullableString,
                'cold_chain' => ['type' => 'boolean', 'nullable' => false], 'par_level' => $integer,
                'supplier_id' => $nullableInteger,
            ]),
            'messages' => self::table('id', [
                'id' => $integer, 'consumer_id' => $integer, 'pharmacy_id' => $integer, 'message' => $opaque,
                'prescription_image' => $nullableOpaque, 'reply' => $nullableOpaque, 'replied_at' => $timestamp,
                'is_read' => ['type' => 'boolean', 'nullable' => false], 'created_at' => $timestamp,
                'updated_at' => $timestamp, 'verified_by' => $nullableInteger,
                'verification_status' => $nullableString, 'verification_notes' => $nullableOpaque,
                'verified_at' => $timestamp, 'attachments' => ['type' => 'json', 'nullable' => true],
                'sender' => $string,
            ]),
            'cycle_counts' => self::table('id', [
                'id' => $integer, 'pharmacy_id' => $integer, 'name' => $string, 'notes' => $nullableOpaque,
                'scheduled_at' => $timestamp, 'completed_at' => $timestamp, 'conducted_by' => $nullableInteger,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'cycle_count_items' => self::table('id', [
                'id' => $integer, 'cycle_count_id' => $integer, 'inventory_item_id' => $integer,
                'expected_quantity' => $integer, 'counted_quantity' => $integer, 'notes' => $nullableOpaque,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'controlled_substance_logs' => self::table('id', [
                'id' => $integer, 'inventory_item_id' => $integer, 'user_id' => $nullableInteger,
                'action' => $string, 'quantity' => $integer, 'notes' => $nullableOpaque, 'logged_at' => $timestamp,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'returns_recalls' => self::table('id', [
                'id' => $integer, 'inventory_item_id' => $integer, 'type' => $string, 'quantity' => $integer,
                'reason' => $nullableOpaque, 'status' => $string, 'requested_by' => $nullableInteger,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'inventory_audits' => self::table('id', [
                'id' => $integer, 'inventory_item_id' => $integer, 'user_id' => $nullableInteger,
                'before_quantity' => $integer, 'after_quantity' => $integer, 'notes' => $nullableOpaque,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'search_logs' => self::table('id', [
                'id' => $integer, 'pharmacy_id' => $integer, 'query' => $nullableString,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'notifications' => self::table('id', [
                'id' => ['type' => 'uuid', 'nullable' => false], 'type' => $string,
                'notifiable_type' => $string, 'notifiable_id' => $integer, 'data' => $opaque,
                'read_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'activity_logs' => self::table('id', [
                'id' => $integer, 'user_id' => $nullableInteger, 'action' => $string,
                'entity_type' => $nullableString, 'entity_id' => $nullableInteger, 'details' => $nullableOpaque,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'survey_responses' => self::table('id', [
                'id' => $integer, 'user_id' => $nullableInteger, 'respondent_type' => $string,
                'respondent_name' => $nullableString, 'fs_completeness' => $nullableInteger,
                'fs_correctness' => $nullableInteger, 'fs_appropriateness' => $nullableInteger,
                'us_recognisability' => $nullableInteger, 'us_learnability' => $nullableInteger,
                'us_operability' => $nullableInteger, 'us_error_protection' => $nullableInteger,
                'us_aesthetics' => $nullableInteger, 'se_confidentiality' => $nullableInteger,
                'se_integrity' => $nullableInteger, 'se_accountability' => $nullableInteger,
                'comments' => $nullableOpaque, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]),
            'sessions' => self::table('id', [
                'id' => $string, 'user_id' => $nullableInteger, 'ip_address' => $nullableOpaque,
                'user_agent' => $nullableOpaque, 'payload' => $opaque, 'last_activity' => $integer,
            ]),
        ];
    }

    /**
     * @param  array<string, array{type: string, nullable: bool, precision?: int, scale?: int}>  $columns
     * @return array{primary_key: string, columns: array<string, array{type: string, nullable: bool, precision?: int, scale?: int}>}
     */
    private static function table(string $primaryKey, array $columns): array
    {
        return ['primary_key' => $primaryKey, 'columns' => $columns];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function newManifest(array $evidence, array $metadata): array
    {
        return [
            'manifest_version' => 1,
            'phase' => 'transactional_authoritative_transfer',
            'generated_at' => gmdate(DATE_ATOM),
            'transfer_passed' => false,
            'abort_required' => true,
            'cutover_performed' => false,
            'runtime_change_permitted' => false,
            'source_mutation_permitted' => false,
            'target_application_traffic_permitted' => false,
            'resumption_policy' => 'discard_and_recreate_unaccepted_target',
            'schema_status' => 'canonical_migrations_must_be_applied_before_transfer',
            'operational_tables' => [
                'migrations' => 'retained_from_canonical_laravel_migrations',
                'cache' => 'not_transferred_and_must_remain_empty',
                'cache_locks' => 'not_transferred_and_must_remain_empty',
            ],
            'backup' => [
                'reference' => $evidence['backup_reference'] ?? null,
                'checksum_sha256' => $evidence['backup_checksum_sha256'] ?? null,
                'restoration_tested' => ($evidence['backup_is_restorable'] ?? null) === true,
            ],
            'gates' => [],
            'tables' => [],
            'normalization' => [],
            'sequence_status' => ['state' => 'not_started'],
            'metadata' => $this->redact($metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, array{passed: bool, reason: string}>
     */
    private function evaluateGates(PDO $source, PDO $target, array $evidence): array
    {
        $sourceDriver = $this->driver($source);
        $targetDriver = $this->driver($target);
        $sourceReadOnly = false;

        if ($sourceDriver === 'sqlite') {
            try {
                $sourceReadOnly = (int) $source->query('PRAGMA query_only')->fetchColumn() === 1;
            } catch (Throwable) {
                $sourceReadOnly = false;
            }
        }

        $checksumBefore = $evidence['source_checksum_before'] ?? null;
        $checksumAfter = $evidence['source_checksum_after_preparation'] ?? null;
        $checksumVerified = is_string($checksumBefore)
            && $checksumBefore !== ''
            && is_string($checksumAfter)
            && hash_equals($checksumBefore, $checksumAfter);

        return [
            'preflight_passed' => $this->booleanEvidenceGate($evidence, 'preflight_passed'),
            'source_preparation_passed' => $this->booleanEvidenceGate($evidence, 'source_preparation_passed'),
            'backup_is_restorable' => $this->booleanEvidenceGate($evidence, 'backup_is_restorable'),
            'canonical_schema_created' => $this->booleanEvidenceGate($evidence, 'canonical_schema_created'),
            'canonical_migrations_verified' => $this->booleanEvidenceGate($evidence, 'canonical_migrations_verified'),
            'target_is_unaccepted' => $this->booleanEvidenceGate($evidence, 'target_is_unaccepted'),
            'target_application_traffic_disabled' => $this->booleanEvidenceGate($evidence, 'target_application_traffic_disabled'),
            'source_checksum_preserved_before_transfer' => $this->gate(
                $checksumVerified,
                $checksumVerified ? 'verified' : 'checksum_missing_or_changed'
            ),
            'source_connection_is_read_only_sqlite' => $this->gate(
                $sourceDriver === 'sqlite' && $sourceReadOnly,
                $sourceDriver === 'sqlite' && $sourceReadOnly ? 'verified' : 'source_must_be_query_only_sqlite'
            ),
            'target_connection_is_postgresql' => $this->gate(
                $targetDriver === 'pgsql',
                $targetDriver === 'pgsql' ? 'verified' : 'target_must_be_postgresql'
            ),
            'session_decision_valid' => $this->sessionGate($evidence),
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{passed: bool, reason: string}
     */
    private function sessionGate(array $evidence): array
    {
        $mode = $evidence['session_mode'] ?? null;

        if ($mode === 'transfer') {
            $passed = ($evidence['session_continuity_approved'] ?? null) === true
                && ($evidence['app_key_retained'] ?? null) === true;

            return $this->gate($passed, $passed ? 'session_transfer_approved' : 'session_transfer_not_approved');
        }

        if ($mode === 'forced_logout') {
            $passed = ($evidence['forced_logout_approved'] ?? null) === true;

            return $this->gate($passed, $passed ? 'forced_logout_approved' : 'forced_logout_not_approved');
        }

        return $this->gate(false, 'session_decision_missing_or_invalid');
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<int, string>
     */
    private function selectedTables(array $evidence): array
    {
        $policy = $this->policy();

        return array_values(array_filter(
            self::TRANSFER_ORDER,
            static fn (string $table): bool => isset($policy[$table])
                && ($table !== 'sessions' || ($evidence['session_mode'] ?? null) === 'transfer')
        ));
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function assertSchemaAvailable(PDO $source, PDO $target, array $tables): void
    {
        $policy = $this->policy();

        foreach ($tables as $table) {
            if (! isset($policy[$table])) {
                throw new RuntimeException('Transfer policy is incomplete.');
            }

            $columns = implode(', ', array_map($this->quoteIdentifier(...), array_keys($policy[$table]['columns'])));
            $quotedTable = $this->quoteIdentifier($table);
            $source->query("SELECT {$columns} FROM {$quotedTable} WHERE 1 = 0");
            $target->query("SELECT {$columns} FROM {$quotedTable} WHERE 1 = 0");
        }

        foreach (['cache', 'cache_locks'] as $operationalTable) {
            $quotedTable = $this->quoteIdentifier($operationalTable);
            $count = $target->query("SELECT COUNT(*) FROM {$quotedTable}")->fetchColumn();

            if ((int) $count !== 0) {
                throw new RuntimeException('Operational target table is not empty.');
            }
        }

        $target->query('SELECT COUNT(*) FROM "migrations"')->fetchColumn();
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function assertTargetEmpty(PDO $target, array $tables): void
    {
        foreach ($tables as $table) {
            $count = $target->query('SELECT COUNT(*) FROM '.$this->quoteIdentifier($table))->fetchColumn();

            if ((int) $count !== 0) {
                throw new RuntimeException('The unaccepted target contains transfer rows.');
            }
        }
    }

    /**
     * @param  array<int|string, int|string|null>|null  $deferredPharmacyIds
     * @return array<string, mixed>
     */
    private function transferTable(
        PDO $source,
        PDO $target,
        string $table,
        ?array &$deferredPharmacyIds = null
    ): array {
        $definition = $this->policy()[$table];
        $primaryKey = $definition['primary_key'];
        $columns = array_keys($definition['columns']);
        $sourceColumns = array_map(
            function (string $column) use ($definition): string {
                $quoted = $this->quoteIdentifier($column);

                return ($definition['columns'][$column]['type'] ?? null) === 'decimal'
                    ? "CAST({$quoted} AS TEXT) AS {$quoted}"
                    : $quoted;
            },
            $columns
        );
        $quotedSourceColumns = implode(', ', $sourceColumns);
        $quotedTable = $this->quoteIdentifier($table);
        $select = $source->prepare(
            "SELECT {$quotedSourceColumns} FROM {$quotedTable} ORDER BY ".$this->quoteIdentifier($primaryKey).' LIMIT :limit OFFSET :offset'
        );
        $insert = $this->prepareInsert($target, $table, $columns);
        $hash = hash_init('sha256');
        $count = 0;
        $minimum = null;
        $maximum = null;
        $offset = 0;

        while (true) {
            $select->bindValue(':limit', $this->batchSize, PDO::PARAM_INT);
            $select->bindValue(':offset', $offset, PDO::PARAM_INT);
            $select->execute();
            $rows = $select->fetchAll(PDO::FETCH_ASSOC);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $key = $row[$primaryKey] ?? null;
                $normalized = $this->normalizeRow($table, $row, $key);
                $canonical = $this->canonicalRow($table, $normalized, $key);

                if ($table === 'users') {
                    $deferredPharmacyIds[$key] = $normalized['pharmacy_id'];
                    $normalized['pharmacy_id'] = null;
                }

                try {
                    $this->executeInsert($insert, $normalized);
                } catch (PDOException $exception) {
                    throw new MigrationTransferException('target_insert_rejected', $table, $key, null, $exception);
                }

                hash_update($hash, json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $minimum ??= $key;
                $maximum = $key;
                $count++;
            }

            $offset += count($rows);
        }

        return [
            'row_count' => $count,
            'primary_key_minimum' => $minimum,
            'primary_key_maximum' => $maximum,
            'canonical_sha256' => hash_final($hash),
        ];
    }

    /**
     * @param  array<int|string, int|string|null>  $deferredPharmacyIds
     */
    private function restoreUserPharmacyLinks(PDO $target, array $deferredPharmacyIds): void
    {
        $statement = $target->prepare('UPDATE "users" SET "pharmacy_id" = :pharmacy_id WHERE "id" = :id');

        foreach ($deferredPharmacyIds as $userId => $pharmacyId) {
            if ($pharmacyId === null) {
                continue;
            }

            try {
                $statement->bindValue(':pharmacy_id', $pharmacyId, PDO::PARAM_INT);
                $statement->bindValue(':id', $userId, PDO::PARAM_INT);
                $statement->execute();

                if ($statement->rowCount() !== 1) {
                    throw new MigrationTransferException('deferred_user_pharmacy_link_not_restored', 'users', $userId, 'pharmacy_id');
                }
            } catch (PDOException $exception) {
                throw new MigrationTransferException('deferred_user_pharmacy_link_rejected', 'users', $userId, 'pharmacy_id', $exception);
            }
        }
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, array<string, mixed>>
     */
    private function collectTargetEvidence(PDO $target, array $tables): array
    {
        $evidence = [];

        foreach ($tables as $table) {
            $definition = $this->policy()[$table];
            $primaryKey = $definition['primary_key'];
            $columns = array_keys($definition['columns']);
            $quotedColumns = implode(', ', array_map($this->quoteIdentifier(...), $columns));
            $query = $target->query(
                'SELECT '.$quotedColumns.' FROM '.$this->quoteIdentifier($table).' ORDER BY '.$this->quoteIdentifier($primaryKey)
            );
            $hash = hash_init('sha256');
            $count = 0;
            $minimum = null;
            $maximum = null;

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $key = $row[$primaryKey] ?? null;
                $normalized = $this->normalizeRow($table, $row, $key);
                $canonical = $this->canonicalRow($table, $normalized, $key);
                hash_update($hash, json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $minimum ??= $key;
                $maximum = $key;
                $count++;
            }

            $evidence[$table] = [
                'row_count' => $count,
                'primary_key_minimum' => $minimum,
                'primary_key_maximum' => $maximum,
                'canonical_sha256' => hash_final($hash),
            ];
        }

        return $evidence;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sourceEvidence
     * @param  array<string, array<string, mixed>>  $targetEvidence
     */
    private function assertEquivalent(array $sourceEvidence, array $targetEvidence): void
    {
        foreach ($sourceEvidence as $table => $source) {
            $target = $targetEvidence[$table] ?? null;

            if (! is_array($target)
                || $source['row_count'] !== $target['row_count']
                || $source['primary_key_minimum'] !== $target['primary_key_minimum']
                || $source['primary_key_maximum'] !== $target['primary_key_maximum']
                || ! hash_equals($source['canonical_sha256'], $target['canonical_sha256'])) {
                throw new MigrationTransferException('source_target_equivalence_failed', $table);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sourceEvidence
     * @param  array<string, array<string, mixed>>  $targetEvidence
     * @return array<string, array<string, mixed>>
     */
    private function combineEvidence(array $sourceEvidence, array $targetEvidence): array
    {
        $combined = [];

        foreach ($sourceEvidence as $table => $source) {
            $combined[$table] = [
                'source' => $source,
                'target' => $targetEvidence[$table],
                'equivalent' => true,
            ];
        }

        return $combined;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function prepareInsert(PDO $target, string $table, array $columns): \PDOStatement
    {
        $quotedColumns = implode(', ', array_map($this->quoteIdentifier(...), $columns));
        $placeholders = implode(', ', array_map(static fn (string $column): string => ':'.$column, $columns));

        return $target->prepare(
            'INSERT INTO '.$this->quoteIdentifier($table)." ({$quotedColumns}) VALUES ({$placeholders})"
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function executeInsert(\PDOStatement $statement, array $row): void
    {
        foreach ($row as $column => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue(':'.$column, $value, $type);
        }

        $statement->execute();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(string $table, array $row, int|string|null $primaryKey): array
    {
        $normalized = [];

        foreach ($this->policy()[$table]['columns'] as $column => $definition) {
            if (! array_key_exists($column, $row)) {
                throw new MigrationTransferException('source_column_missing', $table, $primaryKey, $column);
            }

            $normalized[$column] = $this->normalizer->normalize(
                $row[$column],
                $definition,
                $table,
                $primaryKey,
                $column
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array<string, mixed>>
     */
    private function canonicalRow(string $table, array $row, int|string|null $primaryKey): array
    {
        $canonical = [];

        foreach ($this->policy()[$table]['columns'] as $column => $definition) {
            $canonical[$column] = $this->normalizer->canonicalize(
                $row[$column],
                $definition,
                $table,
                $primaryKey,
                $column
            );
        }

        return $canonical;
    }

    /**
     * @return array<string, array{primary_key: string, columns: array<string, array{type: string, nullable: bool, precision?: int, scale?: int}>}>
     */
    private function policy(): array
    {
        return $this->policy ?? self::medFindTransferPolicy();
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new RuntimeException('Unsafe schema identifier in transfer policy.');
        }

        return '"'.$identifier.'"';
    }

    private function driver(PDO $pdo): ?string
    {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            return is_string($driver) ? $driver : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{passed: bool, reason: string}
     */
    private function booleanEvidenceGate(array $evidence, string $key): array
    {
        $passed = array_key_exists($key, $evidence) && $evidence[$key] === true;

        return $this->gate($passed, $passed ? 'verified' : 'evidence_missing_or_failed');
    }

    /**
     * @param  array<string, array{passed: bool, reason: string}>  $gates
     */
    private function hasFailedGate(array $gates): bool
    {
        foreach ($gates as $gate) {
            if ($gate['passed'] !== true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{passed: bool, reason: string}
     */
    private function gate(bool $passed, string $reason): array
    {
        return ['passed' => $passed, 'reason' => $reason];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function finalize(array $manifest): array
    {
        if ($manifest['transfer_passed'] !== true) {
            $manifest['abort_required'] = true;
        }

        return $this->redact($manifest);
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
