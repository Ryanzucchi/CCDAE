<x-filament-panels::page>
    <style>
        /* Estilos do Leaflet e UI */
        .leaflet-pane svg,
        .leaflet-pane canvas {
            max-width: none !important;
            display: block !important;
        }
        .climate-legend {
            background: rgba(255, 255, 255, 0.96);
            color: #111827;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
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
            background: #2563eb;
            color: #fff;
            border-color: #3b82f6;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }

        /* Reset Leaflet divIcon */
        .wind-arrow,
        .wind-flow-arrow {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        .wind-flow-arrow svg { filter: drop-shadow(0 0 5px rgba(125, 211, 252, .8)); }
        .wind-flow-line { animation: wind-flow 1.4s linear infinite; }
        @keyframes wind-flow { to { stroke-dashoffset: -20; } }
        .timeline-range {
            appearance: none;
            width: 100%;
            height: 8px;
            border-radius: 9999px;
            outline: none;
            cursor: pointer;
        }
        .timeline-range::-webkit-slider-thumb {
            appearance: none;
            width: 22px;
            height: 22px;
            border-radius: 9999px;
            background: rgb(var(--primary-600));
            border: 4px solid #fff;
            box-shadow: 0 1px 7px rgba(0,0,0,.28);
        }
        .timeline-range::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 9999px;
            background: rgb(var(--primary-600));
            border: 4px solid #fff;
            box-shadow: 0 1px 7px rgba(0,0,0,.28);
        }
        .timeline-tick { width: 8px; height: 8px; padding: 0; border: 0; border-radius: 9999px; background: #d1d5db; transition: .2s; cursor: pointer; }
        .timeline-tick.active { background: rgb(var(--primary-600)); transform: scale(1.35); }
        .climate-timeline { overflow: hidden; margin-top: 16px; border-radius: 12px; background: #fff; border: 1px solid rgba(17,24,39,.1); box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .timeline-layout { display: grid; grid-template-columns: auto minmax(150px, 220px) minmax(280px, 1fr) minmax(120px, auto); align-items: center; gap: 20px; padding: 18px 20px; }
        .timeline-play { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border: 0; border-radius: 9999px; background: rgb(var(--primary-600)); color: #fff; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,.16); }
        .timeline-play:hover { background: rgb(var(--primary-500)); }
        .timeline-play svg { width: 18px !important; height: 18px !important; display: block; }
        .timeline-heading { min-width: 0; }
        .timeline-title-row { display: flex; align-items: center; gap: 8px; color: #111827; }
        .timeline-title-row svg { width: 18px !important; height: 18px !important; flex: 0 0 18px; color: rgb(var(--primary-600)); }
        .timeline-title { font-size: 14px; font-weight: 700; line-height: 1.25; }
        .timeline-subtitle { margin-top: 3px; font-size: 12px; line-height: 1.35; color: #6b7280; }
        .timeline-control { min-width: 0; }
        .timeline-labels { display: flex; justify-content: space-between; margin-bottom: 9px; font-size: 11px; font-weight: 600; color: #6b7280; }
        .timeline-ticks { display: flex; justify-content: space-between; align-items: center; padding: 0 4px; margin-top: 10px; }
        .timeline-selected { padding-left: 18px; border-left: 1px solid #e5e7eb; text-align: right; }
        .timeline-selected-label { display: block; font-size: 10px; font-weight: 600; line-height: 1.2; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }
        .timeline-selected-value { display: block; margin-top: 4px; font-size: 15px; font-weight: 700; line-height: 1.2; color: #111827; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .dark .climate-timeline { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .dark .timeline-title, .dark .timeline-selected-value { color: #fff; }
        .dark .timeline-subtitle, .dark .timeline-labels, .dark .timeline-selected-label { color: #9ca3af; }
        .dark .timeline-selected { border-color: rgba(255,255,255,.1); }
        @media (max-width: 900px) {
            .timeline-layout { grid-template-columns: auto 1fr auto; gap: 14px; }
            .timeline-control { grid-column: 1 / -1; grid-row: 2; }
        }
        .dark .timeline-tick { background: #4b5563; }
        .dark .timeline-tick.active { background: rgb(var(--primary-400)); }
        .heat-blob-icon { background: transparent !important; border: 0 !important; }
        .heat-blob {
            width: 90px;
            height: 90px;
            border-radius: 9999px;
            transform: translate(-50%, -50%);
            opacity: .42;
            filter: blur(8px);
            pointer-events: none;
        }
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
        .dark .wind-summary { background: rgba(15, 23, 42, .92); color: #e0f2fe; border-color: rgba(56, 189, 248, .45); }
        .dark .wind-summary-kicker { color: #7dd3fc; }
        .dark .wind-summary-detail { color: #bae6fd; }
        .dark .wind-summary-help { color: #94a3b8; }
    </style>

    <x-filament::section>
        <div wire:ignore x-data="climateMapPage" x-init="init()" class="space-y-4">
            <div style="position: relative; border-radius: 0.75rem; overflow: hidden; height: 750px; background: #0a0f1d;">
            
            <!-- Controle de Filtros e Camadas Climáticas (Flutuante Superior Esquerdo) -->
            <div class="climate-controls" style="position: absolute; top: 12px; left: 60px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; padding: 8px; border-radius: 10px; max-width: 280px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.16);">
                <div class="climate-controls-title" style="font-weight: bold; font-size: 13px; border-bottom-width: 1px; padding-bottom: 4px; margin-bottom: 4px;">
                    Variáveis climáticas
                </div>
                <div style="display: grid; grid-template-columns: 1fr; gap: 6px;">
                    <button type="button" @click="setMetric('temperatura')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'temperatura' ? 'active' : ''">
                        <x-heroicon-o-fire class="h-4 w-4" /> Temperatura (°C)
                    </button>
                    <button type="button" @click="setMetric('radiacao')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'radiacao' ? 'active' : ''">
                        <x-heroicon-o-sun class="h-4 w-4" /> Radiação solar (W/m²)
                    </button>
                    <button type="button" @click="setMetric('vento')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'vento' ? 'active' : ''">
                        <x-heroicon-o-arrow-path class="h-4 w-4" /> Fluxo de ventos
                    </button>
                    <button type="button" @click="setMetric('chuva')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'chuva' ? 'active' : ''">
                        <x-heroicon-o-cloud class="h-4 w-4" /> Precipitação (mm)
                    </button>
                    <button type="button" @click="setMetric('particulas')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'particulas' ? 'active' : ''">
                        <x-heroicon-o-funnel class="h-4 w-4" /> Qualidade do ar (PM2.5)
                    </button>
                    <button type="button" @click="setMetric('pressao')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'pressao' ? 'active' : ''">
                        <x-heroicon-o-scale class="h-4 w-4" /> Pressão atmosférica (hPa)
                    </button>
                    <button type="button" @click="setMetric('uv')" class="climate-btn px-3 py-1.5 rounded-md text-xs text-left flex items-center gap-2" :class="metric === 'uv' ? 'active' : ''">
                        <x-heroicon-o-sparkles class="h-4 w-4" /> Índice UV
                    </button>
                </div>
            </div>

            <!-- Mapa Principal -->
            <div id="climate-map" style="width: 100%; height: 100%;"></div>

            <div class="wind-summary" x-show="metric === 'vento'" x-transition style="position:absolute;z-index:9999;right:18px;top:18px;width:190px;padding:14px;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.16)">
                <div class="wind-summary-kicker" style="font-size:10px;text-transform:uppercase;letter-spacing:.12em">Campo de vento</div>
                <div style="display:flex;align-items:center;gap:14px;margin-top:8px">
                    <div style="transform-origin:center" :style="`transform:rotate(${windSummary.direction}deg)`"><x-heroicon-o-arrow-up class="h-9 w-9" /></div>
                    <div>
                        <div style="font-size:22px;font-weight:800" x-text="`${windSummary.speed} km/h`"></div>
                        <div class="wind-summary-detail" style="font-size:12px" x-text="`${windSummary.cardinal} · ${windSummary.description}`"></div>
                    </div>
                </div>
                <div class="wind-summary-help" style="font-size:10px;margin-top:8px">As trilhas animadas mostram direção e intensidade por distrito.</div>
            </div>
            </div>

            <div class="climate-timeline">
                <div class="timeline-layout">
                    <button type="button" @click="togglePlay()" class="timeline-play" :title="isPlaying ? 'Pausar' : 'Reproduzir histórico'">
                        <x-heroicon-s-pause x-show="isPlaying" class="h-5 w-5" />
                        <x-heroicon-s-play x-show="!isPlaying" class="h-5 w-5 translate-x-px" />
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
                        <input
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
                                <button type="button" tabindex="-1" class="timeline-tick" :class="index <= frameIndex ? 'active' : ''" @click="frameIndex = index; frameChanged()"></button>
                            </template>
                        </div>
                    </div>
                    <div class="timeline-selected">
                        <span class="timeline-selected-label">Leitura selecionada</span>
                        <strong class="timeline-selected-value" x-text="currentFrame ? currentFrame.label : 'Sem histórico'"></strong>
                    </div>
                </div>
            </div>

            <script>
                let cMap = null;
                let cLayerGroup = null;
                let cLegendControl = null;

                document.addEventListener('alpine:init', () => {
                    Alpine.data('climateMapPage', () => ({
                        metric: 'temperatura',
                        climaTimeline: @json($this->climaTimeline),
                        frameIndex: Math.max(@json(count($this->climaTimeline)) - 1, 0),
                        isPlaying: false,
                        playTimer: null,

                        get currentFrame() {
                            return this.climaTimeline[this.frameIndex] || null;
                        },

                        get climaData() {
                            return this.currentFrame?.data || [];
                        },

                        get timelineTrackStyle() {
                            const max = Math.max(this.climaTimeline.length - 1, 1);
                            const progress = (this.frameIndex / max) * 100;
                            const inactive = document.documentElement.classList.contains('dark') ? 'rgb(75 85 99)' : 'rgb(229 231 235)';
                            return `background:linear-gradient(to right, rgb(var(--primary-600)) 0%, rgb(var(--primary-600)) ${progress}%, ${inactive} ${progress}%, ${inactive} 100%)`;
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
                            setTimeout(() => {
                                this.initMap();
                                this.renderLayers();
                            }, 300);
                        },

                        setMetric(m) {
                            this.metric = m;
                            this.renderLayers();
                        },

                        frameChanged() {
                            this.stopPlayback();
                            this.renderLayers();
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
                                this.renderLayers();
                            }, 1200);
                        },

                        stopPlayback() {
                            this.isPlaying = false;
                            if (this.playTimer) clearInterval(this.playTimer);
                            this.playTimer = null;
                        },

                        initMap() {
                            let mapContainer = document.getElementById('climate-map');
                            if (!mapContainer) return;

                            if (cMap !== null) {
                                cMap.remove();
                                cMap = null;
                            }

                            cMap = L.map('climate-map', {
                                preferCanvas: true,
                                maxZoom: 18
                            }).setView([-16.070830, -57.687500], 12);

                            cLayerGroup = L.featureGroup().addTo(cMap);

                            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                                attribution: '&copy; OpenStreetMap &copy; CARTO'
                            }).addTo(cMap);
                        },

                        // Escalas de Cores
                        getColorForValue(val) {
                            if (val === null || val === undefined) return '#4b5563'; // Cinza (Sem dados)

                            if (this.metric === 'temperatura') {
                                return val > 36 ? '#dc2626' : // Vermelho forte
                                       val > 32 ? '#f97316' : // Laranja
                                       val > 28 ? '#facc15' : // Amarelo
                                       val > 24 ? '#4ade80' : // Verde claro
                                                  '#3b82f6';  // Azul
                            }

                            if (this.metric === 'radiacao') {
                                return val > 800 ? '#ef4444' : // Vermelho solar
                                       val > 500 ? '#f97316' : // Laranja
                                       val > 200 ? '#eab308' : // Amarelo escuro
                                       val > 50  ? '#a16207' : // Âmbar com contraste no mapa claro
                                                   '#475569';  // Cinza ardósia
                            }

                            if (this.metric === 'chuva') {
                                return val > 40 ? '#1e3a8a' : // Azul profundo
                                       val > 20 ? '#2563eb' : // Azul
                                       val > 5  ? '#60a5fa' : // Azul claro
                                       val > 0  ? '#bfdbfe' : // Celeste
                                                  '#e5e7eb';  // Cinza (Sem chuva)
                            }

                            if (this.metric === 'particulas') {
                                return val > 120 ? '#7f1d1d' : // Péssimo
                                       val > 80  ? '#ef4444' : // Ruim
                                       val > 40  ? '#eab308' : // Moderado
                                                   '#22c55e';  // Bom
                            }

                            if (this.metric === 'vento') {
                                // Para vento no mapa coroplético, colorimos pelo valor da velocidade
                                return val > 30 ? '#dc2626' : // Ventania
                                       val > 15 ? '#eab308' : // Moderado
                                                  '#22c55e';  // Brisa leve
                            }

                            if (this.metric === 'pressao') {
                                return val > 1020 ? '#a78bfa' :
                                       val > 1010 ? '#60a5fa' :
                                       val > 1000 ? '#22c55e' :
                                                    '#f59e0b';
                            }

                            if (this.metric === 'uv') {
                                return val >= 11 ? '#7f1d1d' :
                                       val >= 8  ? '#dc2626' :
                                       val >= 6  ? '#f97316' :
                                       val >= 3  ? '#facc15' :
                                                   '#22c55e';
                            }

                            return '#4b5563';
                        },

                        getMetricValue(d) {
                            return {
                                temperatura: d.temperatura,
                                radiacao: d.radiacao,
                                chuva: d.chuva,
                                particulas: d.particulas,
                                pressao: d.pressao,
                                uv: d.uv,
                                vento: d.vento_velocidade,
                            }[this.metric] ?? null;
                        },

                        getHeatGradient() {
                            const gradients = {
                                temperatura: { 0.0:'#2563eb', 0.35:'#22c55e', 0.6:'#fde047', 0.82:'#f97316', 1.0:'#dc2626' },
                                radiacao: { 0.0:'#475569', 0.3:'#a16207', 0.65:'#eab308', 1.0:'#ef4444' },
                                chuva: { 0.0:'#dbeafe', 0.3:'#60a5fa', 0.65:'#2563eb', 1.0:'#172554' },
                                particulas: { 0.0:'#22c55e', 0.4:'#facc15', 0.72:'#ef4444', 1.0:'#7f1d1d' },
                                pressao: { 0.0:'#f59e0b', 0.4:'#22c55e', 0.7:'#60a5fa', 1.0:'#a78bfa' },
                                uv: { 0.0:'#22c55e', 0.35:'#facc15', 0.6:'#f97316', 0.82:'#dc2626', 1.0:'#7f1d1d' },
                            };
                            return gradients[this.metric] || gradients.temperatura;
                        },

                        renderLayers() {
                            if (!cLayerGroup || !cMap) return;
                            cLayerGroup.clearLayers();

                            let bounds = [];
                            const heatCandidates = this.metric === 'vento'
                                ? []
                                : this.climaData
                                    .map(d => this.getMetricValue(d))
                                    .filter(value => value !== null && value !== undefined)
                                    .map(Number)
                                    .filter(Number.isFinite);
                            const heatMin = heatCandidates.length ? Math.min(...heatCandidates) : 0;
                            const heatMax = heatCandidates.length ? Math.max(...heatCandidates) : 1;
                            const heatRange = heatMax - heatMin;
                            const heatPoints = [];

                            this.climaData.forEach(d => {
                                let geo = null;
                                if (d.geojson) {
                                    try {
                                        geo = typeof d.geojson === 'string' ? JSON.parse(d.geojson) : d.geojson;
                                    } catch(e) {}
                                }

                                if (geo) {
                                    let valueToColor = null;
                                    let labelValue = 'N/A';

                                    if (this.metric === 'temperatura') {
                                        valueToColor = d.temperatura;
                                        labelValue = d.temperatura ? `${d.temperatura}°C (Sensation: ${d.sensacao_termica || d.temperatura}°C)` : 'Sem Registro';
                                    } else if (this.metric === 'radiacao') {
                                        valueToColor = d.radiacao;
                                        labelValue = d.radiacao ? `${d.radiacao} W/m² (UV: ${d.uv || 0})` : 'Sem Registro';
                                    } else if (this.metric === 'chuva') {
                                        valueToColor = d.chuva;
                                        labelValue = d.chuva !== null ? `${d.chuva} mm` : '0 mm (Seco)';
                                    } else if (this.metric === 'particulas') {
                                        valueToColor = d.particulas;
                                        labelValue = d.particulas ? `${d.particulas} PM2.5` : 'Bom / Sem dados';
                                    } else if (this.metric === 'vento') {
                                        valueToColor = d.vento_velocidade;
                                        labelValue = d.vento_velocidade !== null ? `${d.vento_velocidade} km/h (${d.vento_direcao ?? 0}°)` : 'Sem Registro';
                                    } else if (this.metric === 'pressao') {
                                        valueToColor = d.pressao;
                                        labelValue = d.pressao !== null ? `${d.pressao} hPa` : 'Sem Registro';
                                    } else if (this.metric === 'uv') {
                                        valueToColor = d.uv;
                                        labelValue = d.uv !== null ? `UV ${d.uv}` : 'Sem Registro';
                                    }

                                    let color = this.metric === 'vento' ? this.getColorForValue(valueToColor) : '#94a3b8';

                                    // Camada Coroplética de Distrito
                                    let polygonLayer = L.geoJSON(geo, {
                                        style: {
                                            fillColor: color,
                                            fillOpacity: this.metric === 'vento' ? 0.2 : 0,
                                            color: this.metric === 'vento' ? color : '#475569',
                                            weight: this.metric === 'vento' ? 1.4 : 0.65,
                                            opacity: this.metric === 'vento' ? 0.75 : 0.3
                                        }
                                    });

                                    // Popups completos com todas as variáveis climáticas integradas
                                    const popupHtml = `
                                        <div style="min-width: 200px; font-family: sans-serif; font-size:12px; line-height:1.6;">
                                            <div style="font-weight: bold; border-bottom: 2px solid #3b82f6; padding-bottom: 4px; margin-bottom: 8px; color: #1e3a8a; text-transform: uppercase;">
                                                ${d.nome}
                                            </div>
                                            <b>Temperatura:</b> ${d.temperatura ? `${d.temperatura}°C` : 'N/A'}<br>
                                            <b>Sensação Térmica:</b> ${d.sensacao_termica ? `${d.sensacao_termica}°C` : 'N/A'}<br>
                                            <b>Radiação solar:</b> ${d.radiacao ? `${d.radiacao} W/m²` : 'N/A'} (UV: ${d.uv || '0'})<br>
                                            <b>Vento:</b> ${d.vento_velocidade ? `${d.vento_velocidade} km/h às ${d.vento_direcao}°` : 'N/A'}<br>
                                            <b>Precipitação:</b> ${d.chuva ? `${d.chuva} mm` : '0 mm'}<br>
                                            <b>PM2.5:</b> ${d.particulas ? `${d.particulas} µg/m³` : 'N/A'}<br>
                                            <b>Pressão:</b> ${d.pressao ? `${d.pressao} hPa` : 'N/A'}<br>
                                            <div style="font-size: 10px; color:#6b7280; margin-top:8px; border-top:1px solid #e5e7eb; padding-top:4px;">
                                                Última leitura: ${d.timestamp || 'N/A'}
                                            </div>
                                        </div>
                                    `;

                                    polygonLayer.bindPopup(popupHtml);

                                    // Interatividade hover
                                    const baseFillOpacity = this.metric === 'vento' ? 0.2 : 0;
                                    const hoverFillOpacity = this.metric === 'vento' ? 0.42 : 0.05;
                                    const baseColor = this.metric === 'vento' ? color : '#475569';
                                    polygonLayer.on('mouseover', function (e) {
                                        let layer = e.target;
                                        layer.setStyle({
                                            weight: 3,
                                            color: '#3b82f6',
                                            fillOpacity: hoverFillOpacity
                                        });
                                    });
                                    polygonLayer.on('mouseout', function (e) {
                                        let layer = e.target;
                                        layer.setStyle({
                                            weight: 0.8,
                                            color: baseColor,
                                            fillOpacity: baseFillOpacity
                                        });
                                    });

                                    cLayerGroup.addLayer(polygonLayer);

                                    if (this.metric !== 'vento' && polygonLayer.getBounds().isValid() && valueToColor !== null) {
                                        const center = polygonLayer.getBounds().getCenter();
                                        const numericValue = Number(valueToColor);
                                        const normalized = heatRange > 0 ? (numericValue - heatMin) / heatRange : 0.72;
                                        heatPoints.push([center.lat, center.lng, Math.max(0.18, normalized), numericValue]);
                                    }

                                    // Desenhar fluxo de ventos com setas contínuas (campo vetorial)
                                    if (this.metric === 'vento' && d.vento_velocidade !== null && d.vento_direcao !== null) {
                                        try {
                                            const bounds = polygonLayer.getBounds();
                                            const center = bounds.getCenter();
                                            const latMin = bounds.getSouthWest().lat;
                                            const latMax = bounds.getNorthEast().lat;
                                            const lngMin = bounds.getSouthWest().lng;
                                            const lngMax = bounds.getNorthEast().lng;
                                            
                                            const dLat = latMax - latMin;
                                            const dLng = lngMax - lngMin;

                                            // 5 pontos de fluxo contínuo distribuídos pelo distrito
                                            const points = [
                                                [center.lat, center.lng],
                                                [center.lat - dLat * 0.22, center.lng - dLng * 0.22],
                                                [center.lat + dLat * 0.22, center.lng + dLng * 0.22],
                                                [center.lat - dLat * 0.22, center.lng + dLng * 0.22],
                                                [center.lat + dLat * 0.22, center.lng - dLng * 0.22]
                                            ];

                                            const arrowColor = this.getColorForValue(d.vento_velocidade);
                                            
                                            points.forEach(pt => {
                                                // Seta contínua (streamline wind arrow)
                                                // Orientada horizontalmente (aponta para a direita a 90 graus), rotacionamos por (direção - 90)
                                                const scale = 0.65 + (d.vento_velocidade * 0.015); // Seta maior para vento mais forte
                                                const rotation = d.vento_direcao - 90;

                                                const arrowHtml = `
                                                    <div style="transform: rotate(${rotation}deg) scale(${scale}); width: 44px; height: 16px; display: flex; align-items: center; justify-content: center; opacity: 0.85; pointer-events: none;">
                                                        <svg width="44" height="16" viewBox="0 0 44 16" style="overflow: visible;">
                                                            <!-- Linha contínua do fluxo de vento -->
                                                            <line class="wind-flow-line" x1="0" y1="8" x2="32" y2="8" stroke="${arrowColor}" stroke-width="3" stroke-linecap="round" stroke-dasharray="7 4" />
                                                            <!-- Cabeça de seta afilada contínua -->
                                                            <path d="M30,3 L39,8 L30,13 Z" fill="${arrowColor}" stroke="#ffffff" stroke-width="0.5" />
                                                        </svg>
                                                    </div>
                                                `;

                                                const arrowIcon = L.divIcon({
                                                    className: 'wind-flow-arrow',
                                                    html: arrowHtml,
                                                    iconSize: [44, 16],
                                                    iconAnchor: [22, 8]
                                                });

                                                let marker = L.marker([pt[0], pt[1]], { icon: arrowIcon, interactive: true, zIndexOffset: 500 });
                                                marker.bindPopup(popupHtml);
                                                cLayerGroup.addLayer(marker);
                                            });
                                        } catch(e) {}
                                    }

                                    let layerBounds = polygonLayer.getBounds();
                                    bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                    bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                }
                            });

                            if (this.metric !== 'vento' && heatPoints.length) {
                                heatPoints.forEach(([lat, lng, intensity, rawValue], index) => {
                                    const blobColor = this.getColorForValue(rawValue);
                                    const size = 64 + (intensity * 42);
                                    const blob = L.divIcon({
                                        className: 'heat-blob-icon',
                                        html: `<div class="heat-blob" style="width:${size}px;height:${size}px;background:radial-gradient(circle, ${blobColor}b3 0%, ${blobColor}70 28%, ${blobColor}24 58%, transparent 78%)"></div>`,
                                        iconSize: [1, 1],
                                        iconAnchor: [0, 0],
                                    });
                                    cLayerGroup.addLayer(L.marker([lat, lng], { icon: blob, interactive: false, zIndexOffset: -200 }));
                                });
                            }

                            if (bounds.length > 0) {
                                cMap.fitBounds(bounds, { padding: [20, 20] });
                            }

                            this.updateLegend();
                        },

                        updateLegend() {
                            if (cLegendControl) {
                                cMap.removeControl(cLegendControl);
                            }

                            cLegendControl = L.control({ position: 'bottomright' });
                            let currentMetric = this.metric;

                            cLegendControl.onAdd = function (map) {
                                let div = L.DomUtil.create('div', 'climate-legend');
                                let title = '';
                                let grades = [];
                                let colors = [];

                                if (currentMetric === 'temperatura') {
                                    title = 'Temperatura (°C)';
                                    grades = ['< 24°C', '24°C - 28°C', '28°C - 32°C', '32°C - 36°C', '> 36°C'];
                                    colors = ['#3b82f6', '#4ade80', '#facc15', '#f97316', '#dc2626'];
                                } else if (currentMetric === 'radiacao') {
                                    title = 'Radiação solar (W/m²)';
                                    grades = ['< 50', '50 - 200', '200 - 500', '500 - 800', '> 800'];
                                    colors = ['#475569', '#a16207', '#eab308', '#f97316', '#ef4444'];
                                } else if (currentMetric === 'chuva') {
                                    title = 'Precipitação (mm)';
                                    grades = ['Sem Chuva', '0 - 5 mm', '5 - 20 mm', '20 - 40 mm', '> 40 mm'];
                                    colors = ['#e5e7eb', '#bfdbfe', '#60a5fa', '#2563eb', '#1e3a8a'];
                                } else if (currentMetric === 'particulas') {
                                    title = 'Qualidade do ar (PM2.5)';
                                    grades = ['Bom (<40)', 'Moderado (40-80)', 'Ruim (80-120)', 'Péssimo (>120)'];
                                    colors = ['#22c55e', '#eab308', '#ef4444', '#7f1d1d'];
                                } else if (currentMetric === 'vento') {
                                    title = 'Velocidade do vento (km/h)';
                                    grades = ['Leve (<15 km/h)', 'Moderado (15-30 km/h)', 'Ventania (>30 km/h)'];
                                    colors = ['#22c55e', '#eab308', '#dc2626'];
                                } else if (currentMetric === 'pressao') {
                                    title = 'Pressão atmosférica (hPa)';
                                    grades = ['< 1000', '1000 - 1010', '1010 - 1020', '> 1020'];
                                    colors = ['#f59e0b', '#22c55e', '#60a5fa', '#a78bfa'];
                                } else if (currentMetric === 'uv') {
                                    title = 'Índice UV';
                                    grades = ['Baixo (0-2)', 'Moderado (3-5)', 'Alto (6-7)', 'Muito alto (8-10)', 'Extremo (11+)'];
                                    colors = ['#22c55e', '#facc15', '#f97316', '#dc2626', '#7f1d1d'];
                                }

                                div.innerHTML = `<strong>${title}</strong><br><div style="margin-top:6px;"></div>`;
                                for (let i = 0; i < grades.length; i++) {
                                    div.innerHTML += `
                                        <div class="legend-item">
                                            <span class="legend-color" style="background:${colors[i]}; border: 1px solid rgba(255,255,255,0.2)"></span>
                                            ${grades[i]}
                                        </div>
                                    `;
                                }
                                return div;
                            };

                            cLegendControl.addTo(cMap);
                        }
                    }))
                });
            </script>
        </div>
    </x-filament::section>
</x-filament-panels::page>
