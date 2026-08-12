<?php
namespace App\Filament\Energia\Pages;
use App\Models\Distrito;
use Filament\Pages\Dashboard;
use Livewire\Attributes\Url;
class EnergiaDashboard extends Dashboard
{
    protected static ?string $title = 'Energia';
    protected static ?string $navigationLabel = 'Visão geral';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected string $view = 'filament.energia.pages.energia-dashboard';

    #[Url]
    public $distrito_id = null;

    public function getDistritosProperty(): array
    {
        return Distrito::query()->orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function updatedDistritoId(): void
    {
        $this->dispatch('update-energy-infra-map', distrito_id: $this->distrito_id);
    }
}
