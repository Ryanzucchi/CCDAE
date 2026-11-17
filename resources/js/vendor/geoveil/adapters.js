import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { boundsOf, clusterPoints, colorFor, pointRadius, vectorColor } from './state.js';

function installAreaSelection(container, enabled, disableNavigation, enableNavigation, pick) {
    if (!enabled) return { setTool() {}, destroy() {} };
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.classList.add('gv-selection-drawing');
    svg.setAttribute('aria-hidden', 'true');
    Object.assign(svg.style, { position: 'absolute', inset: '0', width: '100%', height: '100%', pointerEvents: 'none', zIndex: '20', overflow: 'hidden' });
    container.append(svg);
    let mode = null, start = null, points = [], path = null, onSelect = () => {};
    const local = event => { const rect = container.getBoundingClientRect(); return [event.clientX - rect.left, event.clientY - rect.top]; };
    const paint = () => {
        if (!points.length) { svg.replaceChildren(); return; }
        if (!path) { path = document.createElementNS(svg.namespaceURI, 'polygon'); path.setAttribute('class', 'gv-selection-shape'); svg.append(path); }
        const display = mode === 'rectangle' && start ? [start, [points.at(-1)[0], start[1]], points.at(-1), [start[0], points.at(-1)[1]]] : points;
        path.setAttribute('points', display.map(point => point.join(',')).join(' '));
    };
    const down = event => {
        if (!mode || event.button !== 0) return;
        event.preventDefault(); event.stopPropagation();
        start = local(event); points = [start]; path = null; disableNavigation();
        container.setPointerCapture?.(event.pointerId); paint();
    };
    const move = event => {
        if (!start) return;
        event.preventDefault(); event.stopPropagation();
        const point = local(event);
        if (mode === 'rectangle') points = [point];
        else if (Math.hypot(point[0] - points.at(-1)[0], point[1] - points.at(-1)[1]) > 4) points.push(point);
        paint();
    };
    const up = event => {
        if (!start) return;
        event.preventDefault(); event.stopPropagation();
        const point = local(event); if (mode === 'rectangle') points = [point]; else points.push(point);
        const polygon = mode === 'rectangle' ? [start, [point[0], start[1]], point, [start[0], point[1]]] : points;
        if (polygon.length >= 3 && Math.abs(polygonArea(polygon)) > 25) onSelect(pick(polygon));
        start = null; enableNavigation(); setTimeout(() => { points = []; path = null; svg.replaceChildren(); }, 0);
    };
    const cancel = () => { start = null; points = []; path = null; svg.replaceChildren(); enableNavigation(); };
    const polygonArea = polygon => polygon.reduce((area, point, index) => { const next = polygon[(index + 1) % polygon.length]; return area + point[0] * next[1] - next[0] * point[1]; }, 0) / 2;
    container.addEventListener('pointerdown', down, true); container.addEventListener('pointermove', move, true);
    container.addEventListener('pointerup', up, true); container.addEventListener('pointercancel', cancel, true);
    return {
        setTool(value, callback) { mode = value; onSelect = callback ?? onSelect; container.style.cursor = value ? 'crosshair' : ''; if (!value) cancel(); },
        destroy() { cancel(); svg.remove(); container.removeEventListener('pointerdown', down, true); container.removeEventListener('pointermove', move, true); container.removeEventListener('pointerup', up, true); container.removeEventListener('pointercancel', cancel, true); },
    };
}

function insidePolygon(point, polygon) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const [x, y] = point, [xi, yi] = polygon[i], [xj, yj] = polygon[j];
        if ((yi > y) !== (yj > y) && x < ((xj - xi) * (y - yi)) / (yj - yi) + xi) inside = !inside;
    }
    return inside;
}

function segmentsIntersect(a, b, c, d) {
    const cross = (p, q, r) => (q[0] - p[0]) * (r[1] - p[1]) - (q[1] - p[1]) * (r[0] - p[0]);
    const onSegment = (p, q, r) => q[0] >= Math.min(p[0], r[0]) && q[0] <= Math.max(p[0], r[0]) && q[1] >= Math.min(p[1], r[1]) && q[1] <= Math.max(p[1], r[1]);
    const abC = cross(a, b, c), abD = cross(a, b, d), cdA = cross(c, d, a), cdB = cross(c, d, b);
    if (abC * abD < 0 && cdA * cdB < 0) return true;
    return (abC === 0 && onSegment(a, c, b)) || (abD === 0 && onSegment(a, d, b)) || (cdA === 0 && onSegment(c, a, d)) || (cdB === 0 && onSegment(c, b, d));
}

function geometrySequences(geometry) {
    const sequences = [];
    const isCoordinate = value => Array.isArray(value) && typeof value[0] === 'number' && typeof value[1] === 'number';
    const visit = value => {
        if (!Array.isArray(value)) return;
        if (isCoordinate(value)) { sequences.push([value]); return; }
        if (value.every(isCoordinate)) { sequences.push(value); return; }
        value.forEach(visit);
    };
    visit(geometry?.coordinates);
    if (geometry?.type === 'MultiPoint') return geometry.coordinates.map(point => [point]);
    return sequences;
}

