<?php

namespace App\Jobs;

use App\Models\Distrito;
use App\Services\Climate\ClimateSimulationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CollectClimateData implements ShouldQueue
{
    use Queueable;

    public function handle(ClimateSimulationService $simulationService): void
    {
        Log::info('Job de alta performance iniciado');

        // Processamos em pedaços (chunks) de 50 para respeitar o limite da API Open-Meteo
        Distrito::chunk(50, function ($distritos) use ($simulationService) {
            $success = $this->processBatch($distritos, $simulationService);
            if ($success === false) {
                Log::warning('Abortando coleta climática devido a falha persistente na API (Rate Limit / Bloqueio).');

                return false;
            }
        });
    }

    protected function processBatch($distritos, ClimateSimulationService $simulationService)
    {
        // A API pode repetir o mesmo `current.time` durante toda a hora. A série
        // histórica deve representar o instante da coleta feita pelo CCDAE.
        $collectedAt = Carbon::now()->startOfMinute();
        $lats = $distritos->pluck('latitude')->implode(',');
        $lons = $distritos->pluck('longitude')->implode(',');

        $weatherResponse = null;
        $aqResponse = null;
        $rateLimited = Cache::get('open_meteo_rate_limited', false);

        if (! $rateLimited) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $weatherResponse = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude' => $lats,
                        'longitude' => $lons,
                        'current' => 'temperature_2m,apparent_temperature,precipitation,surface_pressure,wind_speed_10m,wind_direction_10m,shortwave_radiation,uv_index',
                    ]);
                    if ($weatherResponse->status() === 429) {
                        Cache::put('open_meteo_rate_limited', true, 300);
                        break;
                    }
                    if ($weatherResponse->status() !== 429) {
                        break;
                    }
                } catch (\Throwable $t) {
                    Log::warning("Erro HTTP ao chamar API de clima Open-Meteo (tentativa $attempt/2): ".$t->getMessage());
                }
                sleep(1);
            }

            if (! Cache::get('open_meteo_rate_limited', false)) {
                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    try {
                        $aqResponse = Http::timeout(10)->get('https://air-quality-api.open-meteo.com/v1/air-quality', [
                            'latitude' => $lats,
                            'longitude' => $lons,
                            'current' => 'pm2_5,pm10,dust,ozone',
                        ]);
                        if ($aqResponse->status() === 429) {
                            Cache::put('open_meteo_rate_limited', true, 300);
                            break;
                        }
                        if ($aqResponse->status() !== 429) {
                            break;
                        }
                    } catch (\Throwable $t) {
                        Log::warning("Erro HTTP ao chamar API de qualidade do ar Open-Meteo (tentativa $attempt/2): ".$t->getMessage());
                    }
                    sleep(1);
                }
            }
        }

        try {
            $weatherOrigin = ($weatherResponse && $weatherResponse->successful()) ? 'real' : 'simulado';
            if ($weatherResponse && $weatherResponse->successful()) {
                $weatherData = $weatherResponse->json();
                if (count($distritos) === 1) {
                    $weatherData = [$weatherData];
                }
            } else {
                $statusStr = $weatherResponse ? (string) $weatherResponse->status() : 'Timeout/Erro';
                Log::warning("API Open-Meteo de clima indisponível ou rate limit (Status: {$statusStr}). Usando simulação para lote.");
                $weatherData = $simulationService->generateWeatherData($distritos);
            }

            $airOrigin = ($aqResponse && $aqResponse->successful()) ? 'real' : 'simulado';
            if ($aqResponse && $aqResponse->successful()) {
                $aqData = $aqResponse->json();
                if (count($distritos) === 1) {
                    $aqData = [$aqData];
                }
            } else {
                $statusStr = $aqResponse ? (string) $aqResponse->status() : 'Timeout/Erro';
                Log::warning("API Open-Meteo de qualidade do ar indisponível ou rate limit (Status: {$statusStr}). Usando simulação para lote.");
                $aqData = $simulationService->generateAirQualityData($distritos);
            }

            $temps = [];
            $winds = [];
            $rains = [];
            $pressures = [];
            $radiations = [];
            $uvs = [];
            $particles = [];

            foreach ($distritos as $index => $distrito) {
                $weather = $weatherData[$index] ?? null;
                $aq = $aqData[$index] ?? null;

                if ($weather && isset($weather['current'])) {
                    $wCurrent = $weather['current'];
                    $timestamp = $collectedAt;

                    $temps[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'temperatura' => $wCurrent['temperature_2m'] ?? 0,
                        'sensacao_termica' => $wCurrent['apparent_temperature'] ?? null,
                        'origem_dado' => $weatherOrigin, 'fonte_dado' => $weatherOrigin === 'real' ? 'Open-Meteo' : 'fallback_ccdae',
                    ];

                    $winds[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'velocidade' => $wCurrent['wind_speed_10m'] ?? 0,
                        'direcao' => $wCurrent['wind_direction_10m'] ?? 0,
                        'origem_dado' => $weatherOrigin, 'fonte_dado' => $weatherOrigin === 'real' ? 'Open-Meteo' : 'fallback_ccdae',
                    ];

                    $rains[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'precipitacao_mm' => $wCurrent['precipitation'] ?? 0,
                        'origem_dado' => $weatherOrigin, 'fonte_dado' => $weatherOrigin === 'real' ? 'Open-Meteo' : 'fallback_ccdae',
                    ];

                    $pressures[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'pressao_hpa' => $wCurrent['surface_pressure'] ?? 0,
                        'origem_dado' => $weatherOrigin, 'fonte_dado' => $weatherOrigin === 'real' ? 'Open-Meteo' : 'fallback_ccdae',
                    ];

                    $radiations[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'radiacao_w_m2' => $wCurrent['shortwave_radiation'] ?? 0,
                        'origem_dado' => $weatherOrigin, 'fonte_dado' => $weatherOrigin === 'real' ? 'Open-Meteo' : 'fallback_ccdae',
                    ];

                    $uvs[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'uv' => $wCurrent['uv_index'] ?? 0,
                        'origem_dado' => $weatherOrigin, 'fonte_dado' => $weatherOrigin === 'real' ? 'Open-Meteo' : 'fallback_ccdae',
                    ];
                }

                if ($aq && isset($aq['current'])) {
                    $aqCurrent = $aq['current'];
                    $timestamp = $collectedAt;
                    $dustVal = $aqCurrent['dust'] ?? 0;

                    $particles[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'pm25' => $aqCurrent['pm2_5'] ?? null,
                        'pm10' => $aqCurrent['pm10'] ?? null,
                        'poeira' => $dustVal,
                        'areia' => $dustVal * 0.1,
                        'poluentes' => $aqCurrent['ozone'] ?? null,
                        'origem_dado' => $airOrigin, 'fonte_dado' => $airOrigin === 'real' ? 'Open-Meteo Air Quality' : 'fallback_ccdae',
                    ];
                }
            }

            if (! empty($temps)) {
                DB::table('temperatura_registrada')->upsert($temps, ['distrito_id', 'timestamp'], ['temperatura', 'sensacao_termica', 'origem_dado', 'fonte_dado']);
            }
            if (! empty($winds)) {
                DB::table('vento_registrado')->upsert($winds, ['distrito_id', 'timestamp'], ['velocidade', 'direcao', 'origem_dado', 'fonte_dado']);
            }
            if (! empty($rains)) {
                DB::table('chuva_registrada')->upsert($rains, ['distrito_id', 'timestamp'], ['precipitacao_mm', 'origem_dado', 'fonte_dado']);
            }
            if (! empty($pressures)) {
                DB::table('pressao_atmosferica')->upsert($pressures, ['distrito_id', 'timestamp'], ['pressao_hpa', 'origem_dado', 'fonte_dado']);
            }
            if (! empty($radiations)) {
                DB::table('radiacao_solar')->upsert($radiations, ['distrito_id', 'timestamp'], ['radiacao_w_m2', 'origem_dado', 'fonte_dado']);
            }
            if (! empty($uvs)) {
                DB::table('indice_uv')->upsert($uvs, ['distrito_id', 'timestamp'], ['uv', 'origem_dado', 'fonte_dado']);
            }
            if (! empty($particles)) {
                DB::table('particulas_ar')->upsert($particles, ['distrito_id', 'timestamp'], ['pm25', 'pm10', 'poeira', 'areia', 'poluentes', 'origem_dado', 'fonte_dado']);
            }

            activity()
                ->useLog('climate_collection')
                ->log('Coletados dados meteorológicos completos para '.count($distritos).' distritos.');

            Log::info('Coleta climática persistida', [
                'timestamp_coleta' => $collectedAt->toDateTimeString(),
                'distritos' => count($distritos),
                'temperaturas' => count($temps),
                'ventos' => count($winds),
                'chuvas' => count($rains),
                'qualidade_ar' => count($particles),
            ]);

            // Apenas dorme se chamamos a API externa de fato, para não atrasar a simulação
            if (($weatherResponse && $weatherResponse->successful()) || ($aqResponse && $aqResponse->successful())) {
                sleep(1);
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('Falha no lote de distritos', ['erro' => $e->getMessage()]);

            return false;
        }
    }
}
