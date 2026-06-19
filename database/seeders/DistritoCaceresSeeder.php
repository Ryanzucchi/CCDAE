<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Distrito;

class DistritoCaceresSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = storage_path('app/distritos_reais.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->error("Arquivo CSV não encontrado em: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        while (($data = fgetcsv($file, 0, ',', '"')) !== false) {
            // Estrutura do CSV: id, nome, lat, lng, cidade, created_at, updated_at, geojson
            if (count($data) >= 8) {
                $id = (int)$data[0];
                $nome = trim($data[1]);
                $lat = floatval($data[2]);
                $lng = floatval($data[3]);
                $cidade = trim($data[4]);
                $geojsonStr = $data[7];

                // Remove "" extra de escape do CSV e transforma num JSON válido
                $geojsonStr = str_replace('""', '"', $geojsonStr);
                $geojsonStr = trim($geojsonStr, '"');
                
                $geojson = json_decode($geojsonStr, true);

                if (!$geojson) {
                    $this->command->warn("GeoJSON inválido para $nome. Pulando...");
                    continue;
                }

                Distrito::updateOrCreate(
                    ['id' => $id],
                    [
                        'nome' => $nome,
                        'cidade' => $cidade,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'geojson' => $geojson
                    ]
                );
            }
        }
        fclose($file);
        
        $this->command->info('Distritos reais carregados com sucesso.');
    }
}
