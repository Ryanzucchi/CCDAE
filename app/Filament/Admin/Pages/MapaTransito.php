<?php

namespace App\Filament\Admin\Pages;

use App\Models\Distrito;
use App\Models\ViaTransito;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class MapaTransito extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    protected static string|\UnitEnum|null $navigationGroup = 'Trânsito e Mobilidade';
    protected static ?string $navigationLabel = 'Mapa de Trânsito';
    protected static ?string $title = 'Fluxo de Trânsito Urbano';
    protected static ?string $slug = 'mapa-transito';

    protected string $view = 'filament.admin.pages.mapa-transito';

    #[Url]
    public $distrito_id = null;

    public function getDistritosProperty()
    {
        return Distrito::orderBy('nome')->pluck('nome', 'id')->toArray();
    }

    public function getViasProperty()
    {
        return ViaTransito::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))
            ->get(['id', 'nome', 'geojson', 'nivel_congestionamento', 'velocidade_media', 'impacto_manutencao'])
            ->toArray();
    }

    public function getDistritosGeojsonProperty()
    {
        return Distrito::when($this->distrito_id, fn($q) => $q->where('id', $this->distrito_id))
            ->get(['id', 'nome', 'geojson'])->toArray();
    }

    public function updatedDistritoId()
    {
        $this->dispatch('update-transito-map', [
            'vias' => $this->vias,
            'distritos' => $this->distritos_geojson,
        ]);
    }
}
