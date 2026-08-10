import { bbox } from '@turf/bbox';
import { featureCollection, point } from '@turf/helpers';
import { voronoi } from '@turf/voronoi';
import {
    createMap,
    fitMapToFeatureCollection,
    maplibregl,
    normalizeGeometry,
    parseJson,
    registerLivewireMap,
} from './maplibre/base-map';

const SOURCE_ID = 'ccdae-districts';
const FILL_LAYER_ID = 'ccdae-districts-fill';
const LINE_LAYER_ID = 'ccdae-districts-line';
const POINT_LAYER_ID = 'ccdae-districts-point';
const DEFAULT_COLORS = ['#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444'];

let activeController = null;

function districtProperties(district, color) {
    return {
        districtId: Number(district.id),
        name: String(district.nome ?? 'Distrito'),
        color,
    };
}

function districtFeature(geometry, district, color) {
    return {
        type: 'Feature',
        properties: districtProperties(district, color),
        geometry,
    };
}

function buildDistrictFeatures(districts) {
    const features = [];
    const districtsWithoutGeometry = [];

    districts.forEach((district, index) => {
        const color = DEFAULT_COLORS[index % DEFAULT_COLORS.length];
        const geometry = normalizeGeometry(district.geojson);

        if (geometry) {
            features.push(districtFeature(geometry, district, color));
            return;
        }

        const latitude = Number(district.latitude);
        const longitude = Number(district.longitude);

        if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
            districtsWithoutGeometry.push({ district, latitude, longitude, color });
        }
    });

    if (districtsWithoutGeometry.length > 1) {
        const points = featureCollection(
            districtsWithoutGeometry.map(({ district, latitude, longitude, color }) =>
                point([longitude, latitude], districtProperties(district, color)),
            ),
        );
        const pointsBbox = bbox(points);
        const longitudePadding = (pointsBbox[2] - pointsBbox[0]) * 0.1 || 0.05;
        const latitudePadding = (pointsBbox[3] - pointsBbox[1]) * 0.1 || 0.05;

        try {
            const polygons = voronoi(points, {
                bbox: [
                    pointsBbox[0] - longitudePadding,
                    pointsBbox[1] - latitudePadding,
                    pointsBbox[2] + longitudePadding,
                    pointsBbox[3] + latitudePadding,
                ],
            });

            polygons.features.forEach((polygon, index) => {
                if (!polygon) return;

                const item = districtsWithoutGeometry[index];
                polygon.properties = districtProperties(item.district, item.color);
                features.push(polygon);
            });
        } catch (error) {
            console.error('Não foi possível gerar as áreas de Voronoi.', error);
        }
    }

    const representedIds = new Set(features.map((feature) => Number(feature.properties?.districtId)));
    districtsWithoutGeometry.forEach(({ district, latitude, longitude, color }) => {
        if (!representedIds.has(Number(district.id))) {
            features.push(point([longitude, latitude], districtProperties(district, color)));
        }
    });

    return featureCollection(features);
}

function createPopupContent(properties) {
    const container = document.createElement('div');
    container.className = 'ccdae-map-popup';

    const title = document.createElement('strong');
    title.textContent = properties.name ?? 'Distrito';
    container.appendChild(title);

    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Editar Distrito';
    button.addEventListener('click', () => {
        window.dispatchEvent(
            new CustomEvent('edit-distrito-event', {
                detail: Number(properties.districtId),
            }),
        );
    });
    container.appendChild(button);

    return container;
}

class DistrictsMapController {
    constructor(container) {
        this.container = container;
        this.loaded = false;
        this.pendingDistricts = parseJson(container.dataset.districts);
        this.onMapUpdate = this.onMapUpdate.bind(this);

        this.map = createMap(container, { zoom: 4 });
        this.map.on('load', () => this.onLoad());
        window.addEventListener('update-map', this.onMapUpdate);
    }

