<x-filament-panels::page>
    @vite('resources/js/maps/energy-infrastructure-map.js')
    @livewire(\App\Filament\Energia\Widgets\ResumoEnergia::class, ['distritoId' => filled($distrito_id) ? (int) $distrito_id : null], key('resumo-energia-'.($distrito_id ?: 'cidade')))

    <x-filament::section heading="Visão geral da infraestrutura elétrica" description="Clique em um fio, poste, transformador ou central para consultar seus dados.">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="w-full sm:max-w-sm">
                <label for="energy-dashboard-district" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Distrito</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="energy-dashboard-district" wire:model.live="distrito_id">
                        <option value="">Visão geral da cidade</option>
                        @foreach($this->distritos as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">A visão geral mostra toda a rede elétrica. O distrito apenas filtra a visualização.</p>
        </div>

        <div wire:ignore class="ccdae-map-surface relative overflow-hidden rounded-xl" style="height:min(680px,72vh);min-height:480px">
            <div id="energy-infrastructure-map" data-district="{{ $distrito_id }}"
                 data-martin-base-url="{{ rtrim(config('maps.martin.tilejson_base_url'), '/') }}"
                 data-style-url="{{ config('maps.base_style_url') }}"
                 data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                 data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}"
                 data-raster-attribution="{{ config('maps.raster_attribution') }}"
                 style="width:100%;height:100%"></div>
            <div data-energy-status class="absolute left-1/2 top-5 z-20 -translate-x-1/2 rounded-lg bg-gray-950/85 px-4 py-2 text-sm text-white shadow">Carregando rede elétrica...</div>
            <div class="ccdae-map-panel absolute bottom-8 right-3 z-20 rounded-lg p-3 text-xs">
                <strong>Rede elétrica</strong>
                <div class="mt-2"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-gray-400"></span>Postes</div>
                <div><span class="mr-2 inline-block h-1 w-5 bg-orange-500"></span>Baixa tensão</div>
                <div><span class="mr-2 inline-block h-1 w-5 bg-red-500"></span>Alta tensão</div>
                <div><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>Equipamentos e centrais</div>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
