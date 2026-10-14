import test from 'node:test';
import assert from 'node:assert/strict';
import { restoreNavigationGroups } from '../resources/js/admin-navigation.js';

const group = (name, active = false) => ({ dataset: { navigationGroup: name, navigationActive: String(active) }, open: active });

test('opening a different page reveals its current group even when that group was previously closed', () => {
    const groups = [group('students', true), group('finance'), group('system')];
    restoreNavigationGroups(groups, { getItem: () => '{"students":false,"finance":true,"system":false}' }, 'test');
    assert.deepEqual(groups.map(item => item.open), [true, true, false]);
});

test('closed groups stay closed and invalid stored values cannot open them', () => {
    const groups = [group('students'), group('finance'), group('system')];
    restoreNavigationGroups(groups, { getItem: () => '{"students":false,"finance":"false","system":true}' }, 'test');
    assert.deepEqual(groups.map(item => item.open), [false, false, true]);
});

for (const saved of ['broken json', 'null', '[]', '"invalid"']) {
    test(`unusable preferences preserve accessible defaults: ${saved}`, () => {
        const groups = [group('students', true), group('system')];
        restoreNavigationGroups(groups, { getItem: () => saved }, 'test');
        assert.deepEqual(groups.map(item => item.open), [true, false]);
    });
}

test('blocked browser storage does not prevent opening the current navigation group', () => {
    const groups = [group('students'), group('learning', true)];
    restoreNavigationGroups(groups, { getItem: () => { throw new Error('Storage blocked'); } }, 'test');
    assert.deepEqual(groups.map(item => item.open), [false, true]);
});
