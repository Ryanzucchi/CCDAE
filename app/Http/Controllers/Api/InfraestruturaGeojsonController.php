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

class InfraestruturaGeojsonController extends Controller
{
    public function __invoke(Request $request)
    {
        $distrito_id = $request->query('distrito_id');

        // Sempre retornamos vias e centrais
        $vias = ViaTransito::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(5000)->get(['id', 'geojson', 'nome', 'nivel_congestionamento', 'velocidade_media']);

        $centrais = CentralDistribuicao::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(500)->get(['id', 'latitude', 'longitude', 'geojson', 'nome', 'tipo', 'estado_conservacao']);

        // Camadas de detalhes da infraestrutura física são carregadas apenas por distrito
        $cabeamentos = [];
        $postes = [];
        $antenas = [];
        $equipamentos = [];

        if ($distrito_id) {
            $cabeamentos = Cabeamento::where('distrito_id', $distrito_id)
                ->limit(20000)->get(['id', 'geojson', 'nome', 'tipo_cabo', 'estado_conservacao', 'distrito_id', 'poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id']);

            $postes = Poste::where('distrito_id', $distrito_id)
                ->limit(20000)->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'material', 'estado_conservacao', 'altura_metros']);

            $antenas = Antena::where('distrito_id', $distrito_id)
                ->limit(1000)->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'tipo_sinal', 'estado_conservacao']);

            $equipamentos = EquipamentoInfraestrutura::where('distrito_id', $distrito_id)
                ->limit(20000)->get(['id', 'latitude', 'longitude', 'nome', 'tipo', 'estado_conservacao', 'poste_id', 'central_id']);
        }

        return response()->json([
            'cabeamentos' => $cabeamentos,
            'vias' => $vias,
            'postes' => $postes,
            'antenas' => $antenas,
            'centrais' => $centrais,
            'equipamentos' => $equipamentos,
        ]);
    }
}
