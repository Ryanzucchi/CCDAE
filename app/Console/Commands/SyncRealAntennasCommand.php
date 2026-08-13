<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncRealAntennasCommand extends Command
{
    protected $signature = 'internet:sync-antennas {--bbox=-57.76,-16.15,-57.61,-15.99}';
    protected $description = 'Importa antenas e torres reais de telecomunicações do OpenStreetMap';

    public function handle(): int
    {
        $bbox = array_map('floatval', explode(',', $this->option('bbox')));
        if (count($bbox) !== 4 || $bbox[0] >= $bbox[2] || $bbox[1] >= $bbox[3]) { $this->error('BBox inválido.'); return self::FAILURE; }
        [$west, $south, $east, $north] = $bbox;
        $query = "[out:json][timeout:90];(nwr[\"communication:mobile_phone\"]({$south},{$west},{$north},{$east});nwr[\"tower:type\"=\"communication\"]({$south},{$west},{$north},{$east});nwr[\"telecom\"=\"antenna\"]({$south},{$west},{$north},{$east});nwr[\"man_made\"=\"communications_tower\"]({$south},{$west},{$north},{$east}););out center tags;";
        try {
            $response = Http::asForm()->withHeaders(['User-Agent' => 'CCDAE/1.0 contato-administrativo'])
                ->timeout(config('data_sources.antennas.timeout'))->retry(3, 1000, throw: false)
                ->post(config('data_sources.antennas.overpass_url'), ['data' => $query]);
        } catch (Throwable $exception) {
            $this->error('Não foi possível consultar o Overpass: '.$exception->getMessage()); return self::FAILURE;
        }
        if (! $response->successful()) { $this->error('Overpass respondeu HTTP '.$response->status()); return self::FAILURE; }
        $created = 0; $updated = 0;
        foreach ($response->json('elements', []) as $element) {
            $latitude = $element['lat'] ?? $element['center']['lat'] ?? null; $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;
            if ($latitude === null || $longitude === null) continue;
            $tags = $element['tags'] ?? []; $externalId = ($element['type'] ?? 'node').':'.$element['id'];
            $attributes = ['latitude' => $latitude, 'longitude' => $longitude, 'tipo_sinal' => $this->signal($tags),
                'proprietario' => $tags['operator'] ?? $tags['owner'] ?? null, 'estado_conservacao' => 'bom',
                'codigo_patrimonio' => 'OSM-'.str_replace(':', '-', $externalId), 'tags_origem' => json_encode($tags), 'sincronizado_em' => now(),
                'distrito_id' => DB::table('distritos')->whereRaw('ST_Intersects(geom, ST_SetSRID(ST_Point(?, ?), 4326))', [$longitude, $latitude])->value('id')];
            $exists = DB::table('antenas')->where(['fonte_dado' => 'OpenStreetMap', 'external_id' => $externalId])->exists();
            DB::table('antenas')->updateOrInsert(['fonte_dado' => 'OpenStreetMap', 'external_id' => $externalId], $attributes);
            $exists ? $updated++ : $created++;
        }
        $this->info("Antenas reais sincronizadas: {$created} criadas, {$updated} atualizadas.");
        return self::SUCCESS;
    }

    private function signal(array $tags): string
    {
        if (($tags['communication:mobile_phone'] ?? null) === 'yes') return 'Telefonia móvel';
        return match ($tags['communication:radio'] ?? null) { 'fm' => 'Rádio FM', 'am' => 'Rádio AM', default => 'Telecomunicações' };
    }
}
