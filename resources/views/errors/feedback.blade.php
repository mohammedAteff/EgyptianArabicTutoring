<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @if($admin) data-admin-appearance="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ __('feedback.nav') }} · {{ $status }} — Arabic with Abdallah</title>
    @if($admin)<x-admin.appearance-init />@endif
    <style>
        :root { color-scheme: light; --canvas: #f7f1e8; --surface: #fffdf9; --ink: #201a17; --muted: #665a52; --border: #ded2c6; --accent: #0e4b4b; --on-accent: #fffdf9; }
        html[data-admin-appearance="light"] { --canvas: #f5f3ed; --surface: #fffefb; --ink: #151515; --muted: #3f3f3f; --border: #d5dcd3; --accent: #0e4b4b; }
        html[data-admin-appearance="dark-teal"] { color-scheme: dark; --canvas: #0b1718; --surface: #122224; --ink: #f2f2f2; --muted: #d3d3d3; --border: #324b4b; --accent: #a7d6c4; --on-accent: #111111; }
        html[data-admin-appearance="pure-black"] { color-scheme: dark; --canvas: #000; --surface: #111; --ink: #f2f2f2; --muted: #d0d0d0; --border: #383838; --accent: #d9c49c; --on-accent: #111111; }
        @media (prefers-color-scheme: dark) {
            html:not([data-admin-appearance]) { color-scheme: dark; --canvas: #0b1718; --surface: #122224; --ink: #f2f2f2; --muted: #d3d3d3; --border: #324b4b; --accent: #a7d6c4; --on-accent: #111111; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100svh; display: grid; place-items: center; padding: 24px 16px; background: var(--canvas); color: var(--ink); font: 1rem/1.65 system-ui, sans-serif; }
        main { width: min(100%, 36rem); padding: clamp(24px, 5vw, 48px); border: 1px solid var(--border); border-radius: 20px; background: var(--surface); overflow-wrap: anywhere; }
        .eyebrow { margin: 0 0 16px; color: var(--muted); font-size: .875rem; }
        h1 { margin: 0 0 16px; font-size: clamp(1.5rem, 5vw, 2rem); line-height: 1.25; }
        p { margin: 0 0 24px; }
        nav { display: flex; flex-wrap: wrap; gap: 12px; }
        a { display: inline-flex; align-items: center; min-height: 44px; padding: 10px 16px; border: 1px solid var(--border); border-radius: 10px; color: var(--ink); text-decoration: none; font-weight: 600; }
        .primary { border-color: var(--accent); background: var(--accent); color: var(--on-accent); }
        a:focus-visible { outline: 3px solid var(--accent); outline-offset: 4px; }
    </style>
</head>
<body>
    <main data-feedback-error>
        <p class="eyebrow">{{ __('feedback.nav') }} · {{ $status }}</p>
        <h1>{{ __($status >= 500 ? 'feedback.error_title' : 'feedback.unavailable') }}</h1>
        @if($status >= 500 || $message !== __('feedback.unavailable'))<p>{{ $message }}</p>@endif
        <nav aria-label="{{ __('feedback.nav') }}">
            @if($retryUrl)<a class="primary" href="{{ $retryUrl }}">{{ __('feedback.retry') }}</a>
            @else<a class="primary" href="{{ route($admin ? 'admin.feedback.index' : 'student.feedback.index') }}">{{ __('feedback.back') }}</a>@endif
            <a href="{{ route($admin ? 'admin.dashboard' : 'student.home') }}">{{ __('feedback.workspace') }}</a>
        </nav>
    </main>
</body>
</html>
