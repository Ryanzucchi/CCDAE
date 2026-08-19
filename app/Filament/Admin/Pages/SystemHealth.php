<?php

namespace App\Filament\Admin\Pages;

use App\Jobs\CollectClimateData;
use App\Jobs\CollectTrafficData;
use App\Services\Health\SystemHealthService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
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

    public function startQueueWorker(): void
    {
        Notification::make()
            ->title('Workers são gerenciados pelo Docker')
            ->body('Use docker compose up -d queue scheduler. O painel não inicia processos para evitar instâncias duplicadas.')
            ->info()
            ->send();
    }

    public function stopQueueWorker(): void
    {
        Notification::make()
            ->title('Workers são gerenciados pelo Docker')
            ->body('Use docker compose stop queue scheduler para interrompê-los de forma controlada.')
            ->warning()
            ->send();
    }

    public function clearQueue(): void
    {
        DB::table('jobs')->truncate();
        Notification::make()
            ->title('Fila de jobs limpa com sucesso!')
            ->success()
            ->send();
    }

    public function runCollector(): void
    {
        CollectClimateData::dispatch();
        CollectTrafficData::dispatch();
        Notification::make()
            ->title('Jobs de Coleta (Clima e Trânsito) disparados com sucesso!')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->whereIn('log_name', ['climate_collection', 'traffic_collection']))
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
                    ->color(fn (string $state): string => match ($state) {
                        'climate_collection' => 'info',
                        'traffic_collection' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhum log gravado para os coletores ainda.');
    }

    public function getViewData(): array
    {
        $healthService = app(SystemHealthService::class);

        return [
            'stats' => $healthService->getHealthReport(),
        ];
    }
}
