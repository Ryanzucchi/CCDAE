<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Distrito;
use App\Models\Poste;
use App\Models\CentralDistribuicao;
use App\Models\EquipamentoInfraestrutura;
use App\Models\Cabeamento;
use App\Models\ViaTransito;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class InfraestruturaCaceresSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Limpando infraestrutura antiga...');
        DB::statement('SET session_replication_role = replica;');
        Cabeamento::truncate();
        EquipamentoInfraestrutura::truncate();
        Poste::truncate();
        CentralDistribuicao::truncate();
        DB::statement('SET session_replication_role = DEFAULT;');

        $vias = ViaTransito::whereNotNull('geojson')->get();

        if ($vias->isEmpty()) {
            $this->command->warn('Nenhuma via de trânsito encontrada.');
            return;
        }

        $this->command->info('Gerando Infraestrutura baseada em ' . $vias->count() . ' vias...');

        // 1. Criar Central de Internet e Subestação Elétrica (1 de cada para a cidade, fora da rua)
        $primeiraVia = $vias->first();
        $baseCoord = $this->getFirstCoord($primeiraVia);
        $latCentral = $baseCoord[1] + 0.005; 
        $lngCentral = $baseCoord[0] + 0.005;

        $centralEletrica = CentralDistribuicao::create([
            'nome' => 'Subestação Elétrica Principal',
            'codigo_patrimonio' => 'SUB-01',
            'tipo' => 'Energia',
            'capacidade' => '138kV',
            'latitude' => $latCentral,
            'longitude' => $lngCentral,
        ]);

        $centralInternet = CentralDistribuicao::create([
            'nome' => 'Datacenter / Central Telecom',
            'codigo_patrimonio' => 'DC-01',
            'tipo' => 'Telecom',
            'capacidade' => '100Gbps',
            'latitude' => $latCentral + 0.001,
            'longitude' => $lngCentral + 0.001,
        ]);

        $pops = [];
        $ctos = [];

        foreach ($vias as $via) {
            $coords = $this->extractCoordinates($via);
            if (empty($coords)) continue;

            $pontos = $this->generatePointsAlongLine($coords, 25);
            $postesNaVia = [];

            // A cada aprox 20 vias, criar um POP
            if (rand(1, 20) == 1 || empty($pops)) {
                $popLat = $pontos[0][1] + 0.0002;
                $popLng = $pontos[0][0] + 0.0002;
                $pop = CentralDistribuicao::create([
                    'nome' => 'POP Telecom ' . Str::random(3),
                    'codigo_patrimonio' => 'POP-' . Str::random(5),
                    'tipo' => 'POP',
                    'latitude' => $popLat,
                    'longitude' => $popLng,
                    'distrito_id' => $via->distrito_id,
                ]);
                $pops[] = $pop;
                
                // Conectar POP ao Datacenter (Backbone)
                $this->criarCabo($centralInternet, $pop, 'fibra_optica', 'Backbone');
            }

            foreach ($pontos as $index => $pt) {
                // Alternar lado da rua
                $lado = $index % 2 == 0 ? 'Direito' : 'Esquerdo';

                $poste = Poste::create([
                    'distrito_id' => $via->distrito_id,
                    'codigo_patrimonio' => 'P-' . strtoupper(Str::random(6)),
                    'material' => collect(['concreto', 'concreto', 'madeira', 'ferro', 'fibra'])->random(),
                    'altura_metros' => rand(9, 12),
                    'resistencia_kg' => rand(300, 600),
                    'possui_iluminacao' => ($index % 2 == 0),
                    'latitude' => $pt[1],
                    'longitude' => $pt[0],
                    'observacoes' => json_encode([
                        'rua' => $via->nome,
                        'numero_sequencial' => $index + 1,
                        'lado_rua' => $lado,
                        'fases' => collect(['A', 'B', 'C', 'AB', 'ABC'])->random(),
                        'tensao' => '13.8kV / 220V',
                    ])
                ]);
                $postesNaVia[] = $poste;

                // Conectar ao poste anterior
                if ($index > 0) {
                    $this->criarCabo($postesNaVia[$index - 1], $poste, 'eletrico_baixa_tensao', 'Rede Secundária');
                    $this->criarCabo($postesNaVia[$index - 1], $poste, 'fibra_optica', 'Rede de Distribuição Óptica');
                }

                // Gerar Medidores
                $numCasas = rand(1, 4);
                for ($c = 0; $c < $numCasas; $c++) {
                    EquipamentoInfraestrutura::create([
                        'poste_id' => $poste->id,
                        'nome' => 'Medidor de Energia Residencial',
                        'tipo' => 'Medidor',
                        'observacoes' => json_encode([
                            'consumo_medio_kwh' => rand(100, 500),
                            'responsavel' => 'Imóvel ' . Str::random(4),
                            'fase' => collect(['A', 'B', 'C'])->random()
                        ])
                    ]);
                }

                // Transformador a cada ~12 postes
                if ($index % 12 == 0) {
                    EquipamentoInfraestrutura::create([
                        'poste_id' => $poste->id,
                        'nome' => 'Transformador ' . rand(15, 112) . 'kVA',
                        'tipo' => 'Transformador',
                        'observacoes' => json_encode([
                            'carga_maxima' => '112kVA',
                            'capacidade_utilizada' => rand(40, 90) . '%',
                            'bairros_atendidos' => [$via->distrito->nome ?? 'Centro'],
                            'circuito' => 'C-' . rand(1, 10)
                        ])
                    ]);
                    // Conecta Transformador à Subestação (Alta Tensão)
                    $this->criarCabo($centralEletrica, $poste, 'eletrico_alta_tensao', 'Alimentador Primário');
                }

                // CTO a cada ~4 postes
                if ($index % 4 == 0) {
                    $cto = EquipamentoInfraestrutura::create([
                        'poste_id' => $poste->id,
                        'nome' => 'CTO Óptica 16 portas',
                        'tipo' => 'CTO',
                        'observacoes' => json_encode([
                            'clientes_conectados' => rand(2, 16),
                            'velocidade_maxima' => '1000Mbps',
                            'provedor' => collect(['Provedor A', 'Provedor B', 'Nacional'])->random()
                        ])
                    ]);
                    $ctos[] = $cto;
                }

                // Caixa de Emenda
                if ($index > 0 && $index % 8 == 0) {
                    EquipamentoInfraestrutura::create([
                        'poste_id' => $poste->id,
                        'nome' => 'Caixa de Emenda Óptica (CEO)',
                        'tipo' => 'Caixa_Emenda',
                        'observacoes' => json_encode([
                            'fibras_totais' => 144,
                            'fibras_ocupadas' => rand(12, 100)
                        ])
                    ]);
                }
                
                // Telefonia
                if ($index % 10 == 0) {
                    EquipamentoInfraestrutura::create([
                        'poste_id' => $poste->id,
                        'nome' => 'Armário Telefônico',
                        'tipo' => 'Telefonia',
                        'observacoes' => json_encode([
                            'pares_disponiveis' => rand(10, 50),
                        ])
                    ]);
                }
            }

            // Conectar CTOs aos POPs
            if (count($pops) > 0 && count($ctos) > 0) {
                $lastPop = $pops[array_rand($pops)];
                foreach ($ctos as $cto) {
                    $this->criarCabo($lastPop, $cto->poste, 'fibra_optica', 'Rede de Alimentação CTO');
                }
                $ctos = []; // Reseta pra proxima rua
            }
        }
        
        $this->command->info('Infraestrutura gerada com sucesso respeitando a malha viária!');
    }

    private function criarCabo($origem, $destino, $tipoCabo, $nome)
    {
        $origemCol = $origem instanceof CentralDistribuicao ? 'central_origem_id' : 'poste_origem_id';
        $destinoCol = $destino instanceof CentralDistribuicao ? 'central_destino_id' : 'poste_destino_id';

        $lat1 = $origem->latitude;
        $lon1 = $origem->longitude;
        $lat2 = $destino->latitude;
        $lon2 = $destino->longitude;
        
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return;

        $geojson = [
            'type' => 'LineString',
            'coordinates' => [
                [(float)$lon1, (float)$lat1],
                [(float)$lon2, (float)$lat2]
            ]
        ];

        Cabeamento::create([
            $origemCol => $origem->id,
            $destinoCol => $destino->id,
            'nome' => $nome . ' ' . Str::random(4),
            'tipo_cabo' => $tipoCabo,
            'extensao_metros' => $this->distance($lat1, $lon1, $lat2, $lon2),
            'geojson' => $geojson,
            'estado_conservacao' => 'bom',
            'data_instalacao' => now(),
        ]);
    }

    private function distance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) * sin($latDelta / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) * sin($lonDelta / 2);
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function extractCoordinates($via) {
        $geo = $via->geojson;
        if (!$geo || !isset($geo['type'])) return [];
        
        if ($geo['type'] === 'LineString') {
            return $geo['coordinates'];
        } elseif ($geo['type'] === 'MultiLineString') {
            return $geo['coordinates'][0] ?? [];
        }
        return [];
    }
    
    private function getFirstCoord($via) {
        $coords = $this->extractCoordinates($via);
        return $coords[0] ?? [-57.6836, -16.0744];
    }

    private function generatePointsAlongLine($coordinates, $interval = 25) {
        $points = [];
        $accumulatedDistance = 0;
        
        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $p1 = $coordinates[$i];
            $p2 = $coordinates[$i+1];
            
            $dist = $this->distance($p1[1], $p1[0], $p2[1], $p2[0]);
            
            $remainingSegment = $dist;
            $currentLat = $p1[1];
            $currentLon = $p1[0];
            
            while ($remainingSegment >= $interval - $accumulatedDistance) {
                $walk = $interval - $accumulatedDistance;
                $ratio = $dist > 0 ? $walk / $dist : 0; 
                
                $currentLat = $currentLat + ($p2[1] - $p1[1]) * $ratio;
                $currentLon = $currentLon + ($p2[0] - $p1[0]) * $ratio;
                
                $points[] = [$currentLon, $currentLat];
                
                $remainingSegment -= $walk;
                $accumulatedDistance = 0;
            }
            
            $accumulatedDistance += $remainingSegment;
        }
        
        if (empty($points) && count($coordinates) > 0) {
            $points[] = $coordinates[0];
        }
        
        return $points;
    }
}
