<?php

namespace App\Services\Geo;

class LineSampler
{
    public function sample(?array $geometry, float $spacingMeters): array
    {
        $lines = match ($geometry['type'] ?? null) { 'LineString' => [$geometry['coordinates']], 'MultiLineString' => $geometry['coordinates'], default => [] };
        $samples = [];
        foreach ($lines as $line) for ($i = 1; $i < count($line); $i++) {
            [$x1, $y1] = $line[$i - 1]; [$x2, $y2] = $line[$i];
            $distance = hypot(($x2 - $x1) * cos(deg2rad(($y1 + $y2) / 2)) * 111320, ($y2 - $y1) * 110540);
            $steps = max(1, (int) ceil($distance / $spacingMeters));
            for ($step = 0; $step < $steps; $step++) { $ratio = $step / $steps; $samples[] = [$x1 + ($x2 - $x1) * $ratio, $y1 + ($y2 - $y1) * $ratio]; }
        }
        return $samples;
    }
}
