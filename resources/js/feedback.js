export function feedbackPayload(formData) {
    return JSON.stringify([...formData.entries()].filter(([key]) => !['_token', '_feedback_session', 'request_key'].includes(key)));
}

export function sameFeedbackActor(expected, actual) {
    return typeof expected === 'string' && expected === actual;
}

export function feedbackRequestUrl(value, locale) {
    const url = new URL(value);
    if (['en', 'fr', 'de'].includes(locale)) url.searchParams.set('lang', locale);
    return url;
}

export function newFeedbackIdentity() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
}

export function automaticallyReadFeedback(form, documentRoot, nextFrame) {
    let scheduled = false;
    const schedule = () => {
        if (scheduled || documentRoot.hidden || !form.isConnected || form.dataset.feedbackReadComplete === 'true') return;
        scheduled = true;
        nextFrame(() => nextFrame(() => {
            scheduled = false;
            if (!documentRoot.hidden && form.isConnected && form.dataset.feedbackReadComplete !== 'true') form.requestSubmit();
        }));
    };
    documentRoot.addEventListener('visibilitychange', schedule);
    schedule();
}

function initializeFeedback() {
    document.querySelectorAll('[data-feedback-form]').forEach(form => {
        if (form.dataset.feedbackInitialized) return;
        form.dataset.feedbackInitialized = 'true';
        const status = form.querySelector('[data-feedback-status]');
        const copy = form.querySelector('[data-feedback-copy]').dataset;
        const recovery = form.querySelector('[data-feedback-recovery]');
        const newOperation = form.querySelector('[data-feedback-new-operation]');
        let uncertainPayload = null, busy = false;
        form.querySelectorAll('[data-error-for]').forEach(error => {
            const field = form.elements.namedItem(error.dataset.errorFor);
            error.id = 'feedback-error-' + form.elements.namedItem('request_key').value + '-' + error.dataset.errorFor;
            field?.setAttribute('aria-describedby', error.id);
        });
        const fail = (message, recover = false) => {
            status.textContent = message;
            status.setAttribute('role', 'alert');
            recovery.hidden = !recover;
            if (!form.hasAttribute('data-feedback-auto-read')) status.focus({ preventScroll: false });
        };
        const session = async () => {
            const response = await fetch(feedbackRequestUrl(form.dataset.sessionUrl, form.dataset.feedbackLocale), { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('session');
            const data = await response.json();
            if (!sameFeedbackActor(form.dataset.actor, data.actor)) throw new Error('session');
            form.elements.namedItem('_token').value = data.csrf;
            const proof = form.elements.namedItem('_feedback_session');
            if (proof) proof.value = data.proof;
            return data;
        };
        newOperation.addEventListener('click', () => {
            form.elements.namedItem('request_key').value = newFeedbackIdentity();
            uncertainPayload = null;
            newOperation.hidden = true;
            form.requestSubmit();
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || document.hidden) return;
            const intended = feedbackPayload(new FormData(form));
            if (uncertainPayload !== null && intended !== uncertainPayload) {
                newOperation.hidden = false;
                fail(copy.uncertain, true);
                return;
            }
            busy = true;
            const controls = [...form.elements];
            const disabled = controls.map(control => control.disabled);
            const unlock = () => controls.forEach((control, index) => { control.disabled = disabled[index]; });
            form.querySelectorAll('[data-error-for]').forEach(element => { element.textContent = ''; });
            form.querySelectorAll('[aria-invalid]').forEach(element => element.removeAttribute('aria-invalid'));
            status.setAttribute('role', 'status');
            status.textContent = copy.saving;
            try {
                const authenticated = await session();
                const body = new FormData(form);
                controls.forEach(control => { control.disabled = true; });
                const response = await fetch(feedbackRequestUrl(form.getAttribute('action'), form.dataset.feedbackLocale), {
                    method: 'POST', credentials: 'same-origin', body,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': authenticated.csrf, ...(authenticated.proof ? { 'X-Feedback-Session': authenticated.proof } : {}) },
                });
                if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                    uncertainPayload = intended;
                    throw new Error('session');
                }
                const data = await response.json();
                unlock();
                if (!response.ok) {
                    if (response.status >= 500) uncertainPayload = intended;
                    if ([401, 419].includes(response.status)) throw new Error('session');
                    Object.entries(data.errors || {}).forEach(([name, messages]) => {
                        const field = form.elements.namedItem(name);
                        const error = [...form.querySelectorAll('[data-error-for]')].find(element => element.dataset.errorFor === name);
                        if (field) field.setAttribute('aria-invalid', 'true');
                        if (error) error.textContent = messages.join(' ');
                    });
                    fail(data.message || copy.failed, true);
                    return;
                }
                uncertainPayload = null;
                form.dispatchEvent(new CustomEvent('admin-form-ready', { bubbles: true }));
                if (form.hasAttribute('data-feedback-ack')) {
                    form.dataset.feedbackReadComplete = 'true';
                    status.textContent = copy.acknowledged;
                    recovery.hidden = true;
                    return;
                }
                const destination = new URL(data.redirect, window.location.href);
                if (destination.origin !== window.location.origin) throw new Error('invalid response');
                const otherDraft = [...document.querySelectorAll('[data-feedback-form] textarea')].some(field => !form.contains(field) && field.value.trim());
                if (otherDraft) {
                    status.textContent = copy.saved;
                    recovery.hidden = false;
                    return;
                }
                window.location.assign(destination.href);
            } catch (error) {
                unlock();
                if (error.message !== 'session') uncertainPayload = intended;
                fail(error.message === 'session' ? copy.session : copy.failed, true);
            } finally {
                unlock();
                busy = false;
            }
        });
        if (form.hasAttribute('data-feedback-auto-read')) automaticallyReadFeedback(form, document, callback => window.requestAnimationFrame(callback));
    });
    document.querySelectorAll('[data-feedback-context]').forEach(root => {
        const form = root.closest('form');
        const type = form.elements.namedItem('context_type');
        const selected = form.elements.namedItem('context_id');
        const output = root.querySelector('[data-context-status]');
        const empty = selected.options[0].cloneNode(true);
        type.addEventListener('change', () => { selected.replaceChildren(empty.cloneNode(true)); });
        let generation = 0;
        root.querySelector('[data-context-find]').addEventListener('click', async () => {
            if (!type.value) return;
            const current = ++generation, kind = type.value;
            const url = feedbackRequestUrl(root.dataset.url, form.dataset.feedbackLocale);
            url.searchParams.set('type', kind);
            url.searchParams.set('q', root.querySelector('[data-context-search]').value);
            try {
                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error();
                const data = await response.json();
                if (current !== generation || type.value !== kind) return;
                selected.replaceChildren(empty.cloneNode(true));
                data.items.forEach(item => {
                    const option = document.createElement('option');
                    option.value = String(item.id);
                    option.textContent = item.label;
                    selected.append(option);
                });
                output.textContent = data.items.length ? '' : form.querySelector('[data-feedback-copy]').dataset.contextEmpty;
                selected.focus();
            } catch {
                output.textContent = form.querySelector('[data-feedback-copy]').dataset.contextFailed;
            }
        });
    });
}
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeFeedback);
    else initializeFeedback();
}
