@extends('layouts.student', ['title' => 'Authorized Browsers'])
@section('content')
<div class="space-y-6">
    <a href="{{ route('student.learning.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">← My Learning</a>
    <h1 class="text-3xl font-bold">Authorized Browsers</h1>
    @include('student.learning.feedback')
    <p class="max-w-2xl text-sm leading-6 text-stone-600">Manage the browsers authorized to play protected learning videos. Removing access stops new playback in that browser; videos already open may keep playing briefly. It doesn't sign you out of the Student Portal.</p>
    <form method="POST" action="{{ route('student.video.devices.register') }}">@csrf<button class="min-h-11 rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">Authorize This Browser</button></form>
    <div class="space-y-4">@forelse($devices as $device)
        <article class="space-y-3 rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase text-stone-500">{{ $device->status === 'authorized' ? 'Authorized' : 'Access removed' }} · Last used: {{ $device->last_seen_at?->copy()->timezone($timezone)->format('j M Y, g:i A T') ?? 'Not used yet' }}</p>
            <form method="POST" action="{{ route('student.video.devices.update', $device) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PATCH')<label class="min-w-0 flex-1 text-sm font-medium">Browser label<input name="label" value="{{ $device->label }}" maxlength="80" required class="mt-2 block min-h-11 w-full rounded-lg border border-stone-300 px-3"></label><button class="min-h-11 rounded-xl border border-stone-300 px-4 text-sm font-semibold">Save label</button></form>
            @if($device->status === 'authorized')<form method="POST" action="{{ route('student.video.devices.revoke', $device) }}" onsubmit="return confirm('Remove video access for this browser? This stops new protected-video playback. Your Student Portal sign-in stays active.')">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-800">Remove Access</button></form>@endif
        </article>
    @empty<p class="text-sm text-stone-500">No Authorized Browsers Yet. Authorize this browser when you need access to protected learning videos.</p>@endforelse</div>
</div>
@endsection
