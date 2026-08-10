<x-filament-widgets::widget>
    @vite('resources/js/maps/districts-widget-map.js')

    <x-filament::section>
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold">Mapa Geral de Distritos</h2>
        </div>

        <div
            wire:ignore
            id="distritos-widget-map"
            class="ccdae-map-surface w-full"
            style="width: 100%; height: 400px;"
            data-districts='@json($this->getDistritos(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)'
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
            data-martin-enabled="{{ config('maps.martin.enabled') ? 'true' : 'false' }}"
            data-martin-tilejson-url="{{ config('maps.martin.tilejson_base_url') }}"
        ></div>
    </x-filament::section>
</x-filament-widgets::widget>
