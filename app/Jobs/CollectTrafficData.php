<?php

namespace App\Jobs;

use App\Models\ViaTransito;
use App\Services\Traffic\TomTomTrafficProvider;
use App\Services\Traffic\TomTomVectorTrafficCollector;
use App\Services\Traffic\TrafficSimulationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectTrafficData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public int $uniqueFor = 2400;

    public function uniqueId(): string
    {
        return 'tomtom-vector-flow-city-coverage';
    }

    public function handle(
        TomTomTrafficProvider $provider,
        TomTomVectorTrafficCollector $vectorCollector,
        TrafficSimulationService $simulationService,
    ): void {
        Log::info('Job de coleta de trânsito iniciado', ['fonte' => $provider->available() ? 'TomTom Vector Flow Tiles' : 'simulação identificada']);

        if ($provider->available()) {
            try {
                $metrics = $vectorCollector->collect();
                activity()->useLog('traffic_collection')->withProperties($metrics)
                    ->log("TomTom Vector Flow: {$metrics['tiles']} tiles, {$metrics['segments']} segmentos, {$metrics['matched']} associados.");
                if ($metrics['tiles'] > 0 || $metrics['requests'] === 0) {
                    return;
                }
            } catch (\Throwable $exception) {
                Log::error('Falha na coleta TomTom Vector Flow; ativando fallback CCDAE.', ['erro' => $exception->getMessage()]);
            }
        }

        // Fallback identificado, sem novas chamadas ponto a ponto ao provedor.
        $processed = 0;
        $maximum = config('data_sources.traffic.max_segments_per_run');
        ViaTransito::chunk(50, function ($vias) use ($simulationService, &$processed, $maximum) {
            $remaining = max(0, $maximum - $processed);
            $batch = $vias->take($remaining);
            $this->processBatch($batch, $simulationService);
            $processed += count($batch);

            return $processed < $maximum;
        });
    }

    protected function processBatch($vias, TrafficSimulationService $simulationService)
    {
        try {
            $now = Carbon::now();
            $registros = [];
            $viasUpdates = [];

            foreach ($vias as $via) {
                $result = $simulationService->simulateViaSnapshot($via, null, $now);
                $registros[] = $result['registro'];
                $viasUpdates[$via->id] = $result['via_update'];
            }

            DB::transaction(function () use ($registros, $viasUpdates) {
                if (! empty($registros)) {
                    DB::table('registro_transitos')->upsert($registros, ['via_transito_id', 'timestamp']);
                }

                foreach ($viasUpdates as $id => $updateData) {
                    DB::table('via_transitos')->where('id', $id)->update($updateData);
                }
            });

            activity()
                ->useLog('traffic_collection')
                ->log('Coletados dados de trânsito com proveniência para '.count($vias).' vias.');

            return true;

        } catch (\Throwable $e) {
            Log::error('Falha no lote de vias', ['erro' => $e->getMessage()]);

            return false;
        }
    }
}
