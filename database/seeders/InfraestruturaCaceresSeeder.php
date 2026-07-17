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
        $this->command->info('Apagando infraestrutura antiga (preservando ruas)...');
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

        $this->command->info('Reconstruindo malha com rigor espacial (Aguarde alguns minutos)...');

        $allPostesSpatial = []; 
        $centralCriada = false;

        // Contadores para otimizacao de memoria
        $postesBuffer = [];
        $cabosBuffer = [];
        $equipamentosBuffer = [];

        activity()->withoutLogs(function () use ($vias, &$allPostesSpatial, &$centralCriada) {
            foreach ($vias as $via) {
                $coords = $this->extractCoordinates($via);
                if (empty($coords)) continue;

                $pontos = $this->generatePointsAlongLine($coords, 25);
                $postesNaVia = [];

                foreach ($pontos as $index => $pt) {
                    $lado = $index % 2 == 0 ? 'Direito' : 'Esquerdo';

                    static $posteCounter = 0;
                    $posteCounter++;
                    $poste = Poste::create([
                        'distrito_id' => $via->distrito_id,
                        'codigo_patrimonio' => 'P-' . strtoupper(Str::random(5)) . '-' . $posteCounter,
                        'material' => collect(['concreto', 'concreto', 'madeira', 'ferro', 'fibra'])->random(),
                        'altura_metros' => rand(9, 12),
                        'resistencia_kg' => rand(300, 600),
                        'possui_iluminacao' => ($index % 2 == 0),
                        'latitude' => $pt[1],
                        'longitude' => $pt[0],
                        'observacoes' => json_encode([
                            'via' => $via->nome,
                            'numero' => $index + 1,
                            'lado' => $lado
                        ])
                    ]);
                    $postesNaVia[] = $poste;

                    // Indexar poste espacialmente (grid de ~100m)
                    $gridKey = round($pt[1], 3) . '_' . round($pt[0], 3);
                    if (!isset($allPostesSpatial[$gridKey])) $allPostesSpatial[$gridKey] = [];
                    $allPostesSpatial[$gridKey][] = $poste;

                    // CABEAMENTO: Somente entre poste atual e anterior DA MESMA RUA
                    // Isso garante 100% que o cabo segue o traçado da via e nunca corta quadras.
                    if ($index > 0) {
                        // Backbone em Avenidas Principais (simulado por via mais longas ou nome)
                        $isAvenida = stripos($via->nome, 'Av') !== false;
                        $tipoFibra = $isAvenida ? 'fibra_backbone' : 'fibra_optica';

                        $this->criarCabo($postesNaVia[$index - 1], $poste, 'eletrico_baixa_tensao', 'Rede Secundária (Via)');
                        $this->criarCabo($postesNaVia[$index - 1], $poste, $tipoFibra, 'Cabo Óptico (Via)');
                    }

                    // EQUIPAMENTOS

                    // 1. Medidores e Ligações Residenciais (cada poste atende as casas da frente)
                    $numCasas = rand(1, 4);
                    for ($c = 0; $c < $numCasas; $c++) {
                        EquipamentoInfraestrutura::create([
                            'poste_id' => $poste->id,
                            'nome' => 'Medidor ' . Str::random(4),
                            'tipo' => 'Medidor',
                            'latitude' => $pt[1] + (mt_rand(-5,5)/100000), // recuo da casa
                            'longitude' => $pt[0] + (mt_rand(-5,5)/100000),
                            'observacoes' => json_encode(['consumo' => rand(100,500) . 'kWh'])
                        ]);
                    }

                    // 2. Transformador (Aprox 1 a cada 12 postes = 3 a 4 quadras)
                    if ($index > 0 && $index % 12 == 0) {
                        EquipamentoInfraestrutura::create([
                            'poste_id' => $poste->id,
                            'nome' => 'Transformador ' . rand(30, 112) . 'kVA',
                            'tipo' => 'Transformador',
                        ]);
                    }

                    // 3. CTO (Aprox 1 a cada 4 postes = atende ~12 residencias)
                    if ($index > 0 && $index % 4 == 0) {
                        EquipamentoInfraestrutura::create([
                            'poste_id' => $poste->id,
                            'nome' => 'CTO Óptica 16',
                            'tipo' => 'CTO',
                            'observacoes' => json_encode(['ocupacao' => rand(2,16)])
                        ]);
                    }

                    // 4. Caixa de Emenda e Armários
                    if ($index > 0 && $index % 8 == 0) {
                        EquipamentoInfraestrutura::create(['poste_id' => $poste->id, 'nome' => 'CEO - Caixa de Emenda', 'tipo' => 'Caixa_Emenda']);
                    }
                    if ($index > 0 && $index % 15 == 0) {
                        EquipamentoInfraestrutura::create(['poste_id' => $poste->id, 'nome' => 'Armário Telefônico', 'tipo' => 'Telefonia']);
                    }
                }

                // CRIAR CENTRAL E POP
                if (count($postesNaVia) > 0) {
                    if (!$centralCriada) {
                        $centralCriada = true;
                        $primeiro = $postesNaVia[0];
                        $subestacao = CentralDistribuicao::create([
                            'nome' => 'Subestação Principal Elétrica',
                            'tipo' => 'Energia',
                            'latitude' => $primeiro->latitude + 0.0002, // Fora da rua (terreno próprio)
                            'longitude' => $primeiro->longitude + 0.0002,
                        ]);
                        $datacenter = CentralDistribuicao::create([
                            'nome' => 'DataCenter / Central Telecom',
                            'tipo' => 'Telecom',
                            'latitude' => $primeiro->latitude + 0.0002,
                            'longitude' => $primeiro->longitude - 0.0002,
                        ]);
                        // Liga centrais ao poste principal (entrada na malha viária)
                        $this->criarCabo($subestacao, $primeiro, 'eletrico_alta_tensao', 'Alimentador Primário');
                        $this->criarCabo($datacenter, $primeiro, 'fibra_backbone', 'Saída Datacenter');
                    }

                    // Distribui um POP aleatoriamente (fora da rua)
                    if (rand(1, 40) == 1) {
                        $popPoste = $postesNaVia[array_rand($postesNaVia)];
                        $pop = CentralDistribuicao::create([
                            'nome' => 'POP Telecom ' . Str::random(3),
                            'tipo' => 'POP',
                            'latitude' => $popPoste->latitude - 0.0001,
                            'longitude' => $popPoste->longitude - 0.0001,
                        ]);
                        $this->criarCabo($pop, $popPoste, 'fibra_backbone', 'Ligação POP -> Poste');
                    }
                }

                // CONEXÃO DE CRUZAMENTOS (Garante que a rede vira um Grafo Único sem linhas retas isoladas)
                // Ligar as pontas da rua aos postes próximos (<40m) de outras ruas
                if (count($postesNaVia) > 0) {
                    $this->conectarCruzamento($postesNaVia[0], $allPostesSpatial);
                    if (count($postesNaVia) > 1) {
                        $this->conectarCruzamento($postesNaVia[count($postesNaVia)-1], $allPostesSpatial);
                    }
                }
            }
        });
    }

    private function conectarCruzamento($posteA, $grid) {
        $key = round($posteA->latitude, 3) . '_' . round($posteA->longitude, 3);
        if (!isset($grid[$key])) return;
        
        $closest = null;
        $minD = 40; // max 40 metros para considerar um cruzamento de ruas
        
        foreach ($grid[$key] as $posteB) {
            if ($posteB->id === $posteA->id || abs($posteA->id - $posteB->id) <= 2) continue; 
            
            $d = $this->distance($posteA->latitude, $posteA->longitude, $posteB->latitude, $posteB->longitude);
            if ($d < $minD) {
                $minD = $d;
                $closest = $posteB;
            }
        }
        
        if ($closest) {
            $this->criarCabo($posteA, $closest, 'eletrico_baixa_tensao', 'Travessia de Cruzamento');
            $this->criarCabo($posteA, $closest, 'fibra_optica', 'Travessia de Cruzamento');
        }
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
            'nome' => $nome,
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
        if ($geo['type'] === 'LineString') return $geo['coordinates'];
        if ($geo['type'] === 'MultiLineString') return $geo['coordinates'][0] ?? [];
        return [];
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
                
                $currentLat += ($p2[1] - $p1[1]) * $ratio;
                $currentLon += ($p2[0] - $p1[0]) * $ratio;
                
                // Deslocamento perpendicular para alternar calçadas
                $shift = 0.00003; // ~3 metros
                $dx = $p2[0] - $p1[0];
                $dy = $p2[1] - $p1[1];
                $len = sqrt($dx*$dx + $dy*$dy);
                $finalLat = $currentLat;
                $finalLon = $currentLon;
                if ($len > 0) {
                    $nx = -$dy / $len;
                    $ny = $dx / $len;
                    if (count($points) % 2 == 0) {
                        $finalLon += $nx * $shift;
                        $finalLat += $ny * $shift;
                    } else {
                        $finalLon -= $nx * $shift;
                        $finalLat -= $ny * $shift;
                    }
                }
                
                $points[] = [$finalLon, $finalLat];
                $remainingSegment -= $walk;
                $accumulatedDistance = 0;
            }
            $accumulatedDistance += $remainingSegment;
        }
        
        if (empty($points) && count($coordinates) > 0) $points[] = $coordinates[0];
        
        return $points;
    }
}
