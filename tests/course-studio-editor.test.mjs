import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const view = readFileSync(new URL('../resources/views/components/lms/rich-text-editor.blade.php', import.meta.url), 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1];
function editor(overrides = {}) {
    const textNodes = [];
    const nodes = [];
    const callbacks = {};
    const element = { innerHTML: '', contains: node => node === element, focus() {} };
    const source = { value: '<p>Safe initial text</p>', closest: () => ({ addEventListener: (name, callback) => { callbacks[name] = callback; } }) };
    const document = {
        createTextNode: text => { const node = { nodeType: 3, textContent: text }; textNodes.push(node); return node; },
        createElement: tag => { const node = { tag, textContent: '', appendChild(child) { this.textContent += child.textContent; } }; nodes.push(node); return node; },
        ...overrides.document,
    };
    const window = { getSelection: () => ({ rangeCount: 0 }), ...overrides.window };
    vm.runInNewContext(script, { window, document, URL });
    const component = window.courseRichTextEditor();
    component.$refs = { editor: element, source };
    return { component, element, source, callbacks, textNodes, nodes, window };
}
test('Rich text submits the current editor content instead of a stale initial field', () => {
    const e = editor();
    e.component.init();
    assert.equal(e.element.innerHTML, '<p>Safe initial text</p>');
    e.element.innerHTML = '<p>أهلاً updated English</p>';
    e.callbacks.submit();
    assert.equal(e.source.value, '<p>أهلاً updated English</p>');
});
test('Pasted HTML is inserted as literal Unicode text', () => {
    const e = editor(); let inserted;
    e.component.insert = node => { inserted = node; };
    e.component.paste('مرحبا <img src=x onerror=alert(1)> Hello');
    assert.equal(inserted.nodeType, 3);
    assert.equal(inserted.textContent, 'مرحبا <img src=x onerror=alert(1)> Hello');
    assert.equal(e.nodes.length, 0);
});
test('Formatting cannot create script, image or iframe elements', () => {
    const e = editor();
    for (const tag of ['script', 'img', 'iframe']) e.component.wrap(tag);
    assert.equal(e.nodes.length, 0);
    assert.equal(e.source.value, '<p>Safe initial text</p>');
});
test('Editor links reject executable URLs and embedded credentials', () => {
    for (const value of ['javascript:alert(1)', 'data:text/html,<script>x</script>', 'https://user:password@example.test']) {
        const e = editor({ window: { prompt: () => value } });
        e.component.currentRange = () => { throw new Error('Unsafe URLs must not reach the document.'); };
        e.component.link();
        assert.equal(e.nodes.length, 0);
    }
});
test('Selection outside the current editor is never reused', () => {
    const foreign = {};
    const e = editor({ window: { getSelection: () => ({ rangeCount: 1, getRangeAt: () => ({ commonAncestorContainer: foreign, cloneRange() { throw new Error('Foreign selection must be ignored.'); } }) }) } });
    e.component.remember();
    assert.equal(e.component.range, null);
});
test('Paste and formatting use the current selection before a previously remembered caret', () => {
    const e = editor();
    const freshRange = { commonAncestorContainer: e.element, cloneRange: () => freshRange };
    e.component.range = { commonAncestorContainer: e.element };
    e.window.getSelection = () => ({ rangeCount: 1, getRangeAt: () => freshRange });
    assert.equal(e.component.currentRange(), freshRange);
});
test('Valid links preserve selected lesson text', () => {
    const e = editor({ window: { prompt: () => 'https://example.test/practice' } });
    const range = { extractContents: () => ({ textContent: 'Practice · تدرب' }), insertNode: node => { assert.equal(node.href, 'https://example.test/practice'); }, selectNodeContents() {}, cloneRange: () => range };
    e.component.currentRange = () => range;
    e.component.sync = () => {};
    e.component.link();
    assert.equal(e.nodes[0].tag, 'a');
    assert.equal(e.nodes[0].textContent, 'Practice · تدرب');
});
