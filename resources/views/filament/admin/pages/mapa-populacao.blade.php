<x-filament-panels::page>
    @vite('resources/js/maps/energy-maps.js')
    <x-filament::section>
        <div class="ccdae-map-surface relative" style="height:750px">
            <div data-energy-analytic-map data-map-type="populacao" style="width:100%;height:100%"></div>
            <div data-energy-status class="absolute left-1/2 top-5 z-20 -translate-x-1/2 rounded-lg bg-gray-950/85 px-4 py-2 text-sm text-white shadow">Carregando população...</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
