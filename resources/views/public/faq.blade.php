@extends('layouts.public')
@section('body-class', 'studio-faq-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php
    $search = $search ?? '';
    $topic = $topic ?? '';
    $topics = $topics ?? collect();
    $hasFilterErrors = isset($filterErrors) && $filterErrors->any();
    $faqUrl = localized_url('faq');
    $filterUrl = fn (string $selectedTopic = '', string $keyword = ''): string => $faqUrl.(($params = array_filter(['q' => $keyword, 'topic' => $selectedTopic], fn (string $value): bool => $value !== '')) ? '?'.http_build_query($params) : '').'#faq-results';
@endphp
<div class="faq-page" lang="{{ $copyLocale ?? 'en' }}">
    <header class="faq-intro">
        <x-public-copy-section :section="config('public_content.faq_intro')" :level="1" />
    </header>
    <div class="faq-discovery">
        @if(!($isPreview ?? false))
            <nav class="faq-topic-index" aria-label="{{ website_text('faq', 'Browse by Topic') }}">
                <h2>{{ website_text('faq', 'Browse by Topic') }}</h2>
                <ul>
                    <li><a href="{{ $filterUrl('', $search) }}" @if($topic === '') aria-current="page" @endif>{{ website_text('faq', 'All Questions') }}<span aria-hidden="true">→</span></a></li>
                    @foreach($topics as $item)
                        <li><a href="{{ $filterUrl($item, $search) }}" @if($topic === $item) aria-current="page" @endif>{{ website_text('faq', $item) }}<span aria-hidden="true">→</span></a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        <div class="faq-reading">
            @if(!($isPreview ?? false))
                <form action="{{ $faqUrl }}#faq-results" method="GET" class="faq-search" role="search" aria-label="{{ website_text('faq', 'Search questions') }}">
                    <div class="faq-keyword-control">
                        <label for="faq-search">{{ website_text('faq', 'Search questions') }}</label>
                        <input id="faq-search" type="search" name="q" value="{{ $search }}" maxlength="200" placeholder="{{ website_text('faq', 'What would you like to know?') }}" @if(isset($filterErrors) && $filterErrors->has('q')) aria-invalid="true" aria-describedby="faq-search-error" @endif>
                        @if(isset($filterErrors) && $filterErrors->has('q'))<p id="faq-search-error" class="faq-filter-error">{{ website_text('faq', 'Enter a search of up to 200 characters.') }}</p>@endif
                    </div>
                    <div class="faq-topic-control">
                        <label for="faq-topic">{{ website_text('faq', 'Browse by Topic') }}</label>
                        <select id="faq-topic" name="topic" @if(isset($filterErrors) && $filterErrors->has('topic')) aria-invalid="true" aria-describedby="faq-topic-error" @endif>
                            <option value="">{{ website_text('faq', 'All Questions') }}</option>
                            @foreach($topics as $item)<option value="{{ $item }}" @selected($topic === $item)>{{ website_text('faq', $item) }}</option>@endforeach
                            @if($topic !== '' && !$topics->containsStrict($topic))<option value="{{ $topic }}" selected>{{ $topic }}</option>@endif
                        </select>
                        @if(isset($filterErrors) && $filterErrors->has('topic'))<p id="faq-topic-error" class="faq-filter-error">{{ website_text('faq', 'Choose a topic of up to 100 characters.') }}</p>@endif
                    </div>
                    <button class="studio-button" type="submit">{{ website_text('faq', 'Search') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></button>
                </form>
            @endif
            <section id="faq-results" class="faq-results" aria-labelledby="faq-results-heading" tabindex="-1">
                <header class="faq-results-header">
                    <div>
                        <h2 id="faq-results-heading">{{ strtr(website_text('faq', $faqs->count() === 1 ? '{count} question' : '{count} questions'), ['{count}' => $faqs->count()]) }}</h2>
                        @if($search !== '')<p>{{ strtr(website_text('faq', 'Results for “{query}”'), ['{query}' => $search]) }}</p>@endif
                        @if($topic !== '')<p>{{ strtr(website_text('faq', 'In {topic}'), ['{topic}' => website_text('faq', $topic)]) }}</p>@endif
                    </div>
                    @if(!($isPreview ?? false) && ($search !== '' || $topic !== '' || $hasFilterErrors))
                        <div class="faq-resets">
                            @if(!$hasFilterErrors && $search !== '')<a href="{{ $filterUrl($topic) }}">{{ website_text('faq', 'Clear Search') }}</a>@endif
                            @if(!$hasFilterErrors && $topic !== '')<a href="{{ $filterUrl('', $search) }}">{{ website_text('faq', 'All Questions') }}</a>@endif
                            @if($hasFilterErrors || ($search !== '' && $topic !== ''))<a href="{{ $filterUrl() }}">{{ website_text('faq', 'Clear Search & Filters') }}</a>@endif
                        </div>
                    @endif
                </header>
                @forelse($faqs->groupBy(fn ($faq) => filled($faq->topic) ? $faq->topic : 'General') as $group => $questions)
                    <section class="faq-group" aria-labelledby="faq-group-{{ $loop->index }}">
                        <h3 id="faq-group-{{ $loop->index }}">{{ website_text('faq', $group) }}</h3>
                        <div class="faq-questions">
                            @foreach($questions as $faq)
                                @php
                                    $translation = ($isPreview ?? false) ? ['question' => $faq->question, 'answer' => $faq->answer, 'locale' => 'en'] : $faq->localizedContent();
                                    $answer = $translation['answer'];
                                    $answerParagraphs = preg_split('/\R(?:[ \t]*\R)+/u', $answer) ?: [$answer];
                                @endphp
                                <details class="faq-question" lang="{{ $translation['locale'] }}">
                                    <summary><span>{{ $translation['question'] }}@if(app()->getLocale() !== 'en' && ($translation['locale']) === 'en')<span lang="{{ $copyLocale ?? 'en' }}" class="faq-fallback-label">{{ website_text('faq', 'English Fallback') }}</span>@endif @if(isset($answerOnlyMatches[$faq->id]))<span class="faq-answer-match" lang="{{ $copyLocale ?? 'en' }}">{{ website_text('faq', 'Match found in the answer.') }}</span>@endif</span><span class="faq-disclosure-indicator" aria-hidden="true"></span></summary>
                                    <div class="faq-answer">@foreach($answerParagraphs as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
                                </details>
                            @endforeach
                        </div>
                        @php
                            $nextSteps = match ($group) {
                                'Diagnostic & Booking' => ['booking' => 'Book Your Diagnostic'],
                                'Pricing & Packages' => ['pricing' => 'Explore Lessons & Pricing'],
                                'Cancellations & Refunds' => ['policy' => 'Read the Booking & Refund Policy'],
                                'Student Portal' => ['student' => 'Open the Student Portal'],
                                'Resources & Games' => ['resources' => 'Explore Arabic Resources', 'games' => 'Explore Arabic Games'],
                                default => [],
                            };
                        @endphp
                        @if($nextSteps)
                            <div class="faq-next-steps">
                                @foreach($nextSteps as $destination => $label)<a href="{{ website_link('faq', $destination, $destination === 'student' ? route('student.login') : localized_url($destination)) }}" class="studio-link">{{ website_text('faq', $label) }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>@endforeach
                            </div>
                        @endif
                    </section>
                @empty
                    <div class="faq-empty">
                        <h3>{{ website_text('faq', ($publishedCount ?? 0) === 0 ? 'There are no published questions yet.' : (!($topicHasQuestions ?? true) || $search === '' ? 'There are no published questions in this category yet.' : 'No questions match your search.')) }}</h3>
                        <p>{{ website_text('faq', 'Try another word or browse all questions.') }}</p>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
    <section class="faq-help" aria-labelledby="faq-help-heading">
        @php
            $helpBody = (string) app(\App\Domains\CMS\Services\WebsiteCopyService::class)->value('faq', 'ui.b0e5b39157295001850fde5aacabfff10f0213b7', config('website_copy_text.faq.b0e5b39157295001850fde5aacabfff10f0213b7'));
            $helpParagraphs = preg_split('/\R(?:[ \t]*\R)+/u', $helpBody) ?: [$helpBody];
            $contact = app(\App\Domains\CMS\Services\WhatsAppContactResolver::class)->resolve();
        @endphp
        <div>
            <h2 id="faq-help-heading">{{ website_text('faq', 'Still Have Something on Your Mind?') }}</h2>
            @foreach($helpParagraphs as $paragraph)<p>{{ $paragraph }}</p>@endforeach
        </div>
        <div class="faq-help-actions">
            @if($contact)<a href="{{ $contact['destination'] }}" class="studio-button" target="_blank" rel="noopener noreferrer" data-whatsapp-cta data-whatsapp-placement="faq_help" data-context="public" data-language="{{ $contact['locale'] }}">{{ website_text('faq', 'Contact Abdallah') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>@endif
            <a href="{{ website_link('faq', 'pricing', localized_url('pricing')) }}" class="studio-link">{{ website_text('faq', 'Explore Lessons & Pricing') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
        </div>
    </section>
</div>
@endsection
