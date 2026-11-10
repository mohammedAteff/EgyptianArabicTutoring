@extends('layouts.student', ['title' => 'Notifications'])
@section('content')
<h1 class="text-3xl font-semibold text-nile-900">{{ __('Notifications') }}</h1>
<p class="mt-2 text-stone-600">{{ __('Updates from your lessons and learning area.') }}</p>
@if(session('success'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-green-800">{{ __(session('success')) }}</p>@endif
<div class="mt-6 space-y-4">
@forelse($notifications as $notification)
<article class="rounded-2xl border bg-white p-5 {{ $notification->read_at ? 'border-stone-200' : 'border-nile-300' }}">
    <h2 class="font-semibold text-nile-900">{{ __($notification->title) }} <span class="ml-2 text-xs text-nile-600">{{ __($notification->read_at ? 'Notification read' : 'Notification unread') }}</span></h2>
    <p class="mt-2 break-words text-sm text-stone-600">{{ $messages[$notification->id] }}</p>
    <p class="mt-2 text-xs text-stone-500">{{ localized_date($notification->created_at->copy()->timezone($timezone ?? config('app.timezone', 'UTC')), 'j M Y, g:i A T') }}</p>
    <div class="mt-3 flex flex-wrap items-center gap-4"><a href="{{ url($destinations[$notification->id] ?? $notification->link) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">{{ __($notification->type === 'lesson' ? 'View Session' : 'View Update') }}</a>
    @if(!$notification->read_at)<form method="POST" action="{{ route('student.notifications.update', $notification->id) }}">@csrf @method('PATCH')<button class="min-h-11 rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold">{{ __('Mark as read') }}</button></form>@endif</div>
</article>
@empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-stone-500">{{ __('You’re all caught up.') }}</p>@endforelse
</div>
<div class="mt-6">{{ $notifications->links() }}</div>
@endsection
