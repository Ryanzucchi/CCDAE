<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DistritoCaceresSeeder extends Seeder
{
    public function run(): void
    {
        // Coordenadas delimitadoras da área urbana de Cáceres - MT
        // Aproximadamente 25km² de cobertura central
        $latMin = -16.1000;
        $latMax = -16.0500;
        $lonMin = -57.7000;
        $lonMax = -57.6500;

        // Incremento de 0.0009 graus equivale a aproximadamente 100 metros
        $incremento = 0.0009;

        $distritos = [];
        $agora = Carbon::now();
        $count = 1;

        for ($lat = $latMin; $lat <= $latMax; $lat += $incremento) {
            for ($lon = $lonMin; $lon <= $lonMax; $lon += $incremento) {
                $distritos[] = [
                    'nome' => "Setor Urbano " . str_pad($count++, 3, '0', STR_PAD_LEFT),
                    'latitude' => round($lat, 6),
                    'longitude' => round($lon, 6),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];

                // Inserção em lotes (chunks) para performance e limite de memória
                if (count($distritos) >= 500) {
                    DB::table('distritos')->insert($distritos);
                    $distritos = [];
                }
            }
        }

        // Insere o restante
        if (!empty($distritos)) {
            DB::table('distritos')->insert($distritos);
        }
    }
}
