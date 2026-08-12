<?php
namespace App\Filament\Internet\Widgets;
use App\Models\Antena; use App\Models\Cabeamento; use App\Models\IncidenteInternet;
use Filament\Widgets\StatsOverviewWidget; use Filament\Widgets\StatsOverviewWidget\Stat;
class ResumoInternet extends StatsOverviewWidget
{
    public ?int $distritoId = null;
    protected function getStats(): array
    {
        $scope = $this->distritoId ? 'No distrito selecionado' : 'Total da cidade';
        $antennas = Antena::query()->when($this->distritoId, fn ($q) => $q->where('distrito_id', $this->distritoId))->count();
        $cables = Cabeamento::query()->whereIn('tipo_cabo', ['fibra_optica','fibra_backbone','cobre_telefonia'])->when($this->distritoId, fn ($q) => $q->where('distrito_id', $this->distritoId))->count();
        $incidents = IncidenteInternet::query()->whereNotIn('situacao', ['resolvido','cancelado'])->when($this->distritoId, fn ($q) => $q->where('distrito_id', $this->distritoId))->count();
        return [Stat::make('Antenas cadastradas', number_format($antennas,0,',','.'))->description($scope)->descriptionIcon('heroicon-m-signal')->color('primary'),
            Stat::make('Trechos de telecom', number_format($cables,0,',','.'))->description($scope)->descriptionIcon('heroicon-m-link')->color('info'),
            Stat::make('Incidentes em aberto', number_format($incidents,0,',','.'))->description($scope)->descriptionIcon('heroicon-m-exclamation-triangle')->color($incidents ? 'danger' : 'success')];
    }
}
