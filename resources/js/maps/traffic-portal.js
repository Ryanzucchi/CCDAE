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
const COLOR_BAND_SIZE = 0.10;
const CLASSIFICATIONS = ['Mínimo', 'Muito baixo', 'Baixo', 'Baixo moderado', 'Moderado baixo', 'Moderado', 'Moderado alto', 'Elevado', 'Alto', 'Muito alto', 'Severo', 'Muito severo', 'Crítico', 'Extremo', 'Máximo'];

function colorBandFor(weight) {
    return Math.min(COLOR_BANDS.length - 1, Math.floor(Math.max(0, Math.min(1, weight)) / COLOR_BAND_SIZE));
}

function discreteGradient() {
    const stops = [];
    COLOR_BANDS.forEach((color, index) => {
        const start = index * 10;
        const end = (index + 1) * 10;
        stops.push(`${color} ${start}%`, `${color} ${end}%`);
    });
    return `linear-gradient(90deg, ${stops.join(', ')})`;
}

function classificationFor(weight) {
    const level = Math.min(15, Math.max(1, Math.floor(Math.max(0, Math.min(1, weight)) * 15) + 1));
    return { level, label: CLASSIFICATIONS[level - 1] };
}

function heightScale(value) {
    const normalized = Math.max(0, Math.min(1, value));
    const levels = 15;
    const position = normalized * levels;
    const level = Math.min(levels - 1, Math.floor(position));
    const progress = position - level;
    const maximum = (2 ** levels) - 1;
    const lower = level === 0 ? 0 : ((2 ** level) - 1) / maximum;
    const upper = ((2 ** (level + 1)) - 1) / maximum;
    const smoothProgress = progress * progress * (3 - 2 * progress);
    return lower + (upper - lower) * smoothProgress;
}

