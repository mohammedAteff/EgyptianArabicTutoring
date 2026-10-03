@extends('layouts.admin')
@section('content')
<div class="space-y-6">
    <div><h1 class="text-3xl font-bold text-slate-900">Meeting Links</h1><p class="mt-2 text-slate-500">Manage your external meeting rooms and assign one room to each lesson.</p></div>
    <form method="POST" action="{{ route('admin.meeting-links.settings') }}" class="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-6">
        @csrf
        <label class="text-sm font-semibold">Reveal link to students (minutes before lesson)<input name="reveal_minutes" type="number" min="0" max="1440" required value="{{ $revealMinutes }}" class="mt-2 block rounded-xl border border-slate-300 p-3"></label>
        <button class="rounded-xl bg-amber-600 px-5 py-3 font-bold text-white">Save reveal time</button>
        <p class="w-full text-sm text-slate-500">Telegram countdown lead times are set independently in Telegram Bots → Alert rules.</p>
    </form>
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="mb-4 text-xl font-semibold">Providers</h2>
        @foreach($providers->push(new \App\Domains\Booking\Models\MeetingProvider(['active' => true, 'sort_order' => $providers->count()])) as $provider)
            <form method="POST" action="{{ route('admin.meeting-links.providers') }}" class="mb-4 flex flex-wrap items-end gap-3 border-b border-slate-100 pb-4">
                @csrf @if($provider->id)<input type="hidden" name="id" value="{{ $provider->id }}">@endif
                <label class="flex-1 text-sm">Provider name<input name="name" value="{{ $provider->name }}" required maxlength="80" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm">Icon (optional)<input name="icon" value="{{ $provider->icon }}" maxlength="20" class="mt-1 block w-24 rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm">Order<input name="sort_order" type="number" min="0" max="10000" value="{{ $provider->sort_order }}" required class="mt-1 block w-24 rounded-xl border border-slate-300 p-3"></label>
                <input type="hidden" name="active" value="0"><label class="pb-3 text-sm"><input name="active" type="checkbox" value="1" @checked($provider->active)> Enabled</label>
                <input type="hidden" name="is_default" value="0"><label class="pb-3 text-sm"><input name="is_default" type="checkbox" value="1" @checked($provider->is_default)> Default</label>
                <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">{{ $provider->id ? 'Save provider' : 'Add provider' }}</button>
            </form>
        @endforeach
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="mb-4 text-xl font-semibold">Room pool</h2>
        @foreach($rooms->push(new \App\Domains\Booking\Models\MeetingRoom(['active' => true])) as $room)
            <form method="POST" action="{{ route('admin.meeting-links.rooms') }}" class="mb-5 grid gap-3 border-b border-slate-100 pb-5 md:grid-cols-2">
                @csrf @if($room->id)<input type="hidden" name="id" value="{{ $room->id }}">@endif
                <label class="text-sm">Room name<input name="name" value="{{ $room->name }}" required maxlength="160" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm">Provider<select name="meeting_provider_id" required class="mt-1 w-full rounded-xl border border-slate-300 p-3">@foreach($providers->filter(fn ($item) => $item->id) as $provider)<option value="{{ $provider->id }}" @selected($room->meeting_provider_id === $provider->id)>{{ $provider->icon }} {{ $provider->name }}{{ $provider->active ? '' : ' (disabled)' }}</option>@endforeach</select></label>
                <label class="text-sm md:col-span-2">HTTPS meeting URL<input name="url" type="url" value="{{ $room->url }}" required maxlength="2000" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
                <label class="text-sm">Staff note<textarea name="notes" rows="2" maxlength="4000" class="mt-1 w-full rounded-xl border border-slate-300 p-3">{{ $room->notes }}</textarea></label>
                <div class="flex items-end justify-between gap-3"><input type="hidden" name="active" value="0"><label class="pb-3 text-sm"><input name="active" type="checkbox" value="1" @checked($room->active)> Enabled</label><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">{{ $room->id ? 'Save room' : 'Add room' }}</button></div>
            </form>
        @endforeach
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="mb-4 text-xl font-semibold">Upcoming assignments</h2><form method="POST" action="{{ route('admin.meeting-links.reconcile') }}" class="mb-4">@csrf<button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Assign unassigned future lessons</button></form><p class="mb-4 text-sm text-slate-500">Rooms are assigned automatically from the resolved provider. Consecutive lessons use different rooms. Use the selector for a manual override.</p>
        @forelse($bookings as $booking)
            <form method="POST" action="{{ route('admin.meeting-links.assign', $booking) }}" class="flex flex-wrap items-center gap-4 border-t border-slate-100 py-4">
                @csrf
                <div class="flex-1"><p class="font-semibold">#{{ $booking->id }} · {{ $booking->student?->name ?? $booking->contact?->name }} · {{ $booking->sessionType?->title }}</p><p class="mt-1 text-sm text-slate-500">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($booking->start_at_utc) }} · {{ $booking->meeting_provider_snapshot ?? 'Meeting room assignment needed' }}</p></div>
                <label class="text-sm">Room<select name="meeting_room_id" required class="ml-2 rounded-xl border border-slate-300 p-3"><option value="">Choose room</option>@foreach($rooms->filter(fn ($item) => $item->id && $item->active && $item->provider?->active) as $room)<option value="{{ $room->id }}" @selected($booking->meeting_room_id === $room->id)>{{ $room->provider->name }} · {{ $room->name }}</option>@endforeach</select></label>
                <button class="rounded-xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white">Assign room</button>
            </form>
        @empty <p class="py-6 text-slate-500">No upcoming confirmed lessons.</p> @endforelse
    </section>
</div>
@endsection
