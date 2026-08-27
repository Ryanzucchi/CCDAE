<?php

namespace App\Services\Traffic;

use App\Models\ViaTransito;
use Carbon\Carbon;

class TrafficSimulationService
{
    public function simulateViaSnapshot(ViaTransito $via, ?array $realFlow = null, ?Carbon $now = null): array
    {
        $currentTime = $now ?? Carbon::now();
        $limite = $via->limite_velocidade ?? 60;

        $hour = $currentTime->hour;
        $isRushHour = ($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19);
        $congestionFactor = $isRushHour ? rand(50, 100) : rand(10, 40);

        $velMedia = max(5, $limite - ($limite * ($congestionFactor / 100)));
        $velMin = max(0, $velMedia - 10);
        $velMax = min($limite + 10, $velMedia + 15);

        $baseVolume = $isRushHour ? rand(500, 2000) : rand(50, 400);
        $veiculosTotal = $baseVolume * ($via->numero_faixas ?? 1);
        $origin = $realFlow ? 'misto' : 'simulado';
        $freeFlow = null;
        $congestionRatio = null;

        if ($realFlow) {
            $velMedia = (float) $realFlow['currentSpeed'];
            $freeFlow = max(1, (float) $realFlow['freeFlowSpeed']);
            $congestionRatio = max(0, min(1, $velMedia / $freeFlow));
            $congestionFactor = (int) round(max(0, min(100, (1 - $velMedia / $freeFlow) * 100)));
            $velMin = $velMedia;
            $velMax = $freeFlow;
            $veiculosTotal = null;
        }

        $percentualPesados = rand(1, 20) / 100;
        $alturaMedia = 1.6 + (1.5 * $percentualPesados);
        $pesoMedio = 1.5 + (8 * $percentualPesados);

        $temp = 25.0 + rand(-50, 50) / 10;
        $chuva = rand(0, 100) > 90 ? rand(1, 20) : 0;
        $visibilidade = $chuva > 0 ? rand(500, 2000) : 10000;

        $indiceCongestionamento = $congestionFactor;
        $niveis = ['A', 'B', 'C', 'D', 'E', 'F'];
        $nivelServico = $niveis[min(5, floor($congestionFactor / 20))];

        $acidente = rand(0, 1000) > 990 ? 1 : 0;
        $obras = rand(0, 100) > 95 ? 1 : 0;

        $dadosAvancados = [
            'matriz_od' => [
                'norte_sul' => rand(10, 50),
                'sul_norte' => rand(10, 50),
            ],
            'previsao_proximos_30m' => $congestionFactor + rand(-10, 10),
        ];

        $registro = [
            'via_transito_id' => $via->id,
            'timestamp' => $currentTime->toIso8601String(),
            'veiculos_total' => $veiculosTotal,
            'velocidade_media' => round($velMedia, 1),
            'velocidade_fluxo_livre' => $freeFlow !== null ? round($freeFlow, 1) : null,
            'razao_congestionamento' => $congestionRatio !== null ? round($congestionRatio, 5) : null,
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
            'dados_avancados' => json_encode($dadosAvancados),
            'origem_dado' => $origin,
            'fonte_dado' => $realFlow ? 'TomTom Traffic Flow + estimativas CCDAE' : 'fallback_ccdae',
            'confianca_dado' => $realFlow['confidence'] ?? null,
        ];

        $impactoManutencao = 'baixo';
        if (($veiculosTotal ?? 0) > 1000 || $percentualPesados > 0.15) {
            $impactoManutencao = 'alto';
        } elseif ($veiculosTotal > 500 || $percentualPesados > 0.08) {
            $impactoManutencao = 'medio';
        }

        $viaUpdate = [
            'nivel_congestionamento' => (int) $indiceCongestionamento,
            'velocidade_media' => (int) round($velMedia),
            'volume_veiculos' => $veiculosTotal !== null ? (int) $veiculosTotal : null,
            'impacto_manutencao' => $impactoManutencao,
            'ultima_atualizacao' => $currentTime,
        ];

        return [
            'registro' => $registro,
            'via_update' => $viaUpdate,
        ];
    }
}
