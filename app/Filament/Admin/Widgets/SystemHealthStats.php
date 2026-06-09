<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SystemHealthStats extends BaseWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $totalDistritos = DB::table('distritos')->count();
        
        $totalRegistros = DB::table('temperatura_registrada')->count()
            + DB::table('vento_registrado')->count()
            + DB::table('chuva_registrada')->count()
            + DB::table('pressao_atmosferica')->count()
            + DB::table('radiacao_solar')->count()
            + DB::table('indice_uv')->count()
            + DB::table('particulas_ar')->count();

        $queueCount = DB::table('jobs')->count();
        $failedCount = DB::table('failed_jobs')->count();

        return [
            Stat::make('Distritos Cobertos', number_format($totalDistritos, 0, ',', '.'))
                ->description('Distritos urbanos ativos')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),

            Stat::make('Registros de Clima', number_format($totalRegistros, 0, ',', '.'))
                ->description('Temperaturas, ventos, chuva, etc.')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),

            Stat::make('Fila de Processamento', $queueCount)
                ->description('Jobs pendentes na fila')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($queueCount > 0 ? 'warning' : 'gray'),

            Stat::make('Erros da Fila', $failedCount)
                ->description('Jobs falhados no banco')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failedCount > 0 ? 'danger' : 'gray'),
        ];
    }
}
