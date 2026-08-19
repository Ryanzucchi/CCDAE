<?php

namespace Tests\Feature\Osm;

use App\Services\Osm\BuildingGeometryFactory;
use App\Services\Osm\BuildingHeightResolver;
use App\Services\Osm\BuildingImportService;
use App\Services\Osm\Contracts\BuildingProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuildingImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('osm_buildings');
        Schema::create('osm_buildings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('osm_id');
            $table->string('osm_type');
            $table->string('building')->nullable();
            $table->string('name')->nullable();
            $table->decimal('height')->nullable();
            $table->decimal('building_levels')->nullable();
            $table->decimal('min_height')->nullable();
            $table->decimal('building_min_level')->nullable();
            $table->decimal('render_height');
            $table->decimal('render_min_height')->default(0);
            $table->string('height_source');
            $table->json('tags')->nullable();
            $table->timestamp('synced_at');
            $table->json('geometry')->nullable();
            $table->timestamps();
            $table->unique(['osm_type', 'osm_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('osm_buildings');
        parent::tearDown();
    }

    public function test_sync_upserts_without_duplicates(): void
    {
        $provider = new class implements BuildingProvider
        {
            public string $name = 'Primeiro';

            public function buildingsForBbox(array $bbox): array
            {
                return [[
                    'id' => 123, 'type' => 'way', 'tags' => ['building' => 'house', 'name' => $this->name, 'building:levels' => '2'],
                    'geometry' => [['lon' => -57.1, 'lat' => -16.1], ['lon' => -57.0, 'lat' => -16.1], ['lon' => -57.0, 'lat' => -16.0], ['lon' => -57.1, 'lat' => -16.1]],
                ]];
            }
        };
        $service = new BuildingImportService($provider, new BuildingGeometryFactory, new BuildingHeightResolver);
        $first = $service->sync([-57.2, -16.2, -57.0, -16.0]);
        $provider->name = 'Atualizado';
        $second = $service->sync([-57.2, -16.2, -57.0, -16.0]);
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertDatabaseCount('osm_buildings', 1);
        $this->assertDatabaseHas('osm_buildings', ['osm_id' => 123, 'osm_type' => 'way', 'name' => 'Atualizado', 'height_source' => 'levels']);
    }
}