function geometryTouchesArea(geometry, polygon, project) {
    const sequences = geometrySequences(geometry);
    const projected = sequences.map(sequence => sequence.map(project).filter(Boolean));
    if (projected.some(sequence => sequence.some(point => insidePolygon(point, polygon)))) return true;
    const areaEdges = polygon.map((point, index) => [point, polygon[(index + 1) % polygon.length]]);
    if (projected.some(sequence => sequence.slice(1).some((point, index) => areaEdges.some(([a, b]) => segmentsIntersect(sequence[index], point, a, b))))) return true;

    if (geometry?.type === 'Polygon' || geometry?.type === 'MultiPolygon') {
        const center = polygon.reduce((sum, point) => [sum[0] + point[0] / polygon.length, sum[1] + point[1] / polygon.length], [0, 0]);
        const contains = rings => {
            const projectedRings = rings.map(ring => ring.map(project).filter(Boolean));
            return Boolean(projectedRings[0]?.length && insidePolygon(center, projectedRings[0]) && !projectedRings.slice(1).some(ring => insidePolygon(center, ring)));
        };
        const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
        if (polygons.some(contains)) return true;
    }
    return false;
}

function representativeCoordinate(geometry) {
    const coordinates = [];
    const visit = value => { if (Array.isArray(value) && typeof value[0] === 'number' && typeof value[1] === 'number') coordinates.push(value); else if (Array.isArray(value)) value.forEach(visit); };
    visit(geometry?.coordinates);
    if (!coordinates.length) return null;
    return [coordinates.reduce((sum, point) => sum + point[0], 0) / coordinates.length, coordinates.reduce((sum, point) => sum + point[1], 0) / coordinates.length];
}

function recordPopup(layer, feature) {
    const content = document.createElement('div');
    content.className = 'gv-record-popup';
    const titleField = Object.keys(layer.popup)[0];
    const title = document.createElement('strong');
    title.textContent = String(feature.properties?.[titleField] ?? layer.label);
    content.append(title);
    content.dataset.layout = layer.popupLayout ?? 'definition';
    const fields = Object.entries(layer.popup).slice(1);
    if (fields.length) {
        const details = content.dataset.layout === 'rows' ? null : document.createElement('dl');
        for (const [field, label] of fields) {
            const raw = feature.properties?.[field];
            const text = raw == null || raw === '' ? 'Sem informação' : typeof raw === 'boolean' ? (raw ? 'Sim' : 'Não') : String(raw);
            if (details) {
                const term = document.createElement('dt');
                term.textContent = label;
                const value = document.createElement('dd');
                value.textContent = text;
                details.append(term, value);
            } else {
                const row = document.createElement('div');
                const term = document.createElement('b');
                term.textContent = `${label}: `;
                row.append(term, document.createTextNode(text));
                content.append(row);
            }
        }
        if (details) content.append(details);
    }
    if (layer.recordAction && feature.id != null) {
        const link = document.createElement('a');
        link.textContent = layer.recordAction.label;
        link.href = layer.recordAction.url.replace('{id}', encodeURIComponent(String(feature.id)));
        if (layer.recordAction.modal) link.dataset.geoveilModalLink = '';
        content.append(link);
    }
    return content;
}

function createHoverUi(container, config) {
    const indicator = document.createElement('div');
    indicator.className = 'gv-hover-indicator';
    indicator.dataset.style = ['ring', 'pin', 'crosshair'].includes(config.hoverIndicator?.style) ? config.hoverIndicator.style : 'ring';
    indicator.setAttribute('aria-hidden', 'true');
    indicator.hidden = true;
    const tooltip = document.createElement('div');
    tooltip.className = 'gv-hover-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    container.append(indicator, tooltip);
    return {
        show(point, { title, coordinate, altitude = 0, rows = [] }) {
            if (config.hoverIndicator?.enabled !== false) {
                indicator.style.left = `${point.x}px`;
                indicator.style.top = `${point.y}px`;
                indicator.hidden = false;
            }
            const coords = coordinate ? [coordinate.lng ?? coordinate[0], coordinate.lat ?? coordinate[1]] : null;
            const content = [
                ...(title ? [String(title)] : []),
                ...(coords ? [`Longitude: ${Number(coords[0]).toFixed(5)}°`, `Latitude: ${Number(coords[1]).toFixed(5)}°`] : []),
                `Altitude: ${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(altitude)} m`,
                ...rows.map(String),
            ];
            tooltip.replaceChildren(...content.map((text, index) => {
                const row = document.createElement(index === 0 && title ? 'strong' : 'div');
                row.textContent = text;
                return row;
            }));
            const width = container.clientWidth, height = container.clientHeight;
            const left = point.x + 16 + 280 > width ? Math.max(4, point.x - 288) : point.x + 16;
            const top = point.y + 20 + 150 > height ? Math.max(4, point.y - 158) : point.y + 16;
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
            tooltip.hidden = false;
        },
        hide() { indicator.hidden = true; tooltip.hidden = true; },
        destroy() { indicator.remove(); tooltip.remove(); },
    };
}

function hoverValueField(layer, properties) {
    if (layer.scale?.field) return layer.scale.field;
    return Object.entries(properties ?? {}).find(([key, value]) => !/^(id|.*_id|lat|latitude|lon|lng|longitude|altitude|height)$/i.test(key)
        && value !== null && value !== '' && Number.isFinite(Number(value)))?.[0] ?? null;
}

