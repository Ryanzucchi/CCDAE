<x-filament-panels::page>
    @vite('resources/js/maps/traffic-map.js')
    <x-filament::section heading="Fluxo de trânsito urbano" description="Situação, direção do fluxo e histórico das vias nas últimas 24 horas.">
        <div wire:ignore class="ccdae-map-surface" style="height: 700px">
            <div id="transito-map-container" style="width:100%;height:100%"
                data-payload='@json(['vias' => $this->vias, 'distritos' => $this->distritos], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)'
                data-center='[-57.6806,-16.0722]' data-zoom="13"
                data-style-url="{{ config('maps.base_style_url') }}" data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}" data-raster-attribution="{{ config('maps.raster_attribution') }}"
                data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}" data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}" data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                data-terrain-attribution="{{ config('maps.terrain.attribution') }}" data-martin-enabled="{{ config('maps.martin.enabled') ? 'true' : 'false' }}"
                data-martin-tilejson-url="{{ config('maps.martin.tilejson_base_url') }}"></div>
        </div>
    </x-filament::section>
    <x-filament::section heading="Histórico de trânsito" description="Selecione um horário para visualizar a condição das vias.">
        <div class="grid gap-4 md:grid-cols-[auto_1fr_auto] md:items-center">
            <x-filament::button id="traffic-timeline-play" disabled>Reproduzir</x-filament::button>
            <div>
                <div class="mb-2 flex justify-between text-xs text-gray-500"><span>24 horas atrás</span><span>Agora</span></div>
                <div class="relative pt-6"><output id="traffic-timeline-bubble" class="absolute top-0 rounded bg-gray-950 px-2 py-1 text-xs text-white">--:--</output>
                    <input id="traffic-timeline-range" class="w-full accent-primary-600" type="range" min="0" max="0" value="0" step="1" disabled>
                    <div id="traffic-timeline-ticks" class="hidden"></div>
                </div>
            </div>
            <strong id="traffic-timeline-selected">Carregando...</strong>
        </div>
    </x-filament::section>
</x-filament-panels::page>
