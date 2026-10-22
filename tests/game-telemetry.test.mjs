import test from 'node:test';
import assert from 'node:assert/strict';
import {createGameTelemetry} from '../resources/js/game-telemetry.js';

function harness(failFirst = false) {
    let time = 0, number = 0;
    const calls = [], handlers = {};
    const page = {hidden: false, addEventListener: (name, handler) => handlers[name] = handler, removeEventListener() {}, defaultView: {addEventListener: (name, handler) => handlers[name] = handler, removeEventListener() {}}};
    const tracker = createGameTelemetry({url: '/games/test/track', csrf: 'csrf', document: page, clock: () => time, uuid: () => `attempt-${++number}`, fetch: async (url, options) => {
        calls.push(JSON.parse(options.body));
        if (failFirst && calls.length === 1) throw new Error('offline');
        return {ok: true, status: 200};
    }});
    return {tracker, calls, handlers, page, at: value => time = value};
}

test('one attempt links start and completion, excludes hidden and unfocused time, and ignores double completion', async () => {
    const h = harness();
    h.tracker.start(); await h.tracker.flush();
    h.at(5000); h.page.hidden = true; h.handlers.visibilitychange();
    h.at(65000); h.page.hidden = false; h.handlers.visibilitychange();
    h.at(70000); h.handlers.blur();
    h.at(100000); h.handlers.focus();
    h.at(105000); h.tracker.complete({score: 4, total: 5}); h.tracker.complete({score: 4, total: 5});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.calls.length, 2);
    assert.equal(h.calls[0].metadata.attempt_id, h.calls[1].metadata.attempt_id);
    assert.equal(h.calls[1].metadata.duration_seconds, 15);
    h.tracker.start(); await new Promise(resolve => setImmediate(resolve));
    assert.notEqual(h.calls[2].metadata.attempt_id, h.calls[0].metadata.attempt_id);
});

test('retry retains the same attempt and sends start before completion', async () => {
    const h = harness(true);
    h.tracker.start(); await new Promise(resolve => setImmediate(resolve));
    h.tracker.complete({score: 1, total: 5}); await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(h.calls.map(call => call.action), ['game_started', 'game_started', 'game_completed']);
    assert.deepEqual(h.calls[0], h.calls[1]);
});
