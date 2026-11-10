<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\Forms\Services\FormBuilderService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\HasPublishedShortForm;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class PublicBookingRefinementTest extends TestCase
{
    use HasPublishedShortForm;
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_single_session_keeps_three_stage_progress_and_authoritative_summary(): void
    {
        $session = SessionType::factory()->create(['title' => 'Diagnostic & Learning Roadmap', 'slug' => 'diagnostic-session', 'price' => '25.00', 'description' => 'A personal level assessment.', 'public_bookable' => true]);

        $component = Livewire::test(BookingWizard::class);

        $component->assertSet('currentStep', 2)->assertSet('selectedSessionTypeId', $session->id)
            ->assertSee('25.00 USD')->assertSee('Diagnostic &amp; Learning Roadmap', false)
            ->assertDontSee('Choose Your Session');
        $xpath = $this->xpath($component->html());
        $this->assertSame(3, $xpath->query('//nav[@aria-label="Booking progress"]//li')->length);
        $this->assertSame('1', $xpath->query('//button[@aria-current="step"]/span')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//nav//button[not(@disabled)]')->length);
        $this->assertSame(0, BookingHold::count());
    }

    public function test_diagnostic_scope_uses_published_booking_copy_without_changing_the_service_record(): void
    {
        $text = 'New here? Your first step is the Diagnostic & Learning Roadmap—a friendly introduction, level assessment, and learning consultation for Egyptian Arabic, Modern Standard Arabic (MSA), or both.';
        $session = SessionType::factory()->create(['slug' => 'diagnostic-session', 'description' => 'Original service description.', 'public_bookable' => true]);
        WebsiteCopy::factory()->create(['page' => 'booking', 'locale' => 'en', 'published_content' => ['ui__'.sha1($text) => 'Published diagnostic guidance for Egyptian Arabic and MSA.']]);

        $this->get('/booking')->assertSeeText('Published diagnostic guidance for Egyptian Arabic and MSA.');

        $this->assertSame('Original service description.', $session->fresh()->description);
    }

    public function test_available_date_list_uses_server_dates_and_selecting_a_date_never_reserves_it(): void
    {
        [$session] = $this->prepareBooking();
        $component = Livewire::test(BookingWizard::class)->call('selectTimezone', 'Europe/Paris');
        $xpath = $this->xpath($component->html());
        $dates = $xpath->query('//*[@data-public-date-list]/button');
        $this->assertSame(2, $dates->length);
        $this->assertSame('Monday, October 26, 2026', $dates->item(0)->getAttribute('aria-label'));
        $this->assertSame('Tuesday, October 27, 2026', $dates->item(1)->getAttribute('aria-label'));

        $component->call('selectDate', '2026-10-27');

        $component->assertSet('currentStep', 2)->assertSet('holdId', null)->assertSee('Tutor equivalent:');
        $xpath = $this->xpath($component->html());
        $this->assertSame(1, $xpath->query('//*[@data-public-date-list]/button[@aria-pressed="true"]')->length);
        $this->assertSame(0, BookingHold::count());
        $this->assertSame(0, Booking::count());
        $component->call('nextMonth')->assertSet('selectedDate', null)->assertSet('holdId', null);
        $this->assertSame(0, BookingHold::count());
    }

    public function test_shared_timezone_default_keeps_its_existing_label_and_current_offset(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));

        $html = view('components.timezone-selector', ['timezone' => 'Africa/Cairo', 'attributes' => new ComponentAttributeBag])->render();

        $this->assertStringContainsString('Showing times in your timezone:', $html);
        $this->assertStringContainsString('Africa/Cairo, UTC+3', $html);
        $this->assertStringNotContainsString('data-public-booking-timezone', $html);
    }

    public function test_multiple_session_choices_are_buttons_and_advance_without_acquiring_a_hold(): void
    {
        SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
        $session = SessionType::factory()->create(['title' => 'Eligible returning learner fixture', 'price' => '42.00', 'currency' => 'EUR', 'public_bookable' => true]);
        $component = Livewire::test(BookingWizard::class)->assertSet('currentStep', 1);
        $xpath = $this->xpath($component->html());
        $this->assertSame(2, $xpath->query('//button[contains(@class,"booking-session-option")]')->length);
        $this->assertSame(4, $xpath->query('//nav//li')->length);

        $component->call('selectSession', $session->id);

        $component->assertSet('currentStep', 2)->assertSet('selectedSessionTypeId', $session->id)->assertSee('42.00 EUR');
        $this->assertSame(0, BookingHold::count());
    }

    public function test_no_sessions_provides_a_contact_recovery_without_an_unusable_calendar(): void
    {
        SessionType::query()->update(['public_bookable' => false]);

        $this->get('/booking')->assertSeeText('No sessions are available to book right now.')
            ->assertSeeText('Contact Abdallah')->assertDontSee('data-public-booking-calendar', false);
    }

    public function test_no_slots_explains_the_next_action_and_calendar_variant_is_optional(): void
    {
        SessionType::factory()->create(['public_bookable' => true]);
        AvailabilityRule::query()->delete();

        Livewire::test(BookingWizard::class)->assertSee('No available times this month.')
            ->assertSee('data-public-booking-calendar', false)
            ->assertSee('View Next Month')->assertDontSee('Available lesson times')
            ->call('selectDate', now()->addDay()->toDateString())
            ->assertSee('No available times this month.')->assertDontSee('Choose an available date');

        $defaultCalendar = view('components.booking-calendar', ['month' => '2026-10', 'timezone' => 'UTC', 'slots' => [], 'attributes' => new ComponentAttributeBag])->render();
        $this->assertStringNotContainsString('data-public-booking-calendar', $defaultCalendar);
        $this->assertStringNotContainsString('data-public-date-list', $defaultCalendar);
    }

    public function test_empty_month_recovery_is_bounded_and_never_acquires_a_hold(): void
    {
        $this->prepareBooking();
        AvailabilityRule::query()->update(['enabled' => false]);
        $component = Livewire::test(BookingWizard::class)->set('calendarMonth', '2026-12');
        $component->assertSee('No further months are currently open for booking.')->assertDontSee('View Next Month')
            ->assertDontSee('Available lesson times');
        $xpath = $this->xpath($component->html());
        $this->assertSame(1, $xpath->query('//button[@aria-label="Next Month"][@disabled]')->length);

        AvailabilityRule::query()->update(['enabled' => true]);
        $component->call('previousMonth')->assertSet('calendarMonth', '2026-11')->assertSee('Available lesson times');
        AvailabilityRule::query()->update(['enabled' => false]);
        $component->call('previousMonth')->assertSet('calendarMonth', '2026-10')->assertSee('View Next Month');
        $component->call('nextMonth')->assertSet('calendarMonth', '2026-11')->assertSet('selectedDate', null)
            ->assertSet('currentStep', 2)->assertSet('holdId', null);
        $this->assertSame(0, BookingHold::count());
        $this->assertSame(0, Booking::count());
    }

    public function test_empty_selected_date_does_not_hide_other_available_dates_in_the_month(): void
    {
        $this->prepareBooking();

        Livewire::test(BookingWizard::class)->call('selectDate', '2026-10-26')
            ->assertSet('selectedDate', null)->assertSee('That date has no available times. Please choose another available date.')
            ->assertDontSee('No available times this month.')->assertDontSee('View Next Month');

        $this->assertSame(0, BookingHold::count());
    }

    public function test_adjacent_business_day_slots_do_not_make_an_empty_customer_month_look_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00:00', 'UTC'));
        Setting::set('business_timezone', 'Africa/Cairo');
        SessionType::factory()->create(['public_bookable' => true, 'duration_minutes' => 60]);
        AvailabilityRule::create(['weekday' => 0, 'start_time' => '00:00', 'end_time' => '01:00', 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 2, 'enabled' => true]);

        Livewire::test(BookingWizard::class)->call('selectTimezone', 'Pacific/Honolulu')->set('calendarMonth', '2026-11')
            ->assertSee('No available times this month.')->assertDontSee('Available lesson times');

        $this->assertSame(0, BookingHold::count());
    }

    public function test_required_group_feedback_links_to_the_group_and_preserves_valid_contact_values(): void
    {
        $builder = app(FormBuilderService::class);
        $form = $builder->create(['title' => 'Required group fixture', 'slug' => 'required-group-fixture', 'triggers' => ['pre_booking'], 'is_mandatory' => true], [
            ['question_key' => 'tracks', 'label' => 'Learning tracks', 'question_type' => 'multiple_choice', 'description' => 'Choose a learning track.', 'is_required' => true, 'options' => [['value' => 'msa', 'label' => 'MSA'], ['value' => 'egyptian', 'label' => 'Egyptian Arabic']]],
        ], AdministratorFactory::new()->create(['role' => 'assistant']));
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        [$session, $questionId] = $this->prepareBooking();
        $component = $this->fillDetails($this->selectTime($session));

        $component->call('submitDetails')->assertHasErrors(['intakeAnswers.'.$questionId])->assertSet('currentStep', 3)
            ->assertSet('first_name', 'Synthetic')->assertSet('email', 'synthetic.learner@example.com');

        $xpath = $this->xpath($component->html());
        $group = $xpath->query('//*[@id="intake_'.$questionId.'"]')->item(0);
        $this->assertSame('true', $group->getAttribute('aria-invalid'));
        $this->assertStringContainsString('intake_'.$questionId.'_error', $group->getAttribute('aria-describedby'));
        $this->assertSame(1, $xpath->query('//*[@data-validation-summary]//a[@href="#intake_'.$questionId.'"]')->length);
        $this->assertSame(0, $xpath->query('//input[@type="checkbox"][@required]')->length);
        $component->set('intakeAnswers.'.$questionId, ['unsupported'])->call('submitDetails')
            ->assertHasErrors(['intakeAnswers.'.$questionId])->assertSee('Choose only available options.');
        $component->set('intakeAnswers.'.$questionId, ['msa'])->call('submitDetails')->assertHasNoErrors()->assertSet('currentStep', 4);
        $this->assertSame(1, BookingHold::count());
        $this->assertSame(0, Booking::count());
    }

    public function test_invalid_details_have_associated_feedback_and_can_be_corrected_without_losing_the_hold(): void
    {
        [$session, $questionId] = $this->prepareBooking();
        $component = $this->selectTime($session);
        $holdId = $component->get('holdId');

        $component->call('submitDetails');

        $component->assertHasErrors(['first_name', 'last_name', 'email', 'phone', 'date_of_birth'])->assertSet('currentStep', 3)
            ->assertSee('Please check your details')->assertSee('Your Contact Details')->assertSee('Your Arabic Learning Goals');
        $xpath = $this->xpath($component->html());
        $this->assertSame('true', $xpath->query('//input[@id="first_name"]')->item(0)->getAttribute('aria-invalid'));
        $this->assertSame('first_name_error', $xpath->query('//input[@id="first_name"]')->item(0)->getAttribute('aria-describedby'));
        $this->assertStringContainsString('Please provide your first name.', $xpath->query('//*[@id="first_name_error"]')->item(0)->textContent);

        $this->fillDetails($component)->set('intakeAnswers.'.$questionId, 'Conversations with my family')->call('submitDetails')
            ->assertHasNoErrors()->assertSet('currentStep', 4)->assertSet('holdId', $holdId)
            ->assertSee('Conversations with my family')->assertSee('Booking confirmation does not mean payment has been received.')
            ->assertSee('Booking the Diagnostic does not purchase a coaching package.');
        $component->call('goToStep', 3)->assertSet('first_name', 'Synthetic')->assertSet('holdId', $holdId);
        $component->call('goToStep', 4)->assertSet('currentStep', 3);
        $this->assertSame(1, BookingHold::count());
    }

    public function test_real_booking_converts_the_hold_and_duplicate_confirmation_reuses_the_booking(): void
    {
        Mail::fake();
        Notification::fake();
        [$session] = $this->prepareBooking();
        $component = $this->fillDetails($this->selectTime($session))->call('submitDetails');
        $holdId = $component->get('holdId');

        $component->call('confirmBooking');

        $booking = Booking::sole();
        $component->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
        $this->assertSame('converted', BookingHold::findOrFail($holdId)->status);
        $this->assertSame('confirmed', $booking->status);
        $component->call('confirmBooking')->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
        $this->assertSame(1, Booking::count());
        $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token]))
            ->assertSeeText('Confirmed')->assertSeeText('25.00 USD')->assertSeeText('Payment is arranged separately')
            ->assertSeeText('Add to Calendar (.ics)');
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    #[TestWith(['fr', 'Quelques informations aideront Abdallah à préparer votre séance', 'Cette adresse servira aux échanges concernant votre réservation.', 'Heure du professeur'])]
    #[TestWith(['de', 'Ein paar Angaben helfen Abdallah, Ihre Sitzung vorzubereiten', 'Wir verwenden diese Adresse für Mitteilungen zu Ihrer Buchung.', 'Uhrzeit der Lehrkraft'])]
    public function test_localized_details_and_review_format_held_times_from_the_booking_instant(string $locale, string $introduction, string $emailHelp, string $tutorTime): void
    {
        [$session] = $this->prepareBooking();
        app()->setLocale($locale);
        $component = Livewire::test(BookingWizard::class)->call('selectTimezone', 'Europe/Paris')->call('selectDate', '2026-10-27');
        $component->call('selectSlot', $this->slotIdFor($session, '2026-10-27 12:00:00', 'Europe/Paris', $component->get('visitorToken')))
            ->assertSet('currentStep', 3)->assertSee($introduction)->assertSee($emailHelp)->assertSee('13:00 – 14:00')->assertDontSee('1:00 PM');
        $holdId = $component->get('holdId');
        $this->fillDetails($component)->call('submitDetails')->assertHasNoErrors()->assertSet('currentStep', 4)
            ->assertSee('13:00 - 14:00')->assertSee($tutorTime)->assertDontSee('1:00 PM')->assertSet('holdId', $holdId);
        $this->assertSame('2026-10-27 12:00:00', $component->get('selectedSlotStartUtc'));
    }

    #[TestWith(['fr', 'Je débute complètement en arabe.'])]
    #[TestWith(['de', 'Ich fange mit Arabisch ganz von vorn an.'])]
    public function test_booking_review_translates_option_labels_and_preserves_freely_written_answers(string $locale, string $choice): void
    {
        $builder = app(FormBuilderService::class);
        $source = "I'm completely new to Arabic.";
        $form = $builder->create(['title' => 'Review fixture', 'slug' => 'review-fixture', 'triggers' => ['pre_booking']], [
            ['question_key' => 'experience', 'label' => 'Experience', 'question_type' => 'single_choice', 'options' => [['value' => 'new', 'label' => $source]]],
            ['question_key' => 'comment', 'label' => 'Your goal', 'question_type' => 'short_text'],
        ], AdministratorFactory::new()->create());
        $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
        [$session] = $this->prepareBooking();
        app()->setLocale($locale);
        $component = Livewire::test(BookingWizard::class)->call('selectTimezone', 'Europe/Paris')->call('selectDate', '2026-10-27');
        $component->call('selectSlot', $this->slotIdFor($session, '2026-10-27 12:00:00', 'Europe/Paris', $component->get('visitorToken')));
        $this->fillDetails($component)->set('intakeAnswers.'.$form->publishedVersion->questions->firstWhere('question_key', 'experience')->id, 'new')
            ->set('intakeAnswers.'.$form->publishedVersion->questions->firstWhere('question_key', 'comment')->id, $source)
            ->call('submitDetails')->assertHasNoErrors()->assertSet('currentStep', 4);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
        $answers = (new DOMXPath($document))->query('//dl[contains(@class, "booking-intake-review")]//dd');
        $this->assertSame($choice, $answers->item(0)->textContent);
        $this->assertSame($source, $answers->item(1)->textContent);
    }

    public function test_expired_details_cannot_open_review_and_clears_persisted_reservation_state(): void
    {
        [$session, $questionId] = $this->prepareBooking();
        $component = $this->fillDetails($this->selectTime($session))->set('intakeAnswers.'.$questionId, 'Keep my learning goals');
        $this->travel(17)->minutes();

        $component->call('submitDetails');

        $component->assertSet('currentStep', 2)->assertSet('holdId', null)->assertSet('holdToken', null)
            ->assertSet('selectedSlotStartUtc', null)->assertSet('selectedSlotEndUtc', null)->assertSet('selectedSlotId', null)
            ->assertSet('first_name', 'Synthetic')->assertSet('email', 'synthetic.learner@example.com')
            ->assertSet('intakeAnswers.'.$questionId, 'Keep my learning goals')->assertSee('Please choose a time again.');
        $this->assertSame(2, session('booking_flow_state.current_step'));
        $this->assertNull(session('booking_flow_state.hold_id'));
        $this->assertNull(session('active_booking_hold_id'));
        $this->assertSame(1, BookingHold::count());
        $this->assertSame(0, Booking::count());
        Livewire::test(BookingWizard::class)->assertSet('currentStep', 2)->assertSet('holdId', null);
    }

    public function test_expired_hold_returns_to_scheduling_and_preserves_entered_details(): void
    {
        [$session] = $this->prepareBooking();
        $component = $this->fillDetails($this->selectTime($session))->call('submitDetails');
        $this->travel(18)->minutes();

        $component->call('confirmBooking');

        $component->assertSet('currentStep', 2)->assertSet('first_name', 'Synthetic')
            ->assertSet('email', 'synthetic.learner@example.com')->assertSee('Your reservation hold has expired. Please choose a slot again.')
            ->assertSee('role="alert"', false);
        $this->assertSame(0, Booking::count());
    }

    #[TestWith(['missing'])]
    #[TestWith(['released'])]
    #[TestWith(['token'])]
    #[TestWith(['visitor'])]
    #[TestWith(['browser-session'])]
    #[TestWith(['session-type'])]
    #[TestWith(['start'])]
    #[TestWith(['end'])]
    #[TestWith(['ineligible'])]
    #[TestWith(['unavailable'])]
    public function test_invalid_reservation_cannot_open_review_or_restore_it(string $change): void
    {
        [$session, $questionId] = $this->prepareBooking();
        $component = $this->fillDetails($this->selectTime($session))->set('intakeAnswers.'.$questionId, 'Preserved goals');
        $hold = BookingHold::sole();
        match ($change) {
            'missing' => $hold->delete(),
            'released' => $hold->release(),
            'token' => $hold->update(['hold_token' => 'another-authentication-token']),
            'visitor' => $hold->update(['visitor_token' => 'another-visitor']),
            'browser-session' => $hold->update(['session_token' => 'another-browser-session']),
            'session-type' => $hold->update(['session_type_id' => SessionType::factory()->create()->id]),
            'start' => $hold->update(['slot_start_utc' => $hold->slot_start_utc->addHour()]),
            'end' => $hold->update(['slot_end_utc' => $hold->slot_end_utc->addHour()]),
            'ineligible' => $session->update(['public_bookable' => false]),
            'unavailable' => AvailabilityRule::query()->update(['enabled' => false]),
        };
        $before = $hold->fresh()?->getRawOriginal();

        $component->call('submitDetails');

        $component->assertSet('currentStep', 2)->assertSet('holdId', null)->assertSet('selectedSlot', null)
            ->assertSet('selectedSlotStartUtc', null)->assertSet('selectedSlotEndUtc', null)->assertSet('selectedDate', null)
            ->assertSet('email', 'synthetic.learner@example.com')->assertSet('intakeAnswers.'.$questionId, 'Preserved goals')
            ->assertSee('Please choose a time again.');
        $this->assertSame($before, $hold->fresh()?->getRawOriginal());
        $this->assertSame(0, Booking::count());
        $this->assertNull(session('booking_flow_state.hold_id'));
        Livewire::test(BookingWizard::class)->assertSet('currentStep', 2)->assertSet('holdId', null);
    }

    public function test_repeated_valid_details_preserve_the_owned_hold_and_its_expiry(): void
    {
        [$session] = $this->prepareBooking();
        $component = $this->fillDetails($this->selectTime($session));
        $expiry = BookingHold::sole()->expires_at->toDateTimeString();
        $this->travel(16)->minutes();

        $component->call('submitDetails')->assertSet('currentStep', 4)
            ->call('submitDetails')->assertSet('currentStep', 4);

        $hold = BookingHold::sole();
        $this->assertSame($expiry, $hold->expires_at->toDateTimeString());
        $this->assertSame('active', $hold->status);
        $this->assertSame('synthetic.learner@example.com', $hold->lead_details['email']);
        $this->assertSame(0, Booking::count());
        Livewire::test(BookingWizard::class)->assertSet('currentStep', 4)->assertSet('holdId', $hold->id);
    }

    public function test_reload_rejects_an_expired_serialized_review(): void
    {
        [$session] = $this->prepareBooking();
        $this->fillDetails($this->selectTime($session))->call('submitDetails')->assertSet('currentStep', 4);
        $this->travel(17)->minutes();

        Livewire::test(BookingWizard::class)->assertSet('currentStep', 2)->assertSet('holdId', null)
            ->assertSet('selectedSlot', null)->assertSet('email', 'synthetic.learner@example.com');

        $this->assertSame(2, session('booking_flow_state.current_step'));
        $this->assertNull(session('active_booking_hold_id'));
    }

    #[TestWith(['not-a-date'])]
    #[TestWith(['2026-10-99'])]
    #[TestWith(['2026-10-20'])]
    #[TestWith(['2026-11-03'])]
    #[TestWith(['2026-10-26'])]
    public function test_date_actions_reject_invalid_past_outside_month_and_unavailable_dates(string $date): void
    {
        $this->prepareBooking();

        Livewire::test(BookingWizard::class)->call('selectDate', $date)
            ->assertSet('selectedDate', null)->assertSet('calendarMonth', '2026-10')->assertSet('holdId', null)
            ->assertSee('That date has no available times.');

        $this->assertSame(0, BookingHold::count());
    }

    public function test_availability_refresh_clears_an_unheld_date_and_retains_other_dates(): void
    {
        $this->prepareBooking();
        AvailabilityRule::create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '16:00', 'enabled' => true]);
        $component = Livewire::test(BookingWizard::class)->call('selectDate', '2026-10-27')->assertSet('selectedDate', '2026-10-27');
        AvailabilityRule::query()->where('weekday', 2)->update(['enabled' => false]);

        $component->call('$refresh')->assertSet('selectedDate', null)
            ->assertSee('No times remain on your selected date.')->assertDontSee('No available times this month.');

        $this->assertNull(session('booking_flow_state.selected_date'));
        $this->assertSame(0, BookingHold::count());
    }

    public function test_month_actions_enforce_the_horizon_and_do_not_overflow_at_month_end(): void
    {
        $this->prepareBooking();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00:00', 'UTC'));
        $component = Livewire::test(BookingWizard::class)->call('nextMonth')->assertSet('calendarMonth', '2026-11')
            ->call('nextMonth')->assertSet('calendarMonth', '2026-12');

        $component->call('nextMonth')->assertSet('calendarMonth', '2026-12')
            ->call('previousMonth')->assertSet('calendarMonth', '2026-11')
            ->call('previousMonth')->assertSet('calendarMonth', '2026-10')
            ->call('previousMonth')->assertSet('calendarMonth', '2026-10');

        $this->assertSame('2026-10', session('booking_flow_state.calendar_month'));
        $this->assertSame(0, BookingHold::count());
    }

    public function test_changing_session_clears_the_owned_reservation_without_reserving_a_replacement(): void
    {
        [$session] = $this->prepareBooking();
        $component = $this->selectTime($session);
        $another = SessionType::factory()->create(['public_bookable' => true]);

        $component->call('goToStep', 1)->call('selectSession', $another->id)
            ->assertSet('currentStep', 2)->assertSet('selectedSessionTypeId', $another->id)
            ->assertSet('holdId', null)->assertSet('selectedDate', null)->assertSet('selectedSlotStartUtc', null);

        $this->assertSame('released', BookingHold::sole()->status);
        $this->assertNull(session('active_booking_hold_id'));
        $this->assertSame($another->id, session('booking_flow_state.selected_session_type_id'));
        $this->assertSame(0, Booking::count());
    }

    public function test_slot_taken_after_display_reports_conflict_without_creating_an_owned_hold(): void
    {
        [$session] = $this->prepareBooking();
        $component = Livewire::test(BookingWizard::class)->call('selectTimezone', 'Europe/Paris')->call('selectDate', '2026-10-27');
        $slotId = $this->slotIdFor($session, '2026-10-27 12:00:00', 'Europe/Paris', $component->get('visitorToken'));
        app(BookingHoldService::class)->acquireHold('synthetic-other-visitor', 'synthetic-other-session', $session,
            CarbonImmutable::parse('2026-10-27 12:00:00', 'UTC'), CarbonImmutable::parse('2026-10-27 13:00:00', 'UTC'));

        $component->call('selectSlot', $slotId);

        $component->assertSet('currentStep', 2)->assertSet('holdId', null)->assertSee('role="alert"', false);
        $this->assertNotEmpty($component->get('errorMessage'));
        $this->assertSame(1, BookingHold::count());
        $this->assertSame(0, Booking::count());
    }

    #[TestWith(['confirmed', 'Confirmed', true])]
    #[TestWith(['pending', 'Pending Confirmation', false])]
    #[TestWith(['cancelled', 'Booking Canceled', false])]
    #[TestWith(['completed', 'Session Delivered', false])]
    #[TestWith(['no_show', 'Session Forfeited', false])]
    public function test_confirmation_preserves_status_and_calendar_eligibility(string $status, string $label, bool $calendarAvailable): void
    {
        $booking = Booking::factory()->create(['status' => $status]);

        $response = $this->get(route('booking.confirmation', ['token' => $booking->confirmation_token]));

        $response->assertSeeText($label)->assertSee('booking-confirmation', false);
        $response->assertDontSeeText('Reschedule Online')->assertDontSeeText('Online rescheduling is available');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame($calendarAvailable ? 1 : 0, $xpath->query('//a[contains(@href,"/ics")]')->length);
    }

    public function test_home_and_pricing_keep_the_supported_booking_entry(): void
    {
        foreach (['/', '/pricing'] as $path) {
            $response = $this->get($path);
            $xpath = $this->xpath($response->getContent());
            $actions = $xpath->query('//a[@data-cta="booking"]');
            $this->assertGreaterThan(0, $actions->length);
            foreach ($actions as $action) {
                $this->assertStringEndsWith('/booking', $action->getAttribute('href'));
            }
        }
    }

    public function test_rescheduling_action_keeps_the_existing_direct_contact_redirect(): void
    {
        $booking = Booking::factory()->create();

        $this->get(route('booking.reschedule', $booking->confirmation_token))
            ->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]))
            ->assertSessionHas('info', 'To reschedule, please contact Abdallah directly by WhatsApp, Telegram, or email at least 24 hours before your lesson.');
    }

    /** @return array{SessionType, int} */
    private function prepareBooking(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00', 'UTC'));
        $form = $this->installShortFormFixture();
        $session = SessionType::factory()->create(['slug' => 'diagnostic-session', 'title' => 'Diagnostic & Learning Roadmap', 'price' => '25.00', 'public_bookable' => true]);
        Setting::set('booking_hold_duration_minutes', 17);
        AvailabilityRule::create(['weekday' => 2, 'start_time' => '00:00', 'end_time' => '23:59', 'buffer_minutes' => 0, 'enabled' => true]);

        return [$session, $form->publishedVersion->questions->first()->id];
    }

    private function selectTime(SessionType $session): Testable
    {
        $component = Livewire::test(BookingWizard::class)->call('selectTimezone', 'Europe/Paris')->call('selectDate', '2026-10-27');
        $component->call('selectSlot', $this->slotIdFor($session, '2026-10-27 12:00:00', 'Europe/Paris', $component->get('visitorToken')))
            ->assertSet('currentStep', 3)->assertSee('Reservation expires at 11:17 AM in Europe/Paris.');

        return $component;
    }

    private function fillDetails(Testable $component): Testable
    {
        return $component->set('first_name', 'Synthetic')->set('last_name', 'Learner')->set('date_of_birth', '1990-01-15')
            ->set('email', 'synthetic.learner@example.com')->set('phone', '+1 202 555 0123');
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }
}
