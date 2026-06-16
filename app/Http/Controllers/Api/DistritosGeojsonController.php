<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Distrito;

class DistritosGeojsonController extends Controller
{
    public function __invoke(Request $request)
    {
        $distritos = Distrito::whereNotNull('geojson')->get(['id', 'nome', 'geojson']);
        
        $features = [];
        foreach ($distritos as $distrito) {
            $geo = is_string($distrito->geojson) ? json_decode($distrito->geojson, true) : $distrito->geojson;
            
            if (isset($geo['type']) && $geo['type'] !== 'Feature') {
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $distrito->id,
                        'nome' => $distrito->nome,
                    ],
                    'geometry' => $geo
                ];
            } else {
                if (isset($geo['properties'])) {
                    $geo['properties']['id'] = $distrito->id;
                    $geo['properties']['nome'] = $distrito->nome;
                } else {
                    $geo['properties'] = [
                        'id' => $distrito->id,
                        'nome' => $distrito->nome,
                    ];
                }
                $features[] = $geo;
            }
        }
        
        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features
        ]);
    }
}
