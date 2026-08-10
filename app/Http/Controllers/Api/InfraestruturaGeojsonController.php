<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Poste;
use App\Models\Antena;
use App\Models\CentralDistribuicao;
use App\Models\EquipamentoInfraestrutura;
use App\Models\Cabeamento;
use App\Models\ViaTransito;
use Illuminate\Support\Facades\DB;

class InfraestruturaGeojsonController extends Controller
{
    public function __invoke(Request $request)
    {
        $distrito_id = $request->query('distrito_id');
        $includeFeatures = $request->boolean('include_features', true);
        $bounds = null;

        if (! $includeFeatures && $distrito_id && DB::getDriverName() === 'pgsql') {
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
                SQL, [$distrito_id]);

            if ($extent?->min_x !== null) {
                $bounds = [
                    [(float) $extent->min_x, (float) $extent->min_y],
                    [(float) $extent->max_x, (float) $extent->max_y],
                ];
            }
        }

        $vias = [];
        $centrais = [];

        if ($includeFeatures) {
            $vias = ViaTransito::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
                ->limit(5000)->get(['id', 'geojson', 'nome', 'nivel_congestionamento', 'velocidade_media']);

            $centrais = CentralDistribuicao::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
                ->limit(500)->get(['id', 'latitude', 'longitude', 'geojson', 'nome', 'tipo', 'estado_conservacao']);
        }

        // Camadas de detalhes da infraestrutura física são carregadas apenas por distrito
        $cabeamentos = [];
        $postes = [];
        $antenas = [];
        $equipamentos = [];

        if ($includeFeatures && $distrito_id) {
            $cabeamentos = Cabeamento::where(function ($query) use ($distrito_id) {
                $query->where('distrito_id', $distrito_id)
                    ->orWhereHas('posteOrigem', fn ($poste) => $poste->where('distrito_id', $distrito_id))
                    ->orWhereHas('posteDestino', fn ($poste) => $poste->where('distrito_id', $distrito_id))
                    ->orWhereHas('centralOrigem', fn ($central) => $central->where('distrito_id', $distrito_id))
                    ->orWhereHas('centralDestino', fn ($central) => $central->where('distrito_id', $distrito_id));
            })
                ->limit(20000)->get(['id', 'geojson', 'nome', 'tipo_cabo', 'estado_conservacao', 'distrito_id', 'poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id']);

            $postes = Poste::where('distrito_id', $distrito_id)
                ->limit(20000)->get([
                    'id', 'latitude', 'longitude', 'codigo_patrimonio', 'material',
                    'estado_conservacao', 'altura_metros', 'resistencia_kg',
                    'possui_iluminacao', 'data_instalacao', 'ultima_manutencao',
                ]);

            $antenas = Antena::where('distrito_id', $distrito_id)
                ->limit(1000)->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'tipo_sinal', 'estado_conservacao']);

            $equipamentos = EquipamentoInfraestrutura::where('distrito_id', $distrito_id)
                ->limit(20000)->get([
                    'id', 'latitude', 'longitude', 'nome', 'codigo_patrimonio',
                    'tipo', 'modelo', 'estado_conservacao', 'poste_id', 'central_id',
                    'data_instalacao', 'ultima_manutencao',
                ]);
        }

        return response()->json([
            'cabeamentos' => $cabeamentos,
            'vias' => $vias,
            'postes' => $postes,
            'antenas' => $antenas,
            'centrais' => $centrais,
            'equipamentos' => $equipamentos,
            'meta' => [
                'distrito_id' => $distrito_id ? (int) $distrito_id : null,
                'features_included' => $includeFeatures,
                'bounds' => $bounds,
            ],
        ]);
    }
}
