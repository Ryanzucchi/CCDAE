<?php

namespace App\Services\Traffic;

class OsmRoadAttributeMapper
{
    public function map(array $tags): array
    {
        $highway = strtolower((string) ($tags['highway'] ?? 'residential'));
        $flowDirection = $this->flowDirection($tags);
        $oneWay = $flowDirection !== 'ambos';
        $inferred = [];

        $lanes = $this->positiveInteger($tags['lanes'] ?? null);
        if ($lanes === null) {
            $forward = $this->positiveInteger($tags['lanes:forward'] ?? null);
            $backward = $this->positiveInteger($tags['lanes:backward'] ?? null);
            $lanes = ($forward !== null || $backward !== null) ? ($forward ?? 0) + ($backward ?? 0) : null;
        }
        if ($lanes === null || $lanes < 1) {
            $lanes = $oneWay ? 1 : 2;
            $inferred[] = 'numero_faixas';
        }

        $speedLimit = $this->speedLimit($tags['maxspeed'] ?? null);
        if ($speedLimit === null) {
            $speedLimit = match ($highway) {
                'trunk', 'trunk_link' => 80,
                'primary', 'primary_link' => 60,
                'secondary', 'secondary_link' => 50,
                'tertiary', 'tertiary_link' => 40,
                default => 30,
            };
            $inferred[] = 'limite_velocidade';
        }

        $surface = $this->surface($tags);
        if ($surface === 'desconhecido') {
            $inferred[] = 'estado_pavimento';
        }

        return [
            'tipo' => $highway,
            'numero_faixas' => $lanes,
            'sentido' => $oneWay ? 'unica' : 'dupla',
            'direcao_fluxo' => $flowDirection,
            'limite_velocidade' => $speedLimit,
            'estado_pavimento' => $surface,
            'inclinacao_pista' => $this->incline($tags['incline'] ?? null),
            '_inferred' => $inferred,
        ];
    }

    private function flowDirection(array $tags): string
    {
        $oneWay = strtolower((string) ($tags['oneway'] ?? ''));

        if ($oneWay === '-1') {
            return 'fim_inicio';
        }

        if (in_array($oneWay, ['no', 'false', '0'], true)) {
            return 'ambos';
        }

        if (in_array($oneWay, ['yes', 'true', '1'], true)
            || strtolower((string) ($tags['junction'] ?? '')) === 'roundabout') {
            return 'inicio_fim';
        }

        return 'ambos';
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_scalar($value) || ! preg_match('/\d+/', (string) $value, $matches)) {
            return null;
        }

        $parsed = (int) $matches[0];

        return $parsed > 0 ? $parsed : null;
    }

    private function speedLimit(mixed $value): ?int
    {
        if (! is_scalar($value) || ! preg_match('/\d+(?:[.,]\d+)?/', (string) $value, $matches)) {
            return null;
        }

        $speed = (float) str_replace(',', '.', $matches[0]);
        if (str_contains(strtolower((string) $value), 'mph')) {
            $speed *= 1.60934;
        }

        return max(1, (int) round($speed));
    }

    private function incline(mixed $value): ?float
    {
        if (! is_scalar($value) || ! preg_match('/-?\d+(?:[.,]\d+)?/', (string) $value, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }

    private function surface(array $tags): string
    {
        $smoothness = strtolower((string) ($tags['smoothness'] ?? ''));
        if (in_array($smoothness, ['excellent', 'good'], true)) {
            return 'bom';
        }
        if ($smoothness === 'intermediate') {
            return 'regular';
        }
        if (in_array($smoothness, ['bad', 'very_bad', 'horrible', 'very_horrible', 'impassable'], true)) {
            return 'ruim';
        }

        return match (strtolower((string) ($tags['surface'] ?? ''))) {
            'asphalt' => 'asfalto',
            'concrete', 'concrete:lanes', 'concrete:plates' => 'concreto',
            'paving_stones', 'sett', 'cobblestone' => 'paver',
            'compacted', 'fine_gravel', 'gravel' => 'cascalho',
            'ground', 'dirt', 'earth', 'sand', 'unpaved' => 'terra',
            default => 'desconhecido',
        };
    }
}
