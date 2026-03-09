<?php

namespace App\Jobs;

use App\Models\Distrito;
use App\Services\Climate\ClimateApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectClimateData implements ShouldQueue
{
    use Queueable;

    public function handle(ClimateApiService $service): void
    {
        Log::info("Job iniciado");

        $distritos = Distrito::all();

        Log::info("Distritos encontrados: ".$distritos->count());

        $dados = [];

        foreach ($distritos as $distrito) {

            try {

                $data = $service->getCurrentWeather(
                    $distrito->latitude,
                    $distrito->longitude
                );

                if (!isset($data['current_weather'])) {
                    continue;
                }

                $current = $data['current_weather'];

                $dados[] = [
                    'distrito_id' => $distrito->id,
                    'timestamp' => Carbon::parse($current['time']),
                    'temperatura' => $current['temperature']
                ];

            } catch (\Throwable $e) {

                Log::error("Erro ao coletar clima do distrito ".$distrito->id, [
                    'erro' => $e->getMessage()
                ]);

            }

        }

        if (count($dados) > 0) {

            DB::table('temperatura_registrada')->upsert(
                $dados,
                ['distrito_id','timestamp'],
                ['temperatura']
            );

            Log::info("Registros inseridos: ".count($dados));
        }

    }
}
