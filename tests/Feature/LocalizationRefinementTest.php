<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningMilestone;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\LearningSkill;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentErrorLog;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentSkillAssessment;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\StudentTwoFactorService;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use Database\Factories\BlogFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocalizationRefinementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('X-Analytics-Synthetic', '1');
    }

    #[TestWith(['fr', 'Non commencé', 'Grammaire', 'En cours', 'Compréhension écrite', 'À l’aise'])]
    #[TestWith(['de', 'Nicht begonnen', 'Grammatik', 'In Bearbeitung', 'Lesen', 'Sicher'])]
    public function test_populated_practice_screens_translate_system_states_and_preserve_student_text(string $locale, string $notStarted, string $grammar, string $practising, string $reading, string $comfortable): void
    {
        $student = Student::factory()->verified()->create();
        Homework::factory()->create(['student_id' => $student->id, 'title' => 'تدريب القراءة', 'instructions' => 'اقرأ النص', 'status' => 'assigned', 'student_visible' => true]);
        StudentErrorLog::factory()->create(['student_id' => $student->id, 'category' => 'grammar', 'status' => 'practising', 'student_visible' => true, 'mistake' => 'My original answer', 'correction' => 'أَنَا أَدْرُسُ']);
        StudentSkillAssessment::factory()->create(['student_id' => $student->id, 'learning_skill_id' => LearningSkill::factory()->create(['label' => 'Reading'])->id, 'state' => 'comfortable', 'student_visible' => true]);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);

        $this->get(route('student.teaching.index'))->assertOk()->assertSeeText($notStarted)->assertSeeText($grammar)->assertSeeText($practising)->assertSeeText($reading)->assertSeeText($comfortable)->assertSeeText('My original answer')->assertSeeText('أَنَا أَدْرُسُ')->assertDontSeeText('Not Started')->assertDontSeeText('Working On It');
    }

    #[TestWith(['fr', 'Le Caire', 'Égypte'])]
    #[TestWith(['de', 'Kairo', 'Ägypten'])]
    public function test_timezone_labels_use_the_interface_language_while_ids_and_offsets_stay_stable(string $locale, string $city, string $country): void
    {
        app()->setLocale($locale);
        $display = app(TimezoneDisplayService::class)->formatSlotForDisplay('Africa/Cairo', now('UTC'));
        $this->assertSame($city, $display['city']);
        $this->assertSame($country, $display['timezone_country_name']);
        $this->assertStringContainsString('Africa/Cairo', $display['label']);
        $this->assertStringStartsWith('UTC+', $display['utc_offset']);
    }

    public function test_cached_learning_durations_are_localized_after_the_metrics_cache_is_warmed(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Setting::set('counters.learning_hours.public_enabled', true);
        Cache::put(EngagementCounterService::cacheKey(), [
            'live_users' => ['enabled' => false, 'count' => 0, 'text' => '0 active visitors online right now'],
            'monthly_traffic' => ['enabled' => false, 'source' => 'sessions', 'count' => 1000, 'text' => '1000 website sessions'],
            'learning_hours' => ['enabled' => true, 'window_days' => 7, 'total_hours' => 25.02, 'total_minutes' => 1501, 'formatted_time' => '1 day, 1 hour, 1 minute', 'headline' => 'Globally, students have put in...', 'subtitle' => 'of practice time in the last 7 days'],
        ], 900);

        $this->get('/fr')->assertOk()->assertSeeText('1 jour, 1 heure, 1 minute')->assertDontSeeText('1 day, 1 hour');
        $this->get('/de')->assertOk()->assertSeeText('1 Tag, 1 Stunde, 1 Minute')->assertDontSeeText('1 jour');
        $this->assertSame(1501, Cache::get(EngagementCounterService::cacheKey())['learning_hours']['total_minutes']);
    }

    #[TestWith(['fr', 'Veuillez saisir une adresse e-mail valide.'])]
    #[TestWith(['de', 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'])]
    public function test_public_form_errors_keep_the_selected_language_on_unprefixed_posts(string $locale, string $message): void
    {
        $resource = ResourceFactory::new()->create(['status' => 'published', 'published_at' => now('UTC')->subDay()]);
        $this->withSession(['public_locale' => $locale])->postJson(route('resources.request', $resource->slug), ['email' => 'invalid', 'name' => 'Test'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', $message);
    }

    #[TestWith(['fr', 'Page introuvable'])]
    #[TestWith(['de', 'Seite nicht gefunden'])]
    public function test_missing_pages_use_the_language_in_the_url(string $locale, string $message): void
    {
        config(['app.debug' => false]);
        $this->get('/'.$locale.'/missing-localization-page')->assertNotFound()->assertSeeText($message)->assertSee('lang="'.$locale.'"', false);
    }

    #[TestWith(['fr', 'Quelques améliorations en coulisses.'])]
    #[TestWith(['de', 'Wir arbeiten im Hintergrund.'])]
    public function test_maintenance_screen_is_translated(string $locale, string $message): void
    {
        Setting::set('maintenance_mode', true);
        $this->get('/'.$locale)->assertStatus(503)->assertSeeText($message)->assertSee('lang="'.$locale.'"', false)->assertDontSeeText('Scheduled Maintenance');
    }

    #[TestWith(['fr', 'Cours n° 501 · Réservation confirmée', 'Valable jusqu’au', 'jeu. 29 oct., 16:30–17:30', '1 disponible'])]
    #[TestWith(['de', 'Unterricht Nr. 501 · Buchung bestätigt', 'Gültig bis:', 'Do. 29. Okt., 16:30–17:30', '1 verfügbar'])]
    public function test_stored_notifications_are_translated_on_read_without_changing_the_original_records(string $locale, string $lessonMessage, string $expiryLabel, string $waitlistDate, string $availability): void
    {
        $student = Student::factory()->verified()->create();
        $source = 'Session #501 · Confirmed. Check your session details for the current date and time.';
        $notification = StudentNotification::factory()->create(['student_id' => $student->id, 'type' => 'lesson', 'title' => 'Lesson Update', 'message' => $source]);
        StudentNotification::factory()->create(['student_id' => $student->id, 'type' => 'entitlement', 'message' => 'One-hour lessons · My original package · Purchase #999999 · 1 available · Valid until: 31 Oct 2026, 4:30:00 PM UTC']);
        StudentNotification::factory()->create(['student_id' => $student->id, 'type' => 'waitlist', 'message' => 'Thu 29 Oct, 4:30 PM–5:30 PM · Europe/Paris. Review current availability and confirm to book. This is not a reservation.']);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);

        $this->get(route('student.notifications.index'))->assertOk()->assertSeeText($lessonMessage)->assertSeeText($expiryLabel)->assertSeeText($availability)->assertSeeText('My original package')->assertSeeText('16:30:00 UTC')->assertSeeText($waitlistDate)->assertSeeText('Europe/Paris')->assertDontSeeText('Purchase #999999');
        $this->assertSame($source, $notification->fresh()->message);
    }

    #[TestWith(['fr', 'Navigation entre les pages', 'Suivant', 'Annulé', 'Choisissez une période d’un an maximum.'])]
    #[TestWith(['de', 'Seitennavigation', 'Weiter', 'Storniert', 'Bitte wählen Sie einen Zeitraum von höchstens einem Jahr.'])]
    public function test_populated_planning_pagination_and_submission_errors_keep_the_selected_language(string $locale, string $navigation, string $next, string $cancelled, string $error): void
    {
        $student = Student::factory()->verified()->create();
        StudentUnavailability::factory()->count(16)->create(['student_id' => $student->id, 'status' => 'cancelled']);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);

        $this->get(route('student.scheduling.index'))->assertOk()->assertSee($navigation)->assertSeeText($next)->assertSeeText($cancelled);
        $this->postJson(route('student.scheduling.store', 'holiday'), ['date_from' => '2027-01-01', 'date_to' => '2029-01-01', 'timezone' => 'UTC', 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonPath('errors.date_to.0', $error);
        $this->assertSame(16, StudentUnavailability::query()->where('student_id', $student->id)->count());
    }

    #[TestWith(['fr', 'Veuillez utiliser une adresse e-mail permanente.'])]
    #[TestWith(['de', 'Bitte verwenden Sie eine dauerhaft gültige E-Mail-Adresse.'])]
    public function test_resource_email_quality_errors_are_localized_before_reaching_the_browser(string $locale, string $message): void
    {
        $resource = ResourceFactory::new()->create(['status' => 'published', 'published_at' => now('UTC')->subDay()]);
        $this->withSession(['public_locale' => $locale])->postJson(route('resources.request', $resource->slug), ['email' => 'test@mailinator.com', 'name' => 'Test'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', $message)->assertJsonPath('message', $message);
    }

    #[TestWith(['fr', 'Cette note a changé. Actualisez la page avant de la modifier.'])]
    #[TestWith(['de', 'Diese Notiz wurde geändert. Bitte laden Sie die Seite vor dem Bearbeiten neu.'])]
    public function test_conflict_pages_preserve_the_status_and_explain_the_problem_in_the_selected_language(string $locale, string $message): void
    {
        config(['app.debug' => false]);
        Route::get('/'.$locale.'/localization-conflict', fn () => abort(409, 'This note changed. Reload before editing.'));
        $this->get('/'.$locale.'/localization-conflict')->assertStatus(409)->assertSeeText($message)->assertSee('lang="'.$locale.'"', false);
    }

    #[TestWith(['fr', 'Ressource traduite', 'Description traduite', 'Catégorie traduite'])]
    #[TestWith(['de', 'Übersetztes Material', 'Übersetzte Beschreibung', 'Übersetzte Kategorie'])]
    public function test_resource_library_only_claims_a_fallback_when_visible_content_needs_one(string $locale, string $title, string $description, string $category): void
    {
        $path = $locale === 'fr' ? '/fr/ressources' : '/de/ressourcen';
        $this->get($path)->assertOk()->assertViewHas('isFallback', false)->assertSee('lang="'.$locale.'"', false);
        $resource = ResourceFactory::new()->create(['title' => 'Original material', 'short_description' => 'Original description', 'public_catalogue' => true]);
        $this->get($path)->assertViewHas('isFallback', true);
        $resource->translations()->create(['locale' => $locale, 'status' => 'published', 'title' => $title, 'short_description' => $description]);
        $resource->category->translations()->create(['locale' => $locale, 'status' => 'published', 'name' => $category]);
        $this->get($path)->assertOk()->assertViewHas('isFallback', false)->assertSeeText($title)->assertSeeText($category)->assertSee('lang="'.$locale.'"', false);
        $resource->translations()->where('locale', $locale)->update(['short_description' => '']);
        $this->get($path)->assertViewHas('isFallback', true)->assertSeeText('Original description');
    }

    #[TestWith(['fr', 'Désactivée', 'Activée', 'Non fourni'])]
    #[TestWith(['de', 'Deaktiviert', 'Aktiviert', 'Nicht angegeben'])]
    public function test_account_states_and_missing_profile_details_are_translated(string $locale, string $off, string $enabled, string $missing): void
    {
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);

        $this->get(route('student.security.show'))->assertOk()->assertSeeText($off)->assertDontSeeText('Off');
        $student->forceFill(['two_factor_confirmed_at' => now('UTC')])->save();
        $this->withSession(['student.two_factor_verified' => app(StudentTwoFactorService::class)->fingerprint($student)]);
        $this->get(route('student.security.show'))->assertOk()->assertSeeText($enabled)->assertDontSeeText('Enabled');
        $this->get(route('student.profile'))->assertOk()->assertSeeText($missing)->assertDontSeeText('Not provided');
    }

    #[TestWith(['fr', 'En attente'])]
    #[TestWith(['de', 'Ausstehend'])]
    public function test_pending_milestones_translate_without_rewriting_the_teachers_title(string $locale, string $pending): void
    {
        $student = Student::factory()->verified()->create();
        $plan = LearningPlan::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        LearningMilestone::factory()->create(['learning_plan_id' => $plan->id, 'title' => 'قراءة قصة قصيرة', 'status' => 'pending']);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);

        $this->get(route('student.teaching.index'))->assertOk()->assertSeeText($pending)->assertSeeText('قراءة قصة قصيرة')->assertDontSeeText('Pending');
    }

    #[TestWith(['fr', '1 à vérifier', '2 à vérifier'])]
    #[TestWith(['de', '1 zu prüfen', '2 zu prüfen'])]
    public function test_home_questionnaire_counts_read_naturally_for_one_and_several_forms(string $locale, string $one, string $two): void
    {
        $student = Student::factory()->verified()->create();
        $author = Administrator::factory()->create();
        $builder = app(FormBuilderService::class);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'public_locale' => $locale]);
        foreach (['first' => $one, 'second' => $two] as $slug => $count) {
            $form = $builder->create(['title' => 'Your Learning Goals', 'slug' => $slug, 'trigger' => 'none', 'is_mandatory' => true], [['question_key' => 'goal', 'label' => 'Your goal', 'question_type' => 'short_text']], $author);
            $builder->publish($form->id, $form->active_version_id, $form->lock_version);
            $this->get(route('student.home'))->assertOk()->assertSeeText($count);
        }
    }

    #[TestWith(['fr', '29 octobre 2026', '29 oct. 2026'])]
    #[TestWith(['de', '29. Oktober 2026', '29. Okt 2026'])]
    public function test_public_article_and_card_dates_follow_the_selected_language(string $locale, string $fullDate, string $shortDate): void
    {
        $this->travelTo('2026-10-30 12:00:00 UTC');
        $article = BlogFactory::new()->create(['locale' => $locale, 'status' => 'published', 'published_at' => '2026-10-29 12:00:00', 'editorial_updated_at' => '2026-10-29 13:00:00']);
        $this->withSession(['public_locale' => $locale])->get($article->publicUrl())->assertOk()->assertSeeText($fullDate);
        $response = $this->get(localized_url('blogs'))->assertOk();
        preg_match_all('/<time[^>]*>(.*?)<\/time>/s', $response->getContent(), $dates);
        $this->assertContains($shortDate, $dates[1]);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $descriptions = $xpath->query('//meta[@name="description" or @property="og:description" or @name="twitter:description"]');
        $description = $locale === 'fr' ? 'Des explications pratiques, des conseils d’apprentissage et des articles de réflexion sur l’arabe égyptien, l’arabe standard moderne, la langue et la vie en Égypte.' : 'Praktische Erklärungen, Lerntipps und fundierte Artikel über ägyptisches Arabisch, modernes Hocharabisch, Sprache und das Leben in Ägypten.';
        $this->assertCount(3, $descriptions);
        foreach ($descriptions as $meta) {
            $this->assertSame($description, $meta->getAttribute('content'));
        }
    }

    #[TestWith(['fr', 'Chaque personne a son propre point de départ. Choisissez la séance qui vous convient.'])]
    #[TestWith(['de', 'Jeder Mensch beginnt an einem anderen Punkt. Wählen Sie die Sitzung, die zu Ihnen passt.'])]
    public function test_public_booking_session_selection_guidance_is_translated(string $locale, string $guidance): void
    {
        SessionType::factory()->count(2)->create(['public_bookable' => true]);
        $this->get($locale === 'fr' ? '/fr/reservation' : '/de/buchen')->assertOk()->assertSeeText($guidance)->assertDontSeeText('Every learner starts somewhere different.');
    }

    #[TestWith(['fr', '/fr/tarifs', 'Cours et tarifs'])]
    #[TestWith(['de', '/de/preise', 'Unterricht und Preise'])]
    public function test_pricing_title_and_social_titles_use_the_interface_language(string $locale, string $path, string $title): void
    {
        $response = $this->get($path)->assertOk()->assertDontSee('Lessons &amp; Pricing');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertStringStartsWith($title, $xpath->evaluate('string(//title)'));
        $this->assertStringStartsWith($title, $xpath->evaluate('string(//meta[@property="og:title"]/@content)'));
        $this->assertSame($title, $xpath->evaluate('string(//meta[@name="twitter:title"]/@content)'));
    }
}
