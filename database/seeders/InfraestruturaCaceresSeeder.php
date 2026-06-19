<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Distrito;
use App\Models\Poste;
use App\Models\Antena;
use App\Models\CentralDistribuicao;
use App\Models\EquipamentoInfraestrutura;
use App\Models\Cabeamento;
use Illuminate\Support\Str;

class InfraestruturaCaceresSeeder extends Seeder
{
    public function run()
    {
        // Pega todos os distritos da cidade "Cáceres"
        $distritos = Distrito::where('cidade', 'Cáceres')->get();

        if ($distritos->isEmpty()) {
            $this->command->warn('Nenhum distrito de Cáceres encontrado. Por favor rode os seeders de distritos primeiro.');
            return;
        }

        foreach ($distritos as $distrito) {
            $this->command->info('Semeando infraestrutura para o distrito: ' . $distrito->nome);
            
            // Caso o distrito não tenha lat/lng (improvável), usa um fallback proximo a caceres
            $baseLat = $distrito->latitude ?? -16.0744;
            $baseLng = $distrito->longitude ?? -57.6836;

            // 1. Central de Distribuição (Polígono)
            $latCentral = $baseLat + (mt_rand(-50, 50) / 10000);
            $lngCentral = $baseLng + (mt_rand(-50, 50) / 10000);
            
            $geojsonCentral = [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$lngCentral - 0.001, $latCentral - 0.001],
                    [$lngCentral + 0.001, $latCentral - 0.001],
                    [$lngCentral + 0.001, $latCentral + 0.001],
                    [$lngCentral - 0.001, $latCentral + 0.001],
                    [$lngCentral - 0.001, $latCentral - 0.001],
                ]]
            ];

            $central = CentralDistribuicao::create([
                'distrito_id' => $distrito->id,
                'nome' => 'Subestação ' . $distrito->nome,
                'codigo_patrimonio' => 'CENTRAL-' . strtoupper(Str::random(5)),
                'tipo' => 'Energia',
                'capacidade' => '138kV',
                'area_m2' => 5000,
                'latitude' => $latCentral,
                'longitude' => $lngCentral,
                'geojson' => $geojsonCentral,
                'estado_conservacao' => 'bom',
                'data_instalacao' => now()->subYears(10),
                'ultima_manutencao' => now()->subMonths(6),
            ]);

            // 2. Postes
            $postes = [];
            for ($i = 0; $i < 10; $i++) {
                $postes[] = Poste::create([
                    'distrito_id' => $distrito->id,
                    'codigo_patrimonio' => 'POSTE-' . strtoupper(Str::random(5)),
                    'material' => collect(['concreto', 'madeira', 'ferro'])->random(),
                    'altura_metros' => rand(7, 12),
                    'resistencia_kg' => rand(200, 600),
                    'possui_iluminacao' => (bool)rand(0, 1),
                    'latitude' => $latCentral + (mt_rand(-150, 150) / 10000), // Distribui perto da central
                    'longitude' => $lngCentral + (mt_rand(-150, 150) / 10000),
                    'estado_conservacao' => collect(['novo', 'bom', 'regular', 'ruim'])->random(),
                    'data_instalacao' => now()->subYears(rand(1, 20)),
                ]);
            }

            // 3. Antena
            $antena = Antena::create([
                'distrito_id' => $distrito->id,
                'codigo_patrimonio' => 'ANT-' . strtoupper(Str::random(5)),
                'tipo_sinal' => collect(['5G', '4G', 'Radio'])->random(),
                'frequencia_mhz' => 2600,
                'alcance_metros' => 3000,
                'potencia_dbm' => 45,
                'proprietario' => collect(['Vivo', 'Claro', 'TIM'])->random(),
                'latitude' => $baseLat + (mt_rand(-80, 80) / 10000),
                'longitude' => $baseLng + (mt_rand(-80, 80) / 10000),
                'estado_conservacao' => 'bom',
                'data_instalacao' => now()->subYears(2),
            ]);

            // 4. Equipamentos
            EquipamentoInfraestrutura::create([
                'distrito_id' => $distrito->id,
                'poste_id' => $postes[0]->id,
                'nome' => 'Transformador Trifásico',
                'codigo_patrimonio' => 'TRANSF-' . strtoupper(Str::random(5)),
                'tipo' => 'Transformador',
                'estado_conservacao' => 'regular',
                'data_instalacao' => now()->subYears(5),
            ]);
            
            EquipamentoInfraestrutura::create([
                'distrito_id' => $distrito->id,
                'latitude' => $baseLat + (mt_rand(-80, 80) / 10000),
                'longitude' => $baseLng + (mt_rand(-80, 80) / 10000),
                'nome' => 'Roteador Backbone',
                'codigo_patrimonio' => 'BACK-' . strtoupper(Str::random(5)),
                'tipo' => 'Rede',
                'estado_conservacao' => 'bom',
                'data_instalacao' => now()->subYears(1),
            ]);

            // 5. Cabeamentos (Grafo)
            // Ligar Central -> Poste 0
            $this->createCabeamento($distrito, 'eletrico_alta_tensao', $central, $postes[0]);
            
            // Ligar Poste 0 -> Poste 1, Poste 1 -> Poste 2... formando um caminho
            for ($i = 0; $i < 9; $i++) {
                $this->createCabeamento($distrito, 'eletrico_baixa_tensao', $postes[$i], $postes[$i+1]);
            }
            
            // Fazer algumas ligações extras de fibra
            $this->createCabeamento($distrito, 'fibra_optica', $postes[2], $postes[8]);
        }
    }

    private function createCabeamento($distrito, $tipo, $origem, $destino)
    {
        $origemCol = $origem instanceof CentralDistribuicao ? 'central_origem_id' : 'poste_origem_id';
        $destinoCol = $destino instanceof CentralDistribuicao ? 'central_destino_id' : 'poste_destino_id';

        $geojson = [
            'type' => 'LineString',
            'coordinates' => [
                [(float)$origem->longitude, (float)$origem->latitude],
                [(float)$destino->longitude, (float)$destino->latitude]
            ]
        ];

        $earthRadius = 6371000;
        $latFrom = deg2rad($origem->latitude);
        $lonFrom = deg2rad($origem->longitude);
        $latTo = deg2rad($destino->latitude);
        $lonTo = deg2rad($destino->longitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        $distancia = $angle * $earthRadius;

        Cabeamento::create([
            'distrito_id' => $distrito->id,
            $origemCol => $origem->id,
            $destinoCol => $destino->id,
            'nome' => 'Ligação ' . strtoupper(Str::random(4)),
            'codigo_patrimonio' => 'CABO-' . strtoupper(Str::random(5)),
            'tipo_cabo' => $tipo,
            'extensao_metros' => round($distancia, 2),
            'geojson' => $geojson,
            'estado_conservacao' => collect(['novo', 'bom', 'regular'])->random(),
            'data_instalacao' => now()->subYears(rand(1, 10)),
        ]);
    }
}
