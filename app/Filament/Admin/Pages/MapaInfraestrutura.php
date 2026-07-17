<?php

namespace App\Filament\Admin\Pages;

use App\Models\Distrito;
use App\Models\Poste;
use App\Models\Antena;
use App\Models\CentralDistribuicao;
use App\Models\EquipamentoInfraestrutura;
use App\Models\Cabeamento;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class MapaInfraestrutura extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Mapa da Infraestrutura';
    protected static ?string $title = 'Visão Global da Infraestrutura';
    protected static ?string $slug = 'mapa-infraestrutura';

    protected string $view = 'filament.admin.pages.mapa-infraestrutura';

    #[Url]
    public $distrito_id = null;

    public function getDistritosProperty()
    {
        return Distrito::orderBy('nome')->pluck('nome', 'id')->toArray();
    }

    public function updatedDistritoId()
    {
        $this->dispatch('update-infra-map', [
            'distrito_id' => $this->distrito_id,
        ]);
    }
}
