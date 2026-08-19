<?php

namespace App\Services\Health;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SystemHealthService
{
    public function getHealthReport(): array
    {
        $bottlenecks = [];

        $lastSeen = Cache::get('queue_worker_last_seen');
        $isWorkerRunning = false;
        $workerStatusText = 'Inativo';

        if ($lastSeen !== null) {
            $diff = now()->timestamp - $lastSeen;
            if ($diff <= 600) {
                $isWorkerRunning = true;
                $workerStatusText = "Ativo (último sinal: há {$diff}s)";
            } else {
                $workerStatusText = "Inativo (último sinal: há {$diff}s)";
                $bottlenecks[] = ['type' => 'danger', 'msg' => 'Worker inativo ou atrasado! Último sinal de vida foi há mais de 2 minutos. O sistema parou de coletar clima.'];
            }
        } else {
            $bottlenecks[] = ['type' => 'danger', 'msg' => 'Nenhum sinal do Queue Worker. Ele não foi iniciado ou caiu.'];
        }

        $lastJob = null;
        if (Schema::hasTable('activity_log')) {
            try {
                $lastJob = DB::table('activity_log')
                    ->where('log_name', 'climate_collection')
                    ->latest('id')
                    ->first();
            } catch (\Throwable) {
                // Ignore query failure
            }
        }

        // Database and System Information
        $dbVersion = 'Desconhecido';
        $dbSize = 'Desconhecido';
        $dbLatency = 0;
        try {
            $pdo = DB::connection()->getPdo();
            $dbVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            $startPing = microtime(true);
            DB::select('SELECT 1');
            $dbLatency = round((microtime(true) - $startPing) * 1000, 2);

            if (DB::getDriverName() === 'pgsql') {
                $sizeQuery = DB::select('SELECT pg_size_pretty(pg_database_size(current_database())) as size');
                if (!empty($sizeQuery)) {
                    $dbSize = $sizeQuery[0]->size;
                }
            }
        } catch (\Throwable) {
            $bottlenecks[] = ['type' => 'danger', 'msg' => 'Erro ao conectar com o Banco de Dados.'];
        }

        if ($dbLatency > 150) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Latência alta do Banco de Dados ({$dbLatency}ms)."];
        }

        $martinOnline = false;
        $martinLatency = null;
        $martinSources = 0;
        if (config('maps.martin.enabled')) {
            $martinHealth = Cache::remember('maps.martin.health', 15, function (): array {
                try {
                    $startMartinPing = microtime(true);
                    $response = Http::timeout(2)->acceptJson()->get(config('maps.martin.internal_catalog_url'));

                    return [
                        'online' => $response->successful(),
                        'latency' => round((microtime(true) - $startMartinPing) * 1000, 2),
                        'sources' => count($response->json('tiles', [])),
                    ];
                } catch (\Throwable) {
                    return ['online' => false, 'latency' => null, 'sources' => 0];
                }
            });
            $martinOnline = $martinHealth['online'];
            $martinLatency = $martinHealth['latency'];
            $martinSources = $martinHealth['sources'];

            if (! $martinOnline) {
                $bottlenecks[] = [
                    'type' => 'danger',
                    'msg' => 'Martin indisponível. As camadas vetoriais MVT dos mapas podem não aparecer.',
                ];
            } elseif ($martinLatency > 500) {
                $bottlenecks[] = [
                    'type' => 'warning',
                    'msg' => "Latência alta no catálogo Martin ({$martinLatency}ms).",
                ];
            }
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $loadText = $load ? implode(', ', array_map(fn ($n) => number_format($n, 2), $load)) : 'N/A';
        if ($load && $load[0] > 4.0) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Sobrecarga de CPU: Load Average 1min está em {$load[0]}."];
        }

        // Memory Usage
        $usedBytes = memory_get_usage(true);
        $phpMemory = number_format($usedBytes / 1024 / 1024, 2).' MB';
        $phpLimit = ini_get('memory_limit');

