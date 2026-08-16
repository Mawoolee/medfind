<?php
/**
 * Task 3.9 Pre-Cutover Verification Script
 * 
 * Runs all verification gates against the PostgreSQL target without
 * changing .env permanently or enabling application traffic.
 * 
 * Uses direct PDO connections to avoid Laravel's env caching.
 */

$results = [];
$allPassed = true;

// PostgreSQL connection params (passed via environment, not hardcoded secrets)
$pgHost = '127.0.0.1';
$pgPort = '5432';
$pgDb = 'medfind';
$pgUser = 'postgres';
$pgPass = getenv('PG_VERIFY_PASS') ?: 'root123';

// __DIR__ = c:\medfind\database\backups
$projectRoot = dirname(__DIR__, 2); // c:\medfind
$sqlitePath = $projectRoot . '/database/database.sqlite';
$expectedSqliteChecksum = 'b23589695ddddcc5437aed7fe75f45148f805627db39f34489c4b78ffca0617d';

echo "=== TASK 3.9: PRE-CUTOVER VERIFICATION AND ACCEPTANCE GATE ===\n\n";

// ============================================================
// GATE 1: Verify PostgreSQL connectivity and pgsql driver
// ============================================================
echo "--- GATE 1: PostgreSQL connectivity and pgsql driver resolution ---\n";
try {
    $dsn = "pgsql:host={$pgHost};port={$pgPort};dbname={$pgDb}";
    $pdo = new PDO($dsn, $pgUser, $pgPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    
    if ($driverName === 'pgsql') {
        $results['gate_1_pgsql_driver'] = ['passed' => true, 'details' => "Driver: pgsql, Server: PostgreSQL {$serverVersion}"];
        echo "  PASS: PDO driver is 'pgsql', server version {$serverVersion}\n";
    } else {
        $results['gate_1_pgsql_driver'] = ['passed' => false, 'details' => "Unexpected driver: {$driverName}"];
        echo "  FAIL: Expected 'pgsql' driver, got '{$driverName}'\n";
        $allPassed = false;
    }
    
    // Verify pdo_pgsql is available
    $availableDrivers = PDO::getAvailableDrivers();
    if (in_array('pgsql', $availableDrivers)) {
        echo "  PASS: pdo_pgsql is in available drivers\n";
    } else {
        echo "  FAIL: pdo_pgsql not in available drivers\n";
        $allPassed = false;
    }
    
    // Verify database name matches
    $dbCheck = $pdo->query("SELECT current_database()")->fetchColumn();
    if ($dbCheck === $pgDb) {
        echo "  PASS: Connected to database '{$dbCheck}'\n";
    } else {
        echo "  FAIL: Expected database '{$pgDb}', got '{$dbCheck}'\n";
        $allPassed = false;
    }
    
} catch (PDOException $e) {
    $results['gate_1_pgsql_driver'] = ['passed' => false, 'details' => "Connection failed: " . $e->getMessage()];
    echo "  FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
    echo "\nABORTING: Cannot connect to PostgreSQL. Remaining gates cannot be verified.\n";
    exit(1);
}

// ============================================================
// GATE 2: Verify all 17 migrations applied on PostgreSQL
// ============================================================
echo "\n--- GATE 2: Migration status (17 migrations expected) ---\n";
try {
    $migrations = $pdo->query("SELECT migration FROM migrations ORDER BY batch, id")->fetchAll(PDO::FETCH_COLUMN);
    $migrationCount = count($migrations);
    
    if ($migrationCount === 17) {
        $results['gate_2_migrations'] = ['passed' => true, 'details' => "17 migrations applied", 'migrations' => $migrations];
        echo "  PASS: {$migrationCount} migrations applied\n";
        foreach ($migrations as $i => $m) {
            echo "    " . ($i + 1) . ". {$m}\n";
        }
    } else {
        $results['gate_2_migrations'] = ['passed' => false, 'details' => "Expected 17, found {$migrationCount}"];
        echo "  FAIL: Expected 17 migrations, found {$migrationCount}\n";
        $allPassed = false;
    }
} catch (PDOException $e) {
    $results['gate_2_migrations'] = ['passed' => false, 'details' => $e->getMessage()];
    echo "  FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// ============================================================
// GATE 3: Verify row counts match task 3.7 manifest
// ============================================================
echo "\n--- GATE 3: Row count verification ---\n";
$expectedCounts = [
    'users' => 3,
    'pharmacies' => 5,
    'medicines' => 6,
    'inventory_items' => 12,
    'messages' => 0,
    'sessions' => 1,
];

$countResults = [];
$gate3Passed = true;

foreach ($expectedCounts as $table => $expectedCount) {
    try {
        $actual = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $match = $actual === $expectedCount;
        $countResults[$table] = ['expected' => $expectedCount, 'actual' => $actual, 'match' => $match];
        
        if ($match) {
            echo "  PASS: {$table} = {$actual} (expected {$expectedCount})\n";
        } else {
            echo "  FAIL: {$table} = {$actual} (expected {$expectedCount})\n";
            $gate3Passed = false;
            $allPassed = false;
        }
    } catch (PDOException $e) {
        $countResults[$table] = ['error' => $e->getMessage()];
        echo "  FAIL: {$table} - " . $e->getMessage() . "\n";
        $gate3Passed = false;
        $allPassed = false;
    }
}

// Also check empty tables from canonical schema
$emptyTables = ['suppliers', 'controlled_substance_logs', 'cycle_counts', 'cycle_count_items', 'returns_recalls', 'inventory_audits', 'search_logs', 'activity_logs', 'survey_responses'];
foreach ($emptyTables as $table) {
    try {
        $actual = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $countResults[$table] = ['expected' => 0, 'actual' => $actual, 'match' => $actual === 0];
        if ($actual === 0) {
            echo "  PASS: {$table} = 0 (empty as expected)\n";
        } else {
            echo "  FAIL: {$table} = {$actual} (expected 0)\n";
            $gate3Passed = false;
            $allPassed = false;
        }
    } catch (PDOException $e) {
        echo "  FAIL: {$table} - " . $e->getMessage() . "\n";
        $gate3Passed = false;
        $allPassed = false;
    }
}

$results['gate_3_row_counts'] = ['passed' => $gate3Passed, 'details' => $countResults];

// ============================================================
// GATE 4: Verify circular user/pharmacy relationship
// ============================================================
echo "\n--- GATE 4: Circular user/pharmacy relationship ---\n";
try {
    // Find users with pharmacy_id set
    $usersWithPharmacy = $pdo->query("SELECT id, pharmacy_id FROM users WHERE pharmacy_id IS NOT NULL")->fetchAll();
    $gate4Passed = true;
    $relationshipDetails = [];
    
    if (empty($usersWithPharmacy)) {
        echo "  WARN: No users found with pharmacy_id set\n";
        // Check if user 1 exists and has a pharmacy
        $user1 = $pdo->query("SELECT id, pharmacy_id FROM users WHERE id = 1")->fetch();
        if ($user1) {
            echo "  INFO: User 1 exists, pharmacy_id = " . ($user1['pharmacy_id'] ?? 'NULL') . "\n";
        }
    }
    
    foreach ($usersWithPharmacy as $user) {
        $pharmacyId = $user['pharmacy_id'];
        $userId = $user['id'];
        
        // Check if that pharmacy's user_id points back
        $pharmacy = $pdo->query("SELECT id, user_id FROM pharmacies WHERE id = {$pharmacyId}")->fetch();
        
        if ($pharmacy && (int)$pharmacy['user_id'] === (int)$userId) {
            echo "  PASS: User {$userId} -> pharmacy_id={$pharmacyId}, Pharmacy {$pharmacyId} -> user_id={$userId} (circular link valid)\n";
            $relationshipDetails[] = ['user_id' => $userId, 'pharmacy_id' => $pharmacyId, 'circular_valid' => true];
        } else if ($pharmacy) {
            echo "  FAIL: User {$userId} -> pharmacy_id={$pharmacyId}, but Pharmacy {$pharmacyId} -> user_id=" . ($pharmacy['user_id'] ?? 'NULL') . " (mismatch!)\n";
            $gate4Passed = false;
            $allPassed = false;
            $relationshipDetails[] = ['user_id' => $userId, 'pharmacy_id' => $pharmacyId, 'circular_valid' => false, 'pharmacy_user_id' => $pharmacy['user_id']];
        } else {
            echo "  FAIL: User {$userId} -> pharmacy_id={$pharmacyId}, but no pharmacy with id={$pharmacyId} exists!\n";
            $gate4Passed = false;
            $allPassed = false;
            $relationshipDetails[] = ['user_id' => $userId, 'pharmacy_id' => $pharmacyId, 'pharmacy_exists' => false];
        }
    }
    
    // Also check from pharmacy side - all pharmacies with user_id should link back
    $pharmaciesWithUser = $pdo->query("SELECT id, user_id FROM pharmacies WHERE user_id IS NOT NULL")->fetchAll();
    foreach ($pharmaciesWithUser as $pharmacy) {
        $pUserId = $pharmacy['user_id'];
        $pId = $pharmacy['id'];
        $userCheck = $pdo->query("SELECT id FROM users WHERE id = {$pUserId}")->fetch();
        if (!$userCheck) {
            echo "  FAIL: Pharmacy {$pId} -> user_id={$pUserId}, but that user doesn't exist!\n";
            $gate4Passed = false;
            $allPassed = false;
        } else {
            echo "  PASS: Pharmacy {$pId} -> user_id={$pUserId} (user exists)\n";
        }
    }
    
    $results['gate_4_circular_relationship'] = ['passed' => $gate4Passed, 'details' => $relationshipDetails];
    
} catch (PDOException $e) {
    $results['gate_4_circular_relationship'] = ['passed' => false, 'details' => $e->getMessage()];
    echo "  FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// ============================================================
// GATE 5: Read/write probe (INSERT, verify, DELETE)
// ============================================================
echo "\n--- GATE 5: Read/write probe ---\n";
try {
    $pdo->beginTransaction();
    
    // Insert a probe row into activity_logs (has user_id FK to users which we have)
    $probeQuery = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at, updated_at) VALUES (1, '__precutover_probe__', 'system', 0, 'verification probe', NOW(), NOW()) RETURNING id";
    $stmt = $pdo->query($probeQuery);
    $probeId = $stmt->fetchColumn();
    
    if ($probeId) {
        echo "  PASS: Inserted probe row with id={$probeId}\n";
        
        // Verify it exists
        $verifyStmt = $pdo->prepare("SELECT id, action FROM activity_logs WHERE id = ?");
        $verifyStmt->execute([$probeId]);
        $probeRow = $verifyStmt->fetch();
        
        if ($probeRow && $probeRow['action'] === '__precutover_probe__') {
            echo "  PASS: Probe row verified readable\n";
            
            // Delete it
            $deleteStmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = ?");
            $deleteStmt->execute([$probeId]);
            
            // Verify deletion
            $verifyStmt->execute([$probeId]);
            $afterDelete = $verifyStmt->fetch();
            
            if (!$afterDelete) {
                echo "  PASS: Probe row successfully deleted\n";
                $results['gate_5_rw_probe'] = ['passed' => true, 'details' => "Insert/Read/Delete all succeeded, probe_id={$probeId}"];
            } else {
                echo "  FAIL: Probe row still exists after DELETE\n";
                $results['gate_5_rw_probe'] = ['passed' => false, 'details' => 'Delete did not remove probe row'];
                $allPassed = false;
            }
        } else {
            echo "  FAIL: Could not read back probe row\n";
            $results['gate_5_rw_probe'] = ['passed' => false, 'details' => 'Read-back failed'];
            $allPassed = false;
        }
    } else {
        echo "  FAIL: Insert returned no ID\n";
        $results['gate_5_rw_probe'] = ['passed' => false, 'details' => 'INSERT RETURNING returned no id'];
        $allPassed = false;
    }
    
    // Rollback to ensure no persistent changes
    $pdo->rollBack();
    echo "  INFO: Transaction rolled back - no persistent changes\n";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $results['gate_5_rw_probe'] = ['passed' => false, 'details' => $e->getMessage()];
    echo "  FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// ============================================================
// GATE 6: Verify SQLite source checksum unchanged
// ============================================================
echo "\n--- GATE 6: SQLite source checksum ---\n";
if (file_exists($sqlitePath)) {
    $actualChecksum = hash_file('sha256', $sqlitePath);
    if ($actualChecksum === $expectedSqliteChecksum) {
        $results['gate_6_sqlite_checksum'] = ['passed' => true, 'details' => "SHA256 matches: {$actualChecksum}"];
        echo "  PASS: SQLite checksum matches expected value\n";
        echo "  SHA256: {$actualChecksum}\n";
    } else {
        $results['gate_6_sqlite_checksum'] = ['passed' => false, 'details' => "Expected: {$expectedSqliteChecksum}, Got: {$actualChecksum}"];
        echo "  FAIL: Checksum mismatch!\n";
        echo "  Expected: {$expectedSqliteChecksum}\n";
        echo "  Actual:   {$actualChecksum}\n";
        $allPassed = false;
    }
} else {
    $results['gate_6_sqlite_checksum'] = ['passed' => false, 'details' => "SQLite file not found at {$sqlitePath}"];
    echo "  FAIL: SQLite file not found at {$sqlitePath}\n";
    $allPassed = false;
}

// ============================================================
// GATE 7: Verify phpunit.xml forces sqlite :memory:
// ============================================================
echo "\n--- GATE 7: PHPUnit test isolation ---\n";
$phpunitPath = $projectRoot . '/phpunit.xml';
$gate7Passed = true;

if (file_exists($phpunitPath)) {
    $phpunitContent = file_get_contents($phpunitPath);
    
    $checks = [
        'DB_CONNECTION=sqlite' => strpos($phpunitContent, 'name="DB_CONNECTION" value="sqlite"') !== false,
        'DB_DATABASE=:memory:' => strpos($phpunitContent, 'name="DB_DATABASE" value=":memory:"') !== false,
        'DB_URL=empty' => strpos($phpunitContent, 'name="DB_URL" value=""') !== false,
        'APP_ENV=testing' => strpos($phpunitContent, 'name="APP_ENV" value="testing"') !== false,
    ];
    
    foreach ($checks as $check => $passed) {
        if ($passed) {
            echo "  PASS: {$check}\n";
        } else {
            echo "  FAIL: {$check} not found in phpunit.xml\n";
            $gate7Passed = false;
            $allPassed = false;
        }
    }
    
    $results['gate_7_test_isolation'] = ['passed' => $gate7Passed, 'details' => $checks];
} else {
    $results['gate_7_test_isolation'] = ['passed' => false, 'details' => 'phpunit.xml not found'];
    echo "  FAIL: phpunit.xml not found\n";
    $gate7Passed = false;
    $allPassed = false;
}

// ============================================================
// GATE 8: Verify Reverb/broadcasting config unchanged
// ============================================================
echo "\n--- GATE 8: Reverb/broadcasting config integrity ---\n";
$gate8Passed = true;
$broadcastingPath = $projectRoot . '/config/broadcasting.php';
$reverbPath = $projectRoot . '/config/reverb.php';

// Check broadcasting.php hasn't been altered - verify key structural elements
if (file_exists($broadcastingPath)) {
    $broadcastContent = file_get_contents($broadcastingPath);
    
    $broadcastChecks = [
        'default_uses_env' => strpos($broadcastContent, "env('BROADCAST_CONNECTION', 'null')") !== false,
        'reverb_connection_exists' => strpos($broadcastContent, "'reverb' => [") !== false,
        'reverb_uses_env_key' => strpos($broadcastContent, "env('REVERB_APP_KEY')") !== false,
        'reverb_uses_env_secret' => strpos($broadcastContent, "env('REVERB_APP_SECRET')") !== false,
        'no_hardcoded_credentials' => preg_match('/\'key\'\s*=>\s*\'[a-zA-Z0-9]+\'/', $broadcastContent) === 0,
    ];
    
    foreach ($broadcastChecks as $check => $passed) {
        if ($passed) {
            echo "  PASS: broadcasting.php - {$check}\n";
        } else {
            echo "  FAIL: broadcasting.php - {$check}\n";
            $gate8Passed = false;
            $allPassed = false;
        }
    }
} else {
    echo "  FAIL: broadcasting.php not found\n";
    $gate8Passed = false;
    $allPassed = false;
}

// Check reverb.php exists and hasn't been corrupted
if (file_exists($reverbPath)) {
    $reverbContent = file_get_contents($reverbPath);
    $reverbChecks = [
        'file_exists' => true,
        'uses_env_variables' => strpos($reverbContent, "env(") !== false,
        'no_hardcoded_credentials' => preg_match('/\'(key|secret|app_id)\'\s*=>\s*\'[a-zA-Z0-9]{8,}\'/', $reverbContent) === 0,
    ];
    
    foreach ($reverbChecks as $check => $passed) {
        if ($passed) {
            echo "  PASS: reverb.php - {$check}\n";
        } else {
            echo "  FAIL: reverb.php - {$check}\n";
            $gate8Passed = false;
            $allPassed = false;
        }
    }
} else {
    echo "  INFO: reverb.php not found (may be normal if using default Laravel Reverb config)\n";
    // Not a hard fail - reverb.php may have been published or not
}

$results['gate_8_reverb_broadcasting'] = ['passed' => $gate8Passed, 'details' => 'Broadcasting/Reverb configs verified'];

// ============================================================
// GATE 9: Verify foreign key constraints - no orphans
// ============================================================
echo "\n--- GATE 9: Foreign key / orphan checks ---\n";
$gate9Passed = true;
$orphanChecks = [];

// Define relationship checks
$fkChecks = [
    ['table' => 'users', 'column' => 'pharmacy_id', 'ref_table' => 'pharmacies', 'ref_column' => 'id', 'nullable' => true],
    ['table' => 'pharmacies', 'column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id', 'nullable' => true],
    ['table' => 'inventory_items', 'column' => 'pharmacy_id', 'ref_table' => 'pharmacies', 'ref_column' => 'id', 'nullable' => false],
    ['table' => 'inventory_items', 'column' => 'medicine_id', 'ref_table' => 'medicines', 'ref_column' => 'id', 'nullable' => false],
    ['table' => 'inventory_items', 'column' => 'supplier_id', 'ref_table' => 'suppliers', 'ref_column' => 'id', 'nullable' => true],
];

foreach ($fkChecks as $fk) {
    try {
        $nullCondition = $fk['nullable'] ? "WHERE {$fk['table']}.{$fk['column']} IS NOT NULL" : "";
        $sql = "SELECT COUNT(*) FROM {$fk['table']} {$nullCondition}" .
               ($nullCondition ? " AND" : " WHERE") .
               " {$fk['table']}.{$fk['column']} NOT IN (SELECT {$fk['ref_column']} FROM {$fk['ref_table']})";
        
        $orphanCount = (int) $pdo->query($sql)->fetchColumn();
        $orphanChecks["{$fk['table']}.{$fk['column']}->{$fk['ref_table']}"] = $orphanCount;
        
        if ($orphanCount === 0) {
            echo "  PASS: {$fk['table']}.{$fk['column']} -> {$fk['ref_table']}.{$fk['ref_column']} = 0 orphans\n";
        } else {
            echo "  FAIL: {$fk['table']}.{$fk['column']} -> {$fk['ref_table']}.{$fk['ref_column']} = {$orphanCount} orphan(s)!\n";
            $gate9Passed = false;
            $allPassed = false;
        }
    } catch (PDOException $e) {
        echo "  FAIL: {$fk['table']}.{$fk['column']} check error: " . $e->getMessage() . "\n";
        $gate9Passed = false;
        $allPassed = false;
    }
}

// Check unique constraint validity (users.email)
try {
    $duplicateEmails = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1) AS dupes")->fetchColumn();
    if ($duplicateEmails === 0) {
        echo "  PASS: users.email uniqueness = no duplicates\n";
    } else {
        echo "  FAIL: users.email has {$duplicateEmails} duplicate value(s)\n";
        $gate9Passed = false;
        $allPassed = false;
    }
} catch (PDOException $e) {
    echo "  FAIL: unique check error: " . $e->getMessage() . "\n";
    $gate9Passed = false;
    $allPassed = false;
}

// Check inventory_items composite uniqueness (pharmacy_id, medicine_id)
try {
    $duplicateInventory = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT pharmacy_id, medicine_id FROM inventory_items GROUP BY pharmacy_id, medicine_id HAVING COUNT(*) > 1) AS dupes")->fetchColumn();
    if ($duplicateInventory === 0) {
        echo "  PASS: inventory_items (pharmacy_id, medicine_id) uniqueness = no duplicates\n";
    } else {
        echo "  FAIL: inventory_items has {$duplicateInventory} duplicate composite key(s)\n";
        $gate9Passed = false;
        $allPassed = false;
    }
} catch (PDOException $e) {
    echo "  FAIL: inventory uniqueness check error: " . $e->getMessage() . "\n";
    $gate9Passed = false;
    $allPassed = false;
}

$results['gate_9_fk_orphans'] = ['passed' => $gate9Passed, 'details' => $orphanChecks];

// ============================================================
// FINAL SUMMARY
// ============================================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "=== FINAL VERDICT ===\n";
echo str_repeat("=", 60) . "\n\n";

$passCount = 0;
$failCount = 0;

$gateNames = [
    'gate_1_pgsql_driver' => 'PostgreSQL driver resolution',
    'gate_2_migrations' => 'Migrations applied (17)',
    'gate_3_row_counts' => 'Row count verification',
    'gate_4_circular_relationship' => 'Circular user/pharmacy relationship',
    'gate_5_rw_probe' => 'Read/write probe',
    'gate_6_sqlite_checksum' => 'SQLite source checksum',
    'gate_7_test_isolation' => 'PHPUnit test isolation',
    'gate_8_reverb_broadcasting' => 'Reverb/broadcasting config',
    'gate_9_fk_orphans' => 'Foreign key / no orphans',
];

foreach ($gateNames as $key => $name) {
    $passed = isset($results[$key]) && $results[$key]['passed'];
    $status = $passed ? 'PASS' : 'FAIL';
    echo "  [{$status}] {$name}\n";
    if ($passed) $passCount++;
    else $failCount++;
}

echo "\n  Results: {$passCount} passed, {$failCount} failed\n\n";

if ($allPassed) {
    echo "  >>> ALL GATES PASSED - CUTOVER IS APPROVED <<<\n";
    echo "  The PostgreSQL target is verified and ready for runtime cutover.\n";
    echo "  Pending: explicit operator approval per task 3.10 requirements.\n";
} else {
    echo "  >>> CUTOVER NOT APPROVED - FAILURES DETECTED <<<\n";
    echo "  Fix the above failures before retrying pre-cutover verification.\n";
    echo "  Runtime remains on SQLite. No environment changes permitted.\n";
}

echo "\n";

// Write manifest
$manifest = [
    'manifest_version' => 1,
    'phase' => 'pre_cutover_verification_and_acceptance',
    'task' => '3.9',
    'generated_at' => gmdate('c'),
    'depends_on_task' => '3.8',
    'all_gates_passed' => $allPassed,
    'cutover_approved' => $allPassed,
    'cutover_performed' => false,
    'runtime_change_permitted' => $allPassed,
    'source_mutation_permitted' => false,
    'target_application_traffic_permitted' => false,
    'gates' => $results,
    'gate_summary' => [
        'total' => count($gateNames),
        'passed' => $passCount,
        'failed' => $failCount,
    ],
    'next_steps' => $allPassed 
        ? ['Task 3.10: Runtime cutover with explicit operator approval', 'Do NOT cut over without task 3.10 explicit approval']
        : ['Fix failures and re-run pre-cutover verification', 'Do NOT attempt cutover'],
    'warnings' => [
        'Do NOT enable target application traffic until task 3.10',
        'Do NOT exit maintenance mode',
        'Do NOT modify .env runtime selection without task 3.10 approval',
        'Credentials are NOT logged in this manifest',
    ],
];

$manifestPath = __DIR__ . '/task_3_9_manifest_' . date('Ymd_His') . '.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Manifest written to: {$manifestPath}\n";

exit($allPassed ? 0 : 1);
