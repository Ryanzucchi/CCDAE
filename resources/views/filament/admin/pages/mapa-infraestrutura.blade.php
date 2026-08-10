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
                // Variáveis Globais Three.js
                let scene3D = null;
                let camera3D = null;
                let renderer3D = null;
                let controls3D = null;
                let animationFrameId = null;
                let raycaster3D = null;

                document.addEventListener('alpine:init', () => {
                    Alpine.data('infraMapPage', () => ({
                        cachedData: null,
                        mode: '2d',
                        distritoId: @json($this->distrito_id ?? ''),
                        detailsDistrictId: null,
                        terrainSampler: null,
                        terrainRequestKey: null,

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
                        },

                        setMode(m) {
                            this.mode = m;

                            // Fechar tooltips/popups ao mudar de modo
                            let tooltipEl = document.getElementById('three-tooltip');
                            if (tooltipEl) tooltipEl.style.display = 'none';

                            if (m === '3d') {
                                if (this.distritoId && this.detailsDistrictId !== String(this.distritoId)) {
                                    this.fetchData(this.distritoId, true);
                                }
                                setTimeout(() => {
                                    this.initThree();
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

                        initThree() {
                            const container = document.getElementById('infra-3d-container');
                            if (!container || renderer3D) return;

                            const width = container.clientWidth;
                            const height = container.clientHeight || 750;

                            scene3D = new THREE.Scene();
                            const isDark = document.documentElement.classList.contains('dark');
                            scene3D.background = new THREE.Color(isDark ? 0x07111f : 0xdbeafe);
                            scene3D.fog = new THREE.Fog(isDark ? 0x07111f : 0xdbeafe, 700, 4200);

                            camera3D = new THREE.PerspectiveCamera(45, width / height, 1, 10000);
                            camera3D.position.set(0, 200, 450);

                            renderer3D = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
                            renderer3D.setSize(width, height);
                            renderer3D.setPixelRatio(Math.min(window.devicePixelRatio, 2));
                            renderer3D.outputEncoding = THREE.sRGBEncoding;
                            renderer3D.toneMapping = THREE.ACESFilmicToneMapping;
                            renderer3D.toneMappingExposure = isDark ? 0.72 : 0.95;
                            renderer3D.shadowMap.enabled = true;
                            renderer3D.shadowMap.type = THREE.PCFSoftShadowMap;
                            container.appendChild(renderer3D.domElement);

                            // Fallback de inicialização de controle de órbita robusto
                            try {
                                const ControlsClass = THREE.OrbitControls || window.OrbitControls || OrbitControls;
                                if (!ControlsClass) {
                                    throw new Error("OrbitControls is not defined. Check CDN imports.");
                                }
                                controls3D = new ControlsClass(camera3D, renderer3D.domElement);
                                controls3D.enableDamping = true;
                                controls3D.dampingFactor = 0.05;
                                controls3D.maxPolarAngle = Math.PI / 2 - 0.01;
                                controls3D.minDistance = 1; // Permite zoom de alta proximidade física
                                controls3D.maxDistance = 4000;
                            } catch(err) {
                                console.error("Error loading OrbitControls:", err);
                            }

                            // Raycasting para click nos elementos 3D
                            raycaster3D = new THREE.Raycaster();
                            raycaster3D.params.Line.threshold = 0.6; // Margem de erro em metros para facilitar clique em cabos finos

                            let startX = 0, startY = 0;
                            container.addEventListener('pointerdown', (e) => {
                                startX = e.clientX;
                                startY = e.clientY;
                            });

                            container.addEventListener('pointerup', (e) => {
                                const dx = e.clientX - startX;
                                const dy = e.clientY - startY;
                                // Se o mouse moveu menos de 4px, é considerado um clique puro, não um arrasto de câmera
                                if (Math.sqrt(dx*dx + dy*dy) < 4) {
                                    this.handleThreeClick(e);
                                }
                            });

                            if (controls3D) {
                                controls3D.addEventListener('change', () => {
                                    let tooltip = document.getElementById('three-tooltip');
                                    if (tooltip) tooltip.style.display = 'none';
                                });
                            }

                            const ground = new THREE.Mesh(
                                new THREE.PlaneGeometry(6000, 6000),
                                new THREE.MeshStandardMaterial({ color: isDark ? 0x0b1728 : 0xcbd5e1, roughness: 1 })
                            );
                            ground.rotation.x = -Math.PI / 2;
                            ground.position.y = -0.04;
                            ground.receiveShadow = true;
                            ground.userData.persistent = true;
                            scene3D.add(ground);

                            // Grid Ground
                            const gridHelper = new THREE.GridHelper(6000, 120, isDark ? 0x334155 : 0x64748b, isDark ? 0x172033 : 0x94a3b8);
                            gridHelper.userData.persistent = true;
                            gridHelper.userData.themeRole = 'grid';
                            scene3D.add(gridHelper);

                            // Lights
                            const ambient = new THREE.HemisphereLight(isDark ? 0x93c5fd : 0xffffff, isDark ? 0x0f172a : 0x64748b, 1.15);
                            scene3D.add(ambient);

                            const dir1 = new THREE.DirectionalLight(0xffffff, 1.3); // Luz direcional forte
                            dir1.position.set(400, 800, 400);
                            dir1.castShadow = true;
                            dir1.shadow.mapSize.set(1024, 1024);
                            scene3D.add(dir1);

                            const dir2 = new THREE.DirectionalLight(0x38bdf8, 0.6); // Luz azul de preenchimento mais intensa
                            dir2.position.set(-400, 300, -400);
                            scene3D.add(dir2);

                            const applyTheme = () => {
                                const dark = document.documentElement.classList.contains('dark');
                                if (!scene3D || !renderer3D) return;
                                const color = dark ? 0x07111f : 0xdbeafe;
                                scene3D.background.setHex(color);
                                scene3D.fog.color.setHex(color);
                                ground.material.color.setHex(dark ? 0x0b1728 : 0xcbd5e1);
                                ambient.color.setHex(dark ? 0x7895b5 : 0xffffff);
                                ambient.groundColor.setHex(dark ? 0x07111f : 0x64748b);
                                ambient.intensity = dark ? 0.58 : 1.15;
                                dir1.color.setHex(dark ? 0xb8c9dc : 0xffffff);
                                dir1.intensity = dark ? 0.78 : 1.3;
                                dir2.color.setHex(dark ? 0x1d4f6f : 0x38bdf8);
                                dir2.intensity = dark ? 0.28 : 0.6;
                                renderer3D.toneMappingExposure = dark ? 0.72 : 0.95;

                                scene3D.traverse(object => {
                                    const material = object.material;
                                    if (!material) return;
                                    const materials = Array.isArray(material) ? material : [material];
                                    const role = object.userData.themeRole;
                                    materials.forEach(item => {
                                        if (!item?.color) return;
                                        if (object.userData.terrain) item.color.setHex(dark ? 0x53615b : 0xffffff);
                                        if (role === 'tree-trunk') item.color.setHex(dark ? 0x3b2b22 : 0x6b4423);
                                        if (role === 'tree-canopy') item.color.setHex(dark ? 0x153c2b : 0x245c32);
                                        if (role === 'road') item.color.setHex(dark ? 0x151a20 : 0x343a40);
                                        if (role === 'road-edge') item.color.setHex(dark ? 0x64748b : 0xcbd5e1);
                                    });
                                });
                            };
                            new MutationObserver(applyTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

                            const animate = () => {
                                animationFrameId = requestAnimationFrame(animate);
                                if (controls3D) controls3D.update();
                                renderer3D.render(scene3D, camera3D);
                            };
                            animate();

                            window.addEventListener('resize', () => {
                                if (!camera3D || !renderer3D) return;
                                const w = container.clientWidth;
                                const h = container.clientHeight || 750;
                                camera3D.aspect = w / h;
                                camera3D.updateProjectionMatrix();
                                renderer3D.setSize(w, h);
                            });
                        },

                        handleThreeClick(e) {
                            if (!renderer3D || !camera3D || !scene3D) return;

                            const rect = renderer3D.domElement.getBoundingClientRect();
                            const mouse = new THREE.Vector2(
                                ((e.clientX - rect.left) / rect.width) * 2 - 1,
                                -((e.clientY - rect.top) / rect.height) * 2 + 1
                            );

                            raycaster3D.setFromCamera(mouse, camera3D);
                            const intersects = raycaster3D.intersectObjects(scene3D.children, true);

                            let found = false;

                            for (let i = 0; i < intersects.length; i++) {
                                let obj = intersects[i].object;

                                // Escala até achar a raiz que guarda as propriedades originais de userData
                                while (obj && !obj.userData.type) {
                                    obj = obj.parent;
                                }

                                if (obj && obj.userData.type) {
                                    const item = obj.userData;
                                    let html = '';
                                    let title = '';
                                    let editUrl = '';

                                    if (item.type === 'poste') {
                                        title = 'Poste de distribuição';
                                        const d = item.data;
                                        html = `<b>Código Patrimônio:</b> ${d.codigo_patrimonio || 'S/N'}<br>
                                                <b>Altura:</b> ${d.altura_metros ? `${Number(d.altura_metros).toFixed(2)} m` : 'estimada em 10 m'}<br>
                                                <b>Material:</b> ${d.material || 'não informado'}<br>
                                                <b>Resistência:</b> ${d.resistencia_kg ? `${d.resistencia_kg} kg` : 'não informada'}<br>
                                                <b>Iluminação:</b> ${d.possui_iluminacao ? 'sim' : 'não'}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/postes/${d.id}/edit`;
                                    } else if (item.type === 'cabo') {
                                        title = 'Cabo / fiação';
                                        const d = item.data;
                                        html = `<b>Identificador:</b> ${d.nome || d.tipo_cabo}<br>
                                                <b>Tipo Fiação:</b> ${d.tipo_cabo}<br>
                                                <b>Estado de Conservação:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/cabeamentos/${d.id}/edit`;
                                    } else if (item.type === 'equipamento') {
                                        title = 'Equipamento de rede';
                                        const d = item.data;
                                        html = `<b>Nome:</b> ${d.nome}<br>
                                                <b>Tipo Equipamento:</b> ${d.tipo}<br>
                                                <b>Modelo:</b> ${d.modelo || 'não informado'}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/equipamento-infraestruturas/${d.id}/edit`;
                                    } else if (item.type === 'central') {
                                        title = 'Central / estação';
                                        const d = item.data;
                                        html = `<b>Nome:</b> ${d.nome}<br>
                                                <b>Tipo Central:</b> ${d.tipo}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/central-distribuicaos/${d.id}/edit`;
                                    } else if (item.type === 'antena') {
                                        title = 'Antena de telecomunicações';
                                        const d = item.data;
                                        html = `<b>Código Antena:</b> ${d.codigo_patrimonio || 'S/N'}<br>
                                                <b>Tipo de Sinal:</b> ${d.tipo_sinal}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/antenas/${d.id}/edit`;
                                    } else if (item.type === 'via') {
                                        title = 'Via urbana';
                                        const d = item.data;
                                        html = `<b>Nome:</b> ${d.nome || 'Via sem nome'}<br>
                                                <b>Fluxo:</b> ${d.nivel_congestionamento || 'não informado'}<br>
                                                <b>Velocidade média:</b> ${d.velocidade_media ? `${d.velocidade_media} km/h` : 'não informada'}`;
                                        editUrl = `/admin/via-transitos/${d.id}/edit`;
                                    }

                                    if (html) {
                                        let tooltip = document.getElementById('three-tooltip');
                                        if (tooltip) {
                                            tooltip.innerHTML = `
                                                <div style="font-weight: bold; border-bottom: 1px solid var(--tooltip-border); padding-bottom: 6px; margin-bottom: 8px; color: var(--tooltip-title); font-size: 13px;">
                                                    ${title}
                                                </div>
                                                <div style="line-height: 1.5; color: var(--tooltip-text); font-size: 11px;">
                                                    ${html}
                                                </div>
                                                <div style="margin-top: 12px; text-align: right;">
                                                    <a href="${editUrl}" target="_blank" style="display: inline-block; background: #2563eb; color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 11px; transition: background 0.2s;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                                                        Editar registro
                                                    </a>
                                                </div>
                                            `;
                                            tooltip.style.left = (e.clientX - rect.left + 15) + 'px';
                                            tooltip.style.top = (e.clientY - rect.top + 15) + 'px';
                                            tooltip.style.display = 'block';
                                        }
                                        found = true;
                                        break;
                                    }
                                }
                            }

                            if (!found) {
                                let tooltip = document.getElementById('three-tooltip');
                                if (tooltip) tooltip.style.display = 'none';
                            }
                        },

                        async loadTerrain(bounds) {
                            const container = document.getElementById('infra-3d-container');
                            if (!container || container.dataset.terrainEnabled !== 'true') return null;

                            const zoom = Number(container.dataset.terrainZoom || 12);
                            const template = container.dataset.terrainTileUrl;
                            const scale = 2 ** zoom;
                            const project = (lat, lon) => {
                                const sin = Math.sin(Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI / 180);
                                return {
                                    x: ((lon + 180) / 360) * scale,
                                    y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale,
                                };
                            };

                            const northWest = project(bounds.maxLat, bounds.minLon);
                            const southEast = project(bounds.minLat, bounds.maxLon);
                            const minTileX = Math.floor(northWest.x);
                            const maxTileX = Math.floor(southEast.x);
                            const minTileY = Math.floor(northWest.y);
                            const maxTileY = Math.floor(southEast.y);
                            const requestKey = `${zoom}:${minTileX}:${maxTileX}:${minTileY}:${maxTileY}`;
                            if (this.terrainRequestKey === requestKey && this.terrainSampler) return this.terrainSampler;

                            const tiles = new Map();
                            const requests = [];
                            for (let x = minTileX; x <= maxTileX; x++) {
                                for (let y = minTileY; y <= maxTileY; y++) {
                                    requests.push(new Promise((resolve) => {
                                        const image = new Image();
                                        image.crossOrigin = 'anonymous';
                                        image.onload = () => {
                                            try {
                                                const canvas = document.createElement('canvas');
                                                canvas.width = image.naturalWidth;
                                                canvas.height = image.naturalHeight;
                                                const context = canvas.getContext('2d', { willReadFrequently: true });
                                                context.drawImage(image, 0, 0);
                                                tiles.set(`${x}:${y}`, context.getImageData(0, 0, canvas.width, canvas.height));
                                            } catch (error) {
                                                console.warn('Tile de relevo não pôde ser lido.', error);
                                            }
                                            resolve();
                                        };
                                        image.onerror = resolve;
                                        image.src = template.replace('{z}', zoom).replace('{x}', x).replace('{y}', y);
                                    }));
                                }
                            }
                            await Promise.all(requests);

                            const sampler = (lat, lon) => {
                                const point = project(lat, lon);
                                const tileX = Math.floor(point.x);
                                const tileY = Math.floor(point.y);
                                const tile = tiles.get(`${tileX}:${tileY}`);
                                if (!tile) return 0;
                                const px = Math.max(0, Math.min(tile.width - 1, Math.floor((point.x - tileX) * tile.width)));
                                const py = Math.max(0, Math.min(tile.height - 1, Math.floor((point.y - tileY) * tile.height)));
                                const offset = (py * tile.width + px) * 4;
                                return (tile.data[offset] * 256 + tile.data[offset + 1] + tile.data[offset + 2] / 256) - 32768;
                            };
                            this.terrainRequestKey = requestKey;
                            this.terrainSampler = sampler;
                            return sampler;
                        },

                        async renderThree(data) {
                            if (!scene3D) return;

                            // Limpar objetos anteriores (preservando o grid e as luzes)
                            const toRemove = [];
                            scene3D.traverse(child => {
                                if ((child.isMesh || child.isLine || child.isGroup) && !child.userData.persistent && child.parent === scene3D) {
                                    toRemove.push(child);
                                }
                            });
                            toRemove.forEach(child => scene3D.remove(child));

                            let centerLat = -16.070830;
                            let centerLon = -57.687500;

                            if (data.postes && data.postes.length > 0) {
                                let sumLat = 0, sumLon = 0, count = 0;
                                data.postes.forEach(p => {
                                    if (p.latitude && p.longitude) {
                                        sumLat += parseFloat(p.latitude);
                                        sumLon += parseFloat(p.longitude);
                                        count++;
                                    }
                                });
                                if (count > 0) {
                                    centerLat = sumLat / count;
                                    centerLon = sumLon / count;
                                }
                            }

                            const getLocalCoords = (lat, lon) => {
                                const latRad = centerLat * Math.PI / 180;
                                const metersPerDegreeLon = 111320 * Math.cos(latRad);
                                const metersPerDegreeLat = 110540;
                                const x = (parseFloat(lon) - centerLon) * metersPerDegreeLon;
                                const z = -(parseFloat(lat) - centerLat) * metersPerDegreeLat;
                                return { x, z };
                            };

                            const validPoles = (data.postes || []).filter(p => p.latitude && p.longitude);
                            const latitudes = validPoles.map(p => Number(p.latitude));
                            const longitudes = validPoles.map(p => Number(p.longitude));
                            const terrainBounds = validPoles.length ? {
                                minLat: Math.min(...latitudes) - 0.001,
                                maxLat: Math.max(...latitudes) + 0.001,
                                minLon: Math.min(...longitudes) - 0.001,
                                maxLon: Math.max(...longitudes) + 0.001,
                            } : null;
                            const terrainSampler = terrainBounds ? await this.loadTerrain(terrainBounds) : null;
                            const terrainExaggeration = Number(document.getElementById('infra-3d-container')?.dataset.terrainExaggeration || 1.35);
                            let terrainReference = 0;
                            const roadSegments = [];
                            (data.vias || []).forEach(via => {
                                try {
                                    const geometry = typeof via.geojson === 'string' ? JSON.parse(via.geojson) : via.geojson;
                                    const lines = geometry?.type === 'MultiLineString' ? geometry.coordinates : [geometry?.coordinates];
                                    lines.forEach(coordinates => {
                                        for (let index = 1; index < (coordinates?.length || 0); index++) {
                                            roadSegments.push([
                                                getLocalCoords(coordinates[index - 1][1], coordinates[index - 1][0]),
                                                getLocalCoords(coordinates[index][1], coordinates[index][0]),
                                            ]);
                                        }
                                    });
                                } catch (error) {}
                            });

                            if (terrainSampler && terrainBounds) {
                                const samples = validPoles.map(p => terrainSampler(Number(p.latitude), Number(p.longitude))).filter(Number.isFinite);
                                terrainReference = samples.length ? Math.min(...samples) : 0;
                                const columns = 48;
                                const rows = 48;
                                const vertices = [];
                                const colors = [];
                                const indices = [];
                                const elevationGrid = [];

                                for (let row = 0; row < rows; row++) {
                                    const lat = terrainBounds.maxLat - (row / (rows - 1)) * (terrainBounds.maxLat - terrainBounds.minLat);
                                    for (let column = 0; column < columns; column++) {
                                        const lon = terrainBounds.minLon + (column / (columns - 1)) * (terrainBounds.maxLon - terrainBounds.minLon);
                                        const local = getLocalCoords(lat, lon);
                                        const elevation = Math.max(0, (terrainSampler(lat, lon) - terrainReference) * terrainExaggeration);
                                        elevationGrid.push(elevation);
                                        vertices.push(local.x, elevation, local.z);
                                    }
                                }
                                const minElevation = Math.min(...elevationGrid);
                                const maxElevation = Math.max(...elevationGrid);
                                const elevationRange = Math.max(1, maxElevation - minElevation);
                                const lowColor = new THREE.Color(0x3f6f45);
                                const middleColor = new THREE.Color(0x789653);
                                const highColor = new THREE.Color(0x9b815d);

                                elevationGrid.forEach((elevation, index) => {
                                    const row = Math.floor(index / columns);
                                    const column = index % columns;
                                    const right = elevationGrid[row * columns + Math.min(columns - 1, column + 1)] ?? elevation;
                                    const down = elevationGrid[Math.min(rows - 1, row + 1) * columns + column] ?? elevation;
                                    const slope = Math.min(1, (Math.abs(right - elevation) + Math.abs(down - elevation)) / 12);
                                    const normalized = (elevation - minElevation) / elevationRange;
                                    const color = normalized < 0.55
                                        ? lowColor.clone().lerp(middleColor, normalized / 0.55)
                                        : middleColor.clone().lerp(highColor, (normalized - 0.55) / 0.45);
                                    color.multiplyScalar(1 - slope * 0.2);
                                    colors.push(color.r, color.g, color.b);
                                });
                                for (let row = 0; row < rows - 1; row++) {
                                    for (let column = 0; column < columns - 1; column++) {
                                        const a = row * columns + column;
                                        const b = a + 1;
                                        const c = a + columns;
                                        const d = c + 1;
                                        indices.push(a, c, b, b, c, d);
                                    }
                                }

                                const terrainGeometry = new THREE.BufferGeometry();
                                terrainGeometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3));
                                terrainGeometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
                                terrainGeometry.setIndex(indices);
                                terrainGeometry.computeVertexNormals();
                                const terrain = new THREE.Mesh(
                                    terrainGeometry,
                                    new THREE.MeshStandardMaterial({
                                        color: document.documentElement.classList.contains('dark') ? 0x53615b : 0xffffff,
                                        vertexColors: true,
                                        roughness: 0.96,
                                        metalness: 0,
                                        side: THREE.DoubleSide,
                                    })
                                );
                                terrain.receiveShadow = true;
                                terrain.userData = { terrain: true };
                                scene3D.add(terrain);

                                // Vegetação apenas contextual: instâncias leves, afastadas da infraestrutura cadastrada.
                                const treeCount = Math.min(180, Math.max(50, Math.round(validPoles.length * 0.35)));
                                const trunk = new THREE.InstancedMesh(
                                    new THREE.CylinderGeometry(0.11, 0.16, 1, 6),
                                    new THREE.MeshStandardMaterial({
                                        color: document.documentElement.classList.contains('dark') ? 0x3b2b22 : 0x6b4423,
                                        roughness: 1,
                                    }),
                                    treeCount
                                );
                                const canopy = new THREE.InstancedMesh(
                                    new THREE.ConeGeometry(1, 2.4, 7),
                                    new THREE.MeshStandardMaterial({
                                        color: document.documentElement.classList.contains('dark') ? 0x153c2b : 0x245c32,
                                        roughness: 0.92,
                                    }),
                                    treeCount
                                );
                                const matrix = new THREE.Matrix4();
                                let planted = 0;
                                for (let attempt = 0; attempt < treeCount * 5 && planted < treeCount; attempt++) {
                                    const randomA = Math.abs(Math.sin((attempt + 1) * 12.9898) * 43758.5453) % 1;
                                    const randomB = Math.abs(Math.sin((attempt + 1) * 78.233) * 12345.6789) % 1;
                                    const lat = terrainBounds.minLat + randomA * (terrainBounds.maxLat - terrainBounds.minLat);
                                    const lon = terrainBounds.minLon + randomB * (terrainBounds.maxLon - terrainBounds.minLon);
                                    const local = getLocalCoords(lat, lon);
                                    const tooClose = validPoles.some(p => {
                                        const poleLocal = getLocalCoords(p.latitude, p.longitude);
                                        return Math.hypot(local.x - poleLocal.x, local.z - poleLocal.z) < 7;
                                    });
                                    const onRoad = roadSegments.some(([start, end]) => {
                                        const dx = end.x - start.x;
                                        const dz = end.z - start.z;
                                        const lengthSquared = dx * dx + dz * dz;
                                        const projection = lengthSquared
                                            ? Math.max(0, Math.min(1, ((local.x - start.x) * dx + (local.z - start.z) * dz) / lengthSquared))
                                            : 0;
                                        return Math.hypot(local.x - (start.x + projection * dx), local.z - (start.z + projection * dz)) < 5;
                                    });
                                    if (tooClose || onRoad) continue;

                                    const groundY = Math.max(0, (terrainSampler(lat, lon) - terrainReference) * terrainExaggeration);
                                    const treeHeight = 3.2 + ((attempt * 37) % 28) / 10;
                                    matrix.compose(
                                        new THREE.Vector3(local.x, groundY + treeHeight * 0.22, local.z),
                                        new THREE.Quaternion(),
                                        new THREE.Vector3(1, treeHeight * 0.44, 1)
                                    );
                                    trunk.setMatrixAt(planted, matrix);
                                    matrix.compose(
                                        new THREE.Vector3(local.x, groundY + treeHeight * 0.72, local.z),
                                        new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), randomA * Math.PI),
                                        new THREE.Vector3(treeHeight * 0.42, treeHeight * 0.58, treeHeight * 0.42)
                                    );
                                    canopy.setMatrixAt(planted, matrix);
                                    planted++;
                                }
                                trunk.count = planted;
                                canopy.count = planted;
                                trunk.userData.themeRole = 'tree-trunk';
                                canopy.userData.themeRole = 'tree-canopy';
                                trunk.instanceMatrix.needsUpdate = true;
                                canopy.instanceMatrix.needsUpdate = true;
                                trunk.castShadow = true;
                                canopy.castShadow = true;
                                scene3D.add(trunk, canopy);
                            }

                            const getGroundHeight = (lat, lon) => terrainSampler
                                ? Math.max(0, (terrainSampler(Number(lat), Number(lon)) - terrainReference) * terrainExaggeration)
                                : 0;

                            // OTIMIZAÇÃO EXTREMA: Reutilização de Materiais e Geometrias no WebGL
                            const cylinderGeo = new THREE.CylinderGeometry(0.12, 0.18, 1, 8); // Altura base 1 (vamos escalar)

                            const poleMaterials = {
                                concreto: new THREE.MeshStandardMaterial({ color: 0x94a3b8, roughness: 0.82 }),
                                madeira: new THREE.MeshStandardMaterial({ color: 0x8b5a2b, roughness: 0.92 }),
                                ferro: new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.72, roughness: 0.34 }),
                                fibra: new THREE.MeshStandardMaterial({ color: 0x0891b2, roughness: 0.5 }),
                                default: new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.72 })
                            };

                            const poleDetails = {
                                crossarm: new THREE.BoxGeometry(2.2, 0.12, 0.12),
                                insulator: new THREE.CylinderGeometry(0.07, 0.09, 0.25, 8),
                                lampArm: new THREE.CylinderGeometry(0.035, 0.035, 1.6, 8),
                                lamp: new THREE.SphereGeometry(0.16, 12, 8),
                            };
                            const detailMaterials = {
                                metal: new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.75, roughness: 0.3 }),
                                ceramic: new THREE.MeshStandardMaterial({ color: 0xe2e8f0, roughness: 0.28 }),
                                lamp: new THREE.MeshStandardMaterial({ color: 0xfef3c7, emissive: 0xfbbf24, emissiveIntensity: 1.25 }),
                            };

                            const eqGeometries = {
                                transformador: new THREE.BoxGeometry(0.65, 0.65, 0.65),
                                cto: new THREE.BoxGeometry(0.35, 0.35, 0.22),
                                caixaEmenda: new THREE.CylinderGeometry(0.12, 0.12, 0.4, 8),
                                medidor: new THREE.BoxGeometry(0.16, 0.16, 0.1),
                                central: new THREE.BoxGeometry(6, 4, 6),
                                antenaBase: new THREE.CylinderGeometry(0.05, 0.45, 18, 4, 1, true),
                                antenaSignal: new THREE.SphereGeometry(0.28, 8, 8)
                            };

                            const eqMaterials = {
                                transformador: new THREE.MeshStandardMaterial({ color: 0x4f46e5, metalness: 0.5, roughness: 0.2 }),
                                cto: new THREE.MeshStandardMaterial({ color: 0xec4899 }),
                                caixaEmenda: new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.8 }),
                                medidor: new THREE.MeshStandardMaterial({ color: 0x10b981 }),
                                central: new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.1, transparent: true, opacity: 0.8 }),
                                antenaSignal: new THREE.MeshBasicMaterial({ color: 0xec4899 }),
                                wireframeTower: new THREE.MeshBasicMaterial({ color: 0x475569, wireframe: true })
                            };

                            const cableMaterials = {
                                fibra_optica: new THREE.LineBasicMaterial({ color: 0x06b6d4, linewidth: 2 }),
                                fibra_backbone: new THREE.LineBasicMaterial({ color: 0xfacc15, linewidth: 2 }),
                                eletrico_alta_tensao: new THREE.LineBasicMaterial({ color: 0xef4444, linewidth: 2 }),
                                eletrico_baixa_tensao: new THREE.LineBasicMaterial({ color: 0x3b82f6, linewidth: 2 }),
                                telefonia: new THREE.LineBasicMaterial({ color: 0xa855f7, linewidth: 2 }),
                                default: new THREE.LineBasicMaterial({ color: 0x64748b, linewidth: 2 })
                            };

                            const roadMaterials = {
                                asphalt: new THREE.MeshStandardMaterial({
                                    color: document.documentElement.classList.contains('dark') ? 0x151a20 : 0x343a40,
                                    roughness: 0.94,
                                    metalness: 0,
                                }),
                                edge: new THREE.LineBasicMaterial({
                                    color: document.documentElement.classList.contains('dark') ? 0x64748b : 0xcbd5e1,
                                    transparent: true,
                                    opacity: 0.7,
                                }),
                            };

                            // 1. Renderizar Vias (Ruas)
                            if (data.vias) {
                                data.vias.forEach(v => {
                                    if (!v.geojson) return;
                                    try {
                                        let geo = typeof v.geojson === 'string' ? JSON.parse(v.geojson) : v.geojson;
                                        const lineSets = geo.type === 'MultiLineString' ? geo.coordinates : [geo.coordinates];

                                        let colorHex = 0x10b981;
                                        if (v.nivel_congestionamento === 'moderado') colorHex = 0xfacc15;
                                        if (v.nivel_congestionamento === 'intenso') colorHex = 0xf97316;
                                        if (v.nivel_congestionamento === 'parado') colorHex = 0xef4444;

                                        lineSets.forEach(coords => {
                                            if (!coords || coords.length < 2) return;
                                            const roadWidth = 6;
                                            const ribbonVertices = [];
                                            const ribbonIndices = [];
                                            const centerPoints = [];
                                            const leftEdge = [];
                                            const rightEdge = [];

                                            coords.forEach((coordinate, index) => {
                                                const previous = coords[Math.max(0, index - 1)];
                                                const next = coords[Math.min(coords.length - 1, index + 1)];
                                                const before = getLocalCoords(previous[1], previous[0]);
                                                const after = getLocalCoords(next[1], next[0]);
                                                const length = Math.hypot(after.x - before.x, after.z - before.z) || 1;
                                                const normalX = -(after.z - before.z) / length;
                                                const normalZ = (after.x - before.x) / length;
                                                const local = getLocalCoords(coordinate[1], coordinate[0]);
                                                const y = getGroundHeight(coordinate[1], coordinate[0]) + 0.1;
                                                const left = new THREE.Vector3(local.x + normalX * roadWidth / 2, y, local.z + normalZ * roadWidth / 2);
                                                const right = new THREE.Vector3(local.x - normalX * roadWidth / 2, y, local.z - normalZ * roadWidth / 2);
                                                ribbonVertices.push(left.x, left.y, left.z, right.x, right.y, right.z);
                                                leftEdge.push(left);
                                                rightEdge.push(right);
                                                centerPoints.push(new THREE.Vector3(local.x, y + 0.045, local.z));
                                                if (index < coords.length - 1) {
                                                    const base = index * 2;
                                                    ribbonIndices.push(base, base + 2, base + 1, base + 1, base + 2, base + 3);
                                                }
                                            });

                                            const roadGeometry = new THREE.BufferGeometry();
                                            roadGeometry.setAttribute('position', new THREE.Float32BufferAttribute(ribbonVertices, 3));
                                            roadGeometry.setIndex(ribbonIndices);
                                            roadGeometry.computeVertexNormals();
                                            const road = new THREE.Mesh(roadGeometry, roadMaterials.asphalt);
                                            road.receiveShadow = true;
                                            road.userData = { type: 'via', data: v, themeRole: 'road' };
                                            scene3D.add(road);

                                            const leftRoadEdge = new THREE.Line(new THREE.BufferGeometry().setFromPoints(leftEdge), roadMaterials.edge);
                                            const rightRoadEdge = new THREE.Line(new THREE.BufferGeometry().setFromPoints(rightEdge), roadMaterials.edge);
                                            leftRoadEdge.userData.themeRole = 'road-edge';
                                            rightRoadEdge.userData.themeRole = 'road-edge';
                                            scene3D.add(leftRoadEdge, rightRoadEdge);
                                            scene3D.add(new THREE.Line(
                                                new THREE.BufferGeometry().setFromPoints(centerPoints),
                                                new THREE.LineBasicMaterial({ color: colorHex, transparent: true, opacity: 0.9 })
                                            ));
                                        });
                                    } catch(e){}
                                });
                            }

                            // 2. Renderizar Centrais de Distribuição (Prédios)
                            if (data.centrais) {
                                data.centrais.forEach(c => {
                                    let x = 0, z = 0;
                                    if (c.latitude && c.longitude) {
                                        const pos = getLocalCoords(c.latitude, c.longitude);
                                        x = pos.x; z = pos.z;
                                    } else if (c.geojson) {
                                        try {
                                            let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                            if (geo.coordinates && geo.coordinates[0]) {
                                                const pos = getLocalCoords(geo.coordinates[0][1], geo.coordinates[0][0]);
                                                x = pos.x; z = pos.z;
                                            }
                                        } catch(e){}
                                    }

                                    const box = new THREE.Mesh(eqGeometries.central, eqMaterials.central);
                                    const centralGround = c.latitude && c.longitude ? getGroundHeight(c.latitude, c.longitude) : 0;
                                    box.position.set(x, centralGround + 2, z);
                                    box.castShadow = true;
                                    box.receiveShadow = true;
                                    box.userData = { type: 'central', data: c };
                                    scene3D.add(box);
                                });
                            }

                            // 3. Renderizar Postes
                            const polesMap = {};
                            if (data.postes) {
                                data.postes.forEach(p => {
                                    if (!p.latitude || !p.longitude) return;
                                    const pos = getLocalCoords(p.latitude, p.longitude);
                                    const height = parseFloat(p.altura_metros) || 10;
                                    const groundHeight = getGroundHeight(p.latitude, p.longitude);

                                    const material = poleMaterials[p.material] || poleMaterials.default;
                                    const cylinder = new THREE.Mesh(cylinderGeo, material);
                                    const poleGroup = new THREE.Group();

                                    // Escalar verticalmente conforme a altura do poste
                                    cylinder.scale.set(1, height, 1);
                                    cylinder.position.set(0, height / 2, 0);
                                    cylinder.castShadow = true;
                                    poleGroup.add(cylinder);

                                    const crossarm = new THREE.Mesh(poleDetails.crossarm, detailMaterials.metal);
                                    crossarm.position.y = height - 0.55;
                                    crossarm.castShadow = true;
                                    poleGroup.add(crossarm);

                                    [-0.82, 0, 0.82].forEach(offset => {
                                        const insulator = new THREE.Mesh(poleDetails.insulator, detailMaterials.ceramic);
                                        insulator.position.set(offset, height - 0.36, 0);
                                        poleGroup.add(insulator);
                                    });

                                    if (p.possui_iluminacao) {
                                        const lampArm = new THREE.Mesh(poleDetails.lampArm, detailMaterials.metal);
                                        lampArm.rotation.z = Math.PI / 2;
                                        lampArm.position.set(0.72, height - 1.15, 0);
                                        poleGroup.add(lampArm);

                                        const lamp = new THREE.Mesh(poleDetails.lamp, detailMaterials.lamp);
                                        lamp.scale.set(1.5, 0.55, 0.9);
                                        lamp.position.set(1.5, height - 1.15, 0);
                                        poleGroup.add(lamp);
                                    }

                                    if (['ruim', 'critico'].includes(p.estado_conservacao)) {
                                        const warning = new THREE.Mesh(
                                            new THREE.TorusGeometry(0.36, 0.055, 8, 20),
                                            new THREE.MeshBasicMaterial({ color: p.estado_conservacao === 'critico' ? 0xef4444 : 0xf97316 })
                                        );
                                        warning.rotation.x = Math.PI / 2;
                                        warning.position.y = 0.12;
                                        poleGroup.add(warning);
                                    }

                                    poleGroup.position.set(pos.x, groundHeight, pos.z);

                                    // Associar dados do poste para cliques Raycast
                                    poleGroup.userData = { type: 'poste', data: p };
                                    scene3D.add(poleGroup);

                                    polesMap[p.id] = {
                                        id: p.id,
                                        x: pos.x,
                                        z: pos.z,
                                        height: height,
                                        groundHeight: groundHeight
                                    };
                                });
                            }

                            // 4. Renderizar Equipamentos acoplados
                            if (data.equipamentos) {
                                data.equipamentos.forEach(eq => {
                                    if (eq.poste_id && polesMap[eq.poste_id]) {
                                        const pole = polesMap[eq.poste_id];
                                        const x = pole.x;
                                        const z = pole.z;
                                        let y = 0.5;
                                        let mesh = null;

                                        if (eq.tipo === 'Transformador') {
                                            y = pole.height - 2.2;
                                            mesh = new THREE.Mesh(eqGeometries.transformador, eqMaterials.transformador);
                                            mesh.position.set(x + 0.28, y, z);
                                        } else if (eq.tipo === 'CTO') {
                                            y = pole.height - 0.8;
                                            mesh = new THREE.Mesh(eqGeometries.cto, eqMaterials.cto);
                                            mesh.position.set(x - 0.22, y, z);
                                        } else if (eq.tipo === 'Caixa_Emenda') {
                                            y = pole.height - 1.4;
                                            mesh = new THREE.Mesh(eqGeometries.caixaEmenda, eqMaterials.caixaEmenda);
                                            mesh.rotation.z = Math.PI / 2;
                                            mesh.position.set(x, y, z + 0.22);
                                        } else if (eq.tipo === 'Medidor') {
                                            y = 1.6;
                                            mesh = new THREE.Mesh(eqGeometries.medidor, eqMaterials.medidor);
                                            mesh.position.set(x, y, z - 0.2);
                                        }

                                        if (mesh) {
                                            mesh.userData = { type: 'equipamento', data: eq };
                                            scene3D.add(mesh);
                                        }
                                    }
                                });
                            }

                            // 5. Renderizar Antenas
                            if (data.antenas) {
                                data.antenas.forEach(a => {
                                    if (!a.latitude || !a.longitude) return;
                                    const pos = getLocalCoords(a.latitude, a.longitude);
                                    const height = 18;

                                    const tower = new THREE.Mesh(eqGeometries.antenaBase, eqMaterials.wireframeTower);
                                    const antennaGround = getGroundHeight(a.latitude, a.longitude);
                                    tower.position.set(pos.x, antennaGround + height / 2, pos.z);
                                    // Associar dados da antena
                                    tower.userData = { type: 'antena', data: a };
                                    scene3D.add(tower);

                                    const signal = new THREE.Mesh(eqGeometries.antenaSignal, eqMaterials.antenaSignal);
                                    signal.position.set(pos.x, antennaGround + height, pos.z);
                                    signal.userData = { type: 'antena', data: a };
                                    scene3D.add(signal);
                                });
                            }

                            // 6. Renderizar Cabeamentos (Catenária 3D e empilhamento vertical)
                            if (data.cabeamentos) {
                                let cabosPorTrecho = {};
                                data.cabeamentos.forEach(c => {
                                    if (!c.geojson) return;

                                    let endpoints = [];
                                    if (c.poste_origem_id) endpoints.push('p_orig_' + c.poste_origem_id);
                                    if (c.poste_destino_id) endpoints.push('p_dest_' + c.poste_destino_id);
                                    if (c.central_origem_id) endpoints.push('c_orig_' + c.central_origem_id);
                                    if (c.central_destino_id) endpoints.push('c_dest_' + c.central_destino_id);

                                    let key = '';
                                    if (endpoints.length >= 2) {
                                        key = endpoints.sort().join('_');
                                    } else {
                                        try {
                                            let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                            if (geo.coordinates && geo.coordinates.length >= 2) {
                                                let p1 = geo.coordinates[0];
                                                let p2 = geo.coordinates[geo.coordinates.length - 1];
                                                let c1 = p1[0].toFixed(5) + ',' + p1[1].toFixed(5);
                                                let c2 = p2[0].toFixed(5) + ',' + p2[1].toFixed(5);
                                                key = [c1, c2].sort().join('_');
                                            }
                                        } catch(err) {}
                                    }

                                    if (!key) key = 'cabo_single_' + c.id;
                                    if (!cabosPorTrecho[key]) cabosPorTrecho[key] = [];
                                    cabosPorTrecho[key].push(c);
                                });

                                Object.keys(cabosPorTrecho).forEach(key => {
                                    let group = cabosPorTrecho[key];

                                    group.forEach((c, index) => {
                                        try {
                                            let startPos = null;
                                            let endPos = null;

                                            if (c.poste_origem_id && polesMap[c.poste_origem_id]) {
                                                const p = polesMap[c.poste_origem_id];
                                                startPos = new THREE.Vector3(p.x, p.groundHeight + p.height - (index * 0.45) - 0.3, p.z);
                                            } else if (c.central_origem_id) {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let pt = geo.coordinates[0];
                                                let local = getLocalCoords(pt[1], pt[0]);
                                                startPos = new THREE.Vector3(local.x, getGroundHeight(pt[1], pt[0]) + 2.8, local.z);
                                            }

                                            if (c.poste_destino_id && polesMap[c.poste_destino_id]) {
                                                const p = polesMap[c.poste_destino_id];
                                                endPos = new THREE.Vector3(p.x, p.groundHeight + p.height - (index * 0.45) - 0.3, p.z);
                                            } else if (c.central_destino_id) {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let pt = geo.coordinates[geo.coordinates.length - 1];
                                                let local = getLocalCoords(pt[1], pt[0]);
                                                endPos = new THREE.Vector3(local.x, getGroundHeight(pt[1], pt[0]) + 2.8, local.z);
                                            }

                                            if (!startPos || !endPos) {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let coords = geo.coordinates;
                                                if (coords && coords.length >= 2) {
                                                    let c1 = coords[0];
                                                    let c2 = coords[coords.length - 1];
                                                    let local1 = getLocalCoords(c1[1], c1[0]);
                                                    let local2 = getLocalCoords(c2[1], c2[0]);
                                                    startPos = new THREE.Vector3(local1.x, getGroundHeight(c1[1], c1[0]) + 9.5 - (index * 0.4), local1.z);
                                                    endPos = new THREE.Vector3(local2.x, getGroundHeight(c2[1], c2[0]) + 9.5 - (index * 0.4), local2.z);
                                                }
                                            }

                                            if (startPos && endPos) {
                                                const dist = startPos.distanceTo(endPos);
                                                const sag = Math.max(0.08, dist * 0.035);

                                                const midPoint = new THREE.Vector3(
                                                    (startPos.x + endPos.x) / 2,
                                                    ((startPos.y + endPos.y) / 2) - sag,
                                                    (startPos.z + endPos.z) / 2
                                                );

                                                const curve = new THREE.QuadraticBezierCurve3(startPos, midPoint, endPos);
                                                const curvePoints = curve.getPoints(24);

                                                const material = cableMaterials[c.tipo_cabo] || cableMaterials.default;
                                                const isElectric = String(c.tipo_cabo || '').startsWith('eletrico_');
                                                const cableObject = isElectric
                                                    ? new THREE.Mesh(
                                                        new THREE.TubeGeometry(curve, 10, c.tipo_cabo === 'eletrico_alta_tensao' ? 0.075 : 0.05, 4, false),
                                                        new THREE.MeshStandardMaterial({
                                                            color: c.tipo_cabo === 'eletrico_alta_tensao' ? 0xef4444 : 0xfbbf24,
                                                            emissive: c.tipo_cabo === 'eletrico_alta_tensao' ? 0x7f1d1d : 0x713f12,
                                                            emissiveIntensity: 0.28,
                                                            roughness: 0.48,
                                                        })
                                                    )
                                                    : new THREE.Line(new THREE.BufferGeometry().setFromPoints(curvePoints), material);

                                                // Associar dados do cabo para clique
                                                cableObject.userData = { type: 'cabo', data: c };
                                                scene3D.add(cableObject);
                                            }
                                        } catch(e){}
                                    });
                                });
                            }

                            // Posicionar Câmera
                            if (data.postes && data.postes.length > 0) {
                                let minX = Infinity, maxX = -Infinity, minZ = Infinity, maxZ = -Infinity;
                                Object.keys(polesMap).forEach(id => {
                                    const p = polesMap[id];
                                    if (p.x < minX) minX = p.x;
                                    if (p.x > maxX) maxX = p.x;
                                    if (p.z < minZ) minZ = p.z;
                                    if (p.z > maxZ) maxZ = p.z;
                                });

                                const centerX = (minX + maxX) / 2;
                                const centerZ = (minZ + maxZ) / 2;

                                if (controls3D) {
                                    controls3D.target.set(centerX, 0, centerZ);
                                    controls3D.update();
                                }
                                camera3D.position.set(centerX, Math.max(100, (maxZ - minZ) * 0.4), centerZ + Math.max(200, (maxZ - minZ) * 0.7));
                            }
                        }
                    }))
                });
            </script>
        </div>
    </x-filament::section>
</x-filament-panels::page>
