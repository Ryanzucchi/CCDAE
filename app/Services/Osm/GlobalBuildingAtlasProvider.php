<?php

namespace App\Services\Osm;

use App\Services\Osm\Contracts\BuildingProvider;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class GlobalBuildingAtlasProvider implements BuildingProvider
{
    public function buildingsForBbox(array $bbox): array
    {
        $directory = (string) config('osm.buildings.global_atlas_path');
        if ($directory === '' || ! is_dir($directory)) {
            throw new RuntimeException('Diretório do GlobalBuildingAtlas não encontrado em "'.$directory.'". Execute php artisan gba:check-buildings.');
        }

        $buildings = [];
        foreach ($this->geoJsonFiles($directory) as $file) {
            try {
                $document = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("GeoJSON inválido em {$file}: {$exception->getMessage()}", previous: $exception);
            }

            foreach ($document['features'] ?? [] as $index => $feature) {
                $geometry = $this->geometryToWgs84($feature['geometry'] ?? null);
                if (! $geometry || ! $this->intersects($geometry, $bbox)) {
                    continue;
                }

                $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                $sourceId = (string) ($feature['id'] ?? $properties['id'] ?? $properties['fid'] ?? $properties['ID'] ?? basename($file).':'.$index);
                unset($properties['height'], $properties['Height'], $properties['HEIGHT'], $properties['building_height']);
                $tags = $properties + [
                    'building' => $properties['building'] ?? $properties['type'] ?? 'yes',
                    // A distribuição ODbL contém apenas footprints. Alturas LoD1
                    // CC BY-NC não são lidas nesta configuração comercial.
                    'source' => 'GlobalBuildingAtlas ODbLPolygon',
                    'global_building_atlas_id' => $sourceId,
                ];

                $buildings[] = [
                    'type' => 'gba',
                    'id' => $this->numericId($sourceId),
                    'source_id' => $sourceId,
                    'tags' => $tags,
                    'geojson' => $geometry,
                ];
            }
        }

        return $buildings;
    }

    private function geoJsonFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['geojson', 'json'], true)) {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files);
        if (! $files) {
            throw new RuntimeException('O diretório do GlobalBuildingAtlas está vazio. Copie o GeoJSON GBA.ODbLPolygon para "'.$directory.'" e execute php artisan gba:check-buildings.');
        }

        return $files;
    }

    private function geometryToWgs84(mixed $geometry): ?array
    {
        if (! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }
        $coordinates = $geometry['coordinates'] ?? [];
        $first = $this->firstPoint($coordinates);
        if (! $first) {
            return null;
        }
        $isWebMercator = abs((float) $first[0]) > 180 || abs((float) $first[1]) > 90;
        if ($isWebMercator) {
            $coordinates = $this->mapPoints($coordinates, fn (array $point) => $this->mercatorToWgs84($point));
        }

        return ['type' => $geometry['type'], 'coordinates' => $coordinates];
    }

    private function firstPoint(array $coordinates): ?array
    {
        $value = $coordinates;
        while (is_array($value) && isset($value[0]) && is_array($value[0])) {
            $value = $value[0];
        }

        return isset($value[0], $value[1]) && is_numeric($value[0]) && is_numeric($value[1]) ? $value : null;
    }

    private function mapPoints(array $coordinates, callable $callback): array
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            return $callback($coordinates);
        }

        return array_map(fn ($item) => is_array($item) ? $this->mapPoints($item, $callback) : $item, $coordinates);
    }

    private function mercatorToWgs84(array $point): array
    {
        $longitude = (float) $point[0] / 20037508.34 * 180;
        $latitude = rad2deg(2 * atan(exp(deg2rad((float) $point[1] / 20037508.34 * 180))) - M_PI / 2);

        return [$longitude, $latitude];
    }

    private function intersects(array $geometry, array $bbox): bool
    {
        $points = $this->flatten($geometry['coordinates']);

        return $points && max(array_column($points, 0)) >= $bbox[0] && min(array_column($points, 0)) <= $bbox[2]
            && max(array_column($points, 1)) >= $bbox[1] && min(array_column($points, 1)) <= $bbox[3];
    }

    private function flatten(array $coordinates): array
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            return [[$coordinates[0], $coordinates[1]]];
        }

        return array_merge(...array_map(fn ($item) => is_array($item) ? $this->flatten($item) : [], $coordinates));
    }

    private function numericId(string $sourceId): int
    {
        $hex = substr(hash('sha256', $sourceId), 0, 15);

        return (int) hexdec($hex);
    }
}
