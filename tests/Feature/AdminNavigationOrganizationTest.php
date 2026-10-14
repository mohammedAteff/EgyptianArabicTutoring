<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminNavigationOrganizationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['super_admin'])]
    #[TestWith(['admin'])]
    #[TestWith(['assistant'])]
    public function test_every_existing_destination_and_new_vault_tool_remains_reachable_for_its_staff_role(string $role): void
    {
        config(['development_tools.enabled' => true]);
        $staff = Administrator::factory()->create(['role' => $role]);
        $expected = [
            'admin.operations.index', 'admin.tasks.index', 'admin.staff-bins.index', 'admin.staff-note-templates.library',
            'admin.students.index', 'admin.contacts.index', 'admin.leads', 'admin.bookings.index', 'admin.forms.index', 'admin.forms.completion',
        ];
        if ($role !== 'assistant') {
            $expected = array_merge($expected, [
                'admin.dashboard', 'admin.notifications.index', 'admin.reports.activity', 'admin.data-quality.index',
                'admin.availability.index', 'admin.recurring.index', 'admin.waitlist.index', 'admin.booking-policy.index', 'admin.meeting-links.index',
                'admin.billing.cashier', 'admin.billing.reconcile', 'admin.receivables.index', 'admin.payment-methods.index',
                'admin.lms.courses.index', 'admin.lms.operations', 'admin.resources.index', 'admin.resource-collections.index', 'admin.games.index',
                'admin.content.index', 'admin.website-copy.index', 'admin.blog.index', 'admin.media.index', 'admin.promotions.index',
                'admin.reports.lifecycle', 'admin.reports.index', 'admin.analytics', 'admin.analytics.countries', 'admin.analytics.sections',
                'admin.settings.index', 'admin.telegram.index',
            ]);
        }
        if ($role === 'super_admin') {
            $expected = array_merge($expected, [
                'admin.reason-codes.index', 'admin.security.show', 'admin.health', 'admin.backups.index',
                'admin.administrators.index', 'admin.audit-logs', 'admin.analytics.maintenance', 'admin.development-tools.index',
            ]);
        }
        $expected = array_map(fn (string $destination): string => route($destination), $expected);
        sort($expected);

        $response = $this->actingAs($staff, 'web')->get(route('admin.students.index'))->assertOk();
        $xpath = $this->xpath($response->getContent());

        foreach (['//nav[@id="admin-sidebar-nav"]//a', '//nav[@aria-label="Quick destinations"]//a'] as $selector) {
            $actual = [];
            foreach ($xpath->query($selector) as $link) {
                $actual[] = $link->getAttribute('href');
            }
            $actual = array_values(array_unique($actual));
            sort($actual);
            $this->assertSame($expected, $actual, $selector.' for '.$role);
        }
        $this->assertSame($role === 'assistant' ? 4 : 8, $xpath->query('//nav[@id="admin-sidebar-nav"]/details')->length);
        $this->assertSame($role, $staff->fresh()->role);
    }

    public function test_development_tools_remain_hidden_when_the_existing_switch_is_disabled(): void
    {
        config(['development_tools.enabled' => false]);
        $staff = Administrator::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($staff, 'web')->get(route('admin.students.index'))->assertOk();

        $this->assertSame(0, $this->xpath($response->getContent())->query('//a[@href="'.route('admin.development-tools.index').'"]')->length);
    }

    #[TestWith(['admin.forms.completion', 'learning', 'admin.forms.completion'])]
    #[TestWith(['admin.lms.courses.create', 'learning', 'admin.lms.courses.index'])]
    #[TestWith(['admin.reports.activity', 'daily', 'admin.reports.activity'])]
    public function test_current_page_opens_its_group_and_highlights_one_destination(string $page, string $group, string $destination): void
    {
        $staff = Administrator::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($staff, 'web')->get(route($page))->assertOk();
        $xpath = $this->xpath($response->getContent());

        $this->assertSame(1, $xpath->query('//details[@data-navigation-group="'.$group.'" and @open]')->length);
        $this->assertSame(1, $xpath->query('//nav[@id="admin-sidebar-nav"]//a[@aria-current="page"]')->length);
        $this->assertSame(route($destination), $xpath->query('//nav[@id="admin-sidebar-nav"]//a[@aria-current="page"]')->item(0)->getAttribute('href'));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }
}
