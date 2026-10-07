import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate } from 'node:timers/promises';
import { test } from 'node:test';
import vm from 'node:vm';

const view = readFileSync(new URL('../resources/views/public/resources/show.blade.php', import.meta.url), 'utf8');
const method = view.slice(view.indexOf('submitEmail() {'), view.indexOf('submitPin() {')).trim().replace(/,$/, '');

function gate(response, rejects = false) {
    const requests = [];
    const navigations = [];
    const location = { set href(value) { navigations.push(value); } };
    const component = vm.runInNewContext(`({${method}})`, {
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (rejects) { throw new Error('Network unavailable'); }
            return response;
        },
        window: { location },
    });
    Object.assign(component, { state: 'idle', name: 'Synthetic QA', email: 'qa@example.com', downloadUrl: '',
        requestUrl: '/arabictutor/resources/qa/request', errorMessage: '' });
    return { component, requests, navigations };
}

test('Resource access preserves the single-use grant for the visible download action', async () => {
    const grantedUrl = '/arabictutor/resources/qa/download?token=synthetic-only';
    const { component, requests, navigations } = gate({ ok: true, status: 200, json: async () => ({ download_url: grantedUrl }) });
    component.submitEmail();
    await setImmediate();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(component.state, 'unlocked');
    assert.equal(component.downloadUrl, grantedUrl);
    assert.equal(navigations.length, 0, 'A background navigation must not consume the grant before the visible link is clicked');
});

test('Rejected Resource access never presents or consumes a download grant', async () => {
    for (const [status, state] of [[422, 'idle'], [429, 'rate_limited']]) {
        const { component, navigations } = gate({ ok: false, status, json: async () => ({ errors: { email: ['Invalid synthetic email'] } }) });
        component.submitEmail();
        await setImmediate();
        assert.equal(component.state, state);
        assert.equal(component.downloadUrl, '');
        assert.equal(navigations.length, 0);
        assert.ok(component.errorMessage);
    }
    const { component, navigations } = gate(null, true);
    component.submitEmail();
    await setImmediate();
    assert.equal(component.state, 'idle');
    assert.equal(component.downloadUrl, '');
    assert.equal(navigations.length, 0);
});
