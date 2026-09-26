@extends('layouts.public', ['title' => 'Blog', 'metaDescription' => 'Blog posts, guides, and insights for learning Egyptian Arabic.'])

@section('content')
<section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <h1 class="text-4xl font-semibold tracking-tight text-nile-900">Blog</h1>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($blogs as $blog)
            <article class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                @if($blog->featured_image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($blog->featured_image_path) }}" alt="" loading="lazy" class="aspect-video w-full object-cover">@endif
                <div class="p-5"><p class="text-xs text-stone-500">{{ $blog->published_at?->format('M j, Y') }}</p><h2 class="mt-2 text-xl font-semibold text-nile-900"><a class="hover:underline" href="{{ route('blog.show', $blog->slug) }}">{{ $blog->title }}</a></h2><p class="mt-3 text-sm leading-6 text-stone-600">{{ $blog->excerpt }}</p></div>
            </article>
        @empty
            <p class="text-stone-600">New blog posts are coming soon.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $blogs->links() }}</div>
</section>
@endsection
