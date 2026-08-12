<?php

namespace App\Services\Osm;

use App\Services\Osm\Contracts\BuildingProvider;

class CommercialBuildingProvider implements BuildingProvider
{
    public function __construct(
        private readonly OverpassBuildingProvider $osm,
        private readonly GlobalBuildingAtlasProvider $atlas,
        private readonly BuildingGeometryFactory $geometries,
    ) {}

    public function buildingsForBbox(array $bbox): array
    {
        $osm = $this->osm->buildingsForBbox($bbox);
        $atlas = $this->atlas->buildingsForBbox($bbox);
        $osmCenters = array_values(array_filter(array_map(fn (array $element) => $this->center($this->geometries->fromElement($element)), $osm)));
        $tolerance = (float) config('osm.buildings.deduplication_distance_degrees', 0.00012);

        $complementary = array_values(array_filter($atlas, function (array $element) use ($osmCenters, $tolerance): bool {
            $center = $this->center($this->geometries->fromElement($element));
            if (! $center) return false;
            foreach ($osmCenters as $osmCenter) {
                if (hypot($center[0] - $osmCenter[0], $center[1] - $osmCenter[1]) <= $tolerance) return false;
            }
            return true;
        }));

        return [...$osm, ...$complementary];
    }

    private function center(?array $geometry): ?array
    {
        if (! $geometry) return null;
        $points = $this->flatten($geometry['coordinates'] ?? []);
        if (! $points) return null;
        return [array_sum(array_column($points, 0)) / count($points), array_sum(array_column($points, 1)) / count($points)];
    }

    private function flatten(array $coordinates): array
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) return [[$coordinates[0], $coordinates[1]]];
        return array_merge(...array_map(fn ($item) => is_array($item) ? $this->flatten($item) : [], $coordinates));
    }
}
