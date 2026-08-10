import { bbox } from '@turf/bbox';
import { centroid } from '@turf/centroid';
import { featureCollection, point } from '@turf/helpers';
import {
    createMap,
    fitMapToFeatureCollection,
    maplibregl,
    normalizeGeometry,
    registerLivewireMap,
} from './maplibre/base-map';

const SOURCE_ID = 'ccdae-climate';
const DEFAULT_CENTER = [-57.6875, -16.07083];
let activeController = null;

const METRICS = {
    temperatura: {
        field: 'temperatura',
        title: 'Temperatura (°C)',
        height: (value) => Math.max(0, value * 8),
        heightScale: 'Altura 3D: 8 m por °C',
        grades: ['< 24°C', '24°C – 28°C', '28°C – 32°C', '32°C – 36°C', '> 36°C'],
        colors: ['#3b82f6', '#4ade80', '#facc15', '#f97316', '#dc2626'],
        color: (value) => value > 36 ? '#dc2626' : value > 32 ? '#f97316' : value > 28 ? '#facc15' : value > 24 ? '#4ade80' : '#3b82f6',
    },
    radiacao: {
        field: 'radiacao', title: 'Radiação solar (W/m²)',
        height: (value) => Math.max(0, value * 0.35), heightScale: 'Altura 3D: 0,35 m por W/m²',
        grades: ['< 50', '50 – 200', '200 – 500', '500 – 800', '> 800'],
        colors: ['#475569', '#a16207', '#eab308', '#f97316', '#ef4444'],
        color: (value) => value > 800 ? '#ef4444' : value > 500 ? '#f97316' : value > 200 ? '#eab308' : value > 50 ? '#a16207' : '#475569',
    },
    chuva: {
        field: 'chuva', title: 'Precipitação (mm)',
        height: (value) => Math.max(0, value * 8), heightScale: 'Altura 3D: 8 m por mm',
        grades: ['Sem chuva', '0 – 5 mm', '5 – 20 mm', '20 – 40 mm', '> 40 mm'],
        colors: ['#e5e7eb', '#bfdbfe', '#60a5fa', '#2563eb', '#1e3a8a'],
        color: (value) => value > 40 ? '#1e3a8a' : value > 20 ? '#2563eb' : value > 5 ? '#60a5fa' : value > 0 ? '#bfdbfe' : '#e5e7eb',
    },
    particulas: {
        field: 'particulas', title: 'Qualidade do ar (PM2.5)',
        height: (value) => Math.max(0, value * 2.5), heightScale: 'Altura 3D: 2,5 m por µg/m³',
        grades: ['Bom (< 40)', 'Moderado (40–80)', 'Ruim (80–120)', 'Péssimo (> 120)'],
        colors: ['#22c55e', '#eab308', '#ef4444', '#7f1d1d'],
        color: (value) => value > 120 ? '#7f1d1d' : value > 80 ? '#ef4444' : value > 40 ? '#eab308' : '#22c55e',
    },
    vento: {
        field: 'vento_velocidade', title: 'Velocidade do vento (km/h)',
        height: (value) => Math.max(0, value * 8), heightScale: 'Altura 3D: 8 m por km/h',
        grades: ['Leve (< 15 km/h)', 'Moderado (15–30 km/h)', 'Ventania (> 30 km/h)'],
        colors: ['#22c55e', '#eab308', '#dc2626'],
        color: (value) => value > 30 ? '#dc2626' : value > 15 ? '#eab308' : '#22c55e',
    },
    pressao: {
        field: 'pressao', title: 'Pressão atmosférica (hPa)',
        height: (value) => Math.max(0, (value - 980) * 5), heightScale: 'Altura 3D: 5 m por hPa acima de 980',
        grades: ['< 1000', '1000 – 1010', '1010 – 1020', '> 1020'],
        colors: ['#f59e0b', '#22c55e', '#60a5fa', '#a78bfa'],
        color: (value) => value > 1020 ? '#a78bfa' : value > 1010 ? '#60a5fa' : value > 1000 ? '#22c55e' : '#f59e0b',
    },
    uv: {
        field: 'uv', title: 'Índice UV',
        height: (value) => Math.max(0, value * 20), heightScale: 'Altura 3D: 20 m por ponto UV',
        grades: ['Baixo (0–2)', 'Moderado (3–5)', 'Alto (6–7)', 'Muito alto (8–10)', 'Extremo (11+)'],
        colors: ['#22c55e', '#facc15', '#f97316', '#dc2626', '#7f1d1d'],
        color: (value) => value >= 11 ? '#7f1d1d' : value >= 8 ? '#dc2626' : value >= 6 ? '#f97316' : value >= 3 ? '#facc15' : '#22c55e',
    },
};

