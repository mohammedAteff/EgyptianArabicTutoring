<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormValidationService;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SiteLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->withHeader('X-Analytics-Synthetic', '1');
    }

    #[TestWith(['fr'])]
    #[TestWith(['de'])]
    public function test_public_pages_render_their_bundled_copy_in_the_selected_language(string $locale): void
    {
        foreach (['home', 'about', 'pricing', 'policy', 'faq'] as $page) {
            Livewire::flushState();
            $path = app(LocalizedUrlService::class)->getLocalizedUrl($page, $locale);
            $response = $this->get($path)->assertOk();
            $this->assertStringContainsString('lang="'.$locale.'"', substr($response->getContent(), 0, 150), $page);
            $response->assertSee(__('Book a Lesson', locale: $locale));
        }
    }

    #[TestWith(['fr', 'Quelques mots sur votre parcours en arabe'])]
    #[TestWith(['de', 'Ein paar Worte zu Ihrem bisherigen Weg mit Arabisch'])]
    public function test_portal_preference_persists_and_form_translation_preserves_option_values(string $locale, string $heading): void
    {
        $author = Administrator::factory()->create();
        $this->artisan('forms:install-onboarding', ['--administrator' => $author->id])->assertSuccessful();
        $student = Student::factory()->verified()->create();
        $form = Form::where('slug', 'short-form')->firstOrFail();
        FormSubmission::create(['student_id' => $student->id, 'form_version_id' => $form->published_version_id, 'status' => 'draft']);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $this->get('/student?lang='.$locale)->assertOk();
        Livewire::flushState();
        $this->get(route('student.forms.show', 'short-form'))->assertOk()->assertSee($heading)->assertSee('value="option_01"', false)->assertSee('lang="'.$locale.'"', false);
        $answers = $form->publishedVersion->questions->mapWithKeys(fn ($question): array => [$question->question_key => 'option_01'])->all();
        $this->assertSame($answers, app(FormValidationService::class)->validateAnswers($form->publishedVersion->questions->all(), $answers));
        $this->assertSame('Which best describes your experience with Arabic so far?', $form->publishedVersion->questions->first()->label);
        $firstQuestion = $form->publishedVersion->questions->first();
        $this->postJson(route('student.forms.autosave', $form->slug), ['form_version_id' => $form->published_version_id, 'answers' => [$firstQuestion->question_key => 'unavailable']])
            ->assertUnprocessable()->assertJsonValidationErrors('answers.'.$firstQuestion->question_key)
            ->assertJsonPath('message', __('Choose an available option.', locale: $locale));
    }

    #[TestWith(['fr'])]
    #[TestWith(['de'])]
    public function test_student_destinations_share_the_selected_language(string $locale): void
    {
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String(), 'public_locale' => $locale]);
        foreach (['student.home', 'student.dashboard', 'student.learning.index', 'student.teaching.index', 'student.scheduling.index', 'student.bins.index', 'student.bookmarks.index', 'student.statements.index', 'student.profile', 'student.security.show', 'student.video.devices', 'student.notifications.index'] as $destination) {
            Livewire::flushState();
            $response = $this->get(route($destination))->assertOk();
            $this->assertStringContainsString('lang="'.$locale.'"', substr($response->getContent(), 0, 150), $destination);
            $response->assertSee(__('Sign Out', locale: $locale));
        }
    }

    public function test_french_calendar_download_keeps_utc_times_and_uses_localized_labels(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed', 'start_at_utc' => now('UTC')->addDays(2), 'end_at_utc' => now('UTC')->addDays(2)->addHour()]);
        $response = $this->withSession(['public_locale' => 'de'])->get(route('booking.ics', ['token' => $booking->confirmation_token, 'lang' => 'fr']))
            ->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('DTSTART:'.$booking->start_at_utc->format('Ymd\THis\Z'), false);
        $this->assertStringContainsString('Référence de réservation', str_replace("\r\n ", '', $response->getContent()));
    }

    public function test_faq_search_uses_the_same_translated_content_as_the_answer(): void
    {
        $faq = Faq::create(['question' => 'When does my package validity begin?', 'answer' => 'Your package validity begins at the exact date and time your full package payment is received.', 'active' => true]);
        $this->get('/fr/faq?q=forfait')->assertOk()->assertSee('Quand commence la validité de mon forfait ?')->assertSee(__($faq->answer, locale: 'fr'));
        $this->assertSame('fr', $faq->localizedContent('fr')['locale']);
        $faq->update(['answer' => 'New unpublished wording without a translation']);
        $this->assertSame('en', $faq->localizedContent('fr')['locale']);
    }

    public function test_dates_follow_the_interface_language_without_changing_the_instant(): void
    {
        $date = CarbonImmutable::parse('2026-10-29 16:30:00', 'Africa/Cairo');
        app()->setLocale('fr');
        $this->assertSame('29 octobre 2026', localized_date($date, 'F j, Y'));
        $this->assertSame('29 oct. 2026', localized_date($date, 'M d, Y'));
        $this->assertSame('16:30', localized_date($date, 'g:i A'));
        $this->assertSame('25,00', localized_number(25, 2));
        app()->setLocale('de');
        $this->assertSame('29. Oktober 2026', localized_date($date, 'F j, Y'));
        $this->assertSame('2026-10-29T16:30:00+03:00', $date->toIso8601String());
    }

    public function test_staff_sign_in_stays_in_english_with_a_saved_public_preference(): void
    {
        $this->withSession(['public_locale' => 'de'])->get('/admin/login')->assertOk()->assertSee('lang="en"', false);
        $this->assertSame('en', app()->getLocale());
    }
}
