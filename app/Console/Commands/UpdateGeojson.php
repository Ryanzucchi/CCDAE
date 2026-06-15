<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateGeojson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-geojson';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza os distritos com polígonos geojson para visualização.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $distritos = DB::table('distritos')->get();

        foreach ($distritos as $d) {
            if (!$d->latitude || !$d->longitude) continue;
            
            $lat = (float) $d->latitude;
            $lng = (float) $d->longitude;
            
            // Largura e Altura para simular bairros grandes
            $w = 0.005; 
            $h = 0.005;
            
            $geojson = [
                "type" => "Polygon",
                "coordinates" => [
                    [
                        [$lng - $w, $lat - $h],
                        [$lng + $w, $lat - $h],
                        [$lng + $w, $lat + $h],
                        [$lng - $w, $lat + $h],
                        [$lng - $w, $lat - $h]
                    ]
                ]
            ];
            
            DB::table('distritos')->where('id', $d->id)->update(['geojson' => json_encode($geojson)]);
        }

        $this->info("GeoJSON Atualizado com Sucesso!");
    }
}
