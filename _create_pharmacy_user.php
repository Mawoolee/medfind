<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$u = User::updateOrCreate(
    ['email' => 'pharmacy@medfind.com'],
    [
        'name' => 'Mercury Drug Operator',
        'password' => Hash::make('password'),
        'role' => 'pharmacy',
    ]
);
echo $u->id . ' ' . $u->email . ' ' . $u->role . PHP_EOL;
