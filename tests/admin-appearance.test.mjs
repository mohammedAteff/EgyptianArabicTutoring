import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import { initializeAdminAppearance, normalizeAdminAppearance } from '../resources/js/admin-appearance.js';
import { editableFormState } from '../resources/js/admin-ux.js';

const layout = readFileSync(new URL('../resources/views/layouts/admin.blade.php', import.meta.url), 'utf8');
const signIn = readFileSync(new URL('../resources/views/admin/auth/login.blade.php', import.meta.url), 'utf8');
const sharedInitialization = readFileSync(new URL('../resources/views/components/admin/appearance-init.blade.php', import.meta.url), 'utf8');
const initialization = sharedInitialization.match(/<script data-admin-appearance-init>([\s\S]*?)<\/script>/)[1];

test('dashboard and standalone sign-in restore the same preference before loading styles', () => {
    for (const template of [layout, signIn]) {
        assert.ok(template.includes("@include('components.admin.appearance-init')"));
        assert.ok(template.indexOf("@include('components.admin.appearance-init')") < template.indexOf('@vite'));
        assert.ok(template.includes('data-admin-appearance="light"'));
    }
});

function restore(storage) {
    const root = { dataset: { adminAppearance: 'light' } };
    const context = { document: { documentElement: root } };
    Object.defineProperty(context, 'localStorage', { get: storage });
    runInNewContext(initialization, context);
    return root.dataset.adminAppearance;
}

function documentFixture(appearance = 'light', isAdmin = true) {
    const listeners = new Map();
    const root = { dataset: { adminAppearance: appearance }, hasAttribute: () => isAdmin };
    const select = {
        value: '', dataset: {}, form: null,
        addEventListener(name, listener) { listeners.set(name, [...(listeners.get(name) || []), listener]); },
    };
    const form = { elements: [
        { name: 'title', type: 'text', value: 'Unsaved lesson notes' },
        { name: 'action', type: 'submit', value: 'draft' },
        { name: 'student_ids[]', type: 'hidden', value: '9', dataset: { trackUnsaved: '1' } },
    ] };
    const state = { focus: select, scrollY: 580, disclosures: ['home-introduction'], selected: ['9'], dirty: true };
    const doc = { documentElement: root, querySelector: () => select };
    const change = value => { select.value = value; for (const listener of listeners.get('change') || []) listener({ target: select }); };
    return { doc, root, select, listeners, form, state, change };
}

test('first visit uses Light before the shell paints', () => {
    assert.equal(restore(() => ({ getItem: () => null })), 'light');
});

for (const appearance of ['light', 'dark-teal', 'pure-black']) {
    test(`restores ${appearance} from the admin-specific browser preference`, () => {
        const keys = [];
        assert.equal(restore(() => ({ getItem(key) { keys.push(key); return appearance; } })), appearance);
        assert.deepEqual(keys, ['awa.admin.appearance.v1']);
    });
}

for (const invalid of ['dark', 'Light', 'system', '', '<script>', null, undefined]) {
    test(`unrecognized preference ${String(invalid)} falls back to Light`, () => {
        assert.equal(restore(() => ({ getItem: () => invalid })), 'light');
        assert.equal(normalizeAdminAppearance(invalid), 'light');
    });
}

test('storage getter and read exceptions fall back without escaping initialization', () => {
    assert.equal(restore(() => { throw new Error('storage blocked'); }), 'light');
    assert.equal(restore(() => ({ getItem() { throw new Error('read blocked'); } })), 'light');
});

test('switches immediately, persists only its key, and restores on a subsequent document', () => {
    const writes = new Map();
    const fixture = documentFixture();
    initializeAdminAppearance(fixture.doc, () => ({ setItem: (key, value) => writes.set(key, value) }));

    fixture.change('pure-black');

    assert.equal(fixture.root.dataset.adminAppearance, 'pure-black');
    assert.deepEqual([...writes], [['awa.admin.appearance.v1', 'pure-black']]);
    assert.equal(restore(() => ({ getItem: key => writes.get(key) })), 'pure-black');
});

test('a write exception retains the selected appearance for the current document', () => {
    const fixture = documentFixture();
    initializeAdminAppearance(fixture.doc, () => ({ setItem() { throw new Error('quota'); } }));

    assert.doesNotThrow(() => fixture.change('dark-teal'));
    assert.equal(fixture.root.dataset.adminAppearance, 'dark-teal');
    assert.equal(fixture.select.value, 'dark-teal');
});

test('a storage access exception also retains the current document selection', () => {
    const fixture = documentFixture();
    initializeAdminAppearance(fixture.doc, () => { throw new Error('storage access denied'); });

    fixture.change('pure-black');

    assert.equal(fixture.root.dataset.adminAppearance, 'pure-black');
});

test('appearance changes preserve operational form values and existing unsaved tracking state', () => {
    const fixture = documentFixture();
    const initialFields = editableFormState(fixture.form);
    const initialState = { ...fixture.state, disclosures: [...fixture.state.disclosures], selected: [...fixture.state.selected] };
    initializeAdminAppearance(fixture.doc, () => ({ setItem() {} }));

    fixture.change('dark-teal');

    assert.equal(editableFormState(fixture.form), initialFields);
    assert.equal(fixture.state.scrollY, initialState.scrollY);
    assert.deepEqual(fixture.state.disclosures, initialState.disclosures);
    assert.deepEqual(fixture.state.selected, initialState.selected);
    assert.equal(fixture.state.dirty, true);
    assert.equal(fixture.state.focus, fixture.select);
    assert.equal(fixture.select.form, null);
});

test('initialization is idempotent and ignores non-admin documents', () => {
    const fixture = documentFixture();
    const writes = [];
    const storage = () => ({ setItem: (...args) => writes.push(args) });
    initializeAdminAppearance(fixture.doc, storage);
    initializeAdminAppearance(fixture.doc, storage);
    fixture.change('dark-teal');
    assert.equal(writes.length, 1);

    const publicDocument = documentFixture('light', false);
    initializeAdminAppearance(publicDocument.doc, storage);
    assert.equal(publicDocument.listeners.size, 0);
});

test('the appearance control remains outside the existing time-preference form', () => {
    const start = layout.indexOf('<div class="aq-appearance">');
    const preceding = layout.slice(0, start);
    assert(preceding.lastIndexOf('</form>') > preceding.lastIndexOf('<form'));
    const select = layout.match(/<select id="admin-appearance-select"[^>]*>/)[0];
    assert(!select.includes('name='));
    assert(!select.includes('form='));
});


test('preview appearance changes stay inside the administrative controls', () => {
    const fixture = documentFixture();
    const publicRoot = { dataset: {}, hasAttribute: () => false };
    fixture.root.hasAttribute = name => name === 'data-admin-appearance-scope';
    fixture.doc.documentElement = publicRoot;
    fixture.doc.querySelector = selector => selector === '[data-admin-appearance-scope]' ? fixture.root : fixture.select;
    initializeAdminAppearance(fixture.doc, () => ({ setItem() {} }));

    fixture.change('pure-black');

    assert.equal(fixture.root.dataset.adminAppearance, 'pure-black');
    assert.equal(publicRoot.dataset.adminAppearance, undefined);
    assert.equal(fixture.select.value, 'pure-black');
});
