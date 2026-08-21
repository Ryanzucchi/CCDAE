let stylesRegistered = false;

function registerStyles() {
    if (stylesRegistered) return;
    stylesRegistered = true;
    const style = document.createElement('style');
    style.textContent = `
        .ccdae-map-legend{width:238px;margin:10px!important;padding:12px!important;border:1px solid rgba(148,163,184,.35);border-radius:10px!important;background:rgba(255,255,255,.94)!important;color:#0f172a;box-shadow:0 6px 18px rgba(15,23,42,.14)!important;font:12px/1.35 Inter,ui-sans-serif,system-ui,sans-serif;backdrop-filter:blur(10px)}
        .dark .ccdae-map-legend{border-color:rgba(255,255,255,.12);background:rgba(17,24,39,.94)!important;color:#f8fafc;box-shadow:0 6px 18px rgba(0,0,0,.35)!important}
        .ccdae-map-legend__header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}.ccdae-map-legend__title{display:block;font-size:13px;font-weight:700}.ccdae-map-legend__subtitle{display:block;margin-top:1px;color:#64748b;font-size:10px}.dark .ccdae-map-legend__subtitle{color:#94a3b8}
        .ccdae-map-legend__badge{flex:none;padding:3px 6px;border-radius:5px;background:#f1f5f9;color:#475569;font-size:9px;font-weight:700}.dark .ccdae-map-legend__badge{background:rgba(255,255,255,.09);color:#cbd5e1}
        .ccdae-map-legend__range{display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;color:#475569;font-size:10px;font-weight:650}.dark .ccdae-map-legend__range{color:#cbd5e1}.ccdae-map-legend__bar{height:9px;border-radius:999px;box-shadow:inset 0 0 0 1px rgba(15,23,42,.12)}
        .ccdae-map-legend__labels{display:flex;justify-content:space-between;margin-top:4px;color:#64748b;font-size:9px}.dark .ccdae-map-legend__labels{color:#94a3b8}
        .ccdae-map-legend__specials{display:flex;gap:5px;margin-top:9px}.ccdae-map-legend__chip{display:flex;align-items:center;gap:5px;min-width:0;padding:4px 6px;border:1px solid rgba(148,163,184,.3);border-radius:6px;font-size:9px;font-weight:650}.ccdae-map-legend__dot{width:7px;height:7px;flex:none;border-radius:50%}
        .ccdae-map-legend__rows{display:grid;gap:7px}.ccdae-map-legend__row{display:flex;align-items:center;justify-content:space-between;gap:12px}.ccdae-map-legend__row-label{display:flex;align-items:center;gap:7px}.ccdae-map-legend__line{width:25px;height:4px;border-radius:999px}

        /* Elevacao e Corte de Altura - Design Minimalista e Elegante */
        .ccdae-elevation-control{
            width: 120px;
            margin: 10px !important;
            padding: 10px 8px !important;
            border: 1px solid rgba(148, 163, 184, .3);
            border-radius: 10px !important;
            background: rgba(255, 255, 255, .94) !important;
            color: #0f172a;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .08) !important;
            font: 11px/1.35 Inter, ui-sans-serif, system-ui, sans-serif;
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            align-items: center;
            user-select: none;
        }
        .dark .ccdae-elevation-control{
            border-color: rgba(255, 255, 255, .12);
            background: rgba(17, 24, 39, .94) !important;
            color: #f8fafc;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .3) !important;
        }
        .ccdae-elevation-control__header {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-bottom: 4px;
        }
        .ccdae-elevation-control__title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #475569;
        }
        .dark .ccdae-elevation-control__title {
            color: #94a3b8;
        }
        .ccdae-elevation-control__badge {
            width: 100%;
            text-align: center;
            padding: 2px 4px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #334155;
            font-size: 9.5px;
            font-weight: 700;
            margin-bottom: 8px;
            border: 1px solid rgba(148, 163, 184, .2);
        }
        .dark .ccdae-elevation-control__badge {
            background: rgba(255, 255, 255, .08);
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, .08);
        }
        .ccdae-elevation-control__body {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            height: 140px;
            width: 100%;
            padding: 2px 0;
        }
        .ccdae-elevation-control__track-container {
            position: relative;
            width: 8px;
            height: 130px;
            background: #e2e8f0;
            border-radius: 999px;
            cursor: pointer;
        }
        .dark .ccdae-elevation-control__track-container {
            background: #334155;
        }
        .ccdae-elevation-control__track-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            border-radius: 999px;
            background: #0284c7;
            pointer-events: none;
            transition: height .04s ease-out;
        }
        .dark .ccdae-elevation-control__track-fill {
            background: #38bdf8;
        }
        .ccdae-elevation-control__thumb {
            position: absolute;
            left: 50%;
            transform: translate(-50%, 50%);
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #0284c7;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .2);
            cursor: grab;
            transition: transform .04s ease-out, border-color .15s;
            z-index: 5;
        }
        .dark .ccdae-elevation-control__thumb {
            background: #1e293b;
            border-color: #38bdf8;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .4);
        }
        .ccdae-elevation-control__thumb:hover, .ccdae-elevation-control__thumb.dragging {
            cursor: grabbing;
            transform: translate(-50%, 50%) scale(1.15);
        }
        .ccdae-elevation-control__scale {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 130px;
            font-size: 8.5px;
            font-weight: 600;
            color: #64748b;
        }
        .dark .ccdae-elevation-control__scale {
            color: #94a3b8;
        }
        .ccdae-elevation-control__tick {
            cursor: pointer;
            transition: color .15s;
            padding: 0 2px;
        }
        .ccdae-elevation-control__tick:hover {
            color: #0284c7;
        }
        .dark .ccdae-elevation-control__tick:hover {
            color: #38bdf8;
        }
        .ccdae-elevation-control__presets {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            width: 100%;
            margin-top: 8px;
        }
        .ccdae-elevation-control__btn {
            padding: 2px 0;
            border: 1px solid rgba(148, 163, 184, .3);
            border-radius: 4px;
            background: transparent;
            color: #475569;
            font-size: 8.5px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: all .15s;
        }
        .dark .ccdae-elevation-control__btn {
            color: #cbd5e1;
            border-color: rgba(255, 255, 255, .12);
        }
        .ccdae-elevation-control__btn:hover {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }
        .dark .ccdae-elevation-control__btn:hover {
            background: #38bdf8;
            color: #0f172a;
            border-color: #38bdf8;
        }

        @media(max-width:640px){
            .ccdae-map-legend{width:205px;padding:10px!important}.ccdae-map-legend__subtitle,.ccdae-map-legend__labels{display:none}
            .ccdae-elevation-control{width:105px;padding:8px 6px!important}.ccdae-elevation-control__body{height:110px}.ccdae-elevation-control__track-container,.ccdae-elevation-control__scale{height:100px}
        }
    `;
    document.head.appendChild(style);
}

