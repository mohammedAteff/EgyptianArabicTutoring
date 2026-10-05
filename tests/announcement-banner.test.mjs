import test from 'node:test';
import assert from 'node:assert/strict';
import { announcementBanner } from '../resources/js/announcement-banner.js';

test('dismissal persists only for the same announcement version', () => {
    const values = new Map();
    const storage = () => ({ getItem: key => values.get(key), setItem: (key, value) => values.set(key, value) });
    const first = announcementBanner('first', storage); first.init(); assert.equal(first.dismissed, false);
    first.dismiss(); assert.equal(first.dismissed, true);
    const nextPage = announcementBanner('first', storage); nextPage.init(); assert.equal(nextPage.dismissed, true);
    const revised = announcementBanner('revised', storage); revised.init(); assert.equal(revised.dismissed, false);
});
test('blocked storage does not prevent showing or dismissing a banner', () => {
    const banner = announcementBanner('version', () => { throw new Error('Blocked'); });
    banner.init(); assert.equal(banner.dismissed, false); banner.dismiss(); assert.equal(banner.dismissed, true);
});
