<x-filament-panels::page>
    <style>
        .leaflet-pane svg,
        .leaflet-pane canvas {
            max-width: none !important;
            display: block !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            z-index: 9999 !important;
        }
        .leaflet-pane path {
            pointer-events: visiblePainted !important;
        }
        .leaflet-overlay-pane { z-index: 9999 !important; }
        .leaflet-popup-pane { z-index: 10000 !important; }
        
        .map-legend {
            background: rgba(17, 24, 39, 0.9);
            color: #f3f4f6;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .legend-item { display: flex; align-items: center; margin-bottom: 4px; }
        .legend-line { width: 20px; height: 3px; margin-right: 8px; display: inline-block; }
    </style>
    
    <x-filament::section>
        <div class="mb-4 max-w-sm flex gap-4">
            <x-filament::input.wrapper class="w-full">
                <x-filament::input.select wire:model.live="distrito_id">
                    <option value="">🌎 Ver Todos os Distritos (Visão Macro)</option>
                    @foreach($this->distritos as $id => $nome)
                        <option value="{{ $id }}">📍 {{ $nome }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div wire:ignore style="position: relative; border-radius: 0.5rem; overflow: hidden; height: 750px;">
            <div id="transito-map-container" x-data="transitoMapPage" x-init="init()" style="width: 100%; height: 100%; z-index: 10;"></div>
            
            <script>
                let transitoMap = null;
                let transitoLayerGroup = null;

                document.addEventListener('alpine:init', () => {
                    Alpine.data('transitoMapPage', () => ({
                        init() {
                            setTimeout(() => {
                                let mapContainer = document.getElementById('transito-map-container');
                                if (!mapContainer) return;
                                
                                if (transitoMap !== null) {
                                    transitoMap.remove();
                                    transitoMap = null;
                                } else if (mapContainer._leaflet_id) {
                                    mapContainer._leaflet_id = null;
                                }

                                transitoMap = L.map('transito-map-container', {
                                    preferCanvas: true,
                                    zoomAnimation: false,
                                    fadeAnimation: false
                                }).setView([-16.0722, -57.6806], 13); // Cáceres center default

                                transitoLayerGroup = L.featureGroup().addTo(transitoMap);

                                // Dark mode premium tile layer
                                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                                    attribution: '&copy; OpenStreetMap &copy; CARTO'
                                }).addTo(transitoMap);

                                // Adicionar a legenda
                                let legend = L.control({position: 'bottomright'});
                                legend.onAdd = function (map) {
                                    let div = L.DomUtil.create('div', 'map-legend');
                                    div.innerHTML = `
                                        <strong>Fluxo de Trânsito</strong><br>
                                        <div class="legend-item" style="margin-top:8px;"><span class="legend-line" style="background:#22c55e;"></span> Via Livre (&gt; 40 km/h)</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#eab308;"></span> Moderado (20-40 km/h)</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#f97316;"></span> Intenso (10-20 km/h)</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#ef4444;"></span> Parado (&lt; 10 km/h)</div>
                                        <div class="legend-item" style="margin-top:8px; opacity:0.6"><span class="legend-line" style="background:#4b5563;"></span> Fronteira do Distrito</div>
                                    `;
                                    return div;
                                };
                                legend.addTo(transitoMap);

                                transitoMap.invalidateSize();
                                
                                let payload = {
                                    vias: @json($this->vias),
                                    distritos: @json($this->distritos_geojson),
                                };
                                this.renderMap(payload);
                            }, 300);

                            window.addEventListener('update-transito-map', (e) => {
                                if(transitoMap) {
                                    setTimeout(() => {
                                        transitoMap.invalidateSize(); 
                                        transitoLayerGroup.clearLayers();
                                        let payload = e.detail[0] || e.detail;
                                        this.renderMap(payload);
                                    }, 100);
                                }
                            });
                        },

                        renderMap(data) {
                            let bounds = [];

                            // Distritos boundaries (Background)
                            if (data.distritos) {
                                data.distritos.forEach(d => {
                                    if (d.geojson) {
                                        try {
                                            let geo = typeof d.geojson === 'string' ? JSON.parse(d.geojson) : d.geojson;
                                            let layer = L.geoJSON(geo, { 
                                                style: { 
                                                    color: '#4b5563', 
                                                    weight: 1, 
                                                    fillOpacity: 0.05, 
                                                    dashArray: '5, 5' 
                                                },
                                                interactive: false // Don't trigger popups for background
                                            });
                                            transitoLayerGroup.addLayer(layer);
                                        } catch(e) {}
                                    }
                                });
                            }

                            // Vias de Transito (Lines)
                            if (data.vias) {
                                data.vias.forEach(v => {
                                    if (v.geojson) {
                                        try {
                                            let geo = typeof v.geojson === 'string' ? JSON.parse(v.geojson) : v.geojson;
                                            
                                            // Cores baseadas no congestionamento
                                            let color = '#22c55e'; // Livre
                                            if(v.nivel_congestionamento === 'moderado') color = '#eab308';
                                            if(v.nivel_congestionamento === 'intenso') color = '#f97316';
                                            if(v.nivel_congestionamento === 'parado') color = '#ef4444';
                                            
                                            let layer = L.geoJSON(geo, { style: { color: color, weight: 6, opacity: 0.9 } });
                                            layer.bindPopup(`
                                                <div style="font-family: sans-serif; font-size: 13px;">
                                                    <b style="font-size: 15px;">Via: ${v.nome}</b><br><br>
                                                    <b>Congestionamento:</b> <span style="color:${color}; font-weight:bold; text-transform:uppercase;">${v.nivel_congestionamento}</span><br>
                                                    <b>Velocidade Média:</b> ${v.velocidade_media} km/h<br>
                                                    <b>Impacto Logístico:</b> ${v.impacto_manutencao.toUpperCase()}<br>
                                                </div>
                                            `);
                                            transitoLayerGroup.addLayer(layer);
                                            let layerBounds = layer.getBounds();
                                            bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                            bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                        } catch(e) {}
                                    }
                                });
                            }

                            if(bounds.length > 0) {
                                transitoMap.fitBounds(bounds, { padding: [50, 50] });
                            }
                        }
                    }))
                });
            </script>
        </div>
    </x-filament::section>
</x-filament-panels::page>
