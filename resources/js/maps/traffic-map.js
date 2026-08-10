import { featureCollection } from '@turf/helpers';
import {
    createMap,
    fitMapToFeatureCollection,
    maplibregl,
    martinTilejsonUrl,
    normalizeGeometry,
    parseJson,
    registerLivewireMap,
} from './maplibre/base-map';

const SOURCE_ID = 'ccdae-traffic';
const MVT_SOURCE_ID = 'ccdae-traffic-mvt';
const DISTRICT_LAYER_ID = 'ccdae-traffic-districts';
const ROAD_LAYER_ID = 'ccdae-traffic-roads';
let activeController = null;

const TRAFFIC_COLORS = {
    livre: '#22c55e',
    moderado: '#eab308',
    intenso: '#f97316',
    parado: '#ef4444',
};
const TRAFFIC_LEVELS = ['livre', 'moderado', 'intenso', 'parado'];

function normalizeHistoryReading(reading) {
    if (!Array.isArray(reading)) return reading;
    return {
        roadId: reading[0],
        speed: reading[1],
        vehicles: reading[2],
        congestion: TRAFFIC_LEVELS[reading[3]] ?? 'livre',
        accidents: reading[4] ?? 0,
        works: reading[5] ?? 0,
        flooded: Boolean(reading[6]),
    };
}

function buildFeatures(payload) {
    const districtFeatures = (payload.distritos ?? []).flatMap((district) => {
        const geometry = normalizeGeometry(district.geojson);
        return geometry
            ? [{ type: 'Feature', properties: { kind: 'district', name: district.nome ?? 'Distrito' }, geometry }]
            : [];
    });

    const roadFeatures = (payload.vias ?? []).flatMap((road) => {
        const geometry = normalizeGeometry(road.geojson);
        if (!geometry) return [];

        const congestion = String(road.nivel_congestionamento ?? 'livre').toLowerCase();
        return [{
            type: 'Feature',
            properties: {
                kind: 'road',
                roadId: Number(road.id),
                name: String(road.nome ?? 'Via'),
                congestion,
                speed: road.velocidade_media ?? null,
                maintenanceImpact: String(road.impacto_manutencao ?? 'não informado'),
                color: TRAFFIC_COLORS[congestion] ?? TRAFFIC_COLORS.livre,
            },
            geometry,
        }];
    });

    return { all: featureCollection([...districtFeatures, ...roadFeatures]), roads: featureCollection(roadFeatures) };
}