async function loadIntensityData(type, start, end, endpoint = '/api/transito/intensidade') {
    const cacheKey = `${endpoint}:${type}:${start}:${end}`;
    if (intensityData.has(cacheKey)) return intensityData.get(cacheKey);
    const params = endpoint.includes('/transito/')
        ? { tipo: type, inicio: start, fim: end }
        : { tipo: type };
    const query = `?${new URLSearchParams(params)}`;
    const response = await fetch(`${endpoint}${query}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const payload = await response.json();
    intensityData.set(cacheKey, payload);
    return payload;
}

function buildSpatialIndex(points, influence) {
    const cellSize = influence * 1.5;
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
    for (let offsetX = -3; offsetX <= 3; offsetX++) {
        for (let offsetY = -3; offsetY <= 3; offsetY++) {
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

    const padding = Math.max(0.018, definition.influence * 4.5);
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
        for (let column = 0; column < divisions; column++) {
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

function createWaveLayer(id, surface, maxWeight = 1.0) {
    return {
        id, type: 'custom', renderingMode: '3d', minWeight: 0.004, maxWeight,
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
                uniform float u_max_weight;
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

                void main() {
                    if (v_weight < u_min_weight) discard;
                    vec3 normal = normalize(v_normal);
                    float light = 0.68 + 0.32 * abs(dot(normal, normalize(vec3(-0.45, 0.35, 0.82))));
                    
                    float fadeWidth = (u_min_weight <= 0.005) ? 0.08 : 0.010;
                    float alpha = smoothstep(u_min_weight, u_min_weight + fadeWidth, v_weight) * 0.85;
                    if (alpha <= 0.001) discard;

                    float range = max(0.005, u_max_weight - u_min_weight);
                    float t = clamp((v_weight - u_min_weight) / range, 0.0, 0.999);
                    int band = clamp(int(floor(t * 10.0)), 0, 9);
                    vec3 baseColor = u_palette[band];
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
            this.maxWeightLocation = gl.getUniformLocation(this.program, 'u_max_weight');
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
            gl.uniform1f(this.minWeightLocation, this.minWeight ?? 0.004);
            gl.uniform1f(this.maxWeightLocation, this.maxWeight ?? 1.0);
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
                    gl_PointSize = 15.0;
                }`);
            const fragmentShader = createShader(gl, gl.FRAGMENT_SHADER, `#version 300 es
                precision highp float;
                out vec4 outColor;
                void main() {
                    float distanceFromCenter = length(gl_PointCoord - vec2(0.5));
                    if (distanceFromCenter > 0.5) discard;
                    float edge = smoothstep(0.5, 0.38, distanceFromCenter);
                    outColor = vec4(vec3(1.0) * edge, edge);
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
    const status = container.parentElement?.querySelector('[data-traffic-wave-status], [data-energy-status], [data-internet-status]');
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

            const featureValues = (data.features || [])
                .map((f) => Number(f.properties?.value))
                .filter((v) => Number.isFinite(v));

            let rawMin = Number(data.properties?.minimum);
            let rawMax = Number(data.properties?.maximum);

            if (!Number.isFinite(rawMin) || !Number.isFinite(rawMax) || (rawMin === 0 && rawMax === 0)) {
                if (featureValues.length > 0) {
                    rawMin = Math.min(...featureValues);
                    rawMax = Math.max(...featureValues);
                }
            }

            const isFlatScale = !Number.isFinite(rawMax) || rawMax <= rawMin || (rawMin === 0 && rawMax === 0);

            const formatDisplayValue = (val, asPercent = false) => {
                if (asPercent) return `${Math.round(val)}%`;
                const num = Number(val);
                if (!Number.isFinite(num)) return '0';
                if (num === 0) return '0';
                if (Math.abs(num) >= 100) return num.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                if (Math.abs(num) >= 1) return num.toLocaleString('pt-BR', { maximumFractionDigits: 1 });
                return num.toLocaleString('pt-BR', { maximumFractionDigits: 2 });
            };

            const getLegendStrings = (percentage) => {
                const p = Math.max(1, Math.min(100, Math.round(percentage)));
                if (isFlatScale) {
                    return {
                        minStr: p === 1 ? '0%' : `${p}%`,
                        maxStr: '100%',
                        badgeStr: p === 1 ? '10 faixas de 10%' : (p >= 100 ? 'Pico Máximo' : `Escala ${p}% a 100%`),
                    };
                }
                const visibleMin = p === 1 ? rawMin : (p >= 100 ? rawMax : rawMin + (rawMax - rawMin) * (p / 100));
                return {
                    minStr: formatDisplayValue(visibleMin, false),
                    maxStr: formatDisplayValue(rawMax, false),
                    badgeStr: p === 1 ? '10 faixas de 10%' : (p >= 100 ? 'Pico Máximo' : `Escala ${p}% a 100%`),
                };
            };

            const initialStrings = getLegendStrings(1);

            const legendControl = new MapLegendControl({
                title: definition.title,
                subtitle: 'Intensidade relativa na cidade',
                badge: initialStrings.badgeStr,
                minimum: initialStrings.minStr,
                maximum: initialStrings.maxStr,
                gradient: discreteGradient(),
            });
            map.addControl(legendControl, 'top-right');

            const surface = buildWaveSurface(data, type, definition);
            const maxSurfaceWeight = surface.weights.reduce((max, w) => Math.max(max, w), 0.05);

            const waveLayer = createWaveLayer(`traffic-${type}-wave-surface`, surface, maxSurfaceWeight);
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
                return { feature, numericValue, weight, surfaceWeight, longitude, latitude, altitude, coordinate: [coordinate.x, coordinate.y, coordinate.z] };
            });

            const topCandidate = candidates.length
                ? candidates.reduce((prev, curr) => (Math.max(curr.weight, curr.surfaceWeight) > Math.max(prev.weight, prev.surfaceWeight) ? curr : prev), candidates[0])
                : null;

            let currentElevationCutoff = 0.004;

            map.addControl(new SurfaceElevationSliderControl({
                initialValue: 1,
                onChange: (percentage) => {
                    const num = Math.max(1, Math.min(100, Math.round(percentage)));
                    const { minStr, maxStr, badgeStr } = getLegendStrings(num);

                    if (num === 1) {
                        currentElevationCutoff = 0.004;
                        hoverPoint.hide();
                        tooltip.classList.add('hidden');
                    } else if (num === 100) {
                        currentElevationCutoff = Math.max(0.005, maxSurfaceWeight - 0.015);
                        if (topCandidate) {
                            hoverPoint.setPoint(topCandidate.longitude, topCandidate.latitude, topCandidate.altitude);
                        }
                    } else {
                        const progress = (num - 1) / 99;
                        currentElevationCutoff = 0.004 + progress * (maxSurfaceWeight - 0.025);
                        hoverPoint.hide();
                        tooltip.classList.add('hidden');
                    }

                    legendControl.updateRange(minStr, maxStr, badgeStr);
                    waveLayer.setMinWeight(currentElevationCutoff);
                },
            }), 'top-right');

            map.on('mousemove', (event) => {
                let nearest = null;
                let nearestDistance = 24;
                for (const candidate of candidates) {
                    const effectiveWeight = Math.max(candidate.weight, candidate.surfaceWeight ?? 0);
                    if (effectiveWeight < currentElevationCutoff) continue;
                    const projected = waveLayer.project(candidate.coordinate, map.getCanvas().clientWidth, map.getCanvas().clientHeight);
                    if (!projected) continue;
                    const distance = Math.hypot(projected.x - event.point.x, projected.y - event.point.y);
                    if (distance < nearestDistance) {
                        nearest = candidate;
                        nearestDistance = distance;
                    }
                }
                if (!nearest) {
                    hoverPoint.hide();
                    tooltip.classList.add('hidden');
                    map.getCanvas().style.cursor = '';
                    return;
                }
                hoverPoint.setPoint(nearest.longitude, nearest.latitude, nearest.altitude);
                tooltip.replaceChildren();
                const title = document.createElement('strong');
                const details = document.createElement('div');
                const effectiveWeight = Math.max(nearest.weight, nearest.surfaceWeight ?? 0);
                const relativeT = Math.min(0.999, Math.max(0, (effectiveWeight - currentElevationCutoff) / Math.max(0.001, (maxSurfaceWeight - currentElevationCutoff))));
                const activeBand = Math.min(9, Math.floor(relativeT * 10));
                const classification = classificationFor(relativeT);

                title.textContent = nearest.feature.properties?.name || 'Local sem via identificada';
                details.className = 'mt-1 space-y-0.5';
                const rows = [
                    `${definition.title}: ${formatDisplayValue(nearest.numericValue, false)}`,
                    `Classificação no corte: ${classification.label}`,
                    `Faixa de cor ativa: ${activeBand * 10}–${(activeBand + 1) * 10}%`,
                    `Percentual geral na cidade: ${(effectiveWeight * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`,
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
