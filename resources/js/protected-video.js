export function scopedHlsUrl(candidate, authorizationUrl) {
    const signed = new URL(authorizationUrl);
    const marker = signed.pathname.match(/^(\/bcdn_token=HS256-[A-Za-z0-9_-]+&expires=\d+&token_path=%2F([a-f0-9-]{36})%2F)\/\2\/playlist\.m3u8$/);
    if (signed.protocol !== 'https:' || !/^[a-z0-9-]+\.b-cdn\.net$/.test(signed.hostname) || !marker || signed.search || signed.hash) throw new Error('Invalid protected manifest.');
    const target = new URL(candidate, signed);
    if (target.origin !== signed.origin || target.search || target.hash) throw new Error('Unexpected media target.');
    let path = target.pathname;
    if (path.startsWith('/bcdn_token=')) {
        const index = path.indexOf(`/${marker[2]}/`);
        if (index < 0) throw new Error('Unexpected media scope.');
        path = path.slice(index);
    }
    if (!path.startsWith(`/${marker[2]}/`) || /%2f|%5c|%2e/i.test(path) || !/\.(?:m3u8|ts|m4s|mp4|key|vtt)$/i.test(path)) throw new Error('Unexpected media scope.');
    return `${signed.origin}${marker[1]}${path}`;
}

export function authorizationDelay(data, now = Date.now()) {
    const remaining = Date.parse(data.expires_at) - now;
    if (!Number.isFinite(remaining) || remaining <= 0 || remaining > 610000) throw new Error('Video authorization expired.');
    const interval = Number(data.heartbeat_seconds) * 1000;
    if (!Number.isFinite(interval) || interval < 15000 || interval > 60000) throw new Error('Invalid renewal interval.');
    return Math.max(1000, Math.min(interval, remaining - 5000));
}

export async function protectedPost(url, body, options = {}) {
    const target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin) throw new Error('Invalid authorization target.');
    const response = await fetch(target, {method: 'POST', credentials: 'same-origin', redirect: 'error', referrerPolicy: 'no-referrer',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''},
        body: JSON.stringify(body), cache: 'no-store', ...options});
    let data;
    try { data = await response.json(); } catch { throw new Error('Your session or video authorization is unavailable. Reload this page.'); }
    if (!response.ok) throw new Error(response.status === 409 || response.status === 403 ? data.message : 'Video authorization is unavailable. Check your access or try again later.');
    return data;
}

