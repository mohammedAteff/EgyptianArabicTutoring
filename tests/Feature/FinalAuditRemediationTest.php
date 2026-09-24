<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Models\Page;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinalAuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Audit Admin',
            'email' => 'audit-admin@example.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        RateLimiter::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_generic_page_and_translation_content_is_sanitized_on_every_write_path(): void
    {
        $malicious = '<p>Safe content</p><script>alert(1)</script><p onclick="alert(2)">Click</p><a href="javascript:alert(3)">Bad link</a>';

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.pages.store'), [
                'title' => 'Sanitized Page',
                'slug' => 'sanitized-page',
                'content' => $malicious,
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.pages.index'));

        $page = Page::query()->where('slug', 'sanitized-page')->firstOrFail();
        $this->assertSafeRichText($page->content);

        $this->get(route('page.show', $page->slug))
            ->assertSeeText('Safe content')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onclick="alert(2)"', false)
            ->assertDontSee('javascript:alert(3)', false);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.translations.save-draft', [
                'entityType' => 'page',
                'id' => $page->id,
                'locale' => 'fr',
            ]), [
                'title' => 'Page française sûre',
                'content' => $malicious,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.translations.publish', [
                'entityType' => 'page',
                'id' => $page->id,
                'locale' => 'fr',
            ]), [
                'title' => 'Page française sûre',
                'content' => $malicious,
            ])
            ->assertRedirect();

        $translation = $page->fresh()->liveTranslation('fr');
        $this->assertNotNull($translation);
        $this->assertSafeRichText($translation->content);

        $this->get(route('page.show.fr', $page->slug))
            ->assertSeeText('Page française sûre')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onclick="alert(2)"', false)
            ->assertDontSee('javascript:alert(3)', false);

        $rawRevision = ContentRevision::create([
            'revisable_type' => Page::class,
            'revisable_id' => $page->id,
            'revision_number' => 999,
            'title' => 'Restored safely',
            'content' => ['body' => $malicious],
            'created_by_id' => $this->admin->id,
            'status' => 'published',
        ]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.pages.revisions.restore', [$page, $rawRevision]))
            ->assertRedirect();

        $this->assertSafeRichText($page->fresh()->content);
    }

    public function test_authenticated_student_reschedule_rate_limits_are_isolated_by_student(): void
    {
        $studentA = Student::factory()->verified()->create();
        $studentB = Student::factory()->verified()->create();
        $bookingA = $this->createBookingFor($studentA);
        $bookingB = $this->createBookingFor($studentB);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->authenticateStudent($studentA, '198.51.100.10')
                ->post(route('student.bookings.reschedule.submit', $bookingA->id), [
                    'idempotency_key' => Str::random(48),
                ])
                ->assertSessionHasErrors('slot_id');
        }

        $this->authenticateStudent($studentA, '198.51.100.10')
            ->post(route('student.bookings.reschedule.submit', $bookingA->id), [
                'idempotency_key' => Str::random(48),
            ])
            ->assertTooManyRequests();

        $this->authenticateStudent($studentB, '198.51.100.11')
            ->post(route('student.bookings.reschedule.submit', $bookingB->id), [
                'idempotency_key' => Str::random(48),
            ])
            ->assertSessionHasErrors('slot_id');
    }

    public function test_admin_analytics_and_reports_use_exact_cairo_calendar_day_presets(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Africa/Cairo'));

        foreach ([
            '7d' => '2026-09-18',
            '30d' => '2026-08-26',
            '90d' => '2026-06-27',
        ] as $range => $expectedStart) {
            $dashboard = $this->actingAs($this->admin, 'web')
                ->get(route('admin.analytics', ['range' => $range]));
            $dashboard->assertViewHas('startDate', fn (CarbonImmutable $start): bool => $start->toDateString() === $expectedStart);
            $dashboard->assertViewHas('endDate', fn (CarbonImmutable $end): bool => $end->toDateString() === '2026-09-24');

            $report = $this->actingAs($this->admin, 'web')
                ->get(route('admin.reports.index', ['type' => 'traffic', 'range' => $range]));
            $report->assertViewHas('start', fn (CarbonImmutable $start): bool => $start->toDateString() === $expectedStart);
            $report->assertViewHas('end', fn (CarbonImmutable $end): bool => $end->toDateString() === '2026-09-24');
        }
    }

    private function assertSafeRichText(?string $content): void
    {
        $this->assertIsString($content);
        $this->assertStringContainsString('Safe content', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onclick=', $content);
        $this->assertStringNotContainsString('javascript:', $content);
    }

    private function authenticateStudent(Student $student, string $ip): self
    {
        return $this->actingAs($student, 'student')
            ->withSession([
                'student_id' => $student->id,
                'student_authenticated_at' => now('UTC')->toIso8601String(),
                'student_auth_expires_at' => now('UTC')->addMinutes(180)->toIso8601String(),
            ])
            ->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function createBookingFor(Student $student): Booking
    {
        $sessionType = SessionType::create([
            'title' => 'Audit lesson',
            'slug' => 'audit-lesson-'.Str::random(8),
            'duration_minutes' => 50,
            'price' => 30,
            'currency' => 'USD',
            'active' => true,
        ]);
        $contact = Contact::create([
            'name' => 'Audit Student',
            'email' => 'audit-'.Str::random(10).'@example.com',
        ]);
        $start = CarbonImmutable::now('UTC')->addDays(5);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $start,
            $start->addMinutes(50),
            'Africa/Cairo',
            'Africa/Cairo'
        );

        return Booking::create($snapshot + [
            'student_id' => $student->id,
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'status' => 'confirmed',
            'confirmation_token' => bin2hex(random_bytes(32)),
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }
}
