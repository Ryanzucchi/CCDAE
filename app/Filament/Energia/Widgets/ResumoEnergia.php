<?php

namespace App\Filament\Energia\Widgets;

use App\Models\Cabeamento;
use App\Models\IncidenteEnergia;
use App\Models\Poste;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumoEnergia extends StatsOverviewWidget
{
    public ?int $distritoId = null;

    protected function getStats(): array
    {
        $postes = Poste::query()
            ->when($this->distritoId, fn ($query) => $query->where('distrito_id', $this->distritoId))
            ->count();
        $cabos = Cabeamento::query()
            ->where('tipo_cabo', 'like', 'eletrico_%')
            ->when($this->distritoId, fn ($query) => $query->where('distrito_id', $this->distritoId))
            ->count();
        $incidentes = IncidenteEnergia::query()
            ->whereNotIn('situacao', ['resolvido', 'cancelado'])
            ->when($this->distritoId, fn ($query) => $query->where('distrito_id', $this->distritoId))
            ->count();
        $scope = $this->distritoId ? 'No distrito selecionado' : 'Total da cidade';

        return [
            Stat::make('Postes cadastrados', number_format($postes, 0, ',', '.'))
                ->description($scope)->descriptionIcon('heroicon-m-bolt')->color('warning'),
            Stat::make('Trechos elétricos', number_format($cabos, 0, ',', '.'))
                ->description($scope)->descriptionIcon('heroicon-m-link')->color('info'),
            Stat::make('Incidentes em aberto', number_format($incidentes, 0, ',', '.'))
                ->description($scope)->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($incidentes > 0 ? 'danger' : 'success'),
        ];
    }
}
