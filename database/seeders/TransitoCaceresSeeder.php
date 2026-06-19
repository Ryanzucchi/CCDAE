<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Distrito;
use App\Models\ViaTransito;

class TransitoCaceresSeeder extends Seeder
{
    public function run(): void
    {
        $distritos = Distrito::all();
        
        $viasGeradas = 0;
        
        foreach ($distritos as $distrito) {
            // Verifica se ja tem vias neste distrito
            if (ViaTransito::where('distrito_id', $distrito->id)->exists()) {
                $this->command->info("Vias já geradas para: " . $distrito->nome);
                continue;
            }

            // Gerar 3 a 7 vias principais para o distrito baseadas no centro do distrito
            $qtdVias = rand(3, 7);
            
            for ($i = 0; $i < $qtdVias; $i++) {
                $lat1 = $distrito->latitude + (mt_rand(-50, 50) / 10000);
                $lng1 = $distrito->longitude + (mt_rand(-50, 50) / 10000);
                $lat2 = $lat1 + (mt_rand(-20, 20) / 10000);
                $lng2 = $lng1 + (mt_rand(-20, 20) / 10000);

                // LineString GeoJSON
                $geojson = [
                    "type" => "LineString",
                    "coordinates" => [
                        [$lng1, $lat1],
                        [$lng2, $lat2]
                    ]
                ];

                $nivel = collect(['livre', 'livre', 'livre', 'moderado', 'moderado', 'intenso', 'parado'])->random();
                $velocidade = 0;
                if ($nivel == 'livre') $velocidade = rand(40, 60);
                if ($nivel == 'moderado') $velocidade = rand(20, 40);
                if ($nivel == 'intenso') $velocidade = rand(10, 20);
                if ($nivel == 'parado') $velocidade = rand(0, 5);

                $impacto = 'baixo';
                if ($nivel == 'intenso') $impacto = 'medio';
                if ($nivel == 'parado') $impacto = 'alto';

                ViaTransito::create([
                    'distrito_id' => $distrito->id,
                    'nome' => 'Rua/Avenida ' . fake()->firstName(),
                    'geojson' => $geojson,
                    'nivel_congestionamento' => $nivel,
                    'velocidade_media' => $velocidade,
                    'volume_veiculos' => rand(100, 2000),
                    'impacto_manutencao' => $impacto,
                    'ultima_atualizacao' => now(),
                ]);
                $viasGeradas++;
            }
        }
        
        if ($viasGeradas > 0) {
            $this->command->info("Foram semeadas $viasGeradas vias de transito.");
        }
    }
}
