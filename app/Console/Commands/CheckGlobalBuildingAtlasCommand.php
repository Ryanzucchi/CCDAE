<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CheckGlobalBuildingAtlasCommand extends Command
{
    protected $signature = 'gba:check-buildings';

    protected $description = 'Verifica a instalação local dos arquivos do GlobalBuildingAtlas';

    public function handle(): int
    {
        $directory = (string) config('osm.buildings.global_atlas_path');
        $this->line('Diretório: '.$directory);
        if (! is_dir($directory)) {
            $this->error('Diretório inexistente. Crie-o e copie para ele os GeoJSON GBA.ODbLPolygon oficiais.');
            return self::FAILURE;
        }

        $count = 0;
        $bytes = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['geojson', 'json'], true)) continue;
            $count++;
            $bytes += $file->getSize();
        }
        $this->table(['Arquivos GeoJSON', 'Tamanho'], [[$count, number_format($bytes / 1048576, 2, ',', '.').' MB']]);
        if ($count === 0) {
            $this->warn('Diretório pronto, porém vazio. Baixe os tiles GBA.ODbLPolygon necessários antes da sincronização.');
            $this->line('Consulte: docs/GLOBAL_BUILDING_ATLAS.md');
            return self::INVALID;
        }
        $this->info('GlobalBuildingAtlas pronto para importação.');
        return self::SUCCESS;
    }
}
