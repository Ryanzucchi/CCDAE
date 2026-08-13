<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrafficTimelineController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $districtId = $request->integer('distrito_id') ?: null;
        $cacheKey = 'mapa-transito:timeline:'.($districtId ?: 'todos');

        $timeline = Cache::remember($cacheKey, now()->addMinute(), function () use ($districtId): array {
            $rows = DB::table('registro_transitos as registros')
                ->join('via_transitos as vias', 'vias.id', '=', 'registros.via_transito_id')
                ->where('registros.timestamp', '>=', now()->subHours(24))
                ->when($districtId, fn ($query) => $query->where('vias.distrito_id', $districtId))
                ->orderBy('registros.timestamp')
                ->get([
                    'registros.via_transito_id', 'registros.timestamp', 'registros.velocidade_media',
                    'registros.veiculos_total', 'registros.indice_congestionamento',
                    'registros.acidentes_ativos', 'registros.obras_ativas', 'registros.alagamento_ativo',
                    'registros.origem_dado', 'registros.fonte_dado', 'registros.confianca_dado',
                ]);

            return $rows->groupBy(fn ($row) => substr((string) $row->timestamp, 0, 13))
                ->take(-24)
                ->map(function ($hourRows): array {
                    $timestamp = Carbon::parse($hourRows->last()->timestamp);
                    $data = $hourRows->groupBy('via_transito_id')->map(function ($viaRows): array {
                        $record = $viaRows->last();
                        $speed = $record->velocidade_media !== null ? (float) $record->velocidade_media : null;
                        $index = $record->indice_congestionamento !== null ? (int) $record->indice_congestionamento : null;
                        $congestion = $index !== null
                            ? ($index >= 80 ? 'parado' : ($index >= 55 ? 'intenso' : ($index >= 30 ? 'moderado' : 'livre')))
                            : ($speed !== null ? ($speed < 10 ? 'parado' : ($speed < 20 ? 'intenso' : ($speed <= 40 ? 'moderado' : 'livre'))) : 'livre');

                        return [
                            (int) $record->via_transito_id,
                            $speed,
                            $record->veiculos_total !== null ? (int) $record->veiculos_total : null,
                            array_search($congestion, ['livre', 'moderado', 'intenso', 'parado'], true),
                            (int) ($record->acidentes_ativos ?? 0),
                            (int) ($record->obras_ativas ?? 0),
                            (bool) $record->alagamento_ativo,
                            $record->origem_dado ?? 'desconhecido', $record->fonte_dado, $record->confianca_dado,
                        ];
                    })->values()->all();

                    return [
                        'timestamp' => $timestamp->format('Y-m-d H:00'),
                        'label' => $timestamp->format('d/m H:00'),
                        'data' => $data,
                    ];
                })->values()->all();
        });

        return response()->json(['timeline' => $timeline]);
    }
}
