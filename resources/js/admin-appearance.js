const appearanceStorageKey = 'awa.admin.appearance.v1';
const appearances = new Set(['light', 'dark-teal', 'pure-black']);

export function normalizeAdminAppearance(value) {
    return appearances.has(value) ? value : 'light';
}

export function initializeAdminAppearance(documentRoot, storage = () => window.localStorage) {
    let root = documentRoot.documentElement;
    if (!root.hasAttribute('data-admin-appearance')) {
        root = documentRoot.querySelector('[data-admin-appearance-scope]');
        if (!root?.hasAttribute?.('data-admin-appearance-scope')) return;
    }

    const select = documentRoot.querySelector('[data-admin-appearance-select]');
    if (!select || select.dataset.appearanceInitialized === 'true') return;

    select.value = normalizeAdminAppearance(root.dataset.adminAppearance);
    select.dataset.appearanceInitialized = 'true';
    select.addEventListener('change', () => {
        const appearance = normalizeAdminAppearance(select.value);
        root.dataset.adminAppearance = appearance;
        select.value = appearance;

        try {
            storage().setItem(appearanceStorageKey, appearance);
        } catch {
            // The current document still changes when browser storage is unavailable.
        }
    });
}

if (typeof document !== 'undefined') {
    const initialize = () => initializeAdminAppearance(document);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
