<?php

namespace App\Services\Traffic;

use App\Models\ViaTransito;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TomTomVectorTrafficCollector
{
    public function __construct(
        private readonly TomTomTrafficProvider $provider,
        private readonly MapboxVectorTileDecoder $decoder,
        private readonly TrafficTileCoverageService $coverage,
        private readonly TrafficSimulationService $simulation,
    ) {}

    public function collect(): array
    {
        $zoom = (int) config('data_sources.traffic.vector_zoom', 13);
        $coverageCount = $this->coverage->synchronize($zoom);
        $usage = (int) DB::table('traffic_api_usage')
            ->where('provider', 'tomtom')->where('usage_date', now()->toDateString())
            ->sum('requests');
        $remainingRequests = max(0, (int) config('data_sources.traffic.daily_request_limit', 10000) - $usage);
        $tileLimit = min((int) config('data_sources.traffic.vector_max_tiles_per_run', 64), intdiv($remainingRequests, 2));
        $tiles = $tileLimit > 0 ? $this->coverage->nextTiles($zoom, $tileLimit) : [];
        if ($tiles === []) {
            return ['coverage_tiles' => $coverageCount, 'tiles' => 0, 'segments' => 0, 'matched' => 0, 'requests' => 0];
        }

        $responses = $this->provider->vectorFlowTiles($tiles);
        $now = Carbon::now()->startOfMinute();
        $usageMetrics = ['requests' => 0, 'successful' => 0, 'failed' => 0];
        foreach ($responses as $response) {
            $usageMetrics['requests'] += $response['requests'];
            $usageMetrics['successful'] += $response['successful'];
            $usageMetrics['failed'] += $response['failed'];
        }
        $this->recordUsage($usageMetrics, $now);

        $matcher = app(TrafficRoadMatcher::class);
        $metrics = ['coverage_tiles' => $coverageCount, 'tiles' => 0, 'segments' => 0, 'matched' => 0, 'requests' => $usageMetrics['requests']];
        $viaReadings = [];
        $viaFlow = [];

        foreach ($tiles as $tile) {
            $response = $responses[$tile['id']] ?? ['absolute' => null, 'relative' => null, 'requests' => 0, 'successful' => 0, 'failed' => 0];
            if ($response['absolute'] === null || $response['relative'] === null) {
                continue;
            }

            try {
                $absoluteSegments = $this->decoder->decodeTrafficFlow($response['absolute'], $tile['zoom'], $tile['x'], $tile['y']);
                $relativeDecoded = $this->decoder->decodeTrafficFlow($response['relative'], $tile['zoom'], $tile['x'], $tile['y']);
                $relativeSegments = collect($relativeDecoded)->keyBy('feature_key');
            } catch (\Throwable $exception) {
                Log::warning('Tile TomTom Vector Flow inválido.', [
                    'zoom' => $tile['zoom'], 'x' => $tile['x'], 'y' => $tile['y'], 'erro' => $exception->getMessage(),
                ]);

                continue;
            }

            foreach ($absoluteSegments as $segment) {
                $metrics['segments']++;
                $relative = $relativeSegments->get($segment['feature_key'])
                    ?? $this->matchRelativeSegment($segment, $relativeDecoded);
                $currentSpeed = $this->numericProperty($segment, 'traffic_level');
                $ratio = $relative ? $this->numericProperty($relative, 'traffic_level') : null;
                $freeFlowSpeed = $currentSpeed !== null && $ratio !== null && $ratio > 0.001
                    ? $currentSpeed / $ratio
                    : null;
                $properties = $segment['properties'];
                $match = $matcher->match($segment['geometry']);
                $coverageType = $properties['traffic_road_coverage'] ?? null;
                $direction = $match['direcao_fluxo'] ?? null;
                $identifier = "{$tile['zoom']}/{$tile['x']}/{$tile['y']}/{$segment['feature_key']}";
                $segmentId = $this->persistSegment($identifier, $segment['geometry'], $properties, $match, $direction, $now);
                $this->persistObservation($segmentId, $tile, $now, $currentSpeed, $freeFlowSpeed, $ratio, $properties, $direction);

                if ($match === null) {
                    continue;
                }
                $metrics['matched']++;
                $viaId = $match['via_transito_id'];
                $viaReadings[$viaId][] = [
                    'current_speed' => $currentSpeed,
                    'free_flow_speed' => $freeFlowSpeed,
                    'ratio' => $ratio,
                    'closed' => (bool) ($properties['road_closure'] ?? false),
                ];
                if ($coverageType === 'one_side') {
                    $viaFlow[$viaId] = ['sentido' => 'dupla', 'direcao_fluxo' => 'ambos'];
                } elseif ($coverageType === 'full' && ! isset($viaFlow[$viaId])) {
                    $viaFlow[$viaId] = ['sentido' => 'unica', 'direcao_fluxo' => $direction];
                }
            }

            DB::table('traffic_tiles')->where('id', $tile['id'])->update(['last_collected_at' => $now, 'updated_at' => $now]);
            $metrics['tiles']++;
        }

        $this->persistViaSnapshots($viaReadings, $viaFlow, $now);

        return $metrics;
    }

    private function persistSegment(string $identifier, array $geometry, array $properties, ?array $match, ?string $direction, Carbon $now): int
    {
        $values = [
            'via_transito_id' => $match['via_transito_id'] ?? null,
            'geometry' => json_encode($geometry, JSON_THROW_ON_ERROR),
            'road_type' => $properties['road_type'] ?? null,
            'road_coverage' => $properties['traffic_road_coverage'] ?? null,
            'direcao_fluxo' => $direction,
            'left_hand_traffic' => (bool) ($properties['left_hand_traffic'] ?? false),
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];
        DB::table('traffic_segments')->updateOrInsert(
            ['provider' => 'tomtom', 'segment_identifier' => $identifier],
            [...$values, 'created_at' => $now],
        );
        $id = (int) DB::table('traffic_segments')->where('provider', 'tomtom')->where('segment_identifier', $identifier)->value('id');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE mobility.traffic_segments SET geom = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) WHERE id = ?',
                [json_encode($geometry, JSON_THROW_ON_ERROR), $id],
            );
        }

        return $id;
    }

    private function persistObservation(
        int $segmentId,
        array $tile,
        Carbon $now,
        ?float $currentSpeed,
        ?float $freeFlowSpeed,
        ?float $ratio,
        array $properties,
        ?string $direction,
    ): void {
        DB::table('traffic_observations')->upsert([[
            'traffic_segment_id' => $segmentId,
            'collected_at' => $now,
            'current_speed' => $currentSpeed,
            'free_flow_speed' => $freeFlowSpeed,
            'congestion_ratio' => $ratio,
            'quality' => null,
            'road_closed' => (bool) ($properties['road_closure'] ?? false),
            'direcao_fluxo' => $direction,
            'provider' => 'tomtom',
            'tile_zoom' => $tile['zoom'], 'tile_x' => $tile['x'], 'tile_y' => $tile['y'],
        ]], ['traffic_segment_id', 'collected_at'], [
            'current_speed', 'free_flow_speed', 'congestion_ratio', 'quality', 'road_closed', 'direcao_fluxo',
        ]);
    }

    private function persistViaSnapshots(array $readings, array $flowDirections, Carbon $now): void
    {
        $roads = ViaTransito::query()->whereIn('id', array_keys($readings))->get()->keyBy('id');
        $records = [];
        $updates = [];
        foreach ($readings as $viaId => $viaReadings) {
            $road = $roads->get($viaId);
            $speeds = array_values(array_filter(array_column($viaReadings, 'current_speed'), fn ($value) => $value !== null));
            if (! $road || $speeds === []) {
                continue;
            }
            $freeSpeeds = array_values(array_filter(array_column($viaReadings, 'free_flow_speed'), fn ($value) => $value !== null));
            $ratios = array_values(array_filter(array_column($viaReadings, 'ratio'), fn ($value) => $value !== null));
            $currentSpeed = collect($speeds)->avg();
            $freeFlowSpeed = $freeSpeeds ? collect($freeSpeeds)->avg() : max(1, (float) ($road->limite_velocidade ?? $currentSpeed));
            $ratio = $ratios ? collect($ratios)->avg() : min(1, $currentSpeed / max(1, $freeFlowSpeed));
            $snapshot = $this->simulation->simulateViaSnapshot($road, [
                'currentSpeed' => $currentSpeed,
                'freeFlowSpeed' => $freeFlowSpeed,
                'confidence' => null,
            ], $now);
            $snapshot['registro']['velocidade_fluxo_livre'] = round($freeFlowSpeed, 1);
            $snapshot['registro']['razao_congestionamento'] = round($ratio, 5);
            $snapshot['registro']['fonte_dado'] = 'TomTom Vector Flow Tiles + estimativas CCDAE';
            $records[] = $snapshot['registro'];
            $updates[$viaId] = [...$snapshot['via_update'], ...($flowDirections[$viaId] ?? [])];
        }

        DB::transaction(function () use ($records, $updates): void {
            if ($records) {
                DB::table('registro_transitos')->upsert($records, ['via_transito_id', 'timestamp']);
            }
            foreach ($updates as $viaId => $values) {
                DB::table('via_transitos')->where('id', $viaId)->update($values);
            }
        });
    }

    private function recordUsage(array $metrics, Carbon $now): void
    {
        DB::table('traffic_api_usage')->updateOrInsert(
            ['provider' => 'tomtom', 'usage_date' => $now->toDateString(), 'endpoint' => 'vector_flow_tiles'],
            ['created_at' => $now, 'updated_at' => $now],
        );
        DB::table('traffic_api_usage')
            ->where('provider', 'tomtom')->where('usage_date', $now->toDateString())->where('endpoint', 'vector_flow_tiles')
            ->incrementEach([
                'requests' => $metrics['requests'],
                'successful_requests' => $metrics['successful'],
                'failed_requests' => $metrics['failed'],
            ], ['updated_at' => $now]);
    }

    private function numericProperty(array $segment, string $property): ?float
    {
        $value = $segment['properties'][$property] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function matchRelativeSegment(array $absolute, array $relativeSegments): ?array
    {
        $coordinates = $absolute['geometry']['coordinates'] ?? [];
        if (count($coordinates) < 2) {
            return null;
        }
        $middle = $coordinates[(int) floor(count($coordinates) / 2)];
        $absoluteVector = [
            $coordinates[array_key_last($coordinates)][0] - $coordinates[0][0],
            $coordinates[array_key_last($coordinates)][1] - $coordinates[0][1],
        ];
        $best = null;
        foreach ($relativeSegments as $relative) {
            if (($absolute['properties']['road_type'] ?? null) !== ($relative['properties']['road_type'] ?? null)
                || ($absolute['properties']['traffic_road_coverage'] ?? null) !== ($relative['properties']['traffic_road_coverage'] ?? null)) {
                continue;
            }
            $relativeCoordinates = $relative['geometry']['coordinates'] ?? [];
            for ($index = 1; $index < count($relativeCoordinates); $index++) {
                $candidate = $this->distanceToSegment($middle, $relativeCoordinates[$index - 1], $relativeCoordinates[$index]);
                $relativeVector = [
                    $relativeCoordinates[$index][0] - $relativeCoordinates[$index - 1][0],
                    $relativeCoordinates[$index][1] - $relativeCoordinates[$index - 1][1],
                ];
                $sameDirection = $absoluteVector[0] * $relativeVector[0] + $absoluteVector[1] * $relativeVector[1] >= 0;
                if (($absolute['properties']['traffic_road_coverage'] ?? null) === 'one_side' && ! $sameDirection) {
                    continue;
                }
                if ($candidate <= 40 && ($best === null || $candidate < $best['distance'])) {
                    $best = ['distance' => $candidate, 'segment' => $relative];
                }
            }
        }

        return $best['segment'] ?? null;
    }

    private function distanceToSegment(array $point, array $start, array $end): float
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

        return hypot($px - $ratio * $dx, $py - $ratio * $dy);
    }
}
