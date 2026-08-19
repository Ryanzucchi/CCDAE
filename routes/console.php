<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use App\Jobs\CollectClimateData;
use App\Jobs\CollectTrafficData;
use Illuminate\Support\Facades\Schedule;

Schedule::command('climate:sanitize')->dailyAt('02:00');
Schedule::command('internet:sync-antennas')->weeklyOn(1, '03:20')->withoutOverlapping();

$intervalo = max(1, min(59, (int) env('COLETA_DADOS_INTERVALO', 20)));

// Único ponto de agendamento das coletas. Evita despachos duplicados.
Schedule::job(new CollectTrafficData)
    ->cron("*/{$intervalo} * * * *")
    ->name('collect-traffic-data')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::job(new CollectClimateData)
    ->cron("*/{$intervalo} * * * *")
    ->name('collect-climate-data')
    ->onOneServer()
    ->withoutOverlapping();
