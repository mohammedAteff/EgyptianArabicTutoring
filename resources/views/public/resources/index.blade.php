@extends('layouts.public')
@section('body-class', 'studio-resources-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php
    $libraryUrl = localized_url('resources');
    $clearUrl = $libraryUrl.'#resource-results';
    $hasFilters = $search !== '' || $selectedCategory !== '' || $filterErrors->any();
@endphp
<div class="resources-page" lang="{{ $copyLocale ?? 'en' }}">
    @foreach(config('public_content.resources') as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        <section @class(['resources-section', 'resources-intro' => $index === 0, 'resources-library' => $index === 1, 'resources-methods' => $index === 2, 'resources-access' => $index === 3, 'resources-games' => $index === 4, 'resources-games-preview' => $index === 4 && $showGamePreview]) id="resources-section-{{ $index }}">
            <x-public-copy-section class="resources-copy" :section="$section" :level="$index === 0 ? 1 : 2" :show-blocks="$index !== 2" :preview-blocks="$index === 0 ? 2 : null" :details-label="website_text('resources', 'More about the library')" />
            @if($index === 1)
                @if($catalogueCount > 0)
                <form action="{{ $libraryUrl }}#resource-results" method="GET" class="resources-search" role="search" aria-label="{{ website_text('resources', 'Search resources') }}">
                    <div>
                        <label for="resource-search">{{ website_text('resources', 'Search resources') }}</label>
                        <input id="resource-search" name="q" type="search" value="{{ $search }}" maxlength="200" placeholder="{{ website_text('resources', 'Search resources by topic or title...') }}" @if($filterErrors->has('q')) aria-invalid="true" aria-describedby="resource-search-error" @endif>
                        @if($filterErrors->has('q'))<p id="resource-search-error" class="resources-error">{{ website_text('resources', 'Enter a search of up to 200 characters.') }}</p>@endif
                    </div>
                    @if($categories->isNotEmpty() || $selectedCategory !== '' || $filterErrors->has('category'))
                        <div>
                            <label for="resource-category">{{ website_text('resources', 'Category') }}</label>
                            <select id="resource-category" name="category" @if($filterErrors->has('category')) aria-invalid="true" aria-describedby="resource-category-error" @endif>
                                <option value="">{{ website_text('resources', 'All Resources') }}</option>
                                @foreach($categories as $category)<option value="{{ $category->slug }}" @selected($selectedCategory === $category->slug)>{{ $category->library_name }}</option>@endforeach
                                @if($selectedCategory !== '' && ! $categories->contains('slug', $selectedCategory))<option value="{{ $selectedCategory }}" selected>{{ $selectedCategory }}</option>@endif
                            </select>
                            @if($filterErrors->has('category'))<p id="resource-category-error" class="resources-error">{{ website_text('resources', 'Choose a category of up to 255 characters.') }}</p>@endif
                        </div>
                    @endif
                    <button type="submit" class="studio-button">{{ website_text('resources', 'Search') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></button>
                </form>
                @endif
                <div id="resource-results" class="resources-results" tabindex="-1">
                    @if($catalogueCount > 0 || $hasFilters)
                    <div class="resources-result-bar">
                        <div>
                            @if($catalogueCount > 0)
                            <h3>{{ strtr(website_text('resources', $resources->total() === 1 ? '{count} resource' : '{count} resources'), ['{count}' => $resources->total()]) }}</h3>
                            @if($resources->total() > 0)<p>{{ strtr(website_text('resources', 'Showing {first}–{last} of {total}'), ['{first}' => $resources->firstItem(), '{last}' => $resources->lastItem(), '{total}' => $resources->total()]) }}</p>@endif
                            @endif
                            @if($search !== '')<p>{{ strtr(website_text('resources', 'Results for “{query}”'), ['{query}' => $search]) }}</p>@endif
                            @if($selectedCategory !== '')<p>{{ strtr(website_text('resources', 'In {category}'), ['{category}' => $categoryLabel]) }}</p>@endif
                            @if($catalogueCount === 0)
                                @if($filterErrors->has('q'))<p class="resources-error">{{ website_text('resources', 'Enter a search of up to 200 characters.') }}</p>@endif
                                @if($filterErrors->has('category'))<p class="resources-error">{{ website_text('resources', 'Choose a category of up to 255 characters.') }}</p>@endif
                            @endif
                        </div>
                        @if($hasFilters && ($resources->isNotEmpty() || $catalogueCount === 0))<a href="{{ $clearUrl }}" class="studio-link">{{ website_text('resources', 'Clear Filters') }}</a>@endif
                    </div>
                    @endif
                    @if($resources->isNotEmpty())
                        @if($featuredResource)<x-public-resource-card :resource="$featuredResource" variant="library" :featured="true" />@endif
                        <div class="resources-grid">
                            @foreach($resources as $resource)
                                @if($resource->id !== $featuredResource?->id)<x-public-resource-card :resource="$resource" variant="library" />@endif
                            @endforeach
                        </div>
                    @else
                        <div class="resources-empty">
                            @if($catalogueCount === 0)
                                <h3>{{ website_text('resources', 'No resources are available to browse just yet.') }}</h3>
                                <p>{{ website_text('resources', 'Additional learning materials will appear here as they\'re published.') }}</p>
                            @elseif(! $categoryHasResources)
                                <h4>{{ website_text('resources', 'No resources in this category yet') }}</h4>
                                <p>{{ website_text('resources', 'There aren\'t any published resources in this category at the moment. Explore the rest of the library for other learning materials.') }}</p>
                                <a href="{{ $clearUrl }}" class="studio-link">{{ website_text('resources', 'Browse All Resources') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                            @else
                                <h4>{{ website_text('resources', $search !== '' ? 'No resources match your search.' : 'No resources on this page.') }}</h4>
                                <p>{{ website_text('resources', 'We couldn\'t find a resource matching those terms. Try another search or clear your filters.') }}</p>
                                <a href="{{ $clearUrl }}" class="studio-link">{{ website_text('resources', $hasFilters ? 'Clear Filters' : 'Browse All Resources') }}</a>
                            @endif
                        </div>
                    @endif
                    @if($resources->hasPages())<div class="resources-pagination">@include('public.resources.pagination')</div>@endif
                </div>
            @elseif($index === 2)
                <div class="resources-method-intro">@foreach(array_slice($section['blocks'], 0, 3) as $block)<x-public-copy-block :block="$block" />@endforeach</div>
                <div class="resources-method-grid">
                    @foreach([3, 5, 7] as $start)
                        <div class="resources-method">
                            <span aria-hidden="true" class="resources-step">0{{ $loop->iteration }}</span>
                            @foreach(array_slice($section['blocks'], $start, 2) as $block)<x-public-copy-block :block="$block" />@endforeach
                        </div>
                    @endforeach
                </div>
                <div class="resources-method-close">@foreach(array_slice($section['blocks'], 9) as $block)<x-public-copy-block :block="$block" />@endforeach</div>
            @elseif($index === 4)
                <a href="{{ website_link('resources', 'games', localized_url('games')) }}" class="studio-link">{{ website_text('resources', 'Explore Arabic Games') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                @if($showGamePreview)<img class="resources-game-image" src="{{ asset(config('games.local.6-word-story.cover', 'images/games/6-word-story.webp')) }}" alt="{{ website_text('resources', '6-Word Story game preview') }}" width="1280" height="720" loading="lazy">@endif
            @endif
        </section>
    @endforeach
</div>
@endsection
