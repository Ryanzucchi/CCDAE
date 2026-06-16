<div x-data="{
    init() {
        let attempts = 0;
        let interval = setInterval(() => {
            let mapContainer = document.querySelector('.leaflet-container');
            if (mapContainer && mapContainer._leaflet_map) {
                clearInterval(interval);
                let map = mapContainer._leaflet_map;
                
                fetch('/api/distritos/geojson')
                    .then(res => res.json())
                    .then(data => {
                        L.geoJSON(data, {
                            style: {
                                color: '#9ca3af', // gray-400
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
            attempts++;
            if(attempts > 50) clearInterval(interval); // Timeout após 5 segundos
        }, 100);
    }
}"></div>
