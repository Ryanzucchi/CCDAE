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

        $cabeamentos = Cabeamento::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(15000)->get(['id', 'geojson', 'nome', 'tipo_cabo', 'estado_conservacao']);

        $vias = ViaTransito::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(5000)->get(['id', 'geojson', 'nome', 'nivel_congestionamento', 'velocidade_media']);

        $postes = Poste::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(15000)->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'material', 'estado_conservacao']);

        $antenas = Antena::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(500)->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'tipo_sinal', 'estado_conservacao']);

        $centrais = CentralDistribuicao::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->limit(500)->get(['id', 'latitude', 'longitude', 'geojson', 'nome', 'tipo', 'estado_conservacao']);

        $equipamentos = EquipamentoInfraestrutura::when($distrito_id, fn($q) => $q->where('distrito_id', $distrito_id))
            ->whereNull('poste_id')->whereNull('central_id')
            ->limit(2000)->get(['id', 'latitude', 'longitude', 'nome', 'tipo', 'estado_conservacao']);

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
