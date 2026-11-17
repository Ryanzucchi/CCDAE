const CACHE_PREFIX = 'geoveil:engine-benchmark:v1:';
const CACHE_TTL = 30 * 24 * 60 * 60 * 1000;

function bucket(value, limits) {
    const index = limits.findIndex(limit => value <= limit);
    return index < 0 ? `>${limits.at(-1)}` : `<=${limits[index]}`;
}

export function benchmarkScenario(config, device = {}) {
    const featureCount = config.layers.reduce((sum, layer) => sum + (layer.data?.features?.length ?? 1000), 0);
    const capabilities = [...(config.plugin.selection?.requiredCapabilities ?? [])].sort();
    const geometries = [...new Set(config.layers.map(layer => layer.type))].sort();
    const dimension = capabilities.some(capability => ['surface', 'building'].includes(capability)) ? '3d' : '2d';
    const cores = Number(device.hardwareConcurrency ?? globalThis.navigator?.hardwareConcurrency ?? 4);
    const memory = Number(device.deviceMemory ?? globalThis.navigator?.deviceMemory ?? 4);

    return JSON.stringify({
        version: 1,
        engines: (config.plugin.selection?.candidates ?? []).map(engine => engine.id).sort(),
        capabilities,
        geometries,
        dimension,
        features: bucket(featureCount, [100, 1000, 10000]),
        cores: bucket(cores, [2, 4, 8]),
        memory: bucket(memory, [2, 4, 8]),
        viewport: bucket(Number(device.width ?? globalThis.innerWidth ?? 1280), [640, 1024, 1600]),
        touch: Boolean(device.touch ?? globalThis.navigator?.maxTouchPoints),
    });
}

export function readBenchmarkCache(storage, scenario, now = Date.now()) {
    try {
        const record = JSON.parse(storage?.getItem(`${CACHE_PREFIX}${scenario}`) ?? 'null');
        if (!record || record.version !== 1 || !Number.isFinite(record.savedAt) || now - record.savedAt > CACHE_TTL || !record.metrics || typeof record.metrics !== 'object') return null;
        return record.metrics;
    } catch {
        return null;
    }
}

export function writeBenchmarkCache(storage, scenario, metrics, now = Date.now()) {
    try {
        storage?.setItem(`${CACHE_PREFIX}${scenario}`, JSON.stringify({ version: 1, savedAt: now, metrics }));
        return true;
    } catch {
        return false;
    }
}

/** Do not treat partial trials as a completed comparison between engines. */
export function hasCompleteBenchmark(candidates, metrics) {
    return candidates.length > 0 && candidates.every(engine => {
        const score = metrics?.[engine.id]?.score;
        return score !== null && score !== undefined && Number.isFinite(Number(score));
    });
}

export function rankBenchmarks(candidates, metrics, fallbackId = null) {
    return [...candidates].sort((left, right) => {
        const leftScore = Number(metrics?.[left.id]?.score);
        const rightScore = Number(metrics?.[right.id]?.score);
        const a = Number.isFinite(leftScore) ? leftScore : -Infinity;
        const b = Number.isFinite(rightScore) ? rightScore : -Infinity;
        if (a !== b) return b - a;
        if (left.id === fallbackId) return -1;
        if (right.id === fallbackId) return 1;
        return left.id.localeCompare(right.id);
    });
}
