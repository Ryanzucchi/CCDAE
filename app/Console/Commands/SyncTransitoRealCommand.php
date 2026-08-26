<?php

namespace App\Console\Commands;

use App\Models\Distrito;
use App\Models\ViaTransito;
use App\Services\Traffic\OsmRoadAttributeMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncTransitoRealCommand extends Command
{
    protected $signature = 'transito:sync-real {--source= : Caminho de uma resposta JSON previamente obtida da Overpass}';

    protected $description = 'Sincroniza e enriquece vias de Cáceres via OpenStreetMap sem apagar históricos de trânsito.';

    public function handle(OsmRoadAttributeMapper $attributeMapper): int
    {
        $this->info('Iniciando sincronização segura de vias via OpenStreetMap (Overpass API)...');

        $districts = Distrito::all();
        [$bbox, $districtPoints] = $this->boundingBox($districts);
        $elements = $this->option('source')
            ? $this->loadRoadsFromFile((string) $this->option('source'))
            : $this->fetchRoads($bbox);
        if ($elements === null) {
            return self::FAILURE;
        }

        $this->info('Vias encontradas no OSM: '.count($elements));

        $existingRoads = ViaTransito::query()->get();
        $byOsmId = $existingRoads->whereNotNull('osm_way_id')->keyBy(fn (ViaTransito $road) => (string) $road->osm_way_id);
        $byGeometry = $existingRoads->filter(fn (ViaTransito $road) => filled($road->geojson))
            ->keyBy(fn (ViaTransito $road) => $this->geometryFingerprint($road->geojson));

        $updated = 0;
        $inserted = 0;
        $unchanged = 0;

        $bar = $this->output->createProgressBar(count($elements));
        $bar->start();

        foreach ($elements as $element) {
            $bar->advance();
            if (($element['type'] ?? null) !== 'way' || count($element['geometry'] ?? []) < 2) {
                continue;
            }

            $geometry = $this->geometry($element['geometry']);
            $osmId = (string) $element['id'];
            $road = $byOsmId->get($osmId) ?? $byGeometry->get($this->geometryFingerprint($geometry));
            $tags = $element['tags'] ?? [];
            $attributes = $attributeMapper->map($tags);

            if ($road) {
                if ($this->enrichExistingRoad($road, $osmId, $tags, $attributes)) {
                    $updated++;
                } else {
                    $unchanged++;
                }

                continue;
            }

            $midpoint = $element['geometry'][(int) floor(count($element['geometry']) / 2)];
            $inferred = $attributes['_inferred'];
            unset($attributes['_inferred']);

            ViaTransito::query()->create([
                'osm_way_id' => $osmId,
                'distrito_id' => $this->closestDistrictId($districtPoints, $midpoint),
                'nome' => $tags['name'] ?? 'Via Não Nomeada',
                'geojson' => $geometry,
                ...$attributes,
                'nivel_congestionamento' => 'livre',
                'velocidade_media' => $attributes['limite_velocidade'],
                'volume_veiculos' => null,
                'impacto_manutencao' => 'baixo',
                'ultima_atualizacao' => now(),
                'infraestrutura_json' => $this->osmMetadata($osmId, $tags, $inferred),
            ]);
            $inserted++;
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sincronização concluída: {$updated} vias enriquecidas, {$inserted} inseridas e {$unchanged} já atualizadas.");

        return self::SUCCESS;
    }

    private function enrichExistingRoad(ViaTransito $road, string $osmId, array $tags, array $attributes): bool
    {
        $inferred = $attributes['_inferred'];
        unset($attributes['_inferred']);

        $infrastructure = is_array($road->infraestrutura_json) ? $road->infraestrutura_json : [];
        $previouslyInferred = $infrastructure['osm']['inferred_fields'] ?? [];
        $updates = [];

        foreach ($attributes as $field => $value) {
            if ($value === null) {
                continue;
            }

            $current = $road->{$field};
            $isMissing = $current === null || (is_string($current) && trim($current) === '');
            $nowExplicit = in_array($field, $previouslyInferred, true) && ! in_array($field, $inferred, true);
            if ($isMissing || $nowExplicit) {
                $updates[$field] = $value;
            }
        }

        if (! filled($road->osm_way_id)) {
            $updates['osm_way_id'] = $osmId;
        }
        if ((! filled($road->nome) || $road->nome === 'Via Não Nomeada') && filled($tags['name'] ?? null)) {
            $updates['nome'] = $tags['name'];
        }

        $newOsmMetadata = $this->osmMetadata($osmId, $tags, $inferred)['osm'];
        $previousOsmMetadata = $infrastructure['osm'] ?? null;
        if (is_array($previousOsmMetadata)
            && ($previousOsmMetadata['way_id'] ?? null) === $newOsmMetadata['way_id']
            && ($previousOsmMetadata['tags'] ?? []) == $newOsmMetadata['tags']
            && ($previousOsmMetadata['inferred_fields'] ?? []) == $newOsmMetadata['inferred_fields']) {
            $newOsmMetadata = $previousOsmMetadata;
        }
        $infrastructure['osm'] = $newOsmMetadata;
        $updates['infraestrutura_json'] = $infrastructure;

        $road->fill($updates);
        if (! $road->isDirty()) {
            return false;
        }

        $road->save();

        return true;
    }

    private function osmMetadata(string $osmId, array $tags, array $inferred): array
    {
        $relevantTags = array_intersect_key($tags, array_flip([
            'highway', 'lanes', 'lanes:forward', 'lanes:backward', 'oneway', 'junction',
            'maxspeed', 'surface', 'smoothness', 'incline', 'name',
        ]));

        return ['osm' => [
            'way_id' => $osmId,
            'tags' => $relevantTags,
            'inferred_fields' => array_values($inferred),
            'enriched_at' => now()->toIso8601String(),
        ]];
    }

    private function fetchRoads(string $bbox): ?array
    {
        $query = "[out:json][timeout:60];(way[\"highway\"~\"primary|secondary|tertiary|residential|trunk\"]({$bbox}););out geom;";
        $endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://z.overpass-api.de/api/interpreter',
        ];

        foreach ($endpoints as $url) {
            $this->line("Tentando Overpass API: {$url}...");
            try {
                $response = Http::timeout(90)->withHeaders([
                    'User-Agent' => 'CCDAE/1.0 (Contact: admin@example.com)',
                    'Accept' => '*/*',
                ])->get($url, ['data' => $query]);
                if ($response->successful() && is_array($response->json('elements'))) {
                    return $response->json('elements');
                }

                $this->warn("Erro no endpoint {$url}: ".$response->status());
            } catch (\Throwable $exception) {
                $this->warn("Falha de conexão com {$url}: ".$exception->getMessage());
            }
        }

        $this->error('Todos os endpoints do Overpass API falharam. Nenhuma via foi alterada.');

        return null;
    }

    private function loadRoadsFromFile(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Arquivo Overpass não encontrado ou sem permissão de leitura: {$path}");

            return null;
        }

        $contents = file_get_contents($path);
        $elements = $contents !== false ? json_decode($contents, true)['elements'] ?? null : null;
        if (! is_array($elements)) {
            $this->error("Arquivo Overpass inválido: {$path}");

            return null;
        }

        $this->line("Usando resposta Overpass local: {$path}");

        return $elements;
    }

    private function boundingBox($districts): array
    {
        $points = [];
        foreach ($districts as $district) {
            $latitude = $district->latitude;
            $longitude = $district->longitude;
            $geometry = $district->geojson;

            if (($latitude === null || $longitude === null) && is_array($geometry)) {
                $coordinate = match ($geometry['type'] ?? null) {
                    'Polygon' => $geometry['coordinates'][0][0] ?? null,
                    'MultiPolygon' => $geometry['coordinates'][0][0][0] ?? null,
                    default => null,
                };
                if (is_array($coordinate)) {
                    [$longitude, $latitude] = $coordinate;
                }
            }

            if ($latitude !== null && $longitude !== null) {
                $points[] = ['id' => $district->id, 'lat' => (float) $latitude, 'lon' => (float) $longitude];
            }
        }

        $latitudes = array_column($points, 'lat') ?: [-16.07];
        $longitudes = array_column($points, 'lon') ?: [-57.68];
        $bbox = (min($latitudes) - 0.05).','.(min($longitudes) - 0.05).','
            .(max($latitudes) + 0.05).','.(max($longitudes) + 0.05);

        return [$bbox, $points];
    }

    private function closestDistrictId(array $districts, array $point): ?int
    {
        $closestId = null;
        $minimumDistance = PHP_FLOAT_MAX;
        foreach ($districts as $district) {
            $distance = ($district['lat'] - $point['lat']) ** 2 + ($district['lon'] - $point['lon']) ** 2;
            if ($distance < $minimumDistance) {
                $minimumDistance = $distance;
                $closestId = $district['id'];
            }
        }

        return $closestId;
    }

    private function geometry(array $points): array
    {
        return [
            'type' => 'LineString',
            'coordinates' => array_map(fn (array $point) => [(float) $point['lon'], (float) $point['lat']], $points),
        ];
    }

    private function geometryFingerprint(mixed $geometry): string
    {
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }
        if (($geometry['type'] ?? null) === 'Feature') {
            $geometry = $geometry['geometry'] ?? [];
        }

        $coordinates = $geometry['coordinates'] ?? [];
        $normalized = array_map(
            fn (array $point) => [round((float) $point[0], 7), round((float) $point[1], 7)],
            $coordinates,
        );

        return hash('sha256', json_encode($normalized));
    }
}
