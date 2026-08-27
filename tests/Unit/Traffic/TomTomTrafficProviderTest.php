<?php

namespace Tests\Unit\Traffic;

use App\Services\Traffic\TomTomTrafficProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TomTomTrafficProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('traffic:tomtom:circuit');
    }

    public function test_returns_normalized_real_flow_when_configured(): void
    {
        config(['data_sources.traffic.tomtom_key' => 'test-key']);
        Http::fake(['api.tomtom.com/*' => Http::response(['flowSegmentData' => ['currentSpeed' => 32, 'freeFlowSpeed' => 60, 'confidence' => .91]])]);

        $flow = app(TomTomTrafficProvider::class)->flow(-16.07, -57.68);

        $this->assertSame(32, $flow['currentSpeed']);
        $this->assertSame(.91, $flow['confidence']);
    }

    public function test_does_not_call_provider_without_key(): void
    {
        config(['data_sources.traffic.tomtom_key' => null]);
        Http::fake();
        $this->assertNull(app(TomTomTrafficProvider::class)->flow(-16.07, -57.68));
        Http::assertNothingSent();
    }

    public function test_opens_circuit_after_provider_rejects_request(): void
    {
        config([
            'data_sources.traffic.tomtom_key' => 'test-key',
            'data_sources.traffic.provider_cooldown_seconds' => 3600,
        ]);
        Http::fake(['api.tomtom.com/*' => Http::response(['error' => ['description' => 'No credits']], 403)]);

        $provider = app(TomTomTrafficProvider::class);

        $this->assertNull($provider->flow(-16.07, -57.68));
        $this->assertFalse($provider->available());
        $this->assertSame(403, $provider->status()['status']);
        $this->assertNull($provider->flow(-16.07, -57.68));
        Http::assertSentCount(1);
    }

    public function test_fetches_absolute_and_relative_vector_tiles(): void
    {
        config(['data_sources.traffic.tomtom_key' => 'test-key']);
        Http::fake([
            '*flow/absolute/*' => Http::response('absolute-pbf', 200, ['Content-Type' => 'application/x-protobuf']),
            '*flow/relative/*' => Http::response('relative-pbf', 200, ['Content-Type' => 'application/x-protobuf']),
        ]);

        $tiles = app(TomTomTrafficProvider::class)->vectorFlowTiles([
            ['id' => 7, 'zoom' => 13, 'x' => 2783, 'y' => 4467],
        ]);

        $this->assertSame('absolute-pbf', $tiles[7]['absolute']);
        $this->assertSame('relative-pbf', $tiles[7]['relative']);
        $this->assertSame(2, $tiles[7]['successful']);
        Http::assertSentCount(2);
    }
}
