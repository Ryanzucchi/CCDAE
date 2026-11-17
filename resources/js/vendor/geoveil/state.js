export function numeric(value) {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

/** Build shared per-layer and per-group visibility state for map controls. */
export function createLayerVisibility(layers) {
    const ids = new Set(layers.map(layer => layer.id));
    const groups = new Map();
    layers.forEach(layer => {
        if (!layer.group) return;
        if (!groups.has(layer.group.id)) groups.set(layer.group.id, []);
        groups.get(layer.group.id).push(layer.id);
    });
    const visible = new Set(ids);
    function setLayer(id, enabled) {
        if (!ids.has(id)) throw new Error(`Camada desconhecida: ${id}`);
        enabled ? visible.add(id) : visible.delete(id);
    }
    function setGroup(id, enabled) {
        const members = groups.get(id);
        if (!members) throw new Error(`Grupo desconhecido: ${id}`);
        members.forEach(layerId => enabled ? visible.add(layerId) : visible.delete(layerId));
    }
    function groupState(id) {
        const members = groups.get(id);
        if (!members) throw new Error(`Grupo desconhecido: ${id}`);
        const count = members.filter(layerId => visible.has(layerId)).length;
        return { checked: count === members.length, indeterminate: count > 0 && count < members.length };
    }
    return { visible, setLayer, setGroup, groupState };
}

export function colorFor(layer, properties) {
    if (layer.categories) {
        const value = properties[layer.categories.field];
        if (value === null || value === undefined || value === '') return layer.color ?? '#94a3b8';
        const key = String(value).toLocaleLowerCase('pt-BR');
        if (layer.categories.orderedValues?.length) {
            const index = layer.categories.orderedValues.findIndex(item => String(item).toLocaleLowerCase('pt-BR') === key);
            return index < 0 ? layer.color ?? '#94a3b8' : layer.categories.colors[index % layer.categories.colors.length];
        }
        let hash = 0;
        for (let index = 0; index < key.length; index++) hash = (hash * 31 + key.charCodeAt(index)) >>> 0;
        return layer.categories.colors[hash % layer.categories.colors.length];
    }
    const scale = layer.scale;
    if (!scale) return layer.color;
    const value = numeric(properties[scale.field]);
    if (value === null) return '#94a3b8';
    return scale.colors[scale.breaks.filter(limit => value >= limit).length];
}

/** Convert a point feature's numeric value to its configured proportional-symbol radius. */
export function pointRadius(layer, properties) {
    if (!layer?.radius) return 7;
    const { field, minimum, maximum, minimumRadius, maximumRadius } = layer.radius;
    const raw = Number(properties?.[field]);
    const ratio = Number.isFinite(raw) ? Math.max(0, Math.min(1, (raw - minimum) / (maximum - minimum))) : 0;
    return minimumRadius + ratio * (maximumRadius - minimumRadius);
}

/** Group point features by projected screen cells while retaining their original IDs. */
export function clusterPoints(features, project, radius = 48) {
    const cells = new Map();
    const passthrough = [];
    for (const feature of features) {
        const coordinate = feature.geometry?.type === 'Point' ? feature.geometry.coordinates : null;
        const screen = coordinate ? project(coordinate) : null;
        if (!screen || !Number.isFinite(screen.x) || !Number.isFinite(screen.y)) { passthrough.push(feature); continue; }
        const key = `${Math.floor(screen.x / radius)}:${Math.floor(screen.y / radius)}`;
        if (!cells.has(key)) cells.set(key, []);
        cells.get(key).push(feature);
    }
    const output = [...passthrough];
    for (const [key, members] of cells) {
        if (members.length === 1) { output.push(members[0]); continue; }
        const coordinates = members.map(feature => feature.geometry.coordinates);
        output.push({
            type: 'Feature',
            id: `gv-cluster-${key.replace(':', '-')}`,
            geometry: { type: 'Point', coordinates: [coordinates.reduce((sum, point) => sum + point[0], 0) / members.length, coordinates.reduce((sum, point) => sum + point[1], 0) / members.length] },
            properties: { ...members[0].properties, __gv_cluster_count: members.length, __gv_cluster_ids: members.map(feature => feature.id ?? feature.properties?.id).filter(id => id !== undefined && id !== null) },
        });
    }
    return output;
}
export function vectorColor(layer) {
    if (layer.categories?.orderedValues?.length) {
        const expression = ['match', ['downcase', ['to-string', ['coalesce', ['get', layer.categories.field], '']]]];
        layer.categories.orderedValues.forEach((value, index) => expression.push(String(value).toLocaleLowerCase('pt-BR'), layer.categories.colors[index % layer.categories.colors.length]));
        expression.push(layer.color ?? '#94a3b8');
        return expression;
    }
    return layer.color;
}
export function filteredFeatures(layer, filters, values, search = '', selected = null) {
    const query = search.toLocaleLowerCase('pt-BR').trim();
    const hasActiveFilter = filters.some((filter, index) => {
        if (filter.layer && filter.layer !== layer.id) return false;
        const value = values[index];
        return filter.type === 'date' ? Boolean(value && (value.from || value.to)) : Boolean(value);
    });
    if (!query && !selected && !hasActiveFilter) return layer.data.features;

    return layer.data.features.filter(feature => {
        const props = feature.properties;
        if (selected && (selected.layer !== layer.id || String(selected.id) !== String(feature.id))) return false;
        if (query && !Object.values(props).some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(query))) return false;
        return filters.every((filter, index) => {
            if (filter.layer && filter.layer !== layer.id) return true;
            const value = values[index];
            if (!value || (filter.type === 'date' && !value.from && !value.to)) return true;
            if (filter.type === 'select') return String(props[filter.field] ?? '') === value;
            const date = Date.parse(props[filter.field]);
            if (!Number.isFinite(date)) return false;
            return (!value.from || date >= Date.parse(value.from + 'T00:00:00Z')) && (!value.to || date < Date.parse(value.to + 'T00:00:00Z') + 86400000);
        });
    });
}
export function boundsOf(features) {
    let west = Infinity, south = Infinity, east = -Infinity, north = -Infinity;
    function visit(value) {
        if (!Array.isArray(value)) return;
        if (typeof value[0] === 'number' && typeof value[1] === 'number') {
            if (!Number.isFinite(value[0]) || !Number.isFinite(value[1])) return;
            west = Math.min(west, value[0]); east = Math.max(east, value[0]);
            south = Math.min(south, value[1]); north = Math.max(north, value[1]);
        } else value.forEach(visit);
    }
    features.forEach(feature => visit(feature.geometry?.coordinates));
    return west === Infinity ? null : [[west, south], [east, north]];
}
export function aggregate(widget, features) {
    if (widget.operation === 'count') return features.length;
    return features.reduce((sum, feature) => sum + (numeric(feature.properties[widget.field]) ?? 0), 0);
}

export function timeSeries(widget, features) {
    return features.map(feature => ({ feature, time: Date.parse(feature.properties[widget.time]), value: numeric(feature.properties[widget.field]) }))
        .filter(point => Number.isFinite(point.time) && point.value !== null)
        .sort((a, b) => a.time - b.time);
}
