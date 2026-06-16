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
                    $location = $data['location'] ?? [];
                    $geo = $location['geojson'] ?? null;
                    if (isset($geo['type']) && $geo['type'] === 'FeatureCollection') {
                        $geo = $geo['features'][0]['geometry'] ?? $geo;
                    }
                    $data['geojson'] = Distrito::autoShrinkGeojson($geo);
                    $data['latitude'] = $location['lat'] ?? $data['latitude'] ?? null;
                    $data['longitude'] = $location['lng'] ?? $data['longitude'] ?? null;
                    unset($data['location']);
                    unset($data['map_injector']);
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
            ->form(fn () => DistritoResource::form(Schema::make())->getComponents())
            ->fillForm(fn (array $arguments) => Distrito::find($arguments['id'])?->toArray() ?? [])
            ->action(function (array $data, array $arguments): void {
                $distrito = Distrito::find($arguments['id']);
                if ($distrito) {
                    $location = $data['location'] ?? [];
                    $geo = $location['geojson'] ?? null;
                    if (isset($geo['type']) && $geo['type'] === 'FeatureCollection') {
                        $geo = $geo['features'][0]['geometry'] ?? $geo;
                    }
                    $data['geojson'] = Distrito::autoShrinkGeojson($geo, $distrito->id);
                    $data['latitude'] = $location['lat'] ?? $data['latitude'] ?? null;
                    $data['longitude'] = $location['lng'] ?? $data['longitude'] ?? null;
                    unset($data['location']);
                    unset($data['map_injector']);
                    $distrito->update($data);
                }
            });
    }
}
