const initializedTables = new WeakSet();
const initializedRoots = new WeakSet();

export function mountBulkActions(scope = document) {
    if (scope.addEventListener && !initializedRoots.has(scope)) {
        initializedRoots.add(scope);
        scope.addEventListener('geoveil:bulk-select', event => {
            const { layer, ids } = event.detail ?? {};
            const selectedIds = new Set((ids ?? []).map(String));
            for (const wrapper of scope.querySelectorAll('[data-geoveil-resource-table]')) {
                if (wrapper.dataset.geoveilResourceLayer !== String(layer)) continue;
                for (const input of wrapper.querySelectorAll('[data-geoveil-row-select]')) input.checked = selectedIds.has(String(input.value));
                wrapper.dispatchEvent(new Event('geoveil:selection-sync'));
            }
        });
    }
    const tables = [...(scope.matches?.('[data-geoveil-resource-table]') ? [scope] : []), ...scope.querySelectorAll('[data-geoveil-resource-table]')];
    for (const wrapper of tables) {
        if (initializedTables.has(wrapper)) continue;
        initializedTables.add(wrapper);
        const table = wrapper.querySelector('[data-geoveil-table]');
        const toolbar = wrapper.querySelector('[data-geoveil-bulk-actions]');
        if (!table || !toolbar) continue;
        const rows = [...table.querySelectorAll('[data-geoveil-row-select]')];
        const selectAll = table.querySelector('[data-geoveil-select-all]');
        const count = toolbar.querySelector('[data-geoveil-selection-count]');
        const buttons = [...toolbar.querySelectorAll('button[type="submit"]')];
        const selected = () => rows.filter(input => input.checked);
        const update = () => {
            const checked = selected();
            if (count) count.textContent = `${checked.length} selecionado${checked.length === 1 ? '' : 's'}`;
            buttons.forEach(button => { button.disabled = checked.length === 0; });
            if (selectAll) {
                selectAll.checked = rows.length > 0 && checked.length === rows.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < rows.length;
            }
        };
        wrapper.addEventListener('geoveil:selection-sync', update);
        for (const input of rows) input.addEventListener('change', update);
        selectAll?.addEventListener('change', () => {
            rows.forEach(input => { input.checked = selectAll.checked; });
            update();
        });
        for (const form of toolbar.querySelectorAll('[data-geoveil-bulk-form]')) {
            form.addEventListener('submit', event => {
                form.querySelectorAll('[data-geoveil-generated-id]').forEach(input => input.remove());
                const checked = selected();
                if (checked.length === 0) {
                    event.preventDefault();
                    return;
                }
                const message = form.dataset.confirm;
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
                for (const row of checked) {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = 'selected[]'; input.value = row.value;
                    input.dataset.geoveilGeneratedId = '';
                    form.append(input);
                }
            });
        }
        wrapper.querySelectorAll('[data-geoveil-confirm]').forEach(control => {
            if (control.tagName === 'FORM') {
                control.addEventListener('submit', event => {
                    if (!window.confirm(control.dataset.geoveilConfirm)) event.preventDefault();
                });
            } else {
                control.addEventListener('click', event => {
                    if (!window.confirm(control.dataset.geoveilConfirm)) event.preventDefault();
                });
            }
        });
        update();
    }
}
