import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../resources/js/analytics-telemetry.js', import.meta.url), 'utf8');
function harness(contact = null, socials = [], failures = {}) {
    const batches = [], handlers = {}, timers = [];
    let now = 0;
    const tall = {id: 'resource-preview', dataset: {}, getBoundingClientRect: () => ({top: 0, bottom: 3000, height: 3000, left: 0, right: 800, width: 800})};
    const gate = {dataset: {analyticsEvent: 'resource_gate_viewed', analyticsMetadata: '{"resource_slug":"guide"}'}};
    const document = {readyState: 'complete', visibilityState: 'visible', querySelector: (selector) => selector.includes('analytics-event-url') ? {content: '/analytics/track'} : null,
        querySelectorAll: (selector) => selector === '[data-social-platform]' ? socials : selector === '[data-whatsapp-cta]' ? (contact ? [contact] : []) : selector === '[data-analytics-event]' ? [gate] : selector.includes('#hero') ? [tall] : [],
        addEventListener: (name, callback) => handlers[name] = callback};
    const context = vm.createContext({document, window: {location: {pathname: '/resources/guide', href: 'http://app.test/resources/guide'}, addEventListener: (name, callback) => handlers[name] = callback},
        performance: {now: () => now}, innerHeight: 600, innerWidth: 800, crypto: failures.crypto, navigator: {sendBeacon: () => { if (failures.beacon) throw new Error('Beacon failed'); return false; }}, Blob,
        fetch: async (url, options) => {batches.push(JSON.parse(options.body)); if (failures.fetch) throw new Error('Network failed'); return {ok: !failures.endpoint, status: failures.endpoint ? 503 : 200};}, setInterval: (callback) => timers.push(callback)});
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

test('all configured footer platforms emit exactly one event with authoritative placement and metadata', async () => {
    const clickHandlers = [];
    const socials = ['youtube','tiktok','instagram','telegram','whatsapp','reddit'].map(platform => ({dataset: {socialPlatform: platform}, href: `https://example.org/${platform}`, addEventListener: (name, handler) => clickHandlers.push({platform, handler})}));
    const h = harness(null, socials);
    vm.runInContext(source, h.context);
    await Promise.resolve();
    assert.equal(clickHandlers.length, 6);
    for (const {handler} of clickHandlers) { handler(); await Promise.resolve(); }
    const events = h.events().filter(event => ['social_link_clicked','telegram_clicked','whatsapp_clicked'].includes(event.event_name));
    assert.equal(events.length, 6);
    for (const event of events) { assert.equal(event.metadata.placement, 'footer_social'); assert.equal(event.metadata.context, 'public'); assert.ok(['youtube','tiktok','instagram','telegram','whatsapp','reddit'].includes(event.metadata.platform)); }
    assert.equal(events.filter(event => event.metadata.platform === 'reddit')[0].event_name, 'social_link_clicked');
});

for (const [label, failures] of Object.entries({endpoint: {endpoint: true}, beacon: {beacon: true}, fetch: {fetch: true}, javascript: {crypto: {randomUUID() { throw new Error('Crypto unavailable'); }}}})) {
    test(`${label} failure keeps footer and floating links native and retries reuse event UUIDs`, async () => {
        const clicks = [];
        const makeLink = (dataset) => ({href: 'https://wa.me/201022222222', dataset, addEventListener: (name, handler) => clicks.push(handler)});
        const contact = makeLink({context: 'public', language: 'en'});
        const footer = makeLink({socialPlatform: 'whatsapp'});
        const h = harness(contact, [footer], failures);
        await Promise.resolve(); await Promise.resolve();
        let prevented = false;
        for (const click of clicks) {
            assert.doesNotThrow(() => click({preventDefault() { prevented = true; }}));
            await Promise.resolve(); await Promise.resolve();
        }
        assert.equal(prevented, false);
        assert.equal(contact.href, 'https://wa.me/201022222222');
        const uniqueEvents = [...new Map(h.events().filter(event => event.event_name === 'whatsapp_clicked').map(event => [event.event_uuid, event])).values()];
        assert.equal(uniqueEvents.length, 2);
        assert.deepEqual(uniqueEvents.map(event => event.metadata.placement).sort(), ['floating_cta', 'footer_social']);
    });
}
