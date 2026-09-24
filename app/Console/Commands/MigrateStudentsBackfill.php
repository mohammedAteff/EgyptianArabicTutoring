<?php

namespace App\Console\Commands;

use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class MigrateStudentsBackfill extends Command
{
    protected $signature = 'migrate:students-backfill {--dry-run : Analyze without writing (default)} {--apply : Apply student links in bounded transactions}';

    protected $description = 'Safely link historical bookings to legacy-unverified student profiles';

    public function handle(
        StudentIdentityService $identity,
        TimezoneService $timezones,
        AvailabilityService $availability,
        DatabaseCapability $database,
    ): int {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --apply or --dry-run, not both.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $lock = $apply ? Cache::lock('migrate:students-backfill', 3600) : null;
        if ($lock && ! $lock->get()) {
            $this->error('Another student backfill is running.');

            return self::FAILURE;
        }

        $counts = ['already_linked' => 0, 'linked' => 0, 'created' => 0, 'triage' => 0, 'timezone_triage' => 0];
        $projectedContacts = [];

        try {
            Booking::withTrashed()->whereNull('student_id')->orderBy('id')->chunkById(200, function ($page) use ($apply, $identity, $timezones, $availability, $database, &$counts, &$projectedContacts): void {
                $page->load(['contact' => fn ($query) => $query->withTrashed()]);

                if ($apply) {
                    $intervals = $page
                        ->filter(fn (Booking $booking): bool => $booking->start_at_utc !== null && $booking->end_at_utc !== null)
                        ->map(fn (Booking $booking): array => [
                            'start' => CarbonImmutable::instance($booking->start_at_utc),
                            'end' => CarbonImmutable::instance($booking->end_at_utc),
                        ])
                        ->values()
                        ->all();
                    $candidateStudentIds = $this->candidateStudentIds($page, $identity);

                    $database->transaction(function () use ($page, $identity, $timezones, $availability, $intervals, $candidateStudentIds, &$counts): void {
                        $calendarDates = $availability->acquireCalendarDateLocksForIntervals($intervals);

                        if ($candidateStudentIds !== []) {
                            Student::withTrashed()->whereIn('id', $candidateStudentIds)->orderBy('id')->lockForUpdate()->get(['id']);
                        }

                        $bookings = Booking::withTrashed()
                            ->whereIn('id', $page->modelKeys())
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get()
                            ->load(['contact' => fn ($query) => $query->withTrashed()]);

                        $lockedIntervals = $bookings
                            ->filter(fn (Booking $booking): bool => $booking->start_at_utc !== null && $booking->end_at_utc !== null)
                            ->map(fn (Booking $booking): array => [
                                'start' => CarbonImmutable::instance($booking->start_at_utc),
                                'end' => CarbonImmutable::instance($booking->end_at_utc),
                            ])
                            ->values()
                            ->all();
                        if (array_diff($availability->calendarDatesForIntervals($lockedIntervals), $calendarDates) !== []) {
                            throw new RuntimeException('A booking time changed while its backfill locks were being acquired. Retry the batch.');
                        }

                        foreach ($bookings as $booking) {
                            $this->applyBooking($booking, $identity, $timezones, $counts);
                        }
                    }, 3);

                    return;
                }

                foreach ($page as $booking) {
                    $this->analyzeBooking($booking, $identity, $timezones, $counts, $projectedContacts);
                }
            });
        } finally {
            $lock?->release();
        }

        $this->table(['Outcome', 'Count'], collect($counts)->map(fn (int $count, string $key): array => [$key, $count])->values()->all());
        $this->info($apply ? 'Backfill applied. Review triage exceptions before verifying students.' : 'Dry run only; no records changed.');

        return self::SUCCESS;
    }

    /** @param array<string, int> $counts */
    private function applyBooking(Booking $booking, StudentIdentityService $identity, TimezoneService $timezones, array &$counts): void
    {
        if ($booking->student_id !== null) {
            $counts['already_linked']++;

            return;
        }

        $this->checkTimezone($booking, $timezones, $counts, true);
        $contact = $booking->contact;
        $reason = $this->triageReason($contact?->email, $contact?->phone, $identity);
        if (! $contact || $reason !== null) {
            $this->recordException($booking, $reason ?? 'missing_contact');
            $counts['triage']++;

            return;
        }

        $existingContactStudentId = Booking::withTrashed()
            ->where('contact_id', $contact->id)
            ->whereNotNull('student_id')
            ->value('student_id');
        if ($existingContactStudentId) {
            $linkedStudent = Student::query()->verified()->find($existingContactStudentId)
                ?? Student::query()->where('identity_status', 'legacy_unverified')->find($existingContactStudentId);
            if ($linkedStudent) {
                $booking->update(['student_id' => $linkedStudent->id]);
                $counts['linked']++;

                return;
            }
        }

        $email = $identity->normalizeEmail($contact->email);
        $phone = $identity->normalizePhone($contact->phone);
        [$firstName, $lastName] = $this->splitName($contact->name);
        $normalizedName = trim((string) $contact->name) !== ''
            ? $identity->normalizedFullName($firstName, $lastName)
            : null;
        $matches = $this->matchingStudents($email, $phone);

        if ($matches->count() > 1) {
            $this->recordException($booking, 'ambiguous_identity');
            $counts['triage']++;

            return;
        }

        $existingStudent = $matches->first();
        if ($existingStudent && $this->isExactCanonicalMatch($existingStudent, $normalizedName, $email, $phone)) {
            $booking->update(['student_id' => $existingStudent->id]);
            $counts['linked']++;

            return;
        }

        $student = Student::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name_normalized' => $normalizedName ?? $identity->normalizedFullName($firstName, $lastName),
            'email' => $email,
            'email_normalized' => $email,
            'phone' => $phone,
            'phone_normalized' => $phone,
            'date_of_birth' => null,
            'preferred_timezone' => $timezones->isValid($booking->customer_timezone) ? $booking->customer_timezone : null,
            'identity_status' => 'legacy_unverified',
            'possible_duplicate_of_student_id' => $matches->first()?->id,
        ]);

        $booking->update(['student_id' => $student->id]);
        $counts['created']++;
        $counts['linked']++;

        if ($matches->isNotEmpty()) {
            $this->recordException($booking, 'possible_duplicate', ['possible_duplicate_of_student_id' => $matches->first()->id]);
            $counts['triage']++;
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, true>  $projectedContacts
     */
    private function analyzeBooking(Booking $booking, StudentIdentityService $identity, TimezoneService $timezones, array &$counts, array &$projectedContacts): void
    {
        $this->checkTimezone($booking, $timezones, $counts, false);
        $contact = $booking->contact;
        $reason = $this->triageReason($contact?->email, $contact?->phone, $identity);
        if (! $contact || $reason !== null) {
            $counts['triage']++;

            return;
        }

        if (isset($projectedContacts[$contact->id]) || Booking::withTrashed()->where('contact_id', $contact->id)->whereNotNull('student_id')->exists()) {
            $counts['linked']++;

            return;
        }

        $email = $identity->normalizeEmail($contact->email);
        $phone = $identity->normalizePhone($contact->phone);
        [$firstName, $lastName] = $this->splitName($contact->name);
        $normalizedName = trim((string) $contact->name) !== ''
            ? $identity->normalizedFullName($firstName, $lastName)
            : null;
        $matches = $this->matchingStudents($email, $phone);
        if ($matches->count() > 1) {
            $counts['triage']++;

            return;
        }

        if ($matches->first() && $this->isExactCanonicalMatch($matches->first(), $normalizedName, $email, $phone)) {
            $counts['linked']++;

            return;
        }

        $projectedContacts[$contact->id] = true;
        $counts['created']++;
        $counts['linked']++;
        if ($matches->isNotEmpty()) {
            $counts['triage']++;
        }
    }

    private function triageReason(?string $email, ?string $phone, StudentIdentityService $identity): ?string
    {
        if ($phone && ! str_starts_with(trim($phone), '+')) {
            return 'ambiguous_national_phone';
        }

        try {
            $normalizedPhone = $identity->normalizePhone($phone);
        } catch (InvalidArgumentException) {
            return 'invalid_phone';
        }

        if (! $identity->normalizeEmail($email) && ! $normalizedPhone) {
            return 'missing_identifiers';
        }

        return null;
    }

    /** @return Collection<int, Student> */
    private function matchingStudents(?string $email, ?string $phone): Collection
    {
        return Student::query()
            ->whereIn('identity_status', ['verified', 'legacy_unverified'])
            ->where(function ($query) use ($email, $phone): void {
                if ($email) {
                    $query->where('email_normalized', $email);
                }
                if ($phone) {
                    $email ? $query->orWhere('phone_normalized', $phone) : $query->where('phone_normalized', $phone);
                }
            })
            ->limit(2)
            ->get();
    }

    private function isExactCanonicalMatch(Student $student, ?string $name, ?string $email, ?string $phone): bool
    {
        $providedIdentifiers = array_filter([
            'name_normalized' => $name,
            'email_normalized' => $email,
            'phone_normalized' => $phone,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        if (count($providedIdentifiers) < 2) {
            return false;
        }

        foreach ($providedIdentifiers as $field => $value) {
            if ($student->{$field} !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param Collection<int, Booking> $bookings
     * @return array<int, int>
     */
    private function candidateStudentIds($bookings, StudentIdentityService $identity): array
    {
        $contactIds = $bookings->pluck('contact_id')->filter()->unique()->values();
        if ($contactIds->isEmpty()) {
            return [];
        }

        $studentIds = Booking::withTrashed()
            ->whereIn('contact_id', $contactIds)
            ->whereNotNull('student_id')
            ->pluck('student_id');
        $emails = [];
        $phones = [];
        foreach ($bookings as $booking) {
            if (! $booking->contact) {
                continue;
            }

            $email = $identity->normalizeEmail($booking->contact->email);
            if ($email !== null) {
                $emails[] = $email;
            }

            if ($booking->contact->phone && str_starts_with(trim($booking->contact->phone), '+')) {
                try {
                    $phone = $identity->normalizePhone($booking->contact->phone);
                    if ($phone !== null) {
                        $phones[] = $phone;
                    }
                } catch (InvalidArgumentException) {
                    continue;
                }
            }
        }

        $emails = array_values(array_unique($emails));
        $phones = array_values(array_unique($phones));
        if ($emails !== [] || $phones !== []) {
            $studentIds = $studentIds->merge(Student::withTrashed()
                ->where(function ($query) use ($emails, $phones): void {
                    if ($emails !== []) {
                        $query->whereIn('email_normalized', $emails);
                    }
                    if ($phones !== []) {
                        $emails !== [] ? $query->orWhereIn('phone_normalized', $phones) : $query->whereIn('phone_normalized', $phones);
                    }
                })
                ->pluck('id'));
        }

        return $studentIds->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /** @param array<string, int> $counts */
    private function checkTimezone(Booking $booking, TimezoneService $timezones, array &$counts, bool $apply): void
    {
        if ($timezones->isValid($booking->business_timezone) && $timezones->isValid($booking->customer_timezone)) {
            return;
        }

        $counts['timezone_triage']++;
        if ($apply) {
            DB::table('migration_booking_timezone_exceptions')->updateOrInsert(
                ['booking_id' => $booking->id],
                ['reason' => 'invalid_iana_timezone', 'updated_at' => now('UTC'), 'created_at' => now('UTC')],
            );
        }
    }

    /** @param array<string, int> $context */
    private function recordException(Booking $booking, string $reason, array $context = []): void
    {
        DB::table('migration_booking_exceptions')->updateOrInsert(
            ['booking_id' => $booking->id],
            ['reason' => $reason, 'context' => json_encode(['contact_id' => $booking->contact_id] + $context, JSON_THROW_ON_ERROR), 'updated_at' => now('UTC'), 'created_at' => now('UTC')],
        );
    }

    /** @return array{string, string} */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), 2);

        return [$parts[0] ?: 'Legacy', $parts[1] ?? 'Student'];
    }
}
