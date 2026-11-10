<div>
    <div class="booking-wizard"
     x-data="{
         detected: false,
         stopFeedbackListener: null,
         init() {
             this.stopFeedbackListener = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                 if (message.component.id !== this.$wire.$id) return;
                 const previousStep = this.$wire.currentStep;
                 const previousError = this.$wire.errorMessage;
                 const submitted = Array.from(message.actions).some(action => ['submitDetails', 'confirmBooking'].includes(action.name));
                 const selectedDate = Array.from(message.actions).some(action => action.name === 'selectDate');
                 onSuccess(({ onMorphed }) => onMorphed(() => this.$nextTick(() => requestAnimationFrame(() => {
                     let target = null;
                     if (this.$wire.errorMessage && (previousError !== this.$wire.errorMessage || submitted || selectedDate || previousStep !== this.$wire.currentStep)) {
                         target = this.$el.querySelector('[data-booking-error]');
                     } else if (previousStep !== this.$wire.currentStep) {
                         target = this.$el.querySelector('[data-stage-heading]');
                     } else if (submitted) {
                         target = this.$el.querySelector('[data-validation-summary]');
                     } else if (selectedDate && this.$wire.selectedDate && !this.$wire.errorMessage && !this.$el.querySelector('[data-validation-summary]') && window.matchMedia('(max-width: 420px)').matches) {
                         target = this.$el.querySelector('[data-available-times-heading]');
                         target?.focus({ preventScroll: true });
                         target?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
                         return;
                     }
                     target?.focus();
                 }))));
             });
             if (this.$wire.errorMessage) {
                 this.$nextTick(() => this.$el.querySelector('[data-booking-error]')?.focus());
             }
             if (!this.detected) {
                 const detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                 if (detectedTz && detectedTz !== @js($customerTimezone)) {
                     $wire.setDetectedTimezone(detectedTz);
                 }
                 this.detected = true;
             }
         },
         destroy() { this.stopFeedbackListener?.(); }
     }">

    <!-- Error Alert -->
    @if($errorMessage)
        <div data-booking-error role="alert" tabindex="-1" class="booking-error mb-6 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="text-sm text-rose-700 font-medium">
                {{ __($errorMessage) }}
            </div>
        </div>
    @endif

    @if($errors->any())
        <div data-validation-summary class="booking-error" role="alert" tabindex="-1" wire:key="booking-errors">
            <h2>{{ __('Please check your details') }}</h2>
            <ul>
                @foreach($errors->getMessages() as $field => $messages)
                    @php
                        $errorTarget = str_starts_with($field, 'intakeAnswers.') ? 'intake_'.explode('.', $field)[1] : (in_array($field, ['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'notes', 'intakeForm'], true) ? $field : null);
                    @endphp
                    <li wire:key="booking-error-{{ $field }}">@if($errorTarget)<a href="#{{ $errorTarget }}" @click.prevent="document.getElementById(@js($errorTarget))?.focus()" class="underline">{{ __($messages[0]) }}</a>@else{{ __($messages[0]) }}@endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($activeSessionTypes->isNotEmpty())
        @php
            $steps = $activeSessionTypes->count() > 1
                ? [1 => __('Session'), 2 => __('Date & Time'), 3 => __('Your Details'), 4 => __('Review & Confirm')]
                : [2 => __('Date & Time'), 3 => __('Your Details'), 4 => __('Review & Confirm')];
        @endphp
        <nav class="booking-progress" aria-label="{{ __('Booking progress') }}">
            <ol>
                @foreach($steps as $step => $label)
                    <li wire:key="booking-progress-{{ $step }}" @class(['is-current' => $currentStep === $step, 'is-complete' => $currentStep > $step])>
                        <button type="button" wire:click="goToStep({{ $step }})" wire:loading.attr="disabled" @disabled($step >= $currentStep)
                                @if($currentStep === $step) aria-current="step" @endif>
                            <span class="booking-step-number" aria-hidden="true">{{ $currentStep > $step ? '✓' : $loop->iteration }}</span>
                            <span>{{ __($label) }}<span class="sr-only"> — {{ $currentStep === $step ? __('Current step') : ($currentStep > $step ? __('Completed') : __('Upcoming')) }}</span></span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>
    @else
        <section class="booking-panel booking-empty" role="status">
            <h2 data-stage-heading tabindex="-1">{{ __('No sessions are available to book right now.') }}</h2>
            <p>{{ __('Please check back later or contact Abdallah using an official contact channel.') }}</p>
            <a href="{{ localized_url('contact') }}" class="studio-button studio-button-secondary">{{ __('Contact Abdallah') }}</a>
        </section>
    @endif

    @if($currentStep > 2)
        <x-timezone-selector :timezone="$customerTimezone" :livewire="true" variant="public" />
    @endif
    <p wire:offline class="booking-error" role="status">{{ __('You are offline. Reconnect before continuing; your reservation will still expire at its original time.') }}</p>
    <p class="booking-loading" wire:loading.delay role="status">{{ __('Updating your booking…') }}</p>

    <!-- Active Step Content -->

    <!-- STEP 1: Session Selection (Only if multiple sessions) -->
    @if($currentStep === 1 && $activeSessionTypes->count() > 1)
        <div class="booking-panel">
            <h2 data-stage-heading tabindex="-1" class="text-xl font-bold text-stone-900 mb-2">{{ __('Choose Your Session') }}</h2>
            <p class="text-stone-600 text-sm mb-6">{{ __("Every learner starts somewhere different. Select the session that's right for you.") }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($activeSessionTypes as $type)
                    <button type="button" aria-pressed="{{ $selectedSessionTypeId === $type->id ? 'true' : 'false' }}" wire:loading.attr="disabled" wire:key="booking-session-{{ $type->id }}" wire:click="selectSession({{ $type->id }})"
                         class="booking-session-option text-left cursor-pointer border-2 rounded-2xl p-6 transition-all hover:border-terracotta-500 hover:shadow-md {{ $selectedSessionTypeId === $type->id ? 'border-terracotta-500 bg-terracotta-50/30' : 'border-stone-200' }}">
                        @if($type->slug === 'diagnostic-session')<p class="mb-2 text-xs font-bold uppercase tracking-wide text-terracotta-600">{{ __('Start here · New students') }}</p>@endif
                        <div class="flex items-start justify-between gap-4">
                            <span class="booking-session-title block font-bold text-lg text-stone-900">{{ __($type->title) }}</span>
                            <span class="font-extrabold text-terracotta-600 text-lg">{{ localized_number($type->price, 2) }} {{ $type->currency }}</span>
                        </div>
                        <p class="text-stone-600 text-sm mt-2 leading-relaxed">{{ $type->slug === 'diagnostic-session' ? website_text('booking', 'A friendly introduction, level assessment, and learning consultation for Egyptian Arabic, Modern Standard Arabic (MSA), or both.') : $type->description }}</p>
                        <div class="mt-4 flex items-center gap-2 text-xs font-semibold text-stone-500">
                            <span>⏱ {{ $type->duration_minutes }} {{ __('Minutes') }}</span>
                            <span>•</span>
                            <span>{{ __('Private online session') }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <!-- STEP 2: Date & Slot Selection -->
    @if($currentStep === 2 && $activeSessionTypes->isNotEmpty())
        <div class="booking-panel">
            <!-- Header with Session Summary -->
            @if($sessionType)
                <div class="booking-session-summary mb-8 pb-6 border-b border-stone-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-terracotta-600">{{ __('Selected Session') }}</span>
                        <h2 data-stage-heading tabindex="-1" class="text-xl font-bold text-stone-900">{{ __($sessionType->title) }}</h2>
                        @if($sessionType->slug !== 'diagnostic-session' && $sessionType->description)
                            <details class="studio-read-more booking-session-description"><summary>{{ __('About this session') }}</summary><p>{{ __($sessionType->description) }}</p></details>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 text-sm font-semibold text-stone-700">
                        <span class="bg-stone-100 px-3 py-1 rounded-full">⏱ {{ $sessionType->duration_minutes }} {{ __('min') }}</span>
                        <span class="text-terracotta-600 font-bold">{{ localized_number($sessionType->price, 2) }} {{ $sessionType->currency }}</span>
                    </div>
                </div>
            @endif

            <x-timezone-selector :timezone="$customerTimezone" :livewire="true" variant="public" />
            @php
                $monthHasSlots = collect($availableSlotsByDate)->contains(fn ($dateSlots, $date) => str_starts_with($date, $calendarMonth.'-') && $date >= now($customerTimezone)->toDateString() && !empty($dateSlots));
            @endphp
            <div @class(['booking-schedule-grid', 'booking-schedule-empty' => !$monthHasSlots]) wire:loading.remove wire:target="previousMonth,nextMonth,selectTimezone,setDetectedTimezone,selectSession">
                <!-- Left: Calendar Widget (7 cols) -->
                <div class="booking-calendar-column">
                    <x-booking-calendar :month="$calendarMonth" :timezone="$customerTimezone" :slots="$availableSlotsByDate" :selected-date="$selectedDate" :livewire="true" :can-view-next-month="$canViewNextMonth" variant="public" />
                </div>

                @if(!$monthHasSlots)
                    <section class="booking-empty booking-month-empty" role="status">
                        <h3>{{ __('No available times this month.') }}</h3>
                        @if($canViewNextMonth)
                            <p>{{ __('You can check another month for a suitable time.') }}</p>
                            <button type="button" wire:click="nextMonth" wire:loading.attr="disabled" class="studio-button studio-button-primary">{{ __('View Next Month') }}</button>
                        @else
                            <p>{{ __('No further months are currently open for booking. Please check back later.') }}</p>
                        @endif
                    </section>
                @else
                <!-- Right: Slots for Selected Date (5 cols) -->
                <div class="booking-times" aria-live="polite" aria-atomic="true" wire:loading.remove wire:target="selectDate">
                    <h3 data-available-times-heading tabindex="-1" class="font-bold text-stone-900 text-base mb-1">
                        @if($selectedDate)
                            {{ __('Available Times for :date', ['date' => \Carbon\CarbonImmutable::parse($selectedDate)->locale(app()->getLocale())->translatedFormat(localized_date_format('l, M j'))]) }}
                        @else
                            {{ __('Available lesson times') }}
                        @endif
                    </h3>
                    <p class="text-xs text-stone-500 mb-4">
                        @if($selectedDate)
                            {{ __('Choosing a time reserves it temporarily and opens Your Details. Times include the tutor timezone equivalent.') }}
                        @else
                            {{ __('Choose an available date to see times. Choosing a time reserves it temporarily and opens Your Details.') }}
                        @endif
                    </p>

                    @if($selectedDate && !empty($availableSlotsByDate[$selectedDate]))
                        <div class="booking-slot-list">
                            @foreach($availableSlotsByDate[$selectedDate] as $slot)
                                <button type="button" wire:key="booking-slot-{{ $slot['slot_start_utc'] }}" wire:click="selectSlot('{{ $slot['slot_id'] }}')" wire:loading.attr="disabled" class="booking-slot-option w-full text-left p-3.5 rounded-xl border border-stone-200 hover:border-terracotta-500 hover:bg-terracotta-50/50 transition-all">
                                    <x-booking-slot :appointment="$slot" :timezone="$customerTimezone" />
                                </button>
                            @endforeach
                        </div>
                    @elseif($selectedDate)
                        <div class="p-6 text-center bg-stone-50 rounded-2xl border border-stone-200 text-stone-500 text-sm">
                            {{ __('No times remain on this date. Choose another available date or try another month.') }}
                        </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    @endif

    <!-- STEP 3: Student Contact Details Form -->
    @if($currentStep === 3)
        <div class="booking-panel">
            <!-- Hold Status Banner -->
            <div class="booking-hold mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true">◷</span>
                    <span class="text-xs sm:text-sm font-semibold text-amber-900">
                        {{ __('Your selected time is temporarily reserved.') }}
                        @if($holdExpiresAt)<span class="booking-hold-expiry">{{ __('Reservation expires at :time in :timezone.', ['time' => \Carbon\CarbonImmutable::parse($holdExpiresAt)->setTimezone($customerTimezone)->locale(app()->getLocale())->translatedFormat(localized_date_format('g:i A')), 'timezone' => $customerTimezone]) }}</span>@endif
                    </span>
                </div>
                <div class="text-xs text-amber-800 font-medium">
                    @if($selectedSlot)
                        @php
                            $heldSlotDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($customerTimezone, \Carbon\CarbonImmutable::parse($selectedSlotStartUtc, 'UTC'));
                        @endphp
                        <span class="inline-flex items-center gap-1.5">
                            <x-timezone-flag :display="$heldSlotDisplay" :alt="$heldSlotDisplay['city']" />
                            {{ localized_date(\Carbon\CarbonImmutable::parse($selectedSlotStartUtc, 'UTC')->setTimezone($customerTimezone), 'g:i A') }} – {{ localized_date(\Carbon\CarbonImmutable::parse($selectedSlotEndUtc, 'UTC')->setTimezone($customerTimezone), 'g:i A') }} ({{ $heldSlotDisplay['label'] }})
                        </span>
                    @endif
                </div>
            </div>

            <h2 data-stage-heading tabindex="-1" class="text-xl font-bold text-stone-900 mb-1">{{ __('Tell Me a Little About Yourself.') }}</h2>
            <p class="text-stone-600 text-sm mb-6">{{ __("A few details will help Abdallah prepare for your session and contact you with the information you need. You don't need to know any Arabic before getting started.") }}</p>

            <form wire:submit.prevent="submitDetails" novalidate class="space-y-5">
                <!-- Honeypot anti-spam field -->
                <input type="text" wire:model="honeypot" class="hidden" tabindex="-1" autocomplete="off">

                <fieldset class="booking-fieldset">
                <legend>{{ __('Your Contact Details') }}</legend>
                <!-- First & Last Name -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                            {{ __('First Name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="first_name" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('first_name') ? 'first_name_error' : '' }}" autocomplete="given-name"
                               wire:model.live.debounce.250ms="first_name"
                               required
                               placeholder="{{ __('e.g. Sarah') }}"
                               class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('first_name') border-rose-400 @enderror">
                        @error('first_name') <span id="first_name_error" class="text-xs text-rose-500 font-medium mt-1 block">{{ __($message) }}</span> @enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                            {{ __('Last Name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="last_name" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('last_name') ? 'last_name_error' : '' }}" autocomplete="family-name"
                               wire:model.live.debounce.250ms="last_name"
                               required
                               placeholder="{{ __('e.g. Jenkins') }}"
                               class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('last_name') border-rose-400 @enderror">
                        @error('last_name') <span id="last_name_error" class="text-xs text-rose-500 font-medium mt-1 block">{{ __($message) }}</span> @enderror
                    </div>
                </div>

                <!-- Date of Birth -->
                <div>
                    <label for="date_of_birth" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('Date of Birth') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="date"
                           id="date_of_birth" aria-invalid="{{ $errors->has('date_of_birth') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('date_of_birth') ? 'date_of_birth_error' : '' }} date_of_birth_help" autocomplete="bday"
                           wire:model.live.debounce.250ms="date_of_birth"
                           required
                           max="{{ now()->subYear()->toDateString() }}"
                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('date_of_birth') border-rose-400 @enderror">
                    <p id="date_of_birth_help" class="text-xs text-stone-500 mt-1">{{ __('Used to help verify your identity when accessing your student portal.') }}</p>
                    @error('date_of_birth') <span id="date_of_birth_error" class="text-xs text-rose-500 font-medium mt-1 block">{{ __($message) }}</span> @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('Email Address') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="email"
                           id="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('email') ? 'email_error' : '' }} email_help" autocomplete="email"
                           wire:model.live.debounce.250ms="email"
                           required
                           placeholder="sarah@example.com"
                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('email') border-rose-400 @enderror">
                    <p id="email_help" class="text-xs text-stone-500 mt-1">{{ __("We'll use this address for booking-related communication.") }}</p>
                    @error('email') <span id="email_error" class="text-xs text-rose-500 font-medium mt-1 block">{{ __($message) }}</span> @enderror
                </div>

                <!-- Phone / WhatsApp -->
                <div>
                    <label for="phone" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('WhatsApp or Phone Number') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel"
                           id="phone" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('phone') ? 'phone_error' : '' }} phone_help" autocomplete="tel"
                           wire:model.live.debounce.250ms="phone"
                           required
                           placeholder="+1 555 123 4567"
                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all">
                    @error('phone') <span id="phone_error" class="text-xs text-rose-500 font-medium mt-1 block">{{ __($message) }}</span> @enderror
                    <p id="phone_help" class="text-xs text-stone-500 mt-1">{{ __('Useful for last-minute lesson links or audio check-ins.') }}</p>
                </div>

                </fieldset>
                <fieldset class="booking-fieldset">
                @php $intakePresentation = $preBookingQuestions->first()?->presentation_config ?? []; @endphp
                <legend lang="{{ app()->getLocale() }}">{{ __($intakePresentation['form_title'] ?? __('Your Arabic Learning Goals')) }}</legend>
                @if(!empty($intakePresentation['form_description']))<p lang="{{ app()->getLocale() }}" class="whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ __($intakePresentation['form_description']) }}</p>@endif
                <!-- Dynamic Intake Form Questions (if assigned) -->
                @error('intakeForm') <p id="intakeForm" tabindex="-1" class="text-sm text-rose-600">{{ __($message) }}</p> @enderror
                @if(!empty($preBookingQuestions) && $preBookingQuestions->isNotEmpty())
                    <div lang="{{ app()->getLocale() }}" class="pt-4 border-t border-stone-100 space-y-4">
                        @foreach($preBookingQuestions as $q)
                            @php
                                $questionErrorKey = collect(array_keys($errors->getMessages()))->first(fn ($key) => $key === 'intakeAnswers.'.$q->id || str_starts_with($key, 'intakeAnswers.'.$q->id.'.'));
                            @endphp
                            <div wire:key="intake-question-{{ $q->id }}">
                                @if($q->question_type === 'multiple_choice')
                                    <div id="intake_{{ $q->id }}_label" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">{{ __($q->label) }} @if($q->is_required)<span aria-hidden="true" class="text-rose-500">*</span><span class="sr-only">{{ __('Required') }}</span>@endif</div>
                                @else
                                    <label for="intake_{{ $q->id }}" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                                        {{ __($q->label) }} @if($q->is_required) <span aria-hidden="true" class="text-rose-500">*</span><span class="sr-only">{{ __('Required') }}</span> @endif
                                    </label>
                                @endif
                                @if($q->description)<p id="intake_{{ $q->id }}_help" class="mb-2 text-sm text-stone-600 whitespace-pre-line">{{ __($q->description) }}</p>@endif
                                @if($q->question_type === 'info_block')
                                @elseif($q->question_type === 'long_text')
                                    <textarea id="intake_{{ $q->id }}" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}"
                                              @required($q->is_required) wire:model.live.debounce.250ms="intakeAnswers.{{ $q->id }}"
                                              rows="3"
                                              class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all"></textarea>
                                @elseif(in_array($q->question_type, ['single_choice', 'dropdown'], true))
                                    <select id="intake_{{ $q->id }}" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}"
                                            @required($q->is_required && $q->question_type !== 'multiple_choice') wire:model.live="intakeAnswers.{{ $q->id }}"
                                            class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all">
                                        <option value="">{{ __('Select an option') }}</option>
                                        @foreach($q->options ?? [] as $opt)
                                            <option wire:key="intake-choice-{{ $opt->id }}" value="{{ $opt->value }}">{{ __($opt->label) }}</option>
                                        @endforeach
                                    </select>
                                @elseif($q->question_type === 'multiple_choice')
                                    <div class="space-y-2" role="group" id="intake_{{ $q->id }}" tabindex="-1" aria-labelledby="intake_{{ $q->id }}_label" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}">
                                        @foreach($q->options as $opt)
                                            <label class="flex items-center gap-2 text-sm" wire:key="intake-option-{{ $opt->id }}"><input type="checkbox" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}" @required($q->is_required && $q->question_type !== 'multiple_choice') wire:model.live="intakeAnswers.{{ $q->id }}" value="{{ $opt->value }}">{{ __($opt->label) }}</label>
                                        @endforeach
                                    </div>
                                @elseif($q->question_type === 'yes_no')
                                    <select id="intake_{{ $q->id }}" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}" @required($q->is_required && $q->question_type !== 'multiple_choice') wire:model.live="intakeAnswers.{{ $q->id }}" class="w-full px-4 py-3 rounded-xl border border-stone-200 text-sm"><option value="">{{ __('Select an option') }}</option><option value="1">{{ __('Yes') }}</option><option value="0">{{ __('No') }}</option></select>
                                @elseif($q->question_type === 'rating_scale')
                                    <select id="intake_{{ $q->id }}" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}" @required($q->is_required && $q->question_type !== 'multiple_choice') wire:model.live="intakeAnswers.{{ $q->id }}" class="w-full px-4 py-3 rounded-xl border border-stone-200 text-sm">
                                        <option value="">{{ __('Select a rating') }}</option>
                                        @for($rating = ($q->presentation_config['min'] ?? 1); $rating <= ($q->presentation_config['max'] ?? 5); $rating++)<option value="{{ $rating }}">{{ $rating }}</option>@endfor
                                    </select>
                                @else
                                    <input type="{{ in_array($q->question_type, ['email', 'number', 'date'], true) ? $q->question_type : ($q->question_type === 'phone' ? 'tel' : 'text') }}"
                                           id="intake_{{ $q->id }}" aria-invalid="{{ $questionErrorKey !== null ? 'true' : 'false' }}" aria-describedby="{{ $questionErrorKey !== null ? 'intake_'.$q->id.'_error' : '' }} {{ $q->description ? 'intake_'.$q->id.'_help' : '' }}"
                                           @required($q->is_required) wire:model.live.debounce.250ms="intakeAnswers.{{ $q->id }}"
                                           @if($q->question_type === 'number') step="any" @endif
                                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all">
                                @endif
                                @error($questionErrorKey ?? 'intakeAnswers.'.$q->id) <span id="intake_{{ $q->id }}_error" class="text-xs text-rose-500 font-medium mt-1 block">{{ __($message) }}</span> @enderror
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Learning Goals / Notes -->
                <div>
                    <label for="notes" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __("Anything else you'd like Abdallah to know?") }} <span class="text-stone-400 text-xs font-normal">({{ __('Optional') }})</span>
                    </label>
                    <textarea id="notes" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('notes') ? 'notes_error' : '' }}"
                              wire:model.live.debounce.250ms="notes"
                              rows="3"
                              placeholder="{{ __('Anything that might help us prepare for your diagnostic...') }}"
                              class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all"></textarea>
                </div>

                </fieldset>
                @error('notes') <span id="notes_error" class="booking-field-error">{{ __($message) }}</span> @enderror
                <!-- Action Buttons -->
                <div class="booking-actions pt-4 flex items-center justify-between border-t border-stone-100">
                    <button type="button"
                            wire:click="goToStep(2)"
                            class="text-sm font-semibold text-stone-600 hover:text-stone-900 px-4 py-2.5 rounded-xl hover:bg-stone-50 transition-colors">
                        ← {{ __('Change Slot') }}
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-terracotta-500 hover:bg-terracotta-600 text-white font-semibold text-sm px-6 py-3 rounded-xl shadow-sm hover:shadow transition-all flex items-center gap-2">
                        <span>{{ __('Review Your Booking') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- STEP 4: Review & Final Confirmation -->
    @if($currentStep === 4)
        <div class="booking-panel">
            <h2 data-stage-heading tabindex="-1" class="text-xl font-bold text-stone-900 mb-1">{{ __('One Last Look Before You Book.') }}</h2>
            <p class="text-stone-600 text-sm mb-6">{{ __('Please review your appointment and contact information before confirming your reservation.') }}</p>

            <div class="bg-stone-50 rounded-2xl border border-stone-200/80 p-6 mb-6 space-y-6">
                <!-- Dual Timezone Breakdown (Spec Requirement) -->
                @php
                    $reviewInstant = $selectedSlotStartUtc ? \Carbon\CarbonImmutable::parse($selectedSlotStartUtc, 'UTC') : now('UTC');
                    $reviewCustomerDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($customerTimezone, $reviewInstant);
                    $reviewBusinessTimezone = $selectedSlot['business_timezone'] ?? app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone();
                    $reviewBusinessDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($reviewBusinessTimezone, $reviewInstant);
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-stone-200">
                    <div class="bg-white p-4 rounded-xl border border-stone-200">
                        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                            <x-timezone-flag :display="$reviewCustomerDisplay" :alt="$reviewCustomerDisplay['city']" />
                            {{ __('Your Local Time') }}
                        </div>
                        <div class="text-base font-bold text-stone-900">
                            {{ localized_date($reviewInstant->setTimezone($customerTimezone), 'l, F j, Y') }}
                        </div>
                        <div class="text-lg font-extrabold text-terracotta-600 mt-1">
                            {{ localized_date($reviewInstant->setTimezone($customerTimezone), 'g:i A') }} - {{ localized_date(\Carbon\CarbonImmutable::parse($selectedSlotEndUtc, 'UTC')->setTimezone($customerTimezone), 'g:i A') }}
                        </div>
                        <div class="text-xs text-stone-500 mt-1 font-medium">
                            {{ __('Timezone:') }} {{ $reviewCustomerDisplay['label'] }} · {{ $customerTimezone }}
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-stone-200">
                        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                            <x-timezone-flag :display="$reviewBusinessDisplay" :alt="$reviewBusinessDisplay['city']" />
                            {{ __("Tutor's Time") }} ({{ $reviewBusinessTimezone }})
                        </div>
                        <div class="text-base font-bold text-stone-900">
                            {{ localized_date(\Carbon\CarbonImmutable::parse($selectedSlot['business_date'] ?? $selectedDate), 'l, F j, Y') }}
                        </div>
                        <div class="text-lg font-extrabold text-nile-700 mt-1">
                            {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->studentTime($reviewInstant->setTimezone($reviewBusinessTimezone)) }} - {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->studentTime(\Carbon\CarbonImmutable::parse($selectedSlotEndUtc, 'UTC')->setTimezone($reviewBusinessTimezone)) }}
                        </div>
                        <div class="text-xs text-stone-500 mt-1 font-medium">
                            {{ __('Timezone:') }} {{ $reviewBusinessDisplay['label'] }}
                        </div>
                    </div>
                </div>

                <!-- Session & Student Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1">{{ __('Lesson Format') }}</div>
                        <div class="font-bold text-stone-900">{{ __($sessionType->title) }}</div>
                        <div class="text-sm text-stone-600 mt-0.5">⏱ {{ $sessionType->duration_minutes }} {{ __('Minutes • 1-on-1') }}</div>
                        <div class="text-sm font-semibold text-terracotta-600 mt-1">{{ localized_number($sessionType->price, 2) }} {{ $sessionType->currency }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1">{{ __('Student Details') }}</div>
                        <div class="font-bold text-stone-900">{{ $name }}</div>
                        <div class="text-sm text-stone-600 mt-0.5">{{ $email }}</div>
                        @if($phone)
                            <div class="text-sm text-stone-600">{{ $phone }}</div>
                        @endif
                    </div>
                </div>

                @if($preBookingQuestions->isNotEmpty())
                    <dl class="booking-intake-review">
                        @foreach($preBookingQuestions as $question)
                            @php
                                $answer = $intakeAnswers[$question->id] ?? $intakeAnswers[$question->question_key] ?? null;
                                $answerValues = is_array($answer) ? $answer : [$answer];
                                $answerLabels = collect($answerValues)->map(function ($value) use ($question) {
                                    if ($question->question_type === 'yes_no') {
                                        return (string) $value === '1' ? __('Yes') : __('No');
                                    }
                                    $option = $question->options->firstWhere('value', $value);
                                    return $option ? __($option->label) : $value;
                                })->implode(', ');
                            @endphp
                            @if($question->question_type !== 'info_block' && $answer !== null && $answer !== '' && $answer !== [])
                                <div wire:key="booking-review-answer-{{ $question->id }}"><dt>{{ __($question->label) }}</dt><dd>{{ $answerLabels }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                @endif

                @if($notes)
                    <div class="pt-4 border-t border-stone-200 text-sm">
                        <span class="font-bold text-stone-700">{{ __('Notes for Tutor:') }}</span>
                        <p class="text-stone-600 mt-1 italic">"{{ $notes }}"</p>
                    </div>
                @endif
            </div>

            <section class="mb-5 rounded-xl border border-stone-200 bg-stone-50 p-5">
                <h3 class="font-semibold">{{ __('Payment Information') }}</h3><p class="mt-2 text-sm leading-7 text-stone-600">{{ __('Payment is arranged after booking. When you confirm, your appointment time will be reserved. Abdallah will contact you personally to arrange your session payment and share the relevant session information. You won\'t be charged automatically when submitting this booking form.') }}</p><p class="mt-2 text-sm">{{ __('Booking confirmation does not mean payment has been received. Booking the Diagnostic does not purchase a coaching package.') }}</p>
            </section>
            <!-- Booking Policy Notice -->
            <div class="mb-4 p-4 rounded-xl bg-stone-100/70 text-xs text-stone-600 leading-relaxed">
                <strong>{{ __('Policy Notice:') }}</strong> {{ __('Cancel at least four hours before your appointment to receive a full refund or arrange another available time by direct contact. Later cancellations and no-shows are ordinarily non-refundable. To request rescheduling, contact Abdallah directly at least 24 hours before your lesson; new appointments must be booked at least 24 hours ahead. Genuine emergencies may be considered individually.') }}
                <a href="{{ route('policy') }}" class="font-semibold underline">{{ __('Read the Booking, Cancellation & Refund Policy') }}</a>
            </div>

            <!-- Student Portal Verification Notice -->
            <div class="mb-6 p-4 rounded-xl bg-amber-50/70 border border-amber-200/60 text-xs text-amber-900 leading-relaxed">
                <strong>{{ __('Student Portal Access:') }}</strong> {{ __('If this is your first session, your student portal access will become active once your student profile has been verified. To log in at /student/login, you will need your Date of Birth plus any two of your registered identifiers: your name, email address, or phone number.') }}
            </div>

            <!-- Action Buttons -->
            <div class="booking-actions flex items-center justify-between border-t border-stone-100 pt-6">
                <button type="button"
                        wire:click="goToStep(3)"
                        class="text-sm font-semibold text-stone-600 hover:text-stone-900 px-4 py-2.5 rounded-xl hover:bg-stone-50 transition-colors">
                    ← {{ __('Edit Details') }}
                </button>
                <button type="button"
                        wire:click="confirmBooking"
                        wire:loading.attr="disabled"
                        class="bg-terracotta-500 hover:bg-terracotta-600 disabled:opacity-50 text-white font-bold text-sm px-8 py-3.5 rounded-full shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                    <span wire:loading.remove>{{ __('Confirm My Booking') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ __('Confirming...') }}</span>
                    </span>
                </button>
            </div>
        </div>
    @endif

</div>
</div>
