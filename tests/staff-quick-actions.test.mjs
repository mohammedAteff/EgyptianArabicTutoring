import test from 'node:test';
import assert from 'node:assert/strict';
import { filterQuickDestinations } from '../resources/js/staff-quick-actions.js';

test('quick destinations filter visible labels without markup or query interpretation', () => {
    const destinations = [{textContent: 'Today & Operations'}, {textContent: 'Staff Notes'}, {textContent: 'Cashier Hub'}];
    filterQuickDestinations('  NOTES ', destinations);
    assert.deepEqual(destinations.map(destination => destination.hidden), [true, false, true]);
    filterQuickDestinations('<script>', destinations);
    assert.ok(destinations.every(destination => destination.hidden));
    filterQuickDestinations('', destinations);
    assert.ok(destinations.every(destination => !destination.hidden));
});
