import { createMap, maplibregl, martinTilejsonUrl, registerLivewireMap } from './maplibre/base-map';

let controller;
function label(value, fallback = 'Não informado') { return value === null || value === undefined || value === '' ? fallback : String(value).replaceAll('_', ' '); }
function popupContent(properties, kind) {
    const root = document.createElement('div'); root.className = 'min-w-52 space-y-1 text-sm';
    const title = document.createElement('strong'); title.className = 'block border-b border-gray-200 pb-1 text-base'; title.textContent = properties.nome || properties.codigo_patrimonio || ({ cable: 'Trecho de telecomunicações', antenna: 'Antena', equipment: 'Equipamento de rede' }[kind]); root.appendChild(title);
    const rows = kind === 'cable' ? [['Tipo', label(properties.tipo_cabo)], ['Capacidade', properties.capacidade], ['Instalação', String(properties.subterraneo) === 'true' || Number(properties.subterraneo) === 1 ? 'Subterrânea' : 'Aérea'], ['Extensão', properties.extensao_metros ? `${properties.extensao_metros} m` : null], ['Estado', label(properties.estado_conservacao)]] : kind === 'antenna' ? [['Sinal', properties.tipo_sinal], ['Frequência', properties.frequencia_mhz ? `${properties.frequencia_mhz} MHz` : null], ['Alcance', properties.alcance_metros ? `${properties.alcance_metros} m` : null], ['Potência', properties.potencia_dbm ? `${properties.potencia_dbm} dBm` : null], ['Proprietário', properties.proprietario], ['Estado', label(properties.estado_conservacao)]] : [['Tipo', label(properties.tipo)], ['Modelo', properties.modelo], ['Estado', label(properties.estado_conservacao)]];
    for (const [name, value] of rows) { if (value === null || value === undefined || value === '') continue; const row = document.createElement('div'); row.className = 'flex justify-between gap-4'; const key = document.createElement('span'); key.className = 'text-gray-500'; key.textContent = name; const content = document.createElement('span'); content.className = 'text-right font-medium'; content.textContent = String(value); row.append(key, content); root.appendChild(row); }
    return root;
}
class InternetInfrastructureMap {
    constructor(container) { this.container = container; this.map = createMap(container,{center:[-57.6875,-16.07083],zoom:12}); this.onDistrict = e => this.applyDistrictFilter(e.detail?.distrito_id ?? e.detail?.[0]?.distrito_id ?? ''); window.addEventListener('update-internet-infra-map',this.onDistrict); this.map.on('load',()=>this.setup()); }
    setup() {
        const base = this.container.dataset.martinBaseUrl || '/tiles'; const sources={cables:'internet_cabeamentos',antennas:'internet_antenas',equipment:'internet_equipamentos'};
        Object.entries(sources).forEach(([key,name])=>this.map.addSource(`internet-${key}`,{type:'vector',url:martinTilejsonUrl(base,name)}));
        this.map.addLayer({id:'internet-cables',type:'line',source:'internet-cables','source-layer':sources.cables,paint:{'line-color':['match',['get','tipo_cabo'],'fibra_backbone','#1d4ed8','cobre_telefonia','#ea580c','#22d3ee'],'line-width':['interpolate',['linear'],['zoom'],10,2,17,6],'line-opacity':.92}});
        this.map.addLayer({id:'internet-antennas',type:'circle',source:'internet-antennas','source-layer':sources.antennas,paint:{'circle-radius':['interpolate',['linear'],['zoom'],10,4,17,8],'circle-color':'#d946ef','circle-stroke-color':'#fff','circle-stroke-width':1.5}});
        this.map.addLayer({id:'internet-equipment',type:'circle',source:'internet-equipment','source-layer':sources.equipment,paint:{'circle-radius':['interpolate',['linear'],['zoom'],10,3,17,7],'circle-color':'#0891b2','circle-stroke-color':'#fff','circle-stroke-width':1.5}});
        const kinds={'internet-cables':'cable','internet-antennas':'antenna','internet-equipment':'equipment'}; Object.entries(kinds).forEach(([layer,kind])=>{this.map.on('click',layer,e=>new maplibregl.Popup({maxWidth:'340px'}).setLngLat(e.lngLat).setDOMContent(popupContent(e.features?.[0]?.properties??{},kind)).addTo(this.map));this.map.on('mouseenter',layer,()=>this.map.getCanvas().style.cursor='pointer');this.map.on('mouseleave',layer,()=>this.map.getCanvas().style.cursor='');});
        this.applyDistrictFilter(this.container.dataset.district || ''); this.container.parentElement?.querySelector('[data-internet-status]')?.remove();
    }
    applyDistrictFilter(district) { const filter=district?['==',['get','distrito_id'],Number(district)]:null; ['internet-cables','internet-antennas','internet-equipment'].forEach(layer=>this.map.getLayer(layer)&&this.map.setFilter(layer,filter)); if(!district)this.map.easeTo({center:[-57.6875,-16.07083],zoom:12,duration:500}); }
    destroy(){window.removeEventListener('update-internet-infra-map',this.onDistrict);this.map.remove();}
}
registerLivewireMap(()=>{const el=document.getElementById('internet-infrastructure-map');if(!el)return;controller?.destroy();controller=new InternetInfrastructureMap(el);},()=>{controller?.destroy();controller=null;});
