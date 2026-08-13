<?php

namespace App\Http\Controllers;

use App\Models\Distrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InternetController extends Controller
{
    public function analytics(): JsonResponse
    {
        $collections = Cache::remember('analytics:internet:v2', config('data_sources.analytics_cache_seconds'), fn () => $this->buildAnalytics());
        return response()->json($collections)->header('Cache-Control', 'private, max-age=300');
    }

    private function buildAnalytics(): array
    {
        $collections = ['densidade_rede' => $this->assetDensityFeatures(), 'demanda' => []];
        $districts = Distrito::query()->whereNotNull('geojson')->get(['id', 'nome', 'geojson', 'latitude', 'longitude', 'populacao']);
        foreach ($districts as $district) {
            $population = (float) ($district->populacao ?? 0);
            foreach ($this->territorialSamples($district->geojson, (float) $district->longitude, (float) $district->latitude) as $coordinates) {
                $collections['demanda'][] = ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $coordinates],
                    'properties' => ['districtId' => $district->id, 'name' => $district->nome, 'value' => round($population * 0.78),
                        'populacao' => (int) $population, 'estimado' => true, 'spatialModel' => 'population-distributed']];
            }
        }
        foreach ($collections as $type => $features) {
            $values = array_column(array_column($features, 'properties'), 'value');
            $min = min($values ?: [0]); $max = max($values ?: [0]); $range = $max - $min;
            foreach ($features as &$feature) $feature['properties']['weight'] = $range > 0 ? ($feature['properties']['value'] - $min) / $range : .5;
            $collections[$type] = ['type' => 'FeatureCollection', 'properties' => ['minimum' => $min, 'maximum' => $max], 'features' => $features];
        }
        return $collections;
    }

    private function assetDensityFeatures(): array
    {
        $cellSize = .00115; $cells = [];
        $add = function (float $longitude, float $latitude, string $kind) use (&$cells, $cellSize): void {
            $key = ((int) floor($longitude / $cellSize)).':'.((int) floor($latitude / $cellSize));
            $cells[$key] ??= ['longitude' => 0.0, 'latitude' => 0.0, 'count' => 0, 'antenas' => 0, 'equipamentos' => 0, 'cabos' => 0];
            $cells[$key]['longitude'] += $longitude; $cells[$key]['latitude'] += $latitude; $cells[$key]['count']++; $cells[$key][$kind]++;
        };
        foreach (['antenas' => 'antenas', 'equipamentos_infraestrutura' => 'equipamentos'] as $table => $kind) {
            DB::table($table)->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('id')->chunkById(3000,
                fn ($rows) => $rows->each(fn ($row) => $add((float) $row->longitude, (float) $row->latitude, $kind)), 'id', 'id');
        }
        $cables = DB::table('cabeamentos')->whereIn('tipo_cabo', ['fibra_optica', 'fibra_backbone', 'cobre_telefonia']);
        if (DB::getDriverName() === 'pgsql') $cables->whereNotNull('geom')->selectRaw("ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.00003)) geometry");
        else $cables->whereNotNull('geojson')->selectRaw('geojson as geometry');
        $cables->orderBy('id')->chunk(1000, function ($rows) use ($add): void {
            foreach ($rows as $row) {
                $geometry = is_string($row->geometry) ? json_decode($row->geometry, true) : (array) $row->geometry;
                foreach ($this->lineSamples($geometry, 90) as [$longitude, $latitude]) $add($longitude, $latitude, 'cabos');
            }
        });
        return array_values(array_map(fn ($cell) => ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [$cell['longitude'] / $cell['count'], $cell['latitude'] / $cell['count']]],
            'properties' => ['name' => 'Concentração da rede', 'value' => $cell['count'], 'antenas' => $cell['antenas'], 'equipamentos' => $cell['equipamentos'], 'cabos' => $cell['cabos'], 'cellSizeMeters' => 125]], $cells));
    }

    private function lineSamples(?array $geometry, float $spacingMeters): array
    {
        $lines = match ($geometry['type'] ?? null) { 'LineString' => [$geometry['coordinates']], 'MultiLineString' => $geometry['coordinates'], default => [] };
        $samples = [];
        foreach ($lines as $line) for ($i = 1; $i < count($line); $i++) {
            [$x1, $y1] = $line[$i - 1]; [$x2, $y2] = $line[$i];
            $distance = hypot(($x2 - $x1) * cos(deg2rad(($y1 + $y2) / 2)) * 111320, ($y2 - $y1) * 110540);
            $steps = max(1, (int) ceil($distance / $spacingMeters));
            for ($step = 0; $step < $steps; $step++) { $ratio = $step / $steps; $samples[] = [$x1 + ($x2 - $x1) * $ratio, $y1 + ($y2 - $y1) * $ratio]; }
        }
        return $samples;
    }

    private function territorialSamples(mixed $geometry, float $fallbackLng, float $fallbackLat): array
    {
        if (is_string($geometry)) $geometry = json_decode($geometry, true);
        $points = []; $walk = function ($value) use (&$walk, &$points): void { if (is_array($value) && count($value) >= 2 && is_numeric($value[0]) && is_numeric($value[1])) { $points[] = [(float) $value[0], (float) $value[1]]; return; } if (is_array($value)) foreach ($value as $child) $walk($child); }; $walk($geometry['coordinates'] ?? []);
        if (!$points) return [[$fallbackLng, $fallbackLat]];
        $minLng = min(array_column($points, 0)); $maxLng = max(array_column($points, 0)); $minLat = min(array_column($points, 1)); $maxLat = max(array_column($points, 1));
        $samples = []; for ($row = 0; $row < 5; $row++) for ($column = 0; $column < 5; $column++) { $candidate = [$minLng + (($column + .5) / 5) * ($maxLng - $minLng), $minLat + (($row + .5) / 5) * ($maxLat - $minLat)]; if ($this->insideGeometry($candidate, $geometry)) $samples[] = $candidate; }
        return $samples ?: [[$fallbackLng, $fallbackLat]];
    }

    private function insideGeometry(array $point, array $geometry): bool
    {
        $polygons = ($geometry['type'] ?? null) === 'Polygon' ? [$geometry['coordinates'] ?? []] : ($geometry['coordinates'] ?? []);
        foreach ($polygons as $polygon) { $ring = $polygon[0] ?? []; $inside = false; for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) { [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j]; if ((($yi > $point[1]) !== ($yj > $point[1])) && $point[0] < ($xj - $xi) * ($point[1] - $yi) / (($yj - $yi) ?: 1e-12) + $xi) $inside = !$inside; } if ($inside) return true; }
        return false;
    }
}
