<?php

namespace App\Services\Geo;

class FeatureCollectionFactory
{
    public function normalized(array $features): array
    {
        $values = array_column(array_column($features, 'properties'), 'value');
        $minimum = min($values ?: [0]); $maximum = max($values ?: [0]); $range = $maximum - $minimum;
        foreach ($features as &$feature) $feature['properties']['weight'] = $range > 0 ? ($feature['properties']['value'] - $minimum) / $range : .5;
        return ['type' => 'FeatureCollection', 'properties' => ['minimum' => $minimum, 'maximum' => $maximum], 'features' => $features];
    }
}
