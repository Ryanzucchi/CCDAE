<?php

namespace App\Jobs;

use App\Models\ViaTransito;
use App\Services\Traffic\TomTomTrafficProvider;
use App\Services\Traffic\TrafficSimulationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectTrafficData implements ShouldQueue
{
    use Queueable;

    public function handle(TomTomTrafficProvider $provider, TrafficSimulationService $simulationService): void
    {
        Log::info('Job de coleta de trânsito iniciado', ['fonte' => $provider->available() ? 'TomTom Traffic' : 'simulação identificada']);

        // Processamos em lotes
        $processed = 0;
        $maximum = config('data_sources.traffic.max_segments_per_run');
        ViaTransito::chunk(50, function ($vias) use ($provider, $simulationService, &$processed, $maximum) {
            $remaining = max(0, $maximum - $processed);
            $batch = $vias->take($remaining);
            $this->processBatch($batch, $provider, $simulationService);
            $processed += count($batch);

            return $processed < $maximum;
        });
    }

    protected function processBatch($vias, TomTomTrafficProvider $provider, TrafficSimulationService $simulationService)
    {
        try {
            $now = Carbon::now();
            $registros = [];
            $viasUpdates = [];

            foreach ($vias as $via) {
                [$sampleLongitude, $sampleLatitude] = $this->samplePoint($via->geojson);
                $realFlow = $sampleLatitude !== null ? $provider->flow($sampleLatitude, $sampleLongitude) : null;

                $result = $simulationService->simulateViaSnapshot($via, $realFlow, $now);
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

    private function samplePoint(mixed $geometry): array
    {
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }
        if (($geometry['type'] ?? null) === 'Feature') {
            $geometry = $geometry['geometry'] ?? [];
        }
        $coordinates = $geometry['type'] === 'MultiLineString' ? ($geometry['coordinates'][0] ?? []) : ($geometry['coordinates'] ?? []);
        if (! $coordinates) {
            return [null, null];
        }
        $point = $coordinates[(int) floor(count($coordinates) / 2)] ?? null;

        return is_array($point) && count($point) >= 2 ? [(float) $point[0], (float) $point[1]] : [null, null];
    }
}