        $limitBytes = -1;
        $limitStr = trim($phpLimit);
        if ($limitStr !== '-1') {
            $last = strtolower($limitStr[strlen($limitStr) - 1]);
            $limitVal = (int) $limitStr;
            switch ($last) {
                case 'g': $limitVal *= 1024 * 1024 * 1024; break;
                case 'm': $limitVal *= 1024 * 1024; break;
                case 'k': $limitVal *= 1024; break;
            }
            $limitBytes = $limitVal;
        }
        $memoryPercent = $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 1) : 0;

        if ($memoryPercent > 80) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Uso crítico de memória PHP ({$memoryPercent}%)."];
        }

        // Disk Usage
        $diskFree = @disk_free_space('/');
        $diskTotal = @disk_total_space('/');
        $diskUsedPercent = 0;
        $diskFreeStr = 'N/A';
        $diskTotalStr = 'N/A';
        if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
            $diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
            $diskFreeStr = number_format($diskFree / 1024 / 1024 / 1024, 2).' GB';
            $diskTotalStr = number_format($diskTotal / 1024 / 1024 / 1024, 2).' GB';
            if ($diskUsedPercent > 85) {
                $bottlenecks[] = ['type' => 'warning', 'msg' => "Espaço em disco com uso acima de 85% ({$diskUsedPercent}% utilizado)."];
            }
        }

        // Queue Backlog
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        if ($failedJobs > 0) {
            $bottlenecks[] = ['type' => 'danger', 'msg' => "Existem {$failedJobs} jobs falhados na fila!"];
        }

        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        if ($pendingJobs > 50) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Gargalo na fila: {$pendingJobs} jobs aguardando processamento."];
        }

        // Last Climate data record
        $lastClimateRecordText = 'Nenhum registro encontrado';
        if (Schema::hasTable('temperatura_registrada')) {
            try {
                $lastClimateRecord = DB::table('temperatura_registrada')
                    ->where('timestamp', '<=', now()->endOfMinute())
                    ->latest('timestamp')
                    ->value('timestamp');

                if ($lastClimateRecord) {
                    $lastClimateRecordText = Carbon::parse($lastClimateRecord)->format('d/m/Y H:i').' ('.Carbon::parse($lastClimateRecord)->diffForHumans().')';
                    if (Carbon::parse($lastClimateRecord)->diffInHours(now()) > 2) {
                        $bottlenecks[] = ['type' => 'danger', 'msg' => 'Falta de dados de clima! Último registro foi há mais de 2 horas.'];
                    }
                }
            } catch (\Throwable) {
                // Ignore query failure
            }
        }

        // Last Traffic data record
        $lastTrafficRecordText = 'Nenhum registro encontrado';
        if (Schema::hasTable('registro_transitos')) {
            try {
                $lastTrafficRecord = DB::table('registro_transitos')->latest('timestamp')->value('timestamp');
                if ($lastTrafficRecord) {
                    $lastTrafficRecordText = Carbon::parse($lastTrafficRecord)->format('d/m/Y H:i').' ('.Carbon::parse($lastTrafficRecord)->diffForHumans().')';
                    if (Carbon::parse($lastTrafficRecord)->diffInHours(now()) > 2) {
                        $bottlenecks[] = ['type' => 'warning', 'msg' => 'Falta de dados de trânsito! Último registro foi há mais de 2 horas.'];
                    }
                }
            } catch (\Throwable) {
                // Ignore query failure
            }
        }

        // Worker Log Output
        $logPath = storage_path('logs/queue-worker.log');
        $workerLogs = 'Nenhum log de execução encontrado.';
        if (file_exists($logPath)) {
            $fileLines = file($logPath);
            if ($fileLines !== false) {
                $workerLogs = implode('', array_slice($fileLines, -30));
            }
        }

        return [
            'is_worker_running' => $isWorkerRunning,
            'worker_status_text' => $workerStatusText,
            'last_job' => $lastJob,
            'db_version' => $dbVersion,
            'db_size' => $dbSize,
            'db_latency' => $dbLatency,
            'martin_online' => $martinOnline,
            'martin_latency' => $martinLatency,
            'martin_sources' => $martinSources,
            'system_load' => $loadText,
            'php_memory' => $phpMemory,
            'php_limit' => $phpLimit,
            'php_memory_percent' => $memoryPercent,
            'disk_free' => $diskFreeStr,
            'disk_total' => $diskTotalStr,
            'disk_used_percent' => $diskUsedPercent,
            'last_climate_record' => $lastClimateRecordText,
            'last_traffic_record' => $lastTrafficRecordText,
            'worker_logs' => $workerLogs,
            'queue_driver' => config('queue.default'),
            'bottlenecks' => $bottlenecks,
        ];
    }
}
