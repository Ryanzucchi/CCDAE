<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureBuildingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Schema::dropIfExists('osm_buildings');
        Schema::create('osm_buildings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('osm_id');
            $table->string('osm_type');
            $table->string('building')->nullable();
            $table->string('name')->nullable();
            $table->decimal('height')->nullable();
            $table->decimal('render_height');
            $table->decimal('render_min_height')->default(0);
            $table->decimal('building_levels')->nullable();
            $table->string('height_source');
            $table->json('geometry');
            $table->timestamps();
        });
        DB::table('osm_buildings')->insert([
            'osm_id' => 10, 'osm_type' => 'way', 'building' => 'house', 'name' => 'Casa', 'height' => null,
            'render_height' => 6, 'render_min_height' => 0, 'building_levels' => 2, 'height_source' => 'levels',
            'geometry' => json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[[-57.69, -16.08], [-57.68, -16.08], [-57.68, -16.07], [-57.69, -16.08]]]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('osm_buildings');
        parent::tearDown();
    }

    public function test_bbox_is_required_and_validated(): void
    {
        $this->getJson(route('api.infrastructure-map.buildings'))->assertStatus(422);
        $this->getJson(route('api.infrastructure-map.buildings', ['bbox' => '-200,-16,200,-15']))->assertStatus(422);
    }

    public function test_endpoint_returns_only_intersecting_buildings_as_geojson(): void
    {
        $this->getJson(route('api.infrastructure-map.buildings', ['bbox' => '-57.70,-16.09,-57.67,-16.06']))
            ->assertOk()->assertJsonPath('type', 'FeatureCollection')->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.geometry.type', 'MultiPolygon')->assertJsonPath('features.0.properties.osm_id', 10)
            ->assertJsonMissingPath('features.0.properties.tags');
        $this->getJson(route('api.infrastructure-map.buildings', ['bbox' => '-58,-17,-57.9,-16.9']))
            ->assertOk()->assertJsonCount(0, 'features');
    }
}
