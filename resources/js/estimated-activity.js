/**
 * Conservative, online-only observation. This module never inspects authored content,
 * key values, coordinates, URLs or media positions. Browser checks are not proof of attention.
 */
export function initializeEstimatedActivity(doc = document, win = window) {
    const contexts = [...doc.querySelectorAll('script[data-estimated-activity]')].flatMap(script => {
        try {
            const context = JSON.parse(script.textContent);
            const root = doc.getElementById(context.root);
            return root ? [{ ...context, element: root }] : [];
        } catch { return []; }
    });
    // No collector listeners, clocks, timers, storage or networking in the default-off experience.
    if (!contexts.length) return;
    let stream;
    try {
        if (typeof win.crypto?.randomUUID === 'function') {
            stream = win.crypto.randomUUID();
        } else {
            // getRandomValues also works on local HTTP origins where randomUUID is unavailable.
            const bytes = win.crypto.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('');
            stream = `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        }
    } catch { return; }
    let active = null, permit = null, sequence = 0, lastInput = 0, began = 0;
    let timer = null, flight = null, epoch = 0;
    let retryAfter = 0, stopped = false;
    let interruption = null;
    const subscriptions = new win.AbortController();
    const now = () => win.performance.now();
    const visible = () => doc.visibilityState === 'visible' && doc.hasFocus();
    const playing = () => [...doc.querySelectorAll('video, audio')].some(media => !media.paused && !media.ended);
    const cancel = (reason = null) => {
        if (typeof reason === 'string') interruption = reason;
        epoch++;
        if (timer !== null) win.clearTimeout(timer);
        timer = null;
        if (flight) flight.abort();
        flight = null;
        permit = null;
        sequence = 0;
        active = null;
    };
    const qualifies = () => active && active.element.isConnected && visible() && !playing()
        && now() - lastInput < active.grace;
    const send = async (url, body) => {
        // A single promise owns the stream; interrupted responses cannot revive it.
        if (flight) throw new Error('Busy');
        const controller = new win.AbortController();
        flight = controller;
        const timeout = win.setTimeout(() => controller.abort(), 2500);
        try {
            const response = await win.fetch(url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': doc.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify(body),
            });
            if (!response.ok) {
                const error = new Error('Unavailable');
                error.status = response.status;
                throw error;
            }
            return await response.json();
        } finally {
            win.clearTimeout(timeout);
            if (flight === controller) flight = null;
        }
    };
    const failed = error => {
        cancel('network_failure');
        if ([403, 404, 419].includes(error.status)) {
            stopped = true;
            subscriptions.abort();
        } else {
            retryAfter = now() + (error.status === 429 ? 60000 : 30000);
        }
    };
    const observe = () => {
        if (!qualifies() || !permit) { cancel(); return; }
        began = now();
        const version = epoch;
        timer = win.setTimeout(async () => {
            timer = null;
            const elapsed = now() - began;
            if (version !== epoch || !qualifies() || elapsed < active.window || elapsed > active.window + 1000
                || now() - lastInput >= active.grace) { cancel('window_interrupted'); return; }
            try {
                const answer = await send(active.checkpoint, { permit, sequence: sequence + 1, window_ms: Math.floor(elapsed) });
                if (version !== epoch) return;
                if (!qualifies() || answer.sequence !== sequence + 1) { cancel(); return; }
                sequence = answer.sequence;
                // Deliberately start AFTER acknowledgement. Network latency earns no next-window credit.
                observe();
            } catch (error) { if (version === epoch) failed(error); }
        }, active.window);
    };
    const interaction = async event => {
        if (stopped || !event.isTrusted || now() < retryAfter) return;
        const target = event.target instanceof win.Element ? event.target : null;
        const context = target && contexts.find(item => item.element.contains(target));
        if (!context || !visible() || playing() || target.closest('a, video, audio, iframe, [data-activity-excluded]')) {
            cancel('unsupported'); return;
        }
        const instant = now();
        if (active === context && instant - lastInput < context.grace) { lastInput = instant; return; }
        cancel(active ? 'context_changed' : null);
        active = context;
        lastInput = instant;
        const version = epoch;
        try {
            const answer = await send(context.anchor, { kind: context.kind, record: context.record, stream, ...(interruption ? { interruption } : {}) });
            if (version !== epoch) return;
            if (!qualifies() || answer.sequence !== 0 || !answer.permit) { cancel(); return; }
            permit = answer.permit;
            interruption = null;
            observe();
        } catch (error) { if (version === epoch) failed(error); }
    };
    for (const type of ['pointerdown', 'keydown', 'input', 'wheel', 'touchstart', 'touchmove']) {
        doc.addEventListener(type, interaction, { capture: true, passive: true, signal: subscriptions.signal });
    }
    doc.addEventListener('visibilitychange', () => { if (!visible()) cancel('hidden'); }, { signal: subscriptions.signal });
    doc.addEventListener('focusin', event => {
        if (active && (!active.element.contains(event.target) || event.target.closest('iframe, video, audio, [data-activity-excluded]'))) cancel('unsupported');
    }, { signal: subscriptions.signal });
    doc.addEventListener('play', () => cancel('unsupported'), { capture: true, signal: subscriptions.signal });
    doc.addEventListener('freeze', () => cancel('suspended'), { signal: subscriptions.signal });
    win.addEventListener('blur', () => cancel('unfocused'), { signal: subscriptions.signal });
    win.addEventListener('pagehide', () => cancel('suspended'), { signal: subscriptions.signal });
    // Returning focus, stopping media, scrolling programmatically or receiving an acknowledgement
    // never starts observation. A fresh trusted interaction is required after interruption.
    return cancel;
}

if (typeof document !== 'undefined') initializeEstimatedActivity();
