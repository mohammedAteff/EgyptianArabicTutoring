<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StageOnePresentationTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    #[TestWith(['super_admin', 'Super Admin'])]
    #[TestWith(['admin', 'Admin'])]
    #[TestWith(['assistant', 'Assistant'])]
    public function test_sidebar_humanizes_roles_uses_the_actual_profile_and_preserves_security_access(string $role, string $label): void
    {
        $staff = AdministratorFactory::new()->create(['role' => $role, 'name' => 'Synthetic '.$label]);
        $response = $this->actingAs($staff, 'web')->get(route('admin.students.index'))->assertOk()->assertSeeText($label.' Operations Console')->assertSeeText('Synthetic '.$label);
        $this->assertSame($role, $staff->fresh()->role);
        $xpath = $this->xpath($response->getContent());
        $security = $xpath->query('//a[@href="'.route('admin.security.show').'"]');
        $this->assertSame($role === 'super_admin' ? 1 : 0, $security->length);
        if ($role === 'super_admin') {
            $this->assertSame(1, $xpath->query('//a[@href="'.route('admin.security.show').'"]/svg')->length);
        }
        $this->assertSame(1, $xpath->query('//button[@id="admin-desktop-sidebar-toggle" and @aria-controls="admin-sidebar"]')->length);
    }

    public function test_assistant_shell_omits_destinations_denied_by_existing_role_middleware(): void
    {
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $response = $this->actingAs($assistant, 'web')->get(route('admin.operations.index'))->assertOk();
        $xpath = $this->xpath($response->getContent());

        foreach (['admin.dashboard', 'admin.notifications.index', 'admin.availability.index', 'admin.resources.index',
            'admin.games.index', 'admin.content.index', 'admin.pages.index', 'admin.blog.index', 'admin.promotions.index',
            'admin.media.index', 'admin.analytics', 'admin.analytics.countries', 'admin.analytics.sections',
            'admin.reports.index', 'admin.settings.index', 'admin.health'] as $destination) {
            $this->get(route($destination))->assertForbidden();
            $this->assertSame(0, $xpath->query('//a[@href="'.route($destination).'"]')->length, $destination);
        }
        foreach (['admin.operations.index', 'admin.tasks.index', 'admin.staff-bins.index', 'admin.students.index',
            'admin.bookings.index', 'admin.contacts.index', 'admin.leads', 'admin.forms.index'] as $destination) {
            $this->assertGreaterThan(0, $xpath->query('//nav[@id="admin-sidebar-nav"]//a[@href="'.route($destination).'"]')->length, $destination);
        }
    }

    public function test_identity_copy_controls_are_upper_right_siblings_and_do_not_submit_forms(): void
    {
        $student = Student::factory()->verified()->create();
        $response = $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->get(route('admin.students.show', $student))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(9, $xpath->query('//div[@data-copy-section]/button[@type="button"]')->length);
        $this->assertSame(0, $xpath->query('//label//button')->length);
        $this->assertSame(9, $xpath->query('//div[@data-copy-section and contains(@class,"justify-between")]')->length);
    }

    public function test_reddit_configuration_footer_and_filter_aware_social_exports_reuse_existing_paths(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $staff = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($staff, 'web')->post(route('admin.content.social.store'), ['platform' => 'reddit', 'label' => 'Reddit', 'url_or_phone' => 'https://reddit.com/r/learn_arabic'])->assertSessionHasNoErrors()->assertRedirect();
        $reddit = SocialLink::query()->where('platform', 'reddit')->sole();
        $reddit->update(['enabled' => true]);
        auth('web')->logout();
        $response = $this->get('/')->assertOk()->assertSee('data-social-platform="reddit"', false);
        $xpath = $this->xpath($response->getContent());
        $link = $xpath->query('//a[@data-social-platform="reddit"]')->item(0);
        $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
        $this->assertSame(2, $xpath->query('//a[@data-social-platform="reddit"]/svg[@aria-hidden="true"]')->length);
        $reddit->update(['enabled' => false]);
        $this->get('/')->assertDontSee('data-social-platform="reddit"', false);
        $reddit->update(['enabled' => true, 'url_or_phone' => '']);
        $this->get('/')->assertDontSee('data-social-platform="reddit"', false);

        foreach (['reddit', 'youtube'] as $platform) {
            AnalyticsEvent::create(['event_uuid' => (string) Str::uuid(), 'event_name' => 'social_link_clicked', 'visitor_token' => $platform.'-visitor', 'page' => '/'.$platform, 'metadata' => ['platform' => $platform, 'placement' => 'footer_social'], 'created_at' => now('UTC'), 'is_bot' => false]);
        }
        $scope = ['type' => 'social', 'platform' => 'reddit', 'placement' => 'footer_social', 'range' => 'today'];
        $report = $this->actingAs($staff, 'web')->get(route('admin.reports.index', $scope))->assertOk();
        $this->assertSame(1, $report->viewData('reportData')['total_clicks']);
        $this->assertSame('Reddit', $report->viewData('reportData')['rows']->sole()['platform']);
        foreach (['csv', 'xlsx'] as $format) {
            $export = $this->get(route('admin.reports.export', $scope + ['format' => $format]))->assertOk();
            if ($format === 'csv') {
                $content = $export->streamedContent();
            } else {
                $path = $export->baseResponse->getFile()->getPathname();
                $workbook = IOFactory::load($path);
                $content = json_encode($workbook->getActiveSheet()->toArray(), JSON_THROW_ON_ERROR);
                $workbook->disconnectWorksheets();
                unlink($path);
            }
            $this->assertStringContainsString('Reddit', $content);
            $this->assertStringNotContainsString('YouTube', $content);
        }
    }

    public function test_reddit_configuration_rejects_unsafe_urls_and_assistant_writes(): void
    {
        $fields = ['platform' => 'reddit', 'label' => 'Reddit', 'url_or_phone' => 'javascript:alert(1)'];
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->post(route('admin.content.social.store'), $fields)->assertSessionHasErrors('socials');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']), 'web')->post(route('admin.content.social.store'), $fields)->assertForbidden();
        $this->assertDatabaseCount('social_links', 0);
    }

    public function test_maintenance_analytics_has_one_summary_with_country_flags_and_actual_filtered_bounce_rate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        foreach ([['EG', true, '/chosen'], ['EG', false, '/chosen'], ['ZZ', true, '/other'], ['', true, '/other'], ['XX', true, '/other']] as $index => [$country, $bounced, $path]) {
            DB::table('maintenance_visits')->insert(['visitor_id' => 'visitor-'.$index, 'country_code' => $country, 'url' => $path, 'referrer' => null, 'is_bounced' => $bounced, 'created_at' => now('UTC')]);
        }
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web');
        $this->get(route('admin.health'))->assertSee(route('admin.analytics.maintenance'), false)->assertDontSee('Total Intercepted Hits');
        $all = $this->get(route('admin.analytics.maintenance', ['range' => 'today']))->assertOk()->assertSee('Egypt')->assertSee('Unknown / Unresolved (ZZ)')->assertSee('assets/flags/4x3/globe.svg')->assertSee('assets/flags/4x3/eg.svg');
        $this->assertSame(1, substr_count($all->getContent(), 'Total Intercepted Hits'));
        $filtered = $this->get(route('admin.analytics.maintenance', ['range' => 'today', 'country' => 'EG', 'path' => '/chosen']))->assertOk();
        $this->assertSame(2, $filtered->viewData('maintenanceHitsCount'));
        $this->assertSame(2, $filtered->viewData('maintenanceUniqueVisitors'));
        $this->assertSame(50.0, $filtered->viewData('maintenanceBounceRate'));
        $this->assertSame(2, $filtered->viewData('maintenanceRows')->total());
        $unknown = $this->get(route('admin.analytics.maintenance', ['range' => 'today', 'country' => 'ZZ']))->assertOk();
        $this->assertSame(3, $unknown->viewData('maintenanceHitsCount'));
        $this->assertCount(1, $unknown->viewData('maintenanceCountries'));
        $this->assertSame('ZZ', $unknown->viewData('maintenanceCountries')->sole()['country_code']);
    }

    #[TestWith(['admin'])]
    #[TestWith(['assistant'])]
    public function test_maintenance_analytics_preserves_super_admin_only_authorization(string $role): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => $role]), 'web');
        $this->get(route('admin.analytics.maintenance'))->assertForbidden();
        $this->get(route('admin.analytics.maintenance.export'))->assertForbidden();
        $this->get(route('admin.health.maintenance-visitors.export'))->assertForbidden();
    }

    public function test_analytics_copy_cleanup_preserves_historical_report_semantics(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web');
        $this->get(route('admin.analytics'))->assertOk()->assertDontSee('Based on non-bot HTTP interactions within the rolling active window.');
        $this->get(route('admin.reports.index', ['type' => 'traffic', 'range' => 'custom', 'start_date' => '2025-01-01', 'end_date' => '2025-01-02']))->assertOk()->assertDontSee('Non-Comparable Historical Data')->assertViewHas('isBeforeAuthoritativeCutover', true);
    }

    public function test_maintenance_query_count_stays_bounded_when_visits_grow(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web');
        $queries = [];
        foreach ([1, 20] as $count) {
            for ($index = 0; $index < $count; $index++) {
                DB::table('maintenance_visits')->insert(['visitor_id' => 'visitor-'.$count.'-'.$index, 'country_code' => 'EG', 'url' => '/sample', 'is_bounced' => true, 'created_at' => now('UTC')]);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route('admin.analytics.maintenance'))->assertOk();
            $queries[] = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'maintenance_visits')));
            DB::disableQueryLog();
        }

        $this->assertSame([4, 4], $queries);
    }
}