export class MapLegendControl {
    constructor(options) {
        this.options = options;
    }

    onAdd(map) {
        registerStyles();
        this.map = map;
        const container = document.createElement('div');
        container.className = 'maplibregl-ctrl ccdae-map-legend';
        const header = document.createElement('div');
        header.className = 'ccdae-map-legend__header';
        header.innerHTML = `<div><strong class="ccdae-map-legend__title"></strong><span class="ccdae-map-legend__subtitle"></span></div>`;
        header.querySelector('strong').textContent = this.options.title;
        header.querySelector('span').textContent = this.options.subtitle ?? '';

        const badge = document.createElement('span');
        badge.className = 'ccdae-map-legend__badge';
        badge.setAttribute('data-legend-badge', '');
        badge.textContent = this.options.badge ?? '10 faixas de 10%';
        header.appendChild(badge);
        container.appendChild(header);

        if (this.options.rows) {
            const rows = document.createElement('div');
            rows.className = 'ccdae-map-legend__rows';
            this.options.rows.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'ccdae-map-legend__row';
                row.innerHTML = `<span class="ccdae-map-legend__row-label"><i class="ccdae-map-legend__line"></i><span></span></span><b></b>`;
                row.querySelector('i').style.background = item.color;
                row.querySelector('span span').textContent = item.label;
                row.querySelector('b').textContent = item.value;
                rows.appendChild(row);
            });
            container.appendChild(rows);
        } else {
            const range = document.createElement('div');
            range.className = 'ccdae-map-legend__range';
            range.innerHTML = '<span data-legend-min></span><span data-legend-max></span>';
            range.children[0].textContent = `Mín. ${this.options.minimum}`;
            range.children[1].textContent = `Máx. ${this.options.maximum}`;

            const bar = document.createElement('div');
            bar.className = 'ccdae-map-legend__bar';
            bar.setAttribute('data-legend-bar', '');
            bar.style.background = this.options.gradient;

            const labels = document.createElement('div');
            labels.className = 'ccdae-map-legend__labels';
            labels.innerHTML = '<span>Baixo</span><span>Moderado</span><span>Alto</span><span>Máximo</span>';

            const specials = document.createElement('div');
            specials.className = 'ccdae-map-legend__specials';
            [['#f0abfc','Pico 96,5–99%'],['#39ff14','Extremo > 99%']].forEach(([color,label]) => {
                const chip=document.createElement('span');
                chip.className='ccdae-map-legend__chip';
                chip.innerHTML='<i class="ccdae-map-legend__dot"></i><span></span>';
                chip.querySelector('i').style.background=color;
                chip.querySelector('span').textContent=label;
                specials.appendChild(chip);
            });
            container.append(range, bar, labels, specials);
        }

        this.container = container;
        return container;
    }

    updateRange(minimum, maximum, badge) {
        if (!this.container) return;
        const minEl = this.container.querySelector('[data-legend-min]');
        const maxEl = this.container.querySelector('[data-legend-max]');
        const badgeEl = this.container.querySelector('[data-legend-badge]');
        if (minEl) minEl.textContent = `Mín. ${minimum}`;
        if (maxEl) maxEl.textContent = `Máx. ${maximum}`;
        if (badgeEl && badge) badgeEl.textContent = badge;
    }

    onRemove() {
        this.container?.remove();
    }
}

