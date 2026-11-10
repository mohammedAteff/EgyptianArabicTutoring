@extends('layouts.student', ['title' => 'Authorized Browsers'])
@section('content')
<div class="space-y-6">
    <a href="{{ route('student.learning.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">{{ __('← My Learning') }}</a>
    <h1 class="text-3xl font-bold">{{ __('Authorized Browsers') }}</h1>
    @include('student.learning.feedback')
    <div class="student-surface student-surface--white space-y-4">
        <p class="max-w-2xl text-sm leading-6 text-stone-600">{{ __('Manage the browsers authorized to play protected learning videos. Removing access stops new playback in that browser; videos already open may keep playing briefly. It doesn\'t sign you out of the Student Portal.') }}</p>
        <form method="POST" action="{{ route('student.video.devices.register') }}">@csrf<button class="student-button student-button-primary">{{ __('Authorize This Browser') }}</button></form>
    </div>
    <div class="space-y-4">@forelse($devices as $device)
        <article class="student-surface student-surface--white space-y-3">
            <p class="text-xs font-semibold uppercase text-stone-500">{{ __($device->status === 'authorized' ? 'Authorized' : 'Access removed') }} {{ __('· Last used:') }} {{ __($device->last_seen_at?->copy()->timezone($timezone)->locale(app()->getLocale())->translatedFormat(localized_date_format('j M Y, g:i A T')) ?? 'Not used yet') }}</p>
            <form method="POST" action="{{ route('student.video.devices.update', $device) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PATCH')<label class="student-device-label text-sm font-medium">{{ __('Browser label') }}<input name="label" value="{{ $device->label }}" maxlength="80" required class="mt-2 block min-h-11 w-full rounded-lg border border-stone-300 px-3"></label><button class="student-button student-button-secondary">{{ __('Save label') }}</button></form>
            @if($device->status === 'authorized')<form method="POST" action="{{ route('student.video.devices.revoke', $device) }}" onsubmit="return confirm(@js(__('Remove video access for this browser? This stops new protected-video playback. Your Student Portal sign-in stays active.')))">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-800">{{ __('Remove Access') }}</button></form>@endif
        </article>
    @empty<p class="student-surface student-surface--white text-sm text-stone-500">{{ __('No Authorized Browsers Yet. Authorize this browser when you need access to protected learning videos.') }}</p>@endforelse</div>
</div>
@endsection
