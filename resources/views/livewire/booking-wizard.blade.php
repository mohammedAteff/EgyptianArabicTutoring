<div>
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6"
     x-data="{
         detected: false,
         init() {
             if (!this.detected) {
                 const detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                 if (detectedTz && detectedTz !== @js($customerTimezone)) {
                     $wire.setDetectedTimezone(detectedTz);
                 }
                 this.detected = true;
             }
         }
     }">

    <!-- Error Alert -->
    @if($errorMessage)
        <div class="mb-6 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="text-sm text-rose-700 font-medium">
                {{ $errorMessage }}
            </div>
        </div>
    @endif

    <!-- Timezone Bar -->
    <div class="mb-8 bg-white border border-stone-200/80 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-nile-50 text-nile-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            @php
                $tzDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($customerTimezone, now());
            @endphp
            <div>
                <div class="text-xs text-stone-500 font-medium">{{ __('Showing times in your timezone:') }}</div>
                <div class="text-sm font-bold text-stone-900 flex items-center gap-2 mt-0.5">
                    <span class="inline-block w-5 h-3.5 shrink-0 shadow-xs border border-stone-200 rounded-xs overflow-hidden" aria-hidden="true">
                        <x-timezone-flag :display="$tzDisplay" class="w-full h-full object-cover" />
                    </span>
                    <span class="sr-only">{{ __('Timezone Country: :country. Timezone:', ['country' => $tzDisplay['timezone_country_name']]) }}</span>
                    <span class="font-medium text-stone-900">{{ $tzDisplay['city'] }}</span>
                    <span class="text-xs text-stone-500 font-normal">({{ $customerTimezone }}, {{ $tzDisplay['utc_offset'] }})</span>
                </div>
            </div>
        </div>
        <button type="button"
                wire:click="$toggle('showTimezoneModal')"
                class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-terracotta-600 hover:text-terracotta-700 bg-terracotta-50 hover:bg-terracotta-100 border border-terracotta-200/60 px-3.5 py-2 rounded-xl transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('Change Timezone') }}</span>
        </button>
    </div>

    <!-- Multi-Step Progress Tracker -->
    <div class="mb-10">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 -translate-y-1/2 h-0.5 bg-stone-200 w-full -z-0"></div>

            <!-- Step 1: Session (if multiple) -->
            @if($activeSessionTypes->count() > 1)
                <button type="button"
                        wire:click="goToStep(1)"
                        class="relative z-10 flex flex-col items-center group">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm transition-all {{ $currentStep >= 1 ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-white border-2 border-stone-300 text-stone-500' }}">
                        1
                    </div>
                    <span class="mt-2 text-xs font-semibold {{ $currentStep >= 1 ? 'text-stone-900' : 'text-stone-400' }}">{{ __('Session') }}</span>
                </button>
            @endif

            <!-- Step 2: Date & Time -->
            <button type="button"
                    wire:click="goToStep(2)"
                    class="relative z-10 flex flex-col items-center group">
                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm transition-all {{ $currentStep >= 2 ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-white border-2 border-stone-300 text-stone-500' }}">
                    {{ $activeSessionTypes->count() > 1 ? '2' : '1' }}
                </div>
                <span class="mt-2 text-xs font-semibold {{ $currentStep >= 2 ? 'text-stone-900' : 'text-stone-400' }}">{{ __('Date & Time') }}</span>
            </button>

            <!-- Step 3: Details -->
            <button type="button"
                    wire:click="goToStep(3)"
                    class="relative z-10 flex flex-col items-center group"
                    @disabled($currentStep < 3)>
                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm transition-all {{ $currentStep >= 3 ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-white border-2 border-stone-300 text-stone-500' }}">
                    {{ $activeSessionTypes->count() > 1 ? '3' : '2' }}
                </div>
                <span class="mt-2 text-xs font-semibold {{ $currentStep >= 3 ? 'text-stone-900' : 'text-stone-400' }}">{{ __('Your Details') }}</span>
            </button>

            <!-- Step 4: Review -->
            <button type="button"
                    wire:click="goToStep(4)"
                    class="relative z-10 flex flex-col items-center group"
                    @disabled($currentStep < 4)>
                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm transition-all {{ $currentStep >= 4 ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-white border-2 border-stone-300 text-stone-500' }}">
                    {{ $activeSessionTypes->count() > 1 ? '4' : '3' }}
                </div>
                <span class="mt-2 text-xs font-semibold {{ $currentStep >= 4 ? 'text-stone-900' : 'text-stone-400' }}">{{ __('Review & Confirm') }}</span>
            </button>
        </div>
    </div>

    <!-- Active Step Content -->

    <!-- STEP 1: Session Selection (Only if multiple sessions) -->
    @if($currentStep === 1 && $activeSessionTypes->count() > 1)
        <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-8 shadow-sm">
            <h2 class="text-xl font-bold text-stone-900 mb-2">{{ __('Select Your Lesson Type') }}</h2>
            <p class="text-stone-600 text-sm mb-6">{{ __('Choose the format that best fits your language goals.') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($activeSessionTypes as $type)
                    <div wire:click="selectSession({{ $type->id }})"
                         class="cursor-pointer border-2 rounded-2xl p-6 transition-all hover:border-terracotta-500 hover:shadow-md {{ $selectedSessionTypeId === $type->id ? 'border-terracotta-500 bg-terracotta-50/30' : 'border-stone-200' }}">
                        <div class="flex items-start justify-between">
                            <h3 class="font-bold text-lg text-stone-900">{{ $type->title }}</h3>
                            <span class="font-extrabold text-terracotta-600 text-lg">${{ number_format($type->price, 2) }}</span>
                        </div>
                        <p class="text-stone-600 text-sm mt-2 leading-relaxed">{{ $type->description }}</p>
                        <div class="mt-4 flex items-center gap-2 text-xs font-semibold text-stone-500">
                            <span>⏱ {{ $type->duration_minutes }} {{ __('Minutes') }}</span>
                            <span>•</span>
                            <span>{{ __('1-on-1 via Zoom/Google Meet') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- STEP 2: Date & Slot Selection -->
    @if($currentStep === 2)
        <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-8 shadow-sm">
            <!-- Header with Session Summary -->
            @if($sessionType)
                <div class="mb-8 pb-6 border-b border-stone-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-terracotta-600">{{ __('Selected Lesson') }}</span>
                        <h2 class="text-xl font-bold text-stone-900">{{ $sessionType->title }}</h2>
                    </div>
                    <div class="flex items-center gap-4 text-sm font-semibold text-stone-700">
                        <span class="bg-stone-100 px-3 py-1 rounded-full">⏱ {{ $sessionType->duration_minutes }} {{ __('min') }}</span>
                        <span class="text-terracotta-600 font-bold">${{ number_format($sessionType->price, 2) }} USD</span>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left: Calendar Widget (7 cols) -->
                <div class="lg:col-span-7">
                    <x-booking-calendar :month="$calendarMonth" :timezone="$customerTimezone" :slots="$availableSlotsByDate" :selected-date="$selectedDate" :livewire="true" />
                </div>

                <!-- Right: Slots for Selected Date (5 cols) -->
                <div class="lg:col-span-5 border-t lg:border-t-0 lg:border-l lg:border-stone-100 lg:pl-8 pt-6 lg:pt-0">
                    <h3 class="font-bold text-stone-900 text-base mb-1">
                        @if($selectedDate)
                            {{ __('Available Times for :date', ['date' => \Carbon\CarbonImmutable::parse($selectedDate)->format('l, M j')]) }}
                        @else
                            {{ __('Select a Date') }}
                        @endif
                    </h3>
                    <p class="text-xs text-stone-500 mb-4">
                        {{ __('Slots show in your local time with the tutor timezone equivalent.') }}
                    </p>

                    @if($selectedDate && !empty($availableSlotsByDate[$selectedDate]))
                        <div class="space-y-2.5 max-h-[380px] overflow-y-auto pr-1">
                            @foreach($availableSlotsByDate[$selectedDate] as $slot)
                                @php
                                    $slotInstant = \Carbon\CarbonImmutable::parse($slot['slot_start_utc'], 'UTC');
                                    $slotCustomerDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($customerTimezone, $slotInstant);
                                    $businessTimezone = $slot['business_timezone'] ?? 'Africa/Cairo';
                                    $businessSlotStart = $slotInstant->setTimezone($businessTimezone);
                                    $businessSlotEnd = \Carbon\CarbonImmutable::parse($slot['slot_end_utc'], 'UTC')->setTimezone($businessTimezone);
                                @endphp
                                <button type="button"
                                        wire:key="booking-slot-{{ $slot['slot_start_utc'] }}"
                                        wire:click="selectSlot('{{ $slot['slot_id'] }}')"
                                        wire:loading.attr="disabled"
                                        class="w-full text-left p-3.5 rounded-xl border border-stone-200 hover:border-terracotta-500 hover:bg-terracotta-50/50 hover:shadow-sm transition-all flex items-center justify-between group">
                                    <div>
                                        <div class="font-bold text-sm text-stone-900 group-hover:text-terracotta-600">
                                            {{ $slot['customer_formatted'] }} - {{ $slot['customer_formatted_end'] }}
                                        </div>
                                        <div class="text-xs text-stone-600 mt-1">
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-timezone-flag :display="$slotCustomerDisplay" :alt="__('Flag for :country', ['country' => $slotCustomerDisplay['timezone_country_name']])" />
                                                {{ $slotCustomerDisplay['city'] }} · {{ $customerTimezone }} · {{ $slotCustomerDisplay['utc_offset'] }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-stone-500 mt-0.5">
                                            {{ __('Tutor equivalent: :start – :end', ['start' => $businessSlotStart->format('g:i A'), 'end' => $businessSlotEnd->format('g:i A')]) }} · {{ $businessTimezone }}
                                        </div>
                                    </div>
                                    <div class="text-xs font-semibold text-terracotta-600 group-hover:translate-x-0.5 transition-transform flex items-center gap-1">
                                        <span>{{ __('Select') }}</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @elseif($selectedDate)
                        <div class="p-6 text-center bg-stone-50 rounded-2xl border border-stone-200 text-stone-500 text-sm">
                            {{ __('No available slots remaining on this day. Please select another highlighted day.') }}
                        </div>
                    @else
                        <div class="p-8 text-center bg-stone-50 rounded-2xl border border-dashed border-stone-200 text-stone-400 text-sm">
                            👈 {{ __('Click any highlighted date on the calendar to see open lesson slots.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- STEP 3: Student Contact Details Form -->
    @if($currentStep === 3)
        <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-8 shadow-sm">
            <!-- Hold Status Banner -->
            <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping"></span>
                    <span class="text-xs sm:text-sm font-semibold text-amber-900">
                        {{ __('Slot temporarily reserved for you (10 min hold).') }}
                    </span>
                </div>
                <div class="text-xs text-amber-800 font-medium">
                    @if($selectedSlot)
                        @php
                            $heldSlotDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($customerTimezone, \Carbon\CarbonImmutable::parse($selectedSlotStartUtc, 'UTC'));
                        @endphp
                        <span class="inline-flex items-center gap-1.5">
                            <x-timezone-flag :display="$heldSlotDisplay" :alt="$heldSlotDisplay['city']" />
                            {{ $selectedSlot['customer_formatted'] ?? ($selectedSlot['start_formatted'] ?? '') }} – {{ $selectedSlot['customer_formatted_end'] ?? '' }} ({{ $heldSlotDisplay['label'] }})
                        </span>
                    @endif
                </div>
            </div>

            <h2 class="text-xl font-bold text-stone-900 mb-1">{{ __('Your Information') }}</h2>
            <p class="text-stone-600 text-sm mb-6">{{ __('Enter your details so your tutor can send your meeting invite and prepare for your session.') }}</p>

            <form wire:submit.prevent="submitDetails" class="space-y-5">
                <!-- Honeypot anti-spam field -->
                <input type="text" wire:model="honeypot" class="hidden" tabindex="-1" autocomplete="off">

                <!-- First & Last Name -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                            {{ __('First Name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="first_name"
                               wire:model.live.debounce.250ms="first_name"
                               required
                               placeholder="e.g. Sarah"
                               class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('first_name') border-rose-400 @enderror">
                        @error('first_name') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                            {{ __('Last Name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="last_name"
                               wire:model.live.debounce.250ms="last_name"
                               required
                               placeholder="e.g. Jenkins"
                               class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('last_name') border-rose-400 @enderror">
                        @error('last_name') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Date of Birth -->
                <div>
                    <label for="date_of_birth" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('Date of Birth') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="date"
                           id="date_of_birth"
                           wire:model.live.debounce.250ms="date_of_birth"
                           required
                           max="{{ now()->subYear()->toDateString() }}"
                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('date_of_birth') border-rose-400 @enderror">
                    <p class="text-xs text-stone-500 mt-1">{{ __('Required for your student portal identity verification.') }}</p>
                    @error('date_of_birth') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('Email Address') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           wire:model.live.debounce.250ms="email"
                           required
                           placeholder="sarah@example.com"
                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all @error('email') border-rose-400 @enderror">
                    <p class="text-xs text-stone-500 mt-1">{{ __('Your calendar invite (.ics) and confirmation link will be delivered here.') }}</p>
                    @error('email') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Phone / WhatsApp -->
                <div>
                    <label for="phone" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('WhatsApp or Phone Number') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel"
                           id="phone"
                           wire:model.live.debounce.250ms="phone"
                           required
                           placeholder="+1 555 123 4567"
                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all">
                    @error('phone') <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                    <p class="text-xs text-stone-500 mt-1">{{ __('Useful for last-minute lesson links or audio check-ins.') }}</p>
                </div>

                <!-- Dynamic Intake Form Questions (if assigned) -->
                @error('intakeForm') <p role="alert" class="text-sm text-rose-600">{{ $message }}</p> @enderror
                @if(!empty($preBookingQuestions) && $preBookingQuestions->isNotEmpty())
                    <div class="pt-4 border-t border-stone-100 space-y-4">
                        <h3 class="text-sm font-bold text-stone-800 uppercase tracking-wider">{{ __('Intake Questions') }}</h3>
                        @foreach($preBookingQuestions as $q)
                            <div wire:key="intake-question-{{ $q->id }}">
                                <label for="intake_{{ $q->id }}" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                                    {{ $q->label }} @if($q->is_required) <span class="text-rose-500">*</span> @endif
                                </label>
                                @if($q->description)<p class="mb-2 text-sm text-stone-600 whitespace-pre-line">{{ $q->description }}</p>@endif
                                @if($q->question_type === 'info_block')
                                @elseif($q->question_type === 'long_text')
                                    <textarea id="intake_{{ $q->id }}"
                                              wire:model.live.debounce.250ms="intakeAnswers.{{ $q->id }}"
                                              rows="3"
                                              class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all"></textarea>
                                @elseif(in_array($q->question_type, ['single_choice', 'dropdown'], true))
                                    <select id="intake_{{ $q->id }}"
                                            wire:model.live="intakeAnswers.{{ $q->id }}"
                                            class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all">
                                        <option value="">{{ __('Select an option') }}</option>
                                        @foreach($q->options ?? [] as $opt)
                                            <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                                        @endforeach
                                    </select>
                                @elseif($q->question_type === 'multiple_choice')
                                    <div class="space-y-2" role="group" aria-label="{{ $q->label }}">
                                        @foreach($q->options as $opt)
                                            <label class="flex items-center gap-2 text-sm" wire:key="intake-option-{{ $opt->id }}"><input type="checkbox" wire:model.live="intakeAnswers.{{ $q->id }}" value="{{ $opt->value }}">{{ $opt->label }}</label>
                                        @endforeach
                                    </div>
                                @elseif($q->question_type === 'yes_no')
                                    <select id="intake_{{ $q->id }}" wire:model.live="intakeAnswers.{{ $q->id }}" class="w-full px-4 py-3 rounded-xl border border-stone-200 text-sm"><option value="">{{ __('Select an option') }}</option><option value="1">{{ __('Yes') }}</option><option value="0">{{ __('No') }}</option></select>
                                @elseif($q->question_type === 'rating_scale')
                                    <select id="intake_{{ $q->id }}" wire:model.live="intakeAnswers.{{ $q->id }}" class="w-full px-4 py-3 rounded-xl border border-stone-200 text-sm">
                                        <option value="">{{ __('Select a rating') }}</option>
                                        @for($rating = ($q->presentation_config['min'] ?? 1); $rating <= ($q->presentation_config['max'] ?? 5); $rating++)<option value="{{ $rating }}">{{ $rating }}</option>@endfor
                                    </select>
                                @else
                                    <input type="{{ in_array($q->question_type, ['email', 'number', 'date'], true) ? $q->question_type : ($q->question_type === 'phone' ? 'tel' : 'text') }}"
                                           id="intake_{{ $q->id }}"
                                           wire:model.live.debounce.250ms="intakeAnswers.{{ $q->id }}"
                                           @if($q->question_type === 'number') step="any" @endif
                                           class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all">
                                @endif
                                @error('intakeAnswers.'.$q->id) <span class="text-xs text-rose-500 font-medium mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Learning Goals / Notes -->
                <div>
                    <label for="notes" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-2">
                        {{ __('Arabic Level & Learning Goals') }} <span class="text-stone-400 text-xs font-normal">({{ __('Optional') }})</span>
                    </label>
                    <textarea id="notes"
                              wire:model.live.debounce.250ms="notes"
                              rows="3"
                              placeholder="{{ __('Tell Abdallah about your Arabic background, goals (e.g. travel, dialect, conversation), or specific topics you want to practice.') }}"
                              class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-200 outline-none text-stone-900 text-sm transition-all"></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex items-center justify-between border-t border-stone-100">
                    <button type="button"
                            wire:click="goToStep(2)"
                            class="text-sm font-semibold text-stone-600 hover:text-stone-900 px-4 py-2.5 rounded-xl hover:bg-stone-50 transition-colors">
                        ← {{ __('Change Slot') }}
                    </button>
                    <button type="submit"
                            class="bg-terracotta-500 hover:bg-terracotta-600 text-white font-semibold text-sm px-6 py-3 rounded-xl shadow-sm hover:shadow transition-all flex items-center gap-2">
                        <span>{{ __('Continue to Review') }}</span>
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
        <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-8 shadow-sm">
            <h2 class="text-xl font-bold text-stone-900 mb-1">{{ __('Review & Confirm Your Lesson') }}</h2>
            <p class="text-stone-600 text-sm mb-6">{{ __('Please verify your booking details before final confirmation.') }}</p>

            <div class="bg-stone-50 rounded-2xl border border-stone-200/80 p-6 mb-6 space-y-6">
                <!-- Dual Timezone Breakdown (Spec Requirement) -->
                @php
                    $reviewInstant = $selectedSlotStartUtc ? \Carbon\CarbonImmutable::parse($selectedSlotStartUtc, 'UTC') : now('UTC');
                    $reviewCustomerDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($customerTimezone, $reviewInstant);
                    $reviewBusinessTimezone = $selectedSlot['business_timezone'] ?? 'Africa/Cairo';
                    $reviewBusinessDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($reviewBusinessTimezone, $reviewInstant);
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-stone-200">
                    <div class="bg-white p-4 rounded-xl border border-stone-200">
                        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                            <x-timezone-flag :display="$reviewCustomerDisplay" :alt="$reviewCustomerDisplay['city']" />
                            {{ __('Your Local Time') }}
                        </div>
                        <div class="text-base font-bold text-stone-900">
                            {{ \Carbon\CarbonImmutable::parse($selectedDate)->format('l, F j, Y') }}
                        </div>
                        <div class="text-lg font-extrabold text-terracotta-600 mt-1">
                            {{ $selectedSlot['customer_formatted'] ?? '' }} - {{ $selectedSlot['customer_formatted_end'] ?? '' }}
                        </div>
                        <div class="text-xs text-stone-500 mt-1 font-medium">
                            {{ __('Timezone:') }} {{ $reviewCustomerDisplay['label'] }}
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-stone-200">
                        <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                            <x-timezone-flag :display="$reviewBusinessDisplay" :alt="$reviewBusinessDisplay['city']" />
                            {{ __("Tutor's Time") }} ({{ $reviewBusinessTimezone }})
                        </div>
                        <div class="text-base font-bold text-stone-900">
                            {{ \Carbon\CarbonImmutable::parse($selectedSlot['business_date'] ?? $selectedDate)->format('l, F j, Y') }}
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
                        <div class="font-bold text-stone-900">{{ $sessionType->title }}</div>
                        <div class="text-sm text-stone-600 mt-0.5">⏱ {{ $sessionType->duration_minutes }} {{ __('Minutes • 1-on-1') }}</div>
                        <div class="text-sm font-semibold text-terracotta-600 mt-1">${{ number_format($sessionType->price, 2) }} USD</div>
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

                @if($notes)
                    <div class="pt-4 border-t border-stone-200 text-sm">
                        <span class="font-bold text-stone-700">{{ __('Notes for Tutor:') }}</span>
                        <p class="text-stone-600 mt-1 italic">"{{ $notes }}"</p>
                    </div>
                @endif
            </div>

            <!-- Booking Policy Notice -->
            <div class="mb-4 p-4 rounded-xl bg-stone-100/70 text-xs text-stone-600 leading-relaxed">
                <strong>{{ __('Policy Notice:') }}</strong> {{ \App\Domains\CMS\Models\Setting::get('cancellation_policy', 'Cancellations with at least 4 hours notice do not forfeit the session credit. Rescheduling requests must be made directly to Abdallah at least 24 hours before class.') }}
                {{ __('Upon confirmation, you will receive an immediate calendar invitation with direct lesson access links.') }}
            </div>

            <!-- Student Portal Verification Notice -->
            <div class="mb-6 p-4 rounded-xl bg-amber-50/70 border border-amber-200/60 text-xs text-amber-900 leading-relaxed">
                <strong>{{ __('Student Portal Access:') }}</strong> {{ __('If this is your first session, your student portal access will become active once your student profile has been verified. To log in at /student/login, you will need your Date of Birth plus any two of your registered identifiers: your name, email address, or phone number.') }}
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between border-t border-stone-100 pt-6">
                <button type="button"
                        wire:click="goToStep(3)"
                        class="text-sm font-semibold text-stone-600 hover:text-stone-900 px-4 py-2.5 rounded-xl hover:bg-stone-50 transition-colors">
                    ← {{ __('Edit Details') }}
                </button>
                <button type="button"
                        wire:click="confirmBooking"
                        wire:loading.attr="disabled"
                        class="bg-terracotta-500 hover:bg-terracotta-600 disabled:opacity-50 text-white font-bold text-sm px-8 py-3.5 rounded-full shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                    <span wire:loading.remove>{{ __('Confirm & Book Lesson') }}</span>
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

    <!-- Timezone Selector Modal -->
    @if($showTimezoneModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="timezone-selector-title" class="bg-white rounded-3xl border border-stone-200 max-w-lg w-full p-6 shadow-2xl space-y-4"
                 @keydown.escape.window="$wire.set('showTimezoneModal', false)"
                 @click.away="$wire.set('showTimezoneModal', false)">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <h3 id="timezone-selector-title" class="text-lg font-bold text-stone-900">{{ __('Select Your Timezone') }}</h3>
                    <button type="button"
                            wire:click="$set('showTimezoneModal', false)"
                            class="text-stone-400 hover:text-stone-600 p-1 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Search Input -->
                <div>
                    <input type="text"
                           wire:model.live.debounce.150ms="timezoneSearch"
                           placeholder="{{ __('Type a city or region (e.g. Cairo, Berlin, London, New York)...') }}"
                           class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-sm focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-100 outline-none">
                </div>

                <!-- Timezone List -->
                <div class="max-h-72 overflow-y-auto space-y-1 divide-y divide-stone-50">
                    @forelse($curatedTimezones as $tz)
                        @php
                            $selectorDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($tz['id'], now('UTC'));
                        @endphp
                        <button type="button"
                                wire:key="timezone-option-{{ $tz['id'] }}"
                                aria-pressed="{{ $customerTimezone === $tz['id'] ? 'true' : 'false' }}"
                                wire:click="selectTimezone('{{ $tz['id'] }}')"
                                class="w-full text-left px-3 py-2.5 rounded-xl text-sm flex items-center justify-between hover:bg-stone-50 transition-colors {{ $customerTimezone === $tz['id'] ? 'bg-terracotta-50 font-bold text-terracotta-700' : 'text-stone-700' }}">
                            <span class="inline-flex items-center gap-2">
                                <x-timezone-flag :display="$selectorDisplay" :alt="__('Flag for :country', ['country' => $selectorDisplay['timezone_country_name']])" />
                                <span>{{ $selectorDisplay['label'] }}</span>
                            </span>
                            <span class="text-xs text-stone-400 font-mono">{{ $selectorDisplay['utc_offset'] }}</span>
                        </button>
                    @empty
                        <div class="text-center py-6 text-xs text-stone-400">
                            {{ __('No matching timezones found. Try searching for a major capital or city.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
</div>
