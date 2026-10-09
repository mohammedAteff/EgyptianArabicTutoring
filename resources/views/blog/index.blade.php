@extends('layouts.public')
@section('content')
<div class="mx-auto max-w-6xl space-y-14 px-4 py-12 sm:px-6">
    <x-public-english-fallback :is-fallback="$isFallback ?? false" />
    <section lang="en" class="max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">Arabic, Language &amp; Culture</p>
        <h1 class="mt-3 font-serif text-4xl font-semibold text-nile-900 sm:text-5xl">The Arabic Learning Blog</h1>
        <p class="mt-6 text-lg leading-8 text-stone-600">Arabic is more than a collection of words and grammar rules. It's a language shaped by history, culture, everyday conversations, and the people who speak it.</p>
        <p class="mt-4 leading-8 text-stone-600">Explore practical explanations, learning advice, and thoughtful articles about Egyptian Arabic, Modern Standard Arabic (MSA), linguistics, and life in Egypt.</p>
        <p class="mt-4 leading-8 text-stone-600">Whether you're learning from scratch, reconnecting with your heritage, or simply curious about languages, there's always something interesting to discover.</p>
    </section>
    @if($totalPublished > 0)
        <section lang="en" aria-labelledby="article-discovery">
            <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">Explore the Blog</p>
            <h2 id="article-discovery" class="mt-3 text-3xl font-semibold text-nile-900">Find Something Worth Reading.</h2>
            <p class="mt-4 leading-7 text-stone-600">Have a question about Arabic? Curious about an expression you've heard, a grammatical pattern, or an aspect of Egyptian culture? Browse the articles or search for a topic that interests you.</p>
            <form method="GET" class="mt-6 flex flex-wrap items-end gap-4">
                <div class="min-w-48 flex-1"><label for="article-search" class="block text-sm font-semibold">Search articles</label><input id="article-search" name="q" maxlength="200" value="{{ $search }}" placeholder="Search articles and topics..." class="mt-2 w-full rounded-xl border border-stone-300 bg-white px-4 py-3"></div>
                @if($categories->isNotEmpty())
                    <div><label for="article-category" class="block text-sm font-semibold">Category</label><select id="article-category" name="category" class="mt-2 rounded-xl border border-stone-300 bg-white px-4 py-3"><option value="">All Articles</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected($selectedCategory === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
                @endif
                @if($totalPublished > 1)
                    <div><label for="article-sort" class="block text-sm font-semibold">Sort</label><select id="article-sort" name="sort" class="mt-2 rounded-xl border border-stone-300 bg-white px-4 py-3"><option value="newest" @selected($sort === 'newest')>Newest First</option><option value="oldest" @selected($sort === 'oldest')>Oldest First</option></select></div>
                @endif
                <button class="rounded-xl bg-terracotta-600 px-5 py-3 font-semibold text-white">Find Articles</button>
            </form>
        </section>
        @if($featured)<x-blog-card :blog="$featured" :featured="true" />@endif
        <section aria-labelledby="article-library">
            <p lang="en" class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">Latest Articles</p>
            <h2 lang="en" id="article-library" class="mt-3 text-3xl font-semibold text-nile-900">Explore the Latest Articles</h2>
            <p lang="en" class="mt-4 leading-7 text-stone-600">From practical language questions to deeper cultural and linguistic explanations, explore ideas that make Arabic easier to understand and more interesting to learn.</p>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($blogs as $blog)<x-blog-card :blog="$blog" />
                @empty
                    <div lang="en" class="col-span-full rounded-2xl border border-stone-200 bg-white p-8">
                        <h3 class="text-xl font-semibold">{{ $search !== '' ? 'No Articles Found' : 'Nothing in This Category Yet' }}</h3>
                        <p class="mt-3 text-stone-600">{{ $search !== '' ? "We couldn't find an article matching your search. Try another phrase or explore all published articles." : "There aren't any published articles in this category at the moment. You can explore another topic or browse the full collection." }}</p>
                        <a href="{{ localized_url('blogs') }}" class="mt-4 inline-block font-semibold text-terracotta-600 underline">Clear Search &amp; Filters</a>
                    </div>
                @endforelse
            </div>
            <div class="mt-8">{{ $blogs->links() }}</div>
        </section>
    @else
        <section lang="en" class="rounded-2xl border border-stone-200 bg-white p-8 sm:p-10">
            <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">Notes from the Studio</p>
            <h2 class="mt-3 text-3xl font-semibold text-nile-900">Good Things Take a Little Thought.</h2>
            <p class="mt-5 leading-8 text-stone-600">The first articles are being prepared. This space will bring together explanations, observations, and useful ideas about Arabic, language learning, and Egyptian culture.</p>
            <p class="mt-4 leading-8 text-stone-600">In the meantime, you can explore the free learning resources and interactive language games available on the website.</p>
            <div class="mt-6 flex flex-wrap gap-5"><a href="{{ localized_url('resources') }}" class="font-semibold text-terracotta-600 underline">Explore Learning Resources</a><a href="{{ localized_url('games') }}" class="font-semibold text-terracotta-600 underline">Try Arabic Games</a></div>
        </section>
    @endif
    <section lang="en" class="border-t border-stone-200 pt-8">
        <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">More Ways to Learn</p><h2 class="mt-3 text-3xl font-semibold text-nile-900">Curiosity Doesn't Have to Stop Here.</h2>
        <p class="mt-4 leading-8 text-stone-600">An interesting article can lead to another question, a new topic, or something you'd like to practice. Explore downloadable learning materials and interactive language challenges whenever you're ready to take that curiosity a little further.</p>
        <div class="mt-5 flex flex-wrap gap-5"><a href="{{ localized_url('resources') }}" class="font-semibold text-terracotta-600 underline">Explore Learning Resources</a><a href="{{ localized_url('games') }}" class="font-semibold text-terracotta-600 underline">Explore Language Games</a></div>
    </section>
</div>
@endsection
