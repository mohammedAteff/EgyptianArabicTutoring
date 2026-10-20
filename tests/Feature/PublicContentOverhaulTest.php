<?php

namespace Tests\Feature;

use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\PublicContentImporter;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Livewire\BookingWizard;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\HasPublishedShortForm;
use Tests\TestCase;

class PublicContentOverhaulTest extends TestCase
{
    use HasPublishedShortForm;
    use RefreshDatabase;

    public function test_import_preserves_unrelated_terms_privacy_and_historical_faqs_and_is_idempotent(): void
    {
        $old = Faq::create(['question' => 'Previous FAQ', 'answer' => 'Original answer', 'active' => true]);
        $privacy = Page::create(['title' => 'Privacy', 'slug' => 'privacy', 'content' => 'Private data policy', 'status' => 'published']);
        $terms = Page::create(['title' => 'Terms', 'slug' => 'terms', 'content' => "Introduction stays.\n\n1. Delivery & Platform Logistics\nMeeting tools stay.\nPackage Validity:\nOld purchase rule.\n\n2. Invoicing, Payments & Credits\nOld payment rule.\n\n3. Rescheduling, Cancellations & No-Show Policy\nOld timing.\n\n4. Refund Policy\nOld PayPal fee deduction.\n\n5. Materials & Usage\nPersonal educational use only.", 'status' => 'published']);
        $service = app(PublicContentImporter::class);
        $first = $service->import();
        $this->assertTrue($first['changed']);
        $this->assertSame(34, Faq::active()->count());
        $this->assertSame(6, Faq::active()->where('featured', true)->count());
        $this->assertFalse($old->fresh()->active);
        $this->assertSame('Original answer', $old->fresh()->answer);
        $this->assertSame('Private data policy', $privacy->fresh()->content);
        $this->assertStringContainsString('Introduction stays.', $terms->fresh()->content);
        $this->assertStringContainsString('Meeting tools stay.', $terms->fresh()->content);
        $this->assertStringContainsString('Personal educational use only.', $terms->fresh()->content);
        $this->assertStringNotContainsString('Old PayPal fee deduction', $terms->fresh()->content);
        $this->assertStringContainsString('full refund', strtolower($terms->fresh()->content));
        $this->assertGreaterThan(0, $old->revisions()->count());
        $this->assertFalse($service->import()['changed']);
        $this->assertDatabaseCount('faqs', 35);
        $this->assertDatabaseMissing('faq_translations', ['locale' => 'fr']);
        $this->assertDatabaseMissing('faq_translations', ['locale' => 'de']);
    }

    public function test_hidden_and_future_resources_are_absent_from_discovery_without_changing_publication(): void
    {
        $visible = ResourceFactory::new()->create(['title' => 'Available reading guide']);
        $hidden = ResourceFactory::new()->create(['title' => 'Assigned QA material', 'public_catalogue' => false]);
        $future = ResourceFactory::new()->create(['title' => 'Future reading guide', 'published_at' => now()->addDay()]);
        foreach (['/', '/resources'] as $path) {
            $this->get($path)->assertOk()->assertSeeText($visible->title)->assertDontSeeText($hidden->title)->assertDontSeeText($future->title);
        }
        $this->assertTrue($hidden->fresh()->isPublished());
        $this->get('/resources/'.$hidden->slug)->assertOk();
    }

