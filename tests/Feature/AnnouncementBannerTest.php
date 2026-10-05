<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\AnnouncementService;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AnnouncementBannerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function fields(array $overrides = []): array
    {
        return array_replace(['enabled' => 1, 'audience' => 'public', 'severity' => 'information', 'dismissible' => 1, 'message_en' => 'Synthetic announcement', 'message_fr' => 'Annonce de test', 'message_de' => '', 'cta_label_en' => '', 'cta_label_fr' => '', 'cta_label_de' => '', 'cta_url' => '', 'start_at' => '', 'end_at' => ''], $overrides);
    }

    #[TestWith(['super_admin'])]
    #[TestWith(['admin'])]
    public function test_configuration_roles_publish_an_escaped_localized_banner_without_restricting_site_access(string $role): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => $role]), 'web')
            ->post(route('admin.settings.announcement'), $this->fields(['message_en' => '<script>unsafe()</script>', 'cta_label_en' => 'Read more', 'cta_url' => 'https://example.test/notice']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('<script>unsafe()</script>', Setting::get('announcement.message_en'));
        $this->get(route('admin.settings.index'))->assertDontSee('data-site-announcement', false);
        auth('web')->logout();

        $this->get('/')->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false)->assertSee('Read more');
        $this->get('/fr')->assertOk()->assertSee('Annonce de test');
        $this->get('/de')->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false);
    }

    public function test_assistants_and_guests_cannot_publish_announcements(): void
    {
        $this->post(route('admin.settings.announcement'), $this->fields())->assertRedirect(route('admin.login'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']), 'web')->post(route('admin.settings.announcement'), $this->fields())->assertForbidden();
        $this->assertNull(Setting::get('announcement.enabled'));
    }

    #[TestWith(['public', false])]
    #[TestWith(['public_student', true])]
    public function test_portal_respects_the_configured_audience(string $audience, bool $visible): void
    {
        app(AnnouncementService::class)->save($this->fields(['audience' => $audience]));
        $student = Student::factory()->verified()->create();
        $response = $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()])->get(route('student.dashboard'))->assertOk();
        $visible ? $response->assertSee('Synthetic announcement') : $response->assertDontSee('Synthetic announcement');
        $this->get('/')->assertSee('Synthetic announcement');
    }

    public function test_schedule_in_business_time_uses_inclusive_start_and_exclusive_expiry_without_a_worker(): void
    {
        Setting::set('business_timezone', 'Europe/Berlin');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->post(route('admin.settings.announcement'), $this->fields(['start_at' => '2026-10-05T14:00', 'end_at' => '2026-10-05T15:00']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-05T12:00:00+00:00', Setting::get('announcement.start_at'));
        auth('web')->logout();
        $this->travelTo(CarbonImmutable::parse('2026-10-05T11:59:59Z'));
        $this->get('/')->assertDontSee('Synthetic announcement');
        $this->travelTo(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $this->get('/')->assertSee('Synthetic announcement');
        $this->travelTo(CarbonImmutable::parse('2026-10-05T13:00:00Z'));
        $this->get('/')->assertDontSee('Synthetic announcement');
    }

    public function test_end_only_and_disabled_fixed_announcements_are_supported(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->post(route('admin.settings.announcement'), $this->fields(['end_at' => '2026-10-06T12:00', 'dismissible' => 0, 'severity' => 'urgent']))->assertSessionHasNoErrors();
        auth('web')->logout();
        $this->get('/')->assertSee('Synthetic announcement')->assertSee('bg-red-50')->assertDontSee('Dismiss announcement');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->post(route('admin.settings.announcement'), $this->fields(['enabled' => 0, 'message_en' => '']))->assertSessionHasNoErrors();
        auth('web')->logout();
        $this->get('/')->assertDontSee('data-site-announcement', false);
    }

    #[TestWith(['cta_url', 'javascript:alert(1)'])]
    #[TestWith(['cta_url', 'https://name:password@example.test'])]
    #[TestWith(['cta_url', 'https://example.test/%0aunsafe'])]
    #[TestWith(['cta_url', '//example.test'])]
    #[TestWith(['severity', 'script'])]
    #[TestWith(['audience', 'admin'])]
    #[TestWith(['message_en', ''])]
    #[TestWith(['end_at', '2026-10-05T11:00'])]
    #[TestWith(['start_at', '2026-04-24T00:30'])]
    #[TestWith(['start_at', '2026-10-29T23:30'])]
    public function test_invalid_configuration_is_rejected_atomically(string $field, string $value): void
    {
        Setting::set('announcement.message_en', 'Previous', 'announcement');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')
            ->post(route('admin.settings.announcement'), $this->fields(['start_at' => '2026-10-05T12:00', $field => $value]))->assertSessionHasErrors($field);

        $this->assertSame('Previous', Setting::get('announcement.message_en'));
    }
}
