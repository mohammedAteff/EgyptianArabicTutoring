<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\Concerns\HasPublishedShortForm;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class BookingLanguageSwitchSyncTest extends TestCase
{
    use HasPublishedShortForm;
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installShortFormFixture();

        RateLimiter::clear('throttle:hold:ip:127.0.0.1');

        $this->sessionType = SessionType::create([
            'title' => 'Standard Arabic Lesson',
            'slug' => 'standard-lesson',
            'duration_minutes' => 60,
            'price' => 30.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'buffer_minutes' => 0,
                'enabled' => true,
            ]);
        }
    }

    public function test_switch_language_action_preserves_unsent_dom_inputs_and_redirects_without_pii(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(3)->setTime(11, 0, 0);
        // Step 1: Initialize wizard, select timezone & slot -> advances to step 3 with active hold
        $component = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Europe/Paris')
            ->call('selectDate', $slotStart->setTimezone('Europe/Paris')->format('Y-m-d'));
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Europe/Paris', $component->get('visitorToken')));

        $this->assertEquals(3, $component->get('currentStep'));
        $holdId = $component->get('holdId');
        $holdToken = $component->get('holdToken');
        $this->assertNotNull($holdId);
        $this->assertNotNull($holdToken);

        // Simulate user rapidly typing into DOM inputs without blur/debounce completion,
        // then clicking the visible language switch link which passes pending DOM values:
        $pendingDomData = [
            'first_name' => 'Jean-Luc',
            'last_name' => 'Picard',
            'date_of_birth' => '1990-01-15',
            'email' => 'jeanluc@starfleet.fr',
            'phone' => '+33 6 98 76 54 32',
            'notes' => 'Focus on conversational Cairo marketplace dialogue',
        ];

        $component->call('switchLanguage', 'fr', $pendingDomData);

        // Assert redirect occurs to French booking route
        $component->assertRedirect(url('/fr/reservation'));

        // Assert NO PII leaked into the redirect URL
        $redirectUrl = $component->effects['redirect'] ?? '';
        $this->assertStringNotContainsString('jeanluc', $redirectUrl);
        $this->assertStringNotContainsString('starfleet', $redirectUrl);
        $this->assertStringNotContainsString('33698765432', $redirectUrl);
        $this->assertStringNotContainsString('token=', $redirectUrl);

        // Verify session state was updated with pending values before navigation
        $sessionState = Session::get('booking_flow_state');
        $this->assertNotNull($sessionState);
        $this->assertEquals('Jean-Luc Picard', $sessionState['name']);
        $this->assertEquals('Jean-Luc', $sessionState['first_name']);
        $this->assertEquals('Picard', $sessionState['last_name']);
        $this->assertEquals('1990-01-15', $sessionState['date_of_birth']);
        $this->assertEquals('jeanluc@starfleet.fr', $sessionState['email']);
        $this->assertEquals('+33 6 98 76 54 32', $sessionState['phone']);
        $this->assertEquals('Focus on conversational Cairo marketplace dialogue', $sessionState['notes']);
        $this->assertEquals($holdId, $sessionState['hold_id']);
        $this->assertEquals($holdToken, $sessionState['hold_token']);
        $this->assertEquals(3, $sessionState['current_step']);

        // Mount a new component on the French route within the same session
        $frenchComponent = Livewire::test(BookingWizard::class);

        // Verify all pending data, step, timezone, and the exact same hold survive
        $this->assertEquals(3, $frenchComponent->get('currentStep'));
        $this->assertEquals('Jean-Luc Picard', $frenchComponent->get('name'));
        $this->assertEquals('Jean-Luc', $frenchComponent->get('first_name'));
        $this->assertEquals('Picard', $frenchComponent->get('last_name'));
        $this->assertEquals('1990-01-15', $frenchComponent->get('date_of_birth'));
        $this->assertEquals('jeanluc@starfleet.fr', $frenchComponent->get('email'));
        $this->assertEquals('+33 6 98 76 54 32', $frenchComponent->get('phone'));
        $this->assertEquals('Focus on conversational Cairo marketplace dialogue', $frenchComponent->get('notes'));
        $this->assertEquals('Europe/Paris', $frenchComponent->get('customerTimezone'));
        $this->assertEquals($holdId, $frenchComponent->get('holdId'));
        $this->assertEquals($holdToken, $frenchComponent->get('holdToken'));

        // Verify NO duplicate hold was created in the database
        $totalHolds = BookingHold::where('session_token', session()->getId())->count();
        $this->assertEquals(1, $totalHolds, 'Exactly one hold must exist across language switch');
    }

    public function test_switch_language_to_german_at_confirmation_step_preserves_hold_and_details(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(4)->setTime(14, 0, 0);
        $component = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Europe/Berlin')
            ->call('selectDate', $slotStart->setTimezone('Europe/Berlin')->format('Y-m-d'));
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Europe/Berlin', $component->get('visitorToken')))
            ->set('first_name', 'Klaus')
            ->set('last_name', 'Mueller')
            ->set('date_of_birth', '1990-02-10')
            ->set('email', 'klaus@example.de')
            ->set('phone', '+49 170 1234567')
            ->set('notes', 'German speaker learning Arabic')
            ->call('submitDetails');

        $this->assertEquals(4, $component->get('currentStep'), 'Must be at step 4 review');
        $holdId = $component->get('holdId');

        // Click German switcher
        $component->call('switchLanguage', 'de');
        $component->assertRedirect(url('/de/buchen'));

        // Mount new component at /de/buchen
        $deComponent = Livewire::test(BookingWizard::class);

        $this->assertEquals(4, $deComponent->get('currentStep'), 'Step 4 review preserved');
        $this->assertEquals('Klaus Mueller', $deComponent->get('name'));
        $this->assertEquals('klaus@example.de', $deComponent->get('email'));
        $this->assertEquals('+49 170 1234567', $deComponent->get('phone'));
        $this->assertEquals('Europe/Berlin', $deComponent->get('customerTimezone'));
        $this->assertEquals($holdId, $deComponent->get('holdId'));
    }

    public function test_timezone_switches_refresh_customer_slot_display_and_preserve_the_owned_hold(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(3)->setTime(12, 0, 0);
        $component = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Africa/Cairo')
            ->call('selectDate', $slotStart->setTimezone('Africa/Cairo')->format('Y-m-d'));

        $component->assertSee('Cairo · Africa/Cairo · UTC+3')
            ->assertSee('Tutor equivalent: 3:00 PM – 4:00 PM');

        foreach (['Pacific/Honolulu', 'Europe/Berlin', 'America/New_York'] as $timezone) {
            $component->call('selectTimezone', $timezone);

            $this->assertSame($timezone, $component->get('customerTimezone'));
            $this->assertSame($slotStart->setTimezone($timezone)->toDateString(), $component->get('selectedDate'));
            $component->assertSee($timezone);
        }

        $visitorToken = $component->get('visitorToken');
        $component->call('selectSlot', $this->slotIdFor(
            $this->sessionType,
            $slotStart->toDateTimeString(),
            'America/New_York',
            $visitorToken,
        ));

        $holdId = $component->get('holdId');
        $originalSlotId = $component->get('selectedSlotId');
        $this->assertNotNull($holdId);
        $this->assertSame(3, $component->get('currentStep'));

        foreach (['Pacific/Honolulu', 'Europe/Berlin', 'America/New_York'] as $timezone) {
            $component->call('selectTimezone', $timezone);
            $localStart = $slotStart->setTimezone($timezone);

            $this->assertSame(3, $component->get('currentStep'));
            $this->assertSame($holdId, $component->get('holdId'));
            $this->assertSame($slotStart->toDateTimeString(), $component->get('selectedSlotStartUtc'));
            $this->assertSame($timezone, $component->get('selectedSlot')['customer_timezone']);
            $this->assertSame($localStart->format('g:i A'), $component->get('selectedSlot')['customer_formatted']);
            $component->assertSee($localStart->format('g:i A'))
                ->assertSee($timezone);
        }

        $this->assertNotSame($originalSlotId, $component->get('selectedSlotId'));
        $this->assertSame('active', BookingHold::query()->findOrFail($holdId)->status);

        $idempotencyKey = $component->get('idempotencyKey');
        $component->set('first_name', 'Timezone')
            ->set('last_name', 'Test Student')
            ->set('date_of_birth', '1990-03-10')
            ->set('email', 'timezone-test@example.test')
            ->set('phone', '+201000000010')
            ->call('submitDetails')
            ->call('selectTimezone', 'America/New_York')
            ->assertSee('Your Local Time')
            ->assertSee('New York (America/New_York, UTC-4)');

        $component->call('confirmBooking');
        $booking = Booking::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

        $this->assertSame('America/New_York', $booking->customer_timezone);
        $this->get(route('booking.confirmation', $booking->confirmation_token))
            ->assertOk()
            ->assertSeeText('America/New_York')
            ->assertSee('assets/flags/4x3/us.svg', false)
            ->assertSee('assets/flags/4x3/eg.svg', false);
    }

    public function test_switch_language_with_expired_hold_resets_to_step_two_and_retains_contact_data(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(9, 0, 0);
        $component = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Europe/Paris')
            ->call('selectDate', $slotStart->setTimezone('Europe/Paris')->format('Y-m-d'));
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Europe/Paris', $component->get('visitorToken')))
            ->set('first_name', 'Amelie')
            ->set('last_name', 'Poulain')
            ->set('date_of_birth', '1990-04-12')
            ->set('email', 'amelie@paris.fr');

        $holdId = $component->get('holdId');
        $this->assertNotNull($holdId);

        // Expire hold in database
        BookingHold::where('id', $holdId)->update([
            'expires_at' => now()->subMinutes(10),
            'status' => 'expired',
        ]);

        // User switches language to German
        $component->call('switchLanguage', 'de');

        // Mount new component
        $newComponent = Livewire::test(BookingWizard::class);

        // Hold is expired, so user must be sent back to step 2 to choose a fresh slot,
        // but contact inputs are preserved so they don't have to retype them
        $this->assertNull($newComponent->get('holdId'));
        $this->assertEquals(2, $newComponent->get('currentStep'));
        $this->assertEquals('Amelie Poulain', $newComponent->get('name'));
        $this->assertEquals('amelie@paris.fr', $newComponent->get('email'));
    }
}