    public function test_hiding_the_reviewed_resource_preserves_authorized_student_access(): void
    {
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create(['slug' => 'synthetic-followup-access-guide']);
        $assignment = ResourceAssignment::factory()->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        app(PublicContentImporter::class)->import();
        $this->assertFalse($resource->fresh()->public_catalogue);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()])
            ->get(route('student.resources.open', $assignment))->assertRedirect('https://example.test/practice');
        $this->assertDatabaseHas('resource_assignments', ['id' => $assignment->id, 'student_visible' => true]);
    }

    public function test_public_wizard_rejects_hidden_active_lesson_types(): void
    {
        $this->installShortFormFixture();
        $public = SessionType::factory()->create(['title' => 'Public diagnostic']);
        $hidden = SessionType::factory()->create(['title' => 'QA lesson', 'public_bookable' => false]);
        Livewire::test(BookingWizard::class)
            ->assertSee('Public diagnostic')->assertDontSee('QA lesson')
            ->call('selectSession', $hidden->id)
            ->assertSet('selectedSessionTypeId', $public->id);
        $this->assertTrue($hidden->fresh()->active);
    }

    public function test_public_booking_service_rejects_a_type_hidden_after_a_hold_was_created(): void
    {
        $type = SessionType::factory()->create(['public_bookable' => false]);
        $hold = BookingHold::create(['session_type_id' => $type->id, 'visitor_token' => 'visitor', 'session_token' => 'session', 'hold_token' => 'hold', 'slot_start_utc' => now()->addDays(3), 'slot_end_utc' => now()->addDays(3)->addHour(), 'expires_at' => now()->addMinutes(10), 'status' => 'active']);
        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('not available for public booking');
        app(BookingService::class)->createPublicBooking(['idempotency_key' => 'hidden-type-test', 'session_type_id' => $type->id, 'hold_id' => $hold->id, 'hold_token' => 'hold', 'visitor_token' => 'visitor', 'session_token' => 'session', 'customer_timezone' => 'UTC']);
    }

    public function test_faq_search_and_topic_filters_combine_without_showing_hidden_answers(): void
    {
        Faq::create(['question' => 'How do refunds work?', 'answer' => 'A full refund is available.', 'topic' => 'Cancellations & Refunds', 'active' => true]);
        Faq::create(['question' => 'Can I get a refund?', 'answer' => 'Other topic.', 'topic' => 'Pricing & Packages', 'active' => true]);
        Faq::create(['question' => 'Hidden refund rule', 'answer' => 'Secret draft.', 'topic' => 'Cancellations & Refunds', 'active' => false]);
        $this->get('/faq?'.http_build_query(['q' => 'refund', 'topic' => 'Cancellations & Refunds']))
            ->assertOk()->assertSeeText('How do refunds work?')->assertDontSeeText('Can I get a refund?')->assertDontSeeText('Secret draft.');
        $this->get('/faq?q=unmatched')->assertOk()->assertSeeText('No questions match your search.');
    }

    public function test_english_copy_has_honest_fallback_and_no_unavailable_workbook_link(): void
    {
        $this->get('/fr')->assertOk()->assertSeeText('Learn Arabic with a plan built around you.')->assertSeeText('La version anglaise est affichée.');
        $this->get('/pricing')->assertOk()->assertSeeText('$280')->assertSeeText('$390')->assertSeeText('first payment, including a partial payment');
        $this->get('/resources')->assertOk()->assertSeeText('Learning materials are being prepared.')->assertDontSeeText('awaiting its confirmed file')->assertDontSeeText('Explore the Workbook');
        $this->get('/booking-policy')->assertOk()->assertSeeText('Full refund or rescheduling.')->assertSeeText('Online rescheduling requires at least 24 hours');
    }

    public function test_content_import_refuses_a_production_environment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->artisan('content:import-approved')->assertFailed();
        $this->assertDatabaseCount('faqs', 0);
        $this->assertNull(Setting::get('approved_public_content.version'));
    }

    public function test_public_catalogue_downloads_require_name_and_email_even_with_a_legacy_ungated_flag(): void
    {
        $resource = ResourceFactory::new()->create(['status' => 'published', 'published_at' => now(), 'is_gated' => false, 'public_catalogue' => true]);
        $this->get(route('resources.show', $resource->slug))->assertOk()->assertSeeText('Get Free Access');
        $this->get(route('resources.download', $resource->slug))->assertRedirect(route('resources.show', $resource->slug));
        $this->post(route('resources.request', $resource->slug), ['email' => 'synthetic@example.test'])->assertSessionHasErrors('name');
        $this->post(route('resources.request', $resource->slug), ['name' => 'Synthetic reader'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('resource_downloads', 0);
        $this->assertDatabaseCount('resource_requests', 0);
        $this->assertFalse($resource->fresh()->is_gated);
    }

    public function test_staff_can_group_feature_and_clear_faq_topics_while_drafts_leave_live_questions_unchanged(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $faq = Faq::create(['question' => 'Synthetic question', 'answer' => 'Synthetic answer', 'topic' => 'Getting Started', 'active' => true, 'featured' => false]);
        $this->actingAs($admin);
        $data = ['question' => $faq->question, 'answer' => $faq->answer, 'sort_order' => 0, 'active' => 1, 'featured' => 1, 'topic' => 'Pricing & Packages'];
        $this->put(route('admin.content.faq.update', $faq), $data + ['action' => 'draft'])->assertSessionHasNoErrors();
        $this->assertSame('Getting Started', $faq->fresh()->topic);
        $this->assertFalse($faq->fresh()->featured);
        $this->assertSame('Pricing & Packages', $faq->revisions()->where('status', 'draft')->sole()->content['topic']);
        $this->put(route('admin.content.faq.update', $faq), $data + ['action' => 'publish'])->assertSessionHasNoErrors();
        $this->assertSame('Pricing & Packages', $faq->fresh()->topic);
        $this->assertTrue($faq->fresh()->featured);
        $this->put(route('admin.content.faq.update', $faq), array_merge($data, ['action' => 'publish', 'topic' => null, 'featured' => 0]))->assertSessionHasNoErrors();
        $this->assertNull($faq->fresh()->topic);
        $this->assertFalse($faq->fresh()->featured);
        $this->assertSame(2, $faq->revisions()->where('status', 'published')->count());
    }

    public function test_public_booking_preselects_the_real_diagnostic_without_hiding_existing_public_lessons(): void
    {
        $this->installShortFormFixture();
        $legacy = SessionType::factory()->create(['title' => 'Existing public lesson', 'slug' => 'legacy-lesson']);
        $diagnostic = SessionType::factory()->create(['title' => 'Diagnostic & Learning Roadmap', 'slug' => 'diagnostic-session', 'price' => '25.00']);
        Livewire::test(BookingWizard::class)->assertSet('selectedSessionTypeId', $diagnostic->id)
            ->assertSee('Existing public lesson')->assertSee('Diagnostic &amp; Learning Roadmap', false)
            ->call('selectSession', $legacy->id)->assertSet('selectedSessionTypeId', $legacy->id);
    }

    public function test_import_dry_run_reports_changes_without_publishing_or_changing_stored_copy(): void
    {
        $this->artisan('content:import-approved', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('faqs', 0);
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertNull(Setting::get('approved_public_content.version'));
        $this->assertNull(Setting::get('approved_public_content.home_controls_version'));
    }
}
