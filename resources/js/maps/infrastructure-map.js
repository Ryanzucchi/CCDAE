import { featureCollection, point } from '@turf/helpers';
import {
    createMap,
    fitMapToFeatureCollection,
    maplibregl,
    martinTilejsonUrl,
    normalizeGeometry,
    registerLivewireMap,
} from './maplibre/base-map';

const SOURCE_ID = 'ccdae-infrastructure';
const BUILDINGS_SOURCE_ID = 'buildings';
const BUILDINGS_LAYER_ID = '3d-buildings';
const BUILDINGS_MIN_ZOOM = 14;
const DETAIL_MIN_ZOOM = 15;
const DEFAULT_CENTER = [-57.6875, -16.07083];
let activeController = null;

const TRAFFIC_COLORS = {
    livre: '#22c55e',
    moderado: '#eab308',
    intenso: '#f97316',
    parado: '#ef4444',
};

const CABLE_COLORS = {
    fibra_optica: '#06b6d4',
    fibra_backbone: '#facc15',
    eletrico_alta_tensao: '#ef4444',
};

function properties(kind, item, extra = {}) {
    return {
        kind,
        recordId: Number(item.id),
        name: String(item.nome ?? ''),
        state: String(item.estado_conservacao ?? 'não informado'),
        ...extra,
    };
}

function geometryFeature(kind, item, extra = {}) {
    const geometry = normalizeGeometry(item.geojson);
    if (!geometry) return null;

    return {
        type: 'Feature',
        id: `${kind}-${item.id}`,
        properties: properties(kind, item, extra),
        geometry,
    };
}

