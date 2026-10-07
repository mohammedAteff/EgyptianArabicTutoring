<form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="flex shrink-0 gap-1" aria-label="Reorder {{ $label }}">
    @csrf
    <input type="hidden" name="version" value="{{ $course->lock_version }}">
    <input type="hidden" name="operation" value="{{ $operation }}">
    <input type="hidden" name="key" value="{{ $key }}">
    <x-lms.button tone="neutral" name="direction" value="up" :disabled="$index === 0" aria-label="Move {{ $label }} up">↑</x-lms.button>
    <x-lms.button tone="neutral" name="direction" value="down" :disabled="$index === $count-1" aria-label="Move {{ $label }} down">↓</x-lms.button>
</form>
