<?php
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=medfind', 'postgres', 'root123');
$cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'search_logs' ORDER BY ordinal_position");
echo "search_logs columns:\n";
foreach($cols as $c) echo "  " . $c['column_name'] . "\n";

echo "\nactivity_logs columns:\n";
$cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'activity_logs' ORDER BY ordinal_position");
foreach($cols as $c) echo "  " . $c['column_name'] . "\n";
