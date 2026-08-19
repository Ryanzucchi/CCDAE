<?php

namespace App\Services\Traffic;

use Illuminate\Support\Facades\Http;
use Throwable;

class TomTomTrafficProvider
{
    public function configured(): bool
    {
        return filled(config('data_sources.traffic.tomtom_key'));
    }

    public function flow(float $latitude, float $longitude): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = Http::timeout(config('data_sources.traffic.timeout'))->retry(2, 300)->get(
                'https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json',
                ['key' => config('data_sources.traffic.tomtom_key'), 'point' => "{$latitude},{$longitude}", 'unit' => 'kmph'],
            );
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }
        $flow = $response->json('flowSegmentData');
        if (! is_array($flow) || ! isset($flow['currentSpeed'], $flow['freeFlowSpeed'])) {
            return null;
        }

        return $flow;
    }
}
