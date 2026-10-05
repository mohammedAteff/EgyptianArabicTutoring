import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const layout = readFileSync(new URL('../resources/views/layouts/admin.blade.php', import.meta.url), 'utf8');
const factory = layout.slice(layout.indexOf('function adminMobileNav()'), layout.indexOf('window.adminMobileNav ='));

function navigation(collapsed, storage) {
    const document = { documentElement: { dataset: { sidebarCollapsed: String(collapsed) } } };
    const state = runInNewContext(`${factory}; adminMobileNav()`, { document, localStorage: storage });
    return { state, document };
}

test('desktop collapse frees layout width and persists across page navigation independently of mobile state', () => {
    const saved = new Map();
    const storage = { setItem: (key, value) => saved.set(key, value) };
    const first = navigation(false, storage);
    first.state.toggleDesktopSidebar();
    assert.equal(first.document.documentElement.dataset.sidebarCollapsed, 'true');
    assert.equal(saved.get('admin_sidebar_collapsed'), '1');
    assert.equal(first.state.mobileSidebarOpen, false);
    const next = navigation(saved.get('admin_sidebar_collapsed') === '1', storage);
    assert.equal(next.state.desktopCollapsed, true);
    next.state.toggleDesktopSidebar();
    assert.equal(next.document.documentElement.dataset.sidebarCollapsed, 'false');
    assert.equal(saved.get('admin_sidebar_collapsed'), '0');
    assert.match(layout, /html\[data-sidebar-collapsed="true"\] #admin-content \{ margin-left: 0; \}/);
});

test('blocked browser storage still allows desktop collapse and expansion', () => {
    const { state, document } = navigation(false, { setItem: () => { throw new Error('Storage blocked'); } });
    state.toggleDesktopSidebar();
    assert.equal(document.documentElement.dataset.sidebarCollapsed, 'true');
    state.toggleDesktopSidebar();
    assert.equal(document.documentElement.dataset.sidebarCollapsed, 'false');
});
