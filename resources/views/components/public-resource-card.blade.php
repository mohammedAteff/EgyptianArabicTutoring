@props(['resource', 'variant' => 'default', 'featured' => false])
@if($variant === 'library')
    @php
        $cover = $resource->cover_image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($resource->cover_image_path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($resource->cover_image_path) : null;
        $resourceTitle = $resource->library_title;
        $detailUrl = localized_url('resource.detail', $resource->slug);
    @endphp
    <article @class(['resource-library-card', 'resource-library-featured' => $featured]) data-resource-id="{{ $resource->id }}" lang="{{ $resource->library_locale }}">
        @if($cover)
            <div class="resource-library-media">
                <img src="{{ $cover }}" alt="{{ $resourceTitle }}" width="640" height="400" loading="lazy">
            </div>
        @endif
        <div class="resource-library-copy">
            <p class="resource-library-meta"><bdi>{{ __($resource->library_category ?: website_text('resources', 'Learning material')) }}</bdi>@if(filled($resource->file_type))<span aria-hidden="true">·</span><bdi>{{ strtoupper($resource->file_type) }}</bdi>@endif</p>
            <h3><a href="{{ $detailUrl }}"><bdi>{{ $resourceTitle }}</bdi></a></h3>
            <p class="resource-library-description" dir="auto">{{ $resource->library_short_description }}</p>
            <a href="{{ $detailUrl }}" class="studio-link">{{ website_text('resources', 'View Resource') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            <p class="resource-library-access">{{ website_text('resources', 'Free · Name and email required') }}</p>
        </div>
    </article>
@else
@php
    $translation = $resource->liveTranslation();
    $resourceTitle = $translation?->title ?? $resource->title;
@endphp
<article class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm" lang="{{ $translation?->locale ?? 'en' }}">
    @if($resource->cover_image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($resource->cover_image_path))
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($resource->cover_image_path) }}" alt="{{ $resourceTitle }}" class="aspect-[16/9] w-full object-cover" loading="lazy">
    @endif
    <div class="flex grow flex-col gap-4 p-6">
        <p class="text-xs font-bold uppercase tracking-wide text-terracotta-600">{{ __($resource->category?->liveTranslation()?->name ?? $resource->category?->name ?? 'Learning material') }} · {{ __(strtoupper($resource->file_type ?? 'File')) }}</p>
        <h3 class="text-xl font-bold"><a href="{{ localized_url('resource.detail', $resource->slug) }}" class="hover:text-terracotta-600">{{ $resourceTitle }}</a></h3>
        <p class="grow text-sm leading-relaxed text-stone-600">{{ $translation?->short_description ?? $resource->short_description }}</p>
        <a href="{{ localized_url('resource.detail', $resource->slug) }}" class="font-bold text-terracotta-600 underline underline-offset-4">{{ __('Get Free Access') }}</a>
        <p class="text-xs text-stone-500">{{ __('Free · Name and email required') }}</p>
    </div>
</article>
@endif