function pointFeature(kind, item, extra = {}) {
    const latitude = Number(item.latitude);
    const longitude = Number(item.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;

    const feature = point([longitude, latitude], properties(kind, item, extra));
    feature.id = `${kind}-${item.id}`;
    return feature;
}

function cableGroupKey(cable) {
    const endpoints = [
        cable.poste_origem_id ? `p:${cable.poste_origem_id}` : null,
        cable.poste_destino_id ? `p:${cable.poste_destino_id}` : null,
        cable.central_origem_id ? `c:${cable.central_origem_id}` : null,
        cable.central_destino_id ? `c:${cable.central_destino_id}` : null,
    ].filter(Boolean);

    if (endpoints.length >= 2) return endpoints.sort().join('|');

    const geometry = normalizeGeometry(cable.geojson);
    const coordinates = geometry?.coordinates;
    if (geometry?.type === 'LineString' && coordinates?.length >= 2) {
        const first = coordinates[0].map((value) => Number(value).toFixed(5)).join(',');
        const last = coordinates.at(-1).map((value) => Number(value).toFixed(5)).join(',');
        return [first, last].sort().join('|');
    }

    return `cable:${cable.id}`;
}

function cableOffsets(cables) {
    const groups = new Map();
    cables.forEach((cable) => {
        const key = cableGroupKey(cable);
        groups.set(key, [...(groups.get(key) ?? []), cable]);
    });

    const offsets = new Map();
    groups.forEach((group) => {
        group.forEach((cable, index) => offsets.set(cable.id, (index - (group.length - 1) / 2) * 4));
    });
    return offsets;
}

function buildInfrastructureFeatures(payload, excludeMartinDetails = false) {
    const features = [];
    const fitFeatures = [];

    (payload.vias ?? []).forEach((road) => {
        const congestion = String(road.nivel_congestionamento ?? 'livre').toLowerCase();
        const feature = geometryFeature('road', road, {
            congestion,
            speed: road.velocidade_media ?? null,
            color: TRAFFIC_COLORS[congestion] ?? TRAFFIC_COLORS.livre,
        });
        if (feature) {
            features.push(feature);
            fitFeatures.push(feature);
        }
    });

    (payload.centrais ?? []).forEach((central) => {
        const extra = { typeLabel: String(central.tipo ?? 'não informado'), color: '#f59e0b' };
        const feature = geometryFeature('central', central, extra) ?? pointFeature('central', central, extra);
        if (feature) {
            features.push(feature);
            fitFeatures.push(feature);
        }
    });

    const offsets = cableOffsets(excludeMartinDetails ? [] : (payload.cabeamentos ?? []));
    (excludeMartinDetails ? [] : (payload.cabeamentos ?? [])).forEach((cable) => {
        const cableType = String(cable.tipo_cabo ?? 'outro');
        const feature = geometryFeature('cable', cable, {
            typeLabel: cableType,
            color: CABLE_COLORS[cableType] ?? '#3b82f6',
            lineOffset: offsets.get(cable.id) ?? 0,
        });
        if (feature) features.push(feature);
    });

    const pointLayers = [
        ['equipment', payload.equipamentos ?? [], '#10b981', (item) => ({ typeLabel: String(item.tipo ?? 'não informado') })],
        ['pole', payload.postes ?? [], '#9ca3af', (item) => ({ typeLabel: String(item.material ?? 'não informado') })],
        ['antenna', payload.antenas ?? [], '#ec4899', (item) => ({ typeLabel: String(item.tipo_sinal ?? 'não informado') })],
    ];
    pointLayers.forEach(([kind, items, color, extra]) => {
        if (excludeMartinDetails && ['equipment', 'pole', 'antenna'].includes(kind)) return;
        items.forEach((item) => {
            const feature = pointFeature(kind, item, { color, ...extra(item) });
            if (feature) features.push(feature);
        });
    });

    return {
        all: featureCollection(features),
        fit: featureCollection(fitFeatures),
    };
}

function editUrl(kind, id) {
    const paths = {
        central: 'central-distribuicaos',
        cable: 'cabeamentos',
        equipment: 'equipamento-infraestruturas',
        pole: 'postes',
        antenna: 'antenas',
    };
    return paths[kind] ? `/admin/${paths[kind]}/${Number(id)}/edit` : null;
}

function popupContent(properties) {
    const titles = {
        road: 'Via', central: 'Central', cable: 'Cabo', equipment: 'Equipamento', pole: 'Poste', antenna: 'Antena', building: 'Construção',
    };
    const container = document.createElement('div');
    container.className = 'ccdae-infrastructure-popup';

    const title = document.createElement('strong');
    const fallbackName = properties.kind === 'pole' || properties.kind === 'antenna' ? 'S/N' : titles[properties.kind];
    title.textContent = `${titles[properties.kind] ?? 'Registro'}: ${properties.name || fallbackName}`;
    container.appendChild(title);

    const rows = [];
    if (properties.kind === 'building') {
        const sourceLabels = { osm_height: 'altura real informada no OSM', levels: 'calculada pela quantidade de níveis', default: 'altura visual padrão' };
        rows.push(['Tipo', properties.building || 'Não informado']);
        rows.push(['OSM', `${properties.osm_type || 'objeto'} ${properties.osm_id || ''}`.trim()]);
        rows.push(['Altura cadastrada', properties.height ? `${properties.height} m` : 'Não informada']);
        rows.push(['Altura de renderização', `${properties.render_height ?? 0} m`]);
        rows.push(['Níveis', properties.building_levels ?? 'Não informado']);
        rows.push(['Origem da altura', sourceLabels[properties.height_source] ?? properties.height_source ?? 'Não informada']);
    } else if (properties.kind === 'road') {
        rows.push(['Congestionamento', String(properties.congestion ?? 'livre').toUpperCase()]);
        rows.push(['Velocidade média', properties.speed ? `${properties.speed} km/h` : 'Não informada']);
    } else {
        rows.push(['Tipo', properties.typeLabel ?? 'Não informado']);
        rows.push(['Estado', properties.state ?? 'Não informado']);
    }

    rows.forEach(([label, value]) => {
        const row = document.createElement('div');
        const labelElement = document.createElement('b');
        labelElement.textContent = `${label}: `;
        row.append(labelElement, document.createTextNode(value));
        container.appendChild(row);
    });

    const url = editUrl(properties.kind, properties.recordId);
    if (url) {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'Editar registro';
        container.appendChild(link);
    }

    return container;
}

class InfrastructureMapController {
    constructor(container) {
        this.container = container;
        this.loaded = false;
        this.pendingPayload = null;
        this.martinEnabled = container.dataset.martinEnabled === 'true';
        this.martinBaseUrl = (container.dataset.martinTilejsonUrl || '/tiles').replace(/\/$/, '');
        this.districtId = null;
        this.buildingsEnabled = true;
        this.buildingsCacheBounds = null;
        this.buildingsAbortController = null;
        this.buildingsRequestTimer = null;
        this.onData = this.onData.bind(this);
        this.onResize = this.onResize.bind(this);
        this.map = createMap(container, { center: DEFAULT_CENTER, zoom: 12 });
        this.map.setMaxZoom(22);
        this.map.on('load', () => this.onLoad());
        this.map.on('zoom', () => this.updateZoomHint());
        this.onBuildingsViewportChanged = this.onBuildingsViewportChanged.bind(this);
        this.onBuildingsToggle = this.onBuildingsToggle.bind(this);
        this.map.on('moveend', this.onBuildingsViewportChanged);
        this.map.on('zoomend', this.onBuildingsViewportChanged);
        document.addEventListener('change', this.onBuildingsToggle);
        window.addEventListener('infra-map-data', this.onData);
        window.addEventListener('infra-map-resize', this.onResize);
    }

    onLoad() {
        this.map.addSource(SOURCE_ID, { type: 'geojson', data: featureCollection([]) });
        this.map.addSource(BUILDINGS_SOURCE_ID, { type: 'geojson', data: featureCollection([]) });
        const firstSymbolLayer = this.map.getStyle().layers?.find((layer) => layer.type === 'symbol')?.id;
        this.map.addLayer({
            id: BUILDINGS_LAYER_ID,
            type: 'fill-extrusion',
            source: BUILDINGS_SOURCE_ID,
            minzoom: BUILDINGS_MIN_ZOOM,
            paint: {
                'fill-extrusion-color': [
                    'interpolate', ['linear'], ['coalesce', ['get', 'render_height'], 6],
                    0, '#d6d3d1', 12, '#a8a29e', 30, '#78716c', 80, '#57534e',
                ],
                'fill-extrusion-height': ['coalesce', ['to-number', ['get', 'render_height']], 6],
                'fill-extrusion-base': ['coalesce', ['to-number', ['get', 'render_min_height']], 0],
                'fill-extrusion-opacity': 0.78,
                'fill-extrusion-vertical-gradient': true,
            },
        }, firstSymbolLayer);

        this.addLineLayer('infrastructure-roads', 'road', 6);
        this.addFillLayer('infrastructure-central-fill', 'central', '#f59e0b', 0.4);
        this.addLineLayer('infrastructure-central-line', 'central', 2);
        this.addCircleLayer('infrastructure-central-point', 'central', 10, 0);
        this.addLineLayer('infrastructure-cables', 'cable', 4, DETAIL_MIN_ZOOM, ['get', 'lineOffset']);
        this.addCircleLayer('infrastructure-equipment', 'equipment', 5, DETAIL_MIN_ZOOM);
        this.addCircleLayer('infrastructure-poles', 'pole', 5, DETAIL_MIN_ZOOM, '#000000');
        this.addCircleLayer('infrastructure-antennas', 'antenna', 6, DETAIL_MIN_ZOOM);

        const martinInteractions = this.martinEnabled ? this.addMartinLayers() : {};

        this.registerInteractions({
            'infrastructure-roads': null,
            'infrastructure-central-fill': null,
            'infrastructure-central-point': null,
            'infrastructure-cables': null,
            'infrastructure-equipment': null,
            'infrastructure-poles': null,
            'infrastructure-antennas': null,
            [BUILDINGS_LAYER_ID]: 'building',
            ...martinInteractions,
        });

        this.loaded = true;
        this.updateZoomHint();
        this.loadBuildingsForViewport();
        if (this.pendingPayload) {
            this.render(this.pendingPayload.data, this.pendingPayload.fit, this.pendingPayload.bounds);
        }
    }

    addLineLayer(id, kind, width, minzoom = 0, offset = 0) {
        this.map.addLayer({
            id,
            type: 'line',
            source: SOURCE_ID,
            minzoom,
            filter: ['all', ['==', ['get', 'kind'], kind], ['in', ['geometry-type'], ['literal', ['LineString', 'Polygon']]]],
            paint: {
                'line-color': ['coalesce', ['get', 'color'], '#f59e0b'],
                'line-width': width,
                'line-opacity': 0.88,
                'line-offset': offset,
            },
        });
    }

    addFillLayer(id, kind, color, opacity) {
        this.map.addLayer({
            id,
            type: 'fill',
            source: SOURCE_ID,
            filter: ['all', ['==', ['get', 'kind'], kind], ['==', ['geometry-type'], 'Polygon']],
            paint: { 'fill-color': color, 'fill-opacity': opacity },
        });
    }

    addCircleLayer(id, kind, radius, minzoom = 0, stroke = '#ffffff') {
        this.map.addLayer({
            id,
            type: 'circle',
            source: SOURCE_ID,
            minzoom,
            filter: ['all', ['==', ['get', 'kind'], kind], ['==', ['geometry-type'], 'Point']],
            paint: {
                'circle-radius': radius,
                'circle-color': ['get', 'color'],
                'circle-stroke-color': stroke,
                'circle-stroke-width': kind === 'antenna' || kind === 'central' ? 2 : 1,
                'circle-opacity': 0.95,
            },
        });
    }

    addMartinLayers() {
        const sources = {
            vias: martinTilejsonUrl(this.martinBaseUrl, 'vias'),
            centrais: martinTilejsonUrl(this.martinBaseUrl, 'centrais'),
            cabeamentos: martinTilejsonUrl(this.martinBaseUrl, 'cabeamentos'),
            postes: martinTilejsonUrl(this.martinBaseUrl, 'postes'),
            antenas: martinTilejsonUrl(this.martinBaseUrl, 'antenas'),
            equipamentos: martinTilejsonUrl(this.martinBaseUrl, 'equipamentos'),
        };
        Object.entries(sources).forEach(([id, url]) => {
            this.map.addSource(`martin-${id}`, { type: 'vector', url, promoteId: { [id]: 'id' } });
        });

        this.map.addLayer({
            id: 'martin-vias', type: 'line', source: 'martin-vias', 'source-layer': 'vias',
            ...this.martinLayerFilter(),
            paint: {
                'line-color': [
                    'match', ['downcase', ['to-string', ['coalesce', ['get', 'nivel_congestionamento'], 'livre']]],
                    'moderado', TRAFFIC_COLORS.moderado,
                    'intenso', TRAFFIC_COLORS.intenso,
                    'parado', TRAFFIC_COLORS.parado,
                    TRAFFIC_COLORS.livre,
                ],
                'line-width': 6,
                'line-opacity': 0.88,
            },
        });

        const centralGeometryFilter = (type) => this.martinFilter(['==', ['geometry-type'], type]);
        this.map.addLayer({
            id: 'martin-centrais-fill', type: 'fill', source: 'martin-centrais', 'source-layer': 'centrais',
            filter: centralGeometryFilter('Polygon'),
            paint: { 'fill-color': '#f59e0b', 'fill-opacity': 0.4 },
        });
        this.map.addLayer({
            id: 'martin-centrais-line', type: 'line', source: 'martin-centrais', 'source-layer': 'centrais',
            filter: centralGeometryFilter('Polygon'),
            paint: { 'line-color': '#f59e0b', 'line-width': 2, 'line-opacity': 0.9 },
        });
        this.map.addLayer({
            id: 'martin-centrais-point', type: 'circle', source: 'martin-centrais', 'source-layer': 'centrais',
            filter: centralGeometryFilter('Point'),
            paint: {
                'circle-radius': 10,
                'circle-color': '#f59e0b',
                'circle-stroke-color': '#ffffff',
                'circle-stroke-width': 2,
            },
        });

        this.map.addLayer({
            id: 'martin-cabeamentos', type: 'line', source: 'martin-cabeamentos', 'source-layer': 'cabeamentos', minzoom: DETAIL_MIN_ZOOM,
            ...this.martinLayerFilter(),
            paint: {
                'line-color': [
                    'match', ['get', 'tipo_cabo'],
                    'fibra_optica', '#06b6d4',
                    'fibra_backbone', '#facc15',
                    'eletrico_alta_tensao', '#ef4444',
                    '#3b82f6',
                ],
                'line-width': 4,
                'line-opacity': 0.85,
            },
        });

        const pointLayers = [
            ['martin-equipamentos', 'equipamentos', 5, '#10b981', '#ffffff'],
            ['martin-postes', 'postes', 5, '#9ca3af', '#000000'],
            ['martin-antenas', 'antenas', 6, '#ec4899', '#ffffff'],
        ];
        pointLayers.forEach(([id, source, radius, color, stroke]) => {
            this.map.addLayer({
                id,
                type: 'circle',
                source: `martin-${source}`,
                'source-layer': source,
                minzoom: DETAIL_MIN_ZOOM,
                ...this.martinLayerFilter(),
                paint: {
                    'circle-radius': radius,
                    'circle-color': color,
                    'circle-stroke-color': stroke,
                    'circle-stroke-width': source === 'antenas' ? 2 : 1,
                    'circle-opacity': 0.95,
                },
            });
        });

        return {
            'martin-vias': 'road',
            'martin-centrais-fill': 'central',
            'martin-centrais-point': 'central',
            'martin-cabeamentos': 'cable',
            'martin-equipamentos': 'equipment',
            'martin-postes': 'pole',
            'martin-antenas': 'antenna',
        };
    }

    martinFilter(baseFilter = null) {
        const districtFilter = this.districtId
            ? ['==', ['to-number', ['get', 'distrito_id']], Number(this.districtId)]
            : null;
        if (baseFilter && districtFilter) return ['all', baseFilter, districtFilter];
        return baseFilter ?? districtFilter;
    }

    martinLayerFilter(baseFilter = null) {
        const filter = this.martinFilter(baseFilter);
        return filter ? { filter } : {};
    }

    updateMartinFilters() {
        if (!this.martinEnabled || !this.loaded) return;
        this.map.setFilter('martin-vias', this.martinFilter());
        this.map.setFilter('martin-centrais-fill', this.martinFilter(['==', ['geometry-type'], 'Polygon']));
        this.map.setFilter('martin-centrais-line', this.martinFilter(['==', ['geometry-type'], 'Polygon']));
        this.map.setFilter('martin-centrais-point', this.martinFilter(['==', ['geometry-type'], 'Point']));
        ['cabeamentos', 'equipamentos', 'postes', 'antenas'].forEach((source) => {
            this.map.setFilter(`martin-${source}`, this.martinFilter());
        });
    }

    registerInteractions(layers) {
        Object.entries(layers).forEach(([layerId, forcedKind]) => {
            this.map.on('mouseenter', layerId, () => (this.map.getCanvas().style.cursor = 'pointer'));
            this.map.on('mouseleave', layerId, () => (this.map.getCanvas().style.cursor = ''));
            this.map.on('click', layerId, (event) => {
                const feature = event.features?.[0];
                if (!feature) return;
                const popupProperties = { ...(feature.properties ?? {}) };
                if (forcedKind) {
                    popupProperties.kind = forcedKind;
                    popupProperties.recordId = popupProperties.id;
                    popupProperties.state = popupProperties.estado_conservacao;
                    if (forcedKind === 'road') {
                        popupProperties.congestion = popupProperties.nivel_congestionamento;
                        popupProperties.speed = popupProperties.velocidade_media;
                    }
                    popupProperties.typeLabel = popupProperties.tipo_cabo
                        ?? popupProperties.tipo
                        ?? popupProperties.material
                        ?? popupProperties.tipo_sinal
                        ?? 'não informado';
                    if (!popupProperties.name) {
                        popupProperties.name = popupProperties.nome
                            ?? popupProperties.codigo_patrimonio
                            ?? '';
                    }
                }
                new maplibregl.Popup({ maxWidth: '290px' })
                    .setLngLat(event.lngLat)
                    .setDOMContent(popupContent(popupProperties))
                    .addTo(this.map);
            });
        });
    }

    onData(event) {
        const detail = event.detail ?? {};
        this.districtId = detail.distritoId || null;
        this.updateMartinFilters();
        this.render(detail.data ?? {}, detail.fit !== false, detail.data?.meta?.bounds ?? null);
    }

    onResize() {
        setTimeout(() => this.map.resize(), 50);
    }

    onBuildingsToggle(event) {
        if (event.target?.id !== 'infra-buildings-toggle') return;
        this.buildingsEnabled = Boolean(event.target.checked);
        if (this.map.getLayer(BUILDINGS_LAYER_ID)) {
            this.map.setLayoutProperty(BUILDINGS_LAYER_ID, 'visibility', this.buildingsEnabled ? 'visible' : 'none');
        }
        if (this.buildingsEnabled) this.loadBuildingsForViewport(true);
    }

    onBuildingsViewportChanged() {
        clearTimeout(this.buildingsRequestTimer);
        this.buildingsRequestTimer = setTimeout(() => this.loadBuildingsForViewport(), 250);
    }

    async loadBuildingsForViewport(force = false) {
        if (!this.loaded || !this.buildingsEnabled || this.map.getZoom() < BUILDINGS_MIN_ZOOM) {
            if (this.loaded && this.map.getSource(BUILDINGS_SOURCE_ID)) this.map.getSource(BUILDINGS_SOURCE_ID).setData(featureCollection([]));
            return;
        }
        const bounds = this.map.getBounds();
        const requested = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
        if (!force && this.buildingsCacheBounds
            && requested[0] >= this.buildingsCacheBounds[0] && requested[1] >= this.buildingsCacheBounds[1]
            && requested[2] <= this.buildingsCacheBounds[2] && requested[3] <= this.buildingsCacheBounds[3]) return;

        const lngPadding = (requested[2] - requested[0]) * 0.2;
        const latPadding = (requested[3] - requested[1]) * 0.2;
        const queryBounds = [requested[0] - lngPadding, requested[1] - latPadding, requested[2] + lngPadding, requested[3] + latPadding];
        this.buildingsAbortController?.abort();
        this.buildingsAbortController = new AbortController();
        try {
            const response = await fetch(`/api/infrastructure-map/buildings?bbox=${queryBounds.join(',')}`, {
                headers: { Accept: 'application/json' }, signal: this.buildingsAbortController.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            this.map.getSource(BUILDINGS_SOURCE_ID)?.setData(data);
            this.buildingsCacheBounds = queryBounds;
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Erro ao carregar construções locais.', error);
        }
    }

    render(payload, fit = true, bounds = null) {
        this.pendingPayload = { data: payload, fit, bounds };
        if (!this.loaded) return;

        const data = buildInfrastructureFeatures(payload, this.martinEnabled);
        this.map.getSource(SOURCE_ID).setData(this.martinEnabled ? featureCollection([]) : data.all);
        if (fit && bounds?.length === 2) {
            this.map.fitBounds(bounds, { padding: 50, maxZoom: 16, duration: 500 });
        } else if (fit && !fitMapToFeatureCollection(this.map, data.fit, { padding: 50, maxZoom: 16 })) {
            this.map.easeTo({ center: DEFAULT_CENTER, zoom: 12 });
        }
    }

    updateZoomHint() {
        const hint = document.getElementById('map-zoom-hint');
        if (hint) hint.hidden = this.map.getZoom() >= DETAIL_MIN_ZOOM;
    }

    destroy() {
        window.removeEventListener('infra-map-data', this.onData);
        window.removeEventListener('infra-map-resize', this.onResize);
        document.removeEventListener('change', this.onBuildingsToggle);
        clearTimeout(this.buildingsRequestTimer);
        this.buildingsAbortController?.abort();
        this.map.remove();
    }
}

function initialize() {
    const container = document.getElementById('infra-map-container');
    if (!container) return;
    activeController?.destroy();
    activeController = new InfrastructureMapController(container);
}

registerLivewireMap(initialize, () => {
    activeController?.destroy();
    activeController = null;
});
