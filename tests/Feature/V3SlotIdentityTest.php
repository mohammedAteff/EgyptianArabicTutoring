<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class V3SlotIdentityTest extends TestCase
{
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionType = SessionType::create([
            'title' => 'V3 Slot Test',
            'slug' => 'v3-slot-test',
            'duration_minutes' => 60,
            'price' => 30,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            AvailabilityRule::create([
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'buffer_minutes' => 0,
                'enabled' => true,
            ]);
        }
    }

    public function test_manual_timezone_remains_authoritative_after_browser_detection_and_remount(): void
    {
        $wizard = Livewire::test(BookingWizard::class)
            ->call('setDetectedTimezone', 'America/New_York')
            ->call('selectTimezone', 'Asia/Kolkata')
            ->assertSet('customerTimezone', 'Asia/Kolkata')
            ->assertSet('manualTimezoneSelected', true)
            ->call('setDetectedTimezone', 'Europe/Paris')
            ->assertSet('customerTimezone', 'Asia/Kolkata');

        $remounted = Livewire::test(BookingWizard::class)
            ->assertSet('customerTimezone', 'Asia/Kolkata')
            ->assertSet('manualTimezoneSelected', true)
            ->call('setDetectedTimezone', 'Africa/Cairo')
            ->assertSet('customerTimezone', 'Asia/Kolkata');

        $this->assertSame($wizard->get('visitorToken'), $remounted->get('visitorToken'));
    }

    public function test_server_issued_slot_resolves_but_tampered_identity_or_other_visitor_is_rejected(): void
    {
        $start = CarbonImmutable::now('Africa/Cairo')->addDays(3)->setTime(10, 0)->setTimezone('UTC')->toDateTimeString();
        $resolver = app(SlotResolver::class);
        $slotId = $this->slotIdFor($this->sessionType, $start, 'Asia/Kolkata', 'visitor-one');

        $resolved = $resolver->resolve($slotId, $this->sessionType, 'Asia/Kolkata', 'visitor-one');
        $this->assertSame($start, $resolved['slot_start_utc']);

        $identity = json_decode(Crypt::decryptString($slotId), true, 512, JSON_THROW_ON_ERROR);
        $identity['start_utc'] = CarbonImmutable::parse($start, 'UTC')->addHour()->toDateTimeString();
        $fabricatedId = Crypt::encryptString(json_encode($identity, JSON_THROW_ON_ERROR));
        $identity['start_utc'] = $start;
        $identity['resource_id'] = 'other_tutor';
        $otherResourceId = Crypt::encryptString(json_encode($identity, JSON_THROW_ON_ERROR));

        foreach ([
            [$fabricatedId, 'Asia/Kolkata', 'visitor-one'],
            [$otherResourceId, 'Asia/Kolkata', 'visitor-one'],
            [$slotId, 'Africa/Cairo', 'visitor-one'],
            [$slotId, 'Asia/Kolkata', 'visitor-two'],
            ['not-a-slot', 'Asia/Kolkata', 'visitor-one'],
        ] as [$candidate, $timezone, $visitor]) {
            try {
                $resolver->resolve($candidate, $this->sessionType, $timezone, $visitor);
                $this->fail('An unauthorized slot identity was accepted.');
            } catch (SlotUnavailableException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_expired_slot_identity_is_rejected(): void
    {
        $start = CarbonImmutable::now('Africa/Cairo')->addDays(3)->setTime(10, 0)->setTimezone('UTC')->toDateTimeString();
        $slotId = $this->slotIdFor($this->sessionType, $start, 'Africa/Cairo', 'visitor-one');

        $identity = json_decode(Crypt::decryptString($slotId), true, 512, JSON_THROW_ON_ERROR);
        $identity['expires_at'] = now('UTC')->subSecond()->getTimestamp();
        $expiredId = Crypt::encryptString(json_encode($identity, JSON_THROW_ON_ERROR));

        $this->expectException(SlotUnavailableException::class);
        app(SlotResolver::class)->resolve($expiredId, $this->sessionType, 'Africa/Cairo', 'visitor-one');
    }

    public function test_slot_policy_change_invalidates_previously_issued_identity(): void
    {
        $start = CarbonImmutable::now('Africa/Cairo')->addDays(3)->setTime(10, 0)->setTimezone('UTC')->toDateTimeString();
        $slotId = $this->slotIdFor($this->sessionType, $start, 'Africa/Cairo', 'visitor-one');

        AvailabilityRule::query()->update(['buffer_minutes' => 15]);

        $this->expectException(SlotUnavailableException::class);
        app(SlotResolver::class)->resolve($slotId, $this->sessionType, 'Africa/Cairo', 'visitor-one');
    }

    public function test_geographic_zone_without_local_svg_uses_country_flag_not_globe(): void
    {
        $display = app(TimezoneDisplayService::class)->formatSlotForDisplay('Asia/Kolkata', now('UTC'));

        $this->assertSame('IN', $display['timezone_country_code']);
        $this->assertNull($display['flag_asset']);
        $this->assertNotEmpty($display['flag_symbol']);
    }

    public function test_livewire_rejects_client_fabricated_slot_without_creating_hold(): void
    {
        Livewire::test(BookingWizard::class)
            ->call('selectSlot', 'arbitrary-utc-time-or-slot')
            ->assertSet('holdId', null)
            ->assertSet('currentStep', 2);

        $this->assertSame(0, BookingHold::count());
    }

    public function test_manual_timezone_booking_uses_server_slot_utc_instant_and_preserves_timezone(): void
    {
        $start = CarbonImmutable::now('Africa/Cairo')->addDays(3)->setTime(10, 0)->setTimezone('UTC')->toDateTimeString();
        $wizard = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Asia/Kolkata');

        $wizard->call('selectSlot', $this->slotIdFor($this->sessionType, $start, 'Asia/Kolkata', $wizard->get('visitorToken')))
            ->assertSet('currentStep', 3)
            ->set('name', 'Kavya Student')
            ->set('email', 'kavya@example.com')
            ->call('submitDetails');

        $canonicalDate = CarbonImmutable::parse($start, 'UTC')->setTimezone('Asia/Kolkata')->toDateString();
        $wizard->assertSet('selectedDate', $canonicalDate)
            ->call('selectDate', CarbonImmutable::parse($start, 'UTC')->addDay()->toDateString())
            ->assertSet('selectedDate', $canonicalDate)
            ->call('confirmBooking');

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($start, $booking->start_at_utc->setTimezone('UTC')->toDateTimeString());
        $this->assertSame('Asia/Kolkata', $booking->customer_timezone);
    }

    public function test_deactivated_session_type_cannot_issue_new_public_hold(): void
    {
        $start = CarbonImmutable::now('Africa/Cairo')->addDays(3)->setTime(10, 0)->setTimezone('UTC')->toDateTimeString();
        $wizard = Livewire::test(BookingWizard::class);
        $slotId = $this->slotIdFor($this->sessionType, $start, 'Africa/Cairo', $wizard->get('visitorToken'));

        $this->sessionType->update(['active' => false]);

        $wizard->call('selectSlot', $slotId)
            ->assertSet('holdId', null)
            ->assertSet('currentStep', 2);
        $this->assertSame(0, BookingHold::count());
    }
}
