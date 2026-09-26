<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Preview Promotion: {{ $promotion->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 font-sans min-h-screen text-slate-800 antialiased">

    <!-- Admin Preview Header Banner -->
    <div class="bg-slate-900 text-white px-4 py-2.5 text-xs flex items-center justify-between border-b border-slate-800 sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded bg-amber-500 text-slate-950 font-bold uppercase tracking-wider text-[10px]">Preview Mode</span>
            <span class="font-semibold">{{ $promotion->title }}</span>
            <span class="text-slate-400">({{ $promotion->display_type }})</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.promotions.edit', $promotion) }}" class="text-amber-400 hover:text-amber-300 font-semibold underline">Edit Promotion</a>
            <a href="{{ route('admin.promotions.index') }}" class="text-slate-400 hover:text-white">Exit Preview</a>
        </div>
    </div>

    <!-- Promotional Component Render -->
    @include('components.promotional-banner', ['promotion' => $promotion, 'preview' => true])

    <!-- Mock Background / Landing Page Context -->
    <div class="max-w-4xl mx-auto py-16 px-4 space-y-8">
        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xs space-y-4">
            <div class="inline-block px-3 py-1 bg-amber-50 text-amber-800 rounded-full text-xs font-bold">Mock Landing Page Context</div>
            <h1 class="text-3xl font-bold font-serif text-slate-900">Learn Real Egyptian Arabic with Abdallah</h1>
            <p class="text-slate-600 leading-relaxed">
                This is a preview environment demonstrating how this promotion displays in real-world visitor conditions. If the promotion is a top bar, it renders above the navigation. If it is a modal popup, it will appear as an overlay. If it is an inline card, it will display below.
            </p>
        </div>

        @if($promotion->display_type === 'inline_card')
            <div class="pt-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Inline Card Placement</h3>
                @include('components.promotional-banner', ['promotion' => $promotion, 'preview' => true])
            </div>
        @endif
    </div>

</body>
</html>
