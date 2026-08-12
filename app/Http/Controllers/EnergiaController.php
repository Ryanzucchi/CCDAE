<?php

namespace App\Http\Controllers;

use App\Models\Distrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EnergiaController extends Controller
{
    public function analytics(): JsonResponse
    {
        $districts = Distrito::query()->whereNotNull('geojson')->get(['id', 'nome', 'geojson', 'latitude', 'longitude', 'populacao', 'consumo_medio_kwh']);
        $posts = DB::table('postes')->selectRaw('distrito_id, count(*) total')->groupBy('distrito_id')->pluck('total', 'distrito_id');
        $cables = DB::table('cabeamentos')->whereIn('tipo_cabo', ['eletrico_baixa_tensao', 'eletrico_alta_tensao'])
            ->selectRaw('distrito_id, count(*) total')->groupBy('distrito_id')->pluck('total', 'distrito_id');

        $collections = ['densidade' => $this->poleDensityFeatures(), 'consumo' => [], 'populacao' => []];
        foreach ($districts as $district) {
            $samples = $this->territorialSamples($district->geojson, (float) $district->longitude, (float) $district->latitude);
            $population = (float) ($district->populacao ?? 0);
            $consumption = $district->consumo_medio_kwh !== null ? (float) $district->consumo_medio_kwh : $population * 0.18;
            foreach (['consumo' => $consumption, 'populacao' => $population] as $type => $value) {
                foreach ($samples as $index => $coordinates) {
                    $collections[$type][] = ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $coordinates],
                        'properties' => ['districtId' => $district->id, 'name' => $district->nome, 'value' => $value,
                            'sample' => $index + 1, 'samples' => count($samples), 'spatialModel' => 'district-distributed',
                            'postes' => (int) ($posts[$district->id] ?? 0), 'cabos' => (int) ($cables[$district->id] ?? 0),
                            'populacao' => (int) $population, 'estimado' => $type === 'consumo' && $district->consumo_medio_kwh === null]];
                }
            }
        }
        foreach ($collections as $type => $features) {
            $values = array_column(array_column($features, 'properties'), 'value'); $min = min($values ?: [0]); $max = max($values ?: [0]); $range = $max - $min;
            foreach ($features as &$feature) $feature['properties']['weight'] = $range > 0 ? ($feature['properties']['value'] - $min) / $range : .5;
            $collections[$type] = ['type' => 'FeatureCollection', 'properties' => ['minimum' => $min, 'maximum' => $max], 'features' => $features];
        }
        return response()->json($collections);
    }

    private function poleDensityFeatures(): array
    {
        // Aproximadamente 125 m em Cáceres. A grade é contínua e não usa limites de distritos.
        $cellSize = 0.00115;
        $cells = [];
        DB::table('postes')->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('id')->select(['id', 'latitude', 'longitude'])->chunkById(5000, function ($posts) use (&$cells, $cellSize): void {
                foreach ($posts as $post) {
                    $longitude = (float) $post->longitude; $latitude = (float) $post->latitude;
                    $cellX = (int) floor($longitude / $cellSize); $cellY = (int) floor($latitude / $cellSize);
                    $key = $cellX.':'.$cellY;
                    $cells[$key] ??= ['longitude' => 0.0, 'latitude' => 0.0, 'count' => 0];
                    $cells[$key]['longitude'] += $longitude; $cells[$key]['latitude'] += $latitude; $cells[$key]['count']++;
                }
            });

        return array_values(array_map(fn (array $cell): array => [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [$cell['longitude'] / $cell['count'], $cell['latitude'] / $cell['count']]],
            'properties' => [
                'name' => 'Concentração local de postes', 'value' => $cell['count'], 'postes' => $cell['count'],
                'spatialModel' => 'pole-density-grid', 'cellSizeMeters' => 125,
            ],
        ], $cells));
    }

    private function territorialSamples(mixed $geometry, float $fallbackLng, float $fallbackLat): array
    {
        if (is_string($geometry)) $geometry = json_decode($geometry, true);
        $points = []; $walk = function ($value) use (&$walk, &$points): void {
            if (is_array($value) && count($value) >= 2 && is_numeric($value[0]) && is_numeric($value[1])) { $points[] = [(float) $value[0], (float) $value[1]]; return; }
            if (is_array($value)) foreach ($value as $child) $walk($child);
        }; $walk($geometry['coordinates'] ?? []);
        if (!$points) return [[$fallbackLng, $fallbackLat]];
        $minLng = min(array_column($points, 0)); $maxLng = max(array_column($points, 0));
        $minLat = min(array_column($points, 1)); $maxLat = max(array_column($points, 1));
        $lngSpan = max(0.0001, $maxLng - $minLng); $latSpan = max(0.0001, $maxLat - $minLat);
        $columns = max(3, min(8, (int) ceil($lngSpan / 0.0022)));
        $rows = max(3, min(8, (int) ceil($latSpan / 0.0022)));
        $samples = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $candidate = [$minLng + (($column + .5) / $columns) * $lngSpan, $minLat + (($row + .5) / $rows) * $latSpan];
                if ($this->insideGeometry($candidate, $geometry)) $samples[] = $candidate;
            }
        }
        if ($samples) return $samples;
        return [[array_sum(array_column($points, 0)) / count($points), array_sum(array_column($points, 1)) / count($points)]];
    }

    private function insideGeometry(array $point, array $geometry): bool
    {
        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates'] ?? []], 'MultiPolygon' => $geometry['coordinates'] ?? [], default => [],
        };
        foreach ($polygons as $polygon) {
            if (!isset($polygon[0]) || !$this->insideRing($point, $polygon[0])) continue;
            $insideHole = false;
            foreach (array_slice($polygon, 1) as $hole) if ($this->insideRing($point, $hole)) { $insideHole = true; break; }
            if (!$insideHole) return true;
        }
        return false;
    }

    private function insideRing(array $point, array $ring): bool
    {
        $inside = false; $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j];
            if ((($yi > $point[1]) !== ($yj > $point[1])) && ($point[0] < ($xj - $xi) * ($point[1] - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) $inside = !$inside;
        }
        return $inside;
    }
}
