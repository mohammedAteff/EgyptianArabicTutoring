import test from 'node:test';
import assert from 'node:assert/strict';
import {createGameTelemetry} from '../resources/js/game-telemetry.js';

function harness(failFirst = false, options = {}) {
    let time = 0, number = 0;
    const calls = [], handlers = {};
    const page = {hidden: false, addEventListener: (name, handler) => handlers[name] = handler, removeEventListener() {}, defaultView: {addEventListener: (name, handler) => handlers[name] = handler, removeEventListener() {}}};
    const tracker = createGameTelemetry({...options, url: '/games/test/track', csrf: 'csrf', document: page, clock: () => time, uuid: () => `attempt-${++number}`, fetch: async (url, options) => {
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

test('score-free rounds report explicit interruptions once and retain partial active time', async () => {
    const h = harness();
    h.tracker.start('easy', {mode: 'connected', practice_language: 'ar-eg', contract_version: 2});
    await h.tracker.flush();
    h.at(12000); h.tracker.interrupt({reason: 'early_finish', revealed_count: 2});
    h.tracker.complete(); h.tracker.interrupt({reason: 'exit'});
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(h.calls.map(call => call.action), ['game_started', 'game_interrupted']);
    assert.equal(h.calls[1].metadata.duration_seconds, 12);
    assert.equal(h.calls[1].metadata.reason, 'early_finish');
    assert.equal(h.calls[1].metadata.score, undefined);
    assert.equal(h.calls[0].metadata.practice_language, 'ar-eg');
    h.tracker.destroy();
});

test('preview and disabled analytics emit no gameplay events', async () => {
    const calls = [];
    const page = {hidden: false, addEventListener() {}, removeEventListener() {}, defaultView: {addEventListener() {}, removeEventListener() {}}};
    const tracker = createGameTelemetry({url: '/track', csrf: '', document: page, enabled: false, fetch: async (...args) => {calls.push(args); return {ok: true};}});
    tracker.start(); tracker.complete(); await tracker.flush(); tracker.destroy();
    assert.equal(calls.length, 0);
});

test('heartbeats retain cumulative active time and exclude the exit dialog and background', async () => {
    const h = harness(false, {heartbeat: true});
    try {
        h.tracker.start(); await new Promise(resolve => setImmediate(resolve));
        h.at(5000); h.tracker.activity(); await new Promise(resolve => setImmediate(resolve));
        h.at(8000); h.tracker.pause(); await new Promise(resolve => setImmediate(resolve));
        h.at(38000); h.tracker.resume();
        h.at(40000); h.handlers.blur(); await new Promise(resolve => setImmediate(resolve));
        h.at(70000); h.handlers.focus();
        h.at(73000); h.tracker.complete({ending: 'timed'}); await new Promise(resolve => setImmediate(resolve));
        const activity = h.calls.filter(call => call.action === 'game_activity');
        assert.deepEqual(activity.map(call => call.metadata.duration_seconds), [5, 8, 10]);
        assert.deepEqual(activity.map(call => call.metadata.sequence), [1, 2, 3]);
        assert.equal(h.calls.at(-1).metadata.duration_seconds, 13);
        assert.equal(h.calls.at(-1).metadata.score, undefined);
        assert.equal(new Set(h.calls.map(call => call.metadata.attempt_id)).size, 1);
    } finally {
        h.tracker.destroy();
    }
});
