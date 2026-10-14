export function restoreNavigationGroups(groups, storage, key) {
    let saved = {};
    try {
        const value = JSON.parse(storage.getItem(key));
        if (value && typeof value === 'object' && !Array.isArray(value)) saved = value;
    } catch {}
    for (const group of groups) {
        const value = saved[group.dataset.navigationGroup];
        if (group.dataset.navigationActive === 'true') group.open = true;
        else if (typeof value === 'boolean') group.open = value;
    }
}

export function initializeAdminNavigation(nav) {
    if (!nav) return;
    const key = `admin_navigation_groups:${nav.dataset.navigationRole}`;
    const scrollKey = `admin_navigation_scroll:${nav.dataset.navigationRole}`;
    const groups = Array.from(nav.querySelectorAll('[data-navigation-group]'));
    try { restoreNavigationGroups(groups, window.localStorage, key); } catch {}
    const rememberGroups = () => {
        const state = Object.fromEntries(groups.map(group => [group.dataset.navigationGroup, group.open]));
        try { window.localStorage.setItem(key, JSON.stringify(state)); } catch {}
    };
    for (const group of groups) group.addEventListener('toggle', rememberGroups);

    try {
        const saved = window.sessionStorage.getItem(scrollKey);
        if (saved !== null && Number.isFinite(Number(saved))) nav.scrollTop = Number(saved);
    } catch {}
    const current = nav.querySelector('[aria-current="page"]');
    if (current) {
        const bounds = nav.getBoundingClientRect();
        const target = current.getBoundingClientRect();
        if (target.top < bounds.top || target.bottom > bounds.bottom) current.scrollIntoView({ block: 'nearest' });
    }
    const rememberScroll = () => {
        try { window.sessionStorage.setItem(scrollKey, String(nav.scrollTop)); } catch {}
    };
    let timer;
    nav.addEventListener('scroll', () => {
        clearTimeout(timer);
        timer = setTimeout(rememberScroll, 50);
    }, { passive: true });
    nav.addEventListener('click', event => {
        if (event.target.closest('a')) rememberScroll();
    });
    window.addEventListener('beforeunload', rememberScroll);
}

if (typeof document !== 'undefined') {
    const initialize = () => initializeAdminNavigation(document.getElementById('admin-sidebar-nav'));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