export class TimeRangeControl {
    constructor(options = {}) {
        this.options = options;
    }

    onAdd() {
        registerStyles();
        const container = document.createElement('div');
        container.className = 'maplibregl-ctrl ccdae-map-legend';
        container.style.width = '260px';
        const title = document.createElement('strong');
        title.className = 'ccdae-map-legend__title';
        title.textContent = 'Período da análise';
        const mode = document.createElement('select');
        const start = document.createElement('input');
        const end = document.createElement('input');
        const button = document.createElement('button');
        mode.innerHTML = '<option value="range">Intervalo de horas</option><option value="hour">Hora específica</option>';
        [mode, start, end].forEach((field) => {
            field.style.cssText = 'display:block;width:100%;box-sizing:border-box;margin-top:7px;padding:7px 8px;border:1px solid #cbd5e1;border-radius:7px;background:transparent;color:inherit;font:11px system-ui';
        });
        start.type = 'datetime-local';
        end.type = 'datetime-local';
        start.value = this.options.start ?? '';
        end.value = this.options.end ?? '';
        button.type = 'button';
        button.textContent = 'Aplicar período';
        button.style.cssText = 'display:block;width:100%;margin-top:8px;padding:8px;border:0;border-radius:7px;background:#0284c7;color:white;font:700 11px system-ui;cursor:pointer';
        const syncMode = () => {
            end.style.display = mode.value === 'hour' ? 'none' : 'block';
            start.title = mode.value === 'hour' ? 'Hora selecionada' : 'Início';
        };
        mode.addEventListener('change', syncMode);
        syncMode();
        button.addEventListener('click', () => {
            if (!start.value) return;
            let endValue = end.value;
            if (mode.value === 'hour') {
                const date = new Date(start.value);
                date.setHours(date.getHours() + 1);
                endValue = date.toISOString().slice(0, 16);
            }
            if (!endValue) return;
            this.options.onApply?.({ start: start.value, end: endValue });
        });
        container.append(title, mode, start, end, button);
        this.container = container;
        return container;
    }

