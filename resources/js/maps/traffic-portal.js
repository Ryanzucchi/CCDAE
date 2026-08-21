import { featureCollection } from '@turf/helpers';
import { createMap, fitMapToFeatureCollection, maplibregl } from './maplibre/base-map';
import { MapLegendControl, SurfaceElevationSliderControl, TimeRangeControl } from './maplibre/legend-control';

const DEFINITIONS = {
    infracoes: { title: 'Infrações', colors: ['#fef3c7', '#fb923c', '#dc2626', '#7f1d1d'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1350, influence: 0.0030 },
    volume: { title: 'Volume de veículos', colors: ['#e0f2fe', '#38bdf8', '#2563eb', '#312e81'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1800, influence: 0.0030 },
    acidentes: { title: 'Acidentes', colors: ['#ffedd5', '#fb923c', '#ef4444', '#7f1d1d'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1550, influence: 0.0028 },
    obras: { title: 'Obras', colors: ['#ecfccb', '#84cc16', '#ca8a04', '#713f12'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1450, influence: 0.0028 },
    densidade: { title: 'Densidade de postes', colors: ['#fef3c7', '#fbbf24', '#f97316', '#b91c1c'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1450, influence: 0.0018 },
    consumo: { title: 'Consumo médio', colors: ['#dbeafe', '#38bdf8', '#7c3aed', '#4c1d95'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1750, influence: 0.0050 },
    populacao: { title: 'População', colors: ['#dcfce7', '#22c55e', '#0f766e', '#164e63'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1850, influence: 0.0050 },
    densidade_rede: { title: 'Densidade da rede', colors: ['#cffafe', '#22d3ee', '#2563eb', '#312e81'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1550, influence: 0.0022 },
    demanda: { title: 'Demanda potencial', colors: ['#ecfeff', '#06b6d4', '#7c3aed', '#4c1d95'], maximumColor: '#f0abfc', extremeColor: '#39ff14', altitude: 1750, influence: 0.0050 },
};

const intensityData = new Map();
const maps = new WeakMap();
const COLOR_BANDS = ['#172554', '#1d4ed8', '#06b6d4', '#10b981', '#84cc16', '#facc15', '#f97316', '#ef4444', '#a21caf', '#39ff14'];
const CLASSIFICATIONS = ['Mínimo', 'Muito baixo', 'Baixo', 'Baixo moderado', 'Moderado baixo', 'Moderado', 'Moderado alto', 'Elevado', 'Alto', 'Muito alto', 'Severo', 'Muito severo', 'Crítico', 'Extremo', 'Máximo'];

function discreteGradient() {
    const stops = [];
    COLOR_BANDS.forEach((color, index) => {
        const start = index * 10;
        const end = (index + 1) * 10;
        stops.push(`${color} ${start}%`, `${color} ${end}%`);
    });
    return `linear-gradient(90deg, ${stops.join(', ')})`;
}

function heightScale(value) {
    const normalized = Math.max(0, Math.min(1, value));
    return normalized ** 1.6;
}

async function loadIntensityData(type, start, end, endpoint = '/api/transito/intensidade') {
    const cacheKey = `${endpoint}:${type}:${start}:${end}`;
    if (intensityData.has(cacheKey)) return intensityData.get(cacheKey);
    const query = endpoint.includes('/transito/') ? `?${new URLSearchParams({ tipo: type, inicio: start, fim: end })}` : '';
    const response = await fetch(`${endpoint}${query}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const payload = await response.json();
    intensityData.set(cacheKey, payload);
    return payload;
}

function buildSpatialIndex(points, influence) {
    const cellSize = influence * 2;
    const cells = new Map();
    for (const point of points) {
        const key = `${Math.floor(point.longitude / cellSize)}:${Math.floor(point.latitude / cellSize)}`;
        if (!cells.has(key)) cells.set(key, []);
        cells.get(key).push(point);
    }
    return { cells, cellSize };
}

function interpolate(index, longitude, latitude, influence) {
    let accumulatedPower = 0;
    const centerX = Math.floor(longitude / index.cellSize);
    const centerY = Math.floor(latitude / index.cellSize);
    for (let offsetX = -2; offsetX <= 2; offsetX++) {
        for (let offsetY = -2; offsetY <= 2; offsetY++) {
            const nearby = index.cells.get(`${centerX + offsetX}:${centerY + offsetY}`) ?? [];
            for (const point of nearby) {
                const dx = (longitude - point.longitude) * Math.cos(latitude * Math.PI / 180);
                const dy = latitude - point.latitude;
                const distanceSquared = dx * dx + dy * dy;
                const spatialWeight = Math.exp(-distanceSquared / (2 * influence * influence));
                const contribution = point.weight * spatialWeight;
                accumulatedPower += contribution ** 4;
            }
        }
    }
    return Math.min(1, accumulatedPower ** 0.25);
}

function buildWaveSurface(data, type, definition) {
    const points = data.features.map((feature) => ({
        longitude: Number(feature.geometry?.coordinates?.[0]),
        latitude: Number(feature.geometry?.coordinates?.[1]),
        weight: Number(feature.properties?.weight) || 0,
    })).filter((point) => Number.isFinite(point.longitude) && Number.isFinite(point.latitude));
    const padding = type === 'infracoes' ? 0.007 : (['consumo', 'populacao', 'densidade'].includes(type) ? 0.004 : 0.011);
    const west = Math.min(...points.map((point) => point.longitude)) - padding;
    const east = Math.max(...points.map((point) => point.longitude)) + padding;
    const south = Math.min(...points.map((point) => point.latitude)) - padding;
    const north = Math.max(...points.map((point) => point.latitude)) + padding;
    const divisions = 170;
    const longitudeStep = (east - west) / divisions;
    const latitudeStep = (north - south) / divisions;
    const positions = [];
    const normals = [];
    const weights = [];
    const indices = [];
    const spatialIndex = buildSpatialIndex(points, definition.influence);

    for (let row = 0; row <= divisions; row++) {
        for (let column = 0; column <= divisions; column++) {
            const longitude = west + column * longitudeStep;
            const latitude = south + row * latitudeStep;
            const value = interpolate(spatialIndex, longitude, latitude, definition.influence);
            const relativeHeight = heightScale(value);
            const coordinate = maplibregl.MercatorCoordinate.fromLngLat([longitude, latitude], 5 + relativeHeight * definition.altitude);
            positions.push(coordinate.x, coordinate.y, coordinate.z);
            weights.push(value);
        }
    }
    for (let row = 0; row < divisions; row++) {
        for (let column = 0; column <= divisions; column++) {
            const a = row * (divisions + 1) + column;
            const b = a + 1;
            const c = a + divisions + 1;
            const d = c + 1;
            indices.push(a, c, b, b, c, d);
        }
    }
    const vertexAt = (row, column) => {
        const index = (row * (divisions + 1) + column) * 3;
        return [positions[index], positions[index + 1], positions[index + 2]];
    };
    for (let row = 0; row <= divisions; row++) {
        for (let column = 0; column <= divisions; column++) {
            const left = vertexAt(row, Math.max(0, column - 1));
            const right = vertexAt(row, Math.min(divisions, column + 1));
            const down = vertexAt(Math.max(0, row - 1), column);
            const up = vertexAt(Math.min(divisions, row + 1), column);
            const tangentX = [right[0] - left[0], right[1] - left[1], right[2] - left[2]];
            const tangentY = [up[0] - down[0], up[1] - down[1], up[2] - down[2]];
            const normal = [
                tangentX[1] * tangentY[2] - tangentX[2] * tangentY[1],
                tangentX[2] * tangentY[0] - tangentX[0] * tangentY[2],
                tangentX[0] * tangentY[1] - tangentX[1] * tangentY[0],
            ];
            const length = Math.hypot(...normal) || 1;
            normals.push(normal[0] / length, normal[1] / length, normal[2] / length);
        }
    }
    return { positions, normals, weights, indices, spatialIndex, definition };
}

function createShader(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(shader));
    return shader;
}

function createWaveLayer(id, surface) {
    return {
        id, type: 'custom', renderingMode: '3d', minWeight: 0.035,
        setMinWeight(val) {
            this.minWeight = Math.max(0.001, Math.min(0.999, val));
            this.map?.triggerRepaint();
        },
        onAdd(map, gl) {
            this.map = map;
            const vertexShader = createShader(gl, gl.VERTEX_SHADER, `#version 300 es
                precision highp float;
                uniform mat4 u_matrix;
                in vec3 a_position;
                in vec3 a_normal;
                in float a_weight;
                out vec3 v_normal;
                out float v_weight;
                void main() {
                    v_normal = a_normal;
                    v_weight = a_weight;
                    gl_Position = u_matrix * vec4(a_position, 1.0);
                }`);
            const fragmentShader = createShader(gl, gl.FRAGMENT_SHADER, `#version 300 es
                precision highp float;
                uniform float u_min_weight;
                in vec3 v_normal;
                in float v_weight;
                out vec4 outColor;

                const vec3 u_palette[10] = vec3[10](
                    vec3(0.090, 0.145, 0.329), // #172554 Azul escuro
                    vec3(0.114, 0.306, 0.847), // #1d4ed8 Azul vivo
                    vec3(0.024, 0.714, 0.831), // #06b6d4 Ciano
                    vec3(0.063, 0.725, 0.506), // #10b981 Verde Esmeralda
                    vec3(0.518, 0.800, 0.086), // #84cc16 Lima
                    vec3(0.980, 0.800, 0.082), // #facc15 Amarelo
                    vec3(0.976, 0.451, 0.086), // #f97316 Laranja
                    vec3(0.937, 0.267, 0.267), // #ef4444 Vermelho
                    vec3(0.635, 0.110, 0.686), // #a21caf Magenta
                    vec3(0.224, 1.000, 0.078)  // #39ff14 Verde Neon Extremo
                );

                vec3 getDynamicColor(float weight, float minWeight) {
                    float range = max(0.005, 1.0 - minWeight);
                    float t = clamp((weight - minWeight) / range, 0.0, 0.999);
                    int band = int(floor(t * 10.0));
                    band = clamp(band, 0, 9);
                    return u_palette[band];
                }

                void main() {
                    if (v_weight < u_min_weight) discard;
                    vec3 normal = normalize(v_normal);
                    float light = 0.70 + 0.30 * abs(dot(normal, normalize(vec3(-0.45, 0.35, 0.82))));
                    float fade = smoothstep(u_min_weight, u_min_weight + 0.015, v_weight);
                    float alpha = fade * 0.88;
                    if (alpha <= 0.01) discard;
                    vec3 baseColor = getDynamicColor(v_weight, u_min_weight);
                    vec3 shadedColor = baseColor * light;
                    outColor = vec4(shadedColor * alpha, alpha);
                }`);
            this.program = gl.createProgram();
            gl.attachShader(this.program, vertexShader);
            gl.attachShader(this.program, fragmentShader);
            gl.linkProgram(this.program);
            if (!gl.getProgramParameter(this.program, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(this.program));
            gl.deleteShader(vertexShader);
            gl.deleteShader(fragmentShader);
            this.matrixLocation = gl.getUniformLocation(this.program, 'u_matrix');
            this.minWeightLocation = gl.getUniformLocation(this.program, 'u_min_weight');
            this.vao = gl.createVertexArray();
            gl.bindVertexArray(this.vao);
            const positionBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(surface.positions), gl.STATIC_DRAW);
            const positionLocation = gl.getAttribLocation(this.program, 'a_position');
            gl.enableVertexAttribArray(positionLocation);
            gl.vertexAttribPointer(positionLocation, 3, gl.FLOAT, false, 0, 0);
            const normalBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, normalBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(surface.normals), gl.STATIC_DRAW);
            const normalLocation = gl.getAttribLocation(this.program, 'a_normal');
            gl.enableVertexAttribArray(normalLocation);
            gl.vertexAttribPointer(normalLocation, 3, gl.FLOAT, false, 0, 0);
            const weightBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, weightBuffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(surface.weights), gl.STATIC_DRAW);
            const weightLocation = gl.getAttribLocation(this.program, 'a_weight');
            gl.enableVertexAttribArray(weightLocation);
            gl.vertexAttribPointer(weightLocation, 1, gl.FLOAT, false, 0, 0);
            this.indexBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, this.indexBuffer);
            gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint32Array(surface.indices), gl.STATIC_DRAW);
            this.indexCount = surface.indices.length;
            gl.bindVertexArray(null);
        },
        render(gl, args) {
            this.projectionMatrix = args.defaultProjectionData.mainMatrix;
            gl.useProgram(this.program);
            gl.uniformMatrix4fv(this.matrixLocation, false, args.defaultProjectionData.mainMatrix);
            gl.uniform1f(this.minWeightLocation, this.minWeight ?? 0.035);
            gl.bindVertexArray(this.vao);
            gl.drawElements(gl.TRIANGLES, this.indexCount, gl.UNSIGNED_INT, 0);
            gl.bindVertexArray(null);
        },
        project(coordinate, width, height) {
            const matrix = this.projectionMatrix;
            if (!matrix) return null;
            const [x, y, z] = coordinate;
            const clipX = matrix[0] * x + matrix[4] * y + matrix[8] * z + matrix[12];
            const clipY = matrix[1] * x + matrix[5] * y + matrix[9] * z + matrix[13];
            const clipW = matrix[3] * x + matrix[7] * y + matrix[11] * z + matrix[15];
            if (clipW <= 0) return null;
            return { x: (clipX / clipW + 1) * width / 2, y: (1 - clipY / clipW) * height / 2 };
        },
        onRemove(map, gl) {
            gl.deleteVertexArray(this.vao);
            gl.deleteBuffer(this.indexBuffer);
            gl.deleteProgram(this.program);
        },
    };
}

function createHoverPointLayer(id) {
    return {
        id, type: 'custom', renderingMode: '3d', visible: false, coordinate: [0, 0, 0],
        setPoint(longitude, latitude, altitude) {
            const coordinate = maplibregl.MercatorCoordinate.fromLngLat([longitude, latitude], altitude);
            this.coordinate = [coordinate.x, coordinate.y, coordinate.z];
            this.visible = true;
            this.map?.triggerRepaint();
        },
        hide() { this.visible = false; this.map?.triggerRepaint(); },
        onAdd(map, gl) {
            this.map = map;
            const vertexShader = createShader(gl, gl.VERTEX_SHADER, `#version 300 es
                precision highp float;
                uniform mat4 u_matrix;
                uniform vec3 u_point;
                void main() {
                    gl_Position = u_matrix * vec4(u_point, 1.0);
                    gl_PointSize = 20.0;
                }`);
            const fragmentShader = createShader(gl, gl.FRAGMENT_SHADER, `#version 300 es
                precision highp float;
                out vec4 outColor;
                void main() {
                    float distanceFromCenter = length(gl_PointCoord - vec2(0.5));
                    if (distanceFromCenter > 0.5) discard;
                    float ring = smoothstep(0.5, 0.38, distanceFromCenter) - smoothstep(0.32, 0.20, distanceFromCenter);
                    float core = smoothstep(0.20, 0.08, distanceFromCenter);
                    vec3 col = mix(vec3(0.02, 0.71, 0.83), vec3(1.0, 1.0, 1.0), core);
                    float alpha = max(ring * 0.9, core);
                    outColor = vec4(col * alpha, alpha);
                }`);
            this.program = gl.createProgram();
            gl.attachShader(this.program, vertexShader);
            gl.attachShader(this.program, fragmentShader);
            gl.linkProgram(this.program);
            this.matrixLocation = gl.getUniformLocation(this.program, 'u_matrix');
            this.pointLocation = gl.getUniformLocation(this.program, 'u_point');
        },
        render(gl, args) {
            if (!this.visible) return;
            gl.useProgram(this.program);
            gl.uniformMatrix4fv(this.matrixLocation, false, args.defaultProjectionData.mainMatrix);
            gl.uniform3fv(this.pointLocation, this.coordinate);
            gl.drawArrays(gl.POINTS, 0, 1);
        },
        onRemove(map, gl) { gl.deleteProgram(this.program); },
    };
}

async function initializeIntensityMap(container) {
    if (maps.has(container)) return maps.get(container);
    const type = container.dataset.mapType;
    const energyMap = container.hasAttribute('data-energy-analytic-map');
    const internetMap = container.hasAttribute('data-internet-analytic-map');
    const endpoint = internetMap ? '/api/internet/analiticos' : (energyMap ? '/api/energia/analiticos' : '/api/transito/intensidade');
    const definition = DEFINITIONS[type];
    if (!definition) return null;
    const status = container.parentElement?.querySelector('[data-traffic-wave-status]');
    const map = createMap(container, { center: [-57.6875, -16.07083], zoom: 12 });
    const pageQuery = new URLSearchParams(window.location.search);
    const toLocalInput = (date) => {
        const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 16);
    };
    const selectedEnd = pageQuery.get('fim') || toLocalInput(new Date());
    const selectedStart = pageQuery.get('inicio') || toLocalInput(new Date(Date.now() - 24 * 60 * 60 * 1000));
    if (!energyMap && !internetMap) map.addControl(new TimeRangeControl({
        start: selectedStart,
        end: selectedEnd,
        onApply: ({ start, end }) => {
            const url = new URL(window.location.href);
            url.searchParams.set('inicio', start);
            url.searchParams.set('fim', end);
            window.location.assign(url);
        },
    }), 'top-left');
    maps.set(container, map);

    map.on('load', async () => {
        try {
            const payload = await loadIntensityData(type, selectedStart, selectedEnd, endpoint);
            const data = payload[type] ?? featureCollection([]);
            if (!data.features?.length) {
                if (status) status.textContent = type === 'infracoes'
                    ? 'Nenhuma infração registrada no período selecionado.'
                    : 'Nenhum dado disponível para este período.';
                return;
            }
            const formatScaleValue = (value) => Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
            const rawMin = Number(data.properties?.minimum ?? 0);
            const rawMax = Number(data.properties?.maximum ?? 0);
            const rangeSpan = Math.max(0.001, rawMax - rawMin);

            const legendControl = new MapLegendControl({
                title: definition.title,
                subtitle: 'Intensidade relativa na cidade',
                badge: '10 faixas relativas',
                minimum: formatScaleValue(rawMin),
                maximum: formatScaleValue(rawMax),
                gradient: discreteGradient(),
            });
            map.addControl(legendControl, 'top-right');

            const surface = buildWaveSurface(data, type, definition);
            const waveLayer = createWaveLayer(`traffic-${type}-wave-surface`, surface);
            map.addLayer(waveLayer);
            const hoverPoint = createHoverPointLayer(`traffic-${type}-hover-point`);
            map.addLayer(hoverPoint);

            const tooltip = document.createElement('div');
            tooltip.className = 'pointer-events-none absolute z-30 hidden rounded-lg bg-gray-950/90 px-3 py-2 text-xs text-white shadow-xl';
            container.parentElement?.appendChild(tooltip);

            const candidates = data.features.map((feature) => {
                const numericValue = Number(feature.properties?.value) || 0;
                const weight = Number(feature.properties?.weight) || 0;
                const [longitude, latitude] = feature.geometry.coordinates;
                const surfaceWeight = interpolate(surface.spatialIndex, longitude, latitude, definition.influence);
                const altitude = 5 + heightScale(surfaceWeight) * definition.altitude + 18;
                const coordinate = maplibregl.MercatorCoordinate.fromLngLat([longitude, latitude], altitude);
                return { feature, numericValue, weight, longitude, latitude, altitude, coordinate: [coordinate.x, coordinate.y, coordinate.z] };
            });

            const maxCandidate = candidates.length
                ? candidates.reduce((prev, curr) => (curr.weight > prev.weight ? curr : prev), candidates[0])
                : null;
            const maxWeight = maxCandidate ? maxCandidate.weight : 1.0;

            let currentElevationCutoff = 0.035;

            const applyElevationCutoff = (percentage) => {
                const num = Math.max(1, Math.min(100, Math.round(percentage)));
                if (num === 1) {
                    currentElevationCutoff = 0.035;
                    hoverPoint.hide();
                    tooltip.classList.add('hidden');
                    legendControl.updateRange(
                        formatScaleValue(rawMin),
                        formatScaleValue(rawMax),
                        '10 faixas relativas'
                    );
                } else if (num === 100) {
                    currentElevationCutoff = Math.max(0.05, maxWeight - 0.015);
                    if (maxCandidate) {
                        hoverPoint.setPoint(maxCandidate.longitude, maxCandidate.latitude, maxCandidate.altitude);
                        const projected = waveLayer.project(maxCandidate.coordinate, map.getCanvas().clientWidth, map.getCanvas().clientHeight);
                        if (projected) {
                            tooltip.replaceChildren();
                            const title = document.createElement('strong');
                            const details = document.createElement('div');
                            title.textContent = maxCandidate.feature.properties?.name || 'Ponto de Maior Altitude/Pico';
                            details.className = 'mt-1 space-y-0.5';
                            const rows = [
                                `${definition.title}: ${maxCandidate.numericValue.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}`,
                                'Classificação: Topo Supremo',
                                'Escala total na cidade: 100%',
                            ];
                            rows.forEach((text) => { const row = document.createElement('div'); row.textContent = text; details.appendChild(row); });
                            tooltip.append(title, details);
                            tooltip.style.left = `${projected.x + 14}px`;
                            tooltip.style.top = `${projected.y + 14}px`;
                            tooltip.classList.remove('hidden');
                        }
                    }
                    legendControl.updateRange(
                        formatScaleValue(rawMax * 0.99),
                        formatScaleValue(rawMax),
                        'Pico Máximo (100%)'
                    );
                } else {
                    const progress = (num - 1) / 99;
                    currentElevationCutoff = 0.035 + progress * (maxWeight - 0.05);
                    hoverPoint.hide();
                    tooltip.classList.add('hidden');
                    const visibleMin = rawMin + rangeSpan * progress;
                    legendControl.updateRange(
                        formatScaleValue(visibleMin),
                        formatScaleValue(rawMax),
                        `Escala ${num}% a 100%`
                    );
                }
                waveLayer.setMinWeight(currentElevationCutoff);
            };

            map.addControl(new SurfaceElevationSliderControl({
                initialValue: 1,
                onChange: applyElevationCutoff,
            }), 'top-right');

            map.on('mousemove', (event) => {
                let nearest = null;
                let nearestDistance = 18;
                for (const candidate of candidates) {
                    if (candidate.weight < currentElevationCutoff) continue;
                    const projected = waveLayer.project(candidate.coordinate, map.getCanvas().clientWidth, map.getCanvas().clientHeight);
                    if (!projected) continue;
                    const distance = Math.hypot(projected.x - event.point.x, projected.y - event.point.y);
                    if (distance < nearestDistance) {
                        nearest = candidate;
                        nearestDistance = distance;
                    }
                }
                if (!nearest) {
                    if (map.getCanvas().style.cursor === 'pointer') {
                        hoverPoint.hide();
                        tooltip.classList.add('hidden');
                        map.getCanvas().style.cursor = '';
                    }
                    return;
                }
                hoverPoint.setPoint(nearest.longitude, nearest.latitude, nearest.altitude);
                tooltip.replaceChildren();
                const title = document.createElement('strong');
                const details = document.createElement('div');
                const relativeT = Math.min(0.999, Math.max(0, (nearest.weight - currentElevationCutoff) / Math.max(0.001, (1.0 - currentElevationCutoff))));
                const colorBand = Math.min(9, Math.floor(relativeT * 10));
                const classificationLevel = Math.min(15, Math.max(1, Math.floor(relativeT * 15) + 1));
                const classificationLabel = CLASSIFICATIONS[classificationLevel - 1];

                title.textContent = nearest.feature.properties?.name || 'Local sem via identificada';
                details.className = 'mt-1 space-y-0.5';
                const rows = [
                    `${definition.title}: ${nearest.numericValue.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}`,
                    `Classificação no corte: ${classificationLabel}`,
                    `Faixa de cor ativa: ${colorBand * 10}–${(colorBand + 1) * 10}%`,
                    `Percentual geral na cidade: ${(nearest.weight * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`,
                ];
                if (Number(nearest.feature.properties?.streets) > 1) rows.push(`${nearest.feature.properties.streets} vias nesta área`);
                rows.forEach((text) => { const row = document.createElement('div'); row.textContent = text; details.appendChild(row); });
                tooltip.append(title, details);
                tooltip.style.left = `${event.point.x + 14}px`;
                tooltip.style.top = `${event.point.y + 14}px`;
                tooltip.classList.remove('hidden');
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseout', () => { hoverPoint.hide(); tooltip.classList.add('hidden'); });
            fitMapToFeatureCollection(map, data, { padding: 55, maxZoom: 14 });
            map.easeTo({ pitch: 62, bearing: -18, duration: 700 });
            status?.remove();
        } catch (error) {
            console.error(`Erro ao carregar mapa de ${type}.`, error);
            if (status) status.textContent = 'Não foi possível carregar os dados deste mapa.';
        }
    });
    return map;
}

function initializePortal() {
    document.querySelectorAll('[data-traffic-wave-map], [data-energy-analytic-map], [data-internet-analytic-map]').forEach(initializeIntensityMap);
}

document.addEventListener('DOMContentLoaded', initializePortal, { once: true });
document.addEventListener('livewire:navigated', initializePortal);
if (document.readyState !== 'loading') initializePortal();