function finiteValue(value) {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

function climateProperties(district, metric) {
    const definition = METRICS[metric] ?? METRICS.temperatura;
    const value = finiteValue(district[definition.field]);
    return {
        kind: 'district',
        districtId: Number(district.id),
        name: String(district.nome ?? 'Distrito'),
        metric,
        metricValue: value,
        blockHeight: value === null ? 0 : definition.height(value),
        color: value === null ? '#4b5563' : definition.color(value),
        temperatura: district.temperatura ?? null,
        sensacao: district.sensacao_termica ?? null,
        radiacao: district.radiacao ?? null,
        uv: district.uv ?? null,
        windSpeed: district.vento_velocidade ?? null,
        windDirection: district.vento_direcao ?? null,
        rain: district.chuva ?? null,
        particles: district.particulas ?? null,
        pressure: district.pressao ?? null,
        timestamp: String(district.timestamp ?? 'N/A'),
    };
}

function windPoints(feature, properties) {
    if (properties.metricValue === null || finiteValue(properties.windDirection) === null) return [];
    const bounds = bbox(feature);
    const centerLongitude = (bounds[0] + bounds[2]) / 2;
    const centerLatitude = (bounds[1] + bounds[3]) / 2;
    const longitudeSpan = bounds[2] - bounds[0];
    const latitudeSpan = bounds[3] - bounds[1];
    const positions = [
        [centerLongitude, centerLatitude],
        [centerLongitude - longitudeSpan * 0.22, centerLatitude - latitudeSpan * 0.22],
        [centerLongitude + longitudeSpan * 0.22, centerLatitude + latitudeSpan * 0.22],
        [centerLongitude + longitudeSpan * 0.22, centerLatitude - latitudeSpan * 0.22],
        [centerLongitude - longitudeSpan * 0.22, centerLatitude + latitudeSpan * 0.22],
    ];

    return positions.map((coordinates, index) => {
        const arrow = point(coordinates, {
            ...properties,
            kind: 'wind',
            rotation: Number(properties.windDirection),
            arrowSize: Math.min(34, 18 + Number(properties.windSpeed) * 0.35),
        });
        arrow.id = `wind-${properties.districtId}-${index}`;
        return arrow;
    });
}

function buildFeatures(districts, metric) {
    const districtFeatures = [];
    const effectFeatures = [];
    const values = districts.map((district) => finiteValue(district[METRICS[metric]?.field])).filter((value) => value !== null);
    const minimum = values.length ? Math.min(...values) : 0;
    const maximum = values.length ? Math.max(...values) : 1;
    const range = maximum - minimum || 1;

    districts.forEach((district) => {
        const geometry = normalizeGeometry(district.geojson);
        const properties = climateProperties(district, metric);
        let feature = null;

        if (geometry) {
            feature = { type: 'Feature', id: `district-${district.id}`, properties, geometry };
        } else {
            const latitude = finiteValue(district.latitude);
            const longitude = finiteValue(district.longitude);
            if (latitude !== null && longitude !== null) {
                feature = point([longitude, latitude], properties);
                feature.id = `district-${district.id}`;
            }
        }
        if (!feature) return;
        districtFeatures.push(feature);

        if (metric === 'vento') {
            effectFeatures.push(...windPoints(feature, properties));
        } else if (properties.metricValue !== null) {
            const center = centroid(feature, {
                properties: {
                    ...properties,
                    kind: 'heat',
                    intensity: Math.max(0.18, (properties.metricValue - minimum) / range),
                },
            });
            center.id = `heat-${district.id}`;
            effectFeatures.push(center);
        }
    });

    return {
        all: featureCollection([...districtFeatures, ...effectFeatures]),
        districts: featureCollection(districtFeatures),
    };
}

function popupContent(properties) {
    const container = document.createElement('div');
    container.className = 'ccdae-climate-popup';
    const title = document.createElement('strong');
    title.textContent = properties.name ?? 'Distrito';
    container.appendChild(title);

    const rows = [
        ['Temperatura', properties.temperatura !== null ? `${properties.temperatura}°C` : 'N/A'],
        ['Sensação térmica', properties.sensacao !== null ? `${properties.sensacao}°C` : 'N/A'],
        ['Radiação solar', properties.radiacao !== null ? `${properties.radiacao} W/m²` : 'N/A'],
        ['Índice UV', properties.uv ?? 'N/A'],
        ['Vento', properties.windSpeed !== null ? `${properties.windSpeed} km/h a ${properties.windDirection ?? 0}°` : 'N/A'],
        ['Precipitação', properties.rain !== null ? `${properties.rain} mm` : '0 mm'],
        ['PM2.5', properties.particles !== null ? `${properties.particles} µg/m³` : 'N/A'],
        ['Pressão', properties.pressure !== null ? `${properties.pressure} hPa` : 'N/A'],
        ['Última leitura', properties.timestamp ?? 'N/A'],
    ];
    rows.forEach(([label, value]) => {
        const row = document.createElement('div');
        const labelElement = document.createElement('b');
        labelElement.textContent = `${label}: `;
        row.append(labelElement, document.createTextNode(value));
        container.appendChild(row);
    });
    return container;
}

class ClimateMapController {
    constructor(container) {
        this.container = container;
        this.loaded = false;
        this.pendingState = null;
        this.onData = this.onData.bind(this);
        this.map = createMap(container, { center: DEFAULT_CENTER, zoom: 12 });
        this.map.setMaxZoom(18);
        this.map.on('load', () => this.onLoad());
        window.addEventListener('climate-map-data', this.onData);
    }

    onLoad() {
        const arrowCanvas = document.createElement('canvas');
        arrowCanvas.width = 48;
        arrowCanvas.height = 20;
        const arrowContext = arrowCanvas.getContext('2d');
        arrowContext.strokeStyle = '#38bdf8';
        arrowContext.fillStyle = '#38bdf8';
        arrowContext.lineWidth = 4;
        arrowContext.lineCap = 'round';
        arrowContext.beginPath();
        arrowContext.moveTo(3, 10);
        arrowContext.lineTo(35, 10);
        arrowContext.stroke();
        arrowContext.beginPath();
        arrowContext.moveTo(31, 3);
        arrowContext.lineTo(45, 10);
        arrowContext.lineTo(31, 17);
        arrowContext.closePath();
        arrowContext.fill();
        this.map.addImage('climate-wind-arrow', arrowContext.getImageData(0, 0, 48, 20));

        this.map.addSource(SOURCE_ID, { type: 'geojson', data: featureCollection([]) });
        this.map.addLayer({
            id: 'climate-heatmap', type: 'circle', source: SOURCE_ID,
            filter: ['==', ['get', 'kind'], 'heat'],
            minzoom: 10,
            paint: {
                'circle-color': ['get', 'color'],
                'circle-radius': ['interpolate', ['exponential', 1.35], ['zoom'], 10, 18, 13, 34, 16, 54],
                'circle-blur': 0.82,
                'circle-opacity': ['interpolate', ['linear'], ['get', 'intensity'], 0, 0.2, 1, 0.5],
                'circle-stroke-width': 0,
            },
        });
        this.map.addLayer({
            id: 'climate-district-fill', type: 'fill', source: SOURCE_ID,
            filter: ['all', ['==', ['get', 'kind'], 'district'], ['==', ['geometry-type'], 'Polygon']],
            paint: {
                'fill-color': ['get', 'color'],
                'fill-opacity': ['case', ['==', ['get', 'metric'], 'vento'], 0.22, 0.3],
            },
        });
        this.map.addLayer({
            id: 'climate-district-extrusion', type: 'fill-extrusion', source: SOURCE_ID,
            filter: ['all', ['==', ['get', 'kind'], 'district'], ['==', ['geometry-type'], 'Polygon']],
            paint: {
                'fill-extrusion-color': ['get', 'color'],
                'fill-extrusion-height': ['get', 'blockHeight'],
                'fill-extrusion-base': 0,
                'fill-extrusion-opacity': 0.72,
                'fill-extrusion-vertical-gradient': true,
                'fill-extrusion-height-transition': { duration: 450, delay: 0 },
            },
        });
        this.map.addLayer({
            id: 'climate-district-line', type: 'line', source: SOURCE_ID,
            filter: ['all', ['==', ['get', 'kind'], 'district'], ['==', ['geometry-type'], 'Polygon']],
            paint: {
                'line-color': ['get', 'color'],
                'line-width': ['case', ['==', ['get', 'metric'], 'vento'], 1.5, 1.1],
                'line-opacity': ['case', ['==', ['get', 'metric'], 'vento'], 0.8, 0.62],
            },
        });
        this.map.addLayer({
            id: 'climate-district-point', type: 'circle', source: SOURCE_ID,
            filter: ['all', ['==', ['get', 'kind'], 'district'], ['==', ['geometry-type'], 'Point']],
            paint: { 'circle-radius': 8, 'circle-color': ['get', 'color'], 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 2 },
        });
        this.map.addLayer({
            id: 'climate-wind-arrows', type: 'symbol', source: SOURCE_ID,
            filter: ['==', ['get', 'kind'], 'wind'],
            layout: {
                'icon-image': 'climate-wind-arrow',
                'icon-size': ['/', ['get', 'arrowSize'], 26],
                'icon-rotate': ['get', 'rotation'],
                'icon-rotation-alignment': 'map',
                'icon-allow-overlap': true,
            },
        });

        ['climate-district-extrusion', 'climate-district-point', 'climate-wind-arrows'].forEach((layerId) => {
            this.map.on('mouseenter', layerId, () => (this.map.getCanvas().style.cursor = 'pointer'));
            this.map.on('mouseleave', layerId, () => (this.map.getCanvas().style.cursor = ''));
            this.map.on('click', layerId, (event) => {
                const feature = event.features?.[0];
                if (!feature) return;
                new maplibregl.Popup({ maxWidth: '300px' })
                    .setLngLat(event.lngLat)
                    .setDOMContent(popupContent(feature.properties ?? {}))
                    .addTo(this.map);
            });
        });

        this.loaded = true;
        if (this.pendingState) this.render(this.pendingState);
    }

    onData(event) {
        this.render(event.detail ?? {});
    }

    render(state) {
        this.pendingState = state;
        if (!this.loaded) return;
        const metric = METRICS[state.metric] ? state.metric : 'temperatura';
        const data = buildFeatures(Array.isArray(state.data) ? state.data : [], metric);
        this.map.getSource(SOURCE_ID).setData(data.all);
        this.updateHeatmap(metric);
        this.updateLegend(metric);
        if (state.fit !== false) fitMapToFeatureCollection(this.map, data.districts, { padding: 20, maxZoom: 16 });
    }

    updateHeatmap(metric) {
        this.map.setPaintProperty('climate-heatmap', 'circle-color', ['get', 'color']);
    }

    updateLegend(metric) {
        const legend = document.getElementById('climate-map-legend');
        if (!legend) return;
        const definition = METRICS[metric];
        legend.replaceChildren();
        const title = document.createElement('strong');
        title.textContent = definition.title;
        legend.appendChild(title);
        const scale = document.createElement('div');
        scale.className = 'mt-1 mb-2 text-xs opacity-70';
        scale.textContent = definition.heightScale;
        legend.appendChild(scale);
        definition.grades.forEach((grade, index) => {
            const row = document.createElement('div');
            row.className = 'legend-item';
            const swatch = document.createElement('span');
            swatch.className = 'legend-color';
            swatch.style.background = definition.colors[index];
            row.append(swatch, document.createTextNode(grade));
            legend.appendChild(row);
        });
    }

    destroy() {
        window.removeEventListener('climate-map-data', this.onData);
        this.map.remove();
    }
}

function initialize() {
    const container = document.getElementById('climate-map');
    if (!container) return;
    activeController?.destroy();
    activeController = new ClimateMapController(container);
}

registerLivewireMap(initialize, () => {
    activeController?.destroy();
    activeController = null;
});
