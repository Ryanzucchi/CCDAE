<?php

namespace App\Services\Climate;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClimateSimulationService
{
    public function generateWeatherData(Collection $distritos, ?Carbon $now = null): array
    {
        $nowIso = ($now ?? Carbon::now())->toIso8601String();
        $hour = ($now ?? Carbon::now())->hour;
        $isDaytime = $hour >= 6 && $hour <= 18;

        $weatherData = [];
        foreach ($distritos as $distrito) {
            $baseTemp = 22.0 + (sin((float) $distrito->latitude) * 5) + (cos((float) $distrito->longitude) * 5);
            $temperature = round($baseTemp + rand(-20, 20) / 10.0, 1);
            $apparent = round($temperature + rand(-10, 10) / 10.0, 1);
            $precipitation = (rand(0, 100) > 85) ? round(rand(1, 150) / 10.0, 1) : 0.0;
            $pressure = round(1010.0 + rand(-100, 100) / 10.0, 1);
            $windSpeed = round(rand(0, 350) / 10.0, 1);
            $windDir = rand(0, 359);
            $radiation = $isDaytime ? round(rand(200, 800)) : 0.0;
            $uv = $isDaytime ? round(rand(1, 11)) : 0.0;

            $weatherData[] = [
                'current' => [
                    'time' => $nowIso,
                    'temperature_2m' => $temperature,
                    'apparent_temperature' => $apparent,
                    'precipitation' => $precipitation,
                    'surface_pressure' => $pressure,
                    'wind_speed_10m' => $windSpeed,
                    'wind_direction_10m' => $windDir,
                    'shortwave_radiation' => $radiation,
                    'uv_index' => $uv,
                ],
            ];
        }

        return $weatherData;
    }

    public function generateAirQualityData(Collection $distritos, ?Carbon $now = null): array
    {
        $nowIso = ($now ?? Carbon::now())->toIso8601String();

        $aqData = [];
        foreach ($distritos as $distrito) {
            $pm25 = round(rand(50, 450) / 10.0, 1);
            $pm10 = round($pm25 * 1.5 + rand(-5, 5), 1);
            $dust = round(rand(0, 250) / 10.0, 1);
            $ozone = round(rand(100, 900) / 10.0, 1);

            $aqData[] = [
                'current' => [
                    'time' => $nowIso,
                    'pm2_5' => $pm25,
                    'pm10' => $pm10,
                    'dust' => $dust,
                    'ozone' => $ozone,
                ],
            ];
        }

        return $aqData;
    }
}
