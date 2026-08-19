<?php

namespace Tests\Unit\Osm;

use App\Services\Osm\BuildingGeometryFactory;
use App\Services\Osm\GlobalBuildingAtlasProvider;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GlobalBuildingAtlasProviderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/gba-provider-'.bin2hex(random_bytes(5));
        mkdir($this->directory, 0777, true);
        Config::set('osm.buildings.global_atlas_path', $this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_reads_bbox_and_converts_web_mercator_to_wgs84(): void
    {
        file_put_contents($this->directory.'/tile.geojson', json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature', 'id' => 'gba-10',
                'properties' => ['height' => 12.5],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                    [-6421000, -1818000], [-6420000, -1818000], [-6420000, -1817000], [-6421000, -1817000], [-6421000, -1818000],
                ]]],
            ]],
        ], JSON_THROW_ON_ERROR));

        $buildings = (new GlobalBuildingAtlasProvider)->buildingsForBbox([-58, -17, -57, -15]);

        $this->assertCount(1, $buildings);
        $this->assertSame('gba', $buildings[0]['type']);
        $this->assertArrayNotHasKey('height', $buildings[0]['tags']);
        $this->assertSame('GlobalBuildingAtlas ODbLPolygon', $buildings[0]['tags']['source']);
        $this->assertSame('MultiPolygon', (new BuildingGeometryFactory)->fromElement($buildings[0])['type']);
    }
}
