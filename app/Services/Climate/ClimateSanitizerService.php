<?php

namespace App\Services\Climate;

use App\Models\RegiaoClimatica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClimateSanitizerService
{
    /**
     * Sanitiza os dados climáticos, agrupando distritos vizinhos com leituras idênticas
     * em Regiões Climáticas temporais (Time Slices) e apagando os dados redundantes.
     */
    public function sanitize()
    {
        Log::info("Iniciando sanitização climática e agrupamento temporal...");

        // 1. Obter adjacências (grafo de vizinhos) via PostGIS
        $adjacencies = $this->getAdjacencies();

        // 2. Buscar leituras idênticas (baseado em temperatura como proxy principal do payload da estação)
        // Agrupa distritos que tiveram a MESMA temperatura no MESMO segundo.
        $identicalReadings = DB::select("
            SELECT timestamp, temperatura, array_agg(distrito_id) as distritos
            FROM temperatura_registrada
            GROUP BY timestamp, temperatura
            HAVING count(distrito_id) > 1
            ORDER BY timestamp ASC
        ");

        $activeClusters = [];
        $completedClusters = [];

        foreach ($identicalReadings as $reading) {
            $timestamp = $reading->timestamp;
            $distritosRaw = trim($reading->distritos, '{}');
            if (empty($distritosRaw)) continue;
            
            $distritosIds = explode(',', $distritosRaw);

            // 3. Encontrar componentes conectados (sub-grupos que realmente são vizinhos)
            $components = $this->getConnectedComponents($distritosIds, $adjacencies);

            // Array para rastrear quais clusters ativos continuaram neste timestamp
            $continuedKeys = [];

            foreach ($components as $comp) {
                if (count($comp) < 2) continue; // Precisa de pelo menos 2 para agrupar
                
                sort($comp);
                $clusterKey = implode(',', $comp);
                $continuedKeys[] = $clusterKey;

                if (!isset($activeClusters[$clusterKey])) {
                    // Novo cluster ativo (início da ilha de similaridade)
                    $activeClusters[$clusterKey] = [
                        'start' => $timestamp,
                        'last'  => $timestamp,
                        'count' => 1,
                        'distritos' => $comp
                    ];
                } else {
                    // Cluster continua igual (mesma temperatura) neste timestamp
                    $activeClusters[$clusterKey]['last'] = $timestamp;
                    $activeClusters[$clusterKey]['count']++;
                }
            }

            // 4. Fechar clusters que pararam de combinar (não estão em $continuedKeys)
            foreach ($activeClusters as $key => $data) {
                if (!in_array($key, $continuedKeys)) {
                    // A "ilha" quebrou. Se durou pelo menos 2 leituras, salva.
                    if ($data['count'] >= 2) {
                        $completedClusters[] = $data;
                    }
                    unset($activeClusters[$key]);
                }
            }
        }

        // Fechar os que restaram abertos no final do loop
        foreach ($activeClusters as $key => $data) {
            if ($data['count'] >= 2) {
                $completedClusters[] = $data;
            }
        }

        // 5. Processar os clusters completados (Time Slices confirmados)
        $totalRedundantDeleted = 0;

        foreach ($completedClusters as $cluster) {
            $deletedCount = $this->saveAndSanitizeCluster($cluster);
            $totalRedundantDeleted += $deletedCount;
        }

        Log::info("Sanitização concluída. Registros redundantes deletados: {$totalRedundantDeleted}");
        return $totalRedundantDeleted;
    }

    /**
     * Retorna um grafo de adjacência [id => [vizinho_id, vizinho_id]] usando ST_Touches
     */
    private function getAdjacencies(): array
    {
        $rows = DB::select("
            SELECT a.id as d1, b.id as d2 
            FROM distritos a, distritos b 
            WHERE a.id < b.id 
            AND ST_Touches(
                ST_SetSRID(ST_GeomFromGeoJSON(a.geojson::text), 4326), 
                ST_SetSRID(ST_GeomFromGeoJSON(b.geojson::text), 4326)
            )
        ");

        $graph = [];
        foreach ($rows as $row) {
            $graph[$row->d1][] = $row->d2;
            $graph[$row->d2][] = $row->d1;
        }
        return $graph;
    }

    /**
     * Dado um array de IDs que tiveram a mesma temperatura, separa em sub-arrays de distritos
     * que são efetivamente vizinhos (componentes conectados no sub-grafo).
     */
    private function getConnectedComponents(array $nodes, array $adjacencies): array
    {
        $components = [];
        $visited = [];

        foreach ($nodes as $node) {
            if (!isset($visited[$node])) {
                $component = [];
                $queue = [$node];
                $visited[$node] = true;

                while (!empty($queue)) {
                    $current = array_shift($queue);
                    $component[] = $current;

                    if (isset($adjacencies[$current])) {
                        foreach ($adjacencies[$current] as $neighbor) {
                            if (in_array($neighbor, $nodes) && !isset($visited[$neighbor])) {
                                $visited[$neighbor] = true;
                                $queue[] = $neighbor;
                            }
                        }
                    }
                }
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Salva a Região Climática, vincula o Time Slice e deleta os dados redundantes.
     */
    private function saveAndSanitizeCluster(array $cluster): int
    {
        $distritos = $cluster['distritos'];
        $start = $cluster['start'];
        $end = $cluster['last'];
        
        // Ex: "Região 1-4-8"
        $regiaoName = "Região " . implode('-', $distritos);
        $regiao = RegiaoClimatica::firstOrCreate(['nome' => $regiaoName]);

        // Evita duplicar o vínculo exato (caso a rotina rode novamente)
        $exists = DB::table('regiao_climatica_distrito')
            ->where('regiao_climatica_id', $regiao->id)
            ->where('distrito_id', $distritos[0])
            ->where('start_time', $start)
            ->where('end_time', $end)
            ->exists();

        if ($exists) {
            return 0; // Já foi sanitizado/processado
        }

        // 1. Registrar na Tabela Pivô (Time Slice)
        foreach ($distritos as $dId) {
            $regiao->distritos()->attach($dId, [
                'start_time' => $start,
                'end_time'   => $end
            ]);
        }

        // 2. Sanitização (Pruning)
        // O primeiro distrito é eleito o "Primário" para essa faixa, os outros terão seus dados apagados
        $redundantIds = array_slice($distritos, 1);
        $tables = [
            'temperatura_registrada', 
            'chuva_registrada', 
            'vento_registrado', 
            'pressao_atmosferica', 
            'radiacao_solar', 
            'indice_uv', 
            'particula_ar'
        ];

        $deletedCount = 0;

        foreach ($tables as $table) {
            // Verifica se a tabela existe (algumas podem ter sido removidas/renomeadas)
            if (Schema::hasTable($table)) {
                $deletedCount += DB::table($table)
                    ->whereIn('distrito_id', $redundantIds)
                    ->whereBetween('timestamp', [$start, $end])
                    ->delete();
            }
        }

        return $deletedCount;
    }
}