function hoverAltitude(layer, properties) {
    const raw = properties?.[layer.height ?? 'altitude'] ?? properties?.altitude_m ?? properties?.render_height ?? properties?.height ?? 0;
    const value = Number(raw);
    return Number.isFinite(value) ? value : 0;
}

function flowArrow(bidirectional) {
    const canvas = document.createElement('canvas'); canvas.width = 48; canvas.height = 24;
    const context = canvas.getContext('2d');
    for (const [color, width] of [['#111827', 7], ['#ffffff', 3]]) {
        context.strokeStyle = color; context.lineWidth = width; context.lineCap = 'round'; context.lineJoin = 'round';
        context.beginPath(); context.moveTo(bidirectional ? 8 : 4, 12); context.lineTo(bidirectional ? 40 : 42, 12);
        context.moveTo(33, 4); context.lineTo(43, 12); context.lineTo(33, 20);
        if (bidirectional) { context.moveTo(15, 4); context.lineTo(5, 12); context.lineTo(15, 20); }
        context.stroke();
    }
    return context.getImageData(0, 0, canvas.width, canvas.height);
}

export async function maplibreAdapter(container, config, select, report, onViewport) {
    const maplibregl = await import('maplibre-gl');
    maplibregl.setWorkerUrl(workerUrl);
    const hasSurface = config.layers.some(layer => layer.type === 'surface');
    const has3d = config.layers.some(layer => layer.type === 'building');
    const { surfaceLayer } = hasSurface ? await import('./surface.js') : {};
    const surfaces = new Map(), signatures = new Map(), cutoffs = new Map();
    const surfaceLayers = new Map(config.layers.filter(layer => layer.type === 'surface').map(layer => [layer.id, layer]));
    const baseStyle = config.benchmark ? { version: 8, sources: {}, layers: [] } : {
        version: 8, sources: { base: { type: 'raster', tiles: [config.plugin.tiles ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, maxzoom: 19, attribution: config.plugin.attribution } },
        layers: [{ id: 'base', type: 'raster', source: 'base', paint: config.theme === 'dark' ? { 'raster-brightness-max': 0.36, 'raster-saturation': -0.72, 'raster-contrast': 0.18 } : {} }],
    };
    const map = new maplibregl.Map({ container, center: config.center, zoom: config.zoom, pitch: config.pitch ?? (hasSurface ? 62 : has3d ? 48 : 0), bearing: config.bearing ?? (hasSurface ? -18 : 0), style: baseStyle,
    });
    const hoverUi = createHoverUi(container, config);
    let hoveredFeature = null;
    let visibleLayerIds = new Set(config.layers.map(layer => layer.id));
    map.on('error', () => report('Não foi possível carregar parte do mapa. Verifique sua conexão e a fonte cartográfica.'));
    if (!config.benchmark) {
        map.addControl(new maplibregl.NavigationControl());
        map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }));
    }
    let resolveReady;
    const readyPromise = new Promise(resolve => { resolveReady = resolve; });
    let ready = false;
    let pending = config.layers.map(layer => layer.data.features);
    const clusterData = new Map(config.layers.filter(layer => layer.cluster).map(layer => [layer.id, layer.data.features]));
    const clusterLayerId = id => `${id}-gv-clusters`;
    const clusterLabelId = id => `${id}-gv-cluster-count`;
    const clusteredFeatures = (layer, features) => !layer.cluster || map.getZoom() >= layer.cluster.maxZoom
        ? features
        : clusterPoints(features, coordinate => map.project(coordinate), layer.cluster.radius);
    const updateClusters = (layer, features) => {
        if (!layer.cluster) return;
        clusterData.set(layer.id, features);
        const source = map.getSource(clusterLayerId(layer.id));
        if (source) source.setData(colored(layer, clusteredFeatures(layer, features)));
    };
    function colored(layer, features) {
        return { type: 'FeatureCollection', features: features.map(feature => ({ ...feature, properties: { ...feature.properties, __gv_color: colorFor(layer, feature.properties) } })) };
    }
    function updateSurface(layer, features) {
        const signature = features.map(feature => `${feature.id}:${feature.properties?.[layer.surface.field]}`).join('|');
        if (signatures.get(layer.id) === signature) return;
        signatures.set(layer.id, signature);
        if (map.getLayer(layer.id)) map.removeLayer(layer.id);
        surfaces.delete(layer.id);
        const surface = surfaceLayer(layer.id, features, layer.surface);
        if (surface) { surfaces.set(layer.id, surface); map.addLayer(surface); surface.setCutoff(cutoffs.get(layer.id) ?? 1); surface.setOpacity?.(layer.opacity ?? 1); }
    }
    const opacityById = new Map(config.layers.map(layer => [layer.id, layer.opacity ?? 1]));
    let pendingOrder = config.layers.map(layer => layer.id);
    function setLayerOpacity(id, opacity) {
        opacityById.set(id, opacity);
        const layer = config.layers.find(item => item.id === id);
        if (!layer || !ready) return;
        if (layer.type === 'surface') { surfaces.get(id)?.setOpacity?.(visibleLayerIds.has(id) ? opacity : 0); return; }
        const hover = ['boolean', ['feature-state', 'gv_hover'], false];
        const values = {
            polygon: { 'fill-opacity': ['case', hover, .92 * opacity, .72 * opacity] },
            point: { 'circle-opacity': opacity, 'circle-stroke-opacity': opacity },
            line: { 'line-opacity': opacity },
            heatmap: { 'heatmap-opacity': .75 * opacity },
            building: { 'fill-extrusion-opacity': ['case', hover, .96 * opacity, .78 * opacity] },
        }[layer.type] ?? {};
        for (const [property, value] of Object.entries(values)) if (map.getLayer(id)) map.setPaintProperty(id, property, value);
        if (layer.cluster && map.getLayer(clusterLayerId(id))) {
            map.setPaintProperty(clusterLayerId(id), 'circle-opacity', opacity);
            map.setPaintProperty(clusterLayerId(id), 'circle-stroke-opacity', opacity);
            if (map.getLayer(clusterLabelId(id))) map.setPaintProperty(clusterLabelId(id), 'text-opacity', opacity);
        }
        if (layer.flowDirection && map.getLayer(`${id}-direction`)) map.setPaintProperty(`${id}-direction`, 'icon-opacity', opacity);
    }
    function setLayerOrder(order) {
        pendingOrder = [...order];
        if (!ready) return;
        for (const id of order) {
            const layer = config.layers.find(item => item.id === id);
            if (layer?.type === 'surface') { if (map.getLayer(id)) map.moveLayer(id); continue; }
            if (map.getLayer(id)) map.moveLayer(id);
            if (layer?.cluster && map.getLayer(clusterLayerId(id))) map.moveLayer(clusterLayerId(id));
            if (layer?.cluster && map.getLayer(clusterLabelId(id))) map.moveLayer(clusterLabelId(id));
            if (layer?.flowDirection && map.getLayer(`${id}-direction`)) map.moveLayer(`${id}-direction`);
        }
    }
    const classifications = ['Mínimo', 'Muito baixo', 'Baixo', 'Baixo moderado', 'Moderado baixo', 'Moderado', 'Moderado alto', 'Elevado', 'Alto', 'Muito alto', 'Severo', 'Muito severo', 'Crítico', 'Extremo', 'Máximo'];
    function nearestSurface(point, radius = 24) {
        let closest = null, distance = radius;
        for (const [id, surface] of surfaces) for (const candidate of surface.candidates) {
            if (candidate.weight < surface.minWeight) continue;
            const projected = surface.project(candidate.coordinate, map.getCanvas().clientWidth, map.getCanvas().clientHeight);
            if (!projected) continue;
            const gap = Math.hypot(projected.x - point.x, projected.y - point.y);
            if (gap < distance) { distance = gap; closest = { id, surface, candidate }; }
        }
        return closest;
    }
    function clearHoveredFeature() {
        if (hoveredFeature) {
            try { map.removeFeatureState(hoveredFeature.target, 'gv_hover'); } catch { /* The source may have been replaced during a data refresh. */ }
            hoveredFeature = null;
        }
    }
    function showSurfaceHover(event, closest) {
        const { id, surface, candidate } = closest;
        const layer = surfaceLayers.get(id);
        const titleField = Object.keys(layer.popup ?? {})[0] ?? 'name';
        const value = Number(candidate.properties?.value) || 0;
        const relative = Math.min(.999, Math.max(0, (candidate.weight - surface.minWeight) / Math.max(.001, surface.maxWeight - surface.minWeight)));
        const band = Math.min(9, Math.floor(relative * 10));
        const rows = [
            `${layer.label}: ${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: Math.abs(value) >= 100 ? 0 : Math.abs(value) >= 1 ? 1 : 2 }).format(value)}`,
            `Classificação no corte: ${classifications[Math.min(14, Math.floor(relative * 15))]}`,
            `Faixa de cor ativa: ${band * 10}–${(band + 1) * 10}%`,
            `Percentual geral na cidade: ${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(candidate.weight * 100)}%`,
        ];
        if (Number(candidate.properties?.streets) > 1) rows.push(`${candidate.properties.streets} vias nesta área`);
        hoverUi.show(event.point, {
            title: String(candidate.properties?.[titleField] ?? 'Local sem via identificada'),
            coordinate: event.lngLat,
            altitude: candidate.altitude,
            rows,
        });
    }
    function showFeatureHover(event, feature, layer) {
        const id = feature.id;
        const target = { source: feature.source ?? layer.id, ...(layer.vector ? { sourceLayer: layer.vector.sourceLayer } : {}), id };
        if (id !== undefined && id !== null) {
            const key = `${target.source}:${target.sourceLayer ?? ''}:${id}`;
            if (hoveredFeature?.key !== key) {
                clearHoveredFeature();
                try { map.setFeatureState(target, { gv_hover: true }); hoveredFeature = { key, target }; } catch { hoveredFeature = null; }
            }
        } else clearHoveredFeature();
        const properties = feature.properties ?? {};
        const titleField = Object.keys(layer.popup ?? {})[0];
        const valueField = hoverValueField(layer, properties);
        const value = valueField ? properties[valueField] : null;
        const height = hoverAltitude(layer, properties);
        const rows = [`Camada: ${layer.label}`];
        if (valueField) rows.push(`${valueField}: ${value == null ? 'Sem informação' : String(value)}`);
        hoverUi.show(event.point, {
            title: String((titleField && properties[titleField]) ?? layer.label),
            coordinate: event.lngLat,
            altitude: Number.isFinite(height) ? height : 0,
            rows,
        });
    }
    function handleHover(event) {
        if (!ready) return;
        const closestSurface = nearestSurface(event.point);
        if (closestSurface) {
            clearHoveredFeature();
            showSurfaceHover(event, closestSurface);
            map.getCanvas().style.cursor = 'pointer';
            return;
        }
        const layerIds = config.layers.filter(layer => layer.type !== 'surface' && visibleLayerIds.has(layer.id))
            .flatMap(layer => layer.cluster ? [clusterLayerId(layer.id)] : [layer.id]).filter(id => map.getLayer(id));
        const feature = layerIds.length ? map.queryRenderedFeatures(event.point, { layers: layerIds })[0] : null;
        const layer = feature && config.layers.find(item => item.id === feature.layer.id || (item.cluster && clusterLayerId(item.id) === feature.layer.id));
        if (feature && layer) {
            const amount = Number(feature.properties?.__gv_cluster_count);
            if (amount > 1) hoverUi.show(event.point, { title: `${amount} pontos agrupados`, coordinate: event.lngLat, rows: [`Camada: ${layer.label}`, 'Clique para aproximar e ver os pontos.'] });
            else showFeatureHover(event, feature, layer);
            map.getCanvas().style.cursor = 'pointer';
        } else {
            clearHoveredFeature(); hoverUi.hide(); map.getCanvas().style.cursor = '';
        }
    }
    map.on('mousemove', handleHover);
    map.on('mouseout', () => { clearHoveredFeature(); hoverUi.hide(); map.getCanvas().style.cursor = ''; });
    map.on('click', event => {
        const closest = nearestSurface(event.point, 28);
        if (closest) select(closest.id, closest.candidate.id);
    });
    const applyTheme = theme => {
        config.theme = theme;
        if (!ready || !map.getLayer('base')) return;
        map.setPaintProperty('base', 'raster-brightness-max', theme === 'dark' ? .36 : 1);
        map.setPaintProperty('base', 'raster-saturation', theme === 'dark' ? -.72 : 0);
        map.setPaintProperty('base', 'raster-contrast', theme === 'dark' ? .18 : 0);
    };
    map.on('load', () => {
        ready = true;
        applyTheme(config.theme);
        if (config.layers.some(layer => layer.flowDirection)) {
            map.addImage('gv-flow-arrow', flowArrow(false), { pixelRatio: 2 });
            map.addImage('gv-flow-bidirectional', flowArrow(true), { pixelRatio: 2 });
        }
        config.layers.forEach((layer, index) => {
            if (layer.type === 'surface') { updateSurface(layer, pending[index]); return; }
            if (layer.vector) map.addSource(layer.id, { type: 'vector', url: layer.vector.tilejson });
            else map.addSource(layer.id, { type: 'geojson', data: colored(layer, pending[index]) });
            const types = { polygon: 'fill', point: 'circle', line: 'line', heatmap: 'heatmap', building: 'fill-extrusion' };
            const color = layer.vector ? vectorColor(layer) : ['get', '__gv_color'];
            const buildingColor = layer.scale ? ['interpolate', ['linear'], ['to-number', ['get', layer.scale.field], 0],
                0, layer.scale.colors[0], ...layer.scale.breaks.flatMap((breakpoint, index) => [breakpoint, layer.scale.colors[index + 1]])] : color;
            const isHovered = ['boolean', ['feature-state', 'gv_hover'], false];
            const paints = {
                polygon: { 'fill-color': color, 'fill-opacity': ['case', isHovered, 0.92 * (layer.opacity ?? 1), 0.72 * (layer.opacity ?? 1)], 'fill-outline-color': ['case', isHovered, '#facc15', '#134e4a'] },
                point: { 'circle-color': color, 'circle-radius': ['case', isHovered, ['+', layer.radius ? ['interpolate', ['linear'], ['to-number', ['get', layer.radius.field], layer.radius.minimum], layer.radius.minimum, layer.radius.minimumRadius, layer.radius.maximum, layer.radius.maximumRadius] : 7, 3], layer.radius ? ['interpolate', ['linear'], ['to-number', ['get', layer.radius.field], layer.radius.minimum], layer.radius.minimum, layer.radius.minimumRadius, layer.radius.maximum, layer.radius.maximumRadius] : 7], 'circle-opacity': layer.opacity ?? 1, 'circle-stroke-opacity': layer.opacity ?? 1, 'circle-stroke-width': ['case', isHovered, 3, 2], 'circle-stroke-color': ['case', isHovered, '#facc15', '#ffffff'] },
                line: { 'line-color': ['case', isHovered, '#facc15', color], 'line-width': ['case', isHovered, 7, 4], 'line-opacity': layer.opacity ?? 1 },
                heatmap: { 'heatmap-radius': 28, 'heatmap-opacity': 0.75 * (layer.opacity ?? 1) },
                building: { 'fill-extrusion-color': ['case', isHovered, '#facc15', buildingColor], 'fill-extrusion-height': ['to-number', ['get', layer.height ?? 'height'], 0], 'fill-extrusion-base': ['to-number', ['get', 'render_min_height'], 0], 'fill-extrusion-opacity': ['case', isHovered, 0.96 * (layer.opacity ?? 1), 0.78 * (layer.opacity ?? 1)] },
            };
            map.addLayer({ id: layer.id, type: types[layer.type], source: layer.id,
                ...(layer.minZoom !== null ? { minzoom: layer.minZoom } : {}),
                ...(layer.vector ? { 'source-layer': layer.vector.sourceLayer, ...(layer.vector.filter ? { filter: layer.vector.filter } : {}) } : {}),
                paint: paints[layer.type] });
            if (layer.flowDirection) map.addLayer({
                id: `${layer.id}-direction`, type: 'symbol', source: layer.id,
                layout: { 'symbol-placement': 'line', 'symbol-spacing': 85,
                    'icon-image': ['match', ['coalesce', ['get', layer.flowDirection], 'ambos'], 'ambos', 'gv-flow-bidirectional', 'gv-flow-arrow'],
                    'icon-rotate': ['case', ['==', ['get', layer.flowDirection], 'fim_inicio'], 180, 0],
                    'icon-size': 0.8, 'icon-rotation-alignment': 'map', 'icon-keep-upright': false, 'icon-allow-overlap': true },
            });
            map.on('click', layer.id, event => {
                const feature = event.features?.[0];
                if (feature) {
                    select(layer.id, feature.id, feature);
                    if (Object.keys(layer.popup).length) {
                        new maplibregl.Popup({ maxWidth: `${layer.popupMaxWidth ?? 240}px` })
                            .setLngLat(event.lngLat).setDOMContent(recordPopup(layer, feature)).addTo(map);
                    }
                }
            });
            map.on('mouseenter', layer.id, () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', layer.id, () => { map.getCanvas().style.cursor = ''; });
        });
        config.layers.forEach(layer => {
            setLayerOpacity(layer.id, opacityById.get(layer.id) ?? 1);
            if (layer.type === 'surface') surfaces.get(layer.id)?.setOpacity(visibleLayerIds.has(layer.id) ? (opacityById.get(layer.id) ?? 1) : 0);
            else {
                if (map.getLayer(layer.id)) map.setLayoutProperty(layer.id, 'visibility', visibleLayerIds.has(layer.id) ? 'visible' : 'none');
                if (layer.flowDirection && map.getLayer(`${layer.id}-direction`)) map.setLayoutProperty(`${layer.id}-direction`, 'visibility', visibleLayerIds.has(layer.id) ? 'visible' : 'none');
            }
        });
        setLayerOrder(pendingOrder);
        requestAnimationFrame(() => requestAnimationFrame(resolveReady));
    });
    const viewportLayers = config.benchmark ? [] : config.layers.filter(layer => layer.viewport);
    let viewportRequest = null, viewportTimer = null;
    async function loadViewport() {
        if (!viewportLayers.length || !ready) return;
        viewportRequest?.abort(); viewportRequest = new AbortController();
        const current = viewportRequest;
        const bounds = map.getBounds();
        try {
            const layers = await Promise.all(viewportLayers.map(async layer => {
                if (map.getZoom() < (layer.minZoom ?? 0)) return [layer.id, []];
                const url = new URL(layer.viewport, window.location.href);
                for (const [key, value] of Object.entries({ west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth() })) url.searchParams.set(key, String(value));
                const response = await fetch(url, { signal: current.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Não foi possível carregar ${layer.label} da área visível (HTTP ${response.status}).`);
                return [layer.id, (await response.json()).features ?? []];
            }));
            if (!current.signal.aborted) onViewport?.(Object.fromEntries(layers));
        } catch (error) { if (!current.signal.aborted) report(error.message); }
    }
    let viewChangeHandler = null;
    const getViewState = () => {
        const center = map.getCenter();
        return { center: [center.lng, center.lat], zoom: map.getZoom(), bearing: map.getBearing(), pitch: map.getPitch() };
    };
    map.on('moveend', () => {
        viewChangeHandler?.(getViewState());
        clearTimeout(viewportTimer); viewportTimer = setTimeout(loadViewport, 250);
    });
    map.once('load', loadViewport);
    const observer = new ResizeObserver(() => map.resize()); observer.observe(container);
    const areaSelection = installAreaSelection(container, config.selectionTools, () => map.dragPan.disable(), () => map.dragPan.enable(), polygon => {
        if (!ready) return {};
        const xs = polygon.map(point => point[0]), ys = polygon.map(point => point[1]);
        const bounds = [[Math.min(...xs), Math.min(...ys)], [Math.max(...xs), Math.max(...ys)]];
        const selected = Object.fromEntries(config.layers.map(layer => [layer.id, []]));
        try {
            for (const feature of map.queryRenderedFeatures(bounds)) {
                const id = feature.id ?? feature.properties?.id;
                const layerId = feature.layer?.id;
                if (id == null || !selected[layerId]) continue;
                if (geometryTouchesArea(feature.geometry, polygon, coordinate => {
                    const point = map.project(coordinate);
                    return [point.x, point.y];
                })) selected[layerId].push(String(id));
            }
        } catch (error) { report(`Falha ao selecionar feições no mapa: ${error.message}`); }
        return selected;
    });
    return {
        ready: readyPromise,
        update(data, visible, state = {}) {
            if (state.layerOrder) setLayerOrder(state.layerOrder);
            if (state.layerOpacity) Object.entries(state.layerOpacity).forEach(([id, opacity]) => setLayerOpacity(id, opacity));
            pending = data;
            visibleLayerIds = new Set(visible ?? config.layers.map(layer => layer.id));
            if (hoveredFeature && !visibleLayerIds.has(hoveredFeature.target.source)) clearHoveredFeature();
            hoverUi.hide();
            if (ready) config.layers.forEach((layer, index) => {
                if (layer.type === 'surface') {
                    updateSurface(layer, data[index]);
                    surfaces.get(layer.id)?.setOpacity(visibleLayerIds.has(layer.id) ? (opacityById.get(layer.id) ?? 1) : 0);
                } else {
                    if (map.getLayer(layer.id)) map.setLayoutProperty(layer.id, 'visibility', visibleLayerIds.has(layer.id) ? 'visible' : 'none');
                    if (layer.flowDirection && map.getLayer(`${layer.id}-direction`)) map.setLayoutProperty(`${layer.id}-direction`, 'visibility', visibleLayerIds.has(layer.id) ? 'visible' : 'none');
                    if (!layer.vector) map.getSource(layer.id)?.setData(colored(layer, data[index]));
                }
            });
        },
        setLayerOpacity,
        setLayerOrder,
        theme: applyTheme,
        getViewState,
        setViewState(state) { map.jumpTo({ ...(state.center ? { center: state.center } : {}), ...(state.zoom !== undefined ? { zoom: state.zoom } : {}), ...(state.bearing !== undefined ? { bearing: state.bearing } : {}), ...(state.pitch !== undefined ? { pitch: state.pitch } : {}) }); },
        onViewChange(handler) { viewChangeHandler = handler; },
        cutoff(id, percentage) { cutoffs.set(id, percentage); surfaces.get(id)?.setCutoff(percentage); hoverUi.hide(); },
        fit(features) { const bounds = boundsOf(features); if (bounds) map.fitBounds(bounds, { padding: 55, maxZoom: 15, duration: 450 }); },
        setSelectionTool(mode, callback) { areaSelection.setTool(mode, callback); },
        benchmarkStep(step) { map.jumpTo({ center: [config.center[0] + (step % 2 ? 0.05 : -0.05), config.center[1]], zoom: config.zoom }); },
        destroy() { areaSelection.destroy(); clearHoveredFeature(); hoverUi.destroy(); clearTimeout(viewportTimer); viewportRequest?.abort(); observer.disconnect(); map.remove(); },
    };
}

export async function leafletAdapter(container, config, select, report, onViewport) {
    const { default: L } = await import('leaflet');
    container.classList.toggle('gv-dark-tiles', config.theme === 'dark');
    const map = L.map(container, { preferCanvas: true }).setView([config.center[1], config.center[0]], config.zoom);
    const hoverUi = createHoverUi(container, config);
    if (!config.benchmark) {
        L.tileLayer(config.plugin.tiles ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: config.plugin.attribution })
            .on('tileerror', () => report('Não foi possível carregar parte do mapa. Verifique sua conexão e a fonte cartográfica.')).addTo(map);
        L.control.scale({ imperial: false }).addTo(map);
    }
    const opacityById = new Map(config.layers.map(layer => [layer.id, layer.opacity ?? 1]));
    let layerOrder = config.layers.map(layer => layer.id);
    const groups = config.layers.map(layer => L.geoJSON([], {
        style: feature => ({ color: layer.type === 'polygon' ? '#334155' : colorFor(layer, feature.properties), fillColor: colorFor(layer, feature.properties), weight: layer.type === 'polygon' ? 1.5 : 3, opacity: opacityById.get(layer.id) ?? 1, fillOpacity: 0.78 * (opacityById.get(layer.id) ?? 1) }),
        pointToLayer: (feature, latlng) => L.circleMarker(latlng, { radius: pointRadius(layer, feature.properties), opacity: opacityById.get(layer.id) ?? 1, fillOpacity: 0.9 * (opacityById.get(layer.id) ?? 1), color: colorFor(layer, feature.properties) }),
        onEachFeature: (feature, item) => {
            const originalStyle = () => ({ color: layer.type === 'polygon' ? '#334155' : colorFor(layer, feature.properties), fillColor: colorFor(layer, feature.properties), weight: layer.type === 'polygon' ? 1.5 : 3, opacity: opacityById.get(layer.id) ?? 1, fillOpacity: 0.78 * (opacityById.get(layer.id) ?? 1) });
            const showHover = event => {
                item.setStyle?.({ color: '#facc15', fillColor: '#facc15', weight: (layer.type === 'polygon' ? 1.5 : 3) + 2, opacity: opacityById.get(layer.id) ?? 1, fillOpacity: 0.92 * (opacityById.get(layer.id) ?? 1) });
                if (typeof item.getRadius === 'function' && typeof item.setRadius === 'function') item.setRadius(pointRadius(layer, feature.properties) + 3);
                item.bringToFront?.();
                const props = feature.properties ?? {};
                const titleField = Object.keys(layer.popup ?? {})[0];
                const valueField = hoverValueField(layer, props);
                const value = valueField ? props[valueField] : null;
                hoverUi.show(event.containerPoint ?? map.latLngToContainerPoint(event.latlng), {
                    title: String((titleField && props[titleField]) ?? layer.label),
                    coordinate: event.latlng,
                    altitude: hoverAltitude(layer, props),
                    rows: [`Camada: ${layer.label}`, ...(valueField ? [`${valueField}: ${value == null ? 'Sem informação' : String(value)}`] : [])],
                });
            };
            item.on('click', event => {
                select(layer.id, feature.id ?? feature.properties?.id);
                if (Object.keys(layer.popup).length) {
                    L.popup({ maxWidth: layer.popupMaxWidth ?? 280 }).setLatLng(event.latlng)
                        .setContent(recordPopup(layer, feature)).openOn(map);
                }
            });
            item.on('mouseover', showHover);
            item.on('mousemove', showHover);
            item.on('mouseout', () => {
                item.setStyle?.(originalStyle());
                if (typeof item.setRadius === 'function') item.setRadius(pointRadius(layer, feature.properties));
                hoverUi.hide();
            });
        },
    }).addTo(map));
    const renderedData = [];
    const viewportLayers = config.benchmark ? [] : config.layers.filter(layer => layer.viewport);
    let request = null, viewportTimer = null;
    async function loadViewport() {
        if (!viewportLayers.length) return;
        request?.abort(); request = new AbortController();
        const current = request;
        const bounds = map.getBounds();
        const area = { west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth() };
        try {
            const layers = await Promise.all(viewportLayers.map(async layer => {
                if (map.getZoom() < (layer.minZoom ?? 0)) return [layer.id, []];
                const url = new URL(layer.viewport, window.location.href);
                for (const [key, value] of Object.entries(area)) url.searchParams.set(key, String(value));
                const response = await fetch(url, { signal: current.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Não foi possível carregar ${layer.label} da área visível (HTTP ${response.status}).`);
                const body = await response.json();
                return [layer.id, body.features ?? []];
            }));
            if (!current.signal.aborted) onViewport?.(Object.fromEntries(layers));
        } catch (error) {
            if (!current.signal.aborted) report(error.message);
        }
    }
    let viewChangeHandler = null;
    const getViewState = () => {
        const center = map.getCenter();
        return { center: [center.lng, center.lat], zoom: map.getZoom(), bearing: 0, pitch: 0 };
    };
    map.on('moveend', () => {
        viewChangeHandler?.(getViewState());
        clearTimeout(viewportTimer); viewportTimer = setTimeout(loadViewport, 250);
    });
    map.on('mouseout', () => hoverUi.hide());
    loadViewport();
    const observer = new ResizeObserver(() => map.invalidateSize()); observer.observe(container);
    const areaSelection = installAreaSelection(container, config.selectionTools, () => map.dragging.disable(), () => map.dragging.enable(), polygon => {
        const selected = Object.fromEntries(config.layers.map(layer => [layer.id, []]));
        groups.forEach((group, index) => group.eachLayer(item => {
            const feature = item.feature;
            const id = feature?.id ?? feature?.properties?.id;
            if (id == null) return;
            if (geometryTouchesArea(feature?.geometry, polygon, coordinate => {
                const point = map.latLngToContainerPoint([coordinate[1], coordinate[0]]);
                return [point.x, point.y];
            })) selected[config.layers[index].id].push(String(id));
        }));
        return selected;
    });
    return {
        ready: new Promise(resolve => map.whenReady(() => requestAnimationFrame(() => requestAnimationFrame(resolve)))),
        theme(theme) { container.classList.toggle('gv-dark-tiles', theme === 'dark'); },
        getViewState,
        setViewState(state) { map.setView([state.center?.[1] ?? map.getCenter().lat, state.center?.[0] ?? map.getCenter().lng], state.zoom ?? map.getZoom(), { animate: false }); },
        onViewChange(handler) { viewChangeHandler = handler; },
        update(data, visible, state = {}) {
            if (state.layerOrder) this.setLayerOrder(state.layerOrder);
            if (state.layerOpacity) Object.entries(state.layerOpacity).forEach(([id, amount]) => this.setLayerOpacity(id, amount));
            groups.forEach((group, index) => {
                if (visible?.has(config.layers[index].id)) group.addTo(map);
                else if (visible && !visible.has(config.layers[index].id)) map.removeLayer(group);
                if (renderedData[index] === data[index]) return;
                renderedData[index] = data[index];
                group.clearLayers().addData({ type: 'FeatureCollection', features: data[index] });
            });
        },
        setLayerOpacity(id, amount) {
            opacityById.set(id, amount);
            const index = config.layers.findIndex(layer => layer.id === id);
            if (index < 0) return;
            groups[index].eachLayer(item => {
                item.setStyle?.({ opacity: amount, fillOpacity: (item.feature?.geometry?.type === 'Point' || item.feature?.geometry?.type === 'MultiPoint' ? .9 : .78) * amount });
                if (typeof item.setOpacity === 'function') item.setOpacity(amount);
            });
        },
        setLayerOrder(order) {
            layerOrder = [...order];
            for (const id of layerOrder) {
                const index = config.layers.findIndex(layer => layer.id === id);
                groups[index]?.eachLayer(item => item.bringToFront?.());
            }
        },
        fit(features) { const bounds = boundsOf(features); if (bounds) map.fitBounds(bounds.map(([lng, lat]) => [lat, lng]), { padding: [35, 35], maxZoom: 15 }); },
        setSelectionTool(mode, callback) { areaSelection.setTool(mode, callback); },
        benchmarkStep(step) { map.panBy([step % 2 ? 12 : -12, 0], { animate: false }); },
        destroy() { areaSelection.destroy(); hoverUi.destroy(); clearTimeout(viewportTimer); request?.abort(); observer.disconnect(); map.remove(); },
    };
}
