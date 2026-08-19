<?php

namespace App\Services\Infrastructure;

use App\Models\Antena;
use App\Models\Cabeamento;
use App\Models\CentralDistribuicao;
use App\Models\EquipamentoInfraestrutura;
use App\Models\Poste;
use App\Models\ViaTransito;
use Illuminate\Support\Facades\DB;

class InfrastructureQueryService
{
    public function query(?int $districtId, bool $includeFeatures = true, bool $energyOnly = false, bool $energyOverview = false): array
    {
        $bounds = null;

        if (! $includeFeatures && $districtId && DB::getDriverName() === 'pgsql') {
            $bounds = $this->queryDistrictBounds($districtId);
        }

        $vias = [];
        $centrais = [];

        if ($includeFeatures) {
            $vias = ViaTransito::when($districtId, fn ($q) => $q->where('distrito_id', $districtId))
                ->limit(5000)
                ->get(['id', 'geojson', 'nome', 'nivel_congestionamento', 'velocidade_media']);

            $centrais = CentralDistribuicao::when($districtId, fn ($q) => $q->where('distrito_id', $districtId))
                ->limit(500)
                ->get(['id', 'latitude', 'longitude', 'geojson', 'nome', 'tipo', 'estado_conservacao']);
        }

        $cabeamentos = [];
        $postes = [];
        $antenas = [];
        $equipamentos = [];

        if ($includeFeatures && ($districtId || $energyOverview)) {
            $cabeamentos = Cabeamento::where(function ($query) use ($districtId) {
                if (! $districtId) {
                    return;
                }
                $query->where('distrito_id', $districtId)
                    ->orWhereHas('posteOrigem', fn ($poste) => $poste->where('distrito_id', $districtId))
                    ->orWhereHas('posteDestino', fn ($poste) => $poste->where('distrito_id', $districtId))
                    ->orWhereHas('centralOrigem', fn ($central) => $central->where('distrito_id', $districtId))
                    ->orWhereHas('centralDestino', fn ($central) => $central->where('distrito_id', $districtId));
            })->when($energyOnly, fn ($query) => $query->whereIn('tipo_cabo', ['eletrico_baixa_tensao', 'eletrico_alta_tensao']))
                ->when($energyOverview, fn ($query) => $query->whereRaw('1 = 0'))
                ->limit(40000)
                ->get([
                    'id', 'geojson', 'nome', 'codigo_patrimonio', 'tipo_cabo', 'capacidade',
                    'revestimento', 'subterraneo', 'extensao_metros', 'estado_conservacao', 'distrito_id',
                    'poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id',
                ]);

            $postes = Poste::when($districtId, fn ($query) => $query->where('distrito_id', $districtId))
                ->limit($energyOnly ? 40000 : 20000)
                ->get([
                    'id', 'latitude', 'longitude', 'codigo_patrimonio', 'material',
                    'estado_conservacao', 'altura_metros', 'resistencia_kg',
                    'possui_iluminacao', 'data_instalacao', 'ultima_manutencao',
                ]);

            $antenas = $energyOnly ? collect() : Antena::where('distrito_id', $districtId)
                ->limit(1000)
                ->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'tipo_sinal', 'estado_conservacao']);

            $equipamentos = EquipamentoInfraestrutura::when($districtId, fn ($query) => $query->where('distrito_id', $districtId))
                ->when(
                    $energyOverview,
                    fn ($query) => $query->whereRaw('LOWER(tipo) = ?', ['transformador']),
                    fn ($query) => $query->when($energyOnly, fn ($energyQuery) => $energyQuery->whereIn(DB::raw('LOWER(tipo)'), ['transformador', 'medidor'])),
                )
                ->limit(20000)
                ->get([
                    'id', 'latitude', 'longitude', 'nome', 'codigo_patrimonio',
                    'tipo', 'modelo', 'estado_conservacao', 'poste_id', 'central_id',
                    'data_instalacao', 'ultima_manutencao',
                ]);
        }

        return [
            'cabeamentos' => $cabeamentos,
            'vias' => $vias,
            'postes' => $postes,
            'antenas' => $antenas,
            'centrais' => $centrais,
            'equipamentos' => $equipamentos,
            'meta' => [
                'distrito_id' => $districtId ? (int) $districtId : null,
                'features_included' => $includeFeatures,
                'bounds' => $bounds,
            ],
        ];
    }

    private function queryDistrictBounds(int $districtId): ?array
    {
        $extent = DB::selectOne(<<<'SQL'
            SELECT ST_XMin(extent) AS min_x,
                   ST_YMin(extent) AS min_y,
                   ST_XMax(extent) AS max_x,
                   ST_YMax(extent) AS max_y
            FROM (
                SELECT ST_Extent(geom) AS extent
                FROM distritos
                WHERE id = ? AND geom IS NOT NULL
            ) district_bounds
            SQL, [$districtId]);

        if ($extent?->min_x !== null) {
            return [
                [(float) $extent->min_x, (float) $extent->min_y],
                [(float) $extent->max_x, (float) $extent->max_y],
            ];
        }

        return null;
    }
}
