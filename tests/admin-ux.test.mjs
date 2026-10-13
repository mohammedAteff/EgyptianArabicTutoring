import test from 'node:test';
import assert from 'node:assert/strict';
import { editableFormState } from '../resources/js/admin-ux.js';

test('unsaved tracking ignores hidden tokens but detects editable values and checkbox changes', () => {
    const fields = [{ name: '_token', type: 'hidden', value: 'one' }, { name: 'notes', type: 'textarea', value: 'Saved' }, { name: 'shared', type: 'checkbox', checked: false }];
    const form = { elements: fields };
    const initial = editableFormState(form);
    fields[0].value = 'two';
    assert.equal(editableFormState(form), initial);
    fields[1].value = 'Changed';
    assert.notEqual(editableFormState(form), initial);
    fields[1].value = 'Saved';
    fields[2].checked = true;
    assert.notEqual(editableFormState(form), initial);
});

test('multi-select tracking detects an added selection while preserving saved state after undo', () => {
    const field = { name: 'resources', type: 'select-multiple', selectedOptions: [{ value: '4' }] };
    const form = { elements: [field] };
    const initial = editableFormState(form);
    field.selectedOptions.push({ value: '8' });
    assert.notEqual(editableFormState(form), initial);
    field.selectedOptions.pop();
    assert.equal(editableFormState(form), initial);
});

test('ordered collection selections count as unsaved changes even when carried by hidden fields', () => {
    const fields = [{ name: 'resource_ids[]', type: 'hidden', value: '4', dataset: { trackUnsaved: '1' } }, { name: 'resource_ids[]', type: 'hidden', value: '8', dataset: { trackUnsaved: '1' } }];
    const form = { elements: fields };
    const initial = editableFormState(form);
    fields.reverse();
    assert.notEqual(editableFormState(form), initial);
    fields.reverse();
    assert.equal(editableFormState(form), initial);
    fields.push({ name: 'resource_ids[]', type: 'hidden', value: '9', dataset: { trackUnsaved: '1' } });
    assert.notEqual(editableFormState(form), initial);
});

test('initial reactive rendering is saved state while subsequent collection edits warn before leaving', async () => {
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    const documentListeners = new Map();
    const windowListeners = new Map();
    const form = { elements: [{ name: 'title', type: 'text', value: 'Collection' }], isConnected: true, getAttribute: () => 'post', closest: () => form };
    globalThis.document = { readyState: 'complete', querySelector: () => true, querySelectorAll: () => [form], addEventListener: (name, callback) => documentListeners.set(name, callback) };
    globalThis.window = { addEventListener: (name, callback) => windowListeners.set(name, callback) };
    try {
        await import('../resources/js/admin-ux.js?reactive-form-test');
        form.elements.push({ name: 'resource_ids[]', type: 'hidden', value: '4', dataset: { trackUnsaved: '1' } });
        documentListeners.get('admin-form-ready')({ target: form });
        let warnings = 0;
        const event = { preventDefault: () => warnings++ };
        windowListeners.get('beforeunload')(event);
        assert.equal(warnings, 0);
        form.elements[1].value = '8';
        windowListeners.get('beforeunload')(event);
        assert.equal(warnings, 1);
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});
