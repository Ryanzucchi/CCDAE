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
        ];
    }

    public function startQueueWorker()
    {
        if (function_exists('shell_exec')) {
            shell_exec('php artisan queue:work > ' . storage_path('logs/queue-worker.log') . ' 2>&1 &');
        } else {
            @exec('php artisan queue:work > ' . storage_path('logs/queue-worker.log') . ' 2>&1 &');
        }
        
        Notification::make()
            ->title('Queue worker iniciado em segundo plano!')
            ->success()
            ->send();
    }

    public function stopQueueWorker()
    {
        $pids = [];
        foreach (glob('/proc/[0-9]*/cmdline') as $f) {
            $c = @file_get_contents($f);
            if (str_contains($c, 'queue:work') && !str_contains($c, 'tinker') && !str_contains($c, 'SystemHealth')) {
                preg_match('/\/proc\/([0-9]+)\/cmdline/', $f, $matches);
                if (isset($matches[1])) {
                    $pids[] = (int)$matches[1];
                }
            }
        }
        foreach ($pids as $pid) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, 15);
            } else {
                @shell_exec("kill -15 {$pid}");
            }
        }
        Cache::forget('queue_worker_last_seen');
        
        Notification::make()
            ->title('Queue worker parado!')
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
        
        if ($lastSeen !== null) {
            $diff = now()->timestamp - $lastSeen;
            if ($diff <= 120) {
                $isWorkerRunning = true;
                $workerStatusText = "Ativo (último sinal: há {$diff}s)";
            } else {
                $workerStatusText = "Inativo (último sinal: há {$diff}s)";
            }
        }

        $lastJob = DB::table('activity_log')
            ->where('log_name', 'climate_collection')
            ->latest('id')
            ->first();

        // System information
        $dbVersion = 'Desconhecido';
        try {
            $dbVersion = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Exception $e) {}

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $loadText = $load ? implode(', ', array_map(fn($n) => number_format($n, 2), $load)) : 'N/A';

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

        // Last Climate data record
        $lastClimateRecord = DB::table('temperatura_registrada')->latest('timestamp')->value('timestamp');
        $lastClimateRecordText = $lastClimateRecord 
            ? \Carbon\Carbon::parse($lastClimateRecord)->format('d/m/Y H:i') . ' (' . \Carbon\Carbon::parse($lastClimateRecord)->diffForHumans() . ')'
            : 'Nenhum registro encontrado';

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
                'system_load' => $loadText,
                'php_memory' => $phpMemory,
                'php_limit' => $phpLimit,
                'php_memory_percent' => $memoryPercent,
                'last_climate_record' => $lastClimateRecordText,
                'worker_logs' => $workerLogs,
                'queue_driver' => config('queue.default'),
            ],
        ];
    }
}
