@php($isStudentSignIn = ($studentPresentation ?? null) === 'sign-in')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="analytics-event-url" content="{{ route('analytics.track') }}">
    <meta name="analytics-disabled" content="{{ app(\App\Domains\Analytics\Services\AnalyticsService::class)->excluded(request()) ? '1' : '0' }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/studio-fonts.css') }}">
    @livewireStyles
    <title>{{ __($title ?? 'Student Portal') }} — Arabic with Abdallah</title>
    <x-ui-translations />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-portal antialiased {{ $isStudentSignIn ? 'student-portal--sign-in' : '' }} {{ $studentPortalAccount ? 'student-portal--authenticated' : 'student-portal--entry' }}">
    <a href="#student-main" class="student-skip-link">{{ __('Skip to main content') }}</a>
    @if($studentPortalAccount)
        <aside class="student-sidebar" aria-label="{{ __('Student workspace') }}">
            <a href="{{ route('student.home') }}" class="student-brand">
                <img src="{{ asset('assets/brand/wordmark-ink-520.webp') }}" width="520" height="82" alt="Arabic with Abdallah">
            </a>
            <nav aria-label="{{ __('Student navigation') }}" class="student-sidebar-nav">
                @foreach($studentNavigation['home']['items'] as $item)
                    <x-student-navigation-link :item="$item" class="student-home-nav" />
                @endforeach
                @foreach($studentNavigation as $key => $group)
                    @if(in_array($key, ['learning', 'personal', 'records'], true))
                        <section class="student-nav-group" aria-labelledby="student-group-{{ $key }}">
                            <h2 id="student-group-{{ $key }}" class="student-nav-label">{{ __($group['label']) }}</h2>
                            @foreach($group['items'] as $item)
                                <x-student-navigation-link :item="$item" />
                            @endforeach
                        </section>
                    @endif
                @endforeach
            </nav>
        </aside>
    @endif
    <div class="student-column">
        <x-announcement-banner :announcement="$siteAnnouncement" />
        @unless($isStudentSignIn)
        <header class="student-utility-header">
            @if($studentPortalAccount)
                <a href="{{ route('student.home') }}" class="student-brand student-mobile-brand">
                    <img src="{{ asset('assets/brand/wordmark-ink-520.webp') }}" width="520" height="82" alt="Arabic with Abdallah">
                </a>
                <span class="student-workspace-label">{{ __('Student Portal') }}</span>
                <nav aria-label="{{ __('Student utilities') }}" class="student-utilities">
                    @foreach($studentNavigation['utility']['items'] as $item)
                        <a class="student-notifications" href="{{ route($item['destination']) }}" @if($item['current']) aria-current="{{ $item['exact'] ? 'page' : 'location' }}" @endif>
                            <x-student-portal-icon :name="$item['icon']" />
                            <span class="student-notifications-label">{{ __($item['label']) }}</span>
                            @if($studentPortalUnreadCount > 0)<span class="student-unread"><span aria-hidden="true">{{ $studentPortalUnreadCount }}</span><span class="sr-only">{{ $studentPortalUnreadCount }} {{ __('unread') }}</span></span>@endif
                        </a>
                    @endforeach
                    <details class="student-account" data-student-account>
                        <summary @if($studentCurrentGroup === 'account') data-current-section @endif>
                            <x-student-portal-icon name="profile" /><span>{{ __('Your Account') }}</span><x-student-portal-icon name="chevron" />
                            @if($studentCurrentGroup === 'account')<span class="sr-only">{{ __('Current section:') }} {{ __($studentCurrentLabel) }}</span>@endif
                        </summary>
                        <nav aria-label="{{ __('Student account') }}" class="student-account-menu">
                            @foreach($studentNavigation['account']['items'] as $item)
                                <x-student-navigation-link :item="$item" />
                            @endforeach
                            @include('student.partials.sign-out')
                        </nav>
                    </details>
                </nav>
            @else
                <a href="{{ route('home') }}" class="student-brand student-entry-brand">
                    <img src="{{ asset('assets/brand/wordmark-ink-520.webp') }}" width="520" height="82" alt="Arabic with Abdallah">
                </a>
            @endif
        </header>
        @endunless
        <nav aria-label="{{ __('Language selector') }}" class="flex flex-wrap justify-end gap-3 px-4 py-3 text-sm">
            @foreach(['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch'] as $code => $language)
                @if(app()->getLocale() === $code)<span aria-current="true" lang="{{ $code }}" class="font-semibold">{{ $language }}</span>
                @else<a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" class="student-text-link">{{ $language }}</a>@endif
            @endforeach
        </nav>
        <main id="student-main" tabindex="-1" class="student-main student-main--{{ $isStudentSignIn ? 'sign-in' : ($studentPortalAccount ? $studentContentWidth : (empty($isPreview) ? 'focused' : 'standard')) }}">
            <div class="student-workspace">@yield('content')</div>
        </main>
    </div>
    @if($studentPortalAccount)
        @php($studentMoreCurrent = in_array($studentCurrentGroup, ['personal', 'records', 'account'], true) || collect($studentNavigation['learning']['items'])->contains(fn ($item) => $item['current'] && ! ($item['mobile_primary'] ?? true)))
        <nav class="student-bottom-nav" aria-label="{{ __('Student mobile navigation') }}" data-student-bottom-nav>
            <div class="student-bottom-nav-inner">
            @foreach(array_merge($studentNavigation['home']['items'], $studentNavigation['learning']['items']) as $item)
                @if($item['mobile_primary'] ?? true)
                <x-student-navigation-link :item="$item" compact />
                @endif
            @endforeach
            <button type="button" class="student-nav-link" data-student-more @if($studentMoreCurrent) data-current-section aria-describedby="student-more-context" @endif aria-expanded="false" aria-controls="student-more-dialog" aria-haspopup="dialog">
                <x-student-portal-icon name="more" /><span>{{ __('More') }}</span>
            </button>
            </div>
        </nav>
        @if($studentMoreCurrent)
            <span id="student-more-context" class="sr-only">{{ __('Current section:') }} {{ __($studentCurrentLabel) }}{{ __(', available in More.') }}</span>
        @endif
        <dialog id="student-more-dialog" class="student-more-dialog" aria-labelledby="student-more-title" aria-modal="true">
            <div class="student-more-inner">
                <div class="student-more-heading"><h2 id="student-more-title">{{ __('More from your portal') }}</h2><button type="button" data-student-more-close autofocus aria-label="{{ __('Close More menu') }}"><x-student-portal-icon name="close" /></button></div>
                <nav aria-label="{{ __('More student navigation') }}">
                    <section class="student-nav-group" aria-labelledby="student-more-learning">
                        <h3 id="student-more-learning" class="student-nav-label">{{ __('Learning') }}</h3>
                        @foreach($studentNavigation['learning']['items'] as $item)@unless($item['mobile_primary'] ?? true)<x-student-navigation-link :item="$item" />@endunless @endforeach
                    </section>
                    @foreach($studentNavigation as $key => $group)
                        @if(in_array($key, ['personal', 'records', 'account'], true))
                            <section class="student-nav-group" aria-labelledby="student-more-{{ $key }}">
                                <h3 id="student-more-{{ $key }}" class="student-nav-label">{{ __($group['label']) }}</h3>
                                @foreach($group['items'] as $item)
                                    <x-student-navigation-link :item="$item" />
                                @endforeach
                                @if($key === 'account')@include('student.partials.sign-out')@endif
                            </section>
                        @endif
                    @endforeach
                </nav>
            </div>
        </dialog>
        <noscript><nav class="student-no-script" aria-label="{{ __('Additional student navigation') }}">@foreach($studentNavigation as $key => $group)@foreach($group['items'] as $item)@if(in_array($key, ['personal', 'records', 'account'], true) || ($key === 'learning' && ! ($item['mobile_primary'] ?? true)))<x-student-navigation-link :item="$item" />@endif @endforeach @endforeach @include('student.partials.sign-out')</nav></noscript>
    @endif
    @if(empty($isPreview))<div class="student-contact-slot" data-student-floating-tools>@if($studentPortalAccount)<a href="{{ route('student.bins.index') }}" class="student-quick-launcher" data-quick-notes-launcher aria-label="{{ __('Quick Notes') }}"><x-student-portal-icon name="pencil" /><span class="sr-only">{{ __('Quick Notes') }}</span><span class="student-quick-dot" hidden aria-hidden="true"></span></a>@endif<x-whatsapp-cta context="portal" /></div>@endif
    @if($studentPortalAccount)
        <x-quick-notes :session-token="Illuminate\Support\Facades\Crypt::encryptString(json_encode(['student_id' => $studentPortalAccount->id, 'session_id' => session()->getId()]))" />
    @endif
    @livewireScripts
</body>
</html>
