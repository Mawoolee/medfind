<?php
$sqlite = new PDO('sqlite:' . dirname(__DIR__) . '/database.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo "SQLite users:\n";
$rows = $sqlite->query('SELECT id, pharmacy_id FROM users');
foreach($rows as $r) echo "  user {$r['id']} pharmacy_id=" . ($r['pharmacy_id'] ?? 'NULL') . "\n";

echo "\nSQLite pharmacies:\n";
$rows = $sqlite->query('SELECT id, user_id FROM pharmacies');
foreach($rows as $r) echo "  pharmacy {$r['id']} user_id=" . ($r['user_id'] ?? 'NULL') . "\n";

echo "\nPostgreSQL users:\n";
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=medfind', 'postgres', 'root123');
$rows = $pdo->query('SELECT id, pharmacy_id FROM users');
foreach($rows as $r) echo "  user {$r['id']} pharmacy_id=" . ($r['pharmacy_id'] ?? 'NULL') . "\n";

echo "\nPostgreSQL pharmacies:\n";
$rows = $pdo->query('SELECT id, user_id FROM pharmacies');
foreach($rows as $r) echo "  pharmacy {$r['id']} user_id=" . ($r['user_id'] ?? 'NULL') . "\n";
