<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Distrito;
use Illuminate\Support\Facades\File;

class GenerateDistritoSeeder extends Command
{
    protected $signature = 'make:distrito-seeder';
    protected $description = 'Generates DistritoSeeder based on current database records with spell checked names';

    public function handle()
    {
        $distritos = Distrito::all();
        
        $phpCode = "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\nuse App\\Models\\Distrito;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass DistritoSeeder extends Seeder\n{\n    public function run(): void\n    {\n        DB::statement('TRUNCATE TABLE distritos CASCADE;');\n\n        \$distritos = [\n";

        foreach ($distritos as $d) {
            $nome = $this->fixName($d->nome);
            $lat = $d->latitude ?? 'null';
            $lng = $d->longitude ?? 'null';
            $cidade = $d->cidade;
            $geojson = json_encode($d->geojson);
            $geojsonEscaped = str_replace("'", "\\'", $geojson);
            
            $phpCode .= "            [\n";
            $phpCode .= "                'nome' => '{$nome}',\n";
            $phpCode .= "                'latitude' => {$lat},\n";
            $phpCode .= "                'longitude' => {$lng},\n";
            $phpCode .= "                'cidade' => '{$cidade}',\n";
            $phpCode .= "                'geojson' => '{$geojsonEscaped}',\n";
            $phpCode .= "                'created_at' => now(),\n";
            $phpCode .= "                'updated_at' => now(),\n";
            $phpCode .= "            ],\n";
        }

        $phpCode .= "        ];\n\n        Distrito::insert(\$distritos);\n    }\n}\n";

        File::put(database_path('seeders/DistritoSeeder.php'), $phpCode);
        $this->info('DistritoSeeder.php gerado com sucesso!');
    }

    private function fixName($name)
    {
        $parts = explode(' ', trim($name));
        $capitalized = [];
        $prepositions = ['da', 'de', 'do', 'das', 'dos'];

        foreach ($parts as $p) {
            if (in_array(strtolower($p), $prepositions)) {
                $capitalized[] = strtolower($p);
            } else {
                // Capitalize properly considering utf-8
                $capitalized[] = mb_convert_case($p, MB_CASE_TITLE, "UTF-8");
            }
        }

        $finalName = implode(' ', $capitalized);
        $finalName = str_replace([' 1', ' 2', ' 3'], [' I', ' II', ' III'], $finalName);

        return $finalName;
    }
}
