<?php

namespace App\Services\Climate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClimateApiService
{
    protected string $apiUrl = 'https://api.open-meteo.com/v1/forecast';

    public function getCurrentWeather(float $lat, float $lon): array
    {
        try {

            $response = Http::retry(3, 1000)
                ->timeout(10)
                ->get($this->apiUrl, [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'current_weather' => true,
                    'hourly' => 'temperature_2m,relative_humidity_2m,pressure_msl',
                ]);

            if (!$response->successful()) {

                Log::error('Erro API clima', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new \Exception('Erro ao consultar API climática');
            }

            return $response->json();

        } catch (\Throwable $e) {

            Log::error('Falha conexão API clima', [
                'lat' => $lat,
                'lon' => $lon,
                'erro' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
