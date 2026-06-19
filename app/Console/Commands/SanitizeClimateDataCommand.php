<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Climate\ClimateSanitizerService;

class SanitizeClimateDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'climate:sanitize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sanitiza os dados climáticos agrupando distritos vizinhos com registros idênticos.';

    /**
     * Execute the console command.
     */
    public function handle(ClimateSanitizerService $sanitizerService)
    {
        $this->info('Iniciando sanitização de dados climáticos...');
        
        try {
            $deleted = $sanitizerService->sanitize();
            $this->info("Sanitização concluída com sucesso! {$deleted} registros redundantes apagados.");
        } catch (\Exception $e) {
            $this->error("Erro durante a sanitização: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
