<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicBookingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['Africa/Cairo', 'Africa/Cairo', '2026-10-26 07:00:00', '2026-10-26 08:00:00', 1, '10:00 AM', '11:00 AM'])]
    #[TestWith(['Africa/Nairobi', 'Africa/Cairo', '2026-10-26 07:00:00', '2026-10-26 08:00:00', 1, '10:00 AM', '11:00 AM'])]
    #[TestWith(['Europe/Paris', 'Africa/Cairo', '2026-10-26 07:00:00', '2026-10-26 08:00:00', 2, '8:00 AM', '9:00 AM'])]
    #[TestWith(['America/New_York', 'Africa/Cairo', '2026-10-26 07:00:00', '2026-10-26 08:00:00', 2, '3:00 AM', '4:00 AM'])]
    #[TestWith(['UTC', 'Europe/London', '2026-03-29 00:30:00', '2026-03-29 01:30:00', 2, '12:30 AM', '1:30 AM'])]
    #[TestWith(['Pacific/Honolulu', 'Pacific/Kiritimati', '2026-10-26 07:00:00', '2026-10-26 08:00:00', 2, '9:00 PM', '10:00 PM'])]
    #[TestWith(['Asia/Tokyo', 'Africa/Cairo', '2026-10-26 14:30:00', '2026-10-26 15:30:00', 2, '11:30 PM', '12:30 AM'])]
    #[TestWith(['Africa/Nairobi', 'Africa/Cairo', '2026-10-29 20:30:00', '2026-10-29 21:30:00', 2, '11:30 PM', '12:30 AM'])]
    public function test_schedule_combines_only_when_both_local_endpoints_match(string $studentZone, string $tutorZone, string $start, string $end, int $panelCount, string $studentStart, string $studentEnd): void
    {
        $this->travelTo(CarbonImmutable::parse($start, 'UTC')->subDay());
        Setting::set('business_timezone', $tutorZone);
        $booking = Booking::factory()->create(['start_at_utc' => $start, 'end_at_utc' => $end, 'customer_timezone' => $studentZone]);

        $response = $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token, 'timezone' => $studentZone]));

        $xpath = $this->xpath($response->getContent());
        $panels = $xpath->query('//*[@data-confirmation-schedule]');
        $this->assertSame($panelCount, $panels->length);
        $this->assertStringContainsString($studentStart, $panels->item(0)->textContent);
        $this->assertStringContainsString($studentEnd, $panels->item(0)->textContent);
        $this->assertStringContainsString($studentZone, $panels->item(0)->textContent);
        $response->assertSeeText($tutorZone);
        $this->assertSame($start, $booking->fresh()->start_at_utc->format('Y-m-d H:i:s'));
        $this->assertSame($end, $booking->fresh()->end_at_utc->format('Y-m-d H:i:s'));
    }

    public function test_cross_midnight_schedule_names_the_end_date_and_dst_offset(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        Setting::set('business_timezone', 'Africa/Cairo');
        $booking = Booking::factory()->create(['start_at_utc' => '2026-10-29 20:30:00', 'end_at_utc' => '2026-10-29 21:30:00', 'customer_timezone' => 'Africa/Nairobi']);

        $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token, 'timezone' => 'Africa/Nairobi']))
            ->assertSeeText('Friday, October 30, 2026')->assertSeeText('End offset: UTC+02:00');
    }

    #[TestWith(['diagnostic-session', 'confirmed', 'direct', true, true, true])]
    #[TestWith(['private-lesson', 'confirmed', 'direct', false, true, false])]
    #[TestWith(['private-lesson', 'confirmed', 'free', false, false, false])]
    #[TestWith(['diagnostic-session', 'cancelled', 'direct', false, false, false])]
    #[TestWith(['diagnostic-session', 'completed', 'direct', false, false, false])]
    #[TestWith(['diagnostic-session', 'pending', 'direct', false, false, false])]
    public function test_one_next_steps_section_respects_session_status_and_funding(string $slug, string $status, string $funding, bool $roadmap, bool $payment, bool $credit): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        $session = SessionType::factory()->create(['slug' => $slug]);
        $booking = Booking::factory()->create(['session_type_id' => $session->id, 'status' => $status, 'funding_mode' => $funding]);

        $response = $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token]));

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//h2[normalize-space()="What Happens Next?"]')->length);
        $nextSteps = $xpath->query('//*[@data-confirmation-next-steps]')->item(0)->textContent;
        $this->assertSame($roadmap, str_contains($nextSteps, 'personalized written learning roadmap'));
        $this->assertSame($payment, str_contains($nextSteps, 'arrange payment'));
        $this->assertSame($credit, str_contains($nextSteps, 'within 48 hours'));
        $this->assertStringContainsString('Date of Birth plus any two registered identifiers', $nextSteps);
        if ($status !== 'confirmed') {
            $response->assertDontSeeText('Your tutor will share the private video meeting link before the lesson.');
        }
        if (in_array($funding, ['package', 'free'], true)) {
            $response->assertDontSeeText('Payment is arranged separately');
        }
    }

    public function test_calendar_utc_endpoints_are_unchanged_for_either_display(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        $booking = Booking::factory()->create(['start_at_utc' => '2026-10-26 07:00:00', 'end_at_utc' => '2026-10-26 08:00:00']);

        $this->get(route('booking.ics', ['token' => $booking->confirmation_token]))
            ->assertSee('DTSTART:20261026T070000Z', false)->assertSee('DTEND:20261026T080000Z', false);
    }

    public function test_real_package_funding_omits_direct_payment_instructions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        $session = $this->oneHourPackageLesson();
        $student = Student::factory()->verified()->create();
        app(StudentLedgerService::class)->createPackage($student, 'Confirmation fixture', 2, '50.00', '0.00', 'USD', null, 'confirmation-package-grant', entitlementCode: 'one_hour');
        AvailabilityRule::create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '12:00', 'buffer_minutes' => 0, 'enabled' => true]);
        $date = CarbonImmutable::parse('2026-10-26', 'Africa/Cairo');
        $slot = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($session, 'Africa/Cairo', $date, $date, 'confirmation-package-owner')['2026-10-26'][0];
        $slotId = app(SlotResolver::class)->issue($session, $slot, 'Africa/Cairo', 'confirmation-package-owner');
        $booking = app(StudentBookingService::class)->create($student, $slotId, $session->id, 'Africa/Cairo', 'confirmation-package-booking', 'confirmation-package-owner');

        $response = $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token]));

        $response->assertSeeText('reserved using a package session credit')->assertDontSeeText('arrange payment')->assertDontSeeText('Payment is arranged separately');
        $this->assertSame(1, $this->xpath($response->getContent())->query('//h2[normalize-space()="What Happens Next?"]')->length);
    }

    public function test_calendar_description_uses_text_line_breaks_and_folds_utf8_safely(): void
    {
        $session = SessionType::factory()->create(['title' => str_repeat('العربية ', 12).'Diagnostic, roadmap; session']);
        $booking = Booking::factory()->create(['session_type_id' => $session->id]);

        $response = $this->get(route('booking.ics', ['token' => $booking->confirmation_token]));

        $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $content = $response->getContent();
        $unfolded = str_replace("\r\n ", '', $content);
        $this->assertStringContainsString('Session\\nBooking Reference: '.$booking->id, $unfolded);
        $this->assertStringNotContainsString('Session\\\\nBooking Reference:', $unfolded);
        $this->assertStringContainsString('Diagnostic\\, roadmap\\; session', $unfolded);
        foreach (explode("\r\n", $content) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }

    #[TestWith(['pending'])]
    #[TestWith(['cancelled'])]
    #[TestWith(['completed'])]
    #[TestWith(['no_show'])]
    public function test_calendar_route_rejects_bookings_without_a_confirmed_calendar_action(string $status): void
    {
        $booking = Booking::factory()->create(['status' => $status]);

        $this->get(route('booking.ics', ['token' => $booking->confirmation_token]))->assertNotFound();
    }

    public function test_calendar_downloads_preserve_records_and_token_link_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        $booking = Booking::factory()->create(['start_at_utc' => '2026-10-26 07:00:00', 'end_at_utc' => '2026-10-26 08:00:00']);
        $before = $booking->fresh()->getRawOriginal();
        $first = $this->get(route('booking.ics', ['token' => $booking->confirmation_token]))->assertOk();
        $second = $this->get(route('booking.ics', ['token' => $booking->confirmation_token]))->assertOk();

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertStringContainsString('attachment; filename=', $first->headers->get('Content-Disposition'));
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertSame(1, Booking::count());
        $this->assertSame(0, BookingHold::count());
        $this->get(route('booking.ics', ['token' => 'unknown-calendar-token']))->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 08:00:00', 'UTC'));
        $this->get(route('booking.ics', ['token' => $booking->confirmation_token]))->assertGone();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }
}
