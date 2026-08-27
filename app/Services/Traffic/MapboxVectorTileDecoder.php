<?php

namespace App\Services\Traffic;

use RuntimeException;

/**
 * Minimal Mapbox Vector Tile v2 decoder for TomTom Traffic Flow LineStrings.
 */
class MapboxVectorTileDecoder
{
    public function decodeTrafficFlow(string $payload, int $zoom, int $tileX, int $tileY): array
    {
        $segments = [];
        foreach ($this->fields($payload) as [$number, $wire, $value]) {
            if ($number !== 3 || $wire !== 2) {
                continue;
            }

            $layer = $this->decodeLayer($value);
            if (strcasecmp($layer['name'], 'Traffic flow') !== 0) {
                continue;
            }

            foreach ($layer['features'] as $feature) {
                if ($feature['type'] !== 2) {
                    continue;
                }
                $properties = $this->decodeTags($feature['tags'], $layer['keys'], $layer['values']);
                $lines = $this->decodeLineStrings($feature['geometry']);
                foreach ($lines as $lineIndex => $line) {
                    if (count($line) < 2) {
                        continue;
                    }
                    $coordinates = array_map(
                        fn (array $point): array => $this->tilePointToLongitudeLatitude(
                            $point[0], $point[1], $layer['extent'], $zoom, $tileX, $tileY,
                        ),
                        $line,
                    );
                    $segments[] = [
                        'feature_key' => hash('sha256', implode(',', $feature['geometry']).":{$lineIndex}"),
                        'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
                        'properties' => $properties,
                    ];
                }
            }
        }

        return $segments;
    }

    private function decodeLayer(string $payload): array
    {
        $name = '';
        $extent = 4096;
        $features = [];
        $keys = [];
        $values = [];
        foreach ($this->fields($payload) as [$number, $wire, $value]) {
            if ($number === 1 && $wire === 2) {
                $name = $value;
            } elseif ($number === 2 && $wire === 2) {
                $features[] = $this->decodeFeature($value);
            } elseif ($number === 3 && $wire === 2) {
                $keys[] = $value;
            } elseif ($number === 4 && $wire === 2) {
                $values[] = $this->decodeValue($value);
            } elseif ($number === 5 && $wire === 0) {
                $extent = $value;
            }
        }

        return compact('name', 'extent', 'features', 'keys', 'values');
    }

    private function decodeFeature(string $payload): array
    {
        $type = 0;
        $tags = [];
        $geometry = [];
        foreach ($this->fields($payload) as [$number, $wire, $value]) {
            if ($number === 2) {
                $tags = $wire === 2 ? $this->packedVarints($value) : [...$tags, $value];
            } elseif ($number === 3 && $wire === 0) {
                $type = $value;
            } elseif ($number === 4) {
                $geometry = $wire === 2 ? $this->packedVarints($value) : [...$geometry, $value];
            }
        }

        return compact('type', 'tags', 'geometry');
    }

    private function decodeValue(string $payload): mixed
    {
        foreach ($this->fields($payload) as [$number, $wire, $value]) {
            return match ($number) {
                1 => $value,
                2 => unpack('g', $value)[1],
                3 => unpack('e', $value)[1],
                4, 5 => $value,
                6 => $this->zigZagDecode($value),
                7 => (bool) $value,
                default => null,
            };
        }

        return null;
    }

    private function decodeTags(array $tags, array $keys, array $values): array
    {
        $properties = [];
        for ($index = 0; $index + 1 < count($tags); $index += 2) {
            if (isset($keys[$tags[$index]]) && array_key_exists($tags[$index + 1], $values)) {
                $properties[$keys[$tags[$index]]] = $values[$tags[$index + 1]];
            }
        }

        return $properties;
    }

    private function decodeLineStrings(array $commands): array
    {
        $lines = [];
        $currentLine = null;
        $cursorX = 0;
        $cursorY = 0;
        $index = 0;
        while ($index < count($commands)) {
            $commandInteger = $commands[$index++];
            $command = $commandInteger & 0x7;
            $count = $commandInteger >> 3;
            if ($command === 7) {
                continue;
            }
            if (! in_array($command, [1, 2], true)) {
                throw new RuntimeException("Comando de geometria MVT desconhecido: {$command}");
            }
            for ($pointIndex = 0; $pointIndex < $count; $pointIndex++) {
                if ($index + 1 >= count($commands)) {
                    throw new RuntimeException('Geometria MVT truncada.');
                }
                $cursorX += $this->zigZagDecode($commands[$index++]);
                $cursorY += $this->zigZagDecode($commands[$index++]);
                if ($command === 1) {
                    $lines[] = [[$cursorX, $cursorY]];
                    $currentLine = array_key_last($lines);
                } elseif ($currentLine !== null) {
                    $lines[$currentLine][] = [$cursorX, $cursorY];
                }
            }
        }

        return $lines;
    }

    private function tilePointToLongitudeLatitude(
        int $pointX,
        int $pointY,
        int $extent,
        int $zoom,
        int $tileX,
        int $tileY,
    ): array {
        $scale = 2 ** $zoom;
        $worldX = ($tileX + $pointX / $extent) / $scale;
        $worldY = ($tileY + $pointY / $extent) / $scale;

        return [
            round($worldX * 360 - 180, 7),
            round(rad2deg(atan(sinh(M_PI * (1 - 2 * $worldY)))), 7),
        ];
    }

    private function fields(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $length = strlen($payload);
        while ($offset < $length) {
            $key = $this->readVarint($payload, $offset);
            $number = $key >> 3;
            $wire = $key & 0x7;
            $value = match ($wire) {
                0 => $this->readVarint($payload, $offset),
                1 => $this->readBytes($payload, $offset, 8),
                2 => $this->readBytes($payload, $offset, $this->readVarint($payload, $offset)),
                5 => $this->readBytes($payload, $offset, 4),
                default => throw new RuntimeException("Tipo protobuf não suportado: {$wire}"),
            };
            $fields[] = [$number, $wire, $value];
        }

        return $fields;
    }

    private function packedVarints(string $payload): array
    {
        $values = [];
        $offset = 0;
        while ($offset < strlen($payload)) {
            $values[] = $this->readVarint($payload, $offset);
        }

        return $values;
    }

    private function readVarint(string $payload, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        $length = strlen($payload);
        do {
            if ($offset >= $length || $shift > 63) {
                throw new RuntimeException('Varint protobuf inválido ou truncado.');
            }
            $byte = ord($payload[$offset++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) !== 0);

        return $value;
    }

    private function readBytes(string $payload, int &$offset, int $length): string
    {
        if ($length < 0 || $offset + $length > strlen($payload)) {
            throw new RuntimeException('Campo protobuf truncado.');
        }
        $value = substr($payload, $offset, $length);
        $offset += $length;

        return $value;
    }

    private function zigZagDecode(int $value): int
    {
        return ($value >> 1) ^ (-($value & 1));
    }
}
