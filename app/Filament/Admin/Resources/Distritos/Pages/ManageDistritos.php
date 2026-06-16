<?php

namespace App\Filament\Admin\Resources\Distritos\Pages;

use App\Filament\Admin\Resources\Distritos\DistritoResource;
use App\Models\Distrito;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

class ManageDistritos extends ManageRecords
{
    protected static string $resource = DistritoResource::class;

    protected string $view = 'filament.admin.pages.mapa-distritos';

    #[Url]
    public ?string $cidadeSelecionada = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo Distrito')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['geojson'] = Distrito::autoShrinkGeojson($data['geojson'] ?? null);
                    return $data;
                }),
        ];
    }

    public function getCidadesProperty()
    {
        return Distrito::whereNotNull('cidade')
            ->distinct()
            ->orderBy('cidade')
            ->pluck('cidade')
            ->toArray();
    }

    public function updatedCidadeSelecionada()
    {
        $this->dispatch('update-map', ['distritos' => $this->getDistritosDaCidadeProperty()]);
    }

    public function getDistritosDaCidadeProperty()
    {
        if (!$this->cidadeSelecionada) {
            return [];
        }

        return Distrito::where('cidade', $this->cidadeSelecionada)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'nome', 'cidade', 'latitude', 'longitude', 'geojson'])
            ->toArray();
    }

    public function editDistritoAction(): Action
    {
        return Action::make('editDistrito')
            ->hiddenLabel()
            ->hidden()
            ->form(fn () => DistritoResource::form(Schema::make())->getComponents())
            ->fillForm(fn (array $arguments) => Distrito::find($arguments['id'])?->toArray() ?? [])
            ->action(function (array $data, array $arguments): void {
                $distrito = Distrito::find($arguments['id']);
                if ($distrito) {
                    $data['geojson'] = Distrito::autoShrinkGeojson($data['geojson'] ?? null, $distrito->id);
                    $distrito->update($data);
                }
            });
    }
}
