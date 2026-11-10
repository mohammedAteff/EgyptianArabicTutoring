@php
    $confirmedMaintenance = ($maintenanceContext ?? false) === true;
    $heading = $confirmedMaintenance ? __('A little work behind the scenes.') : __('The website is temporarily unavailable.');
    $message = $confirmedMaintenance
        ? (is_string($maintenanceMessage ?? null) && trim($maintenanceMessage) !== ''
            ? __($maintenanceMessage)
            : __("We're making a few improvements to Arabic with Abdallah. The website is temporarily unavailable, but we'll be back as soon as everything is ready. Thank you for your patience."))
        : __('Please try again later. Thank you for your patience.');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} | Arabic with Abdallah</title>
    <link rel="stylesheet" href="{{ asset('assets/fonts/studio-fonts.css') }}">
    <style>
        /* Critical snapshot of public-studio.css tokens; standalone for error rendering without Vite. */
        :root { color-scheme: light; --studio-canvas: #f7f1e8; --studio-surface: #fffdf9; --studio-ink: #201a17; --studio-muted: #665a52; --studio-clay: #b84b28; --studio-teal: #0e4b4b; --studio-teal-soft: #ddebe7; --studio-gold: #d99a2b; --studio-border: #ded2c6; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100svh; display: flex; padding: 48px 32px; background: var(--studio-canvas); color: var(--studio-ink); font: 400 18px/1.75 'Instrument Sans', 'Readex Pro', system-ui, sans-serif; }
        main { width: 100%; max-width: 1120px; min-width: 0; margin: auto; overflow-wrap: anywhere; }
        .availability-card { display: grid; grid-template-columns: 1.15fr 1fr; border: 1px solid var(--studio-border); border-top: 4px solid var(--studio-clay); border-radius: 16px; overflow: hidden; }
        .availability-heading { padding: 48px; background: var(--studio-teal); color: var(--studio-surface); }
        .brand { display: block; width: 320px; max-width: 100%; height: auto; margin-bottom: 56px; }
        .status { display: flex; align-items: center; gap: 12px; margin: 0 0 24px; color: var(--studio-surface); font-size: 11px; font-weight: 600; line-height: 1.5; letter-spacing: .12em; text-transform: uppercase; }
        .status::before { content: ''; flex: 0 0 28px; height: 2px; background: var(--studio-gold); }
        h1 { margin: 0; font: 400 clamp(38px, 4.5vw, 62px)/1.08 Newsreader, Georgia, serif; letter-spacing: -.025em; text-wrap: balance; }
        .availability-copy { display: flex; flex-direction: column; justify-content: center; padding: 48px; background: var(--studio-teal-soft); }
        .message { max-width: 38ch; margin: 0; color: var(--studio-teal); white-space: pre-line; }
        .message::before { content: ''; display: block; width: 48px; height: 3px; margin-bottom: 28px; background: var(--studio-clay); }
        .copyright { margin: 24px 0 0; color: var(--studio-muted); font-size: 12px; line-height: 1.6; }
        @media (max-width: 800px) { body { padding: 32px 24px; } .availability-card { grid-template-columns: minmax(0, 1fr); } .availability-heading, .availability-copy { padding: 36px; } .brand { margin-bottom: 40px; } h1 { max-width: 16ch; font-size: clamp(38px, 6vw, 54px); } .message { max-width: 54ch; } }
        @media (max-width: 480px) { body { padding: 24px 16px; font-size: 17px; } .availability-card { border-radius: 12px; } .availability-heading, .availability-copy { padding: 28px 24px; } .brand { width: 264px; margin-bottom: 36px; } .status { gap: 10px; margin-bottom: 20px; font-size: 10px; letter-spacing: .08em; } .status::before { flex-basis: 20px; } .message::before { margin-bottom: 20px; } .copyright { margin-top: 20px; } }
    </style>
</head>
<body>
    <main aria-labelledby="availability-heading">
        <div class="availability-card">
            <div class="availability-heading">
                <img class="brand" src="{{ asset('assets/brand/wordmark-cream-780.webp') }}" width="780" height="123" alt="Arabic with Abdallah">
                <p class="status">{{ $confirmedMaintenance ? __('Website maintenance') : __('Service unavailable') }}</p>
                <h1 id="availability-heading">{{ $heading }}</h1>
            </div>
            <div class="availability-copy">
                <p class="message">{{ $message }}</p>
            </div>
        </div>
        <p class="copyright">&copy; {{ date('Y') }} Arabic with Abdallah. {{ __('All rights reserved.') }}</p>
    </main>
</body>
</html>
