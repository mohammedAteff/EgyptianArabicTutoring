@extends('layouts.student', ['title' => $lesson->title])
@section('content')
<div data-lms-player class="space-y-6">
    <nav aria-label="Learning breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-nile-800"><a class="inline-flex min-h-11 items-center font-semibold" href="{{ route('student.learning.index') }}">My Learning</a><span aria-hidden="true">/</span><a dir="auto" class="inline-flex min-h-11 items-center break-words" href="{{ route('student.learning.courses.show', $course) }}">{{ $course->title }}</a></nav>
    @include('student.learning.feedback')
    <div class="grid items-start gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        @include('student.learning.curriculum')
        <div class="min-w-0 space-y-6">
            <header class="space-y-2"><p class="text-xs font-semibold uppercase tracking-widest text-nile-700">Lesson</p><h1 dir="auto" class="break-words text-3xl font-bold tracking-tight">{{ $lesson->title }}</h1></header>
            <div class="space-y-5">
                @forelse($blocks as $block)
                    <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                        @switch($block['kind'])
                            @case('video')
                                @include('student.learning.protected-video')
                                @break
                            @case('rich_text')
                                <div dir="auto" class="cms-rich-text break-words">{!! $block['payload']['html'] !!}</div>
                                @break
                            @case('image')
                                <img src="{{ $block['url'] }}" alt="{{ $block['payload']['alt'] }}" loading="lazy" class="mx-auto h-auto max-w-full rounded-xl">
                                @break
                            @case('file')
                            @case('resource')
                                <p dir="auto" class="mb-3 break-words font-semibold">{{ $block['resourceTitle'] ?? ($block['payload']['label'] ?? 'Lesson attachment') }}</p><a href="{{ $block['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-nile-100 px-4 text-sm font-semibold text-nile-900">{{ $block['kind'] === 'file' ? 'Open / download file' : 'Open Resource' }} ↗</a>
                                @break
                            @case('external_link')
                                <p class="mb-3 text-sm text-stone-600">Continue with this lesson link.</p><a href="{{ $block['payload']['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-nile-100 px-4 text-sm font-semibold text-nile-900">Open lesson link ↗</a>
                                @break
                            @case('youtube_video')
                            @case('external_video')
                                @if(($block['payload']['provider'] ?? null) === 'direct')
                                    <video id="learning-video-{{ $block['id'] }}" data-lms-video="{{ $block['id'] }}" src="{{ $block['payload']['url'] }}" controls playsinline preload="metadata" class="aspect-video w-full rounded-xl bg-stone-950"><a href="{{ $block['payload']['url'] }}" rel="noopener noreferrer">Open video</a></video>
                                    <form data-lms-bookmark-form="{{ $block['id'] }}" method="POST" action="{{ route('student.learning.bookmarks.store', [$course, $lesson]) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                        @csrf<input type="hidden" name="block_id" value="{{ $block['id'] }}"><input type="hidden" name="seconds" value="">
                                        <label class="min-w-0 flex-1 text-sm font-medium">Bookmark label <span class="font-normal text-stone-500">(optional)</span><input dir="auto" name="label" maxlength="500" class="mt-2 block min-h-11 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm" placeholder="A word, phrase or useful moment"></label>
                                        <button type="button" data-lms-save-bookmark disabled class="min-h-11 rounded-xl bg-nile-800 px-4 text-sm font-semibold text-white disabled:opacity-50">Bookmark this moment</button>
                                        <p data-lms-video-status role="status" class="w-full text-xs text-stone-500">Bookmarks become available when the video duration is known.</p>
                                        <noscript><p class="text-xs text-stone-600">Enable JavaScript to bookmark video timestamps.</p></noscript>
                                    </form>
                                @else
                                    <iframe src="{{ $block['payload']['embed_url'] }}" title="{{ $lesson->title }} — video" class="aspect-video w-full rounded-xl border-0" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin"></iframe>
                                    <p class="mt-3 text-xs text-stone-500">Timestamp bookmarks are available for direct videos.</p>
                                @endif
                                @break
                            @default
                                <p class="text-sm text-stone-600">This content is currently unavailable.</p>
                        @endswitch
                    </section>
                @empty<p class="rounded-2xl border border-dashed border-stone-300 p-6 text-sm text-stone-600">No lesson content is available yet.</p>@endforelse
                @if($hasBunnyPlaceholder)<p role="status" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">Protected video playback is not available yet. No public video link is provided.</p>@endif
            </div>
            <nav aria-label="Lesson navigation" class="grid grid-cols-2 gap-3">
                @if($previousLesson)<a href="{{ route('student.learning.lessons.show', [$course, $previousLesson]) }}" class="min-h-11 rounded-xl border border-stone-300 bg-white p-4 text-sm text-nile-900"><span class="block text-xs text-stone-500">← Previous lesson</span><span dir="auto" class="mt-1 block break-words font-semibold">{{ $previousLesson->title }}</span></a>@else<div></div>@endif
                @if($nextLesson)<a href="{{ route('student.learning.lessons.show', [$course, $nextLesson]) }}" class="min-h-11 rounded-xl border border-nile-100 bg-nile-50 p-4 text-sm text-nile-900"><span class="block text-xs text-stone-500">Next lesson →</span><span dir="auto" class="mt-1 block break-words font-semibold">{{ $nextLesson->title }}</span></a>@endif
            </nav>
            <section aria-labelledby="video-bookmarks" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <h2 id="video-bookmarks" class="text-lg font-semibold">Your video bookmarks</h2>
                @forelse($bookmarks as $bookmark)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-3">
                        <button type="button" data-lms-jump-block="{{ $bookmark->block_id }}" data-lms-jump-position="{{ $bookmark->position_milliseconds }}" class="min-h-11 min-w-0 flex-1 text-start text-sm text-nile-900"><span class="font-semibold">{{ $bookmark->timestampLabel() }}</span><span dir="auto" class="ms-2 break-words">{{ $bookmark->label ?? 'Saved moment' }}</span></button>
                        <form method="POST" action="{{ route('student.learning.bookmarks.destroy', [$course, $lesson, $bookmark]) }}">@csrf @method('DELETE')<button class="min-h-11 px-3 text-xs font-semibold text-stone-600">Delete bookmark</button></form>
                    </div>
                @empty<p class="text-sm text-stone-500">Save a moment from a supported video to return to it here.</p>@endforelse
                <p data-lms-jump-status role="status" class="text-xs text-stone-500"></p>
            </section>
            <section aria-labelledby="private-lesson-notes" class="space-y-5 rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <div class="space-y-1"><h2 id="private-lesson-notes" class="text-lg font-semibold">Your private notes</h2><p class="text-sm text-stone-500">Visible only to you.</p></div>
                <form method="POST" action="{{ route('student.learning.notes.store', [$course, $lesson]) }}" class="space-y-3">
                    @csrf<label for="new-lesson-note" class="text-sm font-medium">Add a note</label><textarea id="new-lesson-note" dir="auto" name="body" rows="4" maxlength="10000" required class="block w-full rounded-xl border border-stone-300 p-3 text-sm leading-relaxed">{{ old('body') }}</textarea><button class="min-h-11 rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">Save private note</button>
                </form>
                @foreach($notes as $note)
                    <article class="space-y-3 border-t border-stone-100 pt-5">
                        <p dir="auto" class="whitespace-pre-wrap break-words text-sm leading-7">{{ $note->body }}</p><p class="text-xs text-stone-500">Updated {{ $note->updated_at->copy()->timezone($timezone)->format('j M Y, H:i T') }}</p>
                        <details><summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold text-nile-800">Edit note</summary><form method="POST" action="{{ route('student.learning.notes.update', [$course, $lesson, $note]) }}" class="space-y-3">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $note->lock_version }}"><label class="sr-only" for="edit-note-{{ $note->id }}">Edit private note</label><textarea id="edit-note-{{ $note->id }}" dir="auto" name="body" rows="4" maxlength="10000" required class="block w-full rounded-xl border border-stone-300 p-3 text-sm">{{ $note->body }}</textarea><button class="min-h-11 rounded-xl border border-nile-100 px-4 text-sm font-semibold text-nile-900">Save changes</button></form></details>
                        <form method="POST" action="{{ route('student.learning.notes.destroy', $note) }}">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $note->lock_version }}"><button class="min-h-11 text-xs font-semibold text-stone-600">Delete private note</button></form>
                    </article>
                @endforeach
            </section>
            <p class="text-xs text-stone-500">@if($accessEndsAt)Current access available until {{ $accessEndsAt->timezone($timezone)->format('j M Y, H:i T') }}.@endif Opening lessons does not record completion.</p>
        </div>
    </div>
</div>
@endsection
