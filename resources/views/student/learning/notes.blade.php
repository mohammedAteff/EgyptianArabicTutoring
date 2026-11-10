@extends('layouts.student', ['title' => 'Private Lesson Notes'])
@section('content')
<div class="space-y-6">
    <a href="{{ route('student.learning.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">{{ __('← My Learning') }}</a>
    <header class="space-y-2"><h1 class="text-3xl font-bold tracking-tight">{{ __('Private Lesson Notes') }}</h1><p class="text-sm text-stone-600">{{ __('Your notes remain yours when course access ends. You can read or delete them here.') }}</p></header>
    @include('student.learning.feedback')
    @forelse($notes as $note)
        <article class="space-y-3 rounded-2xl border border-stone-200 bg-white p-6"><p dir="auto" class="whitespace-pre-wrap break-words text-sm leading-7">{{ $note->body }}</p><p class="text-xs text-stone-500">{{ __('Updated') }} {{ localized_date($note->updated_at->copy()->timezone($timezone), 'j M Y, g:i A T') }}</p><form method="POST" action="{{ route('student.learning.notes.destroy', $note) }}">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $note->lock_version }}"><button class="min-h-11 text-sm font-semibold text-stone-600">{{ __('Delete Note') }}</button></form></article>
    @empty<p class="rounded-2xl border border-dashed border-stone-300 p-6 text-sm text-stone-600">{{ __('No Private Lesson Notes Yet. Add a note from an available lesson to find it here.') }}</p>@endforelse
    {{ $notes->links() }}
</div>
@endsection
