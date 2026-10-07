@extends('layouts.student', ['title' => 'Authorized video browsers'])
@section('content')
<div class="space-y-6">
    <a href="{{ route('student.learning.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">← My Learning</a>
    <h1 class="text-3xl font-bold">Authorized video browsers</h1>
    @include('student.learning.feedback')
    <p class="max-w-2xl text-sm leading-6 text-stone-600">A browser registration helps protect your account. Clearing cookies creates a new browser identity. It does not prove a physical device. Revoking a browser stops new video authorizations; issued links can remain valid for their short lifetime.</p>
    <form method="POST" action="{{ route('student.video.devices.register') }}">@csrf<button class="min-h-11 rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">Register this browser</button></form>
    <div class="space-y-4">@forelse($devices as $device)
        <article class="space-y-3 rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase text-stone-500">{{ $device->status }} · Last used {{ $device->last_seen_at->copy()->timezone($timezone)->format('j M Y, H:i T') }}</p>
            <form method="POST" action="{{ route('student.video.devices.update', $device) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PATCH')<label class="min-w-0 flex-1 text-sm font-medium">Browser label<input name="label" value="{{ $device->label }}" maxlength="80" required class="mt-2 block min-h-11 w-full rounded-lg border border-stone-300 px-3"></label><button class="min-h-11 rounded-xl border border-stone-300 px-4 text-sm font-semibold">Save label</button></form>
            @if($device->status === 'authorized')<form method="POST" action="{{ route('student.video.devices.revoke', $device) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-800">Revoke browser</button></form>@endif
        </article>
    @empty<p class="text-sm text-stone-500">No video browsers registered yet.</p>@endforelse</div>
</div>
@endsection
