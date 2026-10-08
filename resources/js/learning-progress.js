import {protectedPost} from './protected-video.js';

export function validWatchPosition(position, duration) {
    return Number.isFinite(position) && Number.isFinite(duration) && duration > 0 && position >= 0 && position <= duration && duration <= 86400;
}

export function recordingMime(mediaRecorder) {
    return ['audio/webm', 'audio/ogg'].find(mime => mediaRecorder?.isTypeSupported(mime)) || null;
}

export function initializeLearningProgress() {
    document.querySelectorAll('[data-protected-player][data-watch-url]').forEach(root => {
        const video = root.querySelector('video');
        const status = root.querySelector('[data-watch-status]');
        let watch, security, timer, sequence = 0, generation = 0, resume = null, queue = Promise.resolve();
        const flush = mode => {
            const position = video.currentTime, duration = video.duration, active = watch, proof = security, turn = generation;
            if (!active || !proof || !validWatchPosition(position, duration)) return queue;
            queue = queue.then(async () => {
                if (turn !== generation) return;
                const result = await protectedPost(root.dataset.watchUrl + '/' + active.id,
                    {...proof, watch_token: active.watch_token, sequence: ++sequence, position, mode});
                status.textContent = result.completed ? 'Lesson requirements completed.' : Math.floor(result.percent) + '% watched · progress saved';
            }).catch(() => {
                if (turn === generation) { clearInterval(timer); watch = null; status.textContent = 'Progress could not be saved. Reopen the video to check access.'; }
            });
            return queue;
        };
        root.addEventListener('lms:authorized', event => {
            const turn = ++generation;
            clearInterval(timer); watch = null; sequence = 0; queue = Promise.resolve();
            security = {lease_id: event.detail.lease_id, lease_token: event.detail.lease_token};
            const begin = protectedPost(root.dataset.watchUrl, security).then(data => {
                if (turn !== generation) return;
                watch = data; resume = data.resume_seconds;
                status.textContent = Math.floor(data.percent) + '% watched · resume saved';
                timer = setInterval(() => { if (!document.hidden && !video.paused && !video.seeking && video.readyState >= 3) flush('playing'); }, 10000);
            }).catch(() => { if (turn === generation) status.textContent = 'Video progress is unavailable; playback can still continue.'; });
            event.detail.pending.push(begin);
        });
        video.addEventListener('loadedmetadata', () => {
            if (resume !== null && validWatchPosition(resume, video.duration)) { video.currentTime = resume; resume = null; }
        });
        video.addEventListener('playing', () => flush('playing'));
        video.addEventListener('pause', () => { flush('playing'); flush('anchor'); });
        video.addEventListener('waiting', () => { flush('playing'); flush('anchor'); });
        video.addEventListener('seeking', () => flush('anchor'));
        video.addEventListener('seeked', () => { if (!video.paused) flush('playing'); });
        root.addEventListener('lms:stopping', event => {
            clearInterval(timer);
            const turn = generation;
            const pending = flush('playing').then(() => { if (turn === generation) { watch = null; security = null; generation++; } });
            event.detail.pending.push(pending);
        });
    });
    document.querySelectorAll('[data-lms-assignment]').forEach(form => {
        const button = form.querySelector('[data-audio-record]'), status = form.querySelector('[data-audio-status]');
        if (!button || !status) return;
        const mime = typeof MediaRecorder !== 'undefined' ? recordingMime(MediaRecorder) : null;
        if (!mime || !navigator.mediaDevices?.getUserMedia || !window.isSecureContext) {
            button.disabled = true; status.textContent = 'Recording is unavailable here. Upload an audio file.'; return;
        }
        let recorder, stream, chunks = [], bytes = 0, limit;
        const stopTracks = () => { clearTimeout(limit); stream?.getTracks().forEach(track => track.stop()); stream = null; };
        button.addEventListener('click', async () => {
            if (recorder?.state === 'recording') { recorder.stop(); return; }
            try {
                stream = await navigator.mediaDevices.getUserMedia({audio: true});
                form.querySelector('[data-submission-file]').value = '';
                chunks = []; bytes = 0;
                recorder = new MediaRecorder(stream, {mimeType: mime});
                recorder.addEventListener('dataavailable', event => {
                    bytes += event.data.size;
                    if (bytes <= 25 * 1024 * 1024) chunks.push(event.data);
                    else { status.textContent = 'Recording exceeded 25 MB. Record a shorter response.'; if (recorder.state === 'recording') recorder.stop(); }
                });
                recorder.addEventListener('stop', () => {
                    stopTracks(); button.textContent = 'Record again';
                    if (bytes > 25 * 1024 * 1024 || !bytes) return;
                    const transfer = new DataTransfer();
                    transfer.items.add(new File(chunks, 'response.' + (mime === 'audio/ogg' ? 'ogg' : 'webm'), {type: mime}));
                    form.querySelector('[data-submission-file]').files = transfer.files;
                    form.querySelector('[data-submission-kind]').value = 'audio';
                    form.querySelector('[data-submission-kind]').dispatchEvent(new Event('change', {bubbles: true}));
                    status.textContent = 'Recording ready. Select Submit to save it privately.';
                });
                recorder.addEventListener('error', () => { stopTracks(); status.textContent = 'Recording failed. Upload an audio file.'; });
                recorder.start(1000); button.textContent = 'Stop recording'; status.textContent = 'Recording…';
                limit = setTimeout(() => { if (recorder.state === 'recording') recorder.stop(); }, 10 * 60 * 1000);
            } catch { stopTracks(); status.textContent = 'Microphone permission is unavailable. Upload an audio file.'; }
        });
        window.addEventListener('pagehide', () => { if (recorder?.state === 'recording') recorder.stop(); stopTracks(); });
    });
}
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeLearningProgress, {once: true}); else initializeLearningProgress();
}
