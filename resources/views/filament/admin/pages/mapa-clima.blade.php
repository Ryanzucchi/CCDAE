<x-filament-panels::page>
    @vite('resources/js/maps/climate-map.js')

    <style>
        /* Estilos do mapa climático e UI */
        .climate-legend {
            background: rgba(255, 255, 255, 0.96);
            color: #111827;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(17, 24, 39, 0.12);
        }
        .legend-item { display: flex; align-items: center; margin-bottom: 5px; }
        .legend-color { width: 16px; height: 16px; border-radius: 4px; margin-right: 8px; display: inline-block; }
        
        .climate-btn {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            width: 100%;
            min-height: 34px;
            padding: 7px 10px;
            text-align: left;
            font-size: 12px;
            line-height: 1.25;
            background: rgba(255, 255, 255, 0.96);
            color: #374151;
            border: 1px solid #d1d5db;
            transition: all 0.2s ease;
        }
        .climate-btn:hover { background: #f9fafb; border-color: #9ca3af; }
        .climate-btn svg {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px;
            flex: 0 0 16px;
            stroke-width: 1.8;
        }
        .climate-btn.active {
            background: #0369a1 !important;
            color: #ffffff !important;
            border-color: #0284c7 !important;
            font-weight: 700;
            box-shadow: inset 3px 0 0 #7dd3fc, 0 1px 2px rgba(15, 23, 42, .18);
        }
        .climate-btn.active:hover {
            background: #075985 !important;
        }
        .climate-btn.active svg {
            color: #ffffff !important;
            stroke: currentColor !important;
        }
        .climate-btn:focus-visible {
            outline: 2px solid #38bdf8;
            outline-offset: 2px;
        }

        .climate-timeline { margin-top:16px; border:1px solid #dbe3ec; border-radius:14px; background:#fff; box-shadow:0 4px 16px rgba(15,23,42,.07); }
        .timeline-layout { display:grid; grid-template-columns:auto minmax(145px,190px) minmax(300px,1fr) minmax(125px,auto); align-items:center; gap:22px; padding:20px 22px; }
        .timeline-play { display:flex; align-items:center; justify-content:center; min-width:104px; height:42px; padding:0 18px; border:1px solid #0369a1; border-radius:10px; background:#0369a1; color:#fff; cursor:pointer; box-shadow:0 2px 5px rgba(15,23,42,.18); font-size:12px; font-weight:700; transition:background .15s,border-color .15s,transform .15s; }
        .timeline-play:hover:not(:disabled) { background:#075985; border-color:#075985; transform:translateY(-1px); }
        .timeline-play:disabled { cursor:not-allowed; opacity:.45; box-shadow:none; }
        .timeline-heading { min-width: 0; }
        .timeline-title-row { display: flex; align-items: center; gap: 8px; color: #111827; }
        .timeline-title-row svg { width: 18px !important; height: 18px !important; flex: 0 0 18px; color: rgb(var(--primary-600)); }
        .timeline-title { font-size: 14px; font-weight: 700; line-height: 1.25; }
        .timeline-subtitle { margin-top: 3px; font-size: 12px; line-height: 1.35; color: #6b7280; }
        .timeline-control { min-width: 0; }
        .timeline-labels { display:flex; justify-content:space-between; margin-bottom:18px; font-size:11px; font-weight:650; color:#6b7280; }
        .timeline-range-shell { position:relative; padding-top:10px; }
        .timeline-bubble { position:absolute; top:-16px; left:calc(100% - 11px); z-index:2; min-width:58px; padding:4px 7px; border-radius:6px; background:#0f172a; color:#fff; font-size:10px; font-weight:700; line-height:1; text-align:center; white-space:nowrap; transform:translateX(-50%); pointer-events:none; }
        .timeline-bubble::after { content:''; position:absolute; top:100%; left:50%; border:4px solid transparent; border-top-color:#0f172a; transform:translateX(-50%); }
        .timeline-range { display:block; appearance:none; width:100%; height:8px; margin:0; border:1px solid rgba(3,105,161,.16); border-radius:9999px; outline:none; cursor:pointer; background:linear-gradient(to right,#0284c7 0%,#0284c7 var(--timeline-progress,100%),#dbe3ec var(--timeline-progress,100%),#dbe3ec 100%); }
        .timeline-range:focus-visible { outline:3px solid rgba(56,189,248,.35); outline-offset:4px; }
        .timeline-range:disabled { cursor:not-allowed; opacity:.5; }
        .timeline-range::-webkit-slider-thumb { width:22px; height:22px; appearance:none; border:4px solid #fff; border-radius:50%; background:#0284c7; box-shadow:0 0 0 1px #0369a1,0 3px 9px rgba(2,132,199,.38); }
        .timeline-range::-moz-range-thumb { width:14px; height:14px; border:4px solid #fff; border-radius:50%; background:#0284c7; box-shadow:0 0 0 1px #0369a1,0 3px 9px rgba(2,132,199,.38); }
        .timeline-ticks { display:flex; justify-content:space-between; align-items:center; margin-top:13px; padding:0 11px; }
        .timeline-tick { width:5px; height:5px; padding:0; border:0; border-radius:50%; background:#cbd5e1; cursor:pointer; transition:transform .15s,background .15s; }
        .timeline-tick:hover { background:#38bdf8; transform:scale(1.7); }
        .timeline-tick.active { background:#0369a1; transform:scale(1.8); }
        .timeline-selected { padding-left: 18px; border-left: 1px solid #e5e7eb; text-align: right; }
        .timeline-selected-label { display: block; font-size: 10px; font-weight: 600; line-height: 1.2; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }
        .timeline-selected-value { display: block; margin-top: 4px; font-size: 15px; font-weight: 700; line-height: 1.2; color: #111827; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .dark .climate-timeline { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .dark .timeline-title, .dark .timeline-selected-value { color: #fff; }
        .dark .timeline-subtitle, .dark .timeline-labels, .dark .timeline-selected-label { color: #9ca3af; }
        .dark .timeline-selected { border-color: rgba(255,255,255,.1); }
        .dark .timeline-range { border-color:rgba(56,189,248,.25); background:linear-gradient(to right,#38bdf8 0%,#38bdf8 var(--timeline-progress,100%),#334155 var(--timeline-progress,100%),#334155 100%); }
        .dark .timeline-range::-webkit-slider-thumb { border-color:#0f172a; background:#38bdf8; box-shadow:0 0 0 1px #7dd3fc,0 3px 10px rgba(56,189,248,.35); }
        .dark .timeline-range::-moz-range-thumb { border-color:#0f172a; background:#38bdf8; }
        @media (max-width: 900px) {
            .timeline-layout { grid-template-columns: auto 1fr auto; gap: 14px; }
            .timeline-control { grid-column: 1 / -1; grid-row: 2; }
        }
        .dark .timeline-tick { background: #4b5563; }
        .dark .timeline-tick.active { background:#38bdf8; }
        .climate-controls {
            background: rgba(255, 255, 255, .96);
            border: 1px solid rgba(17, 24, 39, .12);
            color: #111827;
        }
        .climate-controls-title { color: #111827; border-color: rgba(17, 24, 39, .1); }
        .wind-summary {
            background: rgba(255, 255, 255, .96);
            border: 1px solid rgba(14, 165, 233, .35);
            color: #0c4a6e;
        }
        .wind-summary-kicker { color: #0284c7; }
        .wind-summary-detail { color: #0369a1; }
        .wind-summary-help { color: #64748b; }
        .wind-summary svg { width: 34px !important; height: 34px !important; }
        .dark .climate-legend,
        .dark .climate-controls {
            background: rgba(17, 24, 39, .95);
            color: #f3f4f6;
            border-color: rgba(75, 85, 99, .5);
        }
        .dark .climate-controls-title { color: #fff; border-color: rgba(255, 255, 255, .15); }
        .dark .climate-btn { background: rgba(31, 41, 55, .92); color: #d1d5db; border-color: #4b5563; }
        .dark .climate-btn:hover { background: #374151; }
        .dark .climate-btn.active { background: #0c4a6e !important; color: #f0f9ff !important; border-color: #38bdf8 !important; }
        .dark .climate-btn.active:hover { background: #075985 !important; }
        .dark .wind-summary { background: rgba(15, 23, 42, .92); color: #e0f2fe; border-color: rgba(56, 189, 248, .45); }
        .dark .wind-summary-kicker { color: #7dd3fc; }
        .dark .wind-summary-detail { color: #bae6fd; }
        .dark .wind-summary-help { color: #94a3b8; }
    </style>

    <x-filament::section>
        <div wire:ignore x-data="climateMapPage" x-init="init()" class="space-y-4">
            <div class="ccdae-map-surface" style="height: 750px;">
            
            <!-- Controle de Filtros e Camadas Climáticas (Flutuante Superior Esquerdo) -->
            <div class="ccdae-map-panel climate-controls" style="position: absolute; top: 12px; left: 60px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; padding: 8px; max-width: 280px;">
                <div class="climate-controls-title" style="font-weight: bold; font-size: 13px; border-bottom-width: 1px; padding-bottom: 4px; margin-bottom: 4px;">
                    Variáveis climáticas
                </div>
                <div style="display: grid; grid-template-columns: 1fr; gap: 6px;">
                    <button type="button" @click="setMetric('temperatura')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'temperatura' ? 'active' : ''" :aria-pressed="metric === 'temperatura'">
                        <x-heroicon-o-fire class="h-4 w-4" /> Temperatura (°C)
                    </button>
                    <button type="button" @click="setMetric('radiacao')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'radiacao' ? 'active' : ''" :aria-pressed="metric === 'radiacao'">
                        <x-heroicon-o-sun class="h-4 w-4" /> Radiação solar (W/m²)
                    </button>
                    <button type="button" @click="setMetric('vento')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'vento' ? 'active' : ''" :aria-pressed="metric === 'vento'">
                        <x-heroicon-o-arrow-path class="h-4 w-4" /> Fluxo de ventos
                    </button>
                    <button type="button" @click="setMetric('chuva')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'chuva' ? 'active' : ''" :aria-pressed="metric === 'chuva'">
                        <x-heroicon-o-cloud class="h-4 w-4" /> Precipitação (mm)
                    </button>
                    <button type="button" @click="setMetric('particulas')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'particulas' ? 'active' : ''" :aria-pressed="metric === 'particulas'">
                        <x-heroicon-o-funnel class="h-4 w-4" /> Qualidade do ar (PM2.5)
                    </button>
                    <button type="button" @click="setMetric('pressao')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'pressao' ? 'active' : ''" :aria-pressed="metric === 'pressao'">
                        <x-heroicon-o-scale class="h-4 w-4" /> Pressão atmosférica (hPa)
                    </button>
                    <button type="button" @click="setMetric('uv')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'uv' ? 'active' : ''" :aria-pressed="metric === 'uv'">
                        <x-heroicon-o-sparkles class="h-4 w-4" /> Índice UV
                    </button>
                </div>
            </div>

            <!-- Mapa Principal -->
            <div
                id="climate-map"
                data-style-url="{{ config('maps.base_style_url') }}"
                data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}"
                data-raster-attribution="{{ config('maps.raster_attribution') }}"
                data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}"
                data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}"
                data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                data-terrain-attribution="{{ config('maps.terrain.attribution') }}"
                data-terrain-pitch="52"
                style="width: 100%; height: 100%;"
            ></div>

            <div class="ccdae-map-panel wind-summary" x-show="metric === 'vento'" x-transition style="position:absolute;z-index:9999;right:18px;top:18px;width:190px;padding:14px">
                <div class="wind-summary-kicker" style="font-size:10px;text-transform:uppercase;letter-spacing:.12em">Campo de vento</div>
                <div style="display:flex;align-items:center;gap:14px;margin-top:8px">
                    <div style="transform-origin:center" :style="`transform:rotate(${windSummary.direction}deg)`"><x-heroicon-o-arrow-up class="h-9 w-9" /></div>
                    <div>
                        <div style="font-size:22px;font-weight:800" x-text="`${windSummary.speed} km/h`"></div>
                        <div class="wind-summary-detail" style="font-size:12px" x-text="`${windSummary.cardinal} · ${windSummary.description}`"></div>
                    </div>
                </div>
                <div class="wind-summary-help" style="font-size:10px;margin-top:8px">As setas mostram direção e intensidade por distrito.</div>
            </div>

            <div id="climate-map-legend" class="ccdae-map-panel climate-legend" style="position:absolute;right:18px;bottom:28px;z-index:20"></div>
            </div>

            <div class="climate-timeline">
                <div class="timeline-layout">
                    <button type="button" @click="togglePlay()" class="timeline-play" :title="isPlaying ? 'Pausar' : 'Reproduzir histórico'" :disabled="climaTimeline.length < 2" x-text="isPlaying ? 'Pausar' : 'Reproduzir'">
                    </button>
                    <div class="timeline-heading">
                        <div class="timeline-title-row">
                            <x-heroicon-o-clock />
                            <span class="timeline-title">Histórico climático</span>
                        </div>
                        <div class="timeline-subtitle">Leituras das últimas 24 horas</div>
                    </div>
                    <div class="timeline-control">
                        <div class="timeline-labels">
                            <span>24 horas atrás</span>
                            <span>Agora</span>
                        </div>
                        <div class="timeline-range-shell">
                            <output x-ref="timelineBubble" class="timeline-bubble" x-text="currentFrame ? currentFrame.label.split(' ').at(-1) : '--:--'"></output>
                            <input
                                x-ref="timelineRange"
                                class="timeline-range"
                                type="range"
                                min="0"
                                :max="Math.max(climaTimeline.length - 1, 0)"
                                step="1"
                                x-model.number="frameIndex"
                                @input="frameChanged()"
                                :style="timelineTrackStyle"
                                :aria-valuetext="currentFrame ? currentFrame.label : 'Sem histórico'"
                                aria-label="Horário da leitura climática"
                                :disabled="climaTimeline.length < 2"
                            >
                            <div class="timeline-ticks" aria-hidden="true">
                                <template x-for="(frame, index) in climaTimeline" :key="frame.timestamp">
                                    <button type="button" tabindex="-1" class="timeline-tick" :class="index === frameIndex ? 'active' : ''" :title="frame.label" @click="frameIndex = index; frameChanged()"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="timeline-selected">
                        <span class="timeline-selected-label">Leitura selecionada</span>
                        <strong class="timeline-selected-value" x-text="currentFrame ? currentFrame.label : 'Sem histórico'"></strong>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('climateMapPage', () => ({
                        metric: 'temperatura',
                        mapData: @json($this->climaMapData),
                        climaTimeline: @json($this->climaTimeline),
                        frameIndex: Math.max(@json(count($this->climaTimeline)) - 1, 0),
                        isPlaying: false,
                        playTimer: null,
                        resizeHandler: null,

                        get currentFrame() {
                            return this.climaTimeline[this.frameIndex] || null;
                        },

                        get climaData() {
                            const readings = new Map((this.currentFrame?.data || []).map(item => [item.id, item]));
                            return this.mapData.map(district => ({
                                ...district,
                                ...(readings.get(district.id) || {}),
                            }));
                        },

                        get timelineTrackStyle() {
                            const max = Math.max(this.climaTimeline.length - 1, 1);
                            const progress = (this.frameIndex / max) * 100;
                            return `--timeline-progress:${progress}%`;
                        },

                        get windSummary() {
                            const rows = this.climaData.filter(d => d.vento_velocidade !== null && d.vento_direcao !== null);
                            if (!rows.length) return { speed: 0, direction: 0, cardinal: 'N', description: 'sem dados' };
                            const speed = rows.reduce((sum, d) => sum + Number(d.vento_velocidade), 0) / rows.length;
                            const x = rows.reduce((sum, d) => sum + Math.sin(Number(d.vento_direcao) * Math.PI / 180), 0);
                            const y = rows.reduce((sum, d) => sum + Math.cos(Number(d.vento_direcao) * Math.PI / 180), 0);
                            const direction = (Math.atan2(x, y) * 180 / Math.PI + 360) % 360;
                            const cardinals = ['N','NE','L','SE','S','SO','O','NO'];
                            const description = speed < 6 ? 'brisa leve' : speed < 15 ? 'vento moderado' : speed < 30 ? 'vento forte' : 'ventania';
                            return { speed: speed.toFixed(1), direction, cardinal: cardinals[Math.round(direction / 45) % 8], description };
                        },

                        init() {
                            this.resizeHandler = () => this.syncTimelineBubble();
                            window.addEventListener('resize', this.resizeHandler);
                            setTimeout(() => {
                                this.publishMapState(true);
                                this.syncTimelineBubble();
                            }, 300);
                        },

                        syncTimelineBubble() {
                            const range = this.$refs.timelineRange;
                            const bubble = this.$refs.timelineBubble;
                            if (!range || !bubble) return;
                            const maximum = Math.max(this.climaTimeline.length - 1, 0);
                            const progress = maximum ? this.frameIndex / maximum : 0;
                            const thumbDiameter = 22;
                            const thumbRadius = thumbDiameter / 2;
                            const usableWidth = Math.max(0, range.clientWidth - thumbDiameter);
                            bubble.style.left = `${thumbRadius + usableWidth * progress}px`;
                            bubble.style.transform = 'translateX(-50%)';
                        },

                        setMetric(m) {
                            this.metric = m;
                            this.publishMapState(false);
                        },

                        frameChanged() {
                            this.stopPlayback();
                            this.publishMapState(false);
                            requestAnimationFrame(() => this.syncTimelineBubble());
                        },

                        togglePlay() {
                            if (this.isPlaying) {
                                this.stopPlayback();
                                return;
                            }
                            if (this.climaTimeline.length < 2) return;
                            this.isPlaying = true;
                            if (this.frameIndex >= this.climaTimeline.length - 1) this.frameIndex = 0;
                            this.playTimer = setInterval(() => {
                                if (this.frameIndex >= this.climaTimeline.length - 1) {
                                    this.stopPlayback();
                                    return;
                                }
                                this.frameIndex++;
                                this.publishMapState(false);
                                requestAnimationFrame(() => this.syncTimelineBubble());
                            }, 1200);
                        },

                        stopPlayback() {
                            this.isPlaying = false;
                            if (this.playTimer) clearInterval(this.playTimer);
                            this.playTimer = null;
                        },

                        publishMapState(fit = false) {
                            window.dispatchEvent(new CustomEvent('climate-map-data', {
                                detail: {
                                    metric: this.metric,
                                    data: this.climaData,
                                    fit,
                                },
                            }));
                        },

                        destroy() {
                            this.stopPlayback();
                            if (this.resizeHandler) window.removeEventListener('resize', this.resizeHandler);
                        },

                    }))
                });
            </script>
        </div>
    </x-filament::section>
</x-filament-panels::page>
