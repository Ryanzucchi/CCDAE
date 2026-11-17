/** Select low-priority expanded panels to collapse until a zone fits. */
export function overlayPanelsToCollapse(panels, overflow) {
    if (!Number.isFinite(overflow) || overflow <= 0) return [];
    const candidates = panels
        .map((panel, index) => ({ ...panel, index }))
        .filter(panel => panel.open && !panel.userControlled && panel.saving > 0)
        .sort((left, right) => left.priority - right.priority || right.index - left.index);
    const collapsed = [];
    for (const panel of candidates) {
        if (overflow <= 0) break;
        collapsed.push(panel.id);
        overflow -= panel.saving;
    }

    return collapsed;
}
