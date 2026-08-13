<?php

namespace App\Services\Geo;

class DensityGrid
{
    private array $cells = [];

    public function __construct(private readonly float $cellSizeDegrees = 0.00115) {}

    public function add(float $longitude, float $latitude, string $kind = 'items'): void
    {
        $key = ((int) floor($longitude / $this->cellSizeDegrees)).':'.((int) floor($latitude / $this->cellSizeDegrees));
        $this->cells[$key] ??= ['longitude' => 0.0, 'latitude' => 0.0, 'count' => 0, 'kinds' => []];
        $this->cells[$key]['longitude'] += $longitude; $this->cells[$key]['latitude'] += $latitude; $this->cells[$key]['count']++;
        $this->cells[$key]['kinds'][$kind] = ($this->cells[$key]['kinds'][$kind] ?? 0) + 1;
    }

    public function features(string $name, array $kinds = [], array $properties = []): array
    {
        return array_values(array_map(function (array $cell) use ($name, $kinds, $properties): array {
            $counts = []; foreach ($kinds as $kind) $counts[$kind] = $cell['kinds'][$kind] ?? 0;
            return ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [$cell['longitude'] / $cell['count'], $cell['latitude'] / $cell['count']]],
                'properties' => [...$properties, 'name' => $name, 'value' => $cell['count'], ...$counts]];
        }, $this->cells));
    }
}
