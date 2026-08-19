<?php

namespace App\Console\Commands;

use App\Services\Geo\BoundingBox;
use App\Services\Internet\AntennaImportService;
use Illuminate\Console\Command;
use Throwable;

class SyncRealAntennasCommand extends Command
{
    protected $signature = 'internet:sync-antennas {--bbox=-57.76,-16.15,-57.61,-15.99}';

    protected $description = 'Importa antenas e torres reais de telecomunicações';

    public function handle(AntennaImportService $importer): int
    {
        try {
            $result = $importer->sync(BoundingBox::parse((string) $this->option('bbox'), .25));
        } catch (Throwable $exception) {
            $this->error('Não foi possível sincronizar antenas: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info("Antenas reais sincronizadas: {$result['created']} criadas, {$result['updated']} atualizadas, {$result['ignored']} ignoradas.");

        return self::SUCCESS;
    }
}
