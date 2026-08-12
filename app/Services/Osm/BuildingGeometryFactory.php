<?php

namespace App\Services\Osm;

class BuildingGeometryFactory
{
    public function fromElement(array $element): ?array
    {
        if (($element['type'] ?? null) === 'gba') {
            $geometry = $element['geojson'] ?? null;
            if (! is_array($geometry)) return null;
            if (($geometry['type'] ?? null) === 'Polygon') {
                return ['type' => 'MultiPolygon', 'coordinates' => [$geometry['coordinates'] ?? []]];
            }
            return ($geometry['type'] ?? null) === 'MultiPolygon' ? $geometry : null;
        }

        if (($element['type'] ?? null) === 'way') {
            $ring = $this->ring($element['geometry'] ?? []);
            return $ring ? ['type' => 'MultiPolygon', 'coordinates' => [[$ring]]] : null;
        }

        if (($element['type'] ?? null) !== 'relation') return null;
        $outerSegments = [];
        $innerSegments = [];
        foreach ($element['members'] ?? [] as $member) {
            if (($member['type'] ?? null) !== 'way' || empty($member['geometry'])) continue;
            if (($member['role'] ?? 'outer') === 'inner') {
                $innerSegments[] = $this->coordinates($member['geometry']);
            } else {
                $outerSegments[] = $this->coordinates($member['geometry']);
            }
        }
        $outers = $this->joinSegments($outerSegments);
        $inners = $this->joinSegments($innerSegments);
        if (!$outers) return null;

        $polygons = array_map(fn (array $outer) => [$outer], $outers);
        foreach ($inners as $inner) {
            foreach ($polygons as &$polygon) {
                if ($this->pointInRing($inner[0], $polygon[0])) {
                    $polygon[] = $inner;
                    break;
                }
            }
        }
        return ['type' => 'MultiPolygon', 'coordinates' => $polygons];
    }

    private function coordinates(array $geometry): array
    {
        return array_values(array_filter(array_map(fn ($node) => isset($node['lon'], $node['lat'])
            ? [(float) $node['lon'], (float) $node['lat']] : null, $geometry)));
    }

    private function ring(array $geometry): ?array
    {
        $coordinates = $this->coordinates($geometry);
        if (count($coordinates) < 3) return null;
        if ($coordinates[0] !== $coordinates[array_key_last($coordinates)]) $coordinates[] = $coordinates[0];
        return count($coordinates) >= 4 ? $coordinates : null;
    }

    private function joinSegments(array $segments): array
    {
        $rings = [];
        while ($segments) {
            $ring = array_shift($segments);
            $changed = true;
            while ($changed && $ring[0] !== $ring[array_key_last($ring)]) {
                $changed = false;
                foreach ($segments as $index => $segment) {
                    if ($ring[array_key_last($ring)] === $segment[0]) {
                        $ring = [...$ring, ...array_slice($segment, 1)];
                    } elseif ($ring[array_key_last($ring)] === $segment[array_key_last($segment)]) {
                        $ring = [...$ring, ...array_slice(array_reverse($segment), 1)];
                    } elseif ($ring[0] === $segment[array_key_last($segment)]) {
                        $ring = [...array_slice($segment, 0, -1), ...$ring];
                    } elseif ($ring[0] === $segment[0]) {
                        $ring = [...array_slice(array_reverse($segment), 0, -1), ...$ring];
                    } else continue;
                    unset($segments[$index]);
                    $segments = array_values($segments);
                    $changed = true;
                    break;
                }
            }
            if (count($ring) >= 4 && $ring[0] === $ring[array_key_last($ring)]) $rings[] = $ring;
        }
        return $rings;
    }

    private function pointInRing(array $point, array $ring): bool
    {
        [$x, $y] = $point;
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) $inside = !$inside;
        }
        return $inside;
    }
}
