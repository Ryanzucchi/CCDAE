<x-filament-panels::page>
    @vite('resources/js/maps/traffic-portal.js')
    <x-filament::section :heading="$this->getTitle()" :description="$description">
        <div wire:ignore class="relative overflow-hidden rounded-xl" style="height:700px">
            <div data-traffic-wave-map data-map-type="{{ $mapType }}" style="width:100%;height:100%"
                data-style-url="{{ config('maps.base_style_url') }}" data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}" data-raster-attribution="{{ config('maps.raster_attribution') }}"
                data-terrain-enabled="false" data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}" data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                data-terrain-attribution="{{ config('maps.terrain.attribution') }}"></div>
            <div data-traffic-wave-status class="absolute left-1/2 top-5 z-20 -translate-x-1/2 rounded-lg bg-gray-950/85 px-4 py-2 text-sm font-medium text-white shadow">Carregando dados...</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
