<div>
    <style>
        .leaflet-pane svg,
        .leaflet-pane canvas {
            max-width: none !important;
            max-height: none !important;
            display: block !important;
        }
        /* Corrigir o bug onde o SVG do Leaflet muda de tamanho em zoom com Tailwind */
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
                            new ResizeObserver(() => {
                                let currentWidth = map.getContainer().clientWidth;
                                map.invalidateSize();
                                
                                // Quando o modal abrir e o mapa ganhar largura, foca no distrito desenhado/marcado!
                                if (lastWidth === 0 && currentWidth > 0) {
                                    setTimeout(() => {
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
                                                        if (layer.getBounds) {
                                                            map.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 15 });
                                                        } else if (layer.getLatLng) {
                                                            map.setView(layer.getLatLng(), 15);
                                                        }
                                                    } catch(e) {}
                                                }
                                            });
                                        }
                                    }, 200); // Aguarda a injeção do componente
                                }
                                lastWidth = currentWidth;
                            }).observe(map.getContainer());
                        }

                        fetch('/api/distritos/geojson')
                            .then(res => res.json())
                            .then(data => {
                                window.L.geoJSON(data, {
                                    style: {
                                        color: '#9ca3af',
                                        weight: 2,
                                        opacity: 0.6,
                                        fillOpacity: 0.15,
                                        dashArray: '5, 5'
                                    },
                                    onEachFeature: function(feature, layer) {
                                        if (feature.properties && feature.properties.nome) {
                                            layer.bindTooltip(feature.properties.nome, {permanent: false, sticky: true});
                                        }
                                    }
                                }).addTo(map);
                            });
                    }
                }
                attempts++;
                if(attempts > 100) clearInterval(interval);
            }, 100);
        }
    }"></div>
</div>
