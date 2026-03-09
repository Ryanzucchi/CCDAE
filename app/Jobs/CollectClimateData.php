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
            $this->processBatch($distritos);
        });
    }

    protected function processBatch($distritos)
    {
        $lats = $distritos->pluck('latitude')->implode(',');
        $lons = $distritos->pluck('longitude')->implode(',');

        try {
            $response = Http::timeout(15)->get("https://api.open-meteo.com/v1/forecast", [
                'latitude' => $lats,
                'longitude' => $lons,
                'current_weather' => true,
            ]);

            if ($response->failed()) return;

            $data = $response->json();
            $dadosParaInserir = [];

            // A API retorna um array de resultados quando enviamos múltiplas latitudes
            foreach ($data as $index => $result) {
                if (isset($result['current_weather'])) {
                    $current = $result['current_weather'];
                    $distrito = $distritos[$index];

                    $dadosParaInserir[] = [
                        'distrito_id' => $distrito->id,
                        'timestamp' => Carbon::parse($current['time']),
                        'temperatura' => $current['temperature'],
                    ];
                }
            }

            if (!empty($dadosParaInserir)) {
                DB::table('temperatura_registrada')->upsert(
                    $dadosParaInserir,
                    ['distrito_id', 'timestamp'],
                    ['temperatura']
                );
            }

        } catch (\Throwable $e) {
            Log::error("Falha no lote de distritos", ['erro' => $e->getMessage()]);
        }
    }
}
