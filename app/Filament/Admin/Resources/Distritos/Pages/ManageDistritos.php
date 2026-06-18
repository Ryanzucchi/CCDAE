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
                    $data['geojson'] = Distrito::autoShrinkGeojson($geo, null);
                    $data['latitude'] = $data['latitude'] ?? null;
                    $data['longitude'] = $data['longitude'] ?? null;

                    if (!empty($data['geojson'])) {
                        try {
                            $centroid = \Illuminate\Support\Facades\DB::selectOne(
                                "SELECT ST_X(ST_Centroid(ST_GeomFromGeoJSON(?))) as lng, ST_Y(ST_Centroid(ST_GeomFromGeoJSON(?))) as lat", 
                                [json_encode($data['geojson']), json_encode($data['geojson'])]
                            );
                            if ($centroid && $centroid->lat && $centroid->lng) {
                                $data['latitude'] = round($centroid->lat, 6);
                                $data['longitude'] = round($centroid->lng, 6);
                            }
                        } catch (\Exception $e) {}
                    }

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
                file_put_contents(storage_path('logs/debug_edit.log'), "RAW EDIT DATA: " . json_encode($data) . "\n", FILE_APPEND);
                $distrito = Distrito::find($arguments['id']);
                if ($distrito) {
                    $location = $data['location'] ?? [];
                    $geo = $location['geojson'] ?? null;
                    if (isset($geo['type']) && $geo['type'] === 'FeatureCollection') {
                        $geo = $geo['features'][0]['geometry'] ?? $geo;
                    }
                    $data['geojson'] = Distrito::autoShrinkGeojson($geo, $distrito->id);
                    $data['latitude'] = $data['latitude'] ?? null;
                    $data['longitude'] = $data['longitude'] ?? null;

                    if (!empty($data['geojson'])) {
                        try {
                            $centroid = \Illuminate\Support\Facades\DB::selectOne(
                                "SELECT ST_X(ST_Centroid(ST_GeomFromGeoJSON(?))) as lng, ST_Y(ST_Centroid(ST_GeomFromGeoJSON(?))) as lat", 
                                [json_encode($data['geojson']), json_encode($data['geojson'])]
                            );
                            if ($centroid && $centroid->lat && $centroid->lng) {
                                $data['latitude'] = round($centroid->lat, 6);
                                $data['longitude'] = round($centroid->lng, 6);
                            }
                        } catch (\Exception $e) {}
                    }

                    unset($data['location']);
                    unset($data['map_injector']);
                    file_put_contents(storage_path('logs/debug.log'), "EDIT SAVE DATA: " . json_encode($data) . "\n", FILE_APPEND);
                    $distrito->update($data);
                }
            });
    }

    protected function cacheMountedActions(array $mountedActions): array
    {
        $cleanedActions = [];
        foreach ($mountedActions as $action) {
            if (is_array($action) && !empty($action['name'])) {
                $cleanedActions[] = $action;
            } else {
                file_put_contents(storage_path('logs/debug.log'), "REMOVED INVALID ACTION: " . json_encode($action) . "\n", FILE_APPEND);
            }
        }
        $this->mountedActions = $cleanedActions;
        return parent::cacheMountedActions($cleanedActions);
    }
}
