<?php

namespace Tests\Feature\Internet;

use App\Services\Geo\DistrictLocator;
use App\Services\Internet\AntennaImportService;
use App\Services\Internet\Contracts\AntennaProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AntennaImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_idempotent(): void
    {
        $provider = new class implements AntennaProvider
        {
            public function source(): string
            {
                return 'Teste';
            }

            public function antennasForBbox(array $bbox): array
            {
                return [['id' => 10, 'type' => 'node', 'lat' => -16.07, 'lon' => -57.68, 'tags' => ['communication:mobile_phone' => 'yes']]];
            }
        };
        $locator = $this->createMock(DistrictLocator::class);
        $locator->method('forPoint')->willReturn(null);
        $service = new AntennaImportService($provider, $locator);
        $this->assertSame(1, $service->sync([-58, -17, -57, -16])['created']);
        $this->assertSame(1, $service->sync([-58, -17, -57, -16])['updated']);
        $this->assertSame(1, DB::table('antenas')->count());
    }
}