function initialize() {
    document.querySelectorAll('[data-protected-player]').forEach(root => {
        const video = root.querySelector('video');
        const container = root.querySelector('[data-video-container]');
        const status = root.querySelector('[data-protected-status]');
        const watermark = root.querySelector('[data-video-watermark]');
        const play = root.querySelector('[data-video-play]');
        const seek = root.querySelector('[data-video-seek]');
        let requestKey, proof, authorization, hls, renewal, expiry, movement, busy = false, generation = 0;
        const stop = (message, release = true) => {
            const progress = {pending: []};
            root.dispatchEvent(new CustomEvent('lms:stopping', {detail: progress}));
            generation++;
            clearTimeout(renewal); clearTimeout(expiry); clearInterval(movement);
            video.pause(); hls?.destroy(); hls = null;
            video.removeAttribute('src'); video.load(); watermark.hidden = true;
            seek.disabled = true; play.textContent = 'Play video'; status.textContent = message;
            const old = authorization;
            const oldProof = proof;
            authorization = null;
            if (release && old) Promise.allSettled(progress.pending).then(() => protectedPost(old.close_url, {lease_token: oldProof}, {keepalive: true})).catch(() => {});
        };
        const applyPolicy = data => {
            authorizationDelay(data);
            scopedHlsUrl(data.url, data.url);
            authorization = data;
            watermark.textContent = typeof data.watermark === 'string' ? data.watermark : '';
            watermark.hidden = !data.watermark;
            clearInterval(movement);
            if (data.watermark) {
                const move = () => { watermark.style.left = `${5 + Math.random() * 55}%`; watermark.style.top = `${8 + Math.random() * 60}%`; };
                move(); movement = setInterval(move, 9000);
            }
            clearTimeout(expiry);
            expiry = setTimeout(() => stop('Video authorization expired. Select Play video to check access again.'), Math.max(0, Date.parse(data.expires_at) - Date.now()));
        };
        const schedule = () => {
            clearTimeout(renewal);
            const turn = generation;
            renewal = setTimeout(async () => {
                if (!authorization || document.hidden || video.paused) return;
                try {
                    const data = await protectedPost(authorization.renew_url, {request_key: requestKey, lease_token: proof});
                    if (turn !== generation) { protectedPost(data.close_url, {lease_token: proof}, {keepalive: true}).catch(() => {}); return; }
                    applyPolicy(data);
                    if (!hls) {
                        const position = video.currentTime;
                        video.src = data.url;
                        video.addEventListener('loadedmetadata', () => { video.currentTime = Math.min(position, video.duration || position); video.play().catch(() => {}); }, {once: true});
                        video.load();
                    }
                    schedule();
                } catch (error) { if (turn === generation) stop(error.message); }
            }, authorizationDelay(authorization));
        };
        play.addEventListener('click', async () => {
            if (busy) return;
            if (authorization) {
                if (video.paused) { video.play().then(schedule).catch(() => { status.textContent = 'Select Play video again to start.'; }); } else video.pause();
                return;
            }
            busy = true; play.disabled = true; const turn = generation;
            requestKey = crypto.randomUUID(); proof = Array.from(crypto.getRandomValues(new Uint8Array(32)), byte => byte.toString(16).padStart(2, '0')).join('');
            status.textContent = 'Checking video access…';
            try {
                const data = await protectedPost(root.dataset.authorizeUrl, {request_key: requestKey, lease_token: proof});
                if (turn !== generation) { protectedPost(data.close_url, {lease_token: proof}).catch(() => {}); return; }
                applyPolicy(data);
                const progress = {lease_id: data.lease_id, lease_token: proof, pending: []};
                root.dispatchEvent(new CustomEvent('lms:authorized', {detail: progress}));
                await Promise.allSettled(progress.pending);
                if (turn !== generation) return;
                if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = data.url;
                    await video.play();
                } else {
                    const {default: Hls} = await import('hls.js');
                    if (turn !== generation) return;
                    if (!Hls.isSupported()) throw new Error('This browser does not support protected video.');
                    class ScopedLoader extends Hls.DefaultConfig.loader {
                        load(context, config, callbacks) {
                            try { context.url = scopedHlsUrl(context.url, authorization.url); super.load(context, config, callbacks); }
                            catch { stop('The provider returned an unexpected media location.'); }
                        }
                    }
                    hls = new Hls({loader: ScopedLoader, maxBufferLength: 30, backBufferLength: 0, debug: false});
                    hls.on(Hls.Events.ERROR, (_event, details) => { if (details.fatal) stop('This video could not be played. Try again later.'); });
                    hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => { status.textContent = 'Select Play video to start.'; }));
                    hls.loadSource(data.url); hls.attachMedia(video);
                }
                status.textContent = 'Protected session active.'; schedule();
            } catch (error) { if (turn === generation) stop(error.message); }
            finally { busy = false; play.disabled = false; }
        });
        video.addEventListener('play', () => { play.textContent = 'Pause'; if (authorization) schedule(); });
        video.addEventListener('pause', () => { play.textContent = 'Play video'; });
        video.addEventListener('ended', () => stop('Video ended. Select Play video to replay.'));
        video.addEventListener('error', () => { if (authorization) stop('This video could not be played. Try again later.'); });
        video.addEventListener('timeupdate', () => { seek.value = String(video.currentTime); root.querySelector('[data-video-time]').textContent = `${Math.floor(video.currentTime / 60)}:${String(Math.floor(video.currentTime % 60)).padStart(2, '0')}`; });
        video.addEventListener('loadedmetadata', () => { seek.max = String(video.duration); seek.disabled = !Number.isFinite(video.duration); });
        seek.addEventListener('input', () => { if (Number.isFinite(video.duration)) video.currentTime = Math.min(Number(seek.value), video.duration); });
        root.querySelector('[data-video-mute]').addEventListener('click', event => { video.muted = !video.muted; event.currentTarget.textContent = video.muted ? 'Unmute' : 'Mute'; });
        const fullscreen = root.querySelector('[data-video-fullscreen]');
        fullscreen.hidden = !container.requestFullscreen;
        fullscreen.addEventListener('click', () => { if (document.fullscreenElement) document.exitFullscreen().catch(() => {}); else container.requestFullscreen().catch(() => { status.textContent = 'Fullscreen is unavailable in this browser.'; }); });
        root.querySelector('[data-video-close]').addEventListener('click', () => stop('Player closed. Its issued video link expires shortly.'));
        document.addEventListener('visibilitychange', () => { if (document.hidden && authorization) stop('Player stopped while the page was hidden. Select Play video to continue.'); });
        window.addEventListener('pagehide', () => stop('Player closed.'));
    });
}
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true}); else initialize();
}
