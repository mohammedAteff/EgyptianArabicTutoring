<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="analytics-event-url" content="{{ route('analytics.track') }}">
    <meta name="analytics-disabled" content="{{ app(\App\Domains\Analytics\Services\AnalyticsService::class)->excluded(request()) ? '1' : '0' }}">
    @livewireStyles
    <title>{{ $title ?? 'Student Portal' }} — Arabic with Abdallah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[#FAF8F5] text-stone-900 antialiased">
    <x-announcement-banner :announcement="$siteAnnouncement" />
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight text-nile-900">Arabic with Abdallah</a>
            @if(empty($isPreview) && $studentPortalAccount)
                <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold text-nile-800 [&_a]:inline-flex [&_a]:min-h-11 [&_a]:items-center" aria-label="Student navigation">
                    <a href="{{ route('student.dashboard') }}">Sessions</a>
                    <a href="{{ route('student.learning.index') }}" @if(request()->routeIs('student.learning.*')) aria-current="page" @endif>My Learning</a>
                    <a href="{{ route('student.teaching.index') }}">Practice &amp; Feedback</a>
                    <a href="{{ route('student.scheduling.index') }}">Planning</a>
                    <a href="{{ route('student.statements.index') }}">Statements &amp; Receipts</a>
                    <a href="{{ route('student.notifications.index') }}">Notifications @if($studentPortalUnreadCount > 0)<span class="ml-1 rounded-full bg-nile-100 px-2 py-0.5 text-xs" aria-label="{{ $studentPortalUnreadCount }} unread">{{ $studentPortalUnreadCount }}</span>@endif</a>
                <a href="{{ route('student.bins.index') }}" class="text-sm font-semibold text-nile-800">Notes</a>
                </nav>
                <details class="relative text-sm">
                    <summary class="flex min-h-11 cursor-pointer items-center font-semibold text-nile-800">Your Account</summary>
                    <div class="absolute right-0 z-30 mt-2 grid min-w-52 gap-1 rounded-xl border border-stone-200 bg-white p-3 shadow-lg">
                        <a href="{{ route('student.profile') }}" class="flex min-h-11 items-center rounded-lg px-2 hover:bg-stone-50">Profile</a>
                        <a href="{{ route('student.video.devices') }}" class="flex min-h-11 items-center rounded-lg px-2 hover:bg-stone-50">Authorized Browsers</a>
                        <form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="min-h-11 w-full rounded-lg px-2 text-left font-medium text-nile-800 hover:bg-stone-50">Sign Out</button></form>
                    </div>
                </details>
            @endif
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">@if(app()->getLocale() !== 'en')<p role="status" class="mb-6 rounded-xl bg-stone-100 p-4 text-sm text-stone-600">The Student Portal is currently available in English.</p>@endif @yield('content')</main>
@if(empty($isPreview))<x-whatsapp-cta context="portal" />@endif
    @livewireScripts
</body>
</html>
