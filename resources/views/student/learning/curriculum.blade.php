<details open data-lms-curriculum class="rounded-2xl border border-stone-200 bg-white lg:sticky lg:top-6">
    <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-3 p-4 font-semibold text-nile-900">Course curriculum <span aria-hidden="true" class="text-xs text-stone-500">Show / hide</span></summary>
    <nav aria-label="Course curriculum" class="space-y-5 border-t border-stone-100 p-4">
        @forelse($sections as $section)
            <div class="space-y-2"><h2 dir="auto" class="break-words text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $section->title }}</h2><ol class="space-y-1">
                @forelse($section->lessons as $item)
                    <li><a href="{{ route('student.learning.lessons.show', [$course, $item]) }}" @if(isset($lesson) && $lesson->id === $item->id) aria-current="page" @endif class="block min-h-11 rounded-lg px-3 py-3 text-sm leading-relaxed hover:bg-nile-50 {{ isset($lesson) && $lesson->id === $item->id ? 'bg-nile-50 font-semibold text-nile-900' : 'text-stone-700' }}"><span dir="auto" class="block break-words">{{ $item->title }}</span></a></li>
                @empty<li class="py-2 text-xs text-stone-500">No available lessons in this module.</li>@endforelse
            </ol></div>
        @empty<p class="text-sm text-stone-500">No lessons are available yet.</p>@endforelse
    </nav>
</details>
