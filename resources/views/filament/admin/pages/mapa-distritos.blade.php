<x-filament-panels::page>
    @vite('resources/js/maps/districts-map.js')

    <style>
        #distritos-map-container {
            width: 100%;
            height: 100%;
        }

        .ccdae-map-popup {
            min-width: 150px;
            text-align: center;
        }

        .ccdae-map-popup strong {
            display: block;
            color: #111827;
            font-size: 1.1em;
        }

        .ccdae-map-popup button {
            width: 100%;
            margin-top: 12px;
            padding: 6px 12px;
            border: 0;
            border-radius: 6px;
            background: #f59e0b;
            color: #ffffff;
            cursor: pointer;
            font-weight: 600;
        }

        .ccdae-map-popup button:hover {
            background: #d97706;
        }
    </style>

    <x-filament::section>
        <div class="mb-4 max-w-sm">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="cidadeSelecionada">
                    <option value="">Selecione uma Cidade...</option>
                    @foreach($this->cidades as $cidade)
                        <option value="{{ $cidade }}">{{ $cidade }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div wire:ignore class="ccdae-map-surface" style="height: 600px;">
            <div
                id="distritos-map-container"
                data-map-engine="{{ config('maps.engine') }}"
                data-districts='@json($this->getDistritosDaCidadeProperty(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)'
                data-center='@json([config('maps.center.longitude'), config('maps.center.latitude')])'
                data-zoom="{{ config('maps.zoom') }}"
                data-style-url="{{ config('maps.base_style_url') }}"
                data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}"
                data-raster-attribution="{{ config('maps.raster_attribution') }}"
                data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}"
                data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}"
                data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                data-terrain-attribution="{{ config('maps.terrain.attribution') }}"
            ></div>

            <div
                x-data="{ hasCity: {{ count($this->getDistritosDaCidadeProperty()) > 0 ? 'true' : 'false' }} }"
                x-on:update-map.window="hasCity = Boolean($event.detail.distritos?.length || $event.detail[0]?.distritos?.length)"
                x-show="!hasCity"
                class="absolute inset-0 z-20 flex items-center justify-center bg-gray-50/90 p-12 text-center text-gray-500 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-800/90"
            >
                <div>
                    <x-filament::icon
                        alias="panels::pages.dashboard.empty"
                        icon="heroicon-o-map"
                        class="mx-auto mb-4 h-16 w-16 text-gray-400"
                    />
                    <h3 class="text-xl font-medium text-gray-700 dark:text-gray-200">Escolha o seu foco</h3>
                    <p class="mt-2 max-w-sm text-sm">Selecione uma cidade para iniciar a visualização geográfica dos distritos.</p>
                </div>
            </div>
        </div>
    </x-filament::section>

    <script>
        window.addEventListener('edit-distrito-event', (event) => {
            @this.mountAction('editDistrito', { id: event.detail });
        });
    </script>

    <x-filament-actions::modals />
</x-filament-panels::page>
