import { maplibregl, martinTilejsonUrl } from './base-map';

export function label(value, fallback = 'Não informado') {
    return value === null || value === undefined || value === '' ? fallback : String(value).replaceAll('_', ' ');
}

export function popupFromRows(title, rows) {
    const root = document.createElement('div'); root.className = 'min-w-52 space-y-1 text-sm';
    const heading = document.createElement('strong'); heading.className = 'block border-b border-gray-200 pb-1 text-base'; heading.textContent = title; root.appendChild(heading);
    for (const [name, value] of rows) {
        if (value === null || value === undefined || value === '') continue;
        const row = document.createElement('div'); row.className = 'flex justify-between gap-4';
        const key = document.createElement('span'); key.className = 'text-gray-500'; key.textContent = name;
        const content = document.createElement('span'); content.className = 'text-right font-medium'; content.textContent = String(value);
        row.append(key, content); root.appendChild(row);
    }
    return root;
}

export function addMartinSources(map, baseUrl, namespace, sources) {
    for (const [key, source] of Object.entries(sources)) map.addSource(`${namespace}-${key}`, { type: 'vector', url: martinTilejsonUrl(baseUrl, source) });
}

export function bindLayerPopup(map, layers, content) {
    for (const [layer, kind] of Object.entries(layers)) {
        map.on('click', layer, event => new maplibregl.Popup({ maxWidth: '340px' }).setLngLat(event.lngLat).setDOMContent(content(event.features?.[0]?.properties ?? {}, kind, event)).addTo(map));
        map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
        map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
    }
}

export function filterLayersByDistrict(map, layers, district) {
    const filter = district ? ['==', ['get', 'distrito_id'], Number(district)] : null;
    for (const layer of layers) if (map.getLayer(layer)) map.setFilter(layer, filter);
}
