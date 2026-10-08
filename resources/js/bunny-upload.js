import {protectedPost} from './protected-video.js';

function initialize() {
    document.querySelectorAll('[data-bunny-upload]').forEach(form => {
        const status = form.querySelector('[data-upload-status]');
        const progress = form.querySelector('[data-upload-progress]');
        const pause = form.querySelector('[data-upload-pause]');
        const reload = form.querySelector('[data-upload-reload]');
        const button = form.querySelector('button[type="submit"]') ?? form.querySelector('button');
        let upload, asset, authorization, paused = false, busy = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            const file = form.elements.namedItem('video').files[0];
            if (!file || !file.type.startsWith('video/')) { status.textContent = 'Choose a video file.'; return; }
            busy = true; button.disabled = true; status.textContent = 'Preparing direct upload…';
            try {
                if (!globalThis.crypto?.subtle) throw new Error('Secure uploads require HTTPS. Open the secure site and try again.');
                const {Upload, isSupported} = await import('tus-js-client');
                if (!isSupported) throw new Error('This browser does not support resumable uploads.');
                asset ??= await protectedPost(form.dataset.createUrl, {label: form.elements.namedItem('label').value, version: Number(form.dataset.version), request_key: crypto.randomUUID()});
                authorization = await protectedPost(asset.authorization_url, {});
                upload = new Upload(file, {endpoint: authorization.endpoint, chunkSize: 8 * 1024 * 1024, retryDelays: [0, 1000, 3000, 5000], removeFingerprintOnSuccess: true,
                    fingerprint: async () => {
                        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(`${form.dataset.createUrl}:${asset.asset_id}:${file.size}:${file.lastModified}:${file.type}`));
                        return `lms-upload-${Array.from(new Uint8Array(digest), n => n.toString(16).padStart(2,'0')).join('')}`;
                    },
                    metadata: {filetype: file.type, title: `LMS media ${asset.asset_id}`},
                    onBeforeRequest: async request => {
                        const url = new URL(request.getURL());
                        if (url.origin !== 'https://video.bunnycdn.com' || !(url.pathname === '/tusupload' || url.pathname.startsWith('/tusupload/'))) throw new Error('Unexpected upload destination.');
                        if (authorization.expires * 1000 - Date.now() < 60000) authorization = await protectedPost(asset.authorization_url, {});
                        request.setHeader('AuthorizationSignature', authorization.signature); request.setHeader('AuthorizationExpire', String(authorization.expires));
                        request.setHeader('LibraryId', String(authorization.library_id)); request.setHeader('VideoId', authorization.video_id);
                    },
                    onProgress: (sent, total) => { progress.value = Math.round(sent / total * 100); status.textContent = `Uploading ${progress.value}% — keep this page open or resume the same media later.`; },
                    onError: () => { busy = false; button.disabled = false; pause.hidden = true; status.textContent = 'Upload interrupted. Check the media state and resume this upload; do not create a duplicate.'; reload.hidden = false; },
                    onSuccess: () => { status.textContent = 'Upload complete. Bunny is processing the video. Check status before using it in a lesson.'; pause.hidden = true; reload.hidden = false; },
                });
                const previous = await upload.findPreviousUploads();
                if (previous.length) upload.resumeFromPreviousUpload(previous[0]);
                pause.hidden = false; upload.start();
            } catch (error) { status.textContent = error.message; busy = false; button.disabled = false; reload.hidden = false; }
        });
        pause.addEventListener('click', async () => { if (!upload) return; paused = !paused; if (paused) { await upload.abort(); status.textContent = 'Upload paused. Resume when ready.'; } else upload.start(); pause.textContent = paused ? 'Resume upload' : 'Pause upload'; });
        document.querySelectorAll('[data-upload-resume]').forEach(resume => resume.addEventListener('click', () => {
            if (busy) return;
            asset = {asset_id: Number(resume.dataset.assetId), authorization_url: resume.dataset.uploadResume};
            status.textContent = 'Choose the same video file, then select Upload video to resume this existing media.'; form.scrollIntoView({block:'center'});
        }));
    });
    document.querySelectorAll('[data-media-check]').forEach(button => button.addEventListener('click', async () => {
        const state = button.closest('[data-video-asset]').querySelector('[data-media-state]'); button.disabled = true;
        try { const data = await protectedPost(button.dataset.mediaCheck, {}); state.textContent = `${data.status} · ${data.referenced ? 'Referenced / retained — deletion blocked' : 'Unreferenced media candidate'}${data.duration_seconds ? ` · ${data.duration_seconds} seconds` : ''}`; }
        catch (error) { state.textContent = error.message; } finally { button.disabled = false; }
    }));
}
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true}); else initialize();
}
