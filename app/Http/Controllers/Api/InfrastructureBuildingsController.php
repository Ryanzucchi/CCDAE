<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\BoundingBox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InfrastructureBuildingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $bbox = BoundingBox::parse((string) $request->query('bbox', ''), (float) config('osm.buildings.viewport_max_area_degrees'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $version = Cache::get('osm-buildings-cache-version', '1');
        $cacheKey = 'infrastructure-buildings:'.$version.':'.implode(':', array_map(fn ($value) => number_format($value, 4, '.', ''), $bbox));
        $collection = Cache::remember($cacheKey, 60, fn () => $this->featureCollection($bbox));

        return response()->json($collection);
    }

    private function featureCollection(array $bbox): array
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        $query = DB::table('osm_buildings')->select([
            'id', 'osm_id', 'osm_type', 'building', 'name', 'height', 'render_height',
            'render_min_height', 'building_levels', 'height_source',
        ])->limit(10000);

        if (DB::getDriverName() === 'pgsql') {
            $query->selectRaw('ST_AsGeoJSON(geometry)::json AS geometry')
                ->whereRaw('geometry && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $bbox)
                ->whereRaw('ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', $bbox);
        } else {
            $query->addSelect('geometry');
        }
        $rows = $query->get();
        $features = [];
        foreach ($rows as $row) {
            $geometry = is_string($row->geometry) ? json_decode($row->geometry, true) : (array) $row->geometry;
            if (! $geometry || (DB::getDriverName() !== 'pgsql' && ! $this->intersects($geometry, $bbox))) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $row->id, 'osm_id' => $row->osm_id, 'osm_type' => $row->osm_type,
                    'building' => $row->building, 'name' => $row->name, 'height' => $row->height,
                    'render_height' => (float) $row->render_height,
                    'render_min_height' => (float) $row->render_min_height,
                    'building_levels' => $row->building_levels,
                    'height_source' => $row->height_source,
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features, 'meta' => ['bbox' => $bbox, 'count' => count($features), 'limit' => 10000]];
    }

    private function intersects(array $geometry, array $bbox): bool
    {
        $points = $this->flatten($geometry['coordinates'] ?? []);
        if (! $points) {
            return false;
        }

        return max(array_column($points, 0)) >= $bbox[0] && min(array_column($points, 0)) <= $bbox[2]
            && max(array_column($points, 1)) >= $bbox[1] && min(array_column($points, 1)) <= $bbox[3];
    }

    private function flatten(array $coordinates): array
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            return [[$coordinates[0], $coordinates[1]]];
        }

        return array_merge(...array_map(fn ($item) => is_array($item) ? $this->flatten($item) : [], $coordinates));
    }
}
