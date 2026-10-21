<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentPlanningReasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_student_planning_forms_offer_optional_reasons_and_separate_notes_with_approved_copy(): void
    {
        $student = Student::factory()->verified()->create();
        $this->oneHourPackageLesson();
        $this->portal($student);

        $response = $this->get(route('student.scheduling.index'));

        $response->assertSeeText('Plan Around Your Schedule')->assertSeeText("When You're Unavailable")
            ->assertSeeText('Lesson Waitlist')->assertSeeText('Confirm a booking before the time is reserved; joining uses no credit.');
        $xpath = $this->xpath($response->getContent());
        foreach (['holiday', 'waitlist'] as $kind) {
            $form = '//form[@action="'.route('student.scheduling.store', $kind).'"]';
            $reason = $xpath->query($form.'//select[@name="reason_code"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $reason);
            $this->assertFalse($reason->hasAttribute('required'));
            $this->assertSame(1, $xpath->query($form.'//select[@name="reason_code"]/option[@value=""]')->length);
            $this->assertSame(1, $xpath->query($form.'//select[@name="reason_code"]/option[@value="illness"]')->length);
            $notes = $xpath->query($form.'//textarea[@name="notes"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $notes);
            $this->assertFalse($notes->hasAttribute('required'));
        }
    }

    #[TestWith(['holiday'])]
    #[TestWith(['waitlist'])]
    public function test_reason_and_notes_save_independently_without_changing_appointments_or_credits(string $kind): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00', 'UTC'));
        $student = Student::factory()->verified()->create();
        $type = $this->oneHourPackageLesson();
        $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id]);
        $package = app(StudentLedgerService::class)->createPackage($student, 'Engineering planning package', 2, '80.00', '0.00', 'USD', null, 'engineering-planning-credits', entitlementCode: 'one_hour');
        $bookingBefore = $booking->fresh()->getRawOriginal();
        $packageBefore = $package->fresh()->getRawOriginal();
        $ledgerBefore = DB::table('session_ledger_entries')->orderBy('id')->get()->toJson();
        $this->portal($student);
        $data = ['date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'timezone' => 'Africa/Cairo',
            'session_type_id' => $type->id, 'reason_code' => 'holiday', 'notes' => 'Engineering note independent of reason', 'idempotency_key' => 'engineering-'.$kind];

        $this->from(route('student.scheduling.index'))->post(route('student.scheduling.store', $kind), $data)
            ->assertRedirect(route('student.scheduling.index'))->assertSessionHasNoErrors();

        $table = $kind === 'holiday' ? 'student_unavailabilities' : 'booking_waitlists';
        $this->assertDatabaseHas($table, ['student_id' => $student->id, 'reason_code' => 'holiday', 'notes' => 'Engineering note independent of reason']);
        if ($kind === 'holiday') {
            $period = StudentUnavailability::query()->sole();
            $this->assertSame('2026-10-18 21:00:00', $period->starts_at_utc->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-20 21:00:00', $period->ends_at_utc->format('Y-m-d H:i:s'));
            $this->assertSame('active', $period->status);
        } else {
            $period = BookingWaitlist::query()->sole();
            $this->assertSame('2026-10-19', $period->date_from->toDateString());
            $this->assertSame('2026-10-20', $period->date_to->toDateString());
            $this->assertSame('open', $period->status);
        }
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame($packageBefore, $package->fresh()->getRawOriginal());
        $this->assertSame($ledgerBefore, DB::table('session_ledger_entries')->orderBy('id')->get()->toJson());
    }

    #[TestWith(['holiday', false])]
    #[TestWith(['holiday', true])]
    #[TestWith(['waitlist', false])]
    #[TestWith(['waitlist', true])]
    public function test_reason_remains_optional_for_omitted_and_empty_form_values(string $kind, bool $submitEmptyReason): void
    {
        $student = Student::factory()->verified()->create();
        $type = $this->oneHourPackageLesson();
        $this->portal($student);
        $data = ['date_from' => '2026-10-19', 'date_to' => '2026-10-20', 'timezone' => 'Africa/Cairo',
            'session_type_id' => $type->id, 'notes' => 'Engineering notes without a reason', 'idempotency_key' => 'engineering-optional-'.$kind];
        if ($submitEmptyReason) {
            $data['reason_code'] = '';
        }

        $this->post(route('student.scheduling.store', $kind), $data)->assertSessionHasNoErrors();

        $this->assertDatabaseHas($kind === 'holiday' ? 'student_unavailabilities' : 'booking_waitlists',
            ['student_id' => $student->id, 'reason_code' => null, 'notes' => 'Engineering notes without a reason']);
    }

    #[TestWith(['holiday'])]
    #[TestWith(['waitlist'])]
    public function test_invalid_reasons_still_fail_validation_without_creating_a_period(string $kind): void
    {
        $student = Student::factory()->verified()->create();
        $type = $this->oneHourPackageLesson();
        $this->portal($student);

        $this->post(route('student.scheduling.store', $kind), ['date_from' => '2026-10-19', 'date_to' => '2026-10-20',
            'timezone' => 'Africa/Cairo', 'session_type_id' => $type->id, 'reason_code' => 'unlisted_reason', 'idempotency_key' => 'engineering-invalid'])
            ->assertSessionHasErrors(['reason_code' => 'Choose a listed reason.']);

        $this->assertDatabaseCount('student_unavailabilities', 0);
        $this->assertDatabaseCount('booking_waitlists', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'student_unavailability_created']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'student_waitlist_created']);
    }

    public function test_historical_reason_values_and_existing_planning_history_are_preserved(): void
    {
        $student = Student::factory()->verified()->create();
        $type = $this->oneHourPackageLesson();
        $holiday = StudentUnavailability::factory()->create(['student_id' => $student->id, 'reason_code' => 'illness', 'notes' => 'Engineering historical unavailable note',
            'starts_at_utc' => '2026-09-20 00:00:00', 'ends_at_utc' => '2026-09-22 00:00:00', 'timezone' => 'UTC', 'status' => 'cancelled']);
        $interest = BookingWaitlist::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id, 'reason_code' => 'continuing_study',
            'notes' => 'Engineering historical preference note', 'date_from' => '2026-09-23', 'date_to' => '2026-09-24', 'status' => 'closed']);
        $before = [$holiday->fresh()->getRawOriginal(), $interest->fresh()->getRawOriginal()];
        $this->portal($student);

        $response = $this->get(route('student.scheduling.index'));

        $response->assertSeeText('2026-09-20 through 2026-09-21')->assertSeeText('Cancelled')->assertSeeText('2026-09-23 to 2026-09-24')->assertSeeText('Closed');
        $this->assertSame('illness', $response->viewData('holidays')->getCollection()->sole()->reason_code);
        $this->assertSame('continuing_study', $response->viewData('interests')->getCollection()->sole()->reason_code);
        $this->assertSame($before, [$holiday->fresh()->getRawOriginal(), $interest->fresh()->getRawOriginal()]);
    }

    public function test_validation_return_preserves_selected_reason_and_notes_as_separate_fields(): void
    {
        $student = Student::factory()->verified()->create();
        $this->oneHourPackageLesson();
        $this->portal($student);
        $this->withSession(['_old_input' => ['reason_code' => 'illness', 'notes' => 'Engineering retained notes']]);

        $response = $this->get(route('student.scheduling.index'));

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//select[@name="reason_code"]/option[@value="illness" and @selected]')->length);
        $this->assertSame(2, $xpath->query('//textarea[@name="notes" and normalize-space(.)="Engineering retained notes"]')->length);
    }

    private function portal(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html, LIBXML_NONET);

        return new DOMXPath($document);
    }
}
