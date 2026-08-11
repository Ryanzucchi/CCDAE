let stylesRegistered = false;

function registerStyles() {
    if (stylesRegistered) return;
    stylesRegistered = true;
    const style = document.createElement('style');
    style.textContent = `
        .ccdae-map-legend{width:238px;margin:10px!important;padding:12px!important;border:1px solid rgba(148,163,184,.35);border-radius:10px!important;background:rgba(255,255,255,.94)!important;color:#0f172a;box-shadow:0 6px 18px rgba(15,23,42,.14)!important;font:12px/1.35 Inter,ui-sans-serif,system-ui,sans-serif;backdrop-filter:blur(10px)}
        .dark .ccdae-map-legend{border-color:rgba(255,255,255,.12);background:rgba(17,24,39,.94)!important;color:#f8fafc}
        .ccdae-map-legend__header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}.ccdae-map-legend__title{display:block;font-size:13px;font-weight:700}.ccdae-map-legend__subtitle{display:block;margin-top:1px;color:#64748b;font-size:10px}.dark .ccdae-map-legend__subtitle{color:#94a3b8}
        .ccdae-map-legend__badge{flex:none;padding:3px 6px;border-radius:5px;background:#f1f5f9;color:#475569;font-size:9px;font-weight:700}.dark .ccdae-map-legend__badge{background:rgba(255,255,255,.09);color:#cbd5e1}
        .ccdae-map-legend__range{display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;color:#475569;font-size:10px;font-weight:650}.dark .ccdae-map-legend__range{color:#cbd5e1}.ccdae-map-legend__bar{height:9px;border-radius:999px;box-shadow:inset 0 0 0 1px rgba(15,23,42,.12)}
        .ccdae-map-legend__labels{display:flex;justify-content:space-between;margin-top:4px;color:#64748b;font-size:9px}.dark .ccdae-map-legend__labels{color:#94a3b8}
        .ccdae-map-legend__specials{display:flex;gap:5px;margin-top:9px}.ccdae-map-legend__chip{display:flex;align-items:center;gap:5px;min-width:0;padding:4px 6px;border:1px solid rgba(148,163,184,.3);border-radius:6px;font-size:9px;font-weight:650}.ccdae-map-legend__dot{width:7px;height:7px;flex:none;border-radius:50%}
        .ccdae-map-legend__rows{display:grid;gap:7px}.ccdae-map-legend__row{display:flex;align-items:center;justify-content:space-between;gap:12px}.ccdae-map-legend__row-label{display:flex;align-items:center;gap:7px}.ccdae-map-legend__line{width:25px;height:4px;border-radius:999px}
        @media(max-width:640px){.ccdae-map-legend{width:205px;padding:10px!important}.ccdae-map-legend__subtitle,.ccdae-map-legend__labels{display:none}}
    `;
    document.head.appendChild(style);
}

export class MapLegendControl {
    constructor(options) { this.options = options; }
    onAdd() {
        registerStyles();
        const container = document.createElement('div');
        container.className = 'maplibregl-ctrl ccdae-map-legend';
        const header = document.createElement('div');
        header.className = 'ccdae-map-legend__header';
        header.innerHTML = `<div><strong class="ccdae-map-legend__title"></strong><span class="ccdae-map-legend__subtitle"></span></div>`;
        header.querySelector('strong').textContent = this.options.title;
        header.querySelector('span').textContent = this.options.subtitle ?? '';
        if (this.options.badge) {
            const badge = document.createElement('span'); badge.className = 'ccdae-map-legend__badge'; badge.textContent = this.options.badge; header.appendChild(badge);
        }
        container.appendChild(header);
        if (this.options.rows) {
            const rows = document.createElement('div'); rows.className = 'ccdae-map-legend__rows';
            this.options.rows.forEach((item) => {
                const row = document.createElement('div'); row.className = 'ccdae-map-legend__row';
                row.innerHTML = `<span class="ccdae-map-legend__row-label"><i class="ccdae-map-legend__line"></i><span></span></span><b></b>`;
                row.querySelector('i').style.background = item.color; row.querySelector('span span').textContent = item.label; row.querySelector('b').textContent = item.value; rows.appendChild(row);
            });
            container.appendChild(rows);
        } else {
            const range = document.createElement('div'); range.className = 'ccdae-map-legend__range';
            range.innerHTML = '<span></span><span></span>';
            range.children[0].textContent = `Mín. ${this.options.minimum}`; range.children[1].textContent = `Máx. ${this.options.maximum}`;
            const bar = document.createElement('div'); bar.className = 'ccdae-map-legend__bar'; bar.style.background = this.options.gradient;
            const labels = document.createElement('div'); labels.className = 'ccdae-map-legend__labels'; labels.innerHTML = '<span>Baixo</span><span>Moderado</span><span>Alto</span><span>Máximo</span>';
            const specials = document.createElement('div'); specials.className = 'ccdae-map-legend__specials';
            [['#f0abfc','Pico 96,5–99%'],['#39ff14','Extremo > 99%']].forEach(([color,label]) => { const chip=document.createElement('span'); chip.className='ccdae-map-legend__chip'; chip.innerHTML='<i class="ccdae-map-legend__dot"></i><span></span>'; chip.querySelector('i').style.background=color; chip.querySelector('span').textContent=label; specials.appendChild(chip); });
            container.append(range, bar, labels, specials);
        }
        this.container = container;
        return container;
    }
    onRemove() { this.container?.remove(); }
}

export class TimeRangeControl {
    constructor(options = {}) { this.options = options; }
    onAdd() {
        registerStyles();
        const container = document.createElement('div');
        container.className = 'maplibregl-ctrl ccdae-map-legend';
        container.style.width = '260px';
        const title = document.createElement('strong'); title.className = 'ccdae-map-legend__title'; title.textContent = 'Período da análise';
        const mode = document.createElement('select');
        const start = document.createElement('input');
        const end = document.createElement('input');
        const button = document.createElement('button');
        mode.innerHTML = '<option value="range">Intervalo de horas</option><option value="hour">Hora específica</option>';
        [mode, start, end].forEach((field) => { field.style.cssText = 'display:block;width:100%;box-sizing:border-box;margin-top:7px;padding:7px 8px;border:1px solid #cbd5e1;border-radius:7px;background:transparent;color:inherit;font:11px system-ui'; });
        start.type = 'datetime-local'; end.type = 'datetime-local';
        start.value = this.options.start ?? ''; end.value = this.options.end ?? '';
        button.type = 'button'; button.textContent = 'Aplicar período';
        button.style.cssText = 'display:block;width:100%;margin-top:8px;padding:8px;border:0;border-radius:7px;background:#0284c7;color:white;font:700 11px system-ui;cursor:pointer';
        const syncMode = () => { end.style.display = mode.value === 'hour' ? 'none' : 'block'; start.title = mode.value === 'hour' ? 'Hora selecionada' : 'Início'; };
        mode.addEventListener('change', syncMode); syncMode();
        button.addEventListener('click', () => {
            if (!start.value) return;
            let endValue = end.value;
            if (mode.value === 'hour') {
                const date = new Date(start.value); date.setHours(date.getHours() + 1); endValue = date.toISOString().slice(0, 16);
            }
            if (!endValue) return;
            this.options.onApply?.({ start: start.value, end: endValue });
        });
        container.append(title, mode, start, end, button);
        this.container = container;
        return container;
    }
    onRemove() { this.container?.remove(); }
}
