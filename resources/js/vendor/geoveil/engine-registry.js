const engineIdPattern = /^[a-z][a-z0-9_-]*$/;

/** Registry shared by map startup and the asynchronous engine benchmark. */
export class EngineAdapterRegistry {
    #factories = new Map();

    constructor(entries = []) {
        for (const [id, factory] of entries) this.register(id, factory);
    }

    register(id, factory, { replace = false } = {}) {
        if (typeof id !== 'string' || !engineIdPattern.test(id)) {
            throw new TypeError('O identificador do engine deve ser seguro e estável.');
        }
        if (typeof factory !== 'function') throw new TypeError('O adaptador do engine deve ser uma função.');
        if (this.#factories.has(id) && !replace) throw new Error(`O engine "${id}" já foi registrado.`);
        this.#factories.set(id, factory);
        return this;
    }

    get(id) {
        return this.#factories.get(id) ?? null;
    }

    ids() {
        return [...this.#factories.keys()];
    }
}

/** Enforce the lifecycle shared by all engine adapters. */
export function validateEngineAdapter(adapter, id, config = {}) {
    const required = ['update', 'fit', 'destroy'];
    if (config.selectionTools) required.push('setSelectionTool');
    const missing = required.filter(method => typeof adapter?.[method] !== 'function');
    if (missing.length) {
        try { adapter?.destroy?.(); } catch { /* Preserve the lifecycle error below. */ }
        throw new TypeError(`O adaptador do engine "${id}" não implementa: ${missing.join(', ')}.`);
    }

    return adapter;
}