function popupContent(properties) {
    const container = document.createElement('div');
    container.className = 'ccdae-traffic-popup';

    const title = document.createElement('strong');
    title.textContent = `Via: ${properties.name ?? 'Via'}`;
    container.appendChild(title);

    const rows = [
        ['Congestionamento', String(properties.congestion ?? 'livre').toUpperCase()],
        ['Velocidade média', properties.speed ? `${properties.speed} km/h` : 'Não informada'],
        ['Impacto logístico', String(properties.maintenanceImpact ?? 'não informado').toUpperCase()],
        ['Volume na hora', properties.vehicles !== undefined && properties.vehicles !== null ? `${properties.vehicles} veículos` : 'Não informado'],
        ['Ocorrências', `${Number(properties.accidents ?? 0)} acidente(s), ${Number(properties.works ?? 0)} obra(s)${properties.flooded ? ', alagamento' : ''}`],
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

class TrafficMapController {
    constructor(container) {
        this.container = container;
        this.loaded = false;
        this.pendingPayload = parseJson(container.dataset.payload, { vias: [], distritos: [] });
        this.districtId = container.dataset.districtId || null;
        this.martinEnabled = container.dataset.martinEnabled === 'true';
        this.martinBaseUrl = (container.dataset.martinTilejsonUrl || '/tiles').replace(/\/$/, '');
        this.onUpdate = this.onUpdate.bind(this);
        this.onRangeInput = this.onRangeInput.bind(this);
        this.togglePlayback = this.togglePlayback.bind(this);
        this.historicalRoadIds = new Set();
        this.pendingHistory = [];
        this.timeline = [];
        this.frameIndex = 0;
        this.playTimer = null;
        this.timelineRequest = 0;
        this.rangeElement = document.getElementById('traffic-timeline-range');
        this.playElement = document.getElementById('traffic-timeline-play');
        this.selectedElement = document.getElementById('traffic-timeline-selected');
        this.map = createMap(container, { center: [-57.6806, -16.0722], zoom: 13 });
        this.map.on('load', () => this.onLoad());
        window.addEventListener('update-transito-map', this.onUpdate);
        this.rangeElement?.addEventListener('input', this.onRangeInput);
        this.playElement?.addEventListener('click', this.togglePlayback);
        this.loadTimeline(this.districtId);
    }

    onLoad() {
        this.map.addSource(SOURCE_ID, { type: 'geojson', data: featureCollection([]), promoteId: 'roadId' });
        this.map.addLayer({
            id: DISTRICT_LAYER_ID,
            type: 'line',
            source: SOURCE_ID,
            filter: ['==', ['get', 'kind'], 'district'],
            paint: {
                'line-color': '#6b7280',
                'line-width': 1.5,
                'line-dasharray': [3, 3],
                'line-opacity': 0.8,
            },
        });
        if (this.martinEnabled) {
            this.map.addSource(MVT_SOURCE_ID, {
                type: 'vector',
                url: martinTilejsonUrl(this.martinBaseUrl, 'vias'),
                promoteId: { vias: 'id' },
            });
        }

        const initialRoadFilter = this.roadFilter();
        this.map.addLayer({
            id: ROAD_LAYER_ID,
            type: 'line',
            source: this.martinEnabled ? MVT_SOURCE_ID : SOURCE_ID,
            ...(this.martinEnabled ? { 'source-layer': 'vias' } : {}),
            ...(initialRoadFilter ? { filter: initialRoadFilter } : {}),
            paint: {
                'line-color': ['coalesce', ['feature-state', 'historicalColor'], this.martinEnabled
                    ? ['match', ['downcase', ['to-string', ['coalesce', ['get', 'nivel_congestionamento'], 'livre']]],
                        'moderado', TRAFFIC_COLORS.moderado,
                        'intenso', TRAFFIC_COLORS.intenso,
                        'parado', TRAFFIC_COLORS.parado,
                        TRAFFIC_COLORS.livre]
                    : ['get', 'color']],
                'line-width': ['case', ['boolean', ['feature-state', 'hover'], false], 9, 6],
                'line-opacity': 0.9,
            },
        });

        let hoveredRoadId = null;
        this.map.on('mousemove', ROAD_LAYER_ID, (event) => {
            this.map.getCanvas().style.cursor = 'pointer';
            const id = event.features?.[0]?.id;
            if (hoveredRoadId !== null && hoveredRoadId !== id) {
                this.map.setFeatureState(this.featureStateTarget(hoveredRoadId), { hover: false });
            }
            if (id !== undefined) {
                hoveredRoadId = id;
                this.map.setFeatureState(this.featureStateTarget(id), { hover: true });
            }
        });
        this.map.on('mouseleave', ROAD_LAYER_ID, () => {
            this.map.getCanvas().style.cursor = '';
            if (hoveredRoadId !== null) {
                this.map.setFeatureState(this.featureStateTarget(hoveredRoadId), { hover: false });
                hoveredRoadId = null;
            }
        });
        this.map.on('click', ROAD_LAYER_ID, (event) => {
            const feature = event.features?.[0];
            if (!feature) return;
            new maplibregl.Popup({ maxWidth: '280px' })
                .setLngLat(event.lngLat)
                .setDOMContent(popupContent(this.popupProperties(feature.properties ?? {}, feature.state ?? {})))
                .addTo(this.map);
        });

        this.loaded = true;
        this.render(this.pendingPayload);
        this.applyHistory(this.pendingHistory);
        this.map.resize();
    }

    onUpdate(event) {
        const payload = event.detail?.[0] ?? event.detail ?? { vias: [], distritos: [] };
        this.districtId = payload.distrito_id || null;
        if (this.loaded && this.martinEnabled) this.map.setFilter(ROAD_LAYER_ID, this.roadFilter());
        this.render(payload);
        this.stopPlayback();
        this.loadTimeline(this.districtId);
    }

    async loadTimeline(districtId = null) {
        const requestId = ++this.timelineRequest;
        this.setTimelineStatus('Carregando...');
        if (this.rangeElement) this.rangeElement.disabled = true;
        if (this.playElement) this.playElement.disabled = true;

        try {
            const query = districtId ? `?distrito_id=${encodeURIComponent(districtId)}` : '';
            const response = await fetch(`/api/transito/timeline${query}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (requestId !== this.timelineRequest) return;
            this.timeline = Array.isArray(payload.timeline) ? payload.timeline : [];
            this.frameIndex = Math.max(this.timeline.length - 1, 0);
            this.renderTimeline();
            this.publishFrame();
        } catch (error) {
            if (requestId !== this.timelineRequest) return;
            console.error('Erro ao carregar histórico de trânsito.', error);
            this.timeline = [];
            this.frameIndex = 0;
            this.pendingHistory = [];
            this.setTimelineStatus('Sem histórico');
            if (this.loaded) this.applyHistory([]);
        }
    }

    onRangeInput(event) {
        this.stopPlayback();
        this.frameIndex = Number(event.target.value);
        this.renderTimeline();
        this.publishFrame();
    }

    publishFrame() {
        const frame = this.timeline[this.frameIndex] ?? null;
        this.pendingHistory = frame?.data ?? [];
        if (this.loaded) this.applyHistory(this.pendingHistory);
    }

    renderTimeline() {
        const frame = this.timeline[this.frameIndex] ?? null;
        const maximum = Math.max(this.timeline.length - 1, 0);
        if (this.rangeElement) {
            this.rangeElement.max = String(maximum);
            this.rangeElement.value = String(this.frameIndex);
            this.rangeElement.disabled = this.timeline.length < 2;
            const progress = maximum ? (this.frameIndex / maximum) * 100 : 0;
            const inactive = document.documentElement.classList.contains('dark') ? 'rgb(75 85 99)' : 'rgb(229 231 235)';
            this.rangeElement.style.background = `linear-gradient(to right,rgb(var(--primary-600)) 0%,rgb(var(--primary-600)) ${progress}%,${inactive} ${progress}%,${inactive} 100%)`;
            this.rangeElement.setAttribute('aria-valuetext', frame?.label ?? 'Sem histórico');
        }
        if (this.playElement) this.playElement.disabled = this.timeline.length < 2;
        this.setTimelineStatus(frame?.label ?? 'Sem histórico');
    }

    setTimelineStatus(value) {
        if (this.selectedElement) this.selectedElement.textContent = value;
    }

    togglePlayback() {
        if (this.playTimer) {
            this.stopPlayback();
            return;
        }
        if (this.timeline.length < 2) return;
        if (this.frameIndex >= this.timeline.length - 1) this.frameIndex = 0;
        if (this.playElement) this.playElement.textContent = 'Pausar';
        this.renderTimeline();
        this.publishFrame();
        this.playTimer = window.setInterval(() => {
            if (this.frameIndex >= this.timeline.length - 1) {
                this.stopPlayback();
                return;
            }
            this.frameIndex++;
            this.renderTimeline();
            this.publishFrame();
        }, 1200);
    }

    stopPlayback() {
        if (this.playTimer) window.clearInterval(this.playTimer);
        this.playTimer = null;
        if (this.playElement) this.playElement.textContent = 'Reproduzir';
    }

    applyHistory(readings) {
        const nextIds = new Set();
        readings.forEach((rawReading) => {
            const reading = normalizeHistoryReading(rawReading);
            const id = Number(reading.roadId);
            if (!Number.isFinite(id)) return;
            nextIds.add(id);
            this.map.setFeatureState(this.featureStateTarget(id), {
                historicalColor: TRAFFIC_COLORS[reading.congestion] ?? TRAFFIC_COLORS.livre,
                historicalCongestion: reading.congestion,
                historicalSpeed: reading.speed,
                vehicles: reading.vehicles,
                accidents: reading.accidents ?? 0,
                works: reading.works ?? 0,
                flooded: Boolean(reading.flooded),
            });
        });
        this.historicalRoadIds.forEach((id) => {
            if (!nextIds.has(id)) this.map.removeFeatureState(this.featureStateTarget(id));
        });
        this.historicalRoadIds = nextIds;
    }

    roadFilter() {
        if (!this.martinEnabled) return ['==', ['get', 'kind'], 'road'];
        return this.districtId
            ? ['==', ['to-number', ['get', 'distrito_id']], Number(this.districtId)]
            : null;
    }

    featureStateTarget(id) {
        return this.martinEnabled
            ? { source: MVT_SOURCE_ID, sourceLayer: 'vias', id }
            : { source: SOURCE_ID, id };
    }

    popupProperties(properties, state = {}) {
        const base = this.martinEnabled ? {
            name: properties.nome,
            congestion: properties.nivel_congestionamento,
            speed: properties.velocidade_media,
            maintenanceImpact: 'não informado',
        } : properties;
        return {
            ...base,
            congestion: state.historicalCongestion ?? base.congestion,
            speed: state.historicalSpeed ?? base.speed,
            vehicles: state.vehicles,
            accidents: state.accidents,
            works: state.works,
            flooded: state.flooded,
        };
    }

    render(payload) {
        this.pendingPayload = payload;
        if (!this.loaded) return;

        const data = buildFeatures(payload);
        const geojsonData = this.martinEnabled
            ? featureCollection(data.all.features.filter((feature) => feature.properties?.kind === 'district'))
            : data.all;
        this.map.getSource(SOURCE_ID).setData(geojsonData);
        if (!fitMapToFeatureCollection(this.map, data.roads, { padding: 50, maxZoom: 17 })) {
            this.map.easeTo({ center: [-57.6806, -16.0722], zoom: 13 });
        }
    }

    destroy() {
        window.removeEventListener('update-transito-map', this.onUpdate);
        this.rangeElement?.removeEventListener('input', this.onRangeInput);
        this.playElement?.removeEventListener('click', this.togglePlayback);
        this.stopPlayback();
        this.map.remove();
    }
}

function initialize() {
    const container = document.getElementById('transito-map-container');
    if (!container) return;
    activeController?.destroy();
    activeController = new TrafficMapController(container);
}

registerLivewireMap(initialize, () => {
    activeController?.destroy();
    activeController = null;
});
