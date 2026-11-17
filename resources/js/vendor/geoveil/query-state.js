export const SEARCH_QUERY_KEY = 'gv_q';
export const MAP_VIEW_QUERY_KEY = 'gv_view';

/** Validate untrusted shared map state against the current map's layer ids. */
export function normalizeMapViewState(state, layerIds = []) {
    if (!state || typeof state !== 'object' || Array.isArray(state)) return null;
    const normalized = {};
    const center = state.center;
    if (Array.isArray(center) && center.length === 2
        && Number.isFinite(Number(center[0])) && Number.isFinite(Number(center[1]))
        && Number(center[0]) >= -180 && Number(center[0]) <= 180
        && Number(center[1]) >= -90 && Number(center[1]) <= 90) {
        normalized.center = [Number(center[0]), Number(center[1])];
    }
    if (Number.isFinite(Number(state.zoom)) && state.zoom !== null) normalized.zoom = Math.max(0, Math.min(24, Number(state.zoom)));
    if (Number.isFinite(Number(state.bearing)) && state.bearing !== null) normalized.bearing = Math.max(-180, Math.min(180, Number(state.bearing)));
    if (Number.isFinite(Number(state.pitch)) && state.pitch !== null) normalized.pitch = Math.max(0, Math.min(85, Number(state.pitch)));
    const allowed = new Set(layerIds);
    if (Array.isArray(state.hiddenLayers)) {
        normalized.hiddenLayers = [...new Set(state.hiddenLayers.filter(id => typeof id === 'string' && allowed.has(id)))];
    }
    if (Array.isArray(state.layerOrder)) {
        const order = [...new Set(state.layerOrder.filter(id => typeof id === 'string' && allowed.has(id)))];
        normalized.layerOrder = [...order, ...layerIds.filter(id => !order.includes(id))];
    }
    if (state.layerOpacity && typeof state.layerOpacity === 'object' && !Array.isArray(state.layerOpacity)) {
        const opacity = {};
        for (const [id, value] of Object.entries(state.layerOpacity)) {
            if (allowed.has(id) && Number.isFinite(Number(value))) opacity[id] = Math.max(0, Math.min(1, Number(value)));
        }
        if (Object.keys(opacity).length) normalized.layerOpacity = opacity;
    }
    return Object.keys(normalized).length ? normalized : null;
}

/** Read a validated camera/layer view from the GeoVeil-owned URL parameter. */
export function readMapViewState(params, layerIds = []) {
    const value = params.get(MAP_VIEW_QUERY_KEY);
    return decodeMapViewState(value, layerIds);
}

/** Store a compact view state without changing application-owned parameters. */
export function writeMapViewState(params, state, layerIds = []) {
    const next = new URLSearchParams(params);
    const normalized = normalizeMapViewState(state, layerIds);
    if (!normalized) next.delete(MAP_VIEW_QUERY_KEY);
    else {
        const compact = {
            ...(normalized.center ? { c: normalized.center.map(value => Number(value.toFixed(5))) } : {}),
            ...(normalized.zoom !== undefined ? { z: Number(normalized.zoom.toFixed(2)) } : {}),
            ...(normalized.bearing !== undefined ? { b: Number(normalized.bearing.toFixed(1)) } : {}),
            ...(normalized.pitch !== undefined ? { p: Number(normalized.pitch.toFixed(1)) } : {}),
            ...(normalized.hiddenLayers?.length ? { h: normalized.hiddenLayers } : {}),
            ...(normalized.layerOrder ? { o: normalized.layerOrder } : {}),
            ...(normalized.layerOpacity ? { a: normalized.layerOpacity } : {}),
        };
        next.set(MAP_VIEW_QUERY_KEY, JSON.stringify(compact));
    }
    return next;
}

/** Decode the compact representation written by writeMapViewState. */
export function decodeMapViewState(value, layerIds = []) {
    if (!value) return null;
    try {
        const compact = JSON.parse(value);
        return normalizeMapViewState({ center: compact.c, zoom: compact.z, bearing: compact.b, pitch: compact.p, hiddenLayers: compact.h, layerOrder: compact.o, layerOpacity: compact.a }, layerIds);
    } catch { return null; }
}

export function filterQueryKey(filter) {
    return `gv_filter[${filter.key ?? (filter.layer ? `${filter.layer}.${filter.field}` : filter.field)}]`;
}

/** Read only GeoVeil's query parameters, leaving the application's query intact. */
export function readQueryState(params, filters) {
    const values = {};
    const search = params.get(SEARCH_QUERY_KEY) ?? '';
    filters.forEach((filter, index) => {
        const key = filterQueryKey(filter);
        if (filter.type === 'date') {
            const from = params.get(`${key}.from`) ?? '';
            const to = params.get(`${key}.to`) ?? '';
            if (from || to) values[index] = { ...(from ? { from } : {}), ...(to ? { to } : {}) };
        } else {
            const value = params.get(key);
            if (value !== null) values[index] = value;
        }
    });

    return { search, values };
}

/** Replace GeoVeil's values while preserving unrelated application parameters. */
export function writeQueryState(params, filters, values, search) {
    const next = new URLSearchParams(params);
    next.delete(SEARCH_QUERY_KEY);
    if (search.trim()) next.set(SEARCH_QUERY_KEY, search.trim());

    filters.forEach((filter, index) => {
        const key = filterQueryKey(filter);
        next.delete(key);
        next.delete(`${key}.from`);
        next.delete(`${key}.to`);
        const value = values[index];
        if (filter.type === 'date') {
            if (value?.from) next.set(`${key}.from`, value.from);
            if (value?.to) next.set(`${key}.to`, value.to);
        } else if (value !== undefined && value !== '') {
            next.set(key, String(value));
        }
    });

    return next;
}
