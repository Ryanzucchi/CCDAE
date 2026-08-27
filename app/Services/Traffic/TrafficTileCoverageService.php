<?php

namespace App\Services\Traffic;

use App\Models\ViaTransito;
use App\Services\Geo\LineSampler;
use Illuminate\Support\Facades\DB;

class TrafficTileCoverageService
{
    public function __construct(private readonly LineSampler $lineSampler) {}

    public function synchronize(int $zoom): int
    {
        $required = [];
        ViaTransito::query()->whereNotNull('geojson')->select(['id', 'geojson'])->chunkById(250, function ($roads) use ($zoom, &$required): void {
            foreach ($roads as $road) {
                $geometry = $road->geojson;
                $points = $this->lineSampler->sample($geometry, 2000);
                $lines = match ($geometry['type'] ?? null) {
                    'LineString' => [$geometry['coordinates']],
                    'MultiLineString' => $geometry['coordinates'],
                    default => [],
                };
                foreach ($lines as $line) {
                    if ($line) {
                        $points[] = $line[array_key_last($line)];
                    }
                }
                foreach ($points as [$longitude, $latitude]) {
                    [$x, $y] = self::longitudeLatitudeToTile($longitude, $latitude, $zoom);
                    $required["{$x}:{$y}"] = ['provider' => 'tomtom', 'zoom' => $zoom, 'x' => $x, 'y' => $y];
                }
            }
        });

        $now = now();
        DB::transaction(function () use ($zoom, $required, $now): void {
            DB::table('traffic_tiles')->where('provider', 'tomtom')->where('zoom', $zoom)->update(['active' => false, 'updated_at' => $now]);
            foreach (array_chunk(array_values($required), 500) as $chunk) {
                DB::table('traffic_tiles')->upsert(
                    array_map(fn (array $tile): array => [...$tile, 'active' => true, 'created_at' => $now, 'updated_at' => $now], $chunk),
                    ['provider', 'zoom', 'x', 'y'],
                    ['active', 'updated_at'],
                );
            }
        });

        return count($required);
    }

    public function nextTiles(int $zoom, int $limit): array
    {
        return DB::table('traffic_tiles')
            ->where('provider', 'tomtom')
            ->where('zoom', $zoom)
            ->where('active', true)
            ->orderByRaw('last_collected_at IS NOT NULL')
            ->orderBy('last_collected_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'zoom', 'x', 'y'])
            ->map(fn ($tile): array => ['id' => (int) $tile->id, 'zoom' => (int) $tile->zoom, 'x' => (int) $tile->x, 'y' => (int) $tile->y])
            ->all();
    }

    public static function longitudeLatitudeToTile(float $longitude, float $latitude, int $zoom): array
    {
        $latitude = max(-85.05112878, min(85.05112878, $latitude));
        $scale = 2 ** $zoom;
        $x = (int) floor(($longitude + 180) / 360 * $scale);
        $latitudeRadians = deg2rad($latitude);
        $y = (int) floor((1 - asinh(tan($latitudeRadians)) / M_PI) / 2 * $scale);

        return [max(0, min($scale - 1, $x)), max(0, min($scale - 1, $y))];
    }
}
