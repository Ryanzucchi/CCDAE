<?php
namespace App\Services\Internet;
use App\Services\Internet\Contracts\AntennaProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class OverpassAntennaProvider implements AntennaProvider
{
    public function antennasForBbox(array $bbox): array
    {
        [$west,$south,$east,$north]=$bbox;
        $query="[out:json][timeout:90];(nwr[\"communication:mobile_phone\"]({$south},{$west},{$north},{$east});nwr[\"tower:type\"=\"communication\"]({$south},{$west},{$north},{$east});nwr[\"telecom\"=\"antenna\"]({$south},{$west},{$north},{$east});nwr[\"man_made\"=\"communications_tower\"]({$south},{$west},{$north},{$east}););out center tags;";
        $response=Http::asForm()->withHeaders(['User-Agent'=>'CCDAE/1.0 contato-administrativo'])->timeout(config('data_sources.antennas.timeout'))->retry(3,1000,throw:false)->post(config('data_sources.antennas.overpass_url'),['data'=>$query]);
        if($response->status()===429) throw new RuntimeException('A Overpass API limitou temporariamente as consultas.');
        if(!$response->successful()) throw new RuntimeException('Overpass respondeu HTTP '.$response->status().'.');
        return $response->json('elements',[]);
    }
    public function source(): string { return 'OpenStreetMap'; }
}
