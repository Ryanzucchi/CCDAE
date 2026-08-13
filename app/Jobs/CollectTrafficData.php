<?php

namespace App\Jobs;

use App\Models\ViaTransito;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Traffic\TomTomTrafficProvider;

class CollectTrafficData implements ShouldQueue
{
    use Queueable;

    public function handle(TomTomTrafficProvider $provider): void
    {
        Log::info('Job de coleta de trânsito iniciado', ['fonte' => $provider->configured() ? 'TomTom Traffic' : 'simulação identificada']);

        // Processamos em lotes
        $processed = 0; $maximum = config('data_sources.traffic.max_segments_per_run');
        ViaTransito::chunk(50, function ($vias) use ($provider, &$processed, $maximum) {
            $remaining = max(0, $maximum - $processed);
            $batch = $vias->take($remaining);
            $this->processBatch($batch, $provider);
            $processed += count($batch);
            return $processed < $maximum;
        });
    }

    protected function processBatch($vias, TomTomTrafficProvider $provider)
    {
        try {
            $now = Carbon::now();
            $registros = [];
            $viasUpdates = [];

            foreach ($vias as $via) {
                // Simulação baseada no limite de velocidade ou padrão
                $limite = $via->limite_velocidade ?? 60;
                
                // Trânsito dinâmico dependendo da hora
                $hour = $now->hour;
                $isRushHour = ($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19);
                
                $congestionFactor = $isRushHour ? rand(50, 100) : rand(10, 40);
                
                // Velocidade
                $velMedia = max(5, $limite - ($limite * ($congestionFactor / 100)));
                $velMin = max(0, $velMedia - 10);
                $velMax = min($limite + 10, $velMedia + 15);
                
                // Volume
                $baseVolume = $isRushHour ? rand(500, 2000) : rand(50, 400);
                $veiculosTotal = $baseVolume * ($via->numero_faixas ?? 1);
                [$sampleLongitude, $sampleLatitude] = $this->samplePoint($via->geojson);
                $realFlow = $sampleLatitude !== null ? $provider->flow($sampleLatitude, $sampleLongitude) : null;
                $origin = $realFlow ? 'misto' : 'simulado';
                if ($realFlow) {
                    $velMedia = (float) $realFlow['currentSpeed'];
                    $freeFlow = max(1, (float) $realFlow['freeFlowSpeed']);
                    $congestionFactor = (int) round(max(0, min(100, (1 - $velMedia / $freeFlow) * 100)));
                    $velMin = $velMedia; $velMax = $freeFlow;
                    $veiculosTotal = null;
                }

                // Características Veículos
                $percentualPesados = rand(1, 20) / 100;
                $alturaMedia = 1.6 + (1.5 * $percentualPesados);
                $pesoMedio = 1.5 + (8 * $percentualPesados); // ton

                // Clima no trecho (simulado simples para o snapshot de trânsito)
                $temp = 25.0 + rand(-50, 50) / 10;
                $chuva = rand(0, 100) > 90 ? rand(1, 20) : 0;
                $visibilidade = $chuva > 0 ? rand(500, 2000) : 10000;

                // Indicadores
                $indiceCongestionamento = $congestionFactor;
                $niveis = ['A', 'B', 'C', 'D', 'E', 'F'];
                $nivelServico = $niveis[min(5, floor($congestionFactor / 20))];
                
                // Eventos
                $acidente = rand(0, 1000) > 990 ? 1 : 0;
                $obras = rand(0, 100) > 95 ? 1 : 0;

                $dadosAvancados = [
                    'matriz_od' => [
                        'norte_sul' => rand(10, 50),
                        'sul_norte' => rand(10, 50),
                    ],
                    'previsao_proximos_30m' => $congestionFactor + rand(-10, 10)
                ];

                $registros[] = [
                    'via_transito_id' => $via->id,
                    'timestamp' => $now->toIso8601String(),
                    'veiculos_total' => $veiculosTotal,
                    'velocidade_media' => round($velMedia, 1),
                    'velocidade_min' => round($velMin, 1),
                    'velocidade_max' => round($velMax, 1),
                    'tempo_medio_travessia' => round(($via->id % 5 + 1) * (100 / max(1, $velMedia)), 1),
                    'altura_media_veiculos' => round($alturaMedia, 2),
                    'altura_maxima_veiculos' => round($alturaMedia + rand(0, 2), 2),
                    'peso_medio_veiculos' => round($pesoMedio, 2),
                    'peso_maximo_veiculos' => round($pesoMedio + rand(0, 5), 2),
                    'percentual_veiculos_pesados' => round($percentualPesados * 100, 2),
                    'taxa_veiculos_eletricos' => round(rand(1, 10) / 100 * 100, 2),
                    'acidentes_ativos' => $acidente,
                    'obras_ativas' => $obras,
                    'alagamento_ativo' => false,
                    'chuva_mm' => round($chuva, 1),
                    'visibilidade' => $visibilidade,
                    'temperatura' => round($temp, 1),
                    'nivel_ruido' => $veiculosTotal !== null ? round(60 + ($veiculosTotal / 100) + rand(-5, 5), 1) : null,
                    'emissao_co2' => $veiculosTotal !== null ? round($veiculosTotal * 0.15, 2) : null,
                    'indice_congestionamento' => $indiceCongestionamento,
                    'nivel_servico' => $nivelServico,
                    'dados_avancados' => json_encode($dadosAvancados)
                    , 'origem_dado' => $origin, 'fonte_dado' => $realFlow ? 'TomTom Traffic Flow + estimativas CCDAE' : 'fallback_ccdae',
                    'confianca_dado' => $realFlow['confidence'] ?? null,
                ];

                // Atualizar modelo ViaTransito
                $impactoManutencao = 'baixo';
                if (($veiculosTotal ?? 0) > 1000 || $percentualPesados > 0.15) {
                    $impactoManutencao = 'alto';
                } elseif ($veiculosTotal > 500 || $percentualPesados > 0.08) {
                    $impactoManutencao = 'medio';
                }

                $viasUpdates[$via->id] = [
                    'nivel_congestionamento' => (int)$indiceCongestionamento,
                    'velocidade_media' => (int)round($velMedia),
                    'volume_veiculos' => $veiculosTotal !== null ? (int) $veiculosTotal : null,
                    'impacto_manutencao' => $impactoManutencao,
                    'ultima_atualizacao' => $now,
                ];
            }

            DB::transaction(function () use ($registros, $viasUpdates) {
                if (!empty($registros)) {
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
            Log::error("Falha no lote de vias", ['erro' => $e->getMessage()]);
            return false;
        }
    }

    private function samplePoint(mixed $geometry): array
    {
        if (is_string($geometry)) $geometry = json_decode($geometry, true);
        if (($geometry['type'] ?? null) === 'Feature') $geometry = $geometry['geometry'] ?? [];
        $coordinates = $geometry['type'] === 'MultiLineString' ? ($geometry['coordinates'][0] ?? []) : ($geometry['coordinates'] ?? []);
        if (! $coordinates) return [null, null];
        $point = $coordinates[(int) floor(count($coordinates) / 2)] ?? null;
        return is_array($point) && count($point) >= 2 ? [(float) $point[0], (float) $point[1]] : [null, null];
    }
}
