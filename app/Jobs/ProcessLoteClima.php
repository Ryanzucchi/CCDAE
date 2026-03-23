<?php

namespace App\Jobs;

use App\Models\Distrito;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessLoteClima implements ShouldQueue
{
    use Batchable, Queueable;

    public array $distritosIds;

    public function __construct(array $distritosIds)
    {
        $this->distritosIds = $distritosIds;
    }

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $distritos = Distrito::whereIn('id', $this->distritosIds)->get();
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

            // A Open-Meteo retorna os resultados num array quando se envia múltiplas coordenadas
            foreach ($data as $index => $result) {
                if (isset($result['current_weather'])) {
                    $dadosParaInserir[] = [
                        'distrito_id' => $distritos[$index]->id,
                        'timestamp' => Carbon::parse($result['current_weather']['time']),
                        'temperatura' => $result['current_weather']['temperature'],
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
            Log::error("Erro no lote", ['erro' => $e->getMessage()]);
            // Opcional: $this->fail($e); se quiser que o lote marque falha
        }
    }
}
