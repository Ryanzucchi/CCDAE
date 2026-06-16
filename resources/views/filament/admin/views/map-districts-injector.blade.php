<div x-data="{
    init() {
        let attempts = 0;
        let interval = setInterval(() => {
            let mapEl = document.querySelector('[x-data^=\'mapPicker\']');
            if (mapEl) {
                let mapData = Alpine.$data(mapEl);
                if (mapData && mapData.map) {
                    clearInterval(interval);
                    let map = mapData.map;
                    
                    // Fallback para L caso não esteja global
                    let L_ref = window.L;
                    if(!L_ref && mapData.marker) {
                         // hack para pegar o L se não estiver global
                         L_ref = mapData.marker._map ? window.L : window.L;
                    }
                    
                    fetch('/api/distritos/geojson')
                        .then(res => res.json())
                        .then(data => {
                            if (L_ref) {
                                L_ref.geoJSON(data, {
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
                            } else {
                                console.error('Leaflet L reference not found');
                            }
                        });
                }
            }
            attempts++;
            if(attempts > 100) clearInterval(interval); // Timeout após 10 segundos
        }, 100);
    }
}"></div>
