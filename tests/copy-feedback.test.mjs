import test from 'node:test';
import assert from 'node:assert/strict';
import {createCopyFeedback} from '../resources/js/clipboard.js';
import {initializeQuickNoteCopy} from '../resources/js/quick-notes.js';

function fakeTimers() {
    const originalSet = globalThis.setTimeout, originalClear = globalThis.clearTimeout;
    const pending = new Map();
    let next = 0;
    globalThis.setTimeout = (callback, duration) => { pending.set(++next, {callback, duration}); return next; };
    globalThis.clearTimeout = id => pending.delete(id);
    return {pending, restore() { globalThis.setTimeout = originalSet; globalThis.clearTimeout = originalClear; }};
}

test('copy confirmation waits for success, restarts on repeated clicks and expires automatically', async () => {
    const timers = fakeTimers();
    const changes = [], writes = [];
    let resolve;
    const feedback = createCopyFeedback(value => changes.push(value), {write: value => { writes.push(value); return new Promise(done => { resolve = done; }); }});
    try {
        const copying = feedback.copy('شغّل المروحة', 2);
        assert.deepEqual(changes, [null]);
        assert.equal(timers.pending.size, 0);

        resolve();
        assert.equal(await copying, true);

        assert.deepEqual(writes, ['شغّل المروحة']);
        assert.equal(changes.at(-1), 2);
        const second = feedback.copy('شغّل المروحة', 2);
        assert.equal(timers.pending.size, 0);
        resolve();
        await second;
        assert.equal(timers.pending.size, 1);
        const timer = [...timers.pending.values()][0];
        assert.equal(timer.duration, 2400);
        timer.callback();
        assert.equal(changes.at(-1), null);
    } finally { feedback.destroy(); timers.restore(); }
});

test('failed, superseded and dismissed copies never show a misleading success state', async () => {
    const timers = fakeTimers();
    const changes = [], pending = [];
    const feedback = createCopyFeedback(value => changes.push(value), {write: () => new Promise((resolve, reject) => pending.push({resolve, reject}))});
    try {
        const older = feedback.copy('older', 0);
        const newer = feedback.copy('newer', 'all');
        pending[1].resolve();
        await newer;
        pending[0].reject(new Error('Old request failed'));
        assert.equal(await older, false);
        assert.equal(changes.at(-1), 'all');

        const failed = feedback.copy('failed', 1);
        pending[2].reject(new Error('Copy blocked'));
        await assert.rejects(failed, /Copy blocked/);
        assert.equal(changes.at(-1), null);
        assert.equal(timers.pending.size, 0);

        const dismissed = feedback.copy('closing', 3);
        feedback.reset();
        pending[3].resolve();
        assert.equal(await dismissed, false);
        assert.equal(changes.at(-1), null);

        const unmounted = feedback.copy('leaving', 4);
        feedback.destroy();
        const count = changes.length;
        pending[4].resolve();
        assert.equal(await unmounted, false);
        assert.equal(changes.length, count);
    } finally { feedback.destroy(); timers.restore(); }
});

test('floating note copy has independent confirmation, preserves text and handles empty notes and failures', async () => {
    const timers = fakeTimers();
    const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const originalDocument = Object.getOwnPropertyDescriptor(globalThis, 'document');
    const buttonEvents = {}, bodyEvents = {};
    const label = {textContent: ''};
    const button = {dataset: {}, querySelector: () => label, addEventListener(name, handler) { buttonEvents[name] = handler; }};
    const body = {value: '\nأَهْلًا — Guten Tag!\n', addEventListener(name, handler) { bodyEvents[name] = handler; }, focus() { this.focused = true; }, select() { this.selected = true; }};
    const status = {textContent: 'Saving...'};
    const panel = {querySelector: selector => ({'[data-quick-copy]': button, '[data-quick-body]': body, '[data-quick-status]': status})[selector]};
    let copied, feedback;
    try {
        Object.defineProperty(globalThis, 'navigator', {configurable: true, value: {clipboard: {async writeText(value) { copied = value; }}}});
        feedback = initializeQuickNoteCopy(panel);

        await buttonEvents.click();

        assert.equal(copied, '\nأَهْلًا — Guten Tag!\n');
        assert.equal(button.dataset.copied, 'true');
        assert.equal(label.textContent, 'Copied');
        assert.equal(status.textContent, 'Saving...');
        status.textContent = 'Saved';
        assert.equal(button.dataset.copied, 'true');
        [...timers.pending.values()][0].callback();
        assert.equal(label.textContent, 'Copy note text');
        assert.equal(status.textContent, 'Saved');

        body.value = '';
        bodyEvents.input();
        assert.equal(button.disabled, true);
        copied = null;
        await buttonEvents.click();
        assert.equal(copied, null);
        assert.equal(button.dataset.copied, 'false');

        body.value = 'Unsaved text';
        bodyEvents.input();
        assert.equal(button.disabled, false);
        navigator.clipboard.writeText = async () => { throw new Error('Denied'); };
        const textarea = {style: {}, focus() {}, select() {}, remove() {}};
        Object.defineProperty(globalThis, 'document', {configurable: true, value: {activeElement: null, querySelector: () => null, createElement: () => textarea, body: {append() {}}, execCommand: () => false}});

        await buttonEvents.click();

        assert.equal(button.dataset.copied, 'false');
        assert.equal(label.textContent, 'Copy note text');
        assert.equal(body.focused, true);
        assert.equal(body.selected, true);
        assert.equal(status.textContent, 'Select and copy your text using your device.');
    } finally {
        feedback?.destroy(); timers.restore();
        for (const [name, descriptor] of [['navigator', originalNavigator], ['document', originalDocument]]) {
            if (descriptor) Object.defineProperty(globalThis, name, descriptor); else delete globalThis[name];
        }
    }
});
