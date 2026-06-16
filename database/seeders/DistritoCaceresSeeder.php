<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DistritoCaceresSeeder extends Seeder
{
    private function generateOrganicPolygon($centerLat, $centerLng, $baseRadius = 0.001) {
        $points = [];
        $numPoints = random_int(8, 14); // 8 to 14 points for an organic look
        for ($i = 0; $i < $numPoints; $i++) {
            $angle = ($i / $numPoints) * 2 * pi();
            // Adiciona variação de até 40% no raio para criar irregularidades (formato orgânico)
            $radius = $baseRadius * (1 + (mt_rand(-40, 40) / 100)); 
            
            // Fator de correção simples para visualização (1 grau de longitude é menor no sul)
            $lat = $centerLat + ($radius * sin($angle));
            $lng = $centerLng + ($radius * cos($angle) * 1.05); 
            $points[] = [$lng, $lat];
        }
        // Fecha o polígono conectando o último ponto ao primeiro
        $points[] = $points[0];
        
        return [
            "type" => "Polygon",
            "coordinates" => [$points]
        ];
    }

    public function run(): void
    {
        $bairros = [
            ['nome' => 'Centro', 'lat' => -16.0711, 'lng' => -57.6780],
            ['nome' => 'Cavalhada', 'lat' => -16.0740, 'lng' => -57.6850],
            ['nome' => 'Cidade Alta', 'lat' => -16.0650, 'lng' => -57.6700],
            ['nome' => 'Vila Irene', 'lat' => -16.0600, 'lng' => -57.6650],
            ['nome' => 'Cohab Nova', 'lat' => -16.0680, 'lng' => -57.6900],
            ['nome' => 'Cohab Velha', 'lat' => -16.0640, 'lng' => -57.6850],
            ['nome' => 'DNER', 'lat' => -16.0800, 'lng' => -57.6900],
            ['nome' => 'Santa Cruz', 'lat' => -16.0600, 'lng' => -57.6800],
            ['nome' => 'Jardim Celeste', 'lat' => -16.0500, 'lng' => -57.6700],
            ['nome' => 'São Luiz', 'lat' => -16.0550, 'lng' => -57.6600],
            ['nome' => 'Jardim Paraíso', 'lat' => -16.0450, 'lng' => -57.6650],
            ['nome' => 'Vila Mariana', 'lat' => -16.0500, 'lng' => -57.6850],
        ];

        foreach ($bairros as $b) {
            $geojson = $this->generateOrganicPolygon($b['lat'], $b['lng']);

            \App\Models\Distrito::create([
                'nome' => $b['nome'],
                'cidade' => 'Cáceres',
                'latitude' => $b['lat'],
                'longitude' => $b['lng'],
                'geojson' => $geojson
            ]);
        }
    }
}
