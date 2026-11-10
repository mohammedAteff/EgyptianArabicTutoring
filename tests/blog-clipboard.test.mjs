import test from 'node:test';
import assert from 'node:assert/strict';

test('article copy announces success only after copying its URL, and reports total failure honestly', async () => {
    const originals = Object.fromEntries(['navigator', 'window', 'document', 'setTimeout'].map(name => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));
    const status = { textContent: '' };
    const url = 'http://arabicwabdallah.test/blog/arabic-guide';
    const button = { dataset: { copyText: url, copySuccess: 'Link copied.', copyFailure: 'Copy unavailable' }, hasAttribute: name => name === 'data-copy-text', querySelector: () => status };
    let listener, copiedValue, fallbackAllowed = true, removed = 0, restored = 0;
    const textarea = { style: {}, focus() {}, select() {}, remove() { removed++; } };
    try {
        Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard: { async writeText(value) { copiedValue = value; } } } });
        Object.defineProperty(globalThis, 'window', { configurable: true, value: { isSecureContext: true } });
        Object.defineProperty(globalThis, 'setTimeout', { configurable: true, value: () => 0 });
        Object.defineProperty(globalThis, 'document', { configurable: true, value: {
            addEventListener(event, handler) { assert.equal(event, 'click'); listener = handler; },
            activeElement: { focus() { restored++; } },
            querySelector(selector) { return selector === 'dialog[open]' ? { append() {} } : null; },
            createElement() { return textarea; },
            execCommand() { copiedValue = textarea.value; return fallbackAllowed; },
        } });
        await import('../resources/js/clipboard.js?article-feedback');
        await listener({ target: { closest: () => button } });
        assert.equal(copiedValue, url);
        assert.equal(status.textContent, 'Link copied.');
        const arabic = 'أَهْلًا وَسَهْلًا — القاهرة، ١٢٣ (2026)!';
        button.dataset.copyText = arabic;
        await listener({ target: { closest: () => button } });
        assert.equal(copiedValue, arabic);
        button.dataset.copyText = url;
        navigator.clipboard.writeText = async () => { throw new Error('Denied'); };
        status.textContent = '';
        await listener({ target: { closest: () => button } });
        assert.equal(copiedValue, url);
        assert.equal(status.textContent, 'Link copied.');
        fallbackAllowed = false;
        status.textContent = '';
        await listener({ target: { closest: () => button } });
        assert.equal(status.textContent, 'Copy unavailable');
        assert.equal(removed, 2);
        assert.equal(restored, 2);
    } finally {
        for (const [name, descriptor] of Object.entries(originals)) {
            if (descriptor) Object.defineProperty(globalThis, name, descriptor); else delete globalThis[name];
        }
    }
});
