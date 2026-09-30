<?php

namespace App\Livewire;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\Form;
use App\Domains\Students\Exceptions\ConcurrentIdentityProvisioningException;
use App\Domains\Students\Exceptions\StudentIdentityConflictException;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BookingWizard extends Component
{
    public int $currentStep = 2; // Step 1: Session, Step 2: Date/Time, Step 3: Details, Step 4: Review

    #[Locked]
    public ?int $selectedSessionTypeId = null;

    #[Locked]
    public ?string $analyticsVisitorToken = null;

    #[Locked]
    public ?string $analyticsSessionToken = null;

    #[Locked]
    public string $customerTimezone = 'Africa/Cairo';

    #[Locked]
    public bool $manualTimezoneSelected = false;

    public ?string $timezoneCountryCode = 'EG';

    public string $calendarMonth; // Y-m format

    #[Locked]
    public ?string $selectedDate = null; // Y-m-d

    #[Locked]
    public ?string $selectedSlotStartUtc = null;

    #[Locked]
    public ?string $selectedSlotEndUtc = null;

    #[Locked]
    public ?string $selectedSlotId = null;

    #[Locked]
    public ?array $selectedSlot = null;

    #[Locked]
    public ?int $holdId = null;

    #[Locked]
    public ?string $holdToken = null;

    #[Locked]
    public ?string $holdExpiresAt = null;

    public string $name = '';

    public string $first_name = '';

    public string $last_name = '';

    public ?string $date_of_birth = null;

    public array $intakeAnswers = [];

    #[Locked]
    public ?int $preBookingFormId = null;

    #[Locked]
    public ?int $initialPublishedVersionId = null;

    public string $email = '';

    public string $phone = '';

    public string $notes = '';

    public string $honeypot = ''; // anti-bot

    #[Locked]
    public string $idempotencyKey = '';

    #[Locked]
    public string $visitorToken = '';

    public ?string $errorMessage = null;

    public bool $showTimezoneModal = false;

    public string $timezoneSearch = '';

    protected function rules(): array
    {
        return [
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'name' => 'nullable|string|max:200',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'notes' => 'nullable|string|max:1000',
            'honeypot' => 'nullable|max:0',
        ];
    }

    protected $messages = [
        'first_name.required' => 'Please provide your first name.',
        'last_name.required' => 'Please provide your last name.',
        'email.required' => 'Please provide a valid email address for your calendar invite.',
        'email.email' => 'Please provide a valid email address.',
        'date_of_birth.required' => 'Please provide your date of birth.',
        'date_of_birth.date' => 'Please provide a valid date of birth.',
        'date_of_birth.before' => 'Date of birth must be in the past.',
        'honeypot.max' => 'Spam detected.',
    ];

    public function mount(): void
    {
        $this->visitorToken = Session::get('_visitor_token', function () {
            $token = Str::random(32);
            Session::put('_visitor_token', $token);

            return $token;
        });

        $this->idempotencyKey = (string) Str::uuid();

        $this->analyticsVisitorToken = Session::get('analytics_visitor_token') ?? request()->cookie('_va_visitor');
        $this->analyticsSessionToken = Session::get('analytics_session_token') ?? request()->cookie('_va_session');

        // Default timezone
        $defaultTz = Setting::get('business_timezone', 'Africa/Cairo');
        $this->customerTimezone = $defaultTz;
        $this->timezoneCountryCode = app(TimezoneDisplayService::class)->resolveCountryCode($this->customerTimezone);
        $this->calendarMonth = now($this->customerTimezone)->format('Y-m');

        // Check active session types
        $activeSessions = SessionType::where('active', true)->get();
        if ($activeSessions->count() === 1) {
            $this->selectedSessionTypeId = $activeSessions->first()->id;
            $this->currentStep = 2; // skip session selection step
        } elseif ($activeSessions->count() > 1) {
            $this->currentStep = 1;
        }

        // Resolve published pre-booking form
        $publishedForm = Form::query()
            ->where('status', 'published')
            ->whereHas('triggers', fn ($q) => $q->where('trigger_name', 'pre_booking'))
            ->first();
        if ($publishedForm) {
            $this->preBookingFormId = $publishedForm->id;
            $this->initialPublishedVersionId = $publishedForm->published_version_id;
        }

        // Restore booking flow state across language switches or page navigations (Section 28)
        $savedState = Session::get('booking_flow_state');
        if (is_array($savedState) && ! empty($savedState['visitor_token'])) {
            if (hash_equals($this->visitorToken, (string) $savedState['visitor_token'])) {
                $this->customerTimezone = $savedState['customer_timezone'] ?? $this->customerTimezone;
                $this->manualTimezoneSelected = (bool) ($savedState['manual_timezone_selected'] ?? false);
                $this->timezoneCountryCode = $savedState['timezone_country_code']
                    ?? app(TimezoneDisplayService::class)->resolveCountryCode($this->customerTimezone);
                $this->calendarMonth = $savedState['calendar_month'] ?? $this->calendarMonth;
                $this->selectedDate = $savedState['selected_date'] ?? null;
                $this->selectedSessionTypeId = $savedState['selected_session_type_id'] ?? $this->selectedSessionTypeId;
                $this->first_name = $savedState['first_name'] ?? '';
                $this->last_name = $savedState['last_name'] ?? '';
                $this->name = $savedState['name'] ?? trim("{$this->first_name} {$this->last_name}");
                if (empty($this->first_name) && ! empty($this->name)) {
                    $parts = explode(' ', trim($this->name), 2);
                    $this->first_name = $parts[0];
                    $this->last_name = $parts[1] ?? $parts[0];
                }
                $this->date_of_birth = $savedState['date_of_birth'] ?? null;
                $this->intakeAnswers = $savedState['intake_answers'] ?? [];
                $this->email = $savedState['email'] ?? '';
                $this->phone = $savedState['phone'] ?? '';
                $this->notes = $savedState['notes'] ?? '';

                if (! empty($savedState['hold_id']) && ! empty($savedState['hold_token'])) {
                    $hold = BookingHold::where('id', $savedState['hold_id'])
                        ->where('status', 'active')
                        ->first();

                    if ($hold &&
                        $hold->expires_at->isFuture() &&
                        hash_equals((string) $hold->hold_token, (string) $savedState['hold_token']) &&
                        hash_equals((string) $hold->visitor_token, (string) $this->visitorToken) &&
                        hash_equals((string) $hold->session_token, (string) session()->getId())
                    ) {
                        $this->holdId = $hold->id;
                        $this->holdToken = $hold->hold_token;
                        $this->holdExpiresAt = $hold->expires_at->toIso8601String();
                        $this->selectedSlotStartUtc = $savedState['selected_slot_start_utc'] ?? null;
                        $this->selectedSlotEndUtc = $savedState['selected_slot_end_utc'] ?? null;
                        $this->selectedSlotId = $savedState['selected_slot_id'] ?? null;
                        $this->selectedSlot = $savedState['selected_slot'] ?? null;
                        $this->currentStep = $savedState['current_step'] ?? 3;
                    } else {
                        $this->holdId = null;
                        $this->holdToken = null;
                        $this->holdExpiresAt = null;
                        $this->selectedSlotStartUtc = null;
                        $this->selectedSlotEndUtc = null;
                        $this->selectedSlotId = null;
                        $this->selectedSlot = null;
                        $this->currentStep = 2;
                        if ($hold && ! $hold->expires_at->isFuture()) {
                            $this->errorMessage = 'Your reserved slot has expired. Please select a time slot to continue.';
                        }
                    }
                } elseif (! empty($savedState['current_step'])) {
                    $this->currentStep = min($savedState['current_step'], 2);
                }
            }
        }
    }

    public function setDetectedTimezone(string $timezone): void
    {
        if ($this->manualTimezoneSelected || $this->holdId) {
            return;
        }

        $timezoneService = app(TimezoneService::class);
        if ($timezoneService->isValid($timezone)) {
            $this->customerTimezone = $timezone;
            $this->timezoneCountryCode = app(TimezoneDisplayService::class)->resolveCountryCode($timezone);
            $this->calendarMonth = $this->selectedDate
                ? CarbonImmutable::createFromFormat('!Y-m-d', $this->selectedDate, $this->customerTimezone)->format('Y-m')
                : now($this->customerTimezone)->format('Y-m');
            $this->syncSessionState();
        }
    }

    public function selectTimezone(string $timezone): void
    {
        $timezoneService = app(TimezoneService::class);
        if ($timezoneService->isValid($timezone)) {
            $this->customerTimezone = $timezone;
            $this->manualTimezoneSelected = true;
            $this->timezoneCountryCode = app(TimezoneDisplayService::class)->resolveCountryCode($timezone);
            $this->showTimezoneModal = false;
            $this->timezoneSearch = '';

            if ($this->holdId) {
                if ($this->selectedSlotStartUtc) {
                    $heldCustomerStart = CarbonImmutable::parse($this->selectedSlotStartUtc, 'UTC')->setTimezone($timezone);
                    $this->selectedDate = $heldCustomerStart->toDateString();
                    $this->calendarMonth = $heldCustomerStart->format('Y-m');
                }

                $this->refreshHeldSlotForTimezone($timezone);
            } else {
                $this->calendarMonth = $this->selectedDate
                    ? CarbonImmutable::createFromFormat('!Y-m-d', $this->selectedDate, $timezone)->format('Y-m')
                    : now($timezone)->format('Y-m');
            }

            $this->syncSessionState();
        }
    }

    private function refreshHeldSlotForTimezone(string $timezone): void
    {
        $hold = BookingHold::query()
            ->whereKey($this->holdId)
            ->where('hold_token', (string) $this->holdToken)
            ->where('visitor_token', $this->visitorToken)
            ->where('session_token', session()->getId())
            ->first();

        if (! $hold
            || $hold->status !== 'active'
            || ! $hold->expires_at->isFuture()
            || ! $this->selectedSlotStartUtc
            || ! $this->selectedSlotEndUtc
            || $hold->slot_start_utc->toDateTimeString() !== $this->selectedSlotStartUtc
            || $hold->slot_end_utc->toDateTimeString() !== $this->selectedSlotEndUtc) {
            $this->clearBookingHoldState('Your reservation has expired or is no longer valid. Please select a time slot again.');

            return;
        }

        $sessionType = SessionType::query()
            ->whereKey($hold->session_type_id)
            ->where('active', true)
            ->first();

        if (! $sessionType) {
            $this->releaseOwnedHold($hold);
            $this->clearBookingHoldState('This lesson type is no longer available. Please select another time slot.');

            return;
        }

        $slotStartUtc = CarbonImmutable::instance($hold->slot_start_utc)->setTimezone('UTC');
        $slotEndUtc = CarbonImmutable::instance($hold->slot_end_utc)->setTimezone('UTC');
        $customerStart = $slotStartUtc->setTimezone($timezone);
        $customerDate = $customerStart->toDateString();
        $customerDay = $customerStart->startOfDay();
        $availableSlots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate(
            sessionType: $sessionType,
            customerTimezone: $timezone,
            fromDate: $customerDay,
            toDate: $customerDay,
            currentVisitorToken: $this->visitorToken,
        );

        $slot = collect($availableSlots[$customerDate] ?? [])->first(fn (array $candidate): bool => $candidate['slot_start_utc'] === $slotStartUtc->toDateTimeString()
            && $candidate['slot_end_utc'] === $slotEndUtc->toDateTimeString()
        );

        if (! $slot) {
            $this->releaseOwnedHold($hold);
            $this->clearBookingHoldState('Your reserved time is no longer available. Please choose another slot.');

            return;
        }

        $slot['slot_id'] = app(SlotResolver::class)->issue(
            $sessionType,
            $slot,
            $timezone,
            $this->visitorToken,
        );

        $this->selectedDate = $customerDate;
        $this->calendarMonth = $customerStart->format('Y-m');
        $this->selectedSlotStartUtc = $slotStartUtc->toDateTimeString();
        $this->selectedSlotEndUtc = $slotEndUtc->toDateTimeString();
        $this->selectedSlotId = $slot['slot_id'];
        $this->selectedSlot = $slot;
        $this->holdExpiresAt = $hold->expires_at->toIso8601String();
    }

    private function releaseOwnedHold(BookingHold $hold): void
    {
        DB::transaction(function () use ($hold): void {
            $lockedHold = BookingHold::query()->whereKey($hold->id)->lockForUpdate()->first();

            if ($lockedHold
                && $lockedHold->status === 'active'
                && hash_equals((string) $lockedHold->hold_token, (string) $this->holdToken)
                && hash_equals((string) $lockedHold->visitor_token, $this->visitorToken)
                && hash_equals((string) $lockedHold->session_token, (string) session()->getId())) {
                $lockedHold->release();
            }
        });
    }

    private function clearBookingHoldState(string $message): void
    {
        $this->holdId = null;
        $this->holdToken = null;
        $this->holdExpiresAt = null;
        $this->selectedSlot = null;
        $this->selectedSlotStartUtc = null;
        $this->selectedSlotEndUtc = null;
        $this->selectedSlotId = null;
        $this->currentStep = 2;
        $this->errorMessage = $message;

        session()->forget([
            'active_booking_hold_id',
            'active_booking_hold_token',
            'active_booking_slot_start_utc',
            'active_booking_slot_end_utc',
            'active_booking_session_type_id',
        ]);
    }

    public function selectSession(int $id): void
    {
        if (! SessionType::query()->whereKey($id)->where('active', true)->exists()) {
            $this->errorMessage = 'Please select a valid session type.';

            return;
        }

        $this->selectedSessionTypeId = $id;
        $this->selectedSlot = null;
        $this->selectedSlotStartUtc = null;
        $this->selectedSlotEndUtc = null;
        $this->selectedSlotId = null;
        $this->currentStep = 2;
    }

    public function previousMonth(): void
    {
        $current = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth, $this->customerTimezone);
        $minMonth = CarbonImmutable::now($this->customerTimezone)->startOfMonth();

        if ($current->greaterThan($minMonth)) {
            $this->calendarMonth = $current->subMonth()->format('Y-m');
            $this->selectedDate = null;
        }
    }

    public function nextMonth(): void
    {
        $current = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth, $this->customerTimezone);
        $maxHorizonDays = (int) (AvailabilityRule::query()->where('enabled', true)->max('max_horizon_days') ?: Setting::get('booking_max_horizon_days', 60));
        $maxMonth = CarbonImmutable::now($this->customerTimezone)->addDays($maxHorizonDays)->endOfMonth();

        if ($current->lessThan($maxMonth)) {
            $this->calendarMonth = $current->addMonth()->format('Y-m');
            $this->selectedDate = null;
        }
    }

    public function selectDate(string $date): void
    {
        if ($this->currentStep !== 2) {
            return;
        }

        $this->selectedDate = $date;
        $this->calendarMonth = CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->customerTimezone)->format('Y-m');
        $this->errorMessage = null;
    }

    public function selectSlot(string $slotId): void
    {
        $this->errorMessage = null;

        $ip = request()->ip() ?? '127.0.0.1';
        $holdIpKey = 'throttle:hold:ip:'.$ip;
        $holdVisitorKey = 'throttle:hold:visitor:'.$this->visitorToken;

        if (RateLimiter::tooManyAttempts($holdIpKey, 10) || RateLimiter::tooManyAttempts($holdVisitorKey, 5)) {
            $this->errorMessage = 'Too many slot reservation attempts. Please wait a moment before selecting another slot.';

            return;
        }

        RateLimiter::hit($holdIpKey, 60);
        RateLimiter::hit($holdVisitorKey, 60);

        $sessionType = SessionType::query()->whereKey($this->selectedSessionTypeId)->where('active', true)->first();
        if (! $sessionType) {
            $this->errorMessage = 'Please select a valid session type.';

            return;
        }

        try {
            $slotData = app(SlotResolver::class)->resolve(
                $slotId,
                $sessionType,
                $this->customerTimezone,
                $this->visitorToken,
            );
            $startUtc = $slotData['slot_start_utc'];
            $endUtc = $slotData['slot_end_utc'];
            $this->selectedDate = $slotData['customer_date'];

            $aVisitor = $this->analyticsVisitorToken ?? session('analytics_visitor_token') ?? request()->cookie('_va_visitor');
            $aSession = $this->analyticsSessionToken ?? session('analytics_session_token') ?? request()->cookie('_va_session');

            $holdService = app(BookingHoldService::class);
            $hold = $holdService->acquireHold(
                visitorToken: $this->visitorToken,
                sessionToken: session()->getId(),
                sessionType: $sessionType,
                startUtc: CarbonImmutable::parse($startUtc),
                endUtc: CarbonImmutable::parse($endUtc),
                analyticsVisitorToken: $aVisitor,
                analyticsSessionToken: $aSession
            );

            $this->holdId = $hold->id;
            $this->holdToken = $hold->hold_token;
            $this->holdExpiresAt = $hold->expires_at->toIso8601String();
            $this->selectedSlotStartUtc = $startUtc;
            $this->selectedSlotEndUtc = $endUtc;
            $this->selectedSlotId = $slotId;
            $this->selectedSlot = $slotData;

            session([
                'active_booking_hold_id' => $hold->id,
                'active_booking_hold_token' => $hold->hold_token,
                'active_booking_slot_start_utc' => $startUtc,
                'active_booking_slot_end_utc' => $endUtc,
                'active_booking_session_type_id' => $sessionType->id,
            ]);

            // Advance to details step
            $this->currentStep = 3;
            $this->syncSessionState();
        } catch (SlotUnavailableException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function submitDetails(): void
    {
        if ($this->first_name === '' && $this->last_name === '' && ! empty($this->name)) {
            $parts = explode(' ', trim($this->name), 2);
            $this->first_name = $parts[0];
            $this->last_name = $parts[1] ?? $parts[0];
        } else {
            $this->name = trim("{$this->first_name} {$this->last_name}");
        }

        $this->validate();

        if ($this->preBookingFormId && $this->initialPublishedVersionId) {
            $currentPublishedForm = Form::query()
                ->whereKey($this->preBookingFormId)
                ->where('status', 'published')
                ->first();
            if (! $currentPublishedForm || $this->initialPublishedVersionId !== $currentPublishedForm->published_version_id) {
                $this->addError('intakeForm', 'The intake form was updated while you were booking. Please review the updated questions.');

                return;
            }
            $version = $currentPublishedForm->publishedVersion()->with('questions.options')->first();
            if ($version) {
                foreach ($version->questions as $question) {
                    if ($question->is_required && empty($this->intakeAnswers[$question->id]) && empty($this->intakeAnswers[$question->question_key])) {
                        $this->addError("intakeAnswers.{$question->id}", "The {$question->label} question is required.");
                    }
                }
                if ($this->getErrorBag()->isNotEmpty()) {
                    return;
                }
            }
        }

        $this->errorMessage = null;
        $this->currentStep = 4; // Step 4: Review
        $this->syncSessionState();
    }

    public function confirmBooking(): void
    {
        $this->name = trim("{$this->first_name} {$this->last_name}");
        $this->validate();
        $this->errorMessage = null;

        if ($this->preBookingFormId && $this->initialPublishedVersionId) {
            $currentPublishedForm = Form::query()
                ->whereKey($this->preBookingFormId)
                ->where('status', 'published')
                ->first();
            if (! $currentPublishedForm || $this->initialPublishedVersionId !== $currentPublishedForm->published_version_id) {
                $this->addError('intakeForm', 'The intake form was updated while you were booking. Please review the updated questions.');
                $this->currentStep = 3;

                return;
            }
        }

        // Idempotency: If booking for this idempotency key already exists, redirect cleanly
        if ($this->idempotencyKey) {
            $existing = Booking::where('idempotency_key', $this->idempotencyKey)->first();
            if ($existing) {
                $this->holdId = null;
                $this->holdToken = null;
                $this->selectedSlotId = null;
                $this->selectedSlotStartUtc = null;
                $this->selectedSlotEndUtc = null;
                $this->selectedSlot = null;
                session()->forget([
                    'active_booking_hold_id',
                    'active_booking_hold_token',
                    'active_booking_slot_start_utc',
                    'active_booking_slot_end_utc',
                    'active_booking_session_type_id',
                ]);

                $confRoute = match (app()->getLocale()) {
                    'fr' => 'booking.confirmation.fr',
                    'de' => 'booking.confirmation.de',
                    default => 'booking.confirmation',
                };

                $this->redirectRoute($confRoute, ['token' => $existing->confirmation_token]);

                return;
            }
        }

        $ip = request()->ip() ?? '127.0.0.1';
        $normalizedEmail = strtolower(trim((string) $this->email));
        $confirmIpKey = 'throttle:booking-confirm:ip:'.$ip;
        $confirmEmailKey = 'throttle:booking-confirm:email:'.$normalizedEmail;

        if (RateLimiter::tooManyAttempts($confirmIpKey, 5) || RateLimiter::tooManyAttempts($confirmEmailKey, 3)) {
            $this->errorMessage = 'Too many booking attempts. Please wait a moment before trying again.';

            return;
        }

        RateLimiter::hit($confirmIpKey, 60);
        RateLimiter::hit($confirmEmailKey, 300);

        $sessionType = SessionType::find($this->selectedSessionTypeId);
        if (! $sessionType || ! $this->selectedSlotId) {
            $this->errorMessage = 'Incomplete booking details. Please select your time slot again.';
            $this->currentStep = 2;

            return;
        }

        $holdId = $this->holdId ?? session('active_booking_hold_id');
        $holdToken = $this->holdToken ?? session('active_booking_hold_token');
        $sessionToken = session()->getId();

        if (! $holdId || ! $holdToken) {
            $this->errorMessage = 'Your reservation hold has expired or is missing. Please select a time slot again.';
            $this->currentStep = 2;

            return;
        }

        try {
            $bookingService = app(BookingService::class);
            $booking = $bookingService->createPublicBooking([
                'session_type_id' => $sessionType->id,
                'slot_id' => $this->selectedSlotId,
                'customer_timezone' => $this->customerTimezone,
                'customer_name' => $this->name,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'customer_email' => $this->email,
                'email' => $this->email,
                'customer_phone' => $this->phone,
                'phone' => $this->phone,
                'date_of_birth' => $this->date_of_birth,
                'notes' => $this->notes,
                'idempotency_key' => $this->idempotencyKey,
                'hold_id' => (int) $holdId,
                'hold_token' => (string) $holdToken,
                'visitor_token' => $this->visitorToken,
                'session_token' => $sessionToken,
                'analytics_visitor_token' => $this->analyticsVisitorToken ?? session('analytics_visitor_token') ?? request()->cookie('_va_visitor'),
                'analytics_session_token' => $this->analyticsSessionToken ?? session('analytics_session_token') ?? request()->cookie('_va_session'),
                'source' => session('utm_source'),
                'medium' => session('utm_medium'),
                'campaign' => session('utm_campaign'),
                'content' => session('utm_content'),
                'term' => session('utm_term'),
                'intake_answers' => $this->intakeAnswers,
                'form_version_id' => $this->initialPublishedVersionId,
            ], $this->intakeAnswers, $this->initialPublishedVersionId);

            // Release hold reference
            $this->holdId = null;
            $this->holdToken = null;
            Session::forget([
                'active_booking_hold_id',
                'active_booking_hold_token',
                'active_booking_slot_start_utc',
                'active_booking_slot_end_utc',
                'active_booking_session_type_id',
                'booking_flow_state',
            ]);
            $this->selectedSlotId = null;

            // Redirect to confirmation page
            $confRoute = match (app()->getLocale()) {
                'fr' => 'booking.confirmation.fr',
                'de' => 'booking.confirmation.de',
                default => 'booking.confirmation',
            };
            $this->redirectRoute($confRoute, ['token' => $booking->confirmation_token]);
        } catch (SlotUnavailableException $e) {
            $this->errorMessage = $e->getMessage();
            $this->currentStep = 2; // return to slot selection
        } catch (ConcurrentIdentityProvisioningException $e) {
            abort(429, $e->getMessage());
        } catch (StudentIdentityConflictException $e) {
            $this->addError('email', 'A profile conflict was detected. Please contact support.');
            $this->currentStep = 3;
        } catch (\Throwable $e) {
            $this->errorMessage = 'An error occurred while confirming your booking. Please try again.';
        }
    }

    public function goToStep(int $step): void
    {
        $this->errorMessage = null;
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
            $this->syncSessionState();
        }
    }

    public function syncSessionState(): void
    {
        Session::put('booking_flow_state', [
            'visitor_token' => $this->visitorToken,
            'current_step' => $this->currentStep,
            'selected_session_type_id' => $this->selectedSessionTypeId,
            'customer_timezone' => $this->customerTimezone,
            'active_booking_timezone' => $this->customerTimezone,
            'manual_timezone_selected' => $this->manualTimezoneSelected,
            'timezone_country_code' => $this->timezoneCountryCode,
            'calendar_month' => $this->calendarMonth,
            'selected_date' => $this->selectedDate,
            'selected_slot_start_utc' => $this->selectedSlotStartUtc,
            'selected_slot_end_utc' => $this->selectedSlotEndUtc,
            'selected_slot_id' => $this->selectedSlotId,
            'selected_slot' => $this->selectedSlot,
            'hold_id' => $this->holdId,
            'hold_token' => $this->holdToken,
            'hold_expires_at' => $this->holdExpiresAt,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth,
            'intake_answers' => $this->intakeAnswers,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
        ]);
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'first_name' || $propertyName === 'last_name') {
            $this->name = trim("{$this->first_name} {$this->last_name}");
        } elseif ($propertyName === 'name' && empty($this->first_name) && empty($this->last_name)) {
            $parts = explode(' ', trim((string) $this->name), 2);
            $this->first_name = $parts[0];
            $this->last_name = $parts[1] ?? $parts[0];
        }
        $this->syncSessionState();
    }

    public function switchLanguage(string $locale, array $pendingData = []): void
    {
        if (! empty($pendingData)) {
            if (isset($pendingData['name'])) {
                $this->name = (string) $pendingData['name'];
            }
            if (isset($pendingData['first_name'])) {
                $this->first_name = (string) $pendingData['first_name'];
            }
            if (isset($pendingData['last_name'])) {
                $this->last_name = (string) $pendingData['last_name'];
            }
            if (isset($pendingData['date_of_birth'])) {
                $this->date_of_birth = (string) $pendingData['date_of_birth'];
            }
            if (isset($pendingData['email'])) {
                $this->email = (string) $pendingData['email'];
            }
            if (isset($pendingData['phone'])) {
                $this->phone = (string) $pendingData['phone'];
            }
            if (isset($pendingData['notes'])) {
                $this->notes = (string) $pendingData['notes'];
            }
        }

        $this->syncSessionState();

        $targetUrl = match ($locale) {
            'fr' => url('/fr/reservation'),
            'de' => url('/de/buchen'),
            default => url('/booking'),
        };

        $this->redirect($targetUrl);
    }

    public function render()
    {
        $sessionType = $this->selectedSessionTypeId
            ? SessionType::query()->whereKey($this->selectedSessionTypeId)->where('active', true)->first()
            : null;
        $activeSessionTypes = SessionType::where('active', true)->get();

        $availableSlotsByDate = [];
        if ($sessionType) {
            $availabilityService = app(AvailabilityService::class);

            $monthStart = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth, $this->customerTimezone)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();

            $availableSlotsByDate = $availabilityService->getAvailableSlotsGroupedByDate(
                sessionType: $sessionType,
                customerTimezone: $this->customerTimezone,
                fromDate: $monthStart,
                toDate: $monthEnd,
                currentVisitorToken: $this->visitorToken
            );

            $slotResolver = app(SlotResolver::class);
            foreach ($availableSlotsByDate as &$slots) {
                foreach ($slots as &$slot) {
                    $slot['slot_id'] = $slotResolver->issue($sessionType, $slot, $this->customerTimezone, $this->visitorToken);
                }
                unset($slot);
            }
            unset($slots);
        }

        $preBookingQuestions = collect();
        if ($this->preBookingFormId) {
            $preBookingForm = Form::with('publishedVersion.questions.options')->find($this->preBookingFormId);
            $preBookingQuestions = $preBookingForm?->publishedVersion?->questions ?? collect();
        }

        // Filter timezones for modal
        $timezoneService = app(TimezoneService::class);
        $curatedTimezones = $timezoneService->getCuratedList();
        if (! empty($this->timezoneSearch)) {
            $search = strtolower(trim($this->timezoneSearch));
            $curatedTimezones = array_filter($curatedTimezones, function ($tz) use ($search) {
                return str_contains(strtolower($tz['id']), $search) ||
                       str_contains(strtolower($tz['label']), $search);
            });
        }

        return view('livewire.booking-wizard', [
            'sessionType' => $sessionType,
            'activeSessionTypes' => $activeSessionTypes,
            'availableSlotsByDate' => $availableSlotsByDate,
            'curatedTimezones' => $curatedTimezones,
            'preBookingQuestions' => $preBookingQuestions,
        ]);
    }
}
