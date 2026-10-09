@props(['blog', 'featured' => false])
@php($image = app(\App\Domains\CMS\Services\BlogImageService::class)->url($blog->featured_image_path))
<article lang="{{ $blog->locale }}" class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm {{ $featured ? 'md:grid md:grid-cols-2' : '' }}">
    @if($image)<a href="{{ $blog->publicUrl() }}" tabindex="-1" aria-hidden="true"><img src="{{ $image }}" alt="{{ $blog->featured_image_alt ?? '' }}" loading="lazy" class="aspect-video h-full w-full object-cover"></a>@endif
    <div class="p-6">
        @if($featured)<p lang="en" class="mb-3 text-xs font-semibold uppercase tracking-widest text-terracotta-600">Featured Article</p>@endif
        @if($blog->category)<p class="text-sm text-terracotta-600">{{ $blog->category->name }}</p>@endif
        <h2 class="mt-2 text-2xl font-semibold text-nile-900"><a class="hover:underline" href="{{ $blog->publicUrl() }}">{{ $blog->title }}</a></h2>
        <p class="mt-3 text-sm leading-7 text-stone-600">{{ $blog->excerpt }}</p>
        <p lang="en" class="mt-4 text-xs text-stone-500">{{ $blog->published_at?->format('M j, Y') }} · {{ $blog->readingMinutes() }} min read</p>
        <a lang="en" href="{{ $blog->publicUrl() }}" class="mt-5 inline-block font-semibold text-terracotta-600 underline underline-offset-4">Read Article <span class="sr-only">: {{ $blog->title }}</span></a>
    </div>
</article>
