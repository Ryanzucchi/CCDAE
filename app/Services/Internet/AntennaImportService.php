<?php

namespace App\Services\Internet;

use App\Services\Geo\DistrictLocator;
use App\Services\Internet\Contracts\AntennaProvider;
use Illuminate\Support\Facades\DB;

class AntennaImportService
{
    public function __construct(private readonly AntennaProvider $provider, private readonly DistrictLocator $districts) {}

    public function sync(array $bbox): array
    {
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $source = $this->provider->source();

        $existingIds = DB::table('antenas')->where('fonte_dado', $source)->pluck('id', 'external_id')->all();

        foreach ($this->provider->antennasForBbox($bbox) as $element) {
            $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
            $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;
            if ($latitude === null || $longitude === null || ! isset($element['id'])) {
                $ignored++;

                continue;
            }
            $tags = $element['tags'] ?? [];
            $externalId = ($element['type'] ?? 'node').':'.$element['id'];
            $identity = ['fonte_dado' => $source, 'external_id' => $externalId];
            $exists = isset($existingIds[$externalId]);

            DB::table('antenas')->updateOrInsert($identity, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'tipo_sinal' => $this->signal($tags),
                'proprietario' => $tags['operator'] ?? $tags['owner'] ?? null,
                'estado_conservacao' => 'bom',
                'codigo_patrimonio' => 'OSM-'.str_replace(':', '-', $externalId),
                'tags_origem' => json_encode($tags),
                'sincronizado_em' => now(),
                'distrito_id' => $this->districts->forPoint((float) $longitude, (float) $latitude),
            ]);

            if ($exists) {
                $updated++;
            } else {
                $created++;
                $existingIds[$externalId] = true;
            }
        }

        return compact('created', 'updated', 'ignored');
    }

    private function signal(array $tags): string
    {
        if (($tags['communication:mobile_phone'] ?? null) === 'yes') {
            return 'Telefonia móvel';
        }

        return match ($tags['communication:radio'] ?? null) {
            'fm' => 'Rádio FM',
            'am' => 'Rádio AM',
            default => 'Telecomunicações',
        };
    }
}
