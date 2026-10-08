<div data-protected-player data-authorize-url="{{ $block['authorizationUrl'] }}" data-watch-url="{{ route('student.evidence.watch.begin', [$course,$lesson,$block['id']]) }}" class="space-y-3">
    <h2 dir="auto" class="font-semibold">{{ $block['label'] }}</h2>
    <p data-watch-status role="status" class="text-xs text-stone-500">Watch progress is saved while this video plays.</p>
    <div data-video-container class="relative overflow-hidden rounded-xl bg-stone-950">
        <video data-lms-video="{{ $block['id'] }}" playsinline preload="none" disablepictureinpicture disableremoteplayback controlslist="nodownload noremoteplayback nofullscreen" class="aspect-video w-full"></video>
        <span data-video-watermark hidden aria-hidden="true" class="pointer-events-none absolute z-10 max-w-[85%] rounded bg-black/30 px-2 py-1 text-xs font-semibold text-white/80"></span>
        <div class="flex flex-wrap items-center gap-3 bg-stone-900 p-3 text-sm text-white">
            <button data-video-play type="button" class="min-h-11 rounded-lg bg-white/15 px-4 font-semibold">Play video</button>
            <label class="flex min-w-0 flex-1 items-center gap-2"><span class="sr-only">Video position</span><input data-video-seek type="range" min="0" max="0" value="0" step="1" aria-label="Video position" class="min-h-11 min-w-0 flex-1" disabled></label>
            <span data-video-time class="text-xs tabular-nums">0:00</span>
            <button data-video-mute type="button" class="min-h-11 px-3">Mute</button>
            <button data-video-fullscreen type="button" class="min-h-11 px-3">Fullscreen</button>
            <button data-video-close type="button" class="min-h-11 px-3">Close player</button>
        </div>
    </div>
    <p data-protected-status role="status" class="text-sm text-stone-600">Select Play video to check your access and start a protected session.</p>
    <p class="text-xs text-stone-500">Video links expire shortly. <a class="font-semibold text-nile-800 underline" href="{{ route('student.video.devices') }}">Manage authorized browsers</a></p>
    <form data-lms-bookmark-form="{{ $block['id'] }}" method="POST" action="{{ route('student.learning.bookmarks.store', [$course, $lesson]) }}" class="flex flex-wrap items-end gap-3">
        @csrf<input type="hidden" name="block_id" value="{{ $block['id'] }}"><input type="hidden" name="seconds" value="">
        <label class="min-w-0 flex-1 basis-full text-sm font-medium sm:basis-auto">Bookmark label (optional)<input dir="auto" name="label" maxlength="500" class="mt-2 block min-h-11 w-full rounded-lg border border-stone-300 px-3 text-sm"></label>
        <button type="button" data-lms-save-bookmark disabled class="min-h-11 rounded-xl bg-nile-800 px-4 text-sm font-semibold text-white disabled:opacity-50">Bookmark this moment</button>
        <p data-lms-video-status role="status" class="w-full text-xs text-stone-500">Bookmarks become available when the video duration is known.</p>
    </form>
    <noscript><p class="text-sm text-stone-600">JavaScript is required for protected playback.</p></noscript>
</div>
