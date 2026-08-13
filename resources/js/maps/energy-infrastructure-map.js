import { createMap, registerLivewireMap } from './maplibre/base-map';
import { addMartinSources, bindLayerPopup, filterLayersByDistrict, label, popupFromRows } from './maplibre/infrastructure-map';
import { featureCollection, point, feature } from '@turf/helpers';

let controller;

function geometry(value) {
    if (typeof value === 'string') { try { value = JSON.parse(value); } catch { return null; } }
    if (value?.type === 'Feature') return value.geometry;
    return value?.type && value?.coordinates ? value : null;
}

function features(payload) {
    const result = [];
    for (const item of payload.postes ?? []) result.push(point([Number(item.longitude), Number(item.latitude)], { ...item, kind: 'poste' }));
    for (const item of payload.centrais ?? []) {
        const geom = geometry(item.geojson) ?? (item.longitude && item.latitude ? { type: 'Point', coordinates: [Number(item.longitude), Number(item.latitude)] } : null);
        if (geom) result.push(feature(geom, { ...item, kind: 'central' }));
    }
    for (const item of payload.equipamentos ?? []) {
        if (!['transformador', 'medidor'].includes(String(item.tipo ?? '').toLowerCase()) || !item.longitude || !item.latitude) continue;
        result.push(point([Number(item.longitude), Number(item.latitude)], { ...item, kind: 'equipamento' }));
    }
    for (const item of payload.cabeamentos ?? []) {
        if (!String(item.tipo_cabo ?? '').startsWith('eletrico_')) continue;
        const geom = geometry(item.geojson); if (geom) result.push(feature(geom, { ...item, kind: 'cabeamento' }));
    }
    return featureCollection(result);
}

function popupContent(properties) {
    const rows = properties.kind === 'cabeamento' ? [
        ['Identificador', properties.codigo_patrimonio || `Cabo ${properties.id}`],
        ['Tipo', label(properties.tipo_cabo)], ['Capacidade', label(properties.capacidade)],
        ['Revestimento', label(properties.revestimento)], ['Extensão', properties.extensao_metros ? `${properties.extensao_metros} m` : null],
        ['Instalação', properties.subterraneo === true || properties.subterraneo === 'true' || properties.subterraneo === 1 ? 'Subterrânea' : 'Aérea'],
        ['Origem', properties.poste_origem_id ? `Poste ${properties.poste_origem_id}` : (properties.central_origem_id ? `Central ${properties.central_origem_id}` : null)],
        ['Destino', properties.poste_destino_id ? `Poste ${properties.poste_destino_id}` : (properties.central_destino_id ? `Central ${properties.central_destino_id}` : null)],
        ['Estado', label(properties.estado_conservacao)],
    ] : [
        ['Identificador', properties.codigo_patrimonio || `${label(properties.kind)} ${properties.id}`],
        ['Tipo', label(properties.tipo || properties.kind)], ['Material', properties.material ? label(properties.material) : null],
        ['Altura', properties.altura_metros ? `${properties.altura_metros} m` : null], ['Modelo', properties.modelo],
        ['Estado', label(properties.estado_conservacao)],
    ];
    return popupFromRows(properties.nome || properties.codigo_patrimonio || ({ poste: 'Poste', cabeamento: 'Trecho de cabo', equipamento: 'Equipamento', central: 'Central' }[properties.kind] ?? 'Ativo elétrico'), rows);
}

