import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import '../../../css/maps.css';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { bbox } from '@turf/bbox';

maplibregl.setWorkerUrl(maplibreWorkerUrl);

export { maplibregl };

const MARTIN_TILEJSON_VERSION = '20260827-1';
const TERRAIN_SOURCE_ID = 'ccdae-terrain-dem';
const HILLSHADE_LAYER_ID = 'ccdae-terrain-hillshade';

export function martinTilejsonUrl(baseUrl, source) {
    return `${String(baseUrl || '/tiles').replace(/\/$/, '')}/${source}?v=${MARTIN_TILEJSON_VERSION}`;
}

export function parseJson(value, fallback = []) {
    if (!value) return fallback;

    try {
        return JSON.parse(value);
    } catch (error) {
        console.error('Não foi possível interpretar os dados cartográficos.', error);
        return fallback;
    }
}

export function normalizeGeometry(rawGeometry) {
    let geometry = rawGeometry;

    try {
        while (typeof geometry === 'string') geometry = JSON.parse(geometry);
    } catch {
        return null;
    }

    if (!geometry || typeof geometry !== 'object') return null;
    if (geometry.type === 'Feature') return geometry.geometry ?? null;
    if (geometry.type === 'FeatureCollection') return geometry.features?.[0]?.geometry ?? null;

    return geometry.type && geometry.coordinates ? geometry : null;
}

export function isDarkTheme() {
    return document.documentElement.classList.contains('dark');
}

function rasterThemePaint(dark, hasNativeDarkTiles = false) {
    return dark && !hasNativeDarkTiles
        ? {
            'raster-brightness-min': 0,
            'raster-brightness-max': 0.36,
            'raster-contrast': 0.18,
            'raster-saturation': -0.72,
        }
        : {
            'raster-brightness-min': 0,
            'raster-brightness-max': 1,
            'raster-contrast': 0,
            'raster-saturation': 0,
        };
}

