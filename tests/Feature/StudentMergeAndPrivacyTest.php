<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormAnswer;
use App\Domains\Forms\Models\FormQuestion;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormSubmissionRevision;
use App\Domains\Forms\Models\FormVersion;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentMergeAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_merge_preserves_financial_booking_form_and_reschedule_history(): void
    {
        $owner = $this->createAdministrator('super_admin');
        $primary = Student::factory()->verified()->create(['possible_duplicate_of_student_id' => null]);
        $secondary = Student::factory()->verified()->create();
        $third = Student::factory()->verified()->create([
            'merged_into_student_id' => $secondary->id,
            'possible_duplicate_of_student_id' => $secondary->id,
        ]);
        $sessionType = $this->createSessionType();
        $primaryBooking = $this->createBooking($primary, $sessionType, $this->uniqueEmail('primary'));
        $secondaryBooking = $this->createBooking($secondary, $sessionType, $this->uniqueEmail('secondary'), 22);
        $ledger = app(StudentLedgerService::class);
        $primaryPackage = $ledger->createPackage($primary, 'Primary package', 4, '160.00', '10.00', 'USD', null, 'merge-primary-grant');
        $secondaryPackage = $ledger->createPackage($secondary, 'Secondary package', 6, '240.00', '20.00', 'USD', null, 'merge-secondary-grant');
        $payment = $ledger->recordPayment($secondaryPackage, '120.00', 'merge-secondary-payment', $owner->id, reference: 'MERGE-REF-42', notes: 'Keep payment facts');
        $refund = $ledger->refund($payment, '20.00', 'merge-secondary-refund', $owner->id, 'Partial refund');
        $adjustment = $ledger->adjustCredits($secondaryPackage, 1, 'Courtesy lesson', 'merge-secondary-adjustment', $owner->id);

        $form = Form::query()->create([
            'title' => 'Merge history form',
            'slug' => 'merge-history-form',
            'status' => 'published',
            'created_by' => $owner->id,
        ]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1]);
        $form->update(['active_version_id' => $version->id]);
        $submissions = collect([$primary, $secondary])->map(function (Student $student) use ($version): FormSubmission {
            $submission = FormSubmission::query()->create([
                'form_version_id' => $version->id,
                'student_id' => $student->id,
                'status' => 'submitted',
                'submitted_at' => now('UTC'),
                'submission_revision' => 1,
            ]);
            FormSubmissionRevision::query()->create([
                'form_submission_id' => $submission->id,
                'revision_number' => 1,
                'snapshot_answers' => ['goal' => 'Keep this immutable historical answer'],
                'submitted_at' => now('UTC'),
                'created_at' => now('UTC'),
            ]);

            return $submission;
        });

        $rescheduleId = DB::table('session_reschedules')->insertGetId([
            'booking_id' => $secondaryBooking->id,
            'actor_type' => 'student',
            'actor_id' => $secondary->id,
            'old_start_at_utc' => $secondaryBooking->start_at_utc->toDateTimeString(),
            'new_start_at_utc' => $secondaryBooking->start_at_utc->addDay()->toDateTimeString(),
            'old_timezone' => 'Africa/Cairo',
            'new_timezone' => 'Europe/London',
            'idempotency_key' => 'merge-reschedule-'.Str::uuid(),
            'ip_address' => '192.0.2.10',
            'created_at' => now('UTC'),
        ]);
        $rescheduleCreatedAt = DB::table('session_reschedules')->where('id', $rescheduleId)->selectRaw('UNIX_TIMESTAMP(created_at) AS created_at_epoch')->value('created_at_epoch');
        $paymentFacts = ['student_package_id', 'idempotency_key', 'amount_paid', 'currency', 'payment_method', 'transaction_reference', 'paid_at', 'notes', 'created_at'];
        $refundFacts = ['payment_record_id', 'student_package_id', 'idempotency_key', 'amount_refunded', 'currency', 'reason', 'refunded_at', 'created_at'];
        $ledgerFacts = ['student_package_id', 'idempotency_key', 'entry_type', 'credit_change', 'description', 'created_at'];
        $paymentBefore = (array) DB::table('payment_records')->where('id', $payment->id)->first($paymentFacts);
        $refundBefore = (array) DB::table('payment_refunds')->where('id', $refund->id)->first($refundFacts);
        $ledgerBefore = (array) DB::table('session_ledger_entries')->where('id', $adjustment->id)->first($ledgerFacts);
        Cache::put('student_auth_check_'.$primary->id, 'primary');
        Cache::put('student_auth_check_'.$secondary->id, 'secondary');

        $survivor = app(StudentMergeService::class)->merge($primary->id, $secondary->id, $owner->id);

        $this->assertSame($primary->id, $survivor->id);
        $this->assertSame($primary->id, (int) $primaryBooking->fresh()->student_id);
        $this->assertSame($primary->id, (int) $secondaryBooking->fresh()->student_id);
        $this->assertSame($primary->id, (int) $primaryPackage->fresh()->student_id);
        $this->assertSame($primary->id, (int) $secondaryPackage->fresh()->student_id);
        $this->assertSame($paymentBefore, (array) DB::table('payment_records')->where('id', $payment->id)->first($paymentFacts));
        $this->assertSame($refundBefore, (array) DB::table('payment_refunds')->where('id', $refund->id)->first($refundFacts));
        $this->assertSame($ledgerBefore, (array) DB::table('session_ledger_entries')->where('id', $adjustment->id)->first($ledgerFacts));
        $this->assertSame($primary->id, (int) $payment->fresh()->student_id);
        $this->assertSame($primary->id, (int) $refund->fresh()->student_id);
        $this->assertSame($primary->id, (int) $adjustment->fresh()->student_id);
        $this->assertSame(2, FormSubmission::query()->where('form_version_id', $version->id)->where('student_id', $primary->id)->count());
        $preservedRevision = FormSubmissionRevision::query()->where('form_submission_id', $submissions[1]->id)->firstOrFail();
        $this->assertSame('Keep this immutable historical answer', $preservedRevision->snapshot_answers['goal']);
        $this->assertSame($primary->id, (int) DB::table('session_reschedules')->where('id', $rescheduleId)->value('actor_id'));
        $this->assertSame($secondaryBooking->id, (int) DB::table('session_reschedules')->where('id', $rescheduleId)->value('booking_id'));
        $this->assertSame($rescheduleCreatedAt, DB::table('session_reschedules')->where('id', $rescheduleId)->selectRaw('UNIX_TIMESTAMP(created_at) AS created_at_epoch')->value('created_at_epoch'));
        $this->assertSame($primary->id, (int) Student::withTrashed()->findOrFail($secondary->id)->merged_into_student_id);
        $this->assertSame('merged', Student::withTrashed()->findOrFail($secondary->id)->identity_status);
        $this->assertNotNull(Student::withTrashed()->findOrFail($secondary->id)->deleted_at);
        $this->assertSame($primary->id, (int) $third->fresh()->merged_into_student_id);
        $this->assertSame($primary->id, (int) $third->fresh()->possible_duplicate_of_student_id);
        $this->assertNull($primary->fresh()->possible_duplicate_of_student_id);
        $this->assertFalse(Cache::has('student_auth_check_'.$primary->id));
        $this->assertFalse(Cache::has('student_auth_check_'.$secondary->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_merged', 'entity_id' => $primary->id]);
        $this->assertDatabaseHas('student_packages', ['id' => $secondaryPackage->id, 'final_price' => '220.00']);
    }

    public function test_student_merge_acquires_all_present_lock_tiers_in_canonical_order(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $sessionType = $this->createSessionType();
        $this->createBooking($secondary, $sessionType, $this->uniqueEmail('lock-tier-booking'));
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($secondary, 'Lock-tier package', 3, '120.00', '0.00', 'USD', null, 'lock-tier-package-grant');
        $payment = $ledger->recordPayment($package, '100.00', 'lock-tier-payment', null);
        $ledger->refund($payment, '10.00', 'lock-tier-refund', null);

        $lockedTiers = [];
        $tierTables = [
            'booking_calendar_locks',
            'students',
            'bookings',
            'student_packages',
            'payment_records',
            'payment_refunds',
            'session_ledger_entries',
        ];
        DB::listen(function (QueryExecuted $query) use (&$lockedTiers, $tierTables): void {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'for update')) {
                return;
            }

            foreach ($tierTables as $table) {
                if (preg_match('/\\bfrom\\s+[`"]?'.preg_quote($table, '/').'[`"]?(?:\\s|$)/', $sql) === 1) {
                    $lockedTiers[] = $table;
                    break;
                }
            }
        });

        app(StudentMergeService::class)->merge($primary->id, $secondary->id);

        $this->assertSame($tierTables, array_values(array_unique($lockedTiers)));
        $this->assertSame('merged', Student::withTrashed()->findOrFail($secondary->id)->identity_status);
    }

    public function test_admin_cannot_merge_or_anonymize_students(): void
    {
        $administrator = $this->createAdministrator('admin');
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();

        $this->actingAs($administrator, 'web')
            ->post(route('admin.students.merge', $primary->id), ['secondary_student_id' => $secondary->id])
            ->assertForbidden();
        $this->actingAs($administrator, 'web')
            ->post(route('admin.students.anonymize', $primary->id), ['confirmation' => 'ANONYMIZE'])
            ->assertForbidden();

        $this->assertSame('verified', $secondary->fresh()->identity_status);
        $this->assertNull($primary->fresh()->deleted_at);
    }

    public function test_merge_invalidates_the_secondary_students_active_portal_session(): void
    {
        $owner = $this->createAdministrator('super_admin');
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $studentSession = [
            'student_id' => $secondary->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
        ];

        $this->actingAs($secondary, 'student')->withSession($studentSession)
            ->get(route('student.dashboard'))
            ->assertOk();
        $this->assertAuthenticatedAs($secondary, 'student');

        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $owner->id);

        $this->assertFalse(Cache::has('student_auth_check_'.$secondary->id));
        $this->get(route('student.dashboard'))->assertRedirect(route('student.login'));
        $this->assertNull(session('student_id'));
    }

    public function test_assistant_student_view_hides_private_answers_and_financial_records(): void
    {
        $assistant = $this->createAdministrator('assistant');
        $student = Student::factory()->verified()->create([
            'internal_notes' => 'PRIVATE-STAFF-NOTE-913',
            'date_of_birth' => '1985-06-17',
        ]);
        $owner = $this->createAdministrator('super_admin');
        $sessionType = $this->createSessionType();
        $this->createBooking($student, $sessionType, $this->uniqueEmail('assistant-view'));
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Private package', 5, '999.00', '0.00', 'USD', null, 'assistant-private-grant');
        $ledger->recordPayment($package, '999.00', 'assistant-private-payment', $owner->id, reference: 'PRIVATE-TRANSACTION-REF', notes: 'PRIVATE-PAYMENT-NOTE');

        $form = Form::query()->create([
            'title' => 'Assistant visibility form',
            'slug' => 'assistant-visibility-form',
            'status' => 'published',
            'created_by' => $owner->id,
        ]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1]);
        $form->update(['active_version_id' => $version->id]);
        $visibleQuestion = FormQuestion::query()->create([
            'form_version_id' => $version->id,
            'question_key' => 'learning_goal',
            'label' => 'Learning goal',
            'question_type' => 'text',
            'assistant_visible' => true,
        ]);
        $privateQuestion = FormQuestion::query()->create([
            'form_version_id' => $version->id,
            'question_key' => 'private_details',
            'label' => 'Private details',
            'question_type' => 'text',
            'assistant_visible' => false,
        ]);
        $submission = FormSubmission::query()->create([
            'form_version_id' => $version->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now('UTC'),
            'submission_revision' => 1,
        ]);
        FormAnswer::query()->create(['form_submission_id' => $submission->id, 'form_question_id' => $visibleQuestion->id, 'value_text' => 'VISIBLE-LEARNING-GOAL']);
        FormAnswer::query()->create(['form_submission_id' => $submission->id, 'form_question_id' => $privateQuestion->id, 'value_text' => 'PRIVATE-ANSWER-772']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($assistant, 'web')->get(route('admin.students.show', $student->id));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response
            ->assertOk()
            ->assertSee('VISIBLE-LEARNING-GOAL')
            ->assertDontSee('PRIVATE-ANSWER-772')
            ->assertDontSee('Private details')
            ->assertDontSee('PRIVATE-STAFF-NOTE-913')
            ->assertDontSee('1985-06-17')
            ->assertDontSee('PRIVATE-TRANSACTION-REF')
            ->assertDontSee('PRIVATE-PAYMENT-NOTE')
            ->assertDontSee('999.00');

        $selectSql = collect($queries)
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query))
            ->filter(fn (string $query): bool => str_starts_with(ltrim($query), 'select'))
            ->implode("\n");
        foreach (['original_price', 'amount_paid', 'transaction_reference', 'payment_method', 'amount_refunded'] as $financialColumn) {
            $this->assertStringNotContainsString($financialColumn, $selectSql);
        }
    }

    public function test_privacy_erasure_anonymizes_every_booking_contact_but_preserves_shared_contact_and_financial_shells(): void
    {
        $student = Student::factory()->verified()->create([
            'first_name' => 'Nadia',
            'last_name' => 'Hassan',
            'name_normalized' => 'nadia hassan',
            'email' => 'nadia.hassan@example.com',
            'email_normalized' => 'nadia.hassan@example.com',
            'phone' => '+201012345678',
            'phone_normalized' => '+201012345678',
            'date_of_birth' => '1987-04-19',
            'internal_notes' => 'PRIVATE-INTERNAL-NOTE-221',
        ]);
        $otherStudent = Student::factory()->verified()->create();
        $sessionType = $this->createSessionType();
        $sharedContact = Contact::query()->create([
            'name' => 'Nadia Hassan',
            'email' => 'nadia.hassan@example.com',
            'phone' => '+201012345678',
            'notes' => 'CONTACT-PRIVATE-NOTE-991',
        ]);
        $privateContact = Contact::query()->create([
            'name' => 'Nadia Hassan second record',
            'email' => 'nadia.second@example.com',
            'phone' => '+201012345678',
            'notes' => 'SECOND-CONTACT-PRIVATE-NOTE',
        ]);
        $sharedBooking = $this->createBooking($student, $sessionType, 'booking-one@example.com', 25, $sharedContact, [
            'notes' => 'BOOKING-PRIVATE-NOTE-111',
            'cancellation_reason' => 'CANCELLATION-PRIVATE-REASON-333',
        ]);
        $secondBooking = $this->createBooking($student, $sessionType, 'booking-two@example.com', 27, $privateContact);
        $secondBooking->delete();
        $otherBooking = $this->createBooking($otherStudent, $sessionType, 'other-student@example.com', 29, $sharedContact);

        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Erasure retention package', 4, '160.00', '10.00', 'USD', null, 'erasure-package-grant');
        $payment = $ledger->recordPayment($package, '100.00', 'erasure-payment', null, reference: 'RETAIN-REFERENCE-77', notes: 'RETAIN-FINANCIAL-NOTE');
        $refund = $ledger->refund($payment, '10.00', 'erasure-refund', null, 'RETAIN-REFUND-REASON');
        $entryCount = SessionLedgerEntry::query()->where('student_package_id', $package->id)->count();
        $authCacheKey = 'student_auth_check_'.$student->id;
        Cache::put($authCacheKey, 'cached-student');

        $owner = $this->createAdministrator('super_admin');
        $form = Form::query()->create([
            'title' => 'Erasure form',
            'slug' => 'erasure-form',
            'status' => 'published',
            'created_by' => $owner->id,
        ]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1]);
        $form->update(['active_version_id' => $version->id]);
        $question = FormQuestion::query()->create([
            'form_version_id' => $version->id,
            'question_key' => 'personal_story',
            'label' => 'Personal story',
            'question_type' => 'textarea',
        ]);
        $submission = FormSubmission::query()->create([
            'form_version_id' => $version->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now('UTC'),
            'submission_revision' => 1,
        ]);
        FormAnswer::query()->create([
            'form_submission_id' => $submission->id,
            'form_question_id' => $question->id,
            'value_text' => 'Nadia Hassan nadia.hassan@example.com +201012345678',
        ]);
        $revision = FormSubmissionRevision::query()->create([
            'form_submission_id' => $submission->id,
            'revision_number' => 1,
            'snapshot_answers' => ['personal_story' => 'Nadia Hassan nadia.hassan@example.com'],
            'submitted_at' => now('UTC'),
            'created_at' => now('UTC'),
        ]);
        $auditId = DB::table('audit_logs')->insertGetId([
            'event_uuid' => (string) Str::uuid(),
            'actor_type' => 'system',
            'action' => 'legacy_student_profile_updated',
            'entity_type' => Student::class,
            'entity_id' => $student->id,
            'target_type' => Student::class,
            'target_id' => (string) $student->id,
            'previous_data' => json_encode(['message' => 'Profile for Nadia Hassan, DOB 1987-04-19, PRIVATE-INTERNAL-NOTE-221']),
            'new_data' => json_encode(['message' => 'Contact nadia.hassan@example.com +201012345678']),
            'old_values' => json_encode(['message' => 'Profile for Nadia Hassan, DOB 1987-04-19, PRIVATE-INTERNAL-NOTE-221']),
            'new_values' => json_encode(['message' => 'Contact nadia.hassan@example.com +201012345678']),
            'ip_address' => '203.0.113.14',
            'user_agent' => 'Private legacy browser value',
            'created_at' => now('UTC'),
        ]);
        $unrelatedAuditId = DB::table('audit_logs')->insertGetId([
            'event_uuid' => (string) Str::uuid(),
            'actor_type' => 'admin',
            'action' => 'unrelated_system_setting_updated',
            'entity_type' => 'App\\Domains\\Settings\\Models\\Setting',
            'entity_id' => 88,
            'target_type' => 'App\\Domains\\Settings\\Models\\Setting',
            'target_id' => '88',
            'previous_data' => json_encode(['setting' => 'unrelated value']),
            'new_data' => json_encode(['setting' => 'updated value']),
            'old_values' => json_encode(['setting' => 'unrelated value']),
            'new_values' => json_encode(['setting' => 'updated value']),
            'ip_address' => '198.51.100.27',
            'user_agent' => 'Unrelated admin browser',
            'created_at' => now('UTC'),
        ]);
        $revisionCreatedAt = DB::table('form_submission_revisions')->where('id', $revision->id)->selectRaw('UNIX_TIMESTAMP(created_at) AS created_at_epoch')->value('created_at_epoch');
        $auditShellBefore = DB::table('audit_logs')->where('id', $auditId)->select(['event_uuid', 'action'])->selectRaw('UNIX_TIMESTAMP(created_at) AS created_at_epoch')->first();

        app(StudentPrivacyService::class)->anonymize($student->id, $owner->id);

        $erased = Student::withTrashed()->findOrFail($student->id);
        $this->assertSame('Anonymized', $erased->first_name);
        $this->assertSame('Student', $erased->last_name);
        $this->assertSame('anonymized student', $erased->name_normalized);
        $this->assertSame('anonymized_'.$student->id.'@internal.invalid', $erased->email);
        $this->assertNull($erased->phone);
        $this->assertNull($erased->phone_normalized);
        $this->assertNull($erased->date_of_birth);
        $this->assertNull($erased->internal_notes);
        $this->assertNotNull($erased->deleted_at);
        $this->assertNull($sharedBooking->fresh()->notes);
        $this->assertNull($sharedBooking->fresh()->cancellation_reason);
        $this->assertNull($secondBooking->fresh()->notes);
        $this->assertNull($secondBooking->fresh()->cancellation_reason);
        $this->assertNotSame($sharedContact->id, $sharedBooking->fresh()->contact_id);
        $this->assertSame($sharedContact->id, $otherBooking->fresh()->contact_id);
        $this->assertSame('Nadia Hassan', $sharedContact->fresh()->name);
        $this->assertNull($sharedContact->fresh()->deleted_at);
        $this->assertSame('Anonymized Student', $privateContact->fresh()->name);
        $this->assertStringContainsString('@internal.invalid', $privateContact->fresh()->email);
        $this->assertNull($privateContact->fresh()->phone);
        $this->assertNull($privateContact->fresh()->notes);
        $this->assertSame('confirmed', $otherBooking->fresh()->status);
        $this->assertSame('100.00', $payment->fresh()->amount_paid);
        $this->assertSame('10.00', $refund->fresh()->amount_refunded);
        $this->assertSame($entryCount, SessionLedgerEntry::query()->where('student_package_id', $package->id)->count());
        $this->assertSame('[redacted]', FormAnswer::query()->where('form_submission_id', $submission->id)->value('value_text'));
        $revision = FormSubmissionRevision::query()->where('form_submission_id', $submission->id)->firstOrFail();
        $this->assertSame(['personal_story' => '[redacted]'], $revision->snapshot_answers);
        $this->assertSame($revisionCreatedAt, DB::table('form_submission_revisions')->where('id', $revision->id)->selectRaw('UNIX_TIMESTAMP(created_at) AS created_at_epoch')->value('created_at_epoch'));

        $audit = DB::table('audit_logs')->where('id', $auditId)->first();
        $payload = json_encode([$audit->previous_data, $audit->new_data, $audit->old_values, $audit->new_values]);
        foreach (['Nadia', 'Hassan', 'nadia.hassan@example.com', '+201012345678', '1987-04-19', 'PRIVATE-INTERNAL-NOTE-221'] as $identifier) {
            $this->assertStringNotContainsString($identifier, $payload);
        }
        $auditShellAfter = DB::table('audit_logs')->where('id', $auditId)->select(['event_uuid', 'action'])->selectRaw('UNIX_TIMESTAMP(created_at) AS created_at_epoch')->first();
        $this->assertSame((array) $auditShellBefore, (array) $auditShellAfter);
        $this->assertNull($audit->ip_address);
        $this->assertNull($audit->user_agent);
        $unrelatedAudit = DB::table('audit_logs')->where('id', $unrelatedAuditId)->first();
        $this->assertSame('198.51.100.27', $unrelatedAudit->ip_address);
        $this->assertSame('Unrelated admin browser', $unrelatedAudit->user_agent);
        $this->assertFalse(Cache::has($authCacheKey));
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_privacy_erased', 'entity_id' => $student->id]);
    }

    private function createAdministrator(string $role): Administrator
    {
        return Administrator::query()->create([
            'name' => ucfirst($role).' Test',
            'email' => $this->uniqueEmail($role),
            'password' => 'Secure-password-'.Str::random(12),
            'role' => $role,
        ]);
    }

    private function createSessionType(): SessionType
    {
        $slug = 'student-merge-'.Str::lower(Str::random(8));

        return SessionType::query()->create([
            'title' => 'Student Merge Test',
            'slug' => $slug,
            'duration_minutes' => 60,
            'price' => '40.00',
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createBooking(
        Student $student,
        SessionType $sessionType,
        string $email,
        int $daysAhead = 20,
        ?Contact $contact = null,
        array $overrides = [],
    ): Booking {
        $contact ??= Contact::query()->create([
            'name' => 'Student Booking Contact',
            'email' => $email,
        ]);
        $startUtc = CarbonImmutable::now('UTC')->addDays($daysAhead)->startOfHour();
        $endUtc = $startUtc->addMinutes($sessionType->duration_minutes);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            startUtc: $startUtc,
            endUtc: $endUtc,
            customerTimezone: 'Africa/Cairo',
            businessTimezone: 'Africa/Cairo',
        );

        return Booking::query()->create(array_merge($snapshot, [
            'contact_id' => $contact->id,
            'student_id' => $student->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
        ], $overrides));
    }

    private function uniqueEmail(string $prefix): string
    {
        return $prefix.'-'.Str::lower(Str::random(10)).'@example.test';
    }
}
