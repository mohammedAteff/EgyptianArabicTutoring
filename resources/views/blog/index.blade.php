@extends('layouts.public')
@section('body-class', 'studio-blogs-page studio-blogs-index')
@section('content')
<div class="blogs-page">
    <x-public-english-fallback :is-fallback="$isFallback ?? false" />
    <section lang="{{ $copyLocale ?? 'en' }}" class="blogs-hero">
        <div>
            <p class="blogs-eyebrow">{{ website_text('blogs', 'Arabic, Language & Culture') }}</p>
            <h1>{{ website_text('blogs', 'The Arabic Learning Blog') }}</h1>
        </div>
        <div class="blogs-intro">
            <p>{{ website_text('blogs', 'Arabic is more than a collection of words and grammar rules. It\'s a language shaped by history, culture, everyday conversations, and the people who speak it.') }}</p>
            <p>{{ website_text('blogs', 'Explore practical explanations, learning advice, and thoughtful articles about Egyptian Arabic, Modern Standard Arabic (MSA), linguistics, and life in Egypt.') }}</p>
            <p>{{ website_text('blogs', 'Whether you\'re learning from scratch, reconnecting with your heritage, or simply curious about languages, there\'s always something interesting to discover.') }}</p>
        </div>
    </section>
    @if($totalPublished > 0)
        <section aria-labelledby="article-library" class="blogs-library">
            <div lang="{{ $copyLocale ?? 'en' }}" class="blogs-library-intro">
                <div>
                    <p class="blogs-eyebrow">{{ website_text('blogs', 'Explore the Blog') }}</p>
                    <h2 id="article-library">{{ website_text('blogs', 'Find Something Worth Reading.') }}</h2>
                    <p>{{ website_text('blogs', 'Have a question about Arabic? Curious about an expression you\'ve heard, a grammatical pattern, or an aspect of Egyptian culture? Browse the articles or search for a topic that interests you.') }}</p>
                </div>

            </div>
            @if($totalPublished > 1 || $search !== '' || $selectedCategory !== '' || $sort !== 'newest')
                <form method="GET" action="{{ localized_url('blogs') }}#article-library" lang="{{ $copyLocale ?? 'en' }}" class="blogs-filters" role="search" aria-label="{{ website_text('blogs', 'Find Articles') }}">
                    <div class="blogs-search"><label for="article-search">{{ website_text('blogs', 'Search articles') }}</label><input id="article-search" name="q" maxlength="200" value="{{ $search }}" placeholder="{{ website_text('blogs', 'Search articles and topics...') }}"></div>
                    @if($categories->count() >= 2 || $selectedCategory !== '')
                        <div><label for="article-category">{{ website_text('blogs', 'Category') }}</label><select id="article-category" name="category"><option value="">{{ website_text('blogs', 'All Articles') }}</option>@if($selectedCategory !== '' && ! $categories->contains('slug', $selectedCategory))<option value="{{ $selectedCategory }}" selected>{{ $selectedCategory }}</option>@endif @foreach($categories as $category)<option value="{{ $category->slug }}" @selected($selectedCategory === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
                    @endif
                    @if($totalPublished > 1 || $sort !== 'newest')
                        <div><label for="article-sort">{{ website_text('blogs', 'Sort') }}</label><select id="article-sort" name="sort"><option value="newest" @selected($sort === 'newest')>{{ website_text('blogs', 'Newest First') }}</option><option value="oldest" @selected($sort === 'oldest')>{{ website_text('blogs', 'Oldest First') }}</option></select></div>
                    @endif
                    <button class="studio-button studio-button-primary">{{ website_text('blogs', 'Find Articles') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></button>
                    @if($search !== '' || $selectedCategory !== '' || $sort !== 'newest')<a href="{{ localized_url('blogs') }}#article-library" class="blogs-reset">{{ website_text('blogs', 'Clear Search & Filters') }}</a>@endif
                </form>
            @endif
            @if($lead)<x-blog-card :blog="$lead" :wide="true" :featured="$featured !== null" :copy-locale="$copyLocale ?? 'en'" />@endif
            @if($blogs->isNotEmpty())
                <div class="blogs-grid">@foreach($blogs as $blog)<x-blog-card :blog="$blog" :copy-locale="$copyLocale ?? 'en'" />@endforeach</div>
            @elseif(! $lead)
                <div lang="{{ $copyLocale ?? 'en' }}" class="blogs-empty">
                    <h3>{{ __($search !== '' ? website_text('blogs', 'No Articles Found') : website_text('blogs', 'Nothing in This Category Yet')) }}</h3>
                    <p>{{ $search !== '' ? website_text('blogs', 'We couldn\'t find an article matching your search. Try another phrase or explore all published articles.') : website_text('blogs', 'There aren\'t any published articles in this category at the moment. You can explore another topic or browse the full collection.') }}</p>
                    <a href="{{ localized_url('blogs') }}#article-library" class="blogs-link">{{ __($search !== '' ? website_text('blogs', 'Clear Search & Filters') : website_text('blogs', 'View All Articles')) }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                </div>
            @endif
                <div lang="{{ $copyLocale ?? 'en' }}" class="blogs-library-note">
                    <p class="blogs-eyebrow">{{ website_text('blogs', 'Latest Articles') }}</p>
                    <h3>{{ website_text('blogs', 'Explore the Latest Articles') }}</h3>
                    <p>{{ website_text('blogs', 'From practical language questions to deeper cultural and linguistic explanations, explore ideas that make Arabic easier to understand and more interesting to learn.') }}</p>
                </div>
            @if($blogs->hasPages())<div class="blogs-pagination">{{ $blogs->links() }}</div>@endif
        </section>
        <section lang="{{ $copyLocale ?? 'en' }}" class="blogs-invitation">
            <div><p class="blogs-eyebrow">{{ website_text('blogs', 'More Ways to Learn') }}</p><h2>{{ website_text('blogs', 'Curiosity Doesn\'t Have to Stop Here.') }}</h2></div>
            <div><p>{{ website_text('blogs', 'An interesting article can lead to another question, a new topic, or something you\'d like to practice. Explore downloadable learning materials and interactive language challenges whenever you\'re ready to take that curiosity a little further.') }}</p><div class="blogs-actions"><a href="{{ website_link('blogs', 'resources', localized_url('resources')) }}" class="blogs-link">{{ website_text('blogs', 'Explore Learning Resources') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a><a href="{{ website_link('blogs', 'games', localized_url('games')) }}" class="blogs-link">{{ website_text('blogs', 'Explore Language Games') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a></div></div>
        </section>
    @else
        <section lang="{{ $copyLocale ?? 'en' }}" class="blogs-empty blogs-publication-empty">
            <p class="blogs-eyebrow">{{ website_text('blogs', 'Notes from the Studio') }}</p>
            <h2>{{ website_text('blogs', 'Good Things Take a Little Thought.') }}</h2>
            <p>{{ website_text('blogs', 'The first articles are being prepared. This space will bring together explanations, observations, and useful ideas about Arabic, language learning, and Egyptian culture.') }}</p>
            <p>{{ website_text('blogs', 'In the meantime, you can explore the free learning resources and interactive language games available on the website.') }}</p>
            <div class="blogs-actions"><a href="{{ website_link('blogs', 'resources', localized_url('resources')) }}" class="blogs-link">{{ website_text('blogs', 'Explore Learning Resources') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a><a href="{{ website_link('blogs', 'games', localized_url('games')) }}" class="blogs-link">{{ website_text('blogs', 'Try Arabic Games') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a></div>
        </section>
    @endif
</div>
@endsection