    onLoad() {
        this.map.addSource(SOURCE_ID, {
            type: 'geojson',
            data: featureCollection([]),
            promoteId: 'districtId',
        });

        this.map.addLayer({
            id: FILL_LAYER_ID,
            type: 'fill',
            source: SOURCE_ID,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'fill-color': ['get', 'color'],
                'fill-opacity': ['case', ['boolean', ['feature-state', 'hover'], false], 0.65, 0.45],
            },
        });

        this.map.addLayer({
            id: LINE_LAYER_ID,
            type: 'line',
            source: SOURCE_ID,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: {
                'line-color': ['get', 'color'],
                'line-width': ['case', ['boolean', ['feature-state', 'hover'], false], 4, 2],
                'line-opacity': 0.9,
            },
        });

        this.map.addLayer({
            id: POINT_LAYER_ID,
            type: 'circle',
            source: SOURCE_ID,
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-radius': 10,
                'circle-color': ['get', 'color'],
                'circle-stroke-color': '#ffffff',
                'circle-stroke-width': 2,
                'circle-opacity': 0.85,
            },
        });

        this.registerInteractions();
        this.loaded = true;
        this.setDistricts(this.pendingDistricts);
        this.map.resize();
    }

    registerInteractions() {
        let hoveredFeatureId = null;

        this.map.on('mousemove', FILL_LAYER_ID, (event) => {
            this.map.getCanvas().style.cursor = 'pointer';
            const featureId = event.features?.[0]?.id;

            if (hoveredFeatureId !== null && hoveredFeatureId !== featureId) {
                this.map.setFeatureState({ source: SOURCE_ID, id: hoveredFeatureId }, { hover: false });
            }
            if (featureId !== undefined) {
                hoveredFeatureId = featureId;
                this.map.setFeatureState({ source: SOURCE_ID, id: featureId }, { hover: true });
            }
        });

        this.map.on('mouseleave', FILL_LAYER_ID, () => {
            this.map.getCanvas().style.cursor = '';
            if (hoveredFeatureId !== null) {
                this.map.setFeatureState({ source: SOURCE_ID, id: hoveredFeatureId }, { hover: false });
                hoveredFeatureId = null;
            }
        });

        [FILL_LAYER_ID, POINT_LAYER_ID].forEach((layerId) => {
            this.map.on('click', layerId, (event) => {
                const feature = event.features?.[0];
                if (!feature) return;

                new maplibregl.Popup({ closeButton: true, maxWidth: '240px' })
                    .setLngLat(event.lngLat)
                    .setDOMContent(createPopupContent(feature.properties ?? {}))
                    .addTo(this.map);
            });
        });
    }

    onMapUpdate(event) {
        const districts = event.detail?.distritos ?? event.detail?.[0]?.distritos ?? [];
        this.setDistricts(districts);
    }

    setDistricts(districts) {
        this.pendingDistricts = Array.isArray(districts) ? districts : [];
        if (!this.loaded) return;

        const data = buildDistrictFeatures(this.pendingDistricts);
        this.map.getSource(SOURCE_ID).setData(data);

        if (data.features.length === 0) {
            this.map.easeTo({
                center: parseJson(this.container.dataset.center, [-47.9292, -15.7801]),
                zoom: Number(this.container.dataset.zoom) || 4,
            });
            return;
        }

        fitMapToFeatureCollection(this.map, data, { padding: 50 });
    }

    destroy() {
        window.removeEventListener('update-map', this.onMapUpdate);
        this.map.remove();
    }
}

function initializeDistrictsMap() {
    const container = document.getElementById('distritos-map-container');
    if (!container || container.dataset.mapEngine !== 'maplibre') return;

    if (activeController) activeController.destroy();
    activeController = new DistrictsMapController(container);
}

registerLivewireMap(initializeDistrictsMap, () => {
    if (!activeController) return;
    activeController.destroy();
    activeController = null;
});
