<?php

namespace App\Services\Traffic;

use App\Models\ViaTransito;

class TrafficRoadMatcher
{
    private const CELL_SIZE = 0.003;

    private array $grid = [];

    public function __construct()
    {
        ViaTransito::query()->whereNotNull('geojson')->get(['id', 'geojson'])->each(function (ViaTransito $road): void {
            $geometry = $road->geojson;
            $lines = match ($geometry['type'] ?? null) {
                'LineString' => [$geometry['coordinates']],
                'MultiLineString' => $geometry['coordinates'],
                default => [],
            };
            foreach ($lines as $line) {
                for ($index = 1; $index < count($line); $index++) {
                    $segment = ['road_id' => (int) $road->id, 'start' => $line[$index - 1], 'end' => $line[$index]];
                    $minX = (int) floor((min($segment['start'][0], $segment['end'][0]) - 0.001) / self::CELL_SIZE);
                    $maxX = (int) floor((max($segment['start'][0], $segment['end'][0]) + 0.001) / self::CELL_SIZE);
                    $minY = (int) floor((min($segment['start'][1], $segment['end'][1]) - 0.001) / self::CELL_SIZE);
                    $maxY = (int) floor((max($segment['start'][1], $segment['end'][1]) + 0.001) / self::CELL_SIZE);
                    for ($x = $minX; $x <= $maxX; $x++) {
                        for ($y = $minY; $y <= $maxY; $y++) {
                            $this->grid["{$x}:{$y}"][] = $segment;
                        }
                    }
                }
            }
        });
    }

    public function match(array $geometry): ?array
    {
        $coordinates = $geometry['coordinates'] ?? [];
        if (count($coordinates) < 2) {
            return null;
        }
        $middle = $coordinates[(int) floor(count($coordinates) / 2)];
        $best = null;
        $maximum = (float) config('data_sources.traffic.road_match_distance_meters', 80);
        $cellX = (int) floor($middle[0] / self::CELL_SIZE);
        $cellY = (int) floor($middle[1] / self::CELL_SIZE);
        foreach ($this->grid["{$cellX}:{$cellY}"] ?? [] as $roadSegment) {
            $candidate = $this->distanceToSegment($middle, $roadSegment['start'], $roadSegment['end']);
            if ($candidate['distance'] <= $maximum && ($best === null || $candidate['distance'] < $best['distance'])) {
                $segmentVector = [
                    $coordinates[array_key_last($coordinates)][0] - $coordinates[0][0],
                    $coordinates[array_key_last($coordinates)][1] - $coordinates[0][1],
                ];
                $roadVector = [$roadSegment['end'][0] - $roadSegment['start'][0], $roadSegment['end'][1] - $roadSegment['start'][1]];
                $best = [
                    'via_transito_id' => $roadSegment['road_id'],
                    'distance' => $candidate['distance'],
                    'direcao_fluxo' => $segmentVector[0] * $roadVector[0] + $segmentVector[1] * $roadVector[1] >= 0
                        ? 'inicio_fim'
                        : 'fim_inicio',
                ];
            }
        }

        return $best;
    }

    private function distanceToSegment(array $point, array $start, array $end): array
    {
        $latitude = ($point[1] + $start[1] + $end[1]) / 3;
        $scaleX = cos(deg2rad($latitude)) * 111320;
        $scaleY = 110540;
        $px = ($point[0] - $start[0]) * $scaleX;
        $py = ($point[1] - $start[1]) * $scaleY;
        $dx = ($end[0] - $start[0]) * $scaleX;
        $dy = ($end[1] - $start[1]) * $scaleY;
        $lengthSquared = $dx * $dx + $dy * $dy;
        $ratio = $lengthSquared > 0 ? max(0, min(1, ($px * $dx + $py * $dy) / $lengthSquared)) : 0;

        return ['distance' => hypot($px - $ratio * $dx, $py - $ratio * $dy)];
    }
}
