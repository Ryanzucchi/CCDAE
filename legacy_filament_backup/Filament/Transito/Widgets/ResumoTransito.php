<?php

namespace App\Filament\Transito\Widgets;

use App\Models\InfracaoTransito;
use App\Models\RegistroTransito;
use App\Models\ViaTransito;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumoTransito extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $ultimaLeitura = RegistroTransito::query()->latest('timestamp')->first();

        return [
            Stat::make('Vias monitoradas', ViaTransito::query()->count())
                ->description('Malha viária cadastrada')
                ->icon('heroicon-o-map'),
            Stat::make('Veículos na última leitura', number_format((int) ($ultimaLeitura?->veiculos_total ?? 0), 0, ',', '.'))
                ->description($ultimaLeitura?->timestamp?->format('d/m/Y H:i') ?? 'Sem leitura disponível')
                ->icon('heroicon-o-truck'),
            Stat::make('Infrações em 30 dias', InfracaoTransito::query()->where('ocorrido_em', '>=', now()->subDays(30))->count())
                ->description('Registros recentes')
                ->icon('heroicon-o-document-text'),
        ];
    }
}
