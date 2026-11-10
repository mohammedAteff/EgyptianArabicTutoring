import test from 'node:test';
import assert from 'node:assert/strict';
import {copyText} from '../resources/js/clipboard.js';
import {numberedWords} from '../resources/js/games/six-word-story/engine.js';

test('game copying preserves numbered rounds and exact single words through the local HTTP clipboard bridge', async () => {
    const originals = Object.fromEntries(['navigator', 'window'].map(name => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));
    let clipboard;
    try {
        Object.defineProperty(globalThis, 'navigator', {configurable: true, value: {clipboard: {async writeText(value) { clipboard = value; }}}});
        Object.defineProperty(globalThis, 'window', {configurable: true, value: {isSecureContext: false}});

        await copyText(numberedWords(['one', 'two', 'three', 'four', 'five', 'six']));

        assert.equal(clipboard, '1. one\n2. two\n3. three\n4. four\n5. five\n6. six');

        await copyText('شغّل المروحة');

        assert.equal(clipboard, 'شغّل المروحة');
    } finally {
        for (const [name, descriptor] of Object.entries(originals)) {
            if (descriptor) Object.defineProperty(globalThis, name, descriptor); else delete globalThis[name];
        }
    }
});

test('game copying retains six numbered words in order when the secure clipboard API is unavailable', async () => {
    const originals = Object.fromEntries(['navigator', 'window', 'document'].map(name => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));
    const words = ['way out', 'forget', 'potting soil', 'cartographer', 'substitution', 'deckchair'];
    const arabic = ['عيش', 'ميّه', 'شباك', 'قهوة', 'حكاية', 'بيت'];
    let clipboard, removed = 0, restored = 0, fallbackAllowed = true;
    const textarea = {style: {}, focus() {}, select() {}, remove() { removed++; }};
    try {
        Object.defineProperty(globalThis, 'navigator', {configurable: true, value: {}});
        Object.defineProperty(globalThis, 'window', {configurable: true, value: {isSecureContext: false}});
        Object.defineProperty(globalThis, 'document', {configurable: true, value: {
            activeElement: {focus() { restored++; }},
            querySelector() { return null; },
            body: {append() {}},
            createElement() { return textarea; },
            execCommand(command) { assert.equal(command, 'copy'); clipboard = textarea.value; return fallbackAllowed; },
        }});

        await copyText(numberedWords(words));

        assert.equal(clipboard, '1. way out\n2. forget\n3. potting soil\n4. cartographer\n5. substitution\n6. deckchair');
        assert.deepEqual(words, ['way out', 'forget', 'potting soil', 'cartographer', 'substitution', 'deckchair']);

        await copyText(numberedWords(arabic));

        assert.equal(clipboard, '1. عيش\n2. ميّه\n3. شباك\n4. قهوة\n5. حكاية\n6. بيت');
        fallbackAllowed = false;
        await assert.rejects(copyText(numberedWords(words)), /Clipboard unavailable/);
        assert.equal(removed, 3);
        assert.equal(restored, 3);
    } finally {
        for (const [name, descriptor] of Object.entries(originals)) {
            if (descriptor) Object.defineProperty(globalThis, name, descriptor); else delete globalThis[name];
        }
    }
});
