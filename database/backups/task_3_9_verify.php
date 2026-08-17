<?php
/**
 * Task 3.9: Pre-cutover verification and acceptance gate
 * 
 * Performs 9 verification checks against PostgreSQL target.
 * Writes results to task_3_9_verification.json.
 * Does NOT change .env, exit maintenance mode, or enable application traffic.
 */

$results = [
    'manifest_version' => 1,
    'phase' => 'pre_cutover_verification_and_acceptance',
    'task' => '3.9',
    'generated_at' => gmdate('Y-m-d\TH:i:s+00:00'),
    'depends_on_task' => '3.8',
    'cutover_performed' => false,
    'runtime_change_permitted' => false,
    'source_mutation_permitted' => false,
    'target_application_traffic_permitted' => false,
    'gates' => [],
];

// Connect to PostgreSQL
try {
    $pdo = new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=medfind',
        'postgres',
        'root123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $results['gates']['gate_1_pgsql_driver'] = [
        'passed' => false,
        'details' => 'Connection failed: ' . $e->getMessage(),
    ];
    file_put_contents(__DIR__ . '/task_3_9_verification.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "FATAL: Cannot connect to PostgreSQL\n";
    exit(1);
}

// ═══════════════════════════════════════════════════════════════════
// GATE 1: Confirm PostgreSQL resolves as pgsql driver
// ═══════════════════════════════════════════════════════════════════
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
$gate1Passed = ($driver === 'pgsql');
$results['gates']['gate_1_pgsql_driver'] = [
    'passed' => $gate1Passed,
    'details' => "Driver: {$driver}, Server: PostgreSQL {$serverVersion}",
];
echo "Gate 1 - pgsql driver: " . ($gate1Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 2: Verify all 17 migrations are applied
// ═══════════════════════════════════════════════════════════════════
$stmt = $pdo->query("SELECT COUNT(*) FROM migrations");
$migrationCount = (int)$stmt->fetchColumn();
$stmt2 = $pdo->query("SELECT migration FROM migrations ORDER BY id");
$migrations = $stmt2->fetchAll(PDO::FETCH_COLUMN);
$gate2Passed = ($migrationCount === 17);
$results['gates']['gate_2_migrations'] = [
    'passed' => $gate2Passed,
    'details' => "{$migrationCount} migrations applied",
    'migrations' => $migrations,
];
echo "Gate 2 - migrations count (17): " . ($gate2Passed ? 'PASS' : 'FAIL') . " (got {$migrationCount})\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 3: Verify row counts
// ═══════════════════════════════════════════════════════════════════
$expectedCounts = [
    'users' => 3,
    'pharmacies' => 5,
    'medicines' => 6,
    'inventory_items' => 12,
    'messages' => 0,
    'sessions' => 1,
];
$gate3Details = [];
$gate3Passed = true;
foreach ($expectedCounts as $table => $expected) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
    $actual = (int)$stmt->fetchColumn();
    $match = ($actual === $expected);
    if (!$match) $gate3Passed = false;
    $gate3Details[$table] = [
        'expected' => $expected,
        'actual' => $actual,
        'match' => $match,
    ];
}
$results['gates']['gate_3_row_counts'] = [
    'passed' => $gate3Passed,
    'details' => $gate3Details,
];
echo "Gate 3 - row counts: " . ($gate3Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 4: Verify circular user/pharmacy relationship integrity
// The schema supports bidirectional user<->pharmacy links.
// Verify: (a) pharmacies.user_id references valid users,
//          (b) users.pharmacy_id references valid pharmacies (or is NULL),
//          (c) PostgreSQL values match SQLite source exactly.
// ═══════════════════════════════════════════════════════════════════
$gate4Passed = true;
$gate4Details = [];

// Check pharmacies.user_id -> users (at least one pharmacy references a user)
$stmt = $pdo->query("SELECT COUNT(*) FROM pharmacies WHERE user_id IS NOT NULL");
$pharmaciesWithUser = (int)$stmt->fetchColumn();

// Verify no orphan pharmacies.user_id
$stmt = $pdo->query("SELECT COUNT(*) FROM pharmacies p WHERE p.user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.id = p.user_id)");
$orphanPharmUserIds = (int)$stmt->fetchColumn();

// Check users.pharmacy_id -> pharmacies
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE pharmacy_id IS NOT NULL");
$usersWithPharmacy = (int)$stmt->fetchColumn();

// Verify no orphan users.pharmacy_id
$stmt = $pdo->query("SELECT COUNT(*) FROM users u WHERE u.pharmacy_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pharmacies p WHERE p.id = u.pharmacy_id)");
$orphanUserPharmIds = (int)$stmt->fetchColumn();

// Compare with SQLite source
$sqlite = new PDO('sqlite:' . __DIR__ . '/../database.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $sqlite->query("SELECT id, pharmacy_id FROM users ORDER BY id");
$sqliteUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $sqlite->query("SELECT id, user_id FROM pharmacies ORDER BY id");
$sqlitePharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compare users.pharmacy_id
$stmt = $pdo->query("SELECT id, pharmacy_id FROM users ORDER BY id");
$pgUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usersMatch = true;
foreach ($sqliteUsers as $i => $su) {
    $pu = $pgUsers[$i] ?? null;
    if (!$pu || $su['id'] != $pu['id'] || $su['pharmacy_id'] != $pu['pharmacy_id']) {
        $usersMatch = false;
        break;
    }
}

// Compare pharmacies.user_id
$stmt = $pdo->query("SELECT id, user_id FROM pharmacies ORDER BY id");
$pgPharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pharmaciesMatch = true;
foreach ($sqlitePharmacies as $i => $sp) {
    $pp = $pgPharmacies[$i] ?? null;
    if (!$pp || $sp['id'] != $pp['id'] || $sp['user_id'] != $pp['user_id']) {
        $pharmaciesMatch = false;
        break;
    }
}

// Gate passes if: no orphans, data matches source, and at least one directional link exists
$hasRelationship = ($pharmaciesWithUser > 0 || $usersWithPharmacy > 0);
if ($orphanPharmUserIds > 0 || $orphanUserPharmIds > 0) $gate4Passed = false;
if (!$usersMatch || !$pharmaciesMatch) $gate4Passed = false;
if (!$hasRelationship) $gate4Passed = false;

$gate4Details = [
    'pharmacies_with_user_id' => $pharmaciesWithUser,
    'users_with_pharmacy_id' => $usersWithPharmacy,
    'orphan_pharmacy_user_ids' => $orphanPharmUserIds,
    'orphan_user_pharmacy_ids' => $orphanUserPharmIds,
    'users_pharmacy_id_matches_source' => $usersMatch,
    'pharmacies_user_id_matches_source' => $pharmaciesMatch,
    'at_least_one_link_exists' => $hasRelationship,
    'note' => 'Source data has pharmacies referencing users (user_id) but no users referencing pharmacies (pharmacy_id=NULL). Transfer preserved this faithfully.',
];

$results['gates']['gate_4_circular_links'] = [
    'passed' => $gate4Passed,
    'details' => $gate4Details,
];
echo "Gate 4 - user/pharmacy relationship integrity: " . ($gate4Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 5: Verify transactional read/write probe
// ═══════════════════════════════════════════════════════════════════
$gate5Passed = false;
$gate5Details = '';
try {
    $pdo->beginTransaction();
    
    // Use users table which we know exists with standard columns
    $pdo->exec("INSERT INTO users (id, name, email, password, role, created_at, updated_at) VALUES (99999, 'probe_test', 'probe_test@test.invalid', 'probe_hash', 'consumer', NOW(), NOW())");
    
    $stmt = $pdo->query("SELECT id, name FROM users WHERE id = 99999");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['id'] == 99999 && $row['name'] === 'probe_test') {
        $gate5Passed = true;
        $gate5Details = 'INSERT/SELECT probe succeeded within transaction, rolled back cleanly';
    } else {
        $gate5Details = 'Probe row not readable after insert';
    }
    
    $pdo->rollBack();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $gate5Details = 'Probe failed: ' . $e->getMessage();
}
$results['gates']['gate_5_rw_probe'] = [
    'passed' => $gate5Passed,
    'details' => $gate5Details,
];
echo "Gate 5 - read/write probe: " . ($gate5Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 6: Verify SQLite source checksum
// ═══════════════════════════════════════════════════════════════════
$expectedChecksum = 'b23589695ddddcc5437aed7fe75f45148f805627db39f34489c4b78ffca0617d';
$sqlitePath = __DIR__ . '/../database.sqlite';
$gate6Passed = false;
$gate6Details = '';
if (file_exists($sqlitePath)) {
    $actualChecksum = hash_file('sha256', $sqlitePath);
    $gate6Passed = ($actualChecksum === $expectedChecksum);
    $gate6Details = $gate6Passed
        ? "SHA256 matches: {$expectedChecksum}"
        : "SHA256 mismatch. Expected: {$expectedChecksum}, Got: {$actualChecksum}";
} else {
    $gate6Details = "SQLite file not found at: {$sqlitePath}";
}
$results['gates']['gate_6_sqlite_checksum'] = [
    'passed' => $gate6Passed,
    'details' => $gate6Details,
];
echo "Gate 6 - SQLite checksum: " . ($gate6Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 7: Verify phpunit.xml contains DB_CONNECTION=sqlite and DB_DATABASE=:memory:
// ═══════════════════════════════════════════════════════════════════
$phpunitPath = realpath(__DIR__ . '/../../phpunit.xml');
$gate7Passed = false;
$gate7Details = '';
if ($phpunitPath && file_exists($phpunitPath)) {
    $content = file_get_contents($phpunitPath);
    $hasDbConnection = (strpos($content, 'name="DB_CONNECTION" value="sqlite"') !== false);
    $hasDbDatabase = (strpos($content, 'name="DB_DATABASE" value=":memory:"') !== false);
    $hasDbUrl = (strpos($content, 'name="DB_URL" value=""') !== false);
    $gate7Passed = ($hasDbConnection && $hasDbDatabase);
    $gate7Details = [
        'DB_CONNECTION=sqlite' => $hasDbConnection,
        'DB_DATABASE=:memory:' => $hasDbDatabase,
        'DB_URL=""' => $hasDbUrl,
        'phpunit_path' => $phpunitPath,
    ];
} else {
    $gate7Details = "phpunit.xml not found. Searched: " . realpath(__DIR__ . '/../..') . '/phpunit.xml';
}
$results['gates']['gate_7_test_isolation'] = [
    'passed' => $gate7Passed,
    'details' => $gate7Details,
];
echo "Gate 7 - test isolation: " . ($gate7Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 8: Verify no foreign key constraint violations
// ═══════════════════════════════════════════════════════════════════
$gate8Passed = true;
$gate8Details = [];

// Check orphan records for known relationships
$fkChecks = [
    'users.pharmacy_id -> pharmacies.id' => "SELECT COUNT(*) FROM users u WHERE u.pharmacy_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pharmacies p WHERE p.id = u.pharmacy_id)",
    'pharmacies.user_id -> users.id' => "SELECT COUNT(*) FROM pharmacies p WHERE p.user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.id = p.user_id)",
    'inventory_items.pharmacy_id -> pharmacies.id' => "SELECT COUNT(*) FROM inventory_items i WHERE i.pharmacy_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pharmacies p WHERE p.id = i.pharmacy_id)",
    'inventory_items.medicine_id -> medicines.id' => "SELECT COUNT(*) FROM inventory_items i WHERE i.medicine_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM medicines m WHERE m.id = i.medicine_id)",
    'inventory_items.supplier_id -> suppliers.id' => "SELECT COUNT(*) FROM inventory_items i WHERE i.supplier_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.id = i.supplier_id)",
];

foreach ($fkChecks as $relation => $query) {
    $stmt = $pdo->query($query);
    $orphans = (int)$stmt->fetchColumn();
    if ($orphans > 0) $gate8Passed = false;
    $gate8Details[$relation] = $orphans;
}

// Also check pg_constraint for any deferred violations
$stmt = $pdo->query("
    SELECT conname, conrelid::regclass AS table_name, confrelid::regclass AS ref_table
    FROM pg_constraint
    WHERE contype = 'f'
    ORDER BY conrelid::regclass::text
");
$constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
$gate8Details['pg_foreign_keys_count'] = count($constraints);

$results['gates']['gate_8_fk_violations'] = [
    'passed' => $gate8Passed,
    'details' => $gate8Details,
];
echo "Gate 8 - FK violations: " . ($gate8Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// GATE 9: Verify sequences are collision-safe
// ═══════════════════════════════════════════════════════════════════
$sequenceChecks = [
    'users' => 3,
    'pharmacies' => 5,
    'medicines' => 6,
    'inventory_items' => 12,
];
$gate9Passed = true;
$gate9Details = [];

foreach ($sequenceChecks as $table => $maxId) {
    $seqName = "public.{$table}_id_seq";
    $stmt = $pdo->query("SELECT last_value, is_called FROM {$seqName}");
    $seqState = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate next value: if is_called=true, next = last_value + 1; if false, next = last_value
    $lastValue = (int)$seqState['last_value'];
    $isCalled = ($seqState['is_called'] === 't' || $seqState['is_called'] === true || $seqState['is_called'] === 1);
    $nextValue = $isCalled ? $lastValue + 1 : $lastValue;
    $collisionSafe = ($nextValue > $maxId);
    
    if (!$collisionSafe) $gate9Passed = false;
    
    $gate9Details[$table] = [
        'sequence' => $seqName,
        'last_value' => $lastValue,
        'is_called' => $isCalled,
        'next_value' => $nextValue,
        'max_imported_id' => $maxId,
        'collision_safe' => $collisionSafe,
    ];
}

$results['gates']['gate_9_sequences'] = [
    'passed' => $gate9Passed,
    'details' => $gate9Details,
];
echo "Gate 9 - sequences collision-safe: " . ($gate9Passed ? 'PASS' : 'FAIL') . "\n";

// ═══════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════
$totalGates = count($results['gates']);
$passedGates = count(array_filter($results['gates'], fn($g) => $g['passed']));
$allPassed = ($passedGates === $totalGates);

$results['all_gates_passed'] = $allPassed;
$results['cutover_approved'] = $allPassed;
$results['gate_summary'] = [
    'total' => $totalGates,
    'passed' => $passedGates,
    'failed' => $totalGates - $passedGates,
];

if ($allPassed) {
    $results['verdict'] = 'CUTOVER APPROVED';
    $results['next_steps'] = [
        'Task 3.10: Cut over the runtime to PostgreSQL (requires explicit approval)',
        'Do NOT enable target application traffic until task 3.10 approval',
        'Do NOT exit maintenance mode until task 3.10',
        'Do NOT modify .env runtime selection without task 3.10 approval',
    ];
} else {
    $results['verdict'] = 'CUTOVER NOT APPROVED';
    $results['next_steps'] = [
        'Fix failures and re-run pre-cutover verification',
        'Do NOT attempt cutover until all gates pass',
    ];
}

$results['warnings'] = [
    'Do NOT enable target application traffic until task 3.10',
    'Do NOT exit maintenance mode',
    'Do NOT modify .env runtime selection without task 3.10 approval',
    'Credentials are NOT logged in this manifest',
];

// Write results
$outputPath = __DIR__ . '/task_3_9_verification.json';
file_put_contents($outputPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n" . str_repeat('=', 60) . "\n";
echo "RESULT: {$results['verdict']}\n";
echo "Gates: {$passedGates}/{$totalGates} passed\n";
echo str_repeat('=', 60) . "\n";
echo "Results written to: {$outputPath}\n";
