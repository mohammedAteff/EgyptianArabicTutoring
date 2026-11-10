import { uiText } from './ui-translations.js';
import { createCopyFeedback } from './clipboard.js';

export function initializeQuickNoteCopy(panel) {
    const button = panel.querySelector('[data-quick-copy]');
    const label = button.querySelector('[data-quick-copy-label]');
    const body = panel.querySelector('[data-quick-body]');
    const status = panel.querySelector('[data-quick-status]');
    const feedback = createCopyFeedback(copied => {
        button.dataset.copied = String(copied !== null);
        label.textContent = uiText(copied === null ? 'Copy note text' : 'Copied');
        button.disabled = body.value.length === 0;
    });
    feedback.reset();
    button.addEventListener('click', async () => {
        if (!body.value) return;
        try { await feedback.copy(body.value); }
        catch { body.focus(); body.select(); status.textContent = uiText('Select and copy your text using your device.'); }
    });
    body.addEventListener('input', () => feedback.reset());
    return feedback;
}

function draftUuid() {
    if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export class QuickNoteDraft {
    constructor(request, changed = () => {}, uuid = draftUuid) {
        this.request = request;
        this.changed = changed;
        this.uuid = uuid;
        this.reset();
    }
    reset() {
        this.body = '';
        this.savedBody = '';
        this.note = null;
        this.creationId = this.uuid();
        this.pending = null;
        this.state = 'blank';
        this.message = '';
        this.blocked = false;
        this.bookmarkDesired = null;
        this.bookmarkSequence = 0;
        this.bookmarkBusy = null;
        this.changed(this);
    }
    get dirty() { return Boolean(this.pending || this.body !== this.savedBody || this.bookmarkDesired !== null); }
    edit(body) {
        this.body = body;
        if (!this.blocked) this.state = this.body === this.savedBody ? (this.note ? 'saved' : 'blank') : 'unsaved';
        this.changed(this);
    }
    async flush(retry = false) {
        if (this.inFlight) { await this.inFlight; return this.blocked ? false : this.flush(); }
        if (retry && this.state !== 'conflict' && this.state !== 'expired') this.blocked = false;
        if (this.blocked) return false;
        this.inFlight = this.save();
        try { return await this.inFlight; } finally { this.inFlight = null; }
    }
    async save() {
        while (this.pending || this.body !== this.savedBody) {
            if (!this.pending) {
                if (!this.body.trim() || [...this.body].length > 30000) {
                    if (!this.note && !this.body.trim()) { this.state = 'blank'; this.changed(this); return true; }
                    this.state = 'failed';
                    this.message = [...this.body].length > 30000 ? 'Notes can contain up to 30,000 characters. Shorten your text to save.' : 'Write something before saving. Your last saved note is unchanged.';
                    this.changed(this);
                    return false;
                }
                this.pending = { body: this.body, ...(this.note ? { revision: this.note.revision } : { creation_id: this.creationId }) };
            }
            const snapshot = this.pending;
            const bookmarkSequence = this.bookmarkSequence;
            const path = this.note ? `/${this.note.id}` : '';
            this.state = 'saving'; this.changed(this);
            try {
                const result = await this.request(path, this.note ? 'PUT' : 'POST', snapshot);
                if (this.note && this.bookmarkSequence !== bookmarkSequence) result.note.bookmarked = this.note.bookmarked;
                this.note = result.note;
                this.savedBody = result.note.body;
                if (result.note.body !== snapshot.body) throw Object.assign(new Error(), { status: 409 });
                this.pending = null;
                this.state = this.body === this.savedBody ? 'saved' : 'unsaved';
                this.changed(this);
            } catch (error) {
                this.state = error.status === 409 ? 'conflict' : [401, 419].includes(error.status) ? 'expired' : 'failed';
                this.message = this.state === 'conflict' ? 'This note changed in another editor. Copy your text before loading the current version.' : this.state === 'expired' ? 'Your session changed or expired. Copy your draft before signing in again.' : error.status === 422 ? 'Your note could not be saved. Use 1–30,000 characters and try again.' : 'Could not save. Your draft is still here. Check your connection and retry.';
                this.blocked = true;
                this.changed(this);
                return false;
            }
        }
        return true;
    }
    async bookmark() {
        if (this.bookmarkBusy) return this.bookmarkBusy;
        this.bookmarkBusy = (async () => {
            if (!await this.flush() || !this.note) return false;
            while (this.bookmarkDesired !== null) {
                const desired = this.bookmarkDesired;
                try {
                    const result = await this.request(`/${this.note.id}/bookmark`, 'PUT', { bookmarked: desired });
                    this.bookmarkSequence++;
                    this.note.bookmarked = result.note.bookmarked;
                    if (this.bookmarkDesired === result.note.bookmarked) this.bookmarkDesired = null;
                    this.changed(this);
                } catch (error) {
                    this.state = [401, 419].includes(error.status) ? 'expired' : 'failed';
                    this.message = this.state === 'expired' ? 'Your session changed or expired. Copy your draft before signing in again.' : 'Bookmark could not be confirmed. Retry to apply your choice.';
                    this.changed(this); return false;
                }
            }
            return true;
        })();
        try { return await this.bookmarkBusy; } finally { this.bookmarkBusy = null; }
    }
}

function initializeQuickNotes() {
    const panel = document.querySelector('[data-quick-notes]');
    const launcher = document.querySelector('[data-quick-notes-launcher]');
    if (!panel || !launcher || !globalThis.crypto?.getRandomValues || !panel.showModal || panel.dataset.ready) return;
    panel.dataset.ready = 'true';
    const body = panel.querySelector('[data-quick-body]');
    const status = panel.querySelector('[data-quick-status]');
    const retry = panel.querySelector('[data-quick-retry]');
    const restore = panel.querySelector('[data-quick-restore]');
    const load = panel.querySelector('[data-quick-load]');
    const bookmark = panel.querySelector('[data-quick-bookmark]');
    const copyFeedback = initializeQuickNoteCopy(panel);
    const root = document.querySelector('.student-portal');
    const desktop = matchMedia('(min-width: 1024px)');
    let draft;
    let timer;
    let previousOverflow = '';
    let savedScroll = 0;
    let switching = false;
    let backdropStart = false;
    let alive = true;
    let documentGeneration = 0;
    let opening = false;
    let confirmDecision;
    const confirmation = panel.querySelector('[data-quick-confirm]');
    function ask(message) {
        confirmDecision?.(false);
        confirmation.querySelector('p').textContent = uiText(message);
        confirmation.hidden = false;
        confirmation.querySelector('[data-quick-confirm-no]').focus({ preventScroll: true });
        confirmation.scrollIntoView({ block: 'nearest' });
        return new Promise(resolve => { confirmDecision = result => { confirmation.hidden = true; confirmDecision = null; resolve(result); }; });
    }
    confirmation.querySelector('[data-quick-confirm-yes]').addEventListener('click', () => confirmDecision?.(true));
    confirmation.querySelector('[data-quick-confirm-no]').addEventListener('click', () => { confirmDecision?.(false); body.focus({ preventScroll: true }); });
    async function request(path, method, payload) {
        const generation = documentGeneration;
        const response = await fetch(path === '/session' ? panel.dataset.sessionUrl : `${panel.dataset.createUrl.replace(/\/$/, '')}${path}`, {
            method, credentials: 'same-origin', redirect: 'error', cache: 'no-store',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Quick-Notes-Session': panel.dataset.sessionToken },
            ...(payload ? { body: JSON.stringify(payload) } : {}),
        });
        if (!alive || generation !== documentGeneration) throw Object.assign(new Error(), { status: 401 });
        if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
            const details = await response.json().catch(() => ({}));
            if (details.code === 'account_changed') { draft.body = ''; draft.savedBody = ''; draft.pending = null; draft.note = null; body.value = ''; copyFeedback.reset(); }
            throw Object.assign(new Error(), { status: response.status, code: details.code });
        }
        return response.json();
    }
    function listingChanged() {
        if (!document.querySelector('[data-learning-notes-listing]') || document.querySelector('[data-quick-listing-notice]')) return;
        const notice = document.createElement('p');
        notice.dataset.quickListingNotice = '';
        notice.className = 'student-surface'; notice.setAttribute('role', 'status');
        notice.textContent = uiText('Your notes or bookmarks changed. Refresh this listing when your page work is saved.');
        document.querySelector('main').prepend(notice);
    }
    function render(state) {
        if (!draft || state !== draft || !alive) return;
        const messages = { blank: 'Write a note. Changes save automatically.', unsaved: 'Unsaved changes — waiting to save.', saving: 'Saving...', saved: 'Saved' };
        status.textContent = uiText(messages[state.state] || state.message);
        launcher.querySelector('.student-quick-dot').hidden = !state.dirty;
        launcher.setAttribute('aria-label', uiText(state.dirty ? 'Quick Notes — unsaved changes' : 'Quick Notes'));
        retry.hidden = state.state !== 'failed';
        load.hidden = state.state !== 'conflict';
        restore.hidden = !state.note || state.body === state.savedBody;
        bookmark.setAttribute('aria-pressed', String(state.note?.bookmarked ?? false));
        bookmark.querySelector('[data-quick-bookmark-label]').textContent = uiText(state.note?.bookmarked ? 'Remove Bookmark' : 'Save Bookmark');
        bookmark.disabled = state.state === 'expired' || (!state.note && !state.body.trim());
        body.setAttribute('aria-invalid', String(['failed', 'conflict'].includes(state.state)));
        if (state.state === 'saved' && state.note) listingChanged();
    }
    draft = new QuickNoteDraft(request, render);
    render(draft);
    launcher.setAttribute('role', 'button');
    launcher.setAttribute('aria-controls', panel.id);
    launcher.setAttribute('aria-expanded', 'false');
    function mode() {
        if (desktop.matches) { panel.removeAttribute('aria-modal'); panel.show(); }
        else { panel.setAttribute('aria-modal', 'true'); panel.showModal(); document.documentElement.style.overflow = 'hidden'; }
        root.setAttribute('data-quick-notes-open', desktop.matches ? 'desktop' : 'mobile');
    }
    function otherModal() {
        return [...document.querySelectorAll('dialog:modal, [aria-modal="true"]:not(dialog)')].some(element => element !== panel && element.getClientRects().length && element.getAttribute('aria-hidden') !== 'true' && getComputedStyle(element).visibility !== 'hidden');
    }
    async function open() {
        if (opening || panel.open || otherModal() || document.fullscreenElement) return;
        opening = true;
        try { await request('/session', 'GET'); }
        catch (error) { draft.state = [401, 419].includes(error.status) ? 'expired' : 'failed'; draft.blocked = true; draft.message = draft.state === 'expired' ? 'Your session changed or expired. Copy your draft before signing in again.' : 'Could not save. Your draft is still here. Check your connection and retry.'; render(draft); }
        finally { opening = false; }
        savedScroll = scrollY; previousOverflow = document.documentElement.style.overflow;
        mode(); launcher.setAttribute('aria-expanded', 'true'); body.focus({ preventScroll: true });
    }
    function close(focus = true) {
        clearTimeout(timer);
        copyFeedback.reset();
        confirmDecision?.(false);
        void draft.flush();
        if (panel.open) panel.close();
        root.removeAttribute('data-quick-notes-open');
        root.removeAttribute('data-student-keyboard');
        root.toggleAttribute('data-student-higher-interface', Boolean(document.fullscreenElement || otherModal()));
        document.documentElement.style.overflow = previousOverflow;
        launcher.setAttribute('aria-expanded', 'false');
        if (!desktop.matches) window.scrollTo({ top: savedScroll, behavior: 'instant' });
        if (focus) launcher.focus({ preventScroll: true });
    }
    launcher.addEventListener('click', event => { event.preventDefault(); if (panel.open) close(); else void open(); });
    launcher.addEventListener('keydown', event => { if (event.key === ' ') { event.preventDefault(); launcher.click(); } });
    panel.querySelector('[data-quick-close]').addEventListener('click', () => close());
    panel.addEventListener('cancel', event => { event.preventDefault(); close(); });
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(); }
    });
    panel.addEventListener('pointerdown', event => {
        const bounds = panel.getBoundingClientRect();
        backdropStart = event.target === panel && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom);
    });
    panel.addEventListener('click', event => { if (backdropStart && event.target === panel && !desktop.matches) close(); backdropStart = false; });
    body.addEventListener('input', () => { draft.edit(body.value); clearTimeout(timer); timer = setTimeout(() => void draft.flush(), 700); });
    async function resolve() {
        clearTimeout(timer);
        const saved = await draft.flush();
        const bookmarked = draft.bookmarkDesired === null || await draft.bookmark();
        return (saved && bookmarked) || await ask('Your changes are not confirmed saved. Discard this draft and continue?');
    }
    retry.addEventListener('click', async () => { if (await draft.flush(true) && draft.bookmarkDesired !== null) await draft.bookmark(); });
    restore.addEventListener('click', async () => {
        if (draft.inFlight || draft.pending || draft.state === 'conflict' || !await ask('Replace your draft with the last confirmed saved text?')) return;
        draft.blocked = false; draft.edit(draft.savedBody); body.value = draft.body; copyFeedback.reset(); body.focus();
    });
    load.addEventListener('click', async () => {
        if (!draft.note || !await ask('Copy your draft first. Replace it with the current server version?')) return;
        try { const result = await request(`/${draft.note.id}`, 'GET'); draft.note = result.note; draft.pending = null; draft.savedBody = result.note.body; draft.blocked = false; draft.edit(result.note.body); body.value = draft.body; copyFeedback.reset(); }
        catch { status.textContent = uiText('Could not load the current version. Your draft is still here.'); }
    });
    bookmark.addEventListener('click', () => { draft.bookmarkDesired = !(draft.bookmarkDesired ?? draft.note?.bookmarked ?? false); render(draft); void draft.bookmark().then(listingChanged); });
    panel.querySelector('[data-quick-new]').addEventListener('click', async () => { if (await resolve()) { draft.reset(); body.value = ''; copyFeedback.reset(); body.focus(); } });
    document.addEventListener('click', async event => {
        const link = event.target.closest('a[href]');
        if (!link || link === launcher || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
        if (!draft.dirty) return;
        event.preventDefault();
        if (await resolve()) { draft.reset(); location.assign(link.href); }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;
        void request('/session', 'GET').catch(error => {
            if (![401, 419].includes(error.status)) return;
            clearTimeout(timer); draft.state = 'expired'; draft.blocked = true; draft.message = 'Your session changed or expired. Copy your draft before signing in again.'; render(draft);
        });
    });
    window.addEventListener('beforeunload', event => { if (draft.dirty) { event.preventDefault(); event.returnValue = ''; } });
    desktop.addEventListener('change', () => {
        if (!panel.open) return;
        const active = document.activeElement;
        switching = true; panel.close(); document.documentElement.style.overflow = previousOverflow; mode(); switching = false;
        active?.focus({ preventScroll: true });
    });
    panel.addEventListener('close', () => { if (!switching && !panel.open && root.hasAttribute('data-quick-notes-open')) close(); });
    document.addEventListener('student:more-opening', () => { if (panel.open) close(false); });
    document.addEventListener('fullscreenchange', () => { if (document.fullscreenElement && panel.open) close(false); });
    new MutationObserver(() => { if (panel.open && otherModal()) close(false); }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['open', 'aria-modal', 'hidden', 'aria-hidden', 'style'] });
    window.visualViewport?.addEventListener('resize', () => panel.style.setProperty('--quick-viewport-height', `${window.visualViewport.height}px`));
    window.addEventListener('pagehide', () => { documentGeneration++; alive = false; clearTimeout(timer); panel.close(); root.removeAttribute('data-quick-notes-open'); document.documentElement.style.overflow = previousOverflow; body.value = ''; copyFeedback.reset(); draft.reset(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { alive = true; draft = new QuickNoteDraft(request, render); body.value = ''; copyFeedback.reset(); render(draft); } });
}
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeQuickNotes, { once: true });
    else initializeQuickNotes();
}
