<?php

use App\Domain\Inventory\AggregateSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:reconcile-batches {--chunk=500 : Number of aggregates to reconcile per transaction}', function (): int {
    $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($chunkSize === false) {
        $this->error('The --chunk option must be a positive integer.');

        return Command::FAILURE;
    }

    $asOf = CarbonImmutable::today((string) config('app.timezone'));
    $report = app(AggregateSynchronizer::class)->synchronizeChunk($chunkSize, $asOf);

    $this->info(sprintf(
        'Reconciled %d inventory aggregates; updated %d.',
        $report->processed,
        $report->updated,
    ));

    return Command::SUCCESS;
})->purpose('Refresh cached inventory aggregate values from authoritative batches');

Schedule::command('inventory:reconcile-batches')
    ->dailyAt('00:05')
    ->timezone((string) config('app.timezone'))
    ->withoutOverlapping();
