<?php

namespace App\Services\Osm;

class BuildingHeightResolver
{
    public function resolve(array $tags): array
    {
        $height = $this->meters($tags['height'] ?? null);
        $levels = $this->number($tags['building:levels'] ?? null);
        $minHeight = $this->meters($tags['min_height'] ?? null);
        $minLevel = $this->number($tags['building:min_level'] ?? null);
        $metersPerLevel = (float) config('osm.buildings.meters_per_level', 3.0);

        if ($height !== null) {
            $renderHeight = $height;
            $source = ($tags['source'] ?? null) === 'GlobalBuildingAtlas' ? 'gba_height' : 'osm_height';
        } elseif ($levels !== null) {
            $renderHeight = $levels * $metersPerLevel;
            $source = 'levels';
        } else {
            $renderHeight = (float) config('osm.buildings.default_height', 6.0);
            $source = 'default';
        }

        return [
            'height' => $height,
            'building_levels' => $levels,
            'min_height' => $minHeight,
            'building_min_level' => $minLevel,
            'render_height' => max(1.0, $renderHeight),
            'render_min_height' => max(0.0, $minHeight ?? (($minLevel ?? 0) * $metersPerLevel)),
            'height_source' => $source,
        ];
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float) $value;
        if (preg_match('/-?\d+(?:[.,]\d+)?/', (string) $value, $matches)) {
            return (float) str_replace(',', '.', $matches[0]);
        }
        return null;
    }

    private function meters(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $text = trim((string) $value);
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:m|meter|meters)?$/i', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }
        if (preg_match('/^(\d+)\s*[\x{2032}\x{0027}]\s*(\d+)?/u', $text, $matches)) {
            return round(((int) $matches[1] * 0.3048) + ((int) ($matches[2] ?? 0) * 0.0254), 2);
        }
        return null;
    }
}
