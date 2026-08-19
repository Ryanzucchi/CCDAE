<?php

namespace App\Services\Osm;

use App\Models\OsmBuilding;
use App\Services\Osm\Contracts\BuildingProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BuildingImportService
{
    public function __construct(
        private readonly BuildingProvider $provider,
        private readonly BuildingGeometryFactory $geometries,
        private readonly BuildingHeightResolver $heights,
    ) {}

    public function sync(array $bbox): array
    {
        $elements = $this->provider->buildingsForBbox($bbox);
        $stats = ['found' => count($elements), 'created' => 0, 'updated' => 0, 'errors' => 0, 'error_messages' => []];

        if (empty($elements)) {
            return $stats;
        }

        $osmIds = array_filter(array_column($elements, 'id'));
        $existingRecords = ! empty($osmIds)
            ? OsmBuilding::query()->whereIn('osm_id', $osmIds)->get(['id', 'osm_type', 'osm_id'])->keyBy(fn ($b) => $b->osm_type.':'.$b->osm_id)
            : collect();

        foreach ($elements as $element) {
            try {
                $geometry = $this->geometries->fromElement($element);
                if (! $geometry) {
                    $stats['errors']++;
                    $stats['error_messages'][] = ($element['type'] ?? 'objeto').' '.($element['id'] ?? '?').': geometria inválida ou incompleta.';

                    continue;
                }
                $tags = $element['tags'] ?? [];
                $attributes = [
                    'building' => $tags['building'] ?? null,
                    'name' => $tags['name'] ?? null,
                    ...$this->heights->resolve($tags),
                    'tags' => $tags,
                    'synced_at' => now(),
                ];

                $key = ($element['type'] ?? '').':'.($element['id'] ?? '');
                $existing = $existingRecords->get($key);

                $created = DB::transaction(function () use ($element, $geometry, $attributes, $existing): bool {
                    $record = $existing ?: new OsmBuilding(['osm_type' => $element['type'], 'osm_id' => $element['id']]);
                    $isNew = ! $record->exists;
                    $record->fill($attributes);
                    $record->save();
                    $geojson = json_encode($geometry, JSON_THROW_ON_ERROR);
                    if (DB::getDriverName() === 'pgsql') {
                        DB::update('UPDATE osm_buildings SET geometry = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) WHERE id = ?', [$geojson, $record->id]);
                    } else {
                        DB::table('osm_buildings')->where('id', $record->id)->update(['geometry' => $geojson]);
                    }

                    return $isNew;
                });
                $stats[$created ? 'created' : 'updated']++;
            } catch (\Throwable $exception) {
                $stats['errors']++;
                if (count($stats['error_messages']) < 20) {
                    $stats['error_messages'][] = ($element['type'] ?? 'objeto').' '.($element['id'] ?? '?').': '.$exception->getMessage();
                }
            }
        }
        if ($stats['created'] || $stats['updated']) {
            Cache::forever('osm-buildings-cache-version', (string) microtime(true));
        }

        return $stats;
    }
}
