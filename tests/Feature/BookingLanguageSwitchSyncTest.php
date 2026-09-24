<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class BookingLanguageSwitchSyncTest extends TestCase
{
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name' => 'Jean-Luc Picard',
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
            ->set('name', 'Klaus Mueller')
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

    public function test_switch_language_with_expired_hold_resets_to_step_two_and_retains_contact_data(): void
    {
        $slotStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(9, 0, 0);
        $component = Livewire::test(BookingWizard::class)
            ->call('selectTimezone', 'Europe/Paris')
            ->call('selectDate', $slotStart->setTimezone('Europe/Paris')->format('Y-m-d'));
        $component->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStart->toDateTimeString(), 'Europe/Paris', $component->get('visitorToken')))
            ->set('name', 'Amelie Poulain')
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
