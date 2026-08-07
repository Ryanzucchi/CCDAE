<x-filament-panels::page>
    <!-- Carregar Three.js e OrbitControls para a visualização 3D -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

    <style>
        /* RESET do Tailwind para SVG/Canvas do Leaflet */
        .leaflet-pane svg,
        .leaflet-pane canvas {
            max-width: none !important;
            display: block !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            z-index: 9999 !important;
        }
        .leaflet-pane path {
            pointer-events: visiblePainted !important;
        }
        .leaflet-overlay-pane { z-index: 9999 !important; }
        .leaflet-popup-pane { z-index: 10000 !important; }

        /* Legendas e Controles */
        .map-legend {
            background: rgba(17, 24, 39, 0.9);
            color: #f3f4f6;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .legend-item { display: flex; align-items: center; margin-bottom: 4px; }
        .legend-color { width: 14px; height: 14px; border-radius: 50%; margin-right: 8px; display: inline-block; }
        .legend-line { width: 20px; height: 3px; margin-right: 8px; display: inline-block; }
    </style>

    <x-filament::section>
        <div class="mb-4 flex flex-wrap gap-4 items-center justify-between">
            <div class="max-w-sm flex gap-4 w-72">
                <x-filament::input.wrapper class="w-full">
                    <x-filament::input.select wire:model.live="distrito_id">
                        <option value="">🌎 Ver Todos os Distritos (Visão Macro)</option>
                        @foreach($this->distritos as $id => $nome)
                            <option value="{{ $id }}">📍 {{ $nome }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div wire:ignore x-data="infraMapPage" x-init="init()" style="position: relative; border-radius: 0.5rem; overflow: hidden; height: 750px;">
            <!-- Abas Flutuantes de Modo (Superior Esquerdo) -->
            <div style="position: absolute; top: 12px; left: 60px; z-index: 99999 !important; display: flex; gap: 6px; background: rgba(17, 24, 39, 0.9); padding: 4px; border-radius: 8px; border: 1px solid rgba(75, 85, 99, 0.5); pointer-events: auto !important;">
                <button type="button"
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition duration-200 cursor-pointer"
                        style="cursor: pointer !important; pointer-events: auto !important;"
                        :class="mode === '2d' ? 'bg-primary-600 text-white font-black' : 'text-gray-400 hover:text-gray-200'"
                        @click="setMode('2d')">
                    🗺️ Mapa 2D
                </button>
                <button type="button"
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition duration-200 cursor-pointer"
                        style="cursor: pointer !important; pointer-events: auto !important;"
                        :class="mode === '3d' ? 'bg-primary-600 text-white font-black' : 'text-gray-400 hover:text-gray-200'"
                        @click="setMode('3d')">
                    🕶️ Visualizador 3D
                </button>
            </div>

            <!-- Aviso 3D quando nenhum distrito estiver selecionado -->
            <div id="infra-3d-warning"
                 x-show="mode === '3d' && (!cachedData || !cachedData.postes || cachedData.postes.length === 0)"
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: rgba(10, 15, 29, 0.95); z-index: 100; color: #fff; padding: 20px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">🏙️</div>
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
            <div id="infra-map-container" x-show="mode === '2d'" style="width: 100%; height: 100%; z-index: 10;"></div>
            <div id="infra-3d-container" x-show="mode === '3d'" style="width: 100%; height: 100%; background: #0a0f1d;"></div>

            <script>
                let infraMap = null;
                let infraLayerGroup = null;

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

                        init() {
                            setTimeout(() => {
                                this.initLeaflet();
                                this.fetchData();
                            }, 300);

                            window.addEventListener('update-infra-map', (e) => {
                                let distrito_id = e.detail[0]?.distrito_id || '';
                                this.fetchData(distrito_id);
                            });
                        },

                        setMode(m) {
                            this.mode = m;

                            // Fechar tooltips/popups ao mudar de modo
                            let tooltipEl = document.getElementById('three-tooltip');
                            if (tooltipEl) tooltipEl.style.display = 'none';

                            if (m === '3d') {
                                setTimeout(() => {
                                    this.initThree();
                                    if (this.cachedData && this.cachedData.postes && this.cachedData.postes.length > 0) {
                                        this.renderThree(this.cachedData);
                                    }
                                }, 50);
                            } else {
                                setTimeout(() => {
                                    if (infraMap) {
                                        infraMap.invalidateSize();
                                    }
                                }, 50);
                            }
                        },

                        fetchData(distritoId = null) {
                            if (distritoId === null) {
                                distritoId = @json($this->distrito_id ?? '');
                            }

                            let mapContainerEl = document.getElementById('infra-map-container');
                            let container3D = document.getElementById('infra-3d-container');

                            if (mapContainerEl) mapContainerEl.style.opacity = '0.6';
                            if (container3D) container3D.style.opacity = '0.6';

                            fetch(`/api/infraestrutura/geojson?distrito_id=${distritoId}`)
                                .then(res => res.json())
                                .then(payload => {
                                    this.cachedData = payload;
                                    this.renderMap(payload, true);

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

                        initLeaflet() {
                            let mapContainer = document.getElementById('infra-map-container');
                            if (!mapContainer) return;

                            if (infraMap !== null) {
                                infraMap.remove();
                                infraMap = null;
                            } else if (mapContainer._leaflet_id) {
                                mapContainer._leaflet_id = null;
                            }

                            infraMap = L.map('infra-map-container', {
                                preferCanvas: true,
                                zoomAnimation: false,
                                fadeAnimation: false,
                                maxZoom: 22 // Permite dar zoom máximo de aproximação
                            }).setView([-16.070830, -57.687500], 12);

                            infraLayerGroup = L.featureGroup().addTo(infraMap);

                            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                                attribution: '&copy; OpenStreetMap &copy; CARTO',
                                maxZoom: 22,         // Zoom limite do Leaflet
                                maxNativeZoom: 19    // Zoom nativo limite do servidor de imagens
                            }).addTo(infraMap);

                            // Adicionar a legenda
                            let legend = L.control({position: 'bottomright'});
                            legend.onAdd = function (map) {
                                let div = L.DomUtil.create('div', 'map-legend');
                                div.innerHTML = `
                                    <strong>Mapeamento de Infraestrutura</strong><br>
                                    <div class="legend-item" style="margin-top:8px;"><span class="legend-color" style="background:#f59e0b;"></span> Centrais de Distribuição</div>
                                    <div class="legend-item"><span class="legend-color" style="background:#9ca3af; border:1px solid #000;"></span> Postes</div>
                                    <div class="legend-item"><span class="legend-color" style="background:#ec4899;"></span> Antenas Telecom</div>
                                    <div class="legend-item"><span class="legend-color" style="background:#10b981;"></span> Equipamentos</div>
                                    <div class="legend-item" style="margin-top:8px;"><strong>Fluxo de Trânsito</strong></div>
                                    <div class="legend-item"><span class="legend-line" style="background:#22c55e;"></span> Via Livre</div>
                                    <div class="legend-item"><span class="legend-line" style="background:#eab308;"></span> Moderado</div>
                                    <div class="legend-item"><span class="legend-line" style="background:#f97316;"></span> Intenso</div>
                                    <div class="legend-item"><span class="legend-line" style="background:#ef4444;"></span> Parado</div>
                                    <div class="legend-item" style="margin-top:8px;"><strong>Cabeamento</strong></div>
                                    <div class="legend-item"><span class="legend-line" style="background:#06b6d4;"></span> Cabo de Fibra Óptica (Ruas)</div>
                                    <div class="legend-item"><span class="legend-line" style="background:#facc15;"></span> Fibra Backbone (Avenidas)</div>
                                    <div class="legend-item"><span class="legend-line" style="background:#ef4444;"></span> Cabo de Alta Tensão</div>
                                    <div class="legend-item"><span class="legend-line" style="background:#3b82f6;"></span> Outros Cabeamentos</div>
                                `;
                                return div;
                            };
                            legend.addTo(infraMap);

                            let zoomHintControl = L.control({position: 'topright'});
                            zoomHintControl.onAdd = function(map) {
                                let div = L.DomUtil.create('div', 'zoom-hint');
                                div.style.background = 'rgba(239, 68, 68, 0.85)';
                                div.style.color = '#fff';
                                div.style.padding = '8px 12px';
                                div.style.borderRadius = '6px';
                                div.style.fontSize = '12px';
                                div.style.fontWeight = 'bold';
                                div.style.boxShadow = '0 2px 4px rgba(0,0,0,0.2)';
                                div.style.display = 'none';
                                div.id = 'map-zoom-hint';
                                div.innerHTML = '🔍 Aproxime o zoom (nível 15+) para ver postes, cabos e equipamentos';
                                return div;
                            };
                            zoomHintControl.addTo(infraMap);

                            infraMap.on('zoomend', () => {
                                if (this.cachedData) {
                                    this.renderMap(this.cachedData, false);
                                }
                            });
                        },

                        renderMap(data, fit = true) {
                            infraLayerGroup.clearLayers();
                            let bounds = [];
                            let zoom = infraMap.getZoom();

                            let hintEl = document.getElementById('map-zoom-hint');
                            if (zoom < 15) {
                                if (hintEl) hintEl.style.display = 'block';
                            } else {
                                if (hintEl) hintEl.style.display = 'none';
                            }

                            if (data.vias) {
                                data.vias.forEach(v => {
                                    if (v.geojson) {
                                        try {
                                            let geo = typeof v.geojson === 'string' ? JSON.parse(v.geojson) : v.geojson;
                                            let color = '#22c55e';
                                            if(v.nivel_congestionamento === 'moderado') color = '#eab308';
                                            if(v.nivel_congestionamento === 'intenso') color = '#f97316';
                                            if(v.nivel_congestionamento === 'parado') color = '#ef4444';

                                            let layer = L.geoJSON(geo, { style: { color: color, weight: 6, opacity: 0.9 } });
                                            layer.bindPopup(`<b>Via:</b> ${v.nome}<br><b>Congestionamento:</b> <span style="color:${color}; font-weight:bold; text-transform:uppercase;">${v.nivel_congestionamento}</span><br><b>Velocidade Média:</b> ${v.velocidade_media} km/h`);
                                            infraLayerGroup.addLayer(layer);

                                            if (fit) {
                                                let layerBounds = layer.getBounds();
                                                bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                                bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                            }
                                        } catch(e) {}
                                    }
                                });
                            }

                            if (data.centrais) {
                                data.centrais.forEach(c => {
                                    if (c.geojson) {
                                        try {
                                            let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                            let layer = L.geoJSON(geo, { style: { color: '#f59e0b', weight: 2, fillOpacity: 0.4 } });
                                            layer.bindPopup(`<b>Central:</b> ${c.nome}<br><b>Tipo:</b> ${c.tipo}<br><b>Estado:</b> ${c.estado_conservacao}<br><div style="margin-top:5px;"><a href="/admin/central-distribuicaos/${c.id}/edit" target="_blank" style="color:#3b82f6; font-weight:bold; text-decoration:underline;">📝 Editar Central</a></div>`);
                                            infraLayerGroup.addLayer(layer);

                                            if (fit) {
                                                let layerBounds = layer.getBounds();
                                                bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                                bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                            }
                                        } catch (e) {}
                                    } else if (c.latitude && c.longitude) {
                                        let marker = L.circleMarker([c.latitude, c.longitude], { radius: 10, fillColor: '#f59e0b', color: '#fff', weight: 2, fillOpacity: 0.9 });
                                        marker.bindPopup(`<b>Central:</b> ${c.nome}<br><b>Tipo:</b> ${c.tipo}<br><div style="margin-top:5px;"><a href="/admin/central-distribuicaos/${c.id}/edit" target="_blank" style="color:#3b82f6; font-weight:bold; text-decoration:underline;">📝 Editar Central</a></div>`);
                                        infraLayerGroup.addLayer(marker);
                                        if (fit) bounds.push([c.latitude, c.longitude]);
                                    }
                                });
                            }

                            if (zoom >= 15) {
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
                                        let totalLines = group.length;

                                        group.forEach((c, index) => {
                                            try {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let coords = geo.coordinates;

                                                if (totalLines > 1 && coords && coords.length >= 2) {
                                                    const spacing = 0.000018;
                                                    const offsetDistance = (index - (totalLines - 1) / 2) * spacing;
                                                    let newCoords = [];

                                                    for (let i = 0; i < coords.length; i++) {
                                                        let dx = 0, dy = 0;
                                                        if (i === 0) {
                                                            dx = coords[1][0] - coords[0][0];
                                                            dy = coords[1][1] - coords[0][1];
                                                        } else if (i === coords.length - 1) {
                                                            dx = coords[i][0] - coords[i-1][0];
                                                            dy = coords[i][1] - coords[i-1][1];
                                                        } else {
                                                            dx = coords[i+1][0] - coords[i-1][0];
                                                            dy = coords[i+1][1] - coords[i-1][1];
                                                        }

                                                        const len = Math.sqrt(dx*dx + dy*dy);
                                                        if (len === 0) {
                                                            newCoords.push(coords[i]);
                                                            continue;
                                                        }
                                                        const nx = -dy / len;
                                                        const ny = dx / len;

                                                        newCoords.push([
                                                            coords[i][0] + nx * offsetDistance,
                                                            coords[i][1] + ny * offsetDistance
                                                        ]);
                                                    }
                                                    geo.coordinates = newCoords;
                                                }

                                                let color = '#3b82f6';
                                                if(c.tipo_cabo === 'fibra_optica') color = '#06b6d4';
                                                if(c.tipo_cabo === 'fibra_backbone') color = '#facc15';
                                                if(c.tipo_cabo === 'eletrico_alta_tensao') color = '#ef4444';

                                                let layer = L.geoJSON(geo, { style: { color: color, weight: 4, opacity: 0.85 } });
                                                layer.bindPopup(`<b>Cabo:</b> ${c.nome || c.tipo_cabo}<br><b>Tipo:</b> ${c.tipo_cabo}<br><b>Estado:</b> ${c.estado_conservacao}<br><div style="margin-top:5px;"><a href="/admin/cabeamentos/${c.id}/edit" target="_blank" style="color:#3b82f6; font-weight:bold; text-decoration:underline;">📝 Editar Cabo</a></div>`);
                                                infraLayerGroup.addLayer(layer);

                                                if (fit) {
                                                    let layerBounds = layer.getBounds();
                                                    bounds.push([layerBounds.getSouthWest().lat, layerBounds.getSouthWest().lng]);
                                                    bounds.push([layerBounds.getNorthEast().lat, layerBounds.getNorthEast().lng]);
                                                }
                                            } catch(e) {}
                                        });
                                    });
                                }

                                if (data.equipamentos) {
                                    data.equipamentos.forEach(eq => {
                                        if (eq.latitude && eq.longitude) {
                                            let marker = L.circleMarker([eq.latitude, eq.longitude], { radius: 5, fillColor: '#10b981', color: '#fff', weight: 1, fillOpacity: 1 });
                                            marker.bindPopup(`<b>Equipamento:</b> ${eq.nome}<br><b>Tipo:</b> ${eq.tipo}<br><b>Estado:</b> ${eq.estado_conservacao}<br><div style="margin-top:5px;"><a href="/admin/equipamento-infraestruturas/${eq.id}/edit" target="_blank" style="color:#3b82f6; font-weight:bold; text-decoration:underline;">📝 Editar Equipamento</a></div>`);
                                            infraLayerGroup.addLayer(marker);
                                            if (fit) bounds.push([eq.latitude, eq.longitude]);
                                        }
                                    });
                                }

                                if (data.postes) {
                                    data.postes.forEach(p => {
                                        if (p.latitude && p.longitude) {
                                            let marker = L.circleMarker([p.latitude, p.longitude], { radius: 5, fillColor: '#9ca3af', color: '#000', weight: 1, fillOpacity: 1 });
                                            marker.bindPopup(`<b>Poste:</b> ${p.codigo_patrimonio || 'S/N'}<br><b>Material:</b> ${p.material}<br><b>Estado:</b> ${p.estado_conservacao}<br><div style="margin-top:5px;"><a href="/admin/postes/${p.id}/edit" target="_blank" style="color:#3b82f6; font-weight:bold; text-decoration:underline;">📝 Editar Poste</a></div>`);
                                            infraLayerGroup.addLayer(marker);
                                            if (fit) bounds.push([p.latitude, p.longitude]);
                                        }
                                    });
                                }

                                if (data.antenas) {
                                    data.antenas.forEach(a => {
                                        if (a.latitude && a.longitude) {
                                            let marker = L.circleMarker([a.latitude, a.longitude], { radius: 6, fillColor: '#ec4899', color: '#fff', weight: 2, fillOpacity: 1 });
                                            marker.bindPopup(`<b>Antena:</b> ${a.codigo_patrimonio || 'S/N'}<br><b>Sinal:</b> ${a.tipo_sinal}<br><b>Estado:</b> ${a.estado_conservacao}<br><div style="margin-top:5px;"><a href="/admin/antenas/${a.id}/edit" target="_blank" style="color:#3b82f6; font-weight:bold; text-decoration:underline;">📝 Editar Antena</a></div>`);
                                            infraLayerGroup.addLayer(marker);
                                            if (fit) bounds.push([a.latitude, a.longitude]);
                                        }
                                    });
                                }
                            }

                            if(fit && bounds.length > 0) {
                                infraMap.fitBounds(bounds, { padding: [50, 50] });
                            }
                        },

                        initThree() {
                            const container = document.getElementById('infra-3d-container');
                            if (!container || renderer3D) return;

                            const width = container.clientWidth;
                            const height = container.clientHeight || 750;

                            scene3D = new THREE.Scene();
                            scene3D.background = new THREE.Color(0x020408); // Fundo preto ultra-escuro para contraste máximo

                            camera3D = new THREE.PerspectiveCamera(45, width / height, 1, 10000);
                            camera3D.position.set(0, 200, 450);

                            renderer3D = new THREE.WebGLRenderer({ antialias: true });
                            renderer3D.setSize(width, height);
                            renderer3D.setPixelRatio(window.devicePixelRatio);
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

                            // Grid Ground
                            const gridHelper = new THREE.GridHelper(6000, 120, 0x334155, 0x111827); // Grid mais visível
                            scene3D.add(gridHelper);

                            // Lights
                            const ambient = new THREE.AmbientLight(0xffffff, 1.0); // Luz ambiente forte
                            scene3D.add(ambient);

                            const dir1 = new THREE.DirectionalLight(0xffffff, 1.3); // Luz direcional forte
                            dir1.position.set(400, 800, 400);
                            scene3D.add(dir1);

                            const dir2 = new THREE.DirectionalLight(0x38bdf8, 0.6); // Luz azul de preenchimento mais intensa
                            dir2.position.set(-400, 300, -400);
                            scene3D.add(dir2);

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
                                        title = '⚡ Poste de Distribuição';
                                        const d = item.data;
                                        html = `<b>Código Patrimônio:</b> ${d.codigo_patrimonio || 'S/N'}<br>
                                                <b>Material:</b> ${d.material}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/postes/${d.id}/edit`;
                                    } else if (item.type === 'cabo') {
                                        title = '🔌 Cabo / Fiação';
                                        const d = item.data;
                                        html = `<b>Identificador:</b> ${d.nome || d.tipo_cabo}<br>
                                                <b>Tipo Fiação:</b> ${d.tipo_cabo}<br>
                                                <b>Estado de Conservação:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/cabeamentos/${d.id}/edit`;
                                    } else if (item.type === 'equipamento') {
                                        title = '🛠️ Equipamento de Rede';
                                        const d = item.data;
                                        html = `<b>Nome:</b> ${d.nome}<br>
                                                <b>Tipo Equipamento:</b> ${d.tipo}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/equipamento-infraestruturas/${d.id}/edit`;
                                    } else if (item.type === 'central') {
                                        title = '🏢 Central / Estação';
                                        const d = item.data;
                                        html = `<b>Nome:</b> ${d.nome}<br>
                                                <b>Tipo Central:</b> ${d.tipo}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/central-distribuicaos/${d.id}/edit`;
                                    } else if (item.type === 'antena') {
                                        title = '📡 Antena Telecom';
                                        const d = item.data;
                                        html = `<b>Código Antena:</b> ${d.codigo_patrimonio || 'S/N'}<br>
                                                <b>Tipo de Sinal:</b> ${d.tipo_sinal}<br>
                                                <b>Estado:</b> ${d.estado_conservacao || 'bom'}`;
                                        editUrl = `/admin/antenas/${d.id}/edit`;
                                    }

                                    if (html) {
                                        let tooltip = document.getElementById('three-tooltip');
                                        if (tooltip) {
                                            tooltip.innerHTML = `
                                                <div style="font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 6px; margin-bottom: 8px; color: #38bdf8; font-size: 13px;">
                                                    ${title}
                                                </div>
                                                <div style="line-height: 1.5; color: #cbd5e1; font-size: 11px;">
                                                    ${html}
                                                </div>
                                                <div style="margin-top: 12px; text-align: right;">
                                                    <a href="${editUrl}" target="_blank" style="display: inline-block; background: #2563eb; color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 11px; transition: background 0.2s;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                                                        📝 Editar Registro
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

                        renderThree(data) {
                            if (!scene3D) return;

                            // Limpar objetos anteriores (preservando o grid e as luzes)
                            const toRemove = [];
                            scene3D.traverse(child => {
                                if (child.isMesh || child.isLine) {
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

                            // OTIMIZAÇÃO EXTREMA: Reutilização de Materiais e Geometrias no WebGL
                            const cylinderGeo = new THREE.CylinderGeometry(0.12, 0.18, 1, 8); // Altura base 1 (vamos escalar)

                            const poleMaterials = {
                                concrete: new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.6 }),
                                wood: new THREE.MeshStandardMaterial({ color: 0x8b5a2b, roughness: 0.6 }),
                                iron: new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 }),
                                fiber: new THREE.MeshStandardMaterial({ color: 0x06b6d4, roughness: 0.6 }),
                                default: new THREE.MeshStandardMaterial({ color: 0x555555, roughness: 0.6 })
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
                                default: new THREE.LineBasicMaterial({ color: 0x475569, linewidth: 2 })
                            };

                            // 1. Renderizar Vias (Ruas)
                            if (data.vias) {
                                data.vias.forEach(v => {
                                    if (!v.geojson) return;
                                    try {
                                        let geo = typeof v.geojson === 'string' ? JSON.parse(v.geojson) : v.geojson;
                                        let coords = geo.coordinates;
                                        if (!coords || coords.length < 2) return;

                                        let colorHex = 0x10b981;
                                        if (v.nivel_congestionamento === 'moderado') colorHex = 0xfacc15;
                                        if (v.nivel_congestionamento === 'intenso') colorHex = 0xf97316;
                                        if (v.nivel_congestionamento === 'parado') colorHex = 0xef4444;

                                        const points = [];
                                        coords.forEach(c => {
                                            const pos = getLocalCoords(c[1], c[0]);
                                            points.push(new THREE.Vector3(pos.x, 0.05, pos.z));
                                        });

                                        const geometry = new THREE.BufferGeometry().setFromPoints(points);
                                        const material = new THREE.LineBasicMaterial({ color: colorHex, linewidth: 2 });
                                        const line = new THREE.Line(geometry, material);
                                        scene3D.add(line);
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
                                    box.position.set(x, 2, z);
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

                                    const material = poleMaterials[p.material] || poleMaterials.default;
                                    const cylinder = new THREE.Mesh(cylinderGeo, material);

                                    // Escalar verticalmente conforme a altura do poste
                                    cylinder.scale.set(1, height, 1);
                                    cylinder.position.set(pos.x, height / 2, pos.z);

                                    // Associar dados do poste para cliques Raycast
                                    cylinder.userData = { type: 'poste', data: p };
                                    scene3D.add(cylinder);

                                    polesMap[p.id] = {
                                        id: p.id,
                                        x: pos.x,
                                        z: pos.z,
                                        height: height
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
                                    tower.position.set(pos.x, height / 2, pos.z);
                                    // Associar dados da antena
                                    tower.userData = { type: 'antena', data: a };
                                    scene3D.add(tower);

                                    const signal = new THREE.Mesh(eqGeometries.antenaSignal, eqMaterials.antenaSignal);
                                    signal.position.set(pos.x, height, pos.z);
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
                                                startPos = new THREE.Vector3(p.x, p.height - (index * 0.45) - 0.3, p.z);
                                            } else if (c.central_origem_id) {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let pt = geo.coordinates[0];
                                                let local = getLocalCoords(pt[1], pt[0]);
                                                startPos = new THREE.Vector3(local.x, 0.2, local.z);
                                            }

                                            if (c.poste_destino_id && polesMap[c.poste_destino_id]) {
                                                const p = polesMap[c.poste_destino_id];
                                                endPos = new THREE.Vector3(p.x, p.height - (index * 0.45) - 0.3, p.z);
                                            } else if (c.central_destino_id) {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let pt = geo.coordinates[geo.coordinates.length - 1];
                                                let local = getLocalCoords(pt[1], pt[0]);
                                                endPos = new THREE.Vector3(local.x, 0.2, local.z);
                                            }

                                            if (!startPos || !endPos) {
                                                let geo = typeof c.geojson === 'string' ? JSON.parse(c.geojson) : c.geojson;
                                                let coords = geo.coordinates;
                                                if (coords && coords.length >= 2) {
                                                    let c1 = coords[0];
                                                    let c2 = coords[coords.length - 1];
                                                    let local1 = getLocalCoords(c1[1], c1[0]);
                                                    let local2 = getLocalCoords(c2[1], c2[0]);
                                                    startPos = new THREE.Vector3(local1.x, 9.5 - (index * 0.4), local1.z);
                                                    endPos = new THREE.Vector3(local2.x, 9.5 - (index * 0.4), local2.z);
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

                                                const geometry = new THREE.BufferGeometry().setFromPoints(curvePoints);

                                                const material = cableMaterials[c.tipo_cabo] || cableMaterials.default;
                                                const line = new THREE.Line(geometry, material);

                                                // Associar dados do cabo para clique
                                                line.userData = { type: 'cabo', data: c };
                                                scene3D.add(line);
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
