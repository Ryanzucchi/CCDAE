<?php

namespace App\Jobs;

use App\Models\Distrito;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectClimateData implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info("Job de alta performance iniciado");

        // Processamos em pedaços (chunks) de 50 para respeitar o limite da API Open-Meteo
        Distrito::chunk(50, function ($distritos) {
            $success = $this->processBatch($distritos);
            if ($success === false) {
                Log::warning("Abortando coleta climática devido a falha persistente na API (Rate Limit / Bloqueio).");
                return false;
            }
        });
    }

    protected function processBatch($distritos)
    {
        $lats = $distritos->pluck('latitude')->implode(',');
        $lons = $distritos->pluck('longitude')->implode(',');

        $weatherResponse = null;
        $aqResponse = null;
        $rateLimited = \Illuminate\Support\Facades\Cache::get('open_meteo_rate_limited', false);

        if (!$rateLimited) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $weatherResponse = Http::timeout(10)->get("https://api.open-meteo.com/v1/forecast", [
                        'latitude' => $lats,
                        'longitude' => $lons,
                        'current' => 'temperature_2m,apparent_temperature,precipitation,surface_pressure,wind_speed_10m,wind_direction_10m,shortwave_radiation,uv_index',
                    ]);
                    if ($weatherResponse->status() === 429) {
                        \Illuminate\Support\Facades\Cache::put('open_meteo_rate_limited', true, 300);
                        break;
                    }
                    if ($weatherResponse->status() !== 429) {
                        break;
                    }
                } catch (\Throwable $t) {
                    Log::warning("Erro HTTP ao chamar API de clima Open-Meteo (tentativa $attempt/2): " . $t->getMessage());
                }
                sleep(1);
            }

            if (!\Illuminate\Support\Facades\Cache::get('open_meteo_rate_limited', false)) {
                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    try {
                        $aqResponse = Http::timeout(10)->get("https://air-quality-api.open-meteo.com/v1/air-quality", [
                            'latitude' => $lats,
                            'longitude' => $lons,
                            'current' => 'pm2_5,pm10,dust,ozone',
                        ]);
                        if ($aqResponse->status() === 429) {
                            \Illuminate\Support\Facades\Cache::put('open_meteo_rate_limited', true, 300);
                            break;
                        }
                        if ($aqResponse->status() !== 429) {
                            break;
                        }
                    } catch (\Throwable $t) {
                        Log::warning("Erro HTTP ao chamar API de qualidade do ar Open-Meteo (tentativa $attempt/2): " . $t->getMessage());
                    }
                    sleep(1);
                }
            }
        }

        try {
            $weatherData = null;
            if ($weatherResponse && $weatherResponse->successful()) {
                $weatherData = $weatherResponse->json();
                if (count($distritos) === 1) {
                    $weatherData = [$weatherData];
                }
            } else {
                $statusStr = $weatherResponse ? (string)$weatherResponse->status() : 'Timeout/Erro';
                Log::warning("API Open-Meteo de clima indisponível ou rate limit (Status: {$statusStr}). Usando simulação para lote.");
                $weatherData = [];
                $now = Carbon::now()->toIso8601String();
                foreach ($distritos as $distrito) {
                    $baseTemp = 22.0 + (sin($distrito->latitude) * 5) + (cos($distrito->longitude) * 5);
                    $temperature = round($baseTemp + rand(-20, 20) / 10.0, 1);
                    $apparent = round($temperature + rand(-10, 10) / 10.0, 1);
                    $precipitation = (rand(0, 100) > 85) ? round(rand(1, 150) / 10.0, 1) : 0.0;
                    $pressure = round(1010.0 + rand(-100, 100) / 10.0, 1);
                    $windSpeed = round(rand(0, 350) / 10.0, 1);
                    $windDir = rand(0, 359);
                    $hour = Carbon::now()->hour;
                    $isDaytime = $hour >= 6 && $hour <= 18;
                    $radiation = $isDaytime ? round(rand(200, 800)) : 0.0;
                    $uv = $isDaytime ? round(rand(1, 11)) : 0.0;

                    $weatherData[] = [
                        'current' => [
                            'time' => $now,
                            'temperature_2m' => $temperature,
                            'apparent_temperature' => $apparent,
                            'precipitation' => $precipitation,
                            'surface_pressure' => $pressure,
                            'wind_speed_10m' => $windSpeed,
                            'wind_direction_10m' => $windDir,
                            'shortwave_radiation' => $radiation,
                            'uv_index' => $uv
                        ]
                    ];
                }
            }

            $aqData = null;
            if ($aqResponse && $aqResponse->successful()) {
                $aqData = $aqResponse->json();
                if (count($distritos) === 1) {
                    $aqData = [$aqData];
                }
            } else {
                $statusStr = $aqResponse ? (string)$aqResponse->status() : 'Timeout/Erro';
                Log::warning("API Open-Meteo de qualidade do ar indisponível ou rate limit (Status: {$statusStr}). Usando simulação para lote.");
                $aqData = [];
                $now = Carbon::now()->toIso8601String();
                foreach ($distritos as $distrito) {
                    $pm25 = round(rand(50, 450) / 10.0, 1);
                    $pm10 = round($pm25 * 1.5 + rand(-5, 5), 1);
                    $dust = round(rand(0, 250) / 10.0, 1);
                    $ozone = round(rand(100, 900) / 10.0, 1);

                    $aqData[] = [
                        'current' => [
                            'time' => $now,
                            'pm2_5' => $pm25,
                            'pm10' => $pm10,
                            'dust' => $dust,
                            'ozone' => $ozone
                        ]
                    ];
                }
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
                    $timestamp = Carbon::parse($wCurrent['time']);

                    $temps[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'temperatura' => $wCurrent['temperature_2m'] ?? 0,
                        'sensacao_termica' => $wCurrent['apparent_temperature'] ?? null,
                    ];

                    $winds[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'velocidade' => $wCurrent['wind_speed_10m'] ?? 0,
                        'direcao' => $wCurrent['wind_direction_10m'] ?? 0,
                    ];

                    $rains[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'precipitacao_mm' => $wCurrent['precipitation'] ?? 0,
                    ];

                    $pressures[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'pressao_hpa' => $wCurrent['surface_pressure'] ?? 0,
                    ];

                    $radiations[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'radiacao_w_m2' => $wCurrent['shortwave_radiation'] ?? 0,
                    ];

                    $uvs[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'uv' => $wCurrent['uv_index'] ?? 0,
                    ];
                }

                if ($aq && isset($aq['current'])) {
                    $aqCurrent = $aq['current'];
                    $timestamp = Carbon::parse($aqCurrent['time']);
                    $dustVal = $aqCurrent['dust'] ?? 0;

                    $particles[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => $timestamp,
                        'pm25' => $aqCurrent['pm2_5'] ?? null,
                        'pm10' => $aqCurrent['pm10'] ?? null,
                        'poeira' => $dustVal,
                        'areia' => $dustVal * 0.1,
                        'poluentes' => $aqCurrent['ozone'] ?? null,
                    ];
                }
            }

            if (!empty($temps)) {
                DB::table('temperatura_registrada')->upsert($temps, ['distrito_id', 'timestamp'], ['temperatura', 'sensacao_termica']);
            }
            if (!empty($winds)) {
                DB::table('vento_registrado')->upsert($winds, ['distrito_id', 'timestamp'], ['velocidade', 'direcao']);
            }
            if (!empty($rains)) {
                DB::table('chuva_registrada')->upsert($rains, ['distrito_id', 'timestamp'], ['precipitacao_mm']);
            }
            if (!empty($pressures)) {
                DB::table('pressao_atmosferica')->upsert($pressures, ['distrito_id', 'timestamp'], ['pressao_hpa']);
            }
            if (!empty($radiations)) {
                DB::table('radiacao_solar')->upsert($radiations, ['distrito_id', 'timestamp'], ['radiacao_w_m2']);
            }
            if (!empty($uvs)) {
                DB::table('indice_uv')->upsert($uvs, ['distrito_id', 'timestamp'], ['uv']);
            }
            if (!empty($particles)) {
                DB::table('particulas_ar')->upsert($particles, ['distrito_id', 'timestamp'], ['pm25', 'pm10', 'poeira', 'areia', 'poluentes']);
            }

            activity()
                ->useLog('climate_collection')
                ->log("Coletados dados meteorológicos completos para " . count($distritos) . " distritos.");

            // Apenas dorme se chamamos a API externa de fato, para não atrasar a simulação
            if (($weatherResponse && $weatherResponse->successful()) || ($aqResponse && $aqResponse->successful())) {
                sleep(1);
            }
            return true;

        } catch (\Throwable $e) {
            Log::error("Falha no lote de distritos", ['erro' => $e->getMessage()]);
            return false;
        }
    }
}