export function rasterFallbackStyle(tileUrl, attribution, dark = isDarkTheme(), darkTileUrl = '') {
    const sourceId = 'osm-raster';
    const activeTileUrl = dark && darkTileUrl ? darkTileUrl : tileUrl;

    return {
        version: 8,
        sources: {
            [sourceId]: {
                type: 'raster',
                tiles: [activeTileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                tileSize: 256,
                attribution: attribution || '&copy; OpenStreetMap contributors',
            },
        },
        layers: [{ id: sourceId, type: 'raster', source: sourceId, paint: rasterThemePaint(dark, Boolean(darkTileUrl)) }],
    };
}

export function applyMapTheme(map, container, dark = isDarkTheme()) {
    container.classList.toggle('ccdae-map-dark', dark);
    container.classList.toggle('ccdae-map-light', !dark);
    container.style.backgroundColor = dark ? '#0f172a' : '#e2e8f0';
    if (!map.isStyleLoaded()) return;

    const lightTileUrl = container.dataset.rasterTileUrl;
    const darkTileUrl = container.dataset.rasterDarkTileUrl;
    const rasterSource = map.getSource('osm-raster');
    if (rasterSource?.setTiles) {
        const nextTileUrl = dark && darkTileUrl ? darkTileUrl : lightTileUrl;
        if (nextTileUrl && container.dataset.activeRasterTileUrl !== nextTileUrl) {
            rasterSource.setTiles([nextTileUrl]);
            container.dataset.activeRasterTileUrl = nextTileUrl;
        }
    }

    const layers = map.getStyle()?.layers ?? [];
    layers.forEach((layer) => {
        if (layer.type === 'raster') {
            Object.entries(rasterThemePaint(dark, Boolean(darkTileUrl))).forEach(([property, value]) => {
                map.setPaintProperty(layer.id, property, value);
            });
        } else if (layer.type === 'background') {
            map.setPaintProperty(layer.id, 'background-color', dark ? '#0f172a' : '#f8fafc');
        } else if (layer.type === 'hillshade') {
            map.setPaintProperty(layer.id, 'hillshade-shadow-color', dark ? '#020617' : '#111827');
            map.setPaintProperty(layer.id, 'hillshade-highlight-color', dark ? '#475569' : '#f8fafc');
            map.setPaintProperty(layer.id, 'hillshade-accent-color', dark ? '#1e293b' : '#64748b');
        }
    });
}

function addTerrain(map, container) {
    const tileUrl = container.dataset.terrainTileUrl;
    if (container.dataset.terrainEnabled !== 'true' || !tileUrl || map.getSource(TERRAIN_SOURCE_ID)) return;

    const maxzoom = Math.max(0, Math.min(15, Number(container.dataset.terrainZoom) || 12));
    const exaggeration = Math.max(0, Number(container.dataset.terrainExaggeration) || 1.35);
    map.addSource(TERRAIN_SOURCE_ID, {
        type: 'raster-dem',
        tiles: [tileUrl],
        tileSize: 256,
        maxzoom,
        encoding: 'terrarium',
        attribution: container.dataset.terrainAttribution || 'Mapzen Terrain Tiles / AWS Open Data',
    });
    map.setTerrain({ source: TERRAIN_SOURCE_ID, exaggeration });
    map.addLayer({
        id: HILLSHADE_LAYER_ID,
        type: 'hillshade',
        source: TERRAIN_SOURCE_ID,
        paint: {
            'hillshade-exaggeration': 0.55,
            'hillshade-illumination-direction': 315,
        },
    });
    applyMapTheme(map, container);
    map.easeTo({ pitch: Number(container.dataset.terrainPitch) || 42, bearing: -10, duration: 800 });

    if (maplibregl.TerrainControl) {
        map.addControl(new maplibregl.TerrainControl({ source: TERRAIN_SOURCE_ID, exaggeration }), 'top-right');
    }
}

export function createMap(container, options = {}) {
    const center = parseJson(container.dataset.center, options.center ?? [-47.9292, -15.7801]);
    const styleUrl = container.dataset.styleUrl;
    const rasterTileUrl = container.dataset.rasterTileUrl;
    const rasterDarkTileUrl = container.dataset.rasterDarkTileUrl;
    const rasterAttribution = container.dataset.rasterAttribution;

    const map = new maplibregl.Map({
        container,
        style: styleUrl || rasterFallbackStyle(rasterTileUrl, rasterAttribution, isDarkTheme(), rasterDarkTileUrl),
        center,
        zoom: Number(container.dataset.zoom) || options.zoom || 4,
        attributionControl: true,
    });

    map.addControl(new maplibregl.NavigationControl(), 'top-right');
    container.dataset.activeRasterTileUrl = isDarkTheme() && rasterDarkTileUrl ? rasterDarkTileUrl : rasterTileUrl;
    map.on('error', (event) => console.error('Erro ao renderizar mapa MapLibre.', event.error));
    map.on('style.load', () => applyMapTheme(map, container));
    map.on('load', () => addTerrain(map, container));

    const themeObserver = new MutationObserver(() => applyMapTheme(map, container));
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    map.on('remove', () => themeObserver.disconnect());

    return map;
}

export function fitMapToFeatureCollection(map, data, options = {}) {
    if (!data?.features?.length) return false;

    const bounds = bbox(data);
    map.fitBounds(
        [
            [bounds[0], bounds[1]],
            [bounds[2], bounds[3]],
        ],
        {
            padding: options.padding ?? 40,
            maxZoom: options.maxZoom ?? 16,
            duration: options.duration ?? 500,
        },
    );

    return true;
}

export function registerLivewireMap(initializer, destroyer) {
    document.addEventListener('DOMContentLoaded', initializer, { once: true });
    document.addEventListener('livewire:navigated', initializer);
    document.addEventListener('livewire:navigating', destroyer);

    if (document.readyState !== 'loading') initializer();
}
