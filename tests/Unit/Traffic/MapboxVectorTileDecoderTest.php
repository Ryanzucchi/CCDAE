<?php

namespace Tests\Unit\Traffic;

use App\Services\Traffic\MapboxVectorTileDecoder;
use PHPUnit\Framework\TestCase;

class MapboxVectorTileDecoderTest extends TestCase
{
    public function test_decodes_tomtom_traffic_flow_properties_and_geometry(): void
    {
        $keys = ['road_type', 'traffic_level', 'traffic_road_coverage'];
        $values = [
            $this->field(1, 2, 'Major road'),
            $this->field(3, 1, pack('e', 42.5)),
            $this->field(1, 2, 'one_side'),
        ];
        $tags = [0, 0, 1, 1, 2, 2];
        $geometry = [9, 4096, 4096, 10, 1024, 0];
        $feature = $this->field(2, 2, $this->packed($tags))
            .$this->field(3, 0, 2)
            .$this->field(4, 2, $this->packed($geometry));
        $layer = $this->field(1, 2, 'Traffic flow')
            .$this->field(2, 2, $feature)
            .implode('', array_map(fn (string $key): string => $this->field(3, 2, $key), $keys))
            .implode('', array_map(fn (string $value): string => $this->field(4, 2, $value), $values))
            .$this->field(5, 0, 4096)
            .$this->field(15, 0, 2);

        $segments = (new MapboxVectorTileDecoder)->decodeTrafficFlow($this->field(3, 2, $layer), 0, 0, 0);

        $this->assertCount(1, $segments);
        $this->assertSame('Major road', $segments[0]['properties']['road_type']);
        $this->assertSame(42.5, $segments[0]['properties']['traffic_level']);
        $this->assertSame('one_side', $segments[0]['properties']['traffic_road_coverage']);
        $this->assertEqualsWithDelta([0.0, 0.0], $segments[0]['geometry']['coordinates'][0], 0.000001);
        $this->assertEqualsWithDelta([45.0, 0.0], $segments[0]['geometry']['coordinates'][1], 0.000001);
    }

    private function field(int $number, int $wire, string|int $value): string
    {
        $encoded = $this->varint(($number << 3) | $wire);

        return $wire === 2 ? $encoded.$this->varint(strlen((string) $value)).$value : $encoded.($wire === 0 ? $this->varint($value) : $value);
    }

    private function packed(array $values): string
    {
        return implode('', array_map(fn (int $value): string => $this->varint($value), $values));
    }

    private function varint(int $value): string
    {
        $encoded = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $encoded .= chr($value ? $byte | 0x80 : $byte);
        } while ($value);

        return $encoded;
    }
}
