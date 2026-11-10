import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeEstimatedActivity } from '../resources/js/estimated-activity.js';

function fixture({ enabled = true, second = false, grace = 120000, crypto = { randomUUID: () => 'fixture-stream' } } = {}) {
    let clock = 0, identifier = 0, focused = true, fail = false;
    const timers = new Map(), requests = [], handlers = new Map(), windowHandlers = new Map();
    class Element {
        constructor(root = 'lesson', excluded = false) { this.root = root; this.excluded = excluded; this.isConnected = true; }
        contains(target) { return target.root === this.root; }
        closest() { return this.excluded ? this : null; }
    }
    const root = new Element(), other = new Element('other'), outside = new Element('navigation'), excluded = new Element('lesson', true);
    const config = root => ({ root, kind: 'student_lesson', record: 1, anchor: '/anchor', checkpoint: '/checkpoint', grace, window: 15000, gap: 20000 });
    const scripts = enabled ? [{ textContent: JSON.stringify(config('lesson')) }, ...(second ? [{ textContent: JSON.stringify(config('other')) }] : [])] : [];
    const media = { paused: true, ended: false };
    const doc = {
        visibilityState: 'visible', hasFocus: () => focused,
        querySelectorAll: selector => selector === 'script[data-estimated-activity]' ? scripts : [media],
        querySelector: () => ({ content: 'synthetic-csrf' }),
        getElementById: id => id === 'lesson' ? root : other,
        addEventListener: (name, action) => handlers.set(name, action),
    };
    const win = {
        Element, AbortController, crypto, performance: { now: () => clock },
        setTimeout(action, delay) { const id = ++identifier; timers.set(id, { action, time: clock + delay }); return id; },
        clearTimeout: id => timers.delete(id),
        addEventListener: (name, action) => windowHandlers.set(name, action),
        async fetch(url, options) {
            const body = JSON.parse(options.body);
            requests.push({ url, body });
            if (fail) throw new Error('Offline');
            return { ok: true, json: async () => url === '/anchor' ? { permit: 'opaque', sequence: 0 } : { sequence: body.sequence, seconds: 15 } };
        },
    };
    const settle = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };
    initializeEstimatedActivity(doc, win);
    return {
        doc, root, other, outside, excluded, media, requests, timers, handlers, windowHandlers,
        async event(type = 'pointerdown', target = root, trusted = true) { await handlers.get(type)?.({ target, isTrusted: trusted }); await settle(); },
        async advance(ms) {
            clock += ms;
            for (const [id, timer] of [...timers]) if (timer.time <= clock) { timers.delete(id); await timer.action(); }
            await settle();
        },
        async hide() { doc.visibilityState = 'hidden'; handlers.get('visibilitychange')(); await settle(); },
        async blur() { focused = false; windowHandlers.get('blur')(); await settle(); },
        show() { focused = true; doc.visibilityState = 'visible'; },
        fail(value = true) { fail = value; },
    };
}

test('disabled collection initializes no listeners, sampling timers or requests', () => {
    const tab = fixture({ enabled: false });
    assert.equal(tab.handlers.size, 0);
    assert.equal(tab.windowHandlers.size, 0);
    assert.equal(tab.timers.size, 0);
    assert.equal(tab.requests.length, 0);
});

test('page load, script-dispatched input, mouse movement and generic scroll earn no observation', async () => {
    const tab = fixture();
    await tab.event('pointerdown', tab.root, false);
    await tab.event('mousemove');
    await tab.event('scroll');
    await tab.advance(15000);
    assert.equal(tab.requests.length, 0);
    assert.equal(tab.timers.size, 0);
});

test('local HTTP origins use secure random bytes when randomUUID is unavailable', async () => {
    const tab = fixture({ crypto: { getRandomValues: bytes => bytes.fill(17) } });
    await tab.event();
    assert.match(tab.requests[0].body.stream, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    await tab.advance(15000);
    assert.equal(tab.requests.at(-1).body.sequence, 1);
});

test('missing secure randomness leaves the collector disabled without affecting the page', async () => {
    const tab = fixture({ crypto: {} });
    assert.equal(tab.handlers.size, 0);
    assert.equal(tab.timers.size, 0);
    await tab.event();
    assert.equal(tab.requests.length, 0);
});

test('trusted content input anchors zero then sends one bounded window with no event history', async () => {
    const tab = fixture();
    await tab.event();
    assert.deepEqual(tab.requests.map(item => item.url), ['/anchor']);
    await tab.advance(15000);
    assert.deepEqual(tab.requests.map(item => item.url), ['/anchor', '/checkpoint']);
    assert.deepEqual(Object.keys(tab.requests[1].body).sort(), ['permit', 'sequence', 'window_ms']);
    assert.equal(tab.requests[1].body.window_ms, 15000);
    for (let event = 0; event < 50; event++) await tab.event('input');
    assert.equal(tab.requests.length, 2);
});

for (const interruption of ['hide', 'blur', 'context', 'excluded', 'navigation', 'freeze', 'media']) {
    test(interruption + ' discards a partial window and requires a fresh eligible interaction', async () => {
        const tab = fixture({ second: true });
        await tab.event();
        await tab.advance(5000);
        if (interruption === 'hide') await tab.hide();
        if (interruption === 'blur') await tab.blur();
        if (interruption === 'context') await tab.event('pointerdown', tab.other);
        if (interruption === 'excluded') await tab.event('pointerdown', tab.excluded);
        if (interruption === 'navigation') await tab.event('pointerdown', tab.outside);
        if (interruption === 'freeze') tab.handlers.get('freeze')();
        if (interruption === 'media') { tab.media.paused = false; tab.handlers.get('play')(); }
        tab.show();
        await tab.advance(10000);
        assert.equal(tab.requests.filter(item => item.url === '/checkpoint').length, 0);
        tab.media.paused = true;
        await tab.event();
        await tab.advance(15000);
        assert.equal(tab.requests.filter(item => item.url === '/checkpoint').length, 1);
    });
}

test('grace expiry and delayed/suspended callbacks discard incomplete observation', async () => {
    const grace = fixture({ grace: 30000 });
    await grace.event();
    await grace.advance(15000);
    await grace.advance(15000);
    assert.equal(grace.requests.filter(item => item.url === '/checkpoint').length, 1);
    const suspended = fixture();
    await suspended.event();
    await suspended.advance(21000);
    assert.equal(suspended.requests.length, 1);
    assert.equal(suspended.timers.size, 0);
});

test('network failure loses the pending window without retries, catch-up or blocking new input', async () => {
    const tab = fixture();
    await tab.event();
    tab.fail();
    await tab.advance(15000);
    assert.equal(tab.requests.length, 2);
    for (let event = 0; event < 20; event++) await tab.event('input');
    assert.equal(tab.requests.length, 2);
    await tab.advance(60000);
    assert.equal(tab.requests.length, 2);
    tab.fail(false);
    await tab.event('input');
    assert.equal(tab.requests.at(-1).url, '/anchor');
    await tab.advance(15000);
    assert.equal(tab.requests.at(-1).body.sequence, 1);
});
