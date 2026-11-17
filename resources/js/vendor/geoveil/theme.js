/** Resolve a configured theme preference into the light/dark palette. */
export function resolveThemePreference(preference, prefersDark = false) {
    if (preference === 'auto') return prefersDark ? 'dark' : 'light';
    if (preference === 'light' || preference === 'dark') return preference;
    throw new TypeError(`Invalid theme preference: ${preference}`);
}
