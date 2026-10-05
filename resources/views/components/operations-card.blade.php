@props(['heading', 'count', 'url' => null])
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm']) }} aria-label="{{ $heading }}">
    <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-bold">{{ $heading }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ $count }}</span></div>
    <div class="mt-4 space-y-3">{{ $slot }}</div>
    @if($count > 12)<p class="mt-4 text-xs text-slate-500">Showing the first 12 of {{ $count }} records.</p>@endif
    @if($url)<a class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-amber-800" href="{{ $url }}">Open full list →</a>@endif
</section>
