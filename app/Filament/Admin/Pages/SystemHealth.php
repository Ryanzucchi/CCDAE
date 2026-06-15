<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use App\Jobs\CollectClimateData;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Spatie\Activitylog\Models\Activity;

class SystemHealth extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCpuChip;
    protected static ?string $title = 'Saúde do Sistema';
    protected static ?string $navigationLabel = 'Saúde do Sistema';
    protected string $view = 'filament.admin.pages.system-health';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Admin\Widgets\SystemHealthStats::class,
            \App\Filament\Admin\Widgets\DataCollectionChart::class,
        ];
    }

    public function startQueueWorker()
    {
        $base = base_path();
        $log = storage_path('logs/queue-worker.log');
        $cmd = "nohup php {$base}/artisan queue:work > {$log} 2>&1 & echo $!";
        
        if (function_exists('shell_exec')) {
            shell_exec($cmd);
        } else {
            @exec($cmd);
        }
        
        Cache::put('queue_worker_last_seen', now()->timestamp, 300);
        
        Notification::make()
            ->title('Queue worker iniciado em segundo plano!')
            ->success()
            ->send();
    }

    public function stopQueueWorker()
    {
        \Illuminate\Support\Facades\Artisan::call('queue:restart');
        Cache::forget('queue_worker_last_seen');
        
        Notification::make()
            ->title('Sinal de parada enviado ao Worker!')
            ->warning()
            ->send();
    }

    public function clearQueue()
    {
        DB::table('jobs')->truncate();
        Notification::make()
            ->title('Fila de jobs limpa com sucesso!')
            ->success()
            ->send();
    }

    public function runCollector()
    {
        CollectClimateData::dispatch();
        Notification::make()
            ->title('Job disparado com sucesso!')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->where('log_name', 'climate_collection'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Horário')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrição do Evento')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('log_name')
                    ->label('Canal')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhum log gravado para o coletor ainda.');
    }

    public function getViewData(): array
    {
        $lastSeen = Cache::get('queue_worker_last_seen');
        $isWorkerRunning = false;
        $workerStatusText = 'Inativo';
        $bottlenecks = [];
        
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

        $lastJob = DB::table('activity_log')
            ->where('log_name', 'climate_collection')
            ->latest('id')
            ->first();

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

            $sizeQuery = DB::select("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
            if (!empty($sizeQuery)) {
                $dbSize = $sizeQuery[0]->size;
            }
        } catch (\Exception $e) {
            $bottlenecks[] = ['type' => 'danger', 'msg' => 'Erro ao conectar com o Banco de Dados.'];
        }

        if ($dbLatency > 150) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Latência alta do Banco de Dados ({$dbLatency}ms)."];
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $loadText = $load ? implode(', ', array_map(fn($n) => number_format($n, 2), $load)) : 'N/A';
        if ($load && $load[0] > 4.0) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Sobrecarga de CPU: Load Average 1min está em {$load[0]}."];
        }

        // Memory Usage
        $usedBytes = memory_get_usage(true);
        $phpMemory = number_format($usedBytes / 1024 / 1024, 2) . ' MB';
        $phpLimit = ini_get('memory_limit');

        $limitBytes = -1;
        $limitStr = trim($phpLimit);
        if ($limitStr !== '-1') {
            $last = strtolower($limitStr[strlen($limitStr) - 1]);
            $limitVal = (int)$limitStr;
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
            $diskFreeStr = number_format($diskFree / 1024 / 1024 / 1024, 2) . ' GB';
            $diskTotalStr = number_format($diskTotal / 1024 / 1024 / 1024, 2) . ' GB';
            if ($diskUsedPercent > 85) {
                $bottlenecks[] = ['type' => 'warning', 'msg' => "Espaço em disco com uso acima de 85% ({$diskUsedPercent}% utilizado)."];
            }
        }

        // Queue Backlog
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $bottlenecks[] = ['type' => 'danger', 'msg' => "Existem {$failedJobs} jobs falhados na fila!"];
        }

        $pendingJobs = DB::table('jobs')->count();
        if ($pendingJobs > 50) {
            $bottlenecks[] = ['type' => 'warning', 'msg' => "Gargalo na fila: {$pendingJobs} jobs aguardando processamento."];
        }

        // Last Climate data record
        $lastClimateRecord = DB::table('temperatura_registrada')->latest('timestamp')->value('timestamp');
        $lastClimateRecordText = $lastClimateRecord 
            ? \Carbon\Carbon::parse($lastClimateRecord)->format('d/m/Y H:i') . ' (' . \Carbon\Carbon::parse($lastClimateRecord)->diffForHumans() . ')'
            : 'Nenhum registro encontrado';

        if ($lastClimateRecord && \Carbon\Carbon::parse($lastClimateRecord)->diffInHours(now()) > 2) {
            $bottlenecks[] = ['type' => 'danger', 'msg' => 'Falta de dados de clima! Último registro foi há mais de 2 horas.'];
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
            'stats' => [
                'is_worker_running' => $isWorkerRunning,
                'worker_status_text' => $workerStatusText,
                'last_job' => $lastJob,
                'db_version' => $dbVersion,
                'db_size' => $dbSize,
                'db_latency' => $dbLatency,
                'system_load' => $loadText,
                'php_memory' => $phpMemory,
                'php_limit' => $phpLimit,
                'php_memory_percent' => $memoryPercent,
                'disk_free' => $diskFreeStr,
                'disk_total' => $diskTotalStr,
                'disk_used_percent' => $diskUsedPercent,
                'last_climate_record' => $lastClimateRecordText,
                'worker_logs' => $workerLogs,
                'queue_driver' => config('queue.default'),
                'bottlenecks' => $bottlenecks,
            ],
        ];
    }
}
