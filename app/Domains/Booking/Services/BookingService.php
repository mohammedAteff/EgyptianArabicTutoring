<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormAnswer;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormVersion;
use App\Domains\Forms\Services\FormValidationService;
use App\Domains\Students\Exceptions\ConcurrentIdentityProvisioningException;
use App\Domains\Students\Exceptions\StudentIdentityConflictException;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected ContactService $contactService,
        protected AvailabilityService $availabilityService,
        protected AnalyticsService $analyticsService,
        protected DatabaseCapability $databaseCapability,
        protected ?StudentIdentityService $studentIdentityService = null,
    ) {
        $this->studentIdentityService = $studentIdentityService ?? app(StudentIdentityService::class);
    }

    /**
     * Create a new confirmed booking atomically.
     *
     * @param  array{
     *     session_type_id: int,
     *     slot_id?: string,
     *     customer_timezone: string,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     notes?: string|null,
     *     idempotency_key: string,
     *     hold_id?: int|null,
     *     hold_token?: string|null,
     *     visitor_token?: string|null,
     *     session_token?: string|null,
     *     source?: string|null,
     *     medium?: string|null,
     *     campaign?: string|null,
     *     content?: string|null,
     *     term?: string|null
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    /**
     * Create a confirmed booking via public booking flow requiring authenticated hold.
     *
     * @param  array{
     *     session_type_id: int,
     *     slot_id?: string,
     *     customer_timezone: string,
     *     customer_name?: string|null,
     *     customer_email?: string|null,
     *     customer_phone?: string|null,
     *     first_name: string,
     *     last_name: string,
     *     email?: string|null,
     *     phone: string,
     *     date_of_birth: string,
     *     phone_country?: string|null,
     *     notes?: string|null,
     *     idempotency_key: string,
     *     hold_id: int,
     *     hold_token: string,
     *     visitor_token: string,
     *     session_token?: string|null,
     *     source?: string|null,
     *     medium?: string|null,
     *     campaign?: string|null,
     *     content?: string|null,
     *     term?: string|null,
     *     intake_answers?: array<string|int, mixed>,
     *     form_version_id?: int|null
     * }  $data
     * @param  array<string|int, mixed>  $intakeAnswers
     *
     * @throws SlotUnavailableException
     */
    public function createPublicBooking(array $data, array $intakeAnswers = [], ?int $formVersionId = null): Booking
    {
        $idempotencyKey = trim($data['idempotency_key'] ?? '');
        if (empty($idempotencyKey)) {
            throw new \InvalidArgumentException('Idempotency key is required.');
        }

        // Validate hold presence before entering transaction
        $holdId = $data['hold_id'] ?? null;
        $holdToken = $data['hold_token'] ?? null;
        $visitorToken = $data['visitor_token'] ?? null;
        $sessionToken = $data['session_token'] ?? null;

        if (! $holdId || ! is_numeric($holdId)) {
            throw new SlotUnavailableException('A valid reservation hold is required.');
        }
        if (! $holdToken || ! is_string($holdToken) || trim($holdToken) === '') {
            throw new SlotUnavailableException('Reservation hold authentication token is required.');
        }
        if (! $visitorToken || ! is_string($visitorToken) || trim($visitorToken) === '') {
            throw new SlotUnavailableException('Visitor authentication token is required.');
        }
        if (! $sessionToken || ! is_string($sessionToken) || trim($sessionToken) === '') {
            throw new SlotUnavailableException('Session authentication token is required.');
        }

        $sessionType = SessionType::query()->findOrFail($data['session_type_id']);
        $holdSnapshot = BookingHold::query()->find((int) $holdId);
        if (! $holdSnapshot) {
            throw new SlotUnavailableException('Invalid reservation hold authentication.');
        }

        if (! hash_equals((string) $holdSnapshot->hold_token, (string) $holdToken)) {
            throw new SlotUnavailableException('Invalid reservation hold authentication.');
        }
        if (! hash_equals((string) $holdSnapshot->visitor_token, (string) $visitorToken)) {
            throw new SlotUnavailableException('Reservation hold ownership mismatch.');
        }
        if (! hash_equals((string) $holdSnapshot->session_token, (string) $sessionToken)) {
            throw new SlotUnavailableException('Reservation hold session mismatch.');
        }
        if ((int) $holdSnapshot->session_type_id !== (int) $sessionType->id) {
            throw new SlotUnavailableException('Reservation hold session type mismatch.');
        }

        // The authenticated server-side hold is the only source of slot timestamps.
        // Any start/end values supplied by a caller are deliberately ignored.
        $startUtc = $this->timezoneService->toUtc($holdSnapshot->slot_start_utc);
        $endUtc = $this->timezoneService->toUtc($holdSnapshot->slot_end_utc);
        $customerTimezone = $this->timezoneService->validate($data['customer_timezone']);
        $businessTimezone = $this->timezoneService->getBusinessTimezone();

        $analyticsVisitorToken = $data['analytics_visitor_token']
            ?? (session()->isStarted() ? session('analytics_visitor_token') : null)
            ?? request()->cookie('_va_visitor');
        $analyticsSessionToken = $data['analytics_session_token']
            ?? (session()->isStarted() ? session('analytics_session_token') : null)
            ?? request()->cookie('_va_session');
        $expectedVisitorToken = $analyticsVisitorToken ?? $visitorToken;
        $existingBooking = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existingBooking) {
            if (! hash_equals((string) $existingBooking->visitor_token, (string) $expectedVisitorToken)) {
                throw new SlotUnavailableException('This booking request cannot be verified.');
            }

            return $existingBooking;
        }

        if (! $sessionType->active) {
            throw new SlotUnavailableException('This session type is not currently active.');
        }
        if ($startUtc <= CarbonImmutable::now('UTC')) {
            throw new SlotUnavailableException('Cannot book an appointment in the past.');
        }
        $nowUtc = CarbonImmutable::now('UTC');

        $heldSessionType = SessionType::query()->whereKey($holdSnapshot->session_type_id)->first();
        if (! $heldSessionType) {
            throw new SlotUnavailableException('This reservation hold is no longer valid.');
        }
        $initialConfig = $this->availabilityService->resolveSlotConfiguration($heldSessionType, $startUtc, $endUtc);

        $email = trim((string) ($data['customer_email'] ?? $data['email'] ?? ''));
        $phone = trim((string) ($data['customer_phone'] ?? $data['phone'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $dateOfBirth = $data['date_of_birth'] ?? null;
        $missingIdentityFields = [];
        foreach ([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $dateOfBirth,
        ] as $field => $value) {
            if (! is_string($value) || trim($value) === '') {
                $missingIdentityFields[$field] = "Please provide your {$field}.";
            }
        }
        if ($missingIdentityFields !== []) {
            throw ValidationException::withMessages($missingIdentityFields);
        }
        $phoneCountry = $data['phone_country'] ?? null;
        Validator::make([
            'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email,
            'phone' => $phone, 'date_of_birth' => $dateOfBirth,
        ], [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
        ])->validate();
        try {
            $normPhone = $this->studentIdentityService->normalizePhone($phone, $phoneCountry);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }
        $effectiveIntakeAnswers = ! empty($intakeAnswers) ? $intakeAnswers : ($data['intake_answers'] ?? []);
        $effectiveFormVersionId = $formVersionId ?? ($data['form_version_id'] ?? null);

        $liveIntakeForm = $this->publishedIntakeForm();
        $effectiveFormVersionId ??= $liveIntakeForm?->published_version_id;
        $effectiveIntakeAnswers = $this->validatePublicIntake($effectiveFormVersionId, $effectiveIntakeAnswers);

        $connection = DB::connection();
        $dbName = substr($connection->getDatabaseName(), 0, 20);
        $canonicalIdentityString = $this->studentIdentityService->normalizeIdentity(
            $email,
            $phone ?: null,
            $firstName,
            $lastName,
            $phoneCountry
        );
        $identityDigest = substr(hash('sha256', $canonicalIdentityString), 0, 32);
        $lockKey = "{$dbName}:stu:{$identityDigest}";
        if (strlen($lockKey) > 64) {
            throw new \LogicException("Named lock key [{$lockKey}] exceeds MariaDB 64-character limit.");
        }
        $acquired = $connection->selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lockKey], useReadPdo: false);
        if ((int) ($acquired?->acquired ?? 0) !== 1) {
            throw new ConcurrentIdentityProvisioningException(
                'A booking or provisioning request for this student identity is already in progress. Please retry.'
            );
        }

        $bookingCreated = false;
        try {
            $booking = $this->databaseCapability->transaction(function () use (
                $data,
                $idempotencyKey,
                $sessionType,
                $startUtc,
                $endUtc,
                $customerTimezone,
                $businessTimezone,
                $visitorToken,
                $sessionToken,
                $holdId,
                $holdToken,
                $holdSnapshot,
                $initialConfig,
                $analyticsVisitorToken,
                $analyticsSessionToken,
                $nowUtc,
                $email,
                $phone,
                $firstName,
                $lastName,
                $dateOfBirth,
                $normPhone,
                $effectiveIntakeAnswers,
                $effectiveFormVersionId,
                &$bookingCreated,
            ) {
                if ($effectiveFormVersionId !== $this->publishedIntakeForm()?->published_version_id) {
                    throw ValidationException::withMessages([
                        'intakeForm' => 'The intake form was updated while you were booking. Please review the updated questions.',
                    ]);
                }

                // Acquire the canonical scheduling mutex before any booking-row lock.
                $this->availabilityService->acquireCalendarDateLocks(
                    $startUtc,
                    $initialConfig['end_utc'],
                    $initialConfig['buffer_minutes'],
                );

                // Read conversion attribution before resolving the contact so attribution is written on first creation.
                $conversionAttr = $this->analyticsService->getBookingConversionAttribution(
                    visitorToken: $analyticsVisitorToken ?? $visitorToken,
                    bookingTime: $nowUtc
                );

                if ($conversionAttr['utm_source'] !== 'Direct / None') {
                    $utmSource = $conversionAttr['utm_source'];
                    $utmMedium = $conversionAttr['utm_medium'];
                    $utmCampaign = $conversionAttr['utm_campaign'];
                    $utmContent = $conversionAttr['utm_content'];
                    $utmTerm = $conversionAttr['utm_term'];
                    $referrer = $conversionAttr['referrer'];
                    $touchAt = $conversionAttr['touch_at'];
                } else {
                    $utmSource = 'Direct / None';
                    $utmMedium = null;
                    $utmCampaign = null;
                    $utmContent = null;
                    $utmTerm = null;
                    $referrer = null;
                    $touchAt = null;
                }

                // Resolve and lock the contact before locking student identity rows.
                $contact = $this->contactService->resolveOrCreate(
                    email: $email,
                    name: trim("{$firstName} {$lastName}"),
                    phone: $phone ?: null,
                    attribution: [
                        'utm_source' => $utmSource,
                        'utm_medium' => $utmMedium,
                        'utm_campaign' => $utmCampaign,
                        'utm_content' => $utmContent,
                        'utm_term' => $utmTerm,
                    ]
                );

                Contact::whereKey($contact->id)->lockForUpdate()->first();

                // Candidate Student Resolution (Strict StudentIdentityService logic)
                $normEmail = $this->studentIdentityService->normalizeEmail($email);
                $normName = $this->studentIdentityService->normalizedFullName($firstName, $lastName);

                $candidateQuery = Student::withTrashed();
                if ($normEmail && $normPhone) {
                    $candidateQuery->where(function ($q) use ($normEmail, $normPhone) {
                        $q->where('email_normalized', $normEmail)
                            ->orWhere('phone_normalized', $normPhone);
                    });
                } elseif ($normEmail) {
                    $candidateQuery->where('email_normalized', $normEmail);
                } elseif ($normPhone) {
                    $candidateQuery->where('phone_normalized', $normPhone);
                } else {
                    $candidateQuery->whereRaw('0 = 1');
                }

                $rawCandidates = $candidateQuery->lockForUpdate()->get();
                $resolvedCandidates = [];
                foreach ($rawCandidates as $cand) {
                    $canonical = $this->studentIdentityService->resolveCanonicalStudent($cand, lock: true);
                    if (! $canonical->trashed()) {
                        $resolvedCandidates[$canonical->id] = $canonical;
                    }
                }
                $distinctCandidates = array_values($resolvedCandidates);

                if (count($distinctCandidates) > 1) {
                    throw new StudentIdentityConflictException('Multiple conflicting student records matched.');
                }

                if (count($distinctCandidates) === 1) {
                    $matchedStudent = $distinctCandidates[0];
                    $studentId = $matchedStudent->id;
                    if ($matchedStudent->identity_status === 'legacy_unverified') {
                        $updates = [];
                        if (empty($matchedStudent->date_of_birth) && ! empty($dateOfBirth)) {
                            $updates['date_of_birth'] = $dateOfBirth;
                        }
                        if (empty($matchedStudent->phone) && ! empty($normPhone)) {
                            $updates['phone'] = $phone;
                            $updates['phone_normalized'] = $normPhone;
                        }
                        if ($updates !== []) {
                            $matchedStudent->update($updates);
                        }
                    }
                } else {
                    $newStudent = Student::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'name_normalized' => $normName,
                        'email' => $normEmail ?? $email,
                        'email_normalized' => $normEmail,
                        'phone' => $phone ?: null,
                        'phone_normalized' => $normPhone,
                        'date_of_birth' => $dateOfBirth,
                        'preferred_timezone' => $customerTimezone,
                        'identity_status' => 'legacy_unverified',
                    ]);
                    $studentId = $newStudent->id;
                }

                // Lock and verify the hold only after contact and student rows, keeping one global lock order.
                $hold = BookingHold::query()
                    ->where('id', (int) $holdId)
                    ->where('hold_token', (string) $holdToken)
                    ->lockForUpdate()
                    ->first();

                if (! $hold) {
                    throw new SlotUnavailableException('Invalid reservation hold authentication.');
                }
                if ((int) $hold->session_type_id !== (int) $sessionType->id
                    || ! $hold->slot_start_utc->equalTo($holdSnapshot->slot_start_utc)
                    || ! $hold->slot_end_utc->equalTo($holdSnapshot->slot_end_utc)) {
                    throw new SlotUnavailableException('The held slot changed while it was being confirmed. Please select a slot again.');
                }
                if (! hash_equals((string) $hold->visitor_token, (string) $visitorToken)) {
                    throw new SlotUnavailableException('Reservation hold ownership mismatch.');
                }
                if (! hash_equals((string) $hold->session_token, (string) $sessionToken)) {
                    throw new SlotUnavailableException('Reservation hold session mismatch.');
                }

                $expectedVisitorToken = $analyticsVisitorToken ?? $visitorToken;
                $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->visitor_token, (string) $expectedVisitorToken)) {
                        throw new SlotUnavailableException('This booking request cannot be verified.');
                    }

                    return $existing;
                }

                if ($hold->status !== 'active') {
                    throw new SlotUnavailableException('Your reservation hold is no longer active.');
                }
                if ($hold->expires_at <= now()) {
                    throw new SlotUnavailableException('Your reservation hold has expired. Please choose a slot again.');
                }

                $config = $this->availabilityService->resolveSlotConfiguration(
                    sessionType: $sessionType,
                    startUtc: $hold->slot_start_utc,
                    endUtc: $hold->slot_end_utc,
                );
                $this->availabilityService->validateSlotForBooking(
                    sessionType: $sessionType,
                    startUtc: $startUtc,
                    endUtc: $config['end_utc'],
                    currentVisitorToken: $visitorToken,
                    currentHoldToken: $hold->hold_token,
                    excludeHoldId: $hold->id,
                    resolvedConfig: $config
                );

                // Generate timezone snapshot
                $snapshot = $this->timezoneService->createBookingSnapshot(
                    startUtc: $startUtc,
                    endUtc: $endUtc,
                    customerTimezone: $customerTimezone,
                    businessTimezone: $businessTimezone
                );

                // Generate secure non-guessable confirmation token
                $confirmationToken = Str::random(64);

                // Country is a server-derived snapshot.
                $detectedCountry = null;
                $verifiedAnalyticsSession = null;
                if ($analyticsSessionToken) {
                    $verifiedAnalyticsSession = VisitorSession::query()
                        ->where(function ($query) use ($analyticsSessionToken): void {
                            $query->where('session_token', $analyticsSessionToken)
                                ->orWhere('session_id', $analyticsSessionToken);
                        })
                        ->lockForUpdate()
                        ->first();
                }

                $verifiedVisitorToken = $verifiedAnalyticsSession?->visitor?->visitor_token;
                $expectedVisitorToken = $analyticsVisitorToken ?? $visitorToken;
                if ($verifiedAnalyticsSession && $verifiedVisitorToken && hash_equals((string) $verifiedVisitorToken, (string) $expectedVisitorToken)) {
                    $detectedCountry = $verifiedAnalyticsSession->detected_country_code;
                }

                // Create booking with unique idempotency recovery
                try {
                    $booking = Booking::create(array_merge($snapshot, [
                        'contact_id' => $contact->id,
                        'student_id' => $studentId,
                        'visitor_token' => $analyticsVisitorToken ?? $visitorToken,
                        'detected_country_code' => $detectedCountry,
                        'session_type_id' => $sessionType->id,
                        'status' => 'confirmed',
                        'idempotency_key' => $idempotencyKey,
                        'confirmation_token' => $confirmationToken,
                        'notes' => $data['notes'] ?? null,
                        'source' => $utmSource,
                        'medium' => $utmMedium,
                        'campaign' => $utmCampaign,
                        'content' => $utmContent,
                        'term' => $utmTerm,
                        'referrer' => $referrer,
                        'touch_at' => $touchAt,
                    ]));
                    $bookingCreated = true;
                } catch (QueryException $e) {
                    // Granular Duplicate-Key Exception Handling
                    $isIdempotencyDuplicate = $e->getCode() === '23000'
                        && (($e->errorInfo[1] ?? null) === 1062)
                        && str_contains($e->errorInfo[2] ?? '', 'bookings_idempotency_key_unique');
                    if ($isIdempotencyDuplicate) {
                        $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
                        if ($existing && hash_equals((string) $existing->visitor_token, (string) $expectedVisitorToken)) {
                            return $existing;
                        }
                    }
                    throw $e;
                }

                // Persist FormSubmission and FormAnswers if formVersionId provided
                if ($effectiveFormVersionId) {
                    $version = FormVersion::with('questions.options')->findOrFail($effectiveFormVersionId);
                    if ($version) {
                        $submission = FormSubmission::create([
                            'form_version_id' => $version->id,
                            'student_id' => $studentId,
                            'status' => 'submitted',
                            'submitted_at' => now('UTC'),
                            'submission_revision' => 1,
                        ]);
                        $submission->contact_id = $contact->id;
                        $submission->booking_id = $booking->id;
                        $submission->save();

                        if (! empty($effectiveIntakeAnswers)) {
                            $questionMap = $version->questions->keyBy(fn ($q): string => (string) $q->id);
                            $questionKeyMap = $version->questions->keyBy('question_key');
                            foreach ($effectiveIntakeAnswers as $qIdOrKey => $ansVal) {
                                $question = $questionMap->get((string) $qIdOrKey) ?? $questionKeyMap->get((string) $qIdOrKey);
                                if ($question) {
                                    $storedVal = is_array($ansVal)
                                        ? json_encode($ansVal, JSON_THROW_ON_ERROR)
                                        : (is_bool($ansVal) ? ($ansVal ? '1' : '0') : (string) $ansVal);
                                    FormAnswer::create([
                                        'form_submission_id' => $submission->id,
                                        'form_question_id' => $question->id,
                                        'value_text' => $storedVal,
                                    ]);
                                }
                            }
                        }
                    }
                }

                // Convert hold atomically
                $hold->update([
                    'status' => 'converted',
                    'released_at' => now(),
                ]);

                // Record booking event
                BookingEvent::create([
                    'booking_id' => $booking->id,
                    'event_type' => 'created',
                    'performed_by' => 'customer',
                    'previous_data' => null,
                    'new_data' => [
                        'status' => 'confirmed',
                        'start_at_utc' => $startUtc->toDateTimeString(),
                        'end_at_utc' => $endUtc->toDateTimeString(),
                        'customer_timezone' => $customerTimezone,
                    ],
                    'created_at' => now(),
                ]);

                // Track authoritative server-side analytics event post-commit
                DB::afterCommit(function () use ($booking, $sessionType, $startUtc, $endUtc, $customerTimezone, $analyticsVisitorToken, $visitorToken, $analyticsSessionToken, $sessionToken) {
                    $this->analyticsService->trackEvent(
                        eventType: 'booking_completed',
                        page: '/booking/confirmed',
                        visitorToken: $analyticsVisitorToken ?? $visitorToken,
                        sessionToken: $analyticsSessionToken ?? $sessionToken,
                        metadata: [
                            'booking_id' => $booking->id,
                            'session_type_id' => $sessionType->id,
                            'start_at_utc' => $startUtc->toDateTimeString(),
                            'end_at_utc' => $endUtc->toDateTimeString(),
                            'customer_timezone' => $customerTimezone,
                        ]
                    );
                });

                return $booking;
            }, 5);
        } finally {
            $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockKey], useReadPdo: false);
            if ((int) ($released?->released ?? 0) !== 1) {
                throw new \LogicException("Failed to release named lock [{$lockKey}].");
            }
        }

        if ($bookingCreated) {
            try {
                app(AdminNotificationService::class)->notifyBookingCreated($booking);
            } catch (\Throwable) {
                // Notification failures must never roll back or prevent valid bookings.
            }
        }

        return $booking;
    }

    public function publishedIntakeForm(): ?Form
    {
        return Form::query()->where('status', 'published')
            ->whereNotNull('published_version_id')
            ->whereHas('triggers', fn ($query) => $query->where('trigger_name', 'pre_booking'))
            ->with('publishedVersion.questions.options')->first();
    }

    /** @param array<string|int, mixed> $answers @return array<string, mixed> */
    public function validatePublicIntake(?int $versionId, array $answers): array
    {
        $form = $this->publishedIntakeForm();
        if ($versionId !== $form?->published_version_id) {
            throw ValidationException::withMessages([
                'intakeForm' => 'The intake form was updated while you were booking. Please review the updated questions.',
            ]);
        }
        if (! $form) {
            if ($answers !== []) {
                throw ValidationException::withMessages(['intakeForm' => 'No published intake form is available.']);
            }

            return [];
        }

        $questions = $form->publishedVersion->questions;
        $byId = $questions->keyBy(fn ($question): string => (string) $question->id);
        $byKey = $questions->keyBy('question_key');
        $normalized = [];
        foreach ($answers as $key => $value) {
            $question = $byId->get((string) $key) ?? $byKey->get((string) $key);
            if (! $question) {
                throw ValidationException::withMessages(["intakeAnswers.{$key}" => 'Submitted question ID does not belong to the active form version.']);
            }
            if (array_key_exists($question->question_key, $normalized)) {
                throw ValidationException::withMessages(["intakeAnswers.{$question->id}" => 'Submit only one answer per question.']);
            }
            $normalized[$question->question_key] = $value;
        }

        try {
            return app(FormValidationService::class)->validateAnswers($questions->all(), $normalized);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $parts = explode('.', $key);
                $question = $byKey->get($parts[1] ?? '');
                $errors['intakeAnswers.'.($question?->id ?? ($parts[1] ?? $key))] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Create an admin-initiated booking through an explicitly trusted path.
     *
     * @param  array{
     *     session_type_id: int,
     *     start_at_utc: CarbonInterface|string,
     *     end_at_utc: CarbonInterface|string,
     *     customer_timezone?: string|null,
     *     business_timezone?: string|null,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     notes?: string|null,
     *     idempotency_key?: string|null,
     *     source?: string|null,
     *     medium?: string|null,
     *     campaign?: string|null,
     *     content?: string|null,
     *     term?: string|null
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    public function createAdminBooking(array $data, ?int $adminId = null): Booking
    {
        $idempotencyKey = trim($data['idempotency_key'] ?? (string) Str::uuid());

        $sessionType = SessionType::findOrFail($data['session_type_id']);
        $startUtc = $this->timezoneService->toUtc($data['start_at_utc']);
        $endUtc = $this->timezoneService->toUtc($data['end_at_utc']);
        $nowUtc = CarbonImmutable::now('UTC');

        if ($startUtc <= $nowUtc) {
            throw new SlotUnavailableException('Cannot book an appointment in the past.');
        }

        $customerTimezone = $this->timezoneService->validate($data['customer_timezone'] ?? $this->timezoneService->getBusinessTimezone());
        $businessTimezone = $this->timezoneService->getBusinessTimezone();

        $bookingCreated = false;
        $booking = $this->databaseCapability->transaction(function () use (
            $data,
            $idempotencyKey,
            $sessionType,
            $startUtc,
            $endUtc,
            $customerTimezone,
            $businessTimezone,
            $adminId,
            &$bookingCreated,
        ) {
            // Acquire deterministic calendar locks on buffer-expanded business date(s)
            $buffer = $this->availabilityService->resolveEffectiveBuffer($startUtc);
            $this->availabilityService->acquireCalendarDateLocks($startUtc, $endUtc, $buffer);

            // Keep the replay fast path nonlocking; row locks follow Contact resolution.
            $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // Resolve or create canonical Contact
            $contact = $this->contactService->resolveOrCreate(
                email: $data['customer_email'] ?? $data['email'],
                name: $data['customer_name'] ?? $data['name'] ?? null,
                phone: $data['customer_phone'] ?? $data['phone'] ?? null,
                attribution: [
                    'utm_source' => $data['source'] ?? null,
                    'utm_medium' => $data['medium'] ?? null,
                    'utm_campaign' => $data['campaign'] ?? null,
                    'utm_content' => $data['content'] ?? null,
                    'utm_term' => $data['term'] ?? null,
                ]
            );

            // Recheck idempotency under a row lock after Contact, before booking and hold locks.
            $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            // Authoritative slot validation without hold requirement (trusted admin override)
            $this->availabilityService->validateSlotForBooking(
                sessionType: $sessionType,
                startUtc: $startUtc,
                endUtc: $endUtc,
                isTrustedAdmin: true
            );

            // Generate timezone snapshot
            $snapshot = $this->timezoneService->createBookingSnapshot(
                startUtc: $startUtc,
                endUtc: $endUtc,
                customerTimezone: $customerTimezone,
                businessTimezone: $businessTimezone
            );

            $confirmationToken = Str::random(64);

            try {
                $booking = Booking::create(array_merge($snapshot, [
                    'contact_id' => $contact->id,
                    'session_type_id' => $sessionType->id,
                    // Administrator-created bookings have no verified public
                    // analytics session; do not accept a caller-supplied
                    // country claim.
                    'detected_country_code' => null,
                    'status' => 'confirmed',
                    'idempotency_key' => $idempotencyKey,
                    'confirmation_token' => $confirmationToken,
                    'notes' => $data['notes'] ?? 'Booked manually by administrator',
                    'source' => $data['source'] ?? null,
                    'medium' => $data['medium'] ?? null,
                    'campaign' => $data['campaign'] ?? null,
                    'content' => $data['content'] ?? null,
                    'term' => $data['term'] ?? null,
                    'referrer' => $data['referrer'] ?? null,
                    'touch_at' => isset($data['touch_at']) ? CarbonImmutable::parse($data['touch_at']) : null,
                ]));
                $bookingCreated = true;
            } catch (QueryException $e) {
                $isIdempotencyDuplicate = $e->getCode() === '23000'
                    && (($e->errorInfo[1] ?? null) === 1062)
                    && str_contains($e->errorInfo[2] ?? '', 'bookings_idempotency_key_unique');
                if ($isIdempotencyDuplicate) {
                    $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        return $existing;
                    }
                }
                throw $e;
            }

            // Record booking event
            BookingEvent::create([
                'booking_id' => $booking->id,
                'event_type' => 'created',
                'performed_by' => $adminId ? "admin_{$adminId}" : 'admin',
                'previous_data' => null,
                'new_data' => [
                    'status' => 'confirmed',
                    'start_at_utc' => $startUtc->toDateTimeString(),
                    'end_at_utc' => $endUtc->toDateTimeString(),
                    'customer_timezone' => $customerTimezone,
                    'created_by' => 'administrator',
                ],
                'created_at' => now(),
            ]);

            return $booking;
        }, 5);

        if ($bookingCreated) {
            try {
                app(AdminNotificationService::class)->notifyBookingCreated($booking);
            } catch (\Throwable) {
                // Notification failures must never roll back or prevent valid bookings.
            }
        }

        return $booking;
    }

    /**
     * Dispatch booking creation: public booking requires hold, trusted admin path bypasses hold.
     *
     * @throws SlotUnavailableException
     */
    public function createBooking(array $data, bool $isTrustedAdmin = false): Booking
    {
        return $isTrustedAdmin
            ? $this->createAdminBooking($data)
            : $this->createPublicBooking($data);
    }

    /**
     * Retrieve booking by secure confirmation token.
     */
    public function findByConfirmationToken(string $token): ?Booking
    {
        return Booking::query()
            ->with(['contact', 'sessionType', 'events'])
            ->where('confirmation_token', $token)
            ->first();
    }
}
