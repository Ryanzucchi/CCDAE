<div>
    <style>
        .leaflet-pane svg,
        .leaflet-pane canvas {
            max-width: none !important;
            display: block !important;
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
