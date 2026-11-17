import { surfaceColors } from './surface-data.js';
import 'maplibre-gl/dist/maplibre-gl.css';
import 'leaflet/dist/leaflet.css';
import '../css/workspace.css';
import '../css/auth.css';
import { maplibreAdapter, leafletAdapter } from './adapters.js';
import { filteredFeatures, aggregate, numeric, timeSeries, createLayerVisibility } from './state.js';
import { resolveThemePreference } from './theme.js';
import { normalizeMapViewState, readMapViewState, readQueryState, writeMapViewState, writeQueryState } from './query-state.js';
import { benchmarkScenario, hasCompleteBenchmark, readBenchmarkCache, rankBenchmarks, writeBenchmarkCache } from './benchmark.js';
import { mountBulkActions } from './bulk-actions.js';
import { EngineAdapterRegistry, validateEngineAdapter } from './engine-registry.js';
import { overlayPanelsToCollapse } from './overlay-layout.js';

const engines = new EngineAdapterRegistry([['maplibre', maplibreAdapter], ['leaflet', leafletAdapter]]);
const benchmarkRuns = new Map();
export function registerEngine(name, factory) { engines.register(name, factory); }
/** @deprecated Use registerEngine(). Kept for existing GeoVeil 2 consumers. */
export function registerPlugin(name, factory) { return registerEngine(name, factory); }
const nextFrame = () => new Promise(resolve => requestAnimationFrame(resolve));
function waitFor(promise, timeoutMs = 5000) {
    let timer;
    return Promise.race([promise, new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Tempo de benchmark excedido.')), timeoutMs); })])
        .finally(() => clearTimeout(timer));
}
async function measureEngine(engine, config) {
    const factory = engines.get(engine.id);
    if (!factory) throw new Error(`Adaptador do engine ${engine.id} não está registrado.`);
    const host = document.createElement('div');
    host.setAttribute('aria-hidden', 'true');
    host.style.cssText = 'position:fixed;left:-10000px;top:0;width:640px;height:420px;visibility:hidden;pointer-events:none;contain:strict;';
    document.body.append(host);
    const trial = structuredClone(config);
    trial.benchmark = true;
    trial.plugin = engine;
    trial.layers = trial.layers.map(layer => ({ ...layer, viewport: null, vector: null }));
    const heapBefore = performance.memory?.usedJSHeapSize ?? null;
    const start = performance.now();
    let adapter;
    try {
        adapter = validateEngineAdapter(await factory(host, trial, () => {}, () => {}, () => {}), engine.id, trial);
        await waitFor(adapter.ready ?? nextFrame().then(nextFrame));
        const loadMs = performance.now() - start;
        const updateStart = performance.now();
        adapter.update(trial.layers.map(layer => layer.data.features), new Set(trial.layers.map(layer => layer.id)));
        const updateMs = performance.now() - updateStart;
        const frameStart = performance.now();
        const samples = 10;
        for (let frame = 0; frame < samples; frame++) {
            adapter.benchmarkStep?.(frame);
            await nextFrame();
        }
        const frameMs = (performance.now() - frameStart) / samples;
        const heapAfter = performance.memory?.usedJSHeapSize ?? null;
        const memoryBytes = heapBefore === null || heapAfter === null ? null : Math.max(0, heapAfter - heapBefore);
        const memoryPenalty = memoryBytes === null ? 0 : memoryBytes / (1024 * 1024) * 12;
        const cost = Math.max(1, loadMs + updateMs + frameMs * 8 + memoryPenalty);

        return { score: 100000 / cost, loadMs, updateMs, frameMs, fps: 1000 / frameMs, memoryBytes };
    } finally {
        adapter?.destroy?.();
        host.remove();
    }
}
function browserStorage() {
    try { return window.localStorage; } catch { return null; }
}
function chooseCachedEngine(config) {
    const selection = config.plugin.selection;
    const candidates = selection?.candidates ?? [];
    const fallbackId = config.plugin.id;
    const scenario = benchmarkScenario(config);
    const metrics = readBenchmarkCache(browserStorage(), scenario);
    const complete = hasCompleteBenchmark(candidates, metrics);
    const ranked = rankBenchmarks(candidates, complete ? metrics : {}, fallbackId);
    return {
        engine: ranked[0] ?? candidates.find(candidate => candidate.id === fallbackId) ?? config.plugin,
        metrics: complete ? metrics : {}, scenario, candidates, needsBenchmark: !complete,
    };
}
function collectEngineBenchmarks(config, diagnostics, shouldCancel) {
    const existing = benchmarkRuns.get(diagnostics.scenario);
    if (existing) return existing;
    const task = (async () => {
        const metrics = {};
        for (const engine of diagnostics.candidates) {
            if (shouldCancel()) break;
            try { metrics[engine.id] = await measureEngine(engine, config); }
            catch { /* A failed trial is skipped; the compatible server choice remains available. */ }
        }
        if (!shouldCancel() && hasCompleteBenchmark(diagnostics.candidates, metrics)) {
            writeBenchmarkCache(browserStorage(), diagnostics.scenario, metrics);
        }
        return metrics;
    })();
    benchmarkRuns.set(diagnostics.scenario, task);
    return task.finally(() => benchmarkRuns.delete(diagnostics.scenario));
}
const number = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
function el(tag, className, text) {
    const node = document.createElement(tag); node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}
function button(label, action, className = 'gv-button') {
    const node = el('button', className, label); node.type = 'button'; node.addEventListener('click', action); return node;
}
const navigationIcons = {
    home: 'M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z',
    map: 'M3 6 9 3l6 3 6-3v15l-6 3-6-3-6 3zm6-3v15m6-12v15',
    layers: 'm12 3 9 5-9 5-9-5zm-9 9 9 5 9-5m-18 5 9 5 9-5',
    chart: 'M4 19V5m0 14h17M8 15l3-4 3 2 5-7',
    settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm0-5v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4m0-12.8L17 7M7 17l-1.4 1.4',
    users: 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6-7a4 4 0 0 1 0 8m2 3a4 4 0 0 1 2 4v2',
    route: 'M5 6a2 2 0 1 0 0 .01M19 18a2 2 0 1 0 0 .01M7 6h3a3 3 0 0 1 3 3v6a3 3 0 0 0 3 3h1',
    plus: 'M12 5v14m-7-7h14',
    search: 'm20 20-4.5-4.5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0z',
    globe: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zM3 12h18M12 3a14 14 0 0 1 0 18m0-18a14 14 0 0 0 0 18',
    shield: 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11zm-3-11 2 2 4-4',
    building: 'M4 21V4l8-2v19m0-13h8v13M2 21h20M7 7h2m-2 4h2m-2 4h2m8-2h1m-1 4h1',
    file: 'M6 2h9l5 5v15H6zm9 0v5h5M9 12h8m-8 4h8',
    bolt: 'm13 2-3 9h7l-6 11 2-9H6z',
};
function navIcon(name, fallback = '•') {
    const wrapper = el('span', 'gv-nav-icon'); wrapper.setAttribute('aria-hidden', 'true');
    const path = navigationIcons[name];
    if (!path) { wrapper.textContent = fallback; return wrapper; }
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8'); svg.setAttribute('stroke-linecap', 'round'); svg.setAttribute('stroke-linejoin', 'round');
    const shape = document.createElementNS(svg.namespaceURI, 'path'); shape.setAttribute('d', path); svg.append(shape); wrapper.append(svg);
    return wrapper;
}
function renderNavigation(config) {
    const nav = el('nav', `gv-navigation gv-navigation-${config.mode}`); nav.setAttribute('aria-label', 'Navegação do GeoVeil');
    const toggle = button(config.mode === 'rail' ? 'Expandir menu' : 'Recolher menu', () => {
        const collapsed = nav.closest('.gv-workspace').dataset.navigationCollapsed !== 'true';
        nav.closest('.gv-workspace').dataset.navigationCollapsed = String(collapsed);
        toggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
        toggle.textContent = collapsed ? 'Expandir menu' : 'Recolher menu';
    }, 'gv-nav-collapse');
    if (config.mode !== 'topbar') nav.append(toggle);
    const list = el('div', 'gv-nav-list');
    const renderEntry = (entry, depth = 0) => {
        if (entry.type === 'divider') {
            const divider = el('div', 'gv-nav-divider'); divider.setAttribute('role', 'separator');
            if (entry.label) divider.append(el('span', '', entry.label));
            return divider;
        }
        if (entry.type === 'item') {
            const anchor = el('a', 'gv-nav-link'); anchor.href = entry.url; anchor.setAttribute('aria-label', entry.label); anchor.title = entry.label;
            anchor.dataset.pinned = String(Boolean(entry.pinned));
            if (entry.external) { anchor.target = '_blank'; anchor.rel = 'noopener noreferrer'; }
            anchor.append(navIcon(entry.icon, entry.label.slice(0, 1)));
            anchor.append(el('span', 'gv-nav-label', entry.label));
            if (entry.badge !== null && entry.badge !== undefined) anchor.append(el('span', 'gv-nav-badge', String(entry.badge)));
            return anchor;
        }
        if (entry.type !== 'group') return document.createDocumentFragment();
        const details = el('details', `gv-nav-group gv-nav-depth-${depth}`); details.dataset.key = entry.key; details.dataset.pinned = String(Boolean(entry.pinned));
        details.open = config.mode !== 'rail' && depth === 0;
        const summary = el('summary', 'gv-nav-group-label'); summary.setAttribute('aria-label', entry.label); summary.title = entry.label;
        summary.append(navIcon(entry.icon, entry.label.slice(0, 1)));
        summary.append(el('span', 'gv-nav-label', entry.label));
        if (entry.badge !== null && entry.badge !== undefined) summary.append(el('span', 'gv-nav-badge', String(entry.badge)));
        summary.append(el('span', 'gv-nav-chevron', '›'));
        const children = el('div', 'gv-nav-children');
        for (const child of entry.children ?? []) children.append(renderEntry(child, depth + 1));
        details.append(summary, children);
        return details;
    };
    const pinned = config.items.filter(item => item.pinned);
    const ordinary = config.items.filter(item => !item.pinned);
    for (const item of pinned) list.append(renderEntry(item));
    if (pinned.length && ordinary.length) list.append(el('div', 'gv-nav-divider'));
    for (const item of ordinary) list.append(renderEntry(item));
    nav.append(list);
    return nav;
}
function appendSearchHighlight(target, text, query) {
    const value = String(text ?? '');
    const offset = query ? value.toLocaleLowerCase('pt-BR').indexOf(query.toLocaleLowerCase('pt-BR')) : -1;
    if (offset < 0) { target.textContent = value; return; }
    target.append(document.createTextNode(value.slice(0, offset)));
    const mark = el('mark', 'gv-search-highlight', value.slice(offset, offset + query.length));
    target.append(mark, document.createTextNode(value.slice(offset + query.length)));
}
function navigationItems(items, parent = '') {
    return items.flatMap(item => {
        if (item.type === 'item') return [{ label: item.label, url: item.url, parent, external: item.external }];
        if (item.type === 'group') return navigationItems(item.children ?? [], parent ? `${parent} / ${item.label}` : item.label);
        return [];
    });
}
export async function mount(root, config) {
    if (root.geoveil) root.geoveil.destroy();
    root.replaceChildren();
    let themePreference = config.theme ?? 'light';
    const colorScheme = window.matchMedia?.('(prefers-color-scheme: dark)');
    const resolveTheme = () => resolveThemePreference(themePreference, Boolean(colorScheme?.matches));
    config.theme = resolveTheme();
    root.dataset.theme = config.theme;
    root.dataset.themeMode = themePreference;
    root.dataset.layout = config.layout ?? 'workspace';
    root.dataset.density = config.tokens?.density ?? 'comfortable';
    root.dataset.hasDarkLogo = String(Boolean(config.brand?.darkLogo));
    const tokenNames = { primary: '--gv-primary', accent: '--gv-accent', surface: '--gv-surface', text: '--gv-ink', muted: '--gv-muted', border: '--gv-line', font: '--gv-font', radius: '--gv-radius' };
    const previousTokenStyles = new Map();
    for (const [name, value] of Object.entries(config.tokens ?? {})) {
        if (!tokenNames[name]) continue;
        const property = tokenNames[name];
        previousTokenStyles.set(property, [root.style.getPropertyValue(property), root.style.getPropertyPriority(property)]);
        root.style.setProperty(property, value);
    }
    const placements = config.placements ?? {
        information: { placement: 'intern', position: 'top-left' },
        filters: { placement: 'extern', position: 'top-left' },
        legend: { placement: 'intern', position: 'top-right' },
    };
    root.dataset.informationPlacement = placements.information.placement;
    root.dataset.filtersPlacement = placements.filters.placement;
    root.dataset.legendPlacement = placements.legend.placement;
    const navigation = config.navigation?.items?.length ? renderNavigation(config.navigation) : null;
    if (navigation) {
        root.dataset.navigationMode = config.navigation.mode;
        root.dataset.navigationCollapsed = String(config.navigation.mode === 'rail');
    } else {
        delete root.dataset.navigationMode;
        delete root.dataset.navigationCollapsed;
    }
    if (config.layout === 'embedded' && config.layers.some(layer => layer.group)) root.dataset.layerGroups = 'overlay';
    const controller = new AbortController();
    const surfaceControls = new Map();
    const urlParams = new URLSearchParams(window.location.search);
    const layerIds = config.layers.map(layer => layer.id);
    const queryState = config.urlSync !== false ? readQueryState(urlParams, config.filters) : { search: '', values: {} };
    const sharedView = config.viewUrlSync !== false ? readMapViewState(urlParams, layerIds) : null;
    const declaredView = { center: config.center, zoom: config.zoom, bearing: config.bearing, pitch: config.pitch };
    let defaultView = { ...declaredView, bearing: declaredView.bearing ?? 0, pitch: declaredView.pitch ?? 0 };
    if (sharedView?.center) config.center = sharedView.center;
    if (sharedView?.zoom !== undefined) config.zoom = sharedView.zoom;
    if (sharedView?.bearing !== undefined) config.bearing = sharedView.bearing;
    if (sharedView?.pitch !== undefined) config.pitch = sharedView.pitch;
    const values = queryState.values; const layerVisibility = createLayerVisibility(config.layers); const visible = layerVisibility.visible;
    for (const id of sharedView?.hiddenLayers ?? []) layerVisibility.setLayer(id, false);
    let layerOrder = [...(sharedView?.layerOrder ?? layerIds)];
    const layerOpacity = new Map(config.layers.map(layer => [layer.id, sharedView?.layerOpacity?.[layer.id] ?? layer.opacity ?? 1]));
    let latestView = { center: config.center, zoom: config.zoom, bearing: config.bearing ?? 0, pitch: config.pitch ?? 0, hiddenLayers: layerIds.filter(id => !visible.has(id)), layerOrder, layerOpacity: Object.fromEntries(layerOpacity) };
    let search = queryState.search, selected = null, selectedFeature = null, adapter = null, disposed = false, searchTimer = null, benchmarkIdleId = null, benchmarkTimer = null;
    let searchInput = null;
    const filterInputs = new Map();
    const syncUrl = () => {
        if (config.urlSync === false && config.viewUrlSync === false) return;
        const url = new URL(window.location.href);
        let params = url.searchParams;
        if (config.urlSync !== false) params = writeQueryState(params, config.filters, values, search);
        if (config.viewUrlSync !== false) {
            const camera = adapter?.getViewState?.() ?? latestView;
            latestView = normalizeMapViewState({ ...camera, hiddenLayers: layerIds.filter(id => !visible.has(id)), layerOrder, layerOpacity: Object.fromEntries(layerOpacity) }, layerIds) ?? latestView;
            params = writeMapViewState(params, latestView, layerIds);
        }
        url.search = params.toString();
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
    };
    const searchHistoryKey = `geoveil:search-history:${config.id}`;
    let searchHistory = [];
    try { searchHistory = JSON.parse(window.localStorage.getItem(searchHistoryKey) ?? '[]'); } catch { searchHistory = []; }
    if (!Array.isArray(searchHistory)) searchHistory = [];
    const recordSearch = query => {
        const value = query.trim();
        if (!value) return;
        searchHistory = [value, ...searchHistory.filter(item => item.toLocaleLowerCase('pt-BR') !== value.toLocaleLowerCase('pt-BR'))].slice(0, 8);
        try { window.localStorage.setItem(searchHistoryKey, JSON.stringify(searchHistory)); } catch { /* Search history is an optional convenience. */ }
    };
    const header = el('header', 'gv-header');
    const brandConfig = config.brand ?? {};
    const brand = el('div', 'gv-brand');
    if (brandConfig.logo || brandConfig.darkLogo) {
        const logos = el('span', 'gv-brand-logos');
        const addLogo = (source, className) => {
            if (!source) return;
            try {
                const url = new URL(source, window.location.href);
                if (!['http:', 'https:'].includes(url.protocol)) return;
                const image = el('img', className); image.src = url.href; image.alt = brandConfig.name ?? ''; image.loading = 'lazy';
                image.addEventListener('error', () => image.remove(), { once: true }); logos.append(image);
            } catch { /* An invalid optional brand image is omitted. */ }
        };
        addLogo(brandConfig.logo ?? brandConfig.darkLogo, 'gv-logo-light');
        if (brandConfig.darkLogo) addLogo(brandConfig.darkLogo, 'gv-logo-dark');
        if (logos.childNodes.length) brand.append(logos);
    }
    brand.append(el('span', 'gv-eyebrow', brandConfig.name ?? 'GEOVEIL 2 / EXPLORADOR'), el('h1', '', config.title));
    const engineLabel = el('span', 'gv-engine', ({ maplibre: 'MapLibre', leaflet: 'Leaflet' })[config.plugin.id] ?? config.plugin.id);
    const embeddedSearchButton = config.layout === 'embedded' ? button('Buscar', openPalette, 'gv-info-search') : null;
    header.append(brand, engineLabel);
    if (embeddedSearchButton) header.append(embeddedSearchButton);
    let faviconLink = null, originalFavicon = null, createdFavicon = false, appliedFavicon = null;
    if (brandConfig.favicon) {
        try {
            const faviconUrl = new URL(brandConfig.favicon, window.location.href);
            if (['http:', 'https:'].includes(faviconUrl.protocol)) {
                faviconLink = document.querySelector('link[rel~="icon"]');
                if (!faviconLink) { faviconLink = document.createElement('link'); faviconLink.rel = 'icon'; document.head.append(faviconLink); createdFavicon = true; }
                else originalFavicon = faviconLink.href;
                appliedFavicon = faviconUrl.href; faviconLink.href = appliedFavicon;
            }
        } catch { /* An invalid optional favicon is ignored. */ }
    }
    const body = el('div', 'gv-body');
    const aside = el('aside', 'gv-sidebar'); aside.setAttribute('aria-label', 'Filtros e camadas');
    const stage = el('main', 'gv-stage');
    const toolbar = el('div', 'gv-toolbar');
    const canvas = el('div', 'gv-canvas'); canvas.setAttribute('aria-label', 'Mapa interativo');
    const status = el('p', 'gv-status', 'Carregando mapa…'); status.setAttribute('role', 'status');
    const panel = el('aside', 'gv-panel'); panel.setAttribute('aria-label', 'Análises e detalhes');
    const stats = el('div', 'gv-stats');
    const detail = el('section', 'gv-detail');
    const charts = el('section', 'gv-charts');
    const results = el('section', 'gv-results');
    const commandDialog = el('dialog', 'gv-command-palette'); commandDialog.setAttribute('aria-label', 'Busca global');
    const commandHeading = el('h2', '', 'Buscar no GeoVeil'); commandDialog.append(commandHeading);
    const commandInput = el('input', 'gv-command-input'); commandInput.type = 'search'; commandInput.placeholder = 'Recursos, registros, lugares e endereços'; commandInput.setAttribute('aria-label', 'Busca global');
    const commandResults = el('div', 'gv-command-results'); commandResults.setAttribute('role', 'list');
    const closeCommand = button('Fechar', () => commandDialog.close(), 'gv-command-close');
    commandDialog.append(commandInput, commandResults, closeCommand);
    const savedViewsDialog = config.savedViewsEndpoint ? el('dialog', 'gv-command-palette gv-saved-views-dialog') : null;
    let savedViewsList = null, savedViewsStatus = null, savedViewName = null, savedViewShared = null;
    if (savedViewsDialog) {
        savedViewsDialog.setAttribute('aria-label', 'Vistas salvas');
        savedViewsDialog.append(el('h2', '', 'Vistas salvas'));
        const nameLabel = el('label', 'gv-saved-view-name'); nameLabel.append(el('span', '', 'Nome da vista'));
        savedViewName = el('input', 'gv-input'); savedViewName.type = 'text'; savedViewName.maxLength = 120; savedViewName.required = true; savedViewName.placeholder = 'Ex.: Distritos prioritários';
        nameLabel.append(savedViewName);
        const sharedLabel = el('label', 'gv-saved-view-shared'); savedViewShared = document.createElement('input'); savedViewShared.type = 'checkbox';
        sharedLabel.append(savedViewShared, el('span', '', 'Compartilhar com pessoas autorizadas'));
        savedViewsStatus = el('p', 'gv-muted'); savedViewsStatus.setAttribute('role', 'status');
        savedViewsList = el('div', 'gv-saved-view-list');
        const save = button('Salvar vista atual', saveCurrentView, 'gv-button');
        const close = button('Fechar', () => savedViewsDialog.close(), 'gv-command-close');
        savedViewsDialog.append(nameLabel, sharedLabel, savedViewsStatus, save, savedViewsList, close);
    }
    const savedViewsButton = savedViewsDialog ? button('Vistas salvas', openSavedViews, 'gv-button gv-saved-views-button') : null;
    toolbar.append(el('span', 'gv-toolbar-title', 'Visão territorial'), button('Busca global', openPalette, 'gv-button gv-global-search'), button('Enquadrar dados', () => adapter?.fit(current().flat())), button('Limpar seleção', () => { selected = null; render(); }));
    if (savedViewsButton && config.layout !== 'embedded') toolbar.append(savedViewsButton);
    if (savedViewsButton && config.layout === 'embedded' && placements.information.placement === 'extern') header.append(savedViewsButton);
    let selectionTool = null;
    let selectionStatus = null;
    const handleAreaSelection = resultsByLayer => {
        let total = 0;
        for (const [layer, ids] of Object.entries(resultsByLayer ?? {})) {
            total += ids.length;
            root.dispatchEvent(new CustomEvent('geoveil:bulk-select', { bubbles: true, detail: { layer, ids } }));
        }
        if (selectionStatus) selectionStatus.textContent = `${total} feição${total === 1 ? '' : 's'} selecionada${total === 1 ? '' : 's'}`;
    };
    if (config.selectionTools) {
        selectionStatus = el('span', 'gv-selection-status', 'Selecione no mapa');
        const activate = (mode, control) => {
            selectionTool = selectionTool === mode ? null : mode;
            toolbar.querySelectorAll('[data-geoveil-selection-tool]').forEach(item => item.setAttribute('aria-pressed', String(item === control && selectionTool === mode)));
            adapter?.setSelectionTool?.(selectionTool, handleAreaSelection);
        };
        for (const [mode, label] of [['rectangle', 'Selecionar retângulo'], ['lasso', 'Selecionar laço']]) {
            const control = button(label, () => activate(mode, control), 'gv-button gv-selection-tool');
            control.dataset.geoveilSelectionTool = mode; control.setAttribute('aria-pressed', 'false'); toolbar.append(control);
        }
        toolbar.append(selectionStatus);
    }
    if (config.layout === 'map') {
        const togglePanel = (target, label) => {
            const control = button(label, () => { target.hidden = !target.hidden; control.setAttribute('aria-expanded', String(!target.hidden)); });
            target.hidden = true; control.setAttribute('aria-expanded', 'false'); toolbar.prepend(control);
            return control;
        };
        togglePanel(aside, 'Filtros e camadas');
        togglePanel(panel, 'Indicadores e detalhes');
    }
    const overlayRoot = el('div', 'gv-overlay-root');
    overlayRoot.setAttribute('role', 'group'); overlayRoot.setAttribute('aria-label', 'Controles do mapa');
    const overlayZones = new Map();
    for (const position of ['top-left', 'top-center', 'top-right', 'left-center', 'right-center', 'bottom-left', 'bottom-center', 'bottom-right']) {
        const zone = el('div', `gv-overlay-zone gv-overlay-${position}`);
        zone.dataset.position = position;
        zone.setAttribute('role', 'group'); zone.setAttribute('aria-label', `Controles ${position}`);
        overlayZones.set(position, zone);
        overlayRoot.append(zone);
    }
    const collapsible = (className, label, content, open = true) => {
        const details = el('details', className); details.open = open;
        details.append(el('summary', 'gv-overlay-summary', label), content);
        return details;
    };
    let overlayFrame = null;
    const overlayLayoutObserver = new ResizeObserver(() => scheduleOverlayLayout());
    overlayLayoutObserver.observe(overlayRoot);
    const overlayContentObserver = new MutationObserver(() => scheduleOverlayLayout());
    overlayContentObserver.observe(overlayRoot, { childList: true, subtree: true, characterData: true });
    for (const zone of overlayZones.values()) overlayLayoutObserver.observe(zone);
    const fitOverlayContainer = container => {
        if (!container.clientHeight) return;
        const panels = [...container.querySelectorAll('[data-geoveil-overlay]')].filter(panel => !panel.hidden && panel.getClientRects().length);
        const overflow = container.scrollHeight - container.clientHeight;
        if (overflow <= 0) return;
        const candidates = panels.map(panel => {
            const summary = panel.querySelector(':scope > summary');
            return {
                id: panel.dataset.geoveilOverlay,
                open: panel.open,
                userControlled: panel.dataset.userControlled === 'true',
                priority: Number(panel.dataset.overlayPriority ?? 50),
                saving: Math.max(0, panel.getBoundingClientRect().height - (summary?.getBoundingClientRect().height ?? 0)),
            };
        });
        const ids = new Set(overlayPanelsToCollapse(candidates, overflow));
        panels.forEach(panel => {
            if (!ids.has(panel.dataset.geoveilOverlay)) return;
            panel.dataset.autoOpen = 'false';
            panel.dataset.autoCollapsed = 'true';
            panel.open = false;
        });
    };
    function scheduleOverlayLayout() {
        if (overlayFrame !== null) return;
        overlayFrame = requestAnimationFrame(() => {
            overlayFrame = null;
            if (window.matchMedia('(max-width: 650px)').matches) fitOverlayContainer(overlayRoot);
            else for (const zone of overlayZones.values()) fitOverlayContainer(zone);
        });
    }
    let overlaySequence = 0;
    const addOverlay = (node, position = 'top-right', priority = 50, label = 'Mapa') => {
        const zone = overlayZones.get(position);
        if (!zone) throw new Error(`Posição de overlay desconhecida: ${position}`);
        let panel = node;
        if (panel.tagName !== 'DETAILS') {
            const content = el('div', 'gv-overlay-content'); content.append(panel);
            panel = collapsible('gv-auto-overlay', label, content, false);
        }
        const id = `overlay-${++overlaySequence}`;
        panel.dataset.geoveilOverlay = id;
        panel.dataset.overlayPriority = String(priority);
        panel.dataset.autoOpen = String(panel.open);
        panel.addEventListener('toggle', () => {
            if (panel.dataset.autoOpen === String(panel.open)) delete panel.dataset.autoOpen;
            else {
                panel.dataset.userControlled = 'true';
                delete panel.dataset.autoCollapsed;
            }
            scheduleOverlayLayout();
        });
        zone.append(panel);
        overlayLayoutObserver.observe(panel);
        scheduleOverlayLayout();
        return panel;
    };
    if (placements.information.placement === 'intern') {
        const information = el('div', 'gv-information-content');
        information.append(brand, engineLabel);
        if (embeddedSearchButton) information.append(embeddedSearchButton);
        if (savedViewsButton && config.layout === 'embedded') information.append(savedViewsButton);
        addOverlay(collapsible('gv-information-overlay', 'Informações do mapa', information), placements.information.position, 40);
    }
    config.layers.filter(layer => layer.type === 'surface').forEach(layer => {
        const legend = el('section', 'gv-surface-legend-content');
        legend.append(el('strong', '', layer.label), el('p', 'gv-muted', 'Intensidade relativa na cidade'));
        const values = layer.data.features.map(feature => numeric(feature.properties[layer.surface.field])).filter(value => value !== null);
        const range = el('div', 'gv-range'); range.append(el('span', '', `Mín. ${number.format(values.length ? Math.min(...values) : 0)}`), el('span', '', `Máx. ${number.format(values.length ? Math.max(...values) : 0)}`));
        const gradient = el('div', 'gv-gradient'); gradient.style.background = `linear-gradient(90deg, ${surfaceColors.map((color,index) => `${color} ${index*10}%, ${color} ${(index+1)*10}%`).join(',')})`;
        legend.append(range,gradient,el('div','gv-range','Baixo · Moderado · Alto · Máximo'));
        const control = el('section','gv-altitude-content'); const label = el('label','','Altitude · corte de intensidade');
        const input = el('input',''); input.type='range'; input.min='1'; input.max='100'; input.value='1';
        const output = el('output','','Visível: 1%–100%');
        const set = value => { input.value=value; output.textContent=`Visível: ${value}%–100%`; adapter?.cutoff?.(layer.id,Number(value)); updateSurfaceLegends(current()); };
        input.addEventListener('input',()=>set(input.value));
        label.append(input); control.append(label,output);
        const presets = el('div','gv-presets'); [['Tudo',1],['50%',50],['Pico',100]].forEach(([title,value])=>presets.append(button(title,()=>set(value))));
        control.append(presets,el('p','gv-muted','Altura representa intensidade, não relevo.'));
        const legendPanel = collapsible('gv-surface-legend', layer.label, legend);
        const altitudePanel = collapsible('gv-altitude', 'Altitude e corte', control);
        if (placements.legend.placement === 'intern') addOverlay(legendPanel, placements.legend.position, 30);
        else panel.append(legendPanel);
        addOverlay(altitudePanel, placements.legend.position, 50);
        surfaceControls.set(layer.id, {legend: legendPanel, control: altitudePanel, range, input});
    });
    if (config.layout !== 'embedded') stage.append(toolbar);
    stage.append(canvas, status, overlayRoot);
    aside.append(el('h2', '', 'Explorar'));
    const fields = [];
    if (config.search) {
        const label = el('label', 'gv-field', 'Buscar nos dados');
        const input = el('input', 'gv-input'); input.type = 'search'; input.placeholder = 'Nome, cidade ou atributo';
        input.value = search; searchInput = input;
        input.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { search = input.value; selected = null; syncUrl(); render(); }, 180);
        });
        input.addEventListener('keydown', event => { if (event.key === 'Enter') { clearTimeout(searchTimer); search = input.value; selected = null; recordSearch(search); syncUrl(); render(); } });
        input.addEventListener('change', () => recordSearch(input.value));
        label.append(input); aside.append(label); fields.push(input);
    }
    if (config.geocoder) {
        const form = el('form', 'gv-geocoder');
        const label = el('label', 'gv-field', 'Buscar lugar ou endereço');
        const input = el('input', 'gv-input'); input.type = 'search'; input.required = true; input.minLength = 3; input.maxLength = 200; input.placeholder = 'Cidade, rua ou lugar';
        const submit = el('button', 'gv-button', 'Localizar'); submit.type = 'submit';
        const output = el('div', 'gv-geocode-results'); output.setAttribute('aria-live', 'polite');
        label.append(input); form.append(label, submit, output); aside.append(form);
        form.addEventListener('submit', async event => {
            event.preventDefault(); submit.disabled = true; output.textContent = 'Buscando…';
            try {
                const url = new URL(config.geocoder, window.location.href); url.searchParams.set('q', input.value.trim());
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message ?? 'Não foi possível localizar.');
                output.replaceChildren();
                (payload.results ?? []).forEach(result => output.append(button(result.label, () => {
                    adapter?.fit([{ geometry: { type: 'Point', coordinates: [result.longitude, result.latitude] } }]);
                    root.dispatchEvent(new CustomEvent('geoveil:geocode', { bubbles: true, detail: result }));
                }, 'gv-result')));
                if (!payload.results?.length) output.textContent = 'Nenhum lugar encontrado.';
            } catch (error) { if (!disposed) output.textContent = error.message; }
            finally { submit.disabled = false; }
        });
    }
    config.filters.forEach((filter, index) => {
        const label = el(filter.type === 'date' ? 'fieldset' : 'label', 'gv-field');
        label.append(el(filter.type === 'date' ? 'legend' : 'span', '', filter.label));
        if (filter.type === 'select') {
            const input = el('select', 'gv-input'); input.append(new Option('Todos', ''));
            const options = new Set(config.layers.filter(layer => !filter.layer || layer.id === filter.layer).flatMap(layer => layer.data.features.map(feature => feature.properties[filter.field])).filter(value => value !== null && value !== undefined));
            [...options].sort((a,b) => String(a).localeCompare(String(b), 'pt-BR')).forEach(value => input.append(new Option(String(value), String(value))));
            if (values[index] !== undefined && [...input.options].some(option => option.value === String(values[index]))) input.value = String(values[index]);
            else delete values[index];
            input.addEventListener('change', () => { if (input.value) values[index] = input.value; else delete values[index]; selected = null; syncUrl(); render(); }); label.append(input); fields.push(input);
            filterInputs.set(index, [input]);
        } else {
            const inputs = [];
            for (const [part, caption] of [['from', 'De'], ['to', 'Até (UTC)']]) {
                const sublabel = el('label', '', caption); const input = el('input', 'gv-input'); input.type = 'date';
                input.value = values[index]?.[part] ?? '';
                input.addEventListener('change', () => {
                    const range = { ...values[index], [part]: input.value };
                    if (range[part]) values[index] = range; else delete range[part];
                    if (!range.from && !range.to) delete values[index];
                    selected = null; syncUrl(); render();
                });
                sublabel.append(input); label.append(sublabel); fields.push(input);
                inputs.push(input);
            }
            filterInputs.set(index, inputs);
        }
        aside.append(label);
    });
    const activeFilterChips = el('div', 'gv-filter-chips'); activeFilterChips.setAttribute('aria-label', 'Filtros ativos'); activeFilterChips.setAttribute('aria-live', 'polite');
    aside.append(activeFilterChips);
    aside.append(button('Redefinir filtros', () => { fields.forEach(input => { input.value = ''; }); Object.keys(values).forEach(key => delete values[key]); search = ''; if (searchInput) searchInput.value = ''; selected = null; syncUrl(); render(); }, 'gv-reset'));
    aside.append(el('h2', '', 'Camadas'));
    const embeddedLayerControls = el('aside', 'gv-embedded-layer-controls');
    embeddedLayerControls.setAttribute('aria-label', 'Grupos de camadas');
    const layerToggles = new Map();
    const layerRows = new Map();
    const layerOpacityInputs = new Map();
    const groupControls = new Map();
    config.layers.forEach(layer => {
        let host = aside;
        if (layer.group) {
            let group = groupControls.get(layer.group.id);
            if (!group) {
                const section = el('fieldset', 'gv-layer-group');
                const legend = document.createElement('legend');
                const heading = el('label', 'gv-layer-group-toggle');
                const toggleGroup = document.createElement('input'); toggleGroup.type = 'checkbox'; toggleGroup.checked = true;
                heading.append(toggleGroup, el('span', '', layer.group.label)); legend.append(heading); section.append(legend);
                group = { section, toggle: toggleGroup, label: layer.group.label, layerIds: [], sync: () => {} };
                group.sync = () => {
                    const state = layerVisibility.groupState(layer.group.id);
                    group.toggle.checked = state.checked;
                    group.toggle.indeterminate = state.indeterminate;
                    group.toggle.setAttribute('aria-checked', state.indeterminate ? 'mixed' : String(state.checked));
                };
                toggleGroup.addEventListener('change', () => {
                    layerVisibility.setGroup(layer.group.id, toggleGroup.checked);
                    group.layerIds.forEach(id => {
                        const toggle = layerToggles.get(id);
                        if (!toggle) return;
                        toggle.checked = toggleGroup.checked;
                        root.dispatchEvent(new CustomEvent('geoveil:visibility', { bubbles: true, detail: { layer: id, visible: toggleGroup.checked } }));
                    });
                    group.sync(); selected = null; syncUrl(); render();
                });
                (config.layout === 'embedded' ? embeddedLayerControls : aside).append(section);
                groupControls.set(layer.group.id, group);
            } else if (layer.group.label !== group.label) {
                throw new Error(`O grupo ${layer.group.id} tem nomes inconsistentes.`);
            }
            host = group.section;
            group.layerIds.push(layer.id);
            group.sync();
        }
        const row = el('div', 'gv-layer-row');
        const label = el('label', 'gv-layer'); const toggle = document.createElement('input'); toggle.type = 'checkbox'; toggle.checked = visible.has(layer.id);
        toggle.addEventListener('change', () => {
            layerVisibility.setLayer(layer.id, toggle.checked);
            if (layer.group) groupControls.get(layer.group.id)?.sync();
            root.dispatchEvent(new CustomEvent('geoveil:visibility', { bubbles: true, detail: { layer: layer.id, visible: toggle.checked } }));
            selected = null; syncUrl(); render();
        });
        layerToggles.set(layer.id, toggle);
        const colors = layer.categories?.colors ?? layer.scale?.colors ?? [];
        if (layer.color || colors.length) {
            const swatch = el('span', 'gv-layer-color');
            swatch.style.background = colors.length ? `linear-gradient(90deg, ${colors.join(', ')})` : layer.color;
            label.append(toggle, swatch, el('span', '', layer.label));
        } else {
            label.append(toggle, el('span', '', layer.label));
        }
        const controls = el('div', 'gv-layer-controls');
        const opacityLabel = el('label', 'gv-layer-opacity'); opacityLabel.append(el('span', '', 'Opacidade'));
        const opacity = document.createElement('input'); opacity.type = 'range'; opacity.min = '0'; opacity.max = '1'; opacity.step = '0.05'; opacity.value = String(layerOpacity.get(layer.id)); opacity.setAttribute('aria-label', `Opacidade de ${layer.label}`);
        opacityLabel.append(opacity); controls.append(opacityLabel);
        const reorder = el('span', 'gv-layer-reorder');
        const moveLayer = (direction) => {
            const siblings = [...host.querySelectorAll(':scope > .gv-layer-row')];
            const currentRow = siblings.findIndex(item => item.dataset.layerId === layer.id);
            const targetRow = currentRow + direction;
            if (targetRow < 0 || targetRow >= siblings.length) return;
            const otherId = siblings[targetRow].dataset.layerId;
            const from = layerOrder.indexOf(layer.id), to = layerOrder.indexOf(otherId);
            [layerOrder[from], layerOrder[to]] = [layerOrder[to], layerOrder[from]];
            if (direction < 0) host.insertBefore(row, siblings[targetRow]); else host.insertBefore(siblings[targetRow], row);
            layerOrder.forEach((id, index) => { const target = config.layers.find(item => item.id === id); if (target) target.order = index; });
            adapter?.setLayerOrder?.(layerOrder); syncUrl();
            reorder.querySelectorAll('button').forEach((button, index) => { button.disabled = index === 0 ? targetRow === 0 : targetRow === siblings.length - 1; });
        };
        ['Mover camada para cima', 'Mover camada para baixo'].forEach((description, index) => {
            const button = el('button', 'gv-layer-move', index === 0 ? '↑' : '↓'); button.type = 'button'; button.setAttribute('aria-label', `${description}: ${layer.label}`); button.title = `${description}: ${layer.label}`;
            button.addEventListener('click', () => moveLayer(index === 0 ? -1 : 1)); reorder.append(button);
        });
        controls.append(reorder); row.dataset.layerId = layer.id; row.append(label, controls); host.append(row);
        layerRows.set(layer.id, row); layerOpacityInputs.set(layer.id, opacity);
        opacity.addEventListener('input', () => { layerOpacity.set(layer.id, Number(opacity.value)); adapter?.setLayerOpacity?.(layer.id, Number(opacity.value)); syncUrl(); });
        if (layer.scale) {
            const legend = el('div', 'gv-legend'); legend.append(el('span', 'gv-caption', layer.scale.field));
            layer.scale.colors.forEach((color, index) => {
                const row = el('div', 'gv-legend-row'); const swatch = el('span', 'gv-swatch'); swatch.style.backgroundColor = color;
                const breaks = layer.scale.breaks;
                const text = index === 0 ? `< ${number.format(breaks[0])}` : index === breaks.length ? `≥ ${number.format(breaks[index - 1])}` : `${number.format(breaks[index - 1])} – < ${number.format(breaks[index])}`;
                row.append(swatch, el('span', '', text)); legend.append(row);
            });
            if (placements.legend.placement === 'intern') {
                addOverlay(collapsible('gv-data-legend', layer.label, legend), placements.legend.position, 25);
            } else {
                panel.append(legend);
            }
        }
    });
    if (config.layout === 'embedded' && groupControls.size) addOverlay(embeddedLayerControls, placements.filters.position, 80, 'Camadas');
    panel.append(stats, detail, charts, results);
    const filtersAreInternal = placements.filters.placement === 'intern';
    if (filtersAreInternal) {
        const sidebarContent = el('div', 'gv-filter-content');
        sidebarContent.append(...aside.childNodes);
        aside.replaceChildren(collapsible('gv-filter-details', 'Filtros e camadas', sidebarContent));
        addOverlay(aside.firstElementChild, placements.filters.position, 100);
    }
    if (config.layout === 'embedded') {
        body.append(stage);
        if (!filtersAreInternal) aside.classList.add('gv-external-filters');
    } else if (filtersAreInternal) {
        body.append(stage, panel);
    } else {
        body.append(aside, stage, panel);
    }
    if (navigation) root.append(navigation);
    if (placements.information.placement === 'extern') root.append(header);
    root.append(body);
    if (config.layout === 'embedded' && !filtersAreInternal) root.append(aside);
    if (config.layout === 'embedded' && placements.legend.placement === 'extern' && (config.layers.some(layer => layer.type === 'surface' || layer.scale))) root.append(panel);
    root.append(commandDialog);
    if (savedViewsDialog) root.append(savedViewsDialog);
    function current(selection = null) {
        return config.layers.map(layer => visible.has(layer.id) ? filteredFeatures(layer, config.filters, values, search, selection) : []);
    }
    function select(layer, id, renderedFeature = null) {
        selected = { layer, id }; selectedFeature = renderedFeature;
        if (config.layout === 'map') { panel.hidden = false; const control = [...toolbar.querySelectorAll('button')].find(button => button.textContent === 'Indicadores e detalhes'); control?.setAttribute('aria-expanded', 'true'); }
        render();
        const item = renderedFeature ?? config.layers.find(item => item.id === layer)?.data.features.find(feature => String(feature.id) === String(id));
        if (item && config.fitOnSelect !== false) adapter?.fit([item]);
        root.dispatchEvent(new CustomEvent('geoveil:select', { bubbles: true, detail: { layer, feature: item } }));
    }
    function updateSurfaceLegends(data) {
        config.layers.forEach((layer,index) => {
            const ui = surfaceControls.get(layer.id); if (!ui) return;
            const values = data[index].map(feature => numeric(feature.properties[layer.surface.field])).filter(value => value !== null);
            ui.legend.hidden = ui.control.hidden = !values.length;
            if (!values.length) return;
            const min = Math.min(...values), max = Math.max(...values), percentage = Number(ui.input.value);
            const start = percentage === 1 ? min : percentage === 100 ? max : min + (max-min)*percentage/100;
            ui.range.replaceChildren(el('span','',`Mín. ${number.format(start)}`), el('span','',`Máx. ${number.format(max)}`));
        });
    }
    function openPalette() {
        if (!commandDialog.open) commandDialog.showModal();
        commandInput.value = '';
        renderCommandResults('');
        requestAnimationFrame(() => commandInput.focus());
    }
    function commandResult(label, description, action, query = '') {
        const result = button('', action, 'gv-command-result');
        const title = el('span', 'gv-command-result-title'); appendSearchHighlight(title, label, query);
        result.append(title, el('span', 'gv-command-result-description', description));
        commandResults.append(result);
    }
    function renderCommandResults(query) {
        commandResults.replaceChildren();
        const normalized = query.trim().toLocaleLowerCase('pt-BR');
        if (!normalized) {
            if (searchHistory.length) {
                commandResults.append(el('p', 'gv-command-section-title', 'Buscas recentes'));
                searchHistory.forEach(item => commandResult(item, 'Busca recente', () => {
                    commandInput.value = item; applyGlobalSearch(item); renderCommandResults(item);
                }, item));
                commandResults.append(button('Limpar histórico', () => {
                    searchHistory = []; try { window.localStorage.removeItem(searchHistoryKey); } catch { /* Optional history storage. */ }
                    renderCommandResults('');
                }, 'gv-command-clear-history'));
            } else {
                commandResults.append(el('p', 'gv-muted', 'Digite para buscar recursos e registros do mapa.'));
            }
            return;
        }

        let count = 0;
        for (const item of navigationItems(config.navigation?.items ?? [])) {
            const haystack = `${item.label} ${item.parent} ${item.url}`.toLocaleLowerCase('pt-BR');
            if (!haystack.includes(normalized)) continue;
            commandResult(item.label, item.parent || 'Página', () => {
                recordSearch(query);
                if (item.external) window.open(item.url, '_blank', 'noopener,noreferrer');
                else window.location.assign(item.url);
            }, query);
            if (++count >= 8) break;
        }
        if (count < 12) config.layers.forEach(layer => {
            for (const feature of layer.data.features) {
                if (count >= 12) break;
                const properties = feature.properties ?? {};
                const haystack = Object.values(properties).map(value => String(value ?? '')).join(' ').toLocaleLowerCase('pt-BR');
                if (!haystack.includes(normalized)) continue;
                const titleField = Object.keys(layer.popup ?? {})[0];
                const label = String(properties[titleField] ?? feature.id ?? layer.label);
                commandResult(label, layer.label, () => {
                    recordSearch(query); commandDialog.close(); select(layer.id, feature.id); applyGlobalSearch(query);
                }, query);
                count++;
            }
        });
        if (config.geocoder && normalized.length >= 3) {
            const locate = button(`Localizar “${query.trim()}”`, async () => {
                locate.disabled = true; locate.textContent = 'Localizando…';
                try {
                    const url = new URL(config.geocoder, window.location.href); url.searchParams.set('q', query.trim());
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message ?? 'Não foi possível localizar.');
                    commandResults.replaceChildren();
                    for (const place of payload.results ?? []) commandResult(place.label, 'Lugar ou endereço', () => {
                        recordSearch(query); commandDialog.close();
                        adapter?.fit([{ geometry: { type: 'Point', coordinates: [place.longitude, place.latitude] } }]);
                        root.dispatchEvent(new CustomEvent('geoveil:geocode', { bubbles: true, detail: place }));
                    }, query);
                    if (!payload.results?.length) commandResults.append(el('p', 'gv-muted', 'Nenhum lugar encontrado.'));
                } catch (error) { if (!disposed) commandResults.append(el('p', 'gv-status', error.message)); }
                finally { locate.disabled = false; }
            }, 'gv-command-geocode');
            commandResults.append(locate);
        }
        if (!count) commandResults.append(el('p', 'gv-muted', config.geocoder && normalized.length < 3 ? 'Digite ao menos três caracteres para localizar um endereço.' : 'Nenhum recurso ou registro encontrado.'));
    }
    function applyGlobalSearch(query) {
        search = query.trim();
        if (searchInput) searchInput.value = search;
        clearTimeout(searchTimer); syncUrl(); render(); recordSearch(search);
    }
    let paletteTimer = null;
    commandInput.addEventListener('input', () => {
        clearTimeout(paletteTimer);
        paletteTimer = setTimeout(() => renderCommandResults(commandInput.value), 140);
    });
    commandInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            const firstResult = commandResults.querySelector('button, a');
            if (firstResult) { event.preventDefault(); firstResult.click(); }
        }
    });
    const onGlobalShortcut = event => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault(); openPalette();
        }
    };
    window.addEventListener('keydown', onGlobalShortcut, { signal: controller.signal });
    if (config.urlSync !== false || config.viewUrlSync !== false) window.addEventListener('popstate', () => {
        clearTimeout(searchTimer);
        const params = new URLSearchParams(window.location.search);
        if (config.urlSync !== false) {
            const restored = readQueryState(params, config.filters);
            for (const key of Object.keys(values)) delete values[key];
            Object.assign(values, restored.values);
            search = restored.search;
        }
        if (searchInput) searchInput.value = search;
        config.filters.forEach((filter, index) => {
            const inputs = filterInputs.get(index) ?? [];
            if (filter.type === 'date') {
                inputs[0].value = values[index]?.from ?? '';
                inputs[1].value = values[index]?.to ?? '';
            } else if (inputs[0]) {
                const valid = [...inputs[0].options].some(option => option.value === String(values[index] ?? ''));
                if (!valid) delete values[index];
                inputs[0].value = values[index] ?? '';
            }
        });
        if (config.viewUrlSync !== false) {
            const restoredView = readMapViewState(params, layerIds);
            layerIds.forEach(id => layerVisibility.setLayer(id, !restoredView?.hiddenLayers?.includes(id)));
            if (restoredView?.layerOrder) applyLayerOrder(restoredView.layerOrder);
            if (restoredView?.layerOpacity) for (const [id, amount] of Object.entries(restoredView.layerOpacity)) {
                layerOpacity.set(id, amount);
                if (layerOpacityInputs.has(id)) layerOpacityInputs.get(id).value = String(amount);
                adapter?.setLayerOpacity?.(id, amount);
            }
            layerToggles.forEach((toggle, id) => { toggle.checked = visible.has(id); });
            groupControls.forEach(group => group.sync());
            const fallback = { ...defaultView, hiddenLayers: [] };
            latestView = { ...fallback, ...(restoredView ?? {}), hiddenLayers: restoredView?.hiddenLayers ?? [] };
            adapter?.setViewState?.(latestView);
        }
        selected = null; render();
    }, { signal: controller.signal });
    function renderFilterChips() {
        activeFilterChips.replaceChildren();
        const addChip = (label, remove, accessibleLabel) => {
            const chip = el('span', 'gv-filter-chip'); chip.append(el('span', '', label));
            const close = button('×', remove, 'gv-filter-chip-remove'); close.setAttribute('aria-label', accessibleLabel); chip.append(close);
            activeFilterChips.append(chip);
        };
        if (search.trim()) addChip(`Busca: ${search.trim()}`, () => {
            search = ''; if (searchInput) searchInput.value = ''; syncUrl(); render();
        }, 'Remover busca');
        config.filters.forEach((filter, index) => {
            const value = values[index];
            if (value === undefined) return;
            if (filter.type === 'date') {
                const range = [value.from, value.to].filter(Boolean).join(' → ');
                addChip(`${filter.label}: ${range}`, () => {
                    delete values[index]; filterInputs.get(index)?.forEach(input => { input.value = ''; }); syncUrl(); render();
                }, `Remover filtro ${filter.label}`);
            } else {
                const selectedLabel = filterInputs.get(index)?.[0]?.selectedOptions?.[0]?.textContent ?? String(value);
                addChip(`${filter.label}: ${selectedLabel}`, () => {
                    delete values[index]; if (filterInputs.get(index)?.[0]) filterInputs.get(index)[0].value = ''; syncUrl(); render();
                }, `Remover filtro ${filter.label}`);
            }
        });
        activeFilterChips.hidden = activeFilterChips.childElementCount === 0;
    }
    function render() {
        renderFilterChips();
        const data = current(); adapter?.update(data, visible, { layerOrder, layerOpacity: Object.fromEntries(layerOpacity) }); updateSurfaceLegends(data);
        const scoped = current(selected);
        const count = data.reduce((sum, features) => sum + features.length, 0);
        results.replaceChildren(el('h2', '', `Registros · ${number.format(count)}`));
        detail.replaceChildren(el('h2', '', 'Detalhes'));
        if (selected) {
            const layer = config.layers.find(layer => layer.id === selected.layer);
            const feature = selectedFeature ?? layer.data.features.find(feature => String(feature.id) === String(selected.id));
            const entries = Object.entries(layer.popup);
            if (feature) {
                const dl = el('dl', 'gv-attributes');
                entries.forEach(([field, label]) => {
                    const value = feature.properties[field];
                    dl.append(el('dt', '', label), el('dd', '', value == null ? 'Sem informação' : typeof value === 'boolean' ? (value ? 'Sim' : 'Não') : String(value)));
                });
                detail.append(dl);
                if (layer.recordAction && feature.id != null) {
                    const link = el('a', 'gv-record-action', layer.recordAction.label);
                    link.href = layer.recordAction.url.replace('{id}', encodeURIComponent(String(feature.id)));
                    if (layer.recordAction.modal) link.dataset.geoveilModalLink = '';
                    detail.append(link);
                }
            }
        } else detail.append(el('p', 'gv-muted', 'Selecione um elemento no mapa, gráfico ou lista para consultar seus atributos.'));
        stats.replaceChildren(); charts.replaceChildren();
        config.widgets.forEach(widget => {
            const index = config.layers.findIndex(layer => layer.id === widget.layer);
            const features = scoped[index] ?? [];
            if (widget.type === 'stat') {
                const card = el('article', 'gv-stat'); card.append(el('span', '', widget.label), el('strong', '', number.format(aggregate(widget, features)))); stats.append(card);
            } else if (widget.type === 'line') {
                const card = el('article', 'gv-chart'); card.append(el('h2', '', widget.label));
                const points = timeSeries(widget, features);
                if (!points.length) card.append(el('p', 'gv-muted', 'Sem medições para os filtros atuais.'));
                else {
                    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    svg.setAttribute('viewBox', '0 0 260 140'); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', widget.label);
                    const lo = Math.min(...points.map(point => point.value)); const hi = Math.max(...points.map(point => point.value));
                    const start = points[0].time, end = points.at(-1).time;
                    const positions = points.map(point => [10 + 240 * (point.time - start) / (end - start || 1), 120 - 100 * (point.value - lo) / (hi - lo || 1)]);
                    const path = document.createElementNS(svg.namespaceURI, 'polyline');
                    path.setAttribute('points', positions.map(point => point.join(',')).join(' ')); path.setAttribute('fill', 'none'); path.setAttribute('stroke', '#0f766e'); path.setAttribute('stroke-width', '2'); svg.append(path);
                    positions.forEach(([x,y], index) => {
                        const dot = document.createElementNS(svg.namespaceURI, 'circle'); dot.setAttribute('cx', x); dot.setAttribute('cy', y); dot.setAttribute('r', '3'); dot.setAttribute('fill', '#0f766e');
                        const title = document.createElementNS(svg.namespaceURI, 'title'); title.textContent = `${new Date(points[index].time).toISOString()}: ${number.format(points[index].value)}`; dot.append(title); svg.append(dot);
                    });
                    card.append(svg, el('p', 'gv-muted', `${new Date(start).toLocaleDateString('pt-BR', { timeZone: 'UTC' })} – ${new Date(end).toLocaleDateString('pt-BR', { timeZone: 'UTC' })} · ${number.format(lo)} a ${number.format(hi)}`));
                    const list = el('details', 'gv-series-data'); list.append(el('summary', '', 'Consultar medições (UTC)'));
                    points.slice(0, 50).forEach(point => list.append(button(`${new Date(point.time).toISOString()} · ${number.format(point.value)}`, () => select(widget.layer, point.feature.id), 'gv-result')));
                    if (points.length > 50) list.append(el('p', 'gv-muted', 'Lista limitada às primeiras 50 medições. O gráfico inclui todas.'));
                    card.append(list);
                }
                charts.append(card);
            } else {
                const card = el('article', 'gv-chart'); card.append(el('h2', '', widget.label));
                const rows = [...(data[index] ?? [])].filter(feature => numeric(feature.properties[widget.field]) !== null).sort((a,b) => Number(b.properties[widget.field]) - Number(a.properties[widget.field]));
                const max = Math.max(1, ...rows.map(feature => Math.abs(Number(feature.properties[widget.field]))));
                rows.slice(0, 12).forEach(feature => {
                    const row = button('', () => select(widget.layer, feature.id), 'gv-bar-row');
                    row.setAttribute('aria-pressed', String(selected?.layer === widget.layer && String(selected.id) === String(feature.id)));
                    const value = Number(feature.properties[widget.field]);
                    row.append(el('span', 'gv-bar-label', String(feature.properties[widget.labelField] ?? feature.id)), el('strong', '', number.format(value)));
                    const track = el('span', 'gv-bar-track'); const bar = el('span', 'gv-bar'); bar.style.width = `${Math.abs(value) / max * 100}%`; track.append(bar); row.append(track); card.append(row);
                });
                if (!rows.length) card.append(el('p', 'gv-muted', 'Sem dados para os filtros atuais.'));
                if (rows.length > 12) card.append(el('p', 'gv-muted', 'Exibindo os 12 maiores valores.'));
                charts.append(card);
            }
        });
        let shown = 0;
        config.layers.forEach((layer, index) => data[index].forEach(feature => {
            if (shown++ >= 50) return;
            const first = Object.keys(layer.popup)[0];
            const result = button('', () => select(layer.id, feature.id), 'gv-result');
            appendSearchHighlight(result, feature.properties[first] ?? feature.id, search);
            results.append(result);
        }));
        if (!count) results.append(el('p', 'gv-muted', 'Nenhum registro encontrado.'));
        else if (count > 50) results.append(el('p', 'gv-muted', 'Exibindo os primeiros 50. Refine os filtros.'));
    }
    function applyLayerOrder(order) {
        layerOrder = [...order];
        const parents = new Set([...layerRows.values()].map(row => row.parentElement));
        for (const parent of parents) {
            const rows = [...parent.querySelectorAll(':scope > .gv-layer-row')].sort((a, b) => layerOrder.indexOf(a.dataset.layerId) - layerOrder.indexOf(b.dataset.layerId));
            rows.forEach(row => parent.append(row));
        }
        config.layers.forEach(layer => { layer.order = layerOrder.indexOf(layer.id); });
        adapter?.setLayerOrder?.(layerOrder);
    }
    const instance = { setLayerVisible(id, enabled) {
        const toggle = layerToggles.get(id);
        if (!toggle) throw new Error(`Camada desconhecida: ${id}`);
        toggle.checked = Boolean(enabled);
        toggle.dispatchEvent(new Event('change'));
    }, getViewState() {
        const camera = adapter?.getViewState?.() ?? latestView;
        return normalizeMapViewState({ ...camera, hiddenLayers: layerIds.filter(id => !visible.has(id)), layerOrder, layerOpacity: Object.fromEntries(layerOpacity) }, layerIds);
    }, setViewState(state) {
        const normalized = normalizeMapViewState(state, layerIds);
        if (!normalized) throw new TypeError('A vista compartilhada não contém estado válido.');
        layerIds.forEach(id => layerVisibility.setLayer(id, !normalized.hiddenLayers?.includes(id)));
        if (normalized.layerOrder) applyLayerOrder(normalized.layerOrder);
        if (normalized.layerOpacity) for (const [id, amount] of Object.entries(normalized.layerOpacity)) layerOpacity.set(id, amount);
        layerOpacity.forEach((amount, id) => { if (layerOpacityInputs.has(id)) layerOpacityInputs.get(id).value = String(amount); adapter?.setLayerOpacity?.(id, amount); });
        layerToggles.forEach((toggle, id) => { toggle.checked = visible.has(id); });
        groupControls.forEach(group => group.sync());
        latestView = { ...defaultView, ...normalized, hiddenLayers: normalized.hiddenLayers ?? [] };
        adapter?.setViewState?.(latestView);
        syncUrl(); render();
    }, getShareUrl() {
        syncUrl(); return window.location.href;
    }, getSavedViewDefinition() {
        return { version: 1, view: this.getViewState(), filters: structuredClone(values), search };
    }, applySavedViewDefinition(definition) {
        if (!definition || typeof definition !== 'object' || Array.isArray(definition)) throw new TypeError('A definição da vista salva é inválida.');
        if (definition.view) this.setViewState(definition.view);
        for (const key of Object.keys(values)) delete values[key];
        for (const [rawIndex, value] of Object.entries(definition.filters ?? {})) {
            const index = Number(rawIndex), filter = config.filters[index], inputs = filterInputs.get(index) ?? [];
            if (!filter || !inputs.length) continue;
            if (filter.type === 'date') {
                const validDate = date => {
                    if (typeof date !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return false;
                    const parsed = new Date(`${date}T00:00:00Z`);
                    return Number.isFinite(parsed.getTime()) && parsed.toISOString().slice(0, 10) === date;
                };
                const range = { ...(validDate(value?.from) ? { from: value.from } : {}), ...(validDate(value?.to) ? { to: value.to } : {}) };
                if (range.from || range.to) values[index] = range;
            } else if ([...inputs[0].options].some(option => option.value === String(value))) values[index] = String(value);
        }
        config.filters.forEach((filter, index) => {
            const inputs = filterInputs.get(index) ?? [];
            if (filter.type === 'date') {
                inputs[0].value = values[index]?.from ?? '';
                inputs[1].value = values[index]?.to ?? '';
            } else if (inputs[0]) inputs[0].value = values[index] ?? '';
        });
        search = typeof definition.search === 'string' ? definition.search.slice(0, 500) : '';
        if (searchInput) searchInput.value = search;
        selected = null; selectedFeature = null; syncUrl(); render();
    }, getLayerFeatures(id) {
        const layer = config.layers.find(item => item.id === id);
        if (!layer) throw new Error(`Camada desconhecida: ${id}`);
        return layer.data.features.map(feature => structuredClone(feature));
    }, updateLayer(id, features) {
        const layer = config.layers.find(item => item.id === id);
        if (!layer) throw new Error(`Camada desconhecida: ${id}`);
        if (!Array.isArray(features)) throw new TypeError('As feições da camada devem ser uma lista.');
        layer.data.features = features;
        if (selected?.layer === id && !features.some(feature => String(feature.id) === String(selected.id))) selected = null;
        render();
    }, setTheme(theme) {
        if (!['dark', 'light', 'auto'].includes(theme)) throw new Error('Tema inválido.');
        themePreference = theme;
        root.dataset.themeMode = theme;
        config.theme = resolveTheme(); root.dataset.theme = config.theme; adapter?.theme?.(config.theme);
    }, destroy() {
        disposed = true; controller.abort(); adapter?.destroy();
        overlayLayoutObserver.disconnect();
        overlayContentObserver.disconnect();
        if (overlayFrame !== null) cancelAnimationFrame(overlayFrame);
        if (benchmarkIdleId !== null) window.cancelIdleCallback?.(benchmarkIdleId);
        if (benchmarkTimer !== null) clearTimeout(benchmarkTimer);
        if (colorScheme) colorScheme.removeEventListener?.('change', syncSystemTheme);
        if (faviconLink?.href === appliedFavicon) {
            if (createdFavicon) faviconLink.remove();
            else faviconLink.href = originalFavicon;
        }
        for (const [property, [value, priority]] of previousTokenStyles) {
            if (value) root.style.setProperty(property, value, priority);
            else root.style.removeProperty(property);
        }
        root.replaceChildren(); delete root.geoveil;
    } };
    function savedViewsUrl(id = null) {
        const url = new URL(config.savedViewsEndpoint, window.location.href);
        if (url.origin !== window.location.origin) throw new Error('O endpoint de vistas salvas precisa pertencer a esta aplicação.');
        if (id !== null) {
            if (!['string', 'number'].includes(typeof id) || String(id) === '') throw new Error('Identificador de vista inválido.');
            url.pathname = `${url.pathname.replace(/\/+$/, '')}/${encodeURIComponent(String(id))}`;
        }
        return url;
    }
    async function requestSavedViews(method = 'GET', id = null, body = null) {
        const headers = { Accept: 'application/json' };
        const options = { method, credentials: 'same-origin', redirect: 'error', headers };
        if (body !== null) {
            headers['Content-Type'] = 'application/json';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrf) headers['X-CSRF-TOKEN'] = csrf;
            options.body = JSON.stringify(body);
        }
        const response = await fetch(savedViewsUrl(id), options);
        let payload = null;
        if (response.headers.get('content-type')?.includes('application/json')) payload = await response.json();
        if (!response.ok) throw new Error(payload?.message ?? `Falha ao acessar vistas salvas (HTTP ${response.status}).`);
        return payload;
    }
    function renderSavedViews(views) {
        savedViewsList.replaceChildren();
        if (!views.length) savedViewsList.append(el('p', 'gv-muted', 'Nenhuma vista salva para este mapa.'));
        for (const view of views) {
            if (!view || (typeof view.id !== 'string' && typeof view.id !== 'number')) continue;
            const row = el('div', 'gv-saved-view-row');
            const open = button(String(view.name ?? 'Vista sem nome'), () => {
                try { instance.applySavedViewDefinition(view.definition); savedViewsDialog.close(); }
                catch (error) { savedViewsStatus.textContent = error.message; }
            }, 'gv-saved-view-open');
            row.append(open);
            if (view.is_shared) row.append(el('span', 'gv-saved-view-badge', 'Compartilhada'));
            if (view.can_manage === true) {
                row.append(button('Excluir', async () => {
                    if (!window.confirm(`Excluir a vista “${String(view.name ?? '')}”?`)) return;
                    try { await requestSavedViews('DELETE', view.id); await loadSavedViews(); }
                    catch (error) { savedViewsStatus.textContent = error.message; }
                }, 'gv-saved-view-delete'));
            }
            savedViewsList.append(row);
        }
    }
    async function loadSavedViews() {
        savedViewsStatus.textContent = 'Carregando vistas…';
        try {
            const payload = await requestSavedViews();
            const views = Array.isArray(payload?.views) ? payload.views : [];
            renderSavedViews(views);
            savedViewsStatus.textContent = '';
        } catch (error) { savedViewsStatus.textContent = error.message; }
    }
    function openSavedViews() {
        if (!savedViewsDialog) return;
        if (!savedViewsDialog.open) savedViewsDialog.showModal();
        void loadSavedViews();
    }
    async function saveCurrentView() {
        if (!savedViewName?.value.trim()) {
            savedViewsStatus.textContent = 'Informe um nome para salvar esta vista.';
            savedViewName?.focus();
            return;
        }
        savedViewsStatus.textContent = 'Salvando vista…';
        try {
            await requestSavedViews('POST', null, {
                name: savedViewName.value.trim(),
                definition: instance.getSavedViewDefinition(),
                is_shared: Boolean(savedViewShared.checked),
            });
            savedViewsStatus.textContent = 'Vista salva.';
            await loadSavedViews();
        } catch (error) { savedViewsStatus.textContent = error.message; }
    }
    function syncSystemTheme() {
        if (themePreference !== 'auto') return;
        config.theme = resolveTheme(); root.dataset.theme = config.theme; adapter?.theme?.(config.theme);
    }
    if (colorScheme) colorScheme.addEventListener?.('change', syncSystemTheme);
    root.geoveil = instance;
    window.addEventListener('pagehide', event => { if (!event.persisted) instance.destroy(); }, { once: true, signal: controller.signal });
    render();
    try {
        let engineDiagnostics = null;
        if (config.plugin.selection?.strategy === 'best') {
            engineDiagnostics = chooseCachedEngine(config);
            config.plugin = { ...engineDiagnostics.engine, selection: config.plugin.selection };
            engineLabel.textContent = ({ maplibre: 'MapLibre', leaflet: 'Leaflet' })[config.plugin.id] ?? config.plugin.id;
        }
        const factory = engines.get(config.plugin.id);
        if (!factory) throw new Error(`Adaptador não registrado para o engine: ${config.plugin.id}`);
        adapter = validateEngineAdapter(await factory(canvas, config, select, message => { status.textContent = message; }, layers => {
            for (const [id, features] of Object.entries(layers)) {
                const layer = config.layers.find(item => item.id === id);
                if (layer) layer.data.features = features;
            }
            if (!disposed) render();
        }), config.plugin.id, config);
        if (disposed) { adapter.destroy(); return instance; }
        adapter.onViewChange?.(camera => {
            latestView = normalizeMapViewState({ ...camera, hiddenLayers: layerIds.filter(id => !visible.has(id)), layerOrder, layerOpacity: Object.fromEntries(layerOpacity) }, layerIds) ?? latestView;
            syncUrl();
        });
        defaultView = adapter.getViewState?.() ?? defaultView;
        latestView = { ...defaultView, ...(sharedView ?? {}), hiddenLayers: layerIds.filter(id => !visible.has(id)) };
        if (selectionTool) adapter.setSelectionTool?.(selectionTool, handleAreaSelection);
        if (config.debug && engineDiagnostics) {
            const metrics = engineDiagnostics.metrics[config.plugin.id];
            status.textContent = metrics
                ? `Engine ${config.plugin.id} • ${metrics.loadMs.toFixed(0)} ms para iniciar • ${metrics.fps.toFixed(0)} FPS na amostra${metrics.memoryBytes === null ? '' : ` • ${(metrics.memoryBytes / 1048576).toFixed(1)} MB`}`
                : `Engine ${config.plugin.id} • fallback compatível${engineDiagnostics.needsBenchmark ? '; medição em segundo plano' : '; benchmark indisponível'}`;
        } else status.textContent = '';
        adapter.theme?.(config.theme);
        for (const [id, ui] of surfaceControls) adapter.cutoff?.(id, Number(ui.input.value));
        render();
        if (config.fitOnLoad && !sharedView) adapter.fit(current().flat());
        if (engineDiagnostics?.needsBenchmark) {
            const startBenchmark = async () => {
                if (disposed) return;
                const metrics = await collectEngineBenchmarks(config, engineDiagnostics, () => disposed);
                if (!disposed && config.debug && Object.keys(metrics).length) {
                    status.textContent = hasCompleteBenchmark(engineDiagnostics.candidates, metrics)
                        ? `Benchmark salvo para ${Object.keys(metrics).join(', ')}; será usado na próxima abertura.`
                        : `Benchmark incompleto (${Object.keys(metrics).join(', ')}); mantendo o engine compatível de fallback.`;
                }
            };
            const scheduleBenchmark = () => {
                if (disposed) return;
                if (window.requestIdleCallback) benchmarkIdleId = window.requestIdleCallback(() => { void startBenchmark(); }, { timeout: 12000 });
                else benchmarkTimer = window.setTimeout(() => { void startBenchmark(); }, 1500);
            };
            Promise.resolve(adapter.ready).then(scheduleBenchmark);
        }
    } catch (error) { status.textContent = `Não foi possível iniciar o mapa: ${error.message}`; }
    return instance;
}
export function boot(scope = document, options = {}) {
    mountBulkActions(scope);
    return Promise.all([...scope.querySelectorAll('[data-geoveil]')].filter(root => !root.geoveil).map(root => {
        const config = JSON.parse(root.querySelector('[data-geoveil-config]').textContent);
        if (options.theme) config.theme = options.theme;
        return mount(root, config);
    }));
}
