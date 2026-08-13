import { createMap, registerLivewireMap } from './maplibre/base-map';
import { addMartinSources, bindLayerPopup, filterLayersByDistrict, label, popupFromRows } from './maplibre/infrastructure-map';

let controller;
function popupContent(properties, kind) {
    const rows = kind === 'cable' ? [['Tipo', label(properties.tipo_cabo)], ['Capacidade', properties.capacidade], ['Instalação', String(properties.subterraneo) === 'true' || Number(properties.subterraneo) === 1 ? 'Subterrânea' : 'Aérea'], ['Extensão', properties.extensao_metros ? `${properties.extensao_metros} m` : null], ['Estado', label(properties.estado_conservacao)]] : kind === 'antenna' ? [['Sinal', properties.tipo_sinal], ['Frequência', properties.frequencia_mhz ? `${properties.frequencia_mhz} MHz` : null], ['Alcance', properties.alcance_metros ? `${properties.alcance_metros} m` : null], ['Potência', properties.potencia_dbm ? `${properties.potencia_dbm} dBm` : null], ['Proprietário', properties.proprietario], ['Estado', label(properties.estado_conservacao)]] : [['Tipo', label(properties.tipo)], ['Modelo', properties.modelo], ['Estado', label(properties.estado_conservacao)]];
    return popupFromRows(properties.nome || properties.codigo_patrimonio || ({ cable: 'Trecho de telecomunicações', antenna: 'Antena', equipment: 'Equipamento de rede' }[kind]), rows);
}
class InternetInfrastructureMap {
    constructor(container) { this.container = container; this.map = createMap(container,{center:[-57.6875,-16.07083],zoom:12}); this.onDistrict = e => this.applyDistrictFilter(e.detail?.distrito_id ?? e.detail?.[0]?.distrito_id ?? ''); window.addEventListener('update-internet-infra-map',this.onDistrict); this.map.on('load',()=>this.setup()); }
    setup() {
        const base = this.container.dataset.martinBaseUrl || '/tiles'; const sources={cables:'internet_cabeamentos',antennas:'internet_antenas',equipment:'internet_equipamentos',antennas3d:'internet_antenas_3d'};
        addMartinSources(this.map, base, 'internet', sources);
        this.map.addLayer({id:'internet-cables',type:'line',source:'internet-cables','source-layer':sources.cables,paint:{'line-color':['match',['get','tipo_cabo'],'fibra_backbone','#1d4ed8','cobre_telefonia','#ea580c','#22d3ee'],'line-width':['interpolate',['linear'],['zoom'],10,2,17,6],'line-opacity':.92}});
        this.map.addLayer({id:'internet-antennas',type:'circle',source:'internet-antennas','source-layer':sources.antennas,paint:{'circle-radius':['interpolate',['linear'],['zoom'],10,4,17,8],'circle-color':'#d946ef','circle-stroke-color':'#fff','circle-stroke-width':1.5}});
        this.map.addLayer({id:'internet-equipment',type:'circle',source:'internet-equipment','source-layer':sources.equipment,paint:{'circle-radius':['interpolate',['linear'],['zoom'],10,3,17,7],'circle-color':'#0891b2','circle-stroke-color':'#fff','circle-stroke-width':1.5}});
        this.map.addLayer({id:'internet-antennas-3d',type:'fill-extrusion',source:'internet-antennas3d','source-layer':sources.antennas3d,minzoom:14,paint:{'fill-extrusion-color':'#d946ef','fill-extrusion-height':['get','render_height'],'fill-extrusion-base':0,'fill-extrusion-opacity':.95}});
        bindLayerPopup(this.map, {'internet-cables':'cable','internet-antennas':'antenna','internet-equipment':'equipment'}, popupContent);
        this.applyDistrictFilter(this.container.dataset.district || ''); this.container.parentElement?.querySelector('[data-internet-status]')?.remove();
    }
    applyDistrictFilter(district) { filterLayersByDistrict(this.map, ['internet-cables','internet-antennas','internet-equipment','internet-antennas-3d'], district); if(!district)this.map.easeTo({center:[-57.6875,-16.07083],zoom:12,duration:500}); }
    destroy(){window.removeEventListener('update-internet-infra-map',this.onDistrict);this.map.remove();}
}
registerLivewireMap(()=>{const el=document.getElementById('internet-infrastructure-map');if(!el)return;controller?.destroy();controller=new InternetInfrastructureMap(el);},()=>{controller?.destroy();controller=null;});
