import { featureCollection, point } from '@turf/helpers';
import {
    createMap,
    fitMapToFeatureCollection,
    maplibregl,
    martinTilejsonUrl,
    normalizeGeometry,
    parseJson,
    registerLivewireMap,
} from './maplibre/base-map';

const SOURCE_ID = 'ccdae-districts-widget';
const MVT_SOURCE_ID = 'ccdae-districts-widget-mvt';
let activeWidget = null;

function buildFeatures(districts) {
    return featureCollection(
        districts.flatMap((district) => {
            const properties = {
                districtId: Number(district.id),
                name: String(district.nome ?? 'Distrito'),
                city: String(district.cidade ?? ''),
            };
            const geometry = normalizeGeometry(district.geojson);
            if (geometry) return [{ type: 'Feature', properties, geometry }];

            const latitude = Number(district.latitude);
            const longitude = Number(district.longitude);
            return Number.isFinite(latitude) && Number.isFinite(longitude)
                ? [point([longitude, latitude], properties)]
                : [];
        }),
    );
}

function popupContent(properties) {
    const container = document.createElement('div');
    const title = document.createElement('strong');
    const city = document.createElement('div');
    title.textContent = properties.name ?? 'Distrito';
    city.textContent = properties.city ?? '';
    container.append(title, city);
    return container;
}

class DistrictsWidgetMap {
    constructor(container) {
        this.container = container;
        this.martinEnabled = container.dataset.martinEnabled === 'true';
        this.martinBaseUrl = (container.dataset.martinTilejsonUrl || '/tiles').replace(/\/$/, '');
        this.map = createMap(container, { zoom: 10 });
        this.resizeObserver = new ResizeObserver(() => this.map.resize());
        this.resizeObserver.observe(container);
        this.map.on('load', () => this.onLoad());
    }

    onLoad() {
        const data = buildFeatures(parseJson(this.container.dataset.districts));
        const sourceId = this.martinEnabled ? MVT_SOURCE_ID : SOURCE_ID;
        if (this.martinEnabled) {
            this.map.addSource(sourceId, {
                type: 'vector',
                url: martinTilejsonUrl(this.martinBaseUrl, 'distritos'),
                promoteId: { distritos: 'id' },
            });
        } else {
            this.map.addSource(sourceId, { type: 'geojson', data });
        }
        const vectorLayer = this.martinEnabled ? { 'source-layer': 'distritos' } : {};
        this.map.addLayer({
            id: `${SOURCE_ID}-fill`,
            type: 'fill',
            source: sourceId,
            ...vectorLayer,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: { 'fill-color': '#3b82f6', 'fill-opacity': 0.3 },
        });
        this.map.addLayer({
            id: `${SOURCE_ID}-line`,
            type: 'line',
            source: sourceId,
            ...vectorLayer,
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: { 'line-color': '#3b82f6', 'line-width': 2 },
        });
        this.map.addLayer({
            id: `${SOURCE_ID}-point`,
            type: 'circle',
            source: sourceId,
            ...vectorLayer,
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-radius': 7,
                'circle-color': '#3b82f6',
                'circle-stroke-color': '#ffffff',
                'circle-stroke-width': 2,
            },
        });

        [`${SOURCE_ID}-fill`, `${SOURCE_ID}-point`].forEach((layerId) => {
            this.map.on('mouseenter', layerId, () => (this.map.getCanvas().style.cursor = 'pointer'));
            this.map.on('mouseleave', layerId, () => (this.map.getCanvas().style.cursor = ''));
            this.map.on('click', layerId, (event) => {
                const feature = event.features?.[0];
                if (!feature) return;
                new maplibregl.Popup()
                    .setLngLat(event.lngLat)
                    .setDOMContent(popupContent(this.popupProperties(feature.properties ?? {})))
                    .addTo(this.map);
            });
        });

        fitMapToFeatureCollection(this.map, data, { padding: 24, maxZoom: 15 });
    }

    popupProperties(properties) {
        if (!this.martinEnabled) return properties;
        return { name: properties.nome, city: properties.cidade };
    }

    destroy() {
        this.resizeObserver.disconnect();
        this.map.remove();
    }
}

function initialize() {
    const container = document.getElementById('distritos-widget-map');
    if (!container) return;
    if (activeWidget) activeWidget.destroy();
    activeWidget = new DistrictsWidgetMap(container);
}

registerLivewireMap(initialize, () => {
    activeWidget?.destroy();
    activeWidget = null;
});
