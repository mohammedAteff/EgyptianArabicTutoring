import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../resources/js/analytics-telemetry.js', import.meta.url), 'utf8');
function harness(contact = null) {
    const batches = [], handlers = {}, timers = [];
    let now = 0;
    const tall = {id: 'resource-preview', dataset: {}, getBoundingClientRect: () => ({top: 0, bottom: 3000, height: 3000, left: 0, right: 800, width: 800})};
    const gate = {dataset: {analyticsEvent: 'resource_gate_viewed', analyticsMetadata: '{"resource_slug":"guide"}'}};
    const document = {readyState: 'complete', visibilityState: 'visible', querySelector: (selector) => selector.includes('analytics-event-url') ? {content: '/analytics/track'} : null,
        querySelectorAll: (selector) => selector === '[data-whatsapp-cta]' ? (contact ? [contact] : []) : selector === '[data-analytics-event]' ? [gate] : selector.includes('#hero') ? [tall] : [],
        addEventListener: (name, callback) => handlers[name] = callback};
    const context = vm.createContext({document, window: {location: {pathname: '/resources/guide', href: 'http://app.test/resources/guide'}, addEventListener: (name, callback) => handlers[name] = callback},
        performance: {now: () => now}, innerHeight: 600, innerWidth: 800, crypto: {}, navigator: {sendBeacon: () => false}, Blob,
        fetch: async (url, options) => {batches.push(JSON.parse(options.body)); return {ok: true};}, setInterval: (callback) => timers.push(callback)});
    vm.runInContext(source, context);
    return {context, document, handlers, timers, setTime: value => now = value, events: () => batches.flatMap(batch => batch.events)};
}
test('gate payload is allow-listed, HTTP UUID fallback works, and initialization is idempotent', () => {
    const h = harness(); vm.runInContext(source, h.context);
    const gates = h.events().filter(event => event.event_name === 'resource_gate_viewed');
    assert.equal(gates.length, 1); assert.deepEqual(gates[0].metadata, {resource_slug: 'guide'});
    assert.match(gates[0].event_uuid, /^[0-9a-f-]{36}$/);
    assert.equal(h.events().filter(event => event.event_name === 'section_view').length, 1);
});
test('tall section accrues foreground dwell and hidden time is excluded', async () => {
    const h = harness(); await Promise.resolve(); h.setTime(5000); h.document.visibilityState = 'hidden'; h.handlers.visibilitychange(); await Promise.resolve();
    h.setTime(65000); h.timers[0](); await Promise.resolve();
    h.document.visibilityState = 'visible'; h.handlers.visibilitychange(); await Promise.resolve();
    h.setTime(70000); h.handlers.pagehide(); await Promise.resolve();
    const seconds = h.events().filter(event => event.event_name === 'section_dwell').reduce((total, event) => total + event.metadata.dwell_seconds, 0);
    assert.equal(seconds, 10);
});

test('floating WhatsApp click has its own placement and only one listener after repeated initialization', async () => {
    const clicks = [];
    const contact = {href: 'https://wa.me/201022222222', dataset: {context: 'portal', language: 'fr'}, addEventListener: (name, handler) => clicks.push(handler)};
    const h = harness(contact);
    vm.runInContext(source, h.context);
    await Promise.resolve();
    assert.equal(clicks.length, 1);
    clicks[0]();
    await Promise.resolve();
    const events = h.events().filter(event => event.event_name === 'whatsapp_clicked');
    assert.equal(events.length, 1);
    assert.deepEqual(events[0].metadata, {target_url: contact.href, platform: 'whatsapp', placement: 'floating_cta', context: 'portal', language: 'fr'});
});
