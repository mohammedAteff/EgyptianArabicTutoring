@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs font-medium text-stone-500 mb-8">
        <a href="{{ localized_url('home') }}" class="hover:text-stone-800">{{ __('Home') }}</a>
        <span>/</span>
        <a href="{{ localized_url('games') }}" class="hover:text-stone-800">{{ __('Games') }}</a>
        <span>/</span>
        <span class="text-stone-800 font-bold truncate">{{ $translation->title ?? $game->title }}</span>
    </nav>

    @if($isFallback ?? false)
        <div class="mb-8 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl text-sm text-amber-900 font-medium shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('content_fallback_banner') }}</span>
        </div>
    @endif

    <!-- Game Container with Alpine Reactive State -->
    <div @if($isFallback ?? false) lang="en" dir="ltr" @endif data-section-id="game-board" class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-10 shadow-sm"
         x-data="{
             gameSlug: @js($game->slug),
             trackingUrl: @js(route('games.track', ['slug' => $game->slug])),
             csrfToken: @js(csrf_token()),
             state: 'intro', // 'intro', 'playing', 'finished'
             currentIndex: 0,
             score: 0,
             selectedOption: null,
             showFeedback: false,
             questions: [
                 {
                     arabic: 'كام ده يا فندم؟',
                     phonetic: 'Kaam da ya fandem?',
                     meaning: 'How much is this, sir/ma\'am?',
                     context: 'Crucial phrase when shopping in Khan el-Khalili or any Cairo market.',
                     options: [
                         'How much is this, sir?',
                         'Where is the metro station?',
                         'Can you call a taxi for me?',
                         'What is your name?'
                     ],
                     correct: 0
                 },
                 {
                     arabic: 'على جنب هنا لو سمحت',
                     phonetic: 'Ala ganb hena law samaht',
                     meaning: 'Pull over right here, please.',
                     context: 'Used inside Cairo taxis and microbuses when you reach your stop.',
                     options: [
                         'Turn left at the traffic light',
                         'Pull over right here, please',
                         'How long until we arrive?',
                         'The air conditioning is too cold'
                     ],
                     correct: 1
                 },
                 {
                     arabic: 'ولا يهمك خالص',
                     phonetic: 'Wala yehimmak khales',
                     meaning: 'Don\'t worry at all / No problem.',
                     context: 'Friendly reassurance used in everyday social interactions.',
                     options: [
                         'Please hurry up',
                         'I don\'t understand',
                         'Don\'t worry at all / No problem',
                         'I will call you tomorrow'
                     ],
                     correct: 2
                 },
                 {
                     arabic: 'ممكن تديني الحساب؟',
                     phonetic: 'Momken teddeeni el-hesaab?',
                     meaning: 'Could you give me the check / bill?',
                     context: 'Polite way to ask for the check at any Egyptian cafe (ahwa) or restaurant.',
                     options: [
                         'Is this table reserved?',
                         'Where is the restroom?',
                         'Could you give me the check?',
                         'Do you accept credit cards?'
                     ],
                     correct: 2
                 },
                 {
                     arabic: 'ماشي يا باشا',
                     phonetic: 'Maashi ya basha',
                     meaning: 'Alright boss / Sounds good!',
                     context: 'Super common casual confirmation in street dialogue.',
                     options: [
                         'Alright boss / Sounds good!',
                         'Excuse me, pardon me',
                         'Good evening to you',
                         'Where are you going?'
                     ],
                     correct: 0
                 }
             ],
             startGame() {
                 this.state = 'playing';
                 this.currentIndex = 0;
                 this.score = 0;
                 this.selectedOption = null;
                 this.showFeedback = false;

                 // Track game_started event
                 fetch(this.trackingUrl, {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': this.csrfToken
                     },
                     body: JSON.stringify({ action: 'game_started' })
                 });
             },
             chooseOption(index) {
                 if (this.showFeedback) return;
                 this.selectedOption = index;
                 this.showFeedback = true;
                 if (index === this.questions[this.currentIndex].correct) {
                     this.score++;
                 }
             },
             nextQuestion() {
                 this.showFeedback = false;
                 this.selectedOption = null;
                 if (this.currentIndex + 1 < this.questions.length) {
                     this.currentIndex++;
                 } else {
                     this.finishGame();
                 }
             },
             finishGame() {
                 this.state = 'finished';

                 // Track game_completed event
                 fetch(this.trackingUrl, {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': this.csrfToken
                     },
                     body: JSON.stringify({
                         action: 'game_completed',
                         metadata: {
                             score: this.score,
                             total: this.questions.length
                         }
                     })
                 });
             }
         }">

        <!-- STATE 1: Intro Screen -->
        <div x-show="state === 'intro'" class="text-center space-y-6 py-6">
            <div class="w-20 h-20 rounded-3xl bg-amber-100 text-amber-600 flex items-center justify-center font-bold text-4xl mx-auto shadow-sm">
                🎮
            </div>
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3 py-1 rounded-full">
                    {{ $translation->badge ?? ($game->badge ?: 'Interactive Challenge') }}
                </span>
                <h1 class="text-3xl font-extrabold text-stone-900 mt-2">{{ $translation->title ?? $game->title }}</h1>
                <p class="text-stone-600 text-base max-w-md mx-auto mt-2 leading-relaxed">
                    {{ $translation->description ?? ($game->description ?: 'Test your understanding of essential Egyptian Arabic street phrases and expressions used daily in Cairo.') }}
                </p>
            </div>

            <div class="p-6 bg-stone-50 rounded-2xl max-w-md mx-auto border border-stone-100 text-xs text-stone-500 space-y-2">
                <div class="flex items-center justify-between">
                    <span>Questions</span>
                    <span class="font-bold text-stone-800">5 Essential Phrases</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Format</span>
                    <span class="font-bold text-stone-800">Multiple Choice + Cultural Notes</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Target Dialect</span>
                    <span class="font-bold text-stone-800">Egyptian Spoken Arabic (Amiya)</span>
                </div>
            </div>

            <div class="pt-4">
                <button type="button"
                        @click="startGame()"
                        class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-base px-8 py-4 rounded-full shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                    <span>Start Practice Game</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </div>
        </div>

        <!-- STATE 2: Active Playing Screen -->
        <div x-show="state === 'playing'" x-cloak class="space-y-6">
            <!-- Progress Bar -->
            <div class="flex items-center justify-between text-xs font-bold text-stone-500 border-b border-stone-100 pb-3">
                <span>Question <span x-text="currentIndex + 1"></span> of <span x-text="questions.length"></span></span>
                <span>Score: <span x-text="score" class="text-terracotta-600 font-extrabold"></span></span>
            </div>

            <!-- Arabic Phrase Card -->
            <div class="p-8 rounded-3xl bg-stone-50 border border-stone-200/80 text-center space-y-2">
                <bdi dir="rtl" lang="ar" class="font-cairo font-extrabold text-3xl sm:text-4xl text-stone-900 block"
                     x-text="questions[currentIndex].arabic"></bdi>
                <div class="text-sm font-semibold text-terracotta-600"
                     x-text="questions[currentIndex].phonetic"></div>
            </div>

            <!-- Multiple Choice Options -->
            <div class="space-y-3">
                <template x-for="(option, idx) in questions[currentIndex].options" :key="idx">
                    <button type="button"
                            @click="chooseOption(idx)"
                            class="w-full text-left p-4 rounded-2xl border text-sm font-semibold transition-all flex items-center justify-between"
                            :class="{
                                'border-stone-200 hover:border-terracotta-400 hover:bg-stone-50': !showFeedback,
                                'border-emerald-500 bg-emerald-50 text-emerald-900': showFeedback && idx === questions[currentIndex].correct,
                                'border-rose-400 bg-rose-50 text-rose-800': showFeedback && selectedOption === idx && idx !== questions[currentIndex].correct,
                                'opacity-50 border-stone-100': showFeedback && selectedOption !== idx && idx !== questions[currentIndex].correct
                            }">
                        <span x-text="option"></span>
                        <template x-if="showFeedback && idx === questions[currentIndex].correct">
                            <span class="text-emerald-600 font-bold">✓ Correct</span>
                        </template>
                        <template x-if="showFeedback && selectedOption === idx && idx !== questions[currentIndex].correct">
                            <span class="text-rose-600 font-bold">✗ Incorrect</span>
                        </template>
                    </button>
                </template>
            </div>

            <!-- Feedback & Next Button -->
            <div x-show="showFeedback" x-transition class="p-5 rounded-2xl bg-stone-100/80 border border-stone-200 space-y-3">
                <div class="text-xs text-stone-600 leading-relaxed">
                    <strong class="text-stone-800">Context:</strong>
                    <span x-text="questions[currentIndex].context"></span>
                </div>
                <div class="flex justify-end">
                    <button type="button"
                            @click="nextQuestion()"
                            class="bg-stone-900 hover:bg-black text-white text-xs font-bold px-6 py-2.5 rounded-full shadow-sm transition-colors flex items-center gap-1.5">
                        <span x-text="currentIndex + 1 === questions.length ? 'See Results' : 'Next Question'"></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- STATE 3: Finished / Results Screen -->
        <div x-show="state === 'finished'" x-cloak class="text-center space-y-6 py-6">
            <div class="w-20 h-20 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-3xl mx-auto">
                🎉
            </div>
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">
                    Challenge Completed
                </span>
                <h2 class="text-3xl font-extrabold text-stone-900 mt-2">Well Done!</h2>
                <div class="text-4xl font-extrabold text-terracotta-600 mt-3">
                    <span x-text="score"></span> / <span x-text="questions.length"></span>
                </div>
                <p class="text-stone-600 text-sm max-w-md mx-auto mt-2 leading-relaxed">
                    <template x-if="score === questions.length">
                        <span>Perfect score! You have great intuition for Egyptian colloquial expressions.</span>
                    </template>
                    <template x-if="score < questions.length">
                        <span>Great effort! Practicing these expressions will make your Arabic sound natural and warm.</span>
                    </template>
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                <button type="button"
                        @click="startGame()"
                        class="w-full sm:w-auto px-6 py-3 rounded-full border border-stone-300 text-stone-700 hover:bg-stone-50 font-semibold text-sm transition-colors">
                    🔄 Play Again
                </button>
                <a href="{{ localized_url('booking') }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-sm px-7 py-3 rounded-full shadow-md hover:shadow transition-all">
                    <span>Practice with Abdallah 1-on-1</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
