<?php

namespace Tests\Unit\Traffic;

use App\Services\Traffic\TomTomTrafficProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TomTomTrafficProviderTest extends TestCase
{
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
}
