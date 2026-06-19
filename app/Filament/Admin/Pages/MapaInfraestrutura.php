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

    public function getCabeamentosProperty() {
        return Cabeamento::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))->get(['id', 'geojson', 'nome', 'tipo_cabo', 'estado_conservacao'])->toArray();
    }

    public function getViasProperty() {
        return \App\Models\ViaTransito::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))->get(['id', 'geojson', 'nome', 'nivel_congestionamento', 'velocidade_media'])->toArray();
    }

    public function updatedDistritoId()
    {
        $this->dispatch('update-infra-map', [
            'postes' => $this->postes,
            'antenas' => $this->antenas,
            'centrais' => $this->centrais,
            'equipamentos' => $this->equipamentos,
            'cabeamentos' => $this->cabeamentos,
            'vias' => $this->vias,
        ]);
    }

    public function getPostesProperty() {
        return Poste::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'material', 'estado_conservacao'])->toArray();
    }
    
    public function getAntenasProperty() {
        return Antena::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))->get(['id', 'latitude', 'longitude', 'codigo_patrimonio', 'tipo_sinal', 'estado_conservacao'])->toArray();
    }
    
    public function getCentraisProperty() {
        return CentralDistribuicao::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))->get(['id', 'latitude', 'longitude', 'geojson', 'nome', 'tipo', 'estado_conservacao'])->toArray();
    }
    
    public function getEquipamentosProperty() {
        return EquipamentoInfraestrutura::when($this->distrito_id, fn($q) => $q->where('distrito_id', $this->distrito_id))
            ->whereNull('poste_id')->whereNull('central_id')
            ->get(['id', 'latitude', 'longitude', 'nome', 'tipo', 'estado_conservacao'])->toArray();
    }
}
