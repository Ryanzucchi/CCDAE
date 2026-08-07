<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Distrito;
use App\Models\ViaTransito;

class SyncTransitoRealCommand extends Command
{
    protected $signature = 'transito:sync-real';
    protected $description = 'Busca vias reais de Cáceres via Overpass API (OpenStreetMap) para desenhar o trânsito com precisão.';

    public function handle()
    {
        $this->info('Iniciando sincronização de vias reais via OpenStreetMap (Overpass API)...');

        $distritos = Distrito::all();
        $lats = [];
        $lons = [];

        foreach ($distritos as $d) {
            $lat = $d->latitude;
            $lon = $d->longitude;

            if (is_null($lat) || is_null($lon)) {
                $geo = $d->geojson;
                if ($geo && isset($geo['type'])) {
                    if ($geo['type'] === 'Polygon' && isset($geo['coordinates'][0][0])) {
                        $lon = $geo['coordinates'][0][0][0];
                        $lat = $geo['coordinates'][0][0][1];
                    } elseif ($geo['type'] === 'MultiPolygon' && isset($geo['coordinates'][0][0][0])) {
                        $lon = $geo['coordinates'][0][0][0][0];
                        $lat = $geo['coordinates'][0][0][0][1];
                    }
                }
            }

            if (!is_null($lat) && !is_null($lon)) {
                $d->latitude = (float)$lat;
                $d->longitude = (float)$lon;
                $lats[] = (float)$lat;
                $lons[] = (float)$lon;
            }
        }

        // Bounding box aproximado de Cáceres MT focado nos distritos (aprox)
        // bbox: min_lon, min_lat, max_lon, max_lat
        $minLat = (!empty($lats) ? min($lats) : -16.07) - 0.05;
        $maxLat = (!empty($lats) ? max($lats) : -16.07) + 0.05;
        $minLon = (!empty($lons) ? min($lons) : -57.68) - 0.05;
        $maxLon = (!empty($lons) ? max($lons) : -57.68) + 0.05;

        $bbox = "{$minLat},{$minLon},{$maxLat},{$maxLon}";

        // Consulta Overpass para pegar principais vias e ruas residenciais
        $query = "[out:json][timeout:60];
        (
          way[\"highway\"~\"primary|secondary|tertiary|residential|trunk\"]({$bbox});
        );
        out geom;";

        try {
            $endpoints = [
                'https://overpass-api.de/api/interpreter',
                'https://overpass.kumi.systems/api/interpreter',
                'https://z.overpass-api.de/api/interpreter'
            ];

        $response = null;
        $success = false;

        foreach ($endpoints as $url) {
            $this->info("Tentando Overpass API: {$url}...");
            try {
                $response = Http::timeout(40)->withHeaders([
                    'User-Agent' => 'CCDAE/1.0 (Contact: admin@example.com)',
                    'Accept' => '*/*'
                ])->asForm()->post($url, [
                    'data' => $query
                ]);

                if ($response->successful()) {
                    $success = true;
                    break;
                } else {
                    $this->warn("Erro no endpoint {$url}: " . $response->status());
                }
            } catch (\Throwable $t) {
                $this->warn("Falha de conexão com {$url}: " . $t->getMessage());
            }
        }

        if (!$success || is_null($response)) {
            $this->error('Todos os endpoints do Overpass API falharam.');
            return;
        }

        $data = $response->json();

        if (!isset($data['elements'])) {
            $this->error('Nenhum dado retornado da API.');
            return;
        }

            $elements = $data['elements'];
            $this->info('Vias encontradas: ' . count($elements));

            // Limpar vias antigas geradas randomicamente
            ViaTransito::truncate();

            $inseridos = 0;

            $bar = $this->output->createProgressBar(count($elements));
            $bar->start();

            foreach ($elements as $el) {
                if ($el['type'] !== 'way' || !isset($el['geometry'])) continue;

                $coords = [];
                $midPoint = null;
                $idx = 0;
                $midIdx = (int) floor(count($el['geometry']) / 2);

                foreach ($el['geometry'] as $geo) {
                    $coords[] = [$geo['lon'], $geo['lat']];
                    if ($idx === $midIdx) {
                        $midPoint = ['lat' => $geo['lat'], 'lon' => $geo['lon']];
                    }
                    $idx++;
                }

                if (count($coords) < 2 || !$midPoint) continue;

                $geojson = [
                    'type' => 'LineString',
                    'coordinates' => $coords
                ];

                $nome = $el['tags']['name'] ?? 'Via Não Nomeada';

                // Encontrar distrito mais proximo
                $closestDistritoId = null;
                $minDist = PHP_FLOAT_MAX;

                foreach ($distritos as $d) {
                    // Calculo rudimentar de distancia Euclidiana
                    $dist = pow($d->latitude - $midPoint['lat'], 2) + pow($d->longitude - $midPoint['lon'], 2);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closestDistritoId = $d->id;
                    }
                }

                // Gerar nivel de congestionamento e velocidade (simulação baseada no tipo de via)
                $tipo = $el['tags']['highway'] ?? 'residential';
                
                $nivel = 'livre';
                if (in_array($tipo, ['primary', 'trunk'])) {
                    $nivel = collect(['livre', 'moderado', 'moderado', 'intenso', 'parado'])->random();
                } elseif (in_array($tipo, ['secondary'])) {
                    $nivel = collect(['livre', 'livre', 'moderado', 'intenso'])->random();
                } else {
                    $nivel = collect(['livre', 'livre', 'livre', 'livre', 'moderado'])->random();
                }

                $velocidade = 0;
                if ($nivel == 'livre') $velocidade = rand(40, 60);
                if ($nivel == 'moderado') $velocidade = rand(20, 40);
                if ($nivel == 'intenso') $velocidade = rand(10, 20);
                if ($nivel == 'parado') $velocidade = rand(0, 5);

                $impacto = 'baixo';
                if ($nivel == 'intenso') $impacto = 'medio';
                if ($nivel == 'parado') $impacto = 'alto';

                ViaTransito::create([
                    'distrito_id' => $closestDistritoId,
                    'nome' => $nome,
                    'geojson' => $geojson,
                    'nivel_congestionamento' => $nivel,
                    'velocidade_media' => $velocidade,
                    'volume_veiculos' => rand(100, 2000),
                    'impacto_manutencao' => $impacto,
                    'ultima_atualizacao' => now(),
                ]);

                $inseridos++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Importação concluída! $inseridos vias reais inseridas no mapa.");

        } catch (\Exception $e) {
            $this->error('Exceção: ' . $e->getMessage());
        }
    }
}
