<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold">Mapa Geral de Distritos</h2>
        </div>

        <div wire:ignore id="distritos-map" style="width: 100%; height: 400px; border-radius: 0.5rem; z-index: 10;"></div>

        <style>
            .leaflet-pane svg,
            .leaflet-pane canvas {
                max-width: none !important;
                max-height: none !important;
                display: block !important;
            }
            .leaflet-overlay-pane svg {
                max-width: none !important;
                max-height: none !important;
            }
        </style>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('distritosMap', () => ({
                    init() {
                        setTimeout(() => {
                            let map = L.map('distritos-map').setView([-23.550520, -46.633308], 10); // Centro em SP

                            new ResizeObserver(() => {
                                map.invalidateSize();
                            }).observe(document.getElementById('distritos-map'));

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap contributors'
                            }).addTo(map);

                            let distritos = @json($this->getDistritos());
                            let bounds = [];

                            distritos.forEach(d => {
                                if (d.geojson) {
                                    let parsedGeojson = typeof d.geojson === 'string' ? JSON.parse(d.geojson) : d.geojson;
                                    let geoLayer = L.geoJSON(parsedGeojson, {
                                        style: { color: '#3b82f6', weight: 2, fillOpacity: 0.3 }
                                    }).addTo(map);
                                    geoLayer.bindPopup(`<b>${d.nome}</b><br>${d.cidade}`);
                                    
                                    let layerBounds = geoLayer.getBounds();
                                    bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                    bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                } else if (d.latitude && d.longitude) {
                                    let marker = L.marker([d.latitude, d.longitude]).addTo(map);
                                    marker.bindPopup(`<b>${d.nome}</b><br>${d.cidade}`);
                                    bounds.push([d.latitude, d.longitude]);
                                }
                            });

                            if(bounds.length > 0) {
                                map.fitBounds(bounds);
                            }
                        }, 500); // Dá um tempo para o DOM/Filament montar a div
                    }
                }))
            })
        </script>
        
        <div x-data="distritosMap"></div>
    </x-filament::section>
</x-filament-widgets::widget>
