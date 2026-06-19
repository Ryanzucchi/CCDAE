<x-filament-panels::page>
    <style>
        /* RESET do Tailwind para SVG/Canvas do Leaflet: Extremamente crucial para os polígonos */
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
        
        /* Legendas e Controles */
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
        .legend-color { width: 14px; height: 14px; border-radius: 50%; margin-right: 8px; display: inline-block; }
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
            <div id="infra-map-container" x-data="infraMapPage" x-init="init()" style="width: 100%; height: 100%; z-index: 10;"></div>
            
            <script>
                let infraMap = null;
                let infraLayerGroup = null;

                document.addEventListener('alpine:init', () => {
                    Alpine.data('infraMapPage', () => ({
                        init() {
                            setTimeout(() => {
                                let mapContainer = document.getElementById('infra-map-container');
                                if (!mapContainer) return;
                                
                                if (infraMap !== null) {
                                    infraMap.remove();
                                    infraMap = null;
                                } else if (mapContainer._leaflet_id) {
                                    mapContainer._leaflet_id = null;
                                }

                                infraMap = L.map('infra-map-container', {
                                    preferCanvas: true, // ALTA PERFORMANCE
                                    zoomAnimation: false,
                                    fadeAnimation: false
                                }).setView([-23.550520, -46.633308], 10);

                                infraLayerGroup = L.featureGroup().addTo(infraMap);

                                // Dark mode premium tile layer
                                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                                    attribution: '&copy; OpenStreetMap &copy; CARTO'
                                }).addTo(infraMap);

                                // Adicionar a legenda
                                let legend = L.control({position: 'bottomright'});
                                legend.onAdd = function (map) {
                                    let div = L.DomUtil.create('div', 'map-legend');
                                    div.innerHTML = `
                                        <strong>Mapeamento de Infraestrutura</strong><br>
                                        <div class="legend-item" style="margin-top:8px;"><span class="legend-color" style="background:#f59e0b;"></span> Centrais de Distribuição</div>
                                        <div class="legend-item"><span class="legend-color" style="background:#9ca3af; border:1px solid #000;"></span> Postes</div>
                                        <div class="legend-item"><span class="legend-color" style="background:#ec4899;"></span> Antenas Telecom</div>
                                        <div class="legend-item"><span class="legend-color" style="background:#10b981;"></span> Equipamentos</div>
                                        <div class="legend-item" style="margin-top:8px;"><strong>Fluxo de Trânsito</strong></div>
                                        <div class="legend-item"><span class="legend-line" style="background:#22c55e;"></span> Via Livre</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#eab308;"></span> Moderado</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#f97316;"></span> Intenso</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#ef4444;"></span> Parado</div>
                                        <div class="legend-item" style="margin-top:8px;"><strong>Cabeamento</strong></div>
                                        <div class="legend-item"><span class="legend-line" style="background:#06b6d4;"></span> Cabo de Fibra Óptica</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#a855f7;"></span> Cabo de Alta Tensão</div>
                                        <div class="legend-item"><span class="legend-line" style="background:#3b82f6;"></span> Outros Cabeamentos</div>
                                    `;
                                    return div;
                                };
                                legend.addTo(infraMap);

                                infraMap.invalidateSize();
                                
                                let payload = {
                                    postes: @json($this->postes),
                                    antenas: @json($this->antenas),
                                    centrais: @json($this->centrais),
                                    equipamentos: @json($this->equipamentos),
                                    cabeamentos: @json($this->cabeamentos),
                                    vias: @json($this->vias),
                                };
                                this.renderMap(payload);
                            }, 300);

                            window.addEventListener('update-infra-map', (e) => {
                                if(infraMap) {
                                    setTimeout(() => {
                                        infraMap.invalidateSize(); 
                                        infraLayerGroup.clearLayers();
                                        let payload = e.detail[0] || e.detail;
                                        this.renderMap(payload);
                                    }, 100);
                                }
                            });
                        },

                        renderMap(data) {
                            let bounds = [];

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
                                            layer.bindPopup(`<b>Via:</b> ${v.nome}<br><b>Congestionamento:</b> <span style="color:${color}; font-weight:bold; text-transform:uppercase;">${v.nivel_congestionamento}</span><br><b>Velocidade Média:</b> ${v.velocidade_media} km/h`);
                                            infraLayerGroup.addLayer(layer);
                                            let layerBounds = layer.getBounds();
                                            bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                            bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                        } catch(e) {}
                                    }
                                });
                            }

                            // Centrais de Distribuição (Polygons or Points)

                            if (data.centrais) {
                                data.centrais.forEach(c => {
                                    if (c.geojson) {
                                        try {
                                            let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                            let layer = L.geoJSON(geo, { style: { color: '#f59e0b', weight: 2, fillOpacity: 0.4 } });
                                            layer.bindPopup(`<b>Central:</b> ${c.nome}<br><b>Tipo:</b> ${c.tipo}<br><b>Estado:</b> ${c.estado_conservacao}`);
                                            infraLayerGroup.addLayer(layer);
                                            let layerBounds = layer.getBounds();
                                            bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                            bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                        } catch (e) {}
                                    } else if (c.latitude && c.longitude) {
                                        let marker = L.circleMarker([c.latitude, c.longitude], { radius: 10, fillColor: '#f59e0b', color: '#fff', weight: 2, fillOpacity: 0.9 });
                                        marker.bindPopup(`<b>Central:</b> ${c.nome}<br><b>Tipo:</b> ${c.tipo}`);
                                        infraLayerGroup.addLayer(marker);
                                        bounds.push([c.latitude, c.longitude]);
                                    }
                                });
                            }

                            // Cabeamentos (Lines)
                            if (data.cabeamentos) {
                                data.cabeamentos.forEach(c => {
                                    if (c.geojson) {
                                        try {
                                            let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                            let color = '#3b82f6';
                                            if(c.tipo_cabo === 'fibra_optica') color = '#06b6d4';
                                            if(c.tipo_cabo === 'eletrico_alta_tensao') color = '#ef4444';
                                            
                                            let layer = L.geoJSON(geo, { style: { color: color, weight: 4, opacity: 0.85 } });
                                            layer.bindPopup(`<b>Cabo:</b> ${c.nome || c.tipo_cabo}<br><b>Estado:</b> ${c.estado_conservacao}`);
                                            infraLayerGroup.addLayer(layer);
                                            let layerBounds = layer.getBounds();
                                            bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                            bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                        } catch(e) {}
                                    }
                                });
                            }

                            // Equipamentos soltos (Points)
                            if (data.equipamentos) {
                                data.equipamentos.forEach(eq => {
                                    if (eq.latitude && eq.longitude) {
                                        let marker = L.circleMarker([eq.latitude, eq.longitude], { radius: 5, fillColor: '#10b981', color: '#fff', weight: 1, fillOpacity: 1 });
                                        marker.bindPopup(`<b>Equipamento:</b> ${eq.nome}<br><b>Tipo:</b> ${eq.tipo}<br><b>Estado:</b> ${eq.estado_conservacao}`);
                                        infraLayerGroup.addLayer(marker);
                                        bounds.push([eq.latitude, eq.longitude]);
                                    }
                                });
                            }

                            // Postes (Points)
                            if (data.postes) {
                                data.postes.forEach(p => {
                                    if (p.latitude && p.longitude) {
                                        let marker = L.circleMarker([p.latitude, p.longitude], { radius: 5, fillColor: '#9ca3af', color: '#000', weight: 1, fillOpacity: 1 });
                                        marker.bindPopup(`<b>Poste:</b> ${p.codigo_patrimonio || 'S/N'}<br><b>Material:</b> ${p.material}<br><b>Estado:</b> ${p.estado_conservacao}`);
                                        infraLayerGroup.addLayer(marker);
                                        bounds.push([p.latitude, p.longitude]);
                                    }
                                });
                            }

                            // Antenas (Points)
                            if (data.antenas) {
                                data.antenas.forEach(a => {
                                    if (a.latitude && a.longitude) {
                                        let marker = L.circleMarker([a.latitude, a.longitude], { radius: 6, fillColor: '#ec4899', color: '#fff', weight: 2, fillOpacity: 1 });
                                        marker.bindPopup(`<b>Antena:</b> ${a.codigo_patrimonio || 'S/N'}<br><b>Sinal:</b> ${a.tipo_sinal}<br><b>Estado:</b> ${a.estado_conservacao}`);
                                        infraLayerGroup.addLayer(marker);
                                        bounds.push([a.latitude, a.longitude]);
                                    }
                                });
                            }

                            if(bounds.length > 0) {
                                infraMap.fitBounds(bounds, { padding: [50, 50] });
                            }
                        }
                    }))
                });
            </script>
        </div>
    </x-filament::section>
</x-filament-panels::page>
