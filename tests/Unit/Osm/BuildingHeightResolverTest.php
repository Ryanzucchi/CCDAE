<?php

namespace Tests\Unit\Osm;

use App\Services\Osm\BuildingHeightResolver;
use Tests\TestCase;

class BuildingHeightResolverTest extends TestCase
{
    public function test_real_height_has_priority(): void
    {
        $result = app(BuildingHeightResolver::class)->resolve(['height' => '12.5 m', 'building:levels' => '9']);
        $this->assertSame(12.5, $result['height']);
        $this->assertSame(12.5, $result['render_height']);
        $this->assertSame('osm_height', $result['height_source']);
    }

    public function test_levels_are_used_without_persisting_an_estimated_real_height(): void
    {
        config()->set('osm.buildings.meters_per_level', 3.2);
        $result = app(BuildingHeightResolver::class)->resolve(['building:levels' => '4']);
        $this->assertNull($result['height']);
        $this->assertSame(12.8, $result['render_height']);
        $this->assertSame('levels', $result['height_source']);
    }

    public function test_default_render_height_is_used_when_osm_has_no_height(): void
    {
        config()->set('osm.buildings.default_height', 7.0);
        $result = app(BuildingHeightResolver::class)->resolve([]);
        $this->assertNull($result['height']);
        $this->assertSame(7.0, $result['render_height']);
        $this->assertSame('default', $result['height_source']);
    }
}
