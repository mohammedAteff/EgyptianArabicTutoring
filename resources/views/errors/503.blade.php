<!DOCTYPE html>
<html lang="en" class="h-full bg-stone-900 text-stone-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance in Progress | {{ config('business.site_name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center p-6 bg-radial from-stone-900 to-stone-950 font-sans">
    <div class="max-w-md w-full text-center space-y-6">
        <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-500 border border-amber-500/30 flex items-center justify-center mx-auto text-2xl font-bold font-serif">
            <span aria-hidden="true">A</span>
        </div>

        <div class="space-y-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                Scheduled Maintenance
            </span>
            <h1 class="text-2xl font-bold font-serif text-white tracking-tight">We'll Be Right Back</h1>
            <p class="text-sm text-stone-400 leading-relaxed">
                {{ \App\Domains\CMS\Models\Setting::get('maintenance_message', 'The platform is currently undergoing scheduled maintenance. We will be back shortly.') }}
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-stone-800/60 border border-stone-700/60 text-xs text-stone-400 font-serif" dir="rtl">
            <div class="font-bold text-stone-200 mb-1">الموقع تحت الصيانة الدورية حالياً</div>
            <div>سنعود للعمل واستقبال الحجوزات في أقرب وقت. شكراً لتفهمكم.</div>
        </div>

        <div class="pt-4 text-xs text-stone-500">
            &copy; {{ date('Y') }} {{ config('business.site_name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
