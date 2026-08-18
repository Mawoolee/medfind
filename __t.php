<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

$msg = App\Models\Message::find(7);
echo "Field: " . $msg->prescription_image . PHP_EOL;

$svc = app(App\Services\PrescriptionService::class);
$bytes = $svc->retrieve($msg->prescription_image);
echo "Bytes: " . strlen($bytes) . PHP_EOL;
echo "Hex: " . bin2hex(substr($bytes, 0, 4)) . PHP_EOL;
file_put_contents("public/test_rx.jpg", $bytes);
echo "Saved to public/test_rx.jpg" . PHP_EOL;
