<div>
    <style>
        .leaflet-pane svg {
            max-width: none !important;
            max-height: none !important;
        }
        .leaflet-overlay-pane svg {
            max-width: none !important;
            max-height: none !important;
        }
    </style>
    <!-- Carrega a biblioteca global L caso o Filament não a exponha -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <div x-data="{
        init() {
            let attempts = 0;
            let interval = setInterval(() => {
                let mapEl = document.querySelector('[x-data^=\'mapPicker\']');
                if (mapEl) {
                    let mapData = Alpine.$data(mapEl);
                    if (mapData && mapData.map && window.L) {
                        clearInterval(interval);
                        let map = mapData.map;
                        
                        // Correção para o desalinhamento do mouse e foco no ponto editado ao abrir o modal
                        if (!map._hasResizeObserver) {
                            map._hasResizeObserver = true;
                            let lastWidth = 0;
                            
                            // Adicionar hook do Geoman para alterar a cor do polígono sendo desenhado (alto contraste)
                            if (map.pm) {
                                map.pm.setPathOptions({
                                    color: '#ef4444', // Vermelho bem visível
                                    fillColor: '#ef4444',
                                    fillOpacity: 0.4,
                                    weight: 3,
                                });
                            }

                            // Força um invalidate logo no início caso esteja em modal
                            setTimeout(() => { map.invalidateSize(); }, 300);
                            
                            new ResizeObserver(() => {
                                let currentWidth = map.getContainer().clientWidth;
                                map.invalidateSize();
                                
                                // Quando o modal abrir e o mapa ganhar largura, foca no distrito desenhado/marcado!
                                if (lastWidth === 0 && currentWidth > 0) {
                                    setTimeout(() => {
                                        map.invalidateSize(); // Mais um invalidate por garantia
                                        let boundsSet = false;
                                        let layers = map.pm ? map.pm.getGeomanDrawLayers() : [];
                                        if (layers && layers.length > 0) {
                                            let group = L.featureGroup(layers);
                                            map.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: 15 });
                                            boundsSet = true;
                                        } 
                                        
                                        if (!boundsSet) {
                                            // Fallback: varre as layers pra achar o polígono ou ponto
                                            map.eachLayer((layer) => {
                                                if (layer instanceof L.Marker || layer instanceof L.Polygon || layer instanceof L.GeoJSON) {
                                                    try {
                                                        if (layer.options && layer.options.color === '#ef4444') { // É a nossa layer desenhada
                                                            if (layer.getBounds) {
                                                                map.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 15 });
                                                            } else if (layer.getLatLng) {
                                                                map.setView(layer.getLatLng(), 15);
                                                            }
                                                        }
                                                    } catch(e) {}
                                                }
                                            });
                                        }
                                    }, 300); // Aguarda a injeção do componente
                                }
                                lastWidth = currentWidth;
                            }).observe(map.getContainer());
                        }

                        fetch('/api/distritos/geojson')
                            .then(res => res.json())
                            .then(data => {
                                let layer = window.L.geoJSON(data, {
                                    style: {
                                        color: '#3b82f6', // Azul brilhante
                                        weight: 2,
                                        opacity: 0.9,
                                        fillOpacity: 0.2,
                                        dashArray: '4, 4'
                                    },
                                    onEachFeature: function(feature, l) {
                                        if (feature.properties && feature.properties.nome) {
                                            l.bindTooltip(feature.properties.nome, {permanent: false, sticky: true});
                                        }
                                    }
                                });

                                // Função para adicionar ao mapa com segurança
                                const addToMapSafely = () => {
                                    layer.addTo(map);
                                    // Força a re-renderização do SVG para evitar desalinhamento
                                    setTimeout(() => {
                                        if (map.getRenderer(layer)) {
                                            map.getRenderer(layer)._update();
                                        }
                                        map.invalidateSize();
                                    }, 100);
                                };

                                // Se o mapa estiver no meio de uma animação ou arrasto, espera terminar
                                if (map._animatingZoom || map._animatingPan) {
                                    map.once('zoomend moveend', addToMapSafely);
                                } else {
                                    addToMapSafely();
                                }
                            });
                    }
                }
                attempts++;
                if(attempts > 100) clearInterval(interval);
            }, 100);
        }
    }"></div>
</div>
