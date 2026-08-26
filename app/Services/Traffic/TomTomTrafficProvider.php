<?php

namespace App\Services\Traffic;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TomTomTrafficProvider
{
    private const CIRCUIT_KEY = 'traffic:tomtom:circuit';

    public function configured(): bool
    {
        return filled(config('data_sources.traffic.tomtom_key'));
    }

    public function flow(float $latitude, float $longitude): ?array
    {
        if (! $this->available()) {
            return null;
        }

        try {
            $response = Http::timeout(config('data_sources.traffic.timeout'))->get(
                'https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json',
                ['key' => config('data_sources.traffic.tomtom_key'), 'point' => "{$latitude},{$longitude}", 'unit' => 'kmph'],
            );
        } catch (Throwable) {
            $this->openCircuit(null, 'provider_unreachable');

            return null;
        }

        if (! $response->successful()) {
            if (in_array($response->status(), [401, 402, 403, 429], true)) {
                $this->openCircuit($response->status(), 'provider_rejected_request');
            }

            return null;
        }
        $flow = $response->json('flowSegmentData');
        if (! is_array($flow) || ! isset($flow['currentSpeed'], $flow['freeFlowSpeed'])) {
            return null;
        }

        return $flow;
    }

    public function available(): bool
    {
        return $this->configured() && ! Cache::has(self::CIRCUIT_KEY);
    }

    public function status(): array
    {
        if (! $this->configured()) {
            return ['available' => false, 'reason' => 'not_configured', 'status' => null];
        }

        $circuit = Cache::get(self::CIRCUIT_KEY);
        if (is_array($circuit)) {
            return [
                'available' => false,
                'reason' => $circuit['reason'] ?? 'provider_rejected_request',
                'status' => $circuit['status'] ?? null,
            ];
        }

        return ['available' => true, 'reason' => null, 'status' => null];
    }

    private function openCircuit(?int $status, string $reason): void
    {
        $cooldown = max(60, (int) config('data_sources.traffic.provider_cooldown_seconds', 3600));
        Cache::put(self::CIRCUIT_KEY, ['status' => $status, 'reason' => $reason], now()->addSeconds($cooldown));

        Log::warning('Provedor TomTom temporariamente indisponível; coleta seguirá com fallback CCDAE.', [
            'http_status' => $status,
            'cooldown_seconds' => $cooldown,
        ]);
    }
}
