<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ $title ?? 'Student area' }} — Egyptian Arabic Tutoring</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[#FAF8F5] text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight text-nile-900">Egyptian Arabic Tutoring</a>
            @if(session()->has('student_id'))
                <form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="text-sm font-medium text-nile-800 hover:underline">Sign out</button></form>
            @endif
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">@yield('content')</main>
</body>
</html>
