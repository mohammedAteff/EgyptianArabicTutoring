<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Preview Promotion: {{ $promotion->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/admin-quiet-operations.css', 'resources/js/admin-appearance.js'])
</head>
<body class="studio-promotion-preview min-h-screen antialiased">

    <!-- Admin Preview Header Banner -->
    <div data-admin-appearance-scope data-admin-appearance="light" class="aq-preview-controls px-4 py-2.5 text-xs flex flex-wrap gap-3 items-center justify-between border-b sticky top-0 z-50">
        <div class="flex flex-wrap items-center gap-2">
            <span class="aq-preview-badge px-2 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">Preview Mode</span>
            <span class="font-semibold">{{ $promotion->title }}</span>
            <span class="text-slate-400">({{ $promotion->display_type }})</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="aq-appearance">
                <label for="preview-appearance">Appearance</label>
                <select id="preview-appearance" data-admin-appearance-select>
                    <option value="light">Light</option>
                    <option value="dark-teal">Dark Teal</option>
                    <option value="pure-black">Pure Black</option>
                </select>
            </div>
            <a href="{{ route('admin.promotions.edit', $promotion) }}" class="text-amber-400 hover:text-amber-300 font-semibold underline">Edit Promotion</a>
            <a href="{{ route('admin.promotions.index') }}" class="text-slate-400 hover:text-white">Exit Preview</a>
        </div>
    </div>

    <script>
        try {
            const appearance = localStorage.getItem('awa.admin.appearance.v1');
            document.querySelector('[data-admin-appearance-scope]').dataset.adminAppearance = ['light', 'dark-teal', 'pure-black'].includes(appearance) ? appearance : 'light';
        } catch {}
    </script>

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
