export function bookmarkSeconds(media) {
    const duration = Number(media.duration);
    const current = Number(media.currentTime);
    if (!Number.isFinite(duration) || duration <= 0 || !Number.isFinite(current) || current < 0 || current > duration || current > 86400) return null;
    return Math.round(current * 1000) / 1000;
}

export function seekBookmark(media, milliseconds) {
    const seconds = Number(milliseconds) / 1000;
    const duration = Number(media.duration);
    if (!Number.isFinite(seconds) || seconds < 0 || seconds > 86400 || !Number.isFinite(duration) || duration <= 0 || seconds > duration) return false;
    media.currentTime = seconds;
    return true;
}

function initialize() {
    document.querySelectorAll('[data-lms-curriculum]').forEach(outline => {
        outline.open = window.matchMedia('(min-width: 1024px)').matches;
    });
    document.querySelectorAll('[data-lms-player]').forEach(player => {
        const videos = new Map();
        player.querySelectorAll('[data-lms-video]').forEach(video => videos.set(video.dataset.lmsVideo, video));
        player.querySelectorAll('[data-lms-bookmark-form]').forEach(form => {
            const video = videos.get(form.dataset.lmsBookmarkForm);
            const button = form.querySelector('[data-lms-save-bookmark]');
            const status = form.querySelector('[data-lms-video-status]');
            if (!video || !button || !status) return;
            const update = () => {
                button.disabled = bookmarkSeconds(video) === null;
                status.textContent = button.disabled ? 'A timestamp needs a playable video with a known duration.' : 'Save the current video time to your private bookmarks.';
            };
            ['loadedmetadata', 'durationchange', 'timeupdate'].forEach(event => video.addEventListener(event, update));
            video.addEventListener('error', () => {
                button.disabled = true;
                status.textContent = 'This video could not be loaded. Try again later.';
            });
            button.addEventListener('click', () => {
                const seconds = bookmarkSeconds(video);
                if (seconds === null) { update(); return; }
                form.elements.namedItem('seconds').value = String(seconds);
                form.requestSubmit();
            });
            update();
        });
        player.querySelectorAll('[data-lms-jump-block]').forEach(button => button.addEventListener('click', () => {
            const video = videos.get(button.dataset.lmsJumpBlock);
            const status = player.querySelector('[data-lms-jump-status]');
            if (!video || !status) return;
            const seek = () => {
                try {
                    if (!seekBookmark(video, button.dataset.lmsJumpPosition)) {
                        status.textContent = 'This saved timestamp is unavailable in the current video.';
                        return;
                    }
                    video.scrollIntoView({block: 'center'});
                    video.focus();
                    status.textContent = 'Video moved to your saved moment.';
                } catch {
                    status.textContent = 'This video cannot seek to the saved moment yet.';
                }
            };
            if (video.readyState >= 1) seek();
            else {
                status.textContent = 'Waiting for the video to load…';
                video.addEventListener('loadedmetadata', seek, {once: true});
            }
        }));
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
}
