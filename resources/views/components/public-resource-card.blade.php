@props(['resource'])
@php
    $translation = $resource->liveTranslation();
    $resourceTitle = $translation?->title ?? $resource->title;
@endphp
<article class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm" lang="{{ $translation?->locale ?? 'en' }}">
    @if($resource->cover_image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($resource->cover_image_path))
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($resource->cover_image_path) }}" alt="{{ $resourceTitle }}" class="aspect-[16/9] w-full object-cover" loading="lazy">
    @endif
    <div class="flex grow flex-col gap-4 p-6">
        <p class="text-xs font-bold uppercase tracking-wide text-terracotta-600">{{ $resource->category?->liveTranslation()?->name ?? $resource->category?->name ?? 'Learning material' }} · {{ strtoupper($resource->file_type ?? 'File') }}</p>
        <h3 class="text-xl font-bold"><a href="{{ localized_url('resource.detail', $resource->slug) }}" class="hover:text-terracotta-600">{{ $resourceTitle }}</a></h3>
        <p class="grow text-sm leading-relaxed text-stone-600">{{ $translation?->short_description ?? $resource->short_description }}</p>
        <a href="{{ localized_url('resource.detail', $resource->slug) }}" class="font-bold text-terracotta-600 underline underline-offset-4">Get Free Access</a>
        <p class="text-xs text-stone-500">Free · Name and email required</p>
    </div>
</article>