    onRemove() {
        this.container?.remove();
    }
}

export class SurfaceElevationSliderControl {
    constructor(options = {}) {
        this.options = {
            min: 1,
            max: 100,
            value: options.initialValue ?? 1,
            onChange: options.onChange ?? (() => {}),
            ...options,
        };
        this.currentValue = this.options.value;
    }

    onAdd(map) {
        registerStyles();
        this.map = map;
        const container = document.createElement('div');
        container.className = 'maplibregl-ctrl ccdae-elevation-control';

        container.innerHTML = `
            <div class="ccdae-elevation-control__header">
                <span class="ccdae-elevation-control__title">Altitude</span>
            </div>
            <div class="ccdae-elevation-control__badge" data-elevation-badge>
                Visível: <span data-elevation-value>${this.currentValue}</span>%–100%
            </div>
            <div class="ccdae-elevation-control__body">
                <div class="ccdae-elevation-control__track-container" data-elevation-track>
                    <div class="ccdae-elevation-control__track-fill" data-elevation-fill style="height: ${this.currentValue}%;"></div>
                    <div class="ccdae-elevation-control__thumb" data-elevation-thumb style="bottom: ${this.currentValue}%;"></div>
                </div>
                <div class="ccdae-elevation-control__scale">
                    <span class="ccdae-elevation-control__tick" data-preset="100" title="Pico máximo">100%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="75" title="Top 25%">75%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="50" title="Metade superior">50%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="25" title="Top 75%">25%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="1" title="Ver tudo">1%</span>
                </div>
            </div>
            <div class="ccdae-elevation-control__presets">
                <button type="button" class="ccdae-elevation-control__btn" data-preset="1" title="Ver tudo">Tudo</button>
                <button type="button" class="ccdae-elevation-control__btn" data-preset="50" title="Corte 50%">50%</button>
                <button type="button" class="ccdae-elevation-control__btn" data-preset="100" title="Pico 100%">Pico</button>
            </div>
        `;

        const track = container.querySelector('[data-elevation-track]');
        const fill = container.querySelector('[data-elevation-fill]');
        const thumb = container.querySelector('[data-elevation-thumb]');
        const valueSpan = container.querySelector('[data-elevation-value]');

        const updateValue = (val) => {
            const num = Math.max(1, Math.min(100, Math.round(val)));
            this.currentValue = num;
            fill.style.height = `${num}%`;
            thumb.style.bottom = `${num}%`;
            valueSpan.textContent = num;
            this.options.onChange(num);
        };

        const handlePointer = (e) => {
            const rect = track.getBoundingClientRect();
            const clientY = e.clientY ?? e.touches?.[0]?.clientY ?? 0;
            const bottom = rect.bottom;
            const height = rect.height;
            const diff = bottom - clientY;
            const percent = (diff / height) * 100;
            updateValue(percent);
        };

        let isDragging = false;

        const onPointerDown = (e) => {
            isDragging = true;
            thumb.classList.add('dragging');
            handlePointer(e);
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', onPointerUp);
        };

        const onPointerMove = (e) => {
            if (!isDragging) return;
            handlePointer(e);
        };

        const onPointerUp = () => {
            if (!isDragging) return;
            isDragging = false;
            thumb.classList.remove('dragging');
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', onPointerUp);
        };

        track.addEventListener('pointerdown', onPointerDown);

        container.querySelectorAll('[data-preset]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const targetVal = Number(e.currentTarget.getAttribute('data-preset'));
                if (targetVal) updateValue(targetVal);
            });
        });

        container.addEventListener('mousedown', (e) => e.stopPropagation());
        container.addEventListener('touchstart', (e) => e.stopPropagation());
        container.addEventListener('wheel', (e) => e.stopPropagation());
        container.addEventListener('dblclick', (e) => e.stopPropagation());

        this.container = container;
        return container;
    }

    onRemove() {
        this.container?.remove();
    }
}
