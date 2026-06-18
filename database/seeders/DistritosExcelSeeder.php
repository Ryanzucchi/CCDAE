<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Distrito;

class DistritosExcelSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = base_path('distritos.csv');
        if (!file_exists($csvPath)) {
            $this->command->error('Arquivo distritos.csv não encontrado no diretório raiz do projeto!');
            return;
        }

        $file = fopen($csvPath, 'r');
        $headers = fgetcsv($file);
        
        if ($headers === false) {
            $this->command->error('O arquivo CSV está vazio ou é inválido.');
            return;
        }

        // Remover BOM (Byte Order Mark) do primeiro cabeçalho se existir
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        while (($row = fgetcsv($file)) !== false) {
            // Pular linhas vazias
            if (count($headers) !== count($row)) {
                continue;
            }
            $data = array_combine($headers, $row);
            
            $id = $data['id'] ?? null;
            
            $geojson = $data['geojson'] ?? null;
            if ($geojson) {
                // Tenta decodificar caso seja uma string JSON válida
                $decoded = json_decode($geojson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $geojson = $decoded;
                }
            }

            $distritoData = [
                'nome' => $data['nome'] ?? 'Desconhecido',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'cidade' => $data['cidade'] ?? 'Cáceres',
                'geojson' => $geojson,
            ];

            if ($id) {
                Distrito::updateOrCreate(['id' => $id], $distritoData);
            } else {
                Distrito::create($distritoData);
            }
        }
        fclose($file);
        
        $this->command->info('Distritos importados com sucesso a partir do arquivo distritos.csv.');
    }
}
