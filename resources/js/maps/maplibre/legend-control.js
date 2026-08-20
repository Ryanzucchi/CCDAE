let stylesRegistered = false;

function registerStyles() {
    if (stylesRegistered) return;
    stylesRegistered = true;
    const style = document.createElement('style');
    style.textContent = `
        .ccdae-map-legend{width:238px;margin:10px!important;padding:12px!important;border:1px solid rgba(148,163,184,.35);border-radius:12px!important;background:rgba(255,255,255,.94)!important;color:#0f172a;box-shadow:0 8px 24px rgba(15,23,42,.14)!important;font:12px/1.35 Inter,ui-sans-serif,system-ui,sans-serif;backdrop-filter:blur(12px)}
        .dark .ccdae-map-legend{border-color:rgba(255,255,255,.12);background:rgba(17,24,39,.94)!important;color:#f8fafc;box-shadow:0 8px 24px rgba(0,0,0,.4)!important}
        .ccdae-map-legend__header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}.ccdae-map-legend__title{display:block;font-size:13px;font-weight:700}.ccdae-map-legend__subtitle{display:block;margin-top:1px;color:#64748b;font-size:10px}.dark .ccdae-map-legend__subtitle{color:#94a3b8}
        .ccdae-map-legend__badge{flex:none;padding:3px 7px;border-radius:6px;background:#e0f2fe;color:#0369a1;font-size:9px;font-weight:700;letter-spacing:.2px}.dark .ccdae-map-legend__badge{background:rgba(56,189,248,.15);color:#7dd3fc}
        .ccdae-map-legend__range{display:flex;justify-content:space-between;gap:8px;margin-bottom:6px;color:#334155;font-size:10px;font-weight:700}.dark .ccdae-map-legend__range{color:#e2e8f0}.ccdae-map-legend__bar{height:10px;border-radius:999px;box-shadow:inset 0 0 0 1px rgba(15,23,42,.15)}
        .ccdae-map-legend__labels{display:flex;justify-content:space-between;margin-top:5px;color:#64748b;font-size:9px;font-weight:600}.dark .ccdae-map-legend__labels{color:#94a3b8}
        .ccdae-map-legend__specials{display:flex;gap:5px;margin-top:10px}.ccdae-map-legend__chip{display:flex;align-items:center;gap:5px;min-width:0;padding:4px 6px;border:1px solid rgba(148,163,184,.3);border-radius:6px;font-size:9px;font-weight:650}.ccdae-map-legend__dot{width:7px;height:7px;flex:none;border-radius:50%}
        .ccdae-map-legend__rows{display:grid;gap:7px}.ccdae-map-legend__row{display:flex;align-items:center;justify-content:space-between;gap:12px}.ccdae-map-legend__row-label{display:flex;align-items:center;gap:7px}.ccdae-map-legend__line{width:25px;height:4px;border-radius:999px}

        /* Elevacao e Corte de Altura - Design Premium */
        .ccdae-elevation-control{
            width: 140px;
            margin: 10px !important;
            padding: 12px 10px !important;
            border: 1px solid rgba(148, 163, 184, .38);
            border-radius: 14px !important;
            background: rgba(255, 255, 255, .95) !important;
            color: #0f172a;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .18) !important;
            font: 12px/1.35 Inter, ui-sans-serif, system-ui, sans-serif;
            backdrop-filter: blur(14px);
            display: flex;
            flex-direction: column;
            align-items: center;
            user-select: none;
        }
        .dark .ccdae-elevation-control{
            border-color: rgba(255, 255, 255, .15);
            background: rgba(15, 23, 42, .95) !important;
            color: #f8fafc;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .45) !important;
        }
        .ccdae-elevation-control__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 6px;
        }
        .ccdae-elevation-control__title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #0284c7;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .dark .ccdae-elevation-control__title {
            color: #38bdf8;
        }
        .ccdae-elevation-control__badge {
            width: 100%;
            text-align: center;
            padding: 4px 6px;
            border-radius: 6px;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 10px;
            box-shadow: 0 2px 6px rgba(2, 132, 199, .25);
            letter-spacing: .2px;
        }
        .dark .ccdae-elevation-control__badge {
            background: linear-gradient(135deg, #0284c7, #0f766e);
            box-shadow: 0 2px 8px rgba(2, 132, 199, .35);
        }
        .ccdae-elevation-control__body {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            height: 160px;
            width: 100%;
            padding: 4px 0;
        }
        .ccdae-elevation-control__track-container {
            position: relative;
            width: 14px;
            height: 150px;
            background: rgba(148, 163, 184, .25);
            border-radius: 999px;
            cursor: pointer;
            box-shadow: inset 0 1px 3px rgba(0,0,0,.2);
            border: 1px solid rgba(148, 163, 184, .3);
        }
        .dark .ccdae-elevation-control__track-container {
            background: rgba(30, 41, 59, .8);
            border-color: rgba(255, 255, 255, .15);
        }
        .ccdae-elevation-control__track-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            border-radius: 999px;
            background: linear-gradient(0deg, #1d4ed8, #06b6d4, #10b981, #facc15, #f97316, #ef4444, #39ff14);
            box-shadow: 0 0 8px rgba(6, 182, 212, .4);
            pointer-events: none;
            transition: height .05s ease-out;
        }
        .ccdae-elevation-control__thumb {
            position: absolute;
            left: 50%;
            transform: translate(-50%, 50%);
            width: 26px;
            height: 16px;
            border-radius: 8px;
            background: #ffffff;
            border: 2px solid #0284c7;
            box-shadow: 0 3px 8px rgba(0, 0, 0, .25);
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .05s ease-out, background .15s, border-color .15s;
            z-index: 5;
        }
        .dark .ccdae-elevation-control__thumb {
            background: #1e293b;
            border-color: #38bdf8;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .5);
        }
        .ccdae-elevation-control__thumb:hover, .ccdae-elevation-control__thumb.dragging {
            cursor: grabbing;
            border-color: #38bdf8;
            box-shadow: 0 0 10px rgba(56, 189, 248, .6);
            transform: translate(-50%, 50%) scale(1.1);
        }
        .ccdae-elevation-control__thumb::after {
            content: '||';
            font-size: 8px;
            font-weight: 900;
            letter-spacing: -1px;
            color: #64748b;
        }
        .dark .ccdae-elevation-control__thumb::after {
            color: #94a3b8;
        }
        .ccdae-elevation-control__scale {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 150px;
            font-size: 9px;
            font-weight: 700;
            color: #64748b;
        }
        .dark .ccdae-elevation-control__scale {
            color: #94a3b8;
        }
        .ccdae-elevation-control__tick {
            cursor: pointer;
            transition: color .15s;
            padding: 1px 2px;
            border-radius: 3px;
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
            padding: 3px 0;
            border: 1px solid rgba(148, 163, 184, .35);
            border-radius: 5px;
            background: transparent;
            color: #475569;
            font-size: 9px;
            font-weight: 700;
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

        @media(max-width:640px){
            .ccdae-map-legend{width:205px;padding:10px!important}.ccdae-map-legend__subtitle,.ccdae-map-legend__labels{display:none}
            .ccdae-elevation-control{width:115px;padding:10px 6px!important}.ccdae-elevation-control__body{height:120px}.ccdae-elevation-control__track-container,.ccdae-elevation-control__scale{height:110px}
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
        badge.textContent = this.options.badge ?? '10 faixas relativas';
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
            labels.innerHTML = '<span>Piso Visível</span><span>Médio</span><span>Alto</span><span>Pico</span>';

            const specials = document.createElement('div');
            specials.className = 'ccdae-map-legend__specials';
            [['#f0abfc','Pico Top 10%'],['#39ff14','Extremo Top 1%']].forEach(([color,label]) => {
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
                <span class="ccdae-elevation-control__title">
                    <svg style="width:13px;height:13px;display:inline-block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 20h18L14 4 9 14l-3-4z"/></svg>
                    Altitude
                </span>
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
                    <span class="ccdae-elevation-control__tick" data-preset="100" title="Apenas o Topo Máximo (100%)">100%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="75" title="Top 25% mais elevados">75%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="50" title="Metade superior mais alta">50%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="25" title="Top 75% acima da base">25%</span>
                    <span class="ccdae-elevation-control__tick" data-preset="1" title="Ver 100% dos dados completos">1%</span>
                </div>
            </div>
            <div class="ccdae-elevation-control__presets">
                <button type="button" class="ccdae-elevation-control__btn" data-preset="1" title="Visualização completa">Tudo</button>
                <button type="button" class="ccdae-elevation-control__btn" data-preset="50" title="Corte médio">50%</button>
                <button type="button" class="ccdae-elevation-control__btn" data-preset="80" title="Apenas picos">Picos</button>
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

        // Prevent map interaction while dragging
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
