<?php

namespace App\Services\Geo;

use InvalidArgumentException;

class BoundingBox
{
    public static function parse(string $value, ?float $maximumArea = null): array
    {
        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
            throw new InvalidArgumentException('bbox deve conter minLng,minLat,maxLng,maxLat.');
        }
        $bbox = array_map('floatval', $parts);
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        if ($minLng < -180 || $maxLng > 180 || $minLat < -90 || $maxLat > 90 || $minLng >= $maxLng || $minLat >= $maxLat) {
            throw new InvalidArgumentException('bbox possui limites geográficos inválidos.');
        }
        if ($maximumArea !== null && (($maxLng - $minLng) * ($maxLat - $minLat)) > $maximumArea) {
            throw new InvalidArgumentException('bbox excede a área máxima permitida.');
        }
        return $bbox;
    }
}
