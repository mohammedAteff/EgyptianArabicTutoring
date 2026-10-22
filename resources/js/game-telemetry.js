export function createGameTelemetry({url, csrf, document: page = document, clock = () => performance.now(), fetch: send = fetch, uuid = () => globalThis.crypto?.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
    const value = globalThis.crypto?.getRandomValues ? globalThis.crypto.getRandomValues(new Uint8Array(1))[0] & 15 : Math.floor(Math.random() * 16);
    return (character === 'x' ? value : (value & 3) | 8).toString(16);
})}) {
    let attempt = null, elapsed = 0, since = null, finished = false, visible = !page.hidden, focused = page.hasFocus?.() ?? true;
    const pending = new Map();
    let sending = false;
    function accrue() {
        const now = clock();
        if (since !== null) elapsed += Math.max(0, now - since);
        since = attempt && !finished && visible && focused ? now : null;
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
                if (response.ok || (response.status >= 400 && response.status < 500 && response.status !== 429)) pending.delete(key);
                else break;
            }
        } finally { sending = false; }
    }
    function enqueue(action, metadata) {
        pending.set(`${metadata.attempt_id}:${action}`, {action, metadata});
        while (pending.size > 20) pending.delete(pending.keys().next().value);
        void flush();
    }
    const visibility = () => { accrue(); visible = !page.hidden; accrue(); void flush(); };
    const focus = () => { accrue(); focused = true; accrue(); void flush(); };
    const blur = () => { accrue(); focused = false; accrue(); };
    page.addEventListener('visibilitychange', visibility);
    page.defaultView?.addEventListener('focus', focus);
    page.defaultView?.addEventListener('blur', blur);
    page.defaultView?.addEventListener('online', flush);
    page.defaultView?.addEventListener('pagehide', flush);
    return {
        start(level) {
            attempt = uuid(); elapsed = 0; finished = false; since = visible && focused ? clock() : null;
            enqueue('game_started', {attempt_id: attempt, ...(level !== undefined && level !== null ? {level: String(level)} : {})});
            return attempt;
        },
        complete({score, total, level} = {}) {
            if (!attempt || finished) return;
            accrue(); finished = true; since = null;
            enqueue('game_completed', {attempt_id: attempt, score, total, duration_seconds: Math.min(86400, Math.round(elapsed / 1000)), ...(level !== undefined && level !== null ? {level: String(level)} : {})});
        },
        flush,
        destroy() {
            page.removeEventListener('visibilitychange', visibility);
            page.defaultView?.removeEventListener('focus', focus);
            page.defaultView?.removeEventListener('blur', blur);
            page.defaultView?.removeEventListener('online', flush);
            page.defaultView?.removeEventListener('pagehide', flush);
        }
    };
}

if (typeof window !== 'undefined') window.awaGames = {create: createGameTelemetry};
