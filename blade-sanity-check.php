<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$view = 'pharmacy.audit_log';
$path = $app->make('view')->getFinder()->find($view);
$compiled = $app->make('blade.compiler')->compileString(file_get_contents($path));

$tmp = tempnam(sys_get_temp_dir(), 'blade').'.php';
file_put_contents($tmp, $compiled);
$exit = 0;
passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp), $exit);
unlink($tmp);

echo $exit === 0 ? "Blade compile + lint OK for {$view}\n" : "Blade lint FAILED for {$view}\n";
exit($exit);
