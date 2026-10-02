(() => {
    if (typeof document === 'undefined') return;
    const endpoint = document.querySelector('meta[name="analytics-event-url"]')?.content;
    if (!endpoint || document.querySelector('meta[name="analytics-disabled"]')?.content === '1') return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (window.__awaTelemetryInitialized) return;
    window.__awaTelemetryInitialized = true;
    const location = window.location;
    const clock = typeof performance !== 'undefined' ? performance : {now: () => Date.now()};
    const path = location.pathname;
    const template = /resources|ressources|ressourcen/.test(path) ? 'resource' : /games|jeux|spiele/.test(path) ? 'game' : /blog/.test(path) ? 'blog' : 'landing';
    const sections = new Map();
    let queue = [], sending = false, active = null, since = clock.now();
    let visible = document.visibilityState !== 'hidden', focused = true;
    function enqueue(name, metadata = {}) {
        queue.push({event_uuid: (crypto.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => { const number = Math.floor(Math.random() * 16); return (character === 'x' ? number : (number & 3) | 8).toString(16); })), event_name: name, page: location.href.slice(0, 500), metadata});
        if (queue.length >= 10) flush();
    }
    async function flush(exit = false) {
        if (!queue.length || (sending && !exit)) return;
        const batch = queue.splice(0, 20);
        const body = JSON.stringify({events: batch, _token: csrf});
        if (exit && navigator.sendBeacon?.(endpoint, new Blob([body], {type: 'application/json'}))) return;
        sending = true;
        try {
            const response = await fetch(endpoint, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf}, body, keepalive: exit});
            if (!response.ok && (response.status === 429 || response.status >= 500)) queue.unshift(...batch);
        } catch { queue.unshift(...batch); }
        finally { sending = false; }
        queue = queue.slice(-100);
    }
    function commit() {
        const now = clock.now();
        const seconds = (now - since) / 1000;
        if (active && seconds > 0) enqueue('section_dwell', {section_id: active, page_template: template, path, dwell_seconds: Math.min(seconds, 30)});
        since = now;
    }
    function evaluate() {
        let chosen = null, greatest = 0;
        for (const [id, entry] of sections) {
            const rect = entry.element.getBoundingClientRect();
            const height = Math.max(0, Math.min(rect.bottom, innerHeight) - Math.max(rect.top, 0));
            const width = Math.max(0, Math.min(rect.right, innerWidth) - Math.max(rect.left, 0));
            const ratio = rect.height > 0 && rect.width > 0 ? (height * width) / (Math.min(rect.height, innerHeight) * Math.min(rect.width, innerWidth)) : 0;
            const exposed = visible && focused && ratio >= 0.5;
            if (exposed && !entry.exposed) enqueue('section_view', {section_id: id, page_template: template, path});
            entry.exposed = exposed;
            if (exposed && ratio > greatest) { greatest = ratio; chosen = id; }
        }
        if (chosen !== active) { commit(); active = chosen; since = clock.now(); }
    }
    function initialize() {
        document.querySelectorAll('[data-whatsapp-cta]').forEach(link => link.addEventListener('click', () => {
            enqueue('whatsapp_clicked', {target_url: link.href, platform: 'whatsapp', placement: 'floating_cta', context: link.dataset.context, language: link.dataset.language});
            flush(true);
        }));
        document.querySelectorAll('[data-analytics-event]').forEach(element => {
            try { enqueue(element.dataset.analyticsEvent, JSON.parse(element.dataset.analyticsMetadata || '{}')); }
            catch { /* Invalid markup contributes no event. */ }
        });
        document.querySelectorAll('[data-section-id], #hero, #pricing, #curriculum, #tutor-bio, #blog-content, #resource-preview, #game-board').forEach(element => {
            const id = element.dataset.sectionId || element.id;
            if (/^[a-zA-Z0-9_-]{1,64}$/.test(id)) sections.set(id, {element, exposed: false});
        });
        if (typeof IntersectionObserver !== 'undefined') {
            const observer = new IntersectionObserver(evaluate, {threshold: [0, 0.25, 0.5, 0.75, 1]});
            sections.forEach(entry => observer.observe(entry.element));
        }
        evaluate(); flush();
        setInterval(() => { if (visible && focused) { evaluate(); commit(); } flush(); }, 10000);
        setInterval(() => { if (visible && focused) { enqueue('session_activity'); flush(); } }, 45000);
    }
    document.addEventListener('visibilitychange', () => {
        commit(); visible = document.visibilityState !== 'hidden';
        if (!visible) active = null;
        evaluate(); flush(!visible);
    });
    window.addEventListener('blur', () => { commit(); focused = false; active = null; evaluate(); flush(true); });
    window.addEventListener('focus', () => { focused = true; since = clock.now(); evaluate(); });
    window.addEventListener('pagehide', () => { commit(); active = null; visible = false; flush(true); });
    window.addEventListener('pageshow', () => { visible = document.visibilityState !== 'hidden'; since = clock.now(); evaluate(); });
    window.addEventListener('scroll', evaluate, {passive: true});
    window.addEventListener('resize', evaluate);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
