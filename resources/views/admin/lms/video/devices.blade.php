@extends('layouts.admin')
@section('content')
<div class="space-y-6">
<h1 class="font-serif text-2xl font-bold">Authorized video browsers</h1>
<p class="text-sm text-slate-600">{{ $devices->count() }} retained browser registrations · {{ $leases->count() }} active or briefly valid playback authorizations. Login sessions are managed separately.</p>
@forelse($devices as $device)<article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-semibold" dir="auto">{{ $device->label }}</p><p class="mt-2 text-sm text-slate-500">{{ $device->status }} · Last used {{ $device->last_seen_at->format('j M Y, H:i') }} UTC</p>@if($device->status==='authorized')<form method="POST" action="{{ route('admin.lms.video.devices.revoke', [$student, $device]) }}">@csrf @method('DELETE')<x-lms.button tone="danger">Revoke browser</x-lms.button></form>@endif</article>@empty<p class="text-sm text-slate-500">No video browser registrations.</p>@endforelse
<p class="text-xs text-slate-500">Revocation stops renewal. Issued CDN links remain valid until their short expiry. No IP addresses, browser fingerprints, private notes or playback progress appear here.</p>
</div>
@endsection
