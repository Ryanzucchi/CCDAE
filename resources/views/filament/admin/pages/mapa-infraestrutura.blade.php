<x-filament-panels::page>
    @vite('resources/js/maps/infrastructure-map.js')

    <!-- Carregar Three.js e OrbitControls para a visualização 3D -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

    <style>
        /* Legendas e Controles */
        .map-legend {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }
        .legend-item { display: flex; align-items: center; margin-bottom: 4px; }
        .legend-color { width: 14px; height: 14px; border-radius: 50%; margin-right: 8px; display: inline-block; }
        .legend-line { width: 20px; height: 3px; margin-right: 8px; display: inline-block; }
        .infra-mode-switch, .infra-3d-panel, #three-tooltip {
            color: #111827;
            background: rgba(255, 255, 255, .92) !important;
            border: 1px solid rgba(148, 163, 184, .45) !important;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .16);
            backdrop-filter: blur(12px);
        }
        .dark .infra-mode-switch, .dark .infra-3d-panel, .dark #three-tooltip {
            color: #e5e7eb;
            background: rgba(15, 23, 42, .9) !important;
            border-color: rgba(100, 116, 139, .45) !important;
            box-shadow: 0 16px 35px rgba(0, 0, 0, .38);
        }
        #three-tooltip { --tooltip-title: #0369a1; --tooltip-text: #334155; --tooltip-border: rgba(15, 23, 42, .14); }
        .dark #three-tooltip { --tooltip-title: #38bdf8; --tooltip-text: #cbd5e1; --tooltip-border: rgba(255, 255, 255, .2); }
        .infra-3d-panel { position: absolute; right: 12px; bottom: 12px; z-index: 20; padding: 10px 12px; border-radius: 10px; font-size: 11px; line-height: 1.5; }
        .infra-3d-dot { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 999px; }
    </style>

    <x-filament::section>
        <div class="mb-4 flex flex-wrap gap-4 items-center justify-between">
            <div class="max-w-sm flex gap-4 w-72">
                <x-filament::input.wrapper class="w-full">
                    <x-filament::input.select wire:model.live="distrito_id">
                        <option value="">Ver todos os distritos (visão macro)</option>
                        @foreach($this->distritos as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <x-filament::input.checkbox id="infra-buildings-toggle" checked />
                Construções 3D
            </label>
        </div>

        <div wire:ignore x-data="infraMapPage" x-init="init()" class="ccdae-map-surface" style="height: 750px;">
            <!-- Abas Flutuantes de Modo (Superior Esquerdo) -->
            <div class="infra-mode-switch" style="position: absolute; top: 12px; left: 60px; z-index: 99999 !important; display: flex; gap: 6px; padding: 4px; border-radius: 8px; pointer-events: auto !important;">
                <button type="button"
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition duration-200 cursor-pointer"
                        style="cursor: pointer !important; pointer-events: auto !important;"
                        :class="mode === '2d' ? 'bg-primary-600 text-white font-black' : 'text-gray-400 hover:text-gray-200'"
                        @click="setMode('2d')">
                    Mapa 2D
                </button>
                <button type="button"
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition duration-200 cursor-pointer"
                        style="cursor: pointer !important; pointer-events: auto !important;"
                        :class="mode === '3d' ? 'bg-primary-600 text-white font-black' : 'text-gray-400 hover:text-gray-200'"
                        @click="setMode('3d')">
                    Visualizador 3D
                </button>
            </div>

            <!-- Aviso 3D quando nenhum distrito estiver selecionado -->
            <div id="infra-3d-warning"
                 x-show="mode === '3d' && (!cachedData || !cachedData.postes || cachedData.postes.length === 0)"
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: rgba(10, 15, 29, 0.95); z-index: 100; color: #fff; padding: 20px; text-align: center;">
                <h3 style="font-size: 20px; font-weight: bold; margin-bottom: 8px; color: #fff;">Visualizador 3D Requer um Distrito</h3>
                <p style="color: #94a3b8; max-width: 480px; font-size: 14px; line-height: 1.6;">
                    Para renderizar a maquete física tridimensional de postes, cabeamentos e equipamentos em tempo real com alta performance WebGL, selecione um distrito específico no menu superior.
                </p>
            </div>

            <!-- Tooltip Interativo do Three.js (3D) -->
            <div id="three-tooltip"
                 style="position: absolute; display: none; background: rgba(17, 24, 39, 0.95); color: #f3f4f6; padding: 12px; border-radius: 8px; font-size: 12px; border: 1px solid rgba(75, 85, 99, 0.5); z-index: 99999; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); pointer-events: auto; max-width: 250px;">
            </div>

            <!-- Contêineres de Visualização -->
            <div
                id="infra-map-container"
                x-show="mode === '2d'"
                data-martin-enabled="{{ config('maps.martin.enabled') ? 'true' : 'false' }}"
                data-martin-tilejson-url="{{ rtrim(config('maps.martin.tilejson_base_url'), '/') }}"
                data-style-url="{{ config('maps.base_style_url') }}"
                data-raster-tile-url="{{ config('maps.raster_tile_url') }}"
                data-raster-dark-tile-url="{{ config('maps.raster_dark_tile_url') }}"
                data-raster-attribution="{{ config('maps.raster_attribution') }}"
                data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}"
                data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}"
                data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                data-terrain-attribution="{{ config('maps.terrain.attribution') }}"
                style="width: 100%; height: 100%; z-index: 10;"
            ></div>
            <div
                id="infra-3d-container"
                x-show="mode === '3d'"
                data-terrain-enabled="{{ config('maps.terrain.enabled') ? 'true' : 'false' }}"
                data-terrain-tile-url="{{ config('maps.terrain.terrarium_tile_url') }}"
                data-terrain-zoom="{{ config('maps.terrain.zoom') }}"
                data-terrain-exaggeration="{{ config('maps.terrain.exaggeration') }}"
                style="width: 100%; height: 100%;"
            ></div>

            <div x-show="mode === '3d' && cachedData && cachedData.postes && cachedData.postes.length" class="infra-3d-panel">
                <div class="font-bold mb-1">Modelo físico da rede</div>
                <div><span class="infra-3d-dot" style="background:#94a3b8"></span>Postes: altura e material cadastrados</div>
                <div><span class="infra-3d-dot" style="background:#fbbf24"></span>Luminária instalada</div>
                <div><span class="infra-3d-dot" style="background:#22d3ee"></span>Fibra óptica</div>
                <div><span class="infra-3d-dot" style="background:#ef4444"></span>Rede elétrica / estado crítico</div>
                <div><span class="infra-3d-dot" style="background:#3f6f45"></span>Vegetação contextual (ilustrativa)</div>
                <div><span class="infra-3d-dot" style="background:#343a40; border:1px solid #cbd5e1"></span>Ruas e fluxo de trânsito</div>
                <div class="mt-1 opacity-70">Relevo: {{ config('maps.terrain.attribution') }}</div>
            </div>

            <div
                id="map-zoom-hint"
                x-show="mode === '2d'"
                class="absolute right-12 top-3 z-20 rounded-md bg-red-500/85 px-3 py-2 text-xs font-bold text-white shadow"
            >
                Aproxime o zoom (nível 15+) para ver postes, cabos e equipamentos
            </div>

            <div x-show="mode === '2d'" class="ccdae-map-panel map-legend absolute bottom-8 right-3 z-20">
                <strong>Mapeamento de Infraestrutura</strong>
                <div class="legend-item mt-2"><span class="legend-color" style="background:#f59e0b"></span>Centrais de Distribuição</div>
                <div class="legend-item"><span class="legend-color border border-black" style="background:#9ca3af"></span>Postes</div>
                <div class="legend-item"><span class="legend-color" style="background:#ec4899"></span>Antenas Telecom</div>
                <div class="legend-item"><span class="legend-color" style="background:#10b981"></span>Equipamentos</div>
                <div class="legend-item"><span class="legend-color" style="background:#78716c"></span>Construções 3D · OSM + GBA ODbL</div>
                <div class="legend-item mt-2"><strong>Fluxo de Trânsito</strong></div>
                <div class="legend-item"><span class="legend-line" style="background:#22c55e"></span>Via Livre</div>
                <div class="legend-item"><span class="legend-line" style="background:#eab308"></span>Moderado</div>
                <div class="legend-item"><span class="legend-line" style="background:#f97316"></span>Intenso</div>
                <div class="legend-item"><span class="legend-line" style="background:#ef4444"></span>Parado</div>
                <div class="legend-item mt-2"><strong>Cabeamento</strong></div>
                <div class="legend-item"><span class="legend-line" style="background:#06b6d4"></span>Fibra Óptica</div>
                <div class="legend-item"><span class="legend-line" style="background:#facc15"></span>Fibra Backbone</div>
                <div class="legend-item"><span class="legend-line" style="background:#ef4444"></span>Alta Tensão</div>
                <div class="legend-item"><span class="legend-line" style="background:#3b82f6"></span>Outros Cabeamentos</div>
            </div>

            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('infraMapPage', () => ({
                        cachedData: null,
                        mode: '2d',
                        distritoId: @json($this->distrito_id ?? ''),
                        detailsDistrictId: null,
                        scene3D: null,

                        init() {
                            setTimeout(() => {
                                this.fetchData(this.distritoId, false);
                            }, 300);

                            window.addEventListener('update-infra-map', (e) => {
                                let distrito_id = e.detail[0]?.distrito_id || '';
                                this.distritoId = distrito_id;
                                this.detailsDistrictId = null;
                                this.fetchData(distrito_id, false);
                            });

                            document.addEventListener('change', (event) => {
                                if (event.target?.id === 'infra-buildings-toggle' && this.mode === '3d' && this.cachedData) {
                                    this.renderThree(this.cachedData);
                                }
                            });
                        },

                        ensureScene3D() {
                            if (!this.scene3D && window.Infrastructure3DScene) {
                                this.scene3D = new window.Infrastructure3DScene('infra-3d-container', 'three-tooltip');
                            }
                            if (this.scene3D) {
                                this.scene3D.init();
                            }
                        },

                        setMode(m) {
                            this.mode = m;

                            let tooltipEl = document.getElementById('three-tooltip');
                            if (tooltipEl) tooltipEl.style.display = 'none';

                            if (m === '3d') {
                                if (this.distritoId && this.detailsDistrictId !== String(this.distritoId)) {
                                    this.fetchData(this.distritoId, true);
                                }
                                setTimeout(() => {
                                    this.ensureScene3D();
                                    if (this.cachedData && this.cachedData.postes && this.cachedData.postes.length > 0) {
                                        this.renderThree(this.cachedData);
                                    }
                                }, 50);
                            } else {
                                setTimeout(() => {
                                    window.dispatchEvent(new CustomEvent('infra-map-resize'));
                                }, 50);
                            }
                        },

                        fetchData(distritoId = null, includeFeatures = false) {
                            if (distritoId === null) {
                                distritoId = this.distritoId;
                            }
                            this.distritoId = distritoId || '';

                            let mapContainerEl = document.getElementById('infra-map-container');
                            let container3D = document.getElementById('infra-3d-container');

                            if (mapContainerEl) mapContainerEl.style.opacity = '0.6';
                            if (container3D) container3D.style.opacity = '0.6';

                            const includeFeaturesParam = includeFeatures ? '1' : '0';
                            return fetch(`/api/infraestrutura/geojson?distrito_id=${distritoId}&include_features=${includeFeaturesParam}`)
                                .then(res => res.json())
                                .then(payload => {
                                    this.cachedData = payload;
                                    if (includeFeatures && distritoId) {
                                        this.detailsDistrictId = String(distritoId);
                                    }
                                    window.dispatchEvent(new CustomEvent('infra-map-data', {
                                        detail: { data: payload, fit: true, distritoId }
                                    }));

                                    if (this.mode === '3d' && payload.postes && payload.postes.length > 0) {
                                        this.renderThree(payload);
                                    }

                                    if (mapContainerEl) mapContainerEl.style.opacity = '1';
                                    if (container3D) container3D.style.opacity = '1';
                                })
                                .catch(err => {
                                    console.error('Error fetching infra data:', err);
                                    if (mapContainerEl) mapContainerEl.style.opacity = '1';
                                    if (container3D) container3D.style.opacity = '1';
                                });
                        },

                        renderThree(data) {
                            this.ensureScene3D();
                            if (this.scene3D) {
                                const buildingsToggle = document.getElementById('infra-buildings-toggle');
                                const buildingsEnabled = !buildingsToggle || buildingsToggle.checked;
                                this.scene3D.render(data, buildingsEnabled);
                            }
                        }
                    }))
                });
            </script>
        </div>
    </x-filament::section>
</x-filament-panels::page>
