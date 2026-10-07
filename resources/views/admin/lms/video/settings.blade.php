@extends('layouts.admin')
@section('content')
<div class="space-y-6">
    <a href="{{ route('admin.lms.courses.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-600">← Course Studio</a>
    <h1 class="font-serif text-2xl font-bold">Protected video settings</h1>
    <p class="max-w-3xl text-sm leading-6 text-slate-600">Super Admin controls only. Credentials stay encrypted on the server and are never shown again. Blank credential fields preserve existing values. Playback checks the actual provider settings every time it authorizes or renews.</p>
    <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 sm:p-6">
        <h2 class="text-lg font-bold">Bunny Stream library</h2>
        <form method="POST" action="{{ route('admin.lms.video.settings.update') }}" class="grid gap-4 md:grid-cols-2">
            @csrf<input type="hidden" name="version" value="{{ $connection?->lock_version??0 }}">
            <x-lms.input label="Library ID" name="library_id" type="number" min="1" :value="$connection?->library_id" :required="true"/>
            <x-lms.input label="Bunny CDN hostname" name="cdn_hostname" :value="$connection?->cdn_hostname" placeholder="your-library.b-cdn.net" :required="true"/>
            <div class="md:col-span-2"><x-lms.input label="Allowed website domains (comma separated)" name="domains" :value="implode(', ', $connection?->allowed_domains??[])" :required="true" hint="Exact hostnames only. Include this application's domain. No wildcard or URL path."/></div>
            @foreach(['api_key'=>'Library upload API key','read_only_key'=>'Library read-only key / webhook signing key','signing_key'=>'CDN token security key','account_key'=>'Account API key for protection verification'] as $field=>$label)<label class="text-sm font-semibold text-slate-700">{{ $label }}<input type="password" name="{{ $field }}" autocomplete="new-password" minlength="16" maxlength="1000" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3" placeholder="{{ $connection?->{$field} ? 'Configured — leave blank to keep' : 'Not configured' }}"></label>@endforeach
            <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($connection?->enabled)>Enable protected video</label>
            <div><x-lms.button>Save provider settings</x-lms.button></div>
        </form>
        <form method="POST" action="{{ route('admin.lms.video.settings.verify') }}">@csrf<x-lms.button tone="neutral">Verify remote protection settings</x-lms.button></form>
        <p class="text-xs text-slate-500">{{ $connection?->last_verified_at ? 'Last successful configuration check: '.$connection->last_verified_at->format('j M Y, H:i').' UTC' : 'No successful live configuration check recorded.' }}</p>
        <p class="text-xs leading-6 text-slate-500">Required remote controls: embed authentication, linked CDN token authentication without IP binding, HTTPS enforced on the configured CDN hostname, approved domains with empty referrers blocked, original/MP4 fallback/direct/early playback disabled, HLS CORS enabled, no edge rules or scripts bypassing checks. Current player requires DRM disabled in Bunny. Webhook URL: {{ route('bunny-stream.webhook') }}</p>
    </section>
    <section class="space-y-4"><h2 class="text-lg font-bold">Protection profiles</h2><p class="text-sm text-slate-600">Lesson override → course profile → Private for private courses, Member otherwise. These settings do not grant course access. All protected playback in this Portal requires a current Student login, including the Public profile.</p>
    @foreach($profiles as $profile)<form method="POST" action="{{ route('admin.lms.video.profiles.update',$profile) }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $profile->lock_version }}"><h3 class="font-bold">{{ $profile->name }}</h3><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach(['device_limit'=>'Authorized browsers','stream_limit'=>'Active videos','token_seconds'=>'Video link lifetime (seconds)','heartbeat_seconds'=>'Renewal interval (seconds)','lease_seconds'=>'Abandoned session expiry (seconds)'] as $field=>$label)<x-lms.input :label="$label" :name="$field" type="number" :value="$profile->{$field}" :id="'profile-'.$profile->id.'-'.$field" min="1"/>@endforeach
        <label class="text-sm font-semibold">DRM requirement<select name="drm_mode" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">@foreach(['none'=>'None','optional'=>'Optional / future ready','required'=>'Required (playback unavailable)'] as $value=>$label)<option value="{{ $value }}" @selected($profile->drm_mode===$value)>{{ $label }}</option>@endforeach</select></label>
        @foreach(['watermark'=>'Personalized moving watermark','active'=>'Profile active'] as $field=>$label)<label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked($profile->{$field})>{{ $label }}</label>@endforeach
    </div><x-lms.button tone="neutral">Save {{ $profile->name }} profile</x-lms.button></form>@endforeach
    <p class="text-xs leading-6 text-slate-500">Private and Premium require device/stream limits and watermarking. The watermark is a removable browser overlay and a deterrent to casual sharing; it cannot prevent screen capture. Paid DRM is not configured or verified in V1. Required DRM fails closed. Issued CDN links cannot be instantly recalled; revocation stops renewal and waits for their expiry.</p>
    </section>
</div>
@endsection
