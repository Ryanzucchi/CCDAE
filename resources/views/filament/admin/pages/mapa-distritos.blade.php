<x-filament-panels::page>
    <style>
        /* RESET do Tailwind para SVG/Canvas do Leaflet: Extremamente crucial para que os polígonos não sumam! */
        .leaflet-pane svg,
        .leaflet-pane canvas {
            max-width: none !important;
            display: block !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            z-index: 9999 !important; /* Garante que os vetores fiquem acima do mapa base */
        }
        .leaflet-pane path {
            pointer-events: visiblePainted !important;
        }
        .leaflet-overlay-pane {
            z-index: 9999 !important;
        }
        .leaflet-popup-pane {
            z-index: 10000 !important; /* Garante que o popup fique ACIMA dos polígonos */
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

        <!-- 
            Este container DOM está 100% isolado do Livewire. 
            Ele NUNCA deve receber um x-show para esconder toda a caixa, 
            pois o Leaflet não sabe calcular área em displays hidden.
        -->
        <div wire:ignore style="position: relative; border-radius: 0.5rem; overflow: hidden; height: 600px;">
            <!-- O Container do Mapa fica exposto eternamente -->
            <div id="distritos-map-container" x-data="distritosMapPage" x-init="init()" style="width: 100%; height: 100%; z-index: 10;"></div>
            
            <!-- Adicionando Turf.js para gerar Polígonos Fronteiriços automaticamente (Voronoi) -->
            <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

            <script>
                // Variáveis globais para não serem transformadas em Proxy pelo Alpine.
                let leafletMap = null;
                let leafletLayerGroup = null;

                document.addEventListener('alpine:init', () => {
                    Alpine.data('distritosMapPage', () => ({
                        init() {
                            setTimeout(() => {
                                let mapContainer = document.getElementById('distritos-map-container');
                                if (!mapContainer) return;
                                
                                // Se o container já tiver um mapa (ex: Livewire re-renderizou ou Alpine rodou x-init 2x), destruímos o antigo
                                if (leafletMap !== null) {
                                    leafletMap.remove();
                                    leafletMap = null;
                                } else if (mapContainer._leaflet_id) {
                                    // Previne o erro "Map container is already initialized" limpando o id do leaflet
                                    mapContainer._leaflet_id = null;
                                }

                                // Prefer Canvas para evitar conflitos de renderização SVG com Tailwind/Livewire
                                leafletMap = L.map('distritos-map-container', {
                                    preferCanvas: true,
                                    zoomAnimation: false,
                                    fadeAnimation: false
                                }).setView([-15.7801, -47.9292], 4);

                                leafletLayerGroup = L.featureGroup().addTo(leafletMap);

                                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                                    attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
                                }).addTo(leafletMap);

                                leafletMap.invalidateSize();
                                
                                // Carrega inicial via blade direto pro JS
                                let inicial = @json($this->getDistritosDaCidadeProperty());
                                this.loadMarkers(inicial);
                            }, 300);

                            // O Livewire avisa o JS que novos distritos chegaram (ou a cidade mudou)
                            window.addEventListener('update-map', (e) => {
                                if(leafletMap) {
                                    // Dá 100ms para o Tailwind/Alpine esconder a tela sobreposta de "Estado Vazio"
                                    setTimeout(() => {
                                        leafletMap.invalidateSize(); 
                                        leafletLayerGroup.clearLayers();
                                        this.loadMarkers(e.detail.distritos);
                                    }, 100);
                                }
                            });
                        },

                        loadMarkers(distritos) {
                            let bounds = [];
                            
                            // Paleta de Cores Premium para os Polígonos
                            let colors = ['#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444'];
                            let colorIndex = 0;

                            if (!distritos || distritos.length === 0) {
                                // Se não houver nada, mantem uma visualização genérica e sai
                                leafletMap.setView([-15.7801, -47.9292], 4);
                                return;
                            }

                            console.log('Distritos carregados:', distritos);
                            
                            let pointsWithoutGeojson = [];

                            distritos.forEach(d => {
                                let popupContent = `
                                    <div style="text-align:center; min-width: 150px;">
                                        <b style="font-size: 1.1em; color: #111827;">${d.nome}</b><br>
                                        <button 
                                            onclick="window.dispatchEvent(new CustomEvent('edit-distrito-event', {detail: ${d.id}}))" 
                                            style="margin-top: 12px; padding: 6px 12px; width: 100%; background-color: #f59e0b; color: white; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: background 0.3s;">
                                            Editar Distrito
                                        </button>
                                    </div>
                                `;

                                if (d.geojson) {
                                    try {
                                        let geoData = d.geojson;
                                        // Garante parse recursivo caso o Livewire tenha encadeado string dentro de string
                                        while(typeof geoData === 'string') {
                                            geoData = JSON.parse(geoData);
                                        }
                                        
                                        // Aceita geometrias puras ou envoltas em feature
                                        if (geoData && geoData.type) {
                                            let color = colors[colorIndex % colors.length];
                                            colorIndex++;

                                            let geoLayer = L.geoJSON(geoData, {
                                                style: { color: color, weight: 2, opacity: 0.9, fillOpacity: 0.45 }
                                            });

                                            geoLayer.bindPopup(popupContent);
                                            leafletLayerGroup.addLayer(geoLayer);
                                            
                                            let layerBounds = geoLayer.getBounds();
                                            bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                            bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                        } else {
                                            if(d.latitude && d.longitude) pointsWithoutGeojson.push({d: d, popup: popupContent, color: colors[colorIndex % colors.length]});
                                        }
                                    } catch (e) {
                                        if(d.latitude && d.longitude) pointsWithoutGeojson.push({d: d, popup: popupContent, color: colors[colorIndex % colors.length]});
                                    }
                                } else {
                                    if(d.latitude && d.longitude) pointsWithoutGeojson.push({d: d, popup: popupContent, color: colors[colorIndex % colors.length]});
                                }
                                colorIndex++;
                            });
                            
                            // GERADOR DE POLÍGONOS INTELIGENTE (VORONOI)
                            // Se os distritos só tem um ponto (lat/lng), criamos fronteiras matemáticas sem sobreposição
                            if (pointsWithoutGeojson.length > 0 && typeof turf !== 'undefined') {
                                let turfPoints = [];
                                pointsWithoutGeojson.forEach(item => {
                                    turfPoints.push(turf.point([item.d.longitude, item.d.latitude], { itemData: item }));
                                });
                                
                                let pointsCollection = turf.featureCollection(turfPoints);
                                
                                // Limite do mapa para desenhar (bounding box)
                                let bbox = turf.bbox(pointsCollection);
                                // Adiciona padding para as bordas externas (10%)
                                let latPad = (bbox[3] - bbox[1]) * 0.1 || 0.05;
                                let lngPad = (bbox[2] - bbox[0]) * 0.1 || 0.05;
                                let paddedBbox = [bbox[0] - lngPad, bbox[1] - latPad, bbox[2] + lngPad, bbox[3] + latPad];
                                
                                try {
                                    let voronoiPolygons = turf.voronoi(pointsCollection, { bbox: paddedBbox });
                                    
                                    voronoiPolygons.features.forEach((feature, index) => {
                                        let item = turfPoints[index].properties.itemData;
                                        if (feature) {
                                            let color = colors[colorIndex % colors.length];
                                            colorIndex++;
                                            
                                            let geoLayer = L.geoJSON(feature, {
                                                style: { color: color, weight: 2, opacity: 0.9, fillOpacity: 0.45 }
                                            });
                                            
                                            geoLayer.bindPopup(item.popup);
                                            leafletLayerGroup.addLayer(geoLayer);
                                            
                                            let layerBounds = geoLayer.getBounds();
                                            bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                            bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                        } else {
                                            this.addFallbackMarker(item.d, item.popup, bounds, item.color);
                                        }
                                    });
                                } catch (error) {
                                    console.error("Erro gerando Voronoi:", error);
                                    // Fallback para pontos normais se falhar a matemática
                                    pointsWithoutGeojson.forEach(item => this.addFallbackMarker(item.d, item.popup, bounds, item.color));
                                }
                            } else if (pointsWithoutGeojson.length > 0) {
                                pointsWithoutGeojson.forEach(item => this.addFallbackMarker(item.d, item.popup, bounds, item.color));
                            }

                            if(bounds.length > 0) {
                                leafletMap.fitBounds(bounds, { padding: [50, 50] });
                            }
                        },

                        addFallbackMarker(d, popupContent, bounds, color) {
                            if (d.latitude && d.longitude) {
                                // Usa circleMarker em vez do marker padrão que pode ter a imagem de pino quebrada "sem cor"
                                let fallbackColor = color || '#3b82f6';
                                let marker = L.circleMarker([d.latitude, d.longitude], {
                                    radius: 12,
                                    fillColor: fallbackColor,
                                    color: "#ffffff",
                                    weight: 2,
                                    opacity: 1,
                                    fillOpacity: 0.8
                                });
                                marker.bindPopup(popupContent);
                                leafletLayerGroup.addLayer(marker);
                                bounds.push([d.latitude, d.longitude]);
                            }
                        }
                    }))
                });

                window.addEventListener('edit-distrito-event', (e) => {
                    @this.mountAction('editDistrito', { id: e.detail });
                });
            </script>
            
            <!-- Painel Sobreposto de Estado Vazio controlado pelo Alpine -->
            <div x-data="{
                hasCity: {{ count($this->getDistritosDaCidadeProperty()) > 0 ? 'true' : 'false' }}
            }" 
            x-on:update-map.window="hasCity = $event.detail.distritos && $event.detail.distritos.length > 0"
            x-show="!hasCity"
            class="absolute inset-0 flex items-center justify-center p-12 text-center text-gray-500 bg-gray-50/90 dark:bg-gray-800/90 backdrop-blur-sm z-[20] transition-opacity duration-300">
                <div>
                    <x-filament::icon
                        alias="panels::pages.dashboard.empty"
                        icon="heroicon-o-map"
                        class="w-16 h-16 mx-auto mb-4 text-gray-400"
                    />
                    <h3 class="text-xl font-medium text-gray-700 dark:text-gray-200">Escolha o seu Foco</em></h3>
                    <p class="mt-2 text-sm max-w-sm">Por favor, selecione uma cidade no menu acima para iniciar a varredura geográfica de distritos.</p>
                </div>
            </div>

        </div>
    </x-filament::section>

    <!-- Modal nativo do Filament escondido, usado para os forms -->
    <x-filament-actions::modals />
</x-filament-panels::page>