class EnergyInfrastructureMap {
    constructor(container) {
        this.container = container;
        this.map = createMap(container, { center: [-57.6875, -16.07083], zoom: 12 });
        this.onDistrict = (event) => this.load(event.detail?.distrito_id ?? event.detail?.[0]?.distrito_id ?? '');
        window.addEventListener('update-energy-infra-map', this.onDistrict);
        this.map.on('load', () => { this.ready = true; this.setup(); this.load(container.dataset.district ?? ''); });
    }
    setup() {
        const baseUrl = this.container.dataset.martinBaseUrl || '/tiles';
        const sources = { cables: 'energia_cabeamentos', poles: 'energia_postes', equipment: 'energia_equipamentos', stations: 'energia_centrais', poles3d: 'energia_postes_3d', equipment3d: 'energia_equipamentos_3d' };
        addMartinSources(this.map, baseUrl, 'energy', sources);
        this.map.addLayer({ id: 'energy-cables', type: 'line', source: 'energy-cables', 'source-layer': sources.cables, paint: {
            'line-color': ['match', ['get', 'tipo_cabo'], 'eletrico_alta_tensao', '#ef4444', '#f59e0b'], 'line-width': ['interpolate', ['linear'], ['zoom'], 11, 2, 17, 6], 'line-opacity': .9,
        }});
        const pointLayers = [
            ['energy-poles', 'energy-poles', sources.poles, 3, '#9ca3af', 'poste'],
            ['energy-equipment', 'energy-equipment', sources.equipment, 5, '#f59e0b', 'equipamento'],
            ['energy-stations', 'energy-stations', sources.stations, 8, '#dc2626', 'central'],
        ];
        for (const [id, source, sourceLayer, radius, color, kind] of pointLayers) this.map.addLayer({ id, type: 'circle', source, 'source-layer': sourceLayer, paint: {
            'circle-radius': ['interpolate', ['linear'], ['zoom'], 10, Math.max(2, radius - 2), 17, radius + 2], 'circle-color': color, 'circle-stroke-color': '#fff', 'circle-stroke-width': 1,
        }, metadata: { energyKind: kind } });
        this.map.addLayer({ id:'energy-poles-3d', type:'fill-extrusion', source:'energy-poles3d', 'source-layer':sources.poles3d, minzoom:15, paint:{'fill-extrusion-color':'#64748b','fill-extrusion-height':['get','render_height'],'fill-extrusion-base':0,'fill-extrusion-opacity':.95}});
        this.map.addLayer({ id:'energy-equipment-3d', type:'fill-extrusion', source:'energy-equipment3d', 'source-layer':sources.equipment3d, minzoom:15, paint:{'fill-extrusion-color':['match',['downcase',['get','tipo']],'transformador','#f59e0b','#0ea5e9'],'fill-extrusion-height':['get','render_height'],'fill-extrusion-base':0,'fill-extrusion-opacity':.96}});
        bindLayerPopup(this.map, Object.fromEntries(['energy-cables', 'energy-poles', 'energy-equipment', 'energy-stations', 'energy-poles-3d', 'energy-equipment-3d'].map(layer => [layer, layer])), (p, kind, event) => {
            p.kind = event.features?.[0]?.layer?.metadata?.energyKind ?? (event.features?.[0]?.layer?.id === 'energy-cables' ? 'cabeamento' : p.kind);
            return popupContent(p);
        });
        this.applyDistrictFilter(this.container.dataset.district || '');
    }
    async load(district = '') {
        if (!this.ready) return;
        this.applyDistrictFilter(district);
        const status = this.container.parentElement?.querySelector('[data-energy-status]'); if (status) status.remove();
        if (!district) this.map.easeTo({ center: [-57.6875, -16.07083], zoom: 12, duration: 500 });
    }
    applyDistrictFilter(district) {
        filterLayersByDistrict(this.map, ['energy-cables', 'energy-poles', 'energy-equipment', 'energy-stations', 'energy-poles-3d', 'energy-equipment-3d'], district);
    }
    destroy() { window.removeEventListener('update-energy-infra-map', this.onDistrict); this.map.remove(); }
}

registerLivewireMap(() => { const el = document.getElementById('energy-infrastructure-map'); if (!el) return; controller?.destroy(); controller = new EnergyInfrastructureMap(el); }, () => { controller?.destroy(); controller = null; });
