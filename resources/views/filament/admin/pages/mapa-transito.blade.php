<x-filament-panels::page>
    @vite('resources/js/maps/traffic-map.js')

    <style>
        .ccdae-traffic-legend {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .ccdae-traffic-legend div {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .ccdae-traffic-legend span {
            display: inline-block;
            width: 20px;
            height: 3px;
        }

        .ccdae-traffic-popup strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }
        .traffic-timeline { margin-top:16px; border:1px solid #dbe3ec; border-radius:14px; background:#fff; box-shadow:0 4px 16px rgba(15,23,42,.07); }
        .traffic-timeline-layout { display:grid; grid-template-columns:auto minmax(145px,190px) minmax(300px,1fr) minmax(125px,auto); align-items:center; gap:22px; padding:20px 22px; }
        .traffic-timeline-play { display:flex; align-items:center; justify-content:center; min-width:104px; height:42px; padding:0 18px; border:1px solid #0369a1; border-radius:10px; background:#0369a1; color:#fff; cursor:pointer; box-shadow:0 2px 5px rgba(15,23,42,.18); font-size:12px; font-weight:700; transition:background .15s,border-color .15s,transform .15s; }
        .traffic-timeline-play:hover:not(:disabled) { background:#075985; border-color:#075985; transform:translateY(-1px); }
        .traffic-timeline-play:disabled { cursor:not-allowed; opacity:.45; box-shadow:none; }
        .traffic-timeline-play svg { width:18px !important; height:18px !important; }
        .traffic-timeline-title { font-size:14px; font-weight:700; color:#111827; }
        .traffic-timeline-subtitle, .traffic-timeline-labels, .traffic-timeline-selected-label { color:#6b7280; }
        .traffic-timeline-subtitle { margin-top:3px; font-size:12px; }
        .traffic-timeline-labels { display:flex; justify-content:space-between; margin-bottom:18px; font-size:11px; font-weight:650; }
        .traffic-timeline-range-shell { position:relative; padding-top:10px; }
        .traffic-timeline-bubble { position:absolute; top:-16px; left:calc(100% - 11px); z-index:2; min-width:58px; padding:4px 7px; border-radius:6px; background:#0f172a; color:#fff; font-size:10px; font-weight:700; line-height:1; text-align:center; white-space:nowrap; transform:translateX(-50%); pointer-events:none; }
        .traffic-timeline-bubble::after { content:''; position:absolute; top:100%; left:50%; border:4px solid transparent; border-top-color:#0f172a; transform:translateX(-50%); }
        .traffic-timeline-range { display:block; width:100%; height:8px; margin:0; appearance:none; border:1px solid rgba(3,105,161,.16); border-radius:9999px; outline:none; cursor:pointer; background:linear-gradient(to right,#0284c7 0%,#0284c7 var(--traffic-progress,100%),#dbe3ec var(--traffic-progress,100%),#dbe3ec 100%); }
        .traffic-timeline-range:focus-visible { outline:3px solid rgba(56,189,248,.35); outline-offset:4px; }
        .traffic-timeline-range:disabled { cursor:not-allowed; opacity:.5; }
        .traffic-timeline-range::-webkit-slider-thumb { width:22px; height:22px; appearance:none; border:4px solid #fff; border-radius:50%; background:#0284c7; box-shadow:0 0 0 1px #0369a1,0 3px 9px rgba(2,132,199,.38); }
        .traffic-timeline-range::-moz-range-thumb { width:14px; height:14px; border:4px solid #fff; border-radius:50%; background:#0284c7; box-shadow:0 0 0 1px #0369a1,0 3px 9px rgba(2,132,199,.38); }
        .traffic-timeline-ticks { display:flex; justify-content:space-between; align-items:center; margin-top:13px; padding:0 11px; }
        .traffic-timeline-tick { width:5px; height:5px; padding:0; border:0; border-radius:50%; background:#cbd5e1; cursor:pointer; transition:transform .15s,background .15s; }
        .traffic-timeline-tick:hover { background:#38bdf8; transform:scale(1.7); }
        .traffic-timeline-tick.active { background:#0369a1; transform:scale(1.8); }
        .traffic-timeline-selected { padding-left:18px; border-left:1px solid #e5e7eb; text-align:right; }
        .traffic-timeline-selected-label { display:block; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; }
        .traffic-timeline-selected-value { display:block; margin-top:4px; font-size:15px; color:#111827; white-space:nowrap; }
        .dark .traffic-timeline { background:rgb(17 24 39); border-color:rgba(255,255,255,.1); }
        .dark .traffic-timeline-title, .dark .traffic-timeline-selected-value { color:#fff; }
        .dark .traffic-timeline-subtitle, .dark .traffic-timeline-labels, .dark .traffic-timeline-selected-label { color:#9ca3af; }
        .dark .traffic-timeline-selected { border-color:rgba(255,255,255,.1); }
        .dark .traffic-timeline-range { border-color:rgba(56,189,248,.25); background:linear-gradient(to right,#38bdf8 0%,#38bdf8 var(--traffic-progress,100%),#334155 var(--traffic-progress,100%),#334155 100%); }
        .dark .traffic-timeline-range::-webkit-slider-thumb { border-color:#0f172a; background:#38bdf8; box-shadow:0 0 0 1px #7dd3fc,0 3px 10px rgba(56,189,248,.35); }
        .dark .traffic-timeline-range::-moz-range-thumb { border-color:#0f172a; background:#38bdf8; }
        .dark .traffic-timeline-tick { background:#475569; }
        .dark .traffic-timeline-tick.active { background:#38bdf8; }
        @media (max-width: 900px) {
            .traffic-timeline-layout { grid-template-columns:auto 1fr auto; gap:14px; }
            .traffic-timeline-control { grid-column:1 / -1; grid-row:2; }
        }
    </style>

    <x-filament::section>
        <div>
        <div class="mb-4 flex max-w-sm gap-4">
            <x-filament::input.wrapper class="w-full">
                <x-filament::input.select wire:model.live="distrito_id">
                    <option value="">Ver todos os distritos (visão macro)</option>
                    @foreach($this->distritos as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div wire:ignore class="ccdae-map-surface" style="height: 750px;">
            <div
                id="transito-map-container"
                class="h-full w-full"
                style="width: 100%; height: 100%;"
                data-payload='@json(['vias' => $this->vias, 'distritos' => $this->distritos_geojson], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)'
                data-center='@json([-57.6806, -16.0722])'
                data-zoom="13"
                data-style-url="{{ config('maps.base_style_url') }}"
                data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}"
                data-raster-attribution="{{ config('maps.raster_attribution') }}"
                data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}"
                data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}"
                data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                data-terrain-attribution="{{ config('maps.terrain.attribution') }}"
                data-district-id="{{ $this->distrito_id }}"
                data-martin-enabled="{{ config('maps.martin.enabled') ? 'true' : 'false' }}"
                data-martin-tilejson-url="{{ config('maps.martin.tilejson_base_url') }}"
            ></div>

            <div class="ccdae-map-panel ccdae-traffic-legend absolute bottom-8 right-3 z-10">
                <strong>Fluxo de Trânsito</strong>
                <div><span style="background:#22c55e"></span>Via livre (&gt; 40 km/h)</div>
                <div><span style="background:#eab308"></span>Moderado (20–40 km/h)</div>
                <div><span style="background:#f97316"></span>Intenso (10–20 km/h)</div>
                <div><span style="background:#ef4444"></span>Parado (&lt; 10 km/h)</div>
                <div class="opacity-60"><span style="background:#6b7280"></span>Fronteira do distrito</div>
            </div>
        </div>

        <div class="traffic-timeline">
            <div class="traffic-timeline-layout">
                <button id="traffic-timeline-play" type="button" class="traffic-timeline-play" disabled>
                    Reproduzir
                </button>
                <div>
                    <div class="traffic-timeline-title">Histórico de trânsito</div>
                    <div class="traffic-timeline-subtitle">Leituras das últimas 24 horas</div>
                </div>
                <div class="traffic-timeline-control">
                    <div class="traffic-timeline-labels"><span>24 horas atrás</span><span>Agora</span></div>
                    <div class="traffic-timeline-range-shell">
                        <output id="traffic-timeline-bubble" class="traffic-timeline-bubble">--:--</output>
                        <input
                            class="traffic-timeline-range"
                            type="range"
                            min="0"
                            id="traffic-timeline-range"
                            max="0"
                            value="0"
                            step="1"
                            disabled
                            aria-label="Horário da leitura de trânsito"
                        >
                        <div id="traffic-timeline-ticks" class="traffic-timeline-ticks" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="traffic-timeline-selected">
                    <span class="traffic-timeline-selected-label">Leitura selecionada</span>
                    <strong id="traffic-timeline-selected" class="traffic-timeline-selected-value">Carregando...</strong>
                </div>
            </div>
        </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
