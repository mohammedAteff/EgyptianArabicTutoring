export function createGameTelemetry({url, csrf, document: page = document, clock = () => performance.now(), fetch: send = fetch, enabled = true, heartbeat = false, uuid = () => globalThis.crypto?.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
    const value = globalThis.crypto?.getRandomValues ? globalThis.crypto.getRandomValues(new Uint8Array(1))[0] & 15 : Math.floor(Math.random() * 16);
    return (character === 'x' ? value : (value & 3) | 8).toString(16);
})}) {
    let attempt = null, elapsed = 0, since = null, finished = false, visible = !page.hidden, focused = page.hasFocus?.() ?? true;
    const pending = new Map();
    let sending = false, sequence = 0, suspended = false;
    const collecting = enabled && page.querySelector?.('meta[name="analytics-disabled"]')?.content !== '1';
    function accrue() {
        const now = clock();
        if (since !== null) elapsed += Math.max(0, now - since);
        since = attempt && !finished && visible && focused && !suspended ? now : null;
    }
    async function flush() {
        if (sending) return;
        sending = true;
        try {
            for (const [key, body] of pending) {
                let response;
                try {
                    response = await send(url, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify(body), keepalive: true});
                } catch { break; }
                if ((response.ok || (response.status >= 400 && response.status < 500 && response.status !== 429)) && pending.get(key) === body) pending.delete(key);
                else break;
            }
        } finally { sending = false; }
    }
    function enqueue(action, metadata) {
        if (!collecting) return;
        pending.set(`${metadata.attempt_id}:${action}`, {action, metadata});
        while (pending.size > 20) pending.delete(pending.keys().next().value);
        void flush();
    }
    function duration() { return Math.min(86400, Math.round(elapsed / 1000)); }
    function activity() {
        if (!heartbeat || !attempt || finished) return;
        accrue();
        enqueue('game_activity', {attempt_id: attempt, duration_seconds: duration(), sequence: ++sequence, contract_version: 2});
    }
    function interrupt({reason = 'exit', revealed_count} = {}) {
        if (!attempt || finished) return;
        accrue(); finished = true; since = null;
        enqueue('game_interrupted', {attempt_id: attempt, duration_seconds: duration(), reason, revealed_count, contract_version: 2});
    }
    const visibility = () => { accrue(); visible = !page.hidden; accrue(); if (!visible) activity(); void flush(); };
    const focus = () => { accrue(); focused = true; accrue(); void flush(); };
    const blur = () => { accrue(); focused = false; accrue(); activity(); };
    const pagehide = event => { if (heartbeat && !event?.persisted) interrupt({reason: 'page_left'}); else activity(); void flush(); };
    page.addEventListener('visibilitychange', visibility);
    page.defaultView?.addEventListener('focus', focus);
    page.defaultView?.addEventListener('blur', blur);
    page.defaultView?.addEventListener('online', flush);
    page.defaultView?.addEventListener('pagehide', pagehide);
    const pulse = heartbeat && collecting ? setInterval(() => { if (visible && focused) activity(); else void flush(); }, 15000) : null;
    return {
        start(level, properties = {}) {
            attempt = uuid(); elapsed = 0; sequence = 0; finished = false; suspended = false; since = visible && focused ? clock() : null;
            enqueue('game_started', {...properties, attempt_id: attempt, ...(level !== undefined && level !== null ? {level: String(level)} : {})});
            return attempt;
        },
        complete({score, total, level, ...properties} = {}) {
            if (!attempt || finished) return;
            accrue(); finished = true; since = null;
            enqueue('game_completed', {...properties, attempt_id: attempt, score, total, duration_seconds: duration(), ...(level !== undefined && level !== null ? {level: String(level)} : {})});
        },
        activity,
        interrupt,
        pause() { accrue(); suspended = true; accrue(); activity(); },
        resume() { accrue(); suspended = false; accrue(); },
        flush,
        destroy() {
            page.removeEventListener('visibilitychange', visibility);
            page.defaultView?.removeEventListener('focus', focus);
            page.defaultView?.removeEventListener('blur', blur);
            page.defaultView?.removeEventListener('online', flush);
            page.defaultView?.removeEventListener('pagehide', pagehide);
            if (pulse !== null) clearInterval(pulse);
        }
    };
}

if (typeof window !== 'undefined') window.awaGames = {create: createGameTelemetry};
