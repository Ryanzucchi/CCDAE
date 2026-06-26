<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CollectTrafficData;
use App\Jobs\CollectClimateData;

Schedule::command('climate:sanitize')->dailyAt('02:00');

$intervalo = env('COLETA_DADOS_INTERVALO', 20);

// Agenda as coletas baseadas na variável global
Schedule::job(new CollectTrafficData)->cron("*/{$intervalo} * * * *");
Schedule::job(new CollectClimateData)->cron("*/{$intervalo} * * * *");
