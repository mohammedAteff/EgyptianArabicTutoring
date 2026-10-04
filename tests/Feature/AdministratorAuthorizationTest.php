<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministratorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_student_search_uses_canonical_name_and_international_phone_normalization(): void
    {
        $admin = $this->createAdministrator('admin');
        $student = Student::factory()->verified()->create([
            'name_normalized' => 'test student',
            'phone' => '+201012345678',
            'phone_normalized' => '+201012345678',
        ]);

        app(StudentLedgerService::class)->createPackage($student, 'Search roster fixture', 1, '10.00', '0.00', 'USD', null, 'search-roster-fixture', entitlementCode: 'one_hour');

        $this->actingAs($admin, 'web')
            ->get(route('admin.students.index', ['q' => 'Test, Student']))
            ->assertOk()
            ->assertSee('Student #'.$student->id);

        $this->actingAs($admin, 'web')
            ->get(route('admin.students.index', ['q' => '+20 10 1234 5678']))
            ->assertOk()
            ->assertSee('Student #'.$student->id);
    }

    public function test_assistants_can_access_assigned_read_only_pages_but_not_privileged_actions_or_draft_previews(): void
    {
        $assistant = $this->createAdministrator('assistant');
        $this->actingAs($assistant, 'web');

        foreach ([
            route('admin.bookings.index'),
            route('admin.contacts.index'),
            route('admin.students.index'),
            route('admin.forms.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $booking = $this->createBooking();
        $this->post(route('admin.bookings.reschedule', $booking), [])
            ->assertForbidden();

        foreach ([
            route('admin.dashboard'),
            route('admin.bookings.create'),
            route('admin.availability.index'),
            route('admin.content.index'),
            route('admin.media.index'),
            route('admin.reports.index'),
            route('admin.blog.create'),
            route('admin.forms.create'),
            route('admin.settings.index'),
            route('admin.administrators.index'),
            route('home.preview'),
            route('about.preview'),
            route('pages.preview', 'draft-page'),
            route('resources.preview', 'draft-resource'),
            route('games.preview', 'draft-game'),
            route('faq.preview'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post(route('admin.contacts.merge'))->assertForbidden();
    }

    public function test_admins_can_manage_business_content_but_not_owner_only_system_access(): void
    {
        $admin = $this->createAdministrator('admin');
        $this->actingAs($admin, 'web');

        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.blog.create'))->assertOk();
        $this->get(route('admin.forms.create'))->assertOk();
        $this->get(route('home.preview'))->assertOk();
        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSeeText('Settings & Policies')
            ->assertSeeText('Public maintenance mode is managed by a super administrator.')
            ->assertDontSee('name="maintenance_mode"', false)
            ->assertDontSeeText('Backups & Recovery');

        $this->get(route('admin.administrators.index'))->assertForbidden();
        $this->get(route('admin.health'))->assertForbidden();
        $this->post(route('admin.students.merge', 1))->assertForbidden();
    }

    public function test_settings_are_available_to_admin_but_maintenance_mode_remains_super_admin_only(): void
    {
        Setting::set('maintenance_mode', '0', 'general', true);
        $this->get(route('admin.settings.index'))->assertRedirect(route('admin.login'));

        $admin = $this->createAdministrator('admin');
        $this->actingAs($admin, 'web')
            ->post(route('admin.settings.update'), $this->validSettingsPayload([
                'site_name' => 'Operational Admin Update',
            ]))
            ->assertRedirect();

        $this->assertSame('Operational Admin Update', Setting::get('site_name'));

        $this->actingAs($admin, 'web')
            ->post(route('admin.settings.update').'?maintenance_mode=1', $this->validSettingsPayload())
            ->assertForbidden();

        $this->assertSame(0, Setting::get('maintenance_mode'));

        $superAdmin = $this->createAdministrator('super_admin');
        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('name="maintenance_mode"', false);

        $this->actingAs($superAdmin, 'web')
            ->post(route('admin.settings.update'), $this->validSettingsPayload([
                'maintenance_mode' => '1',
            ]))
            ->assertRedirect();

        $this->assertSame(1, Setting::get('maintenance_mode'));
    }

    private function createAdministrator(string $role): Administrator
    {
        return Administrator::query()->create([
            'name' => ucfirst($role),
            'email' => $role.'@example.test',
            'password' => Hash::make('a-long-test-password'),
            'role' => $role,
        ]);
    }

    /** @return array<string, string> */
    private function validSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'site_name' => 'Tutoring Site',
            'business_timezone' => 'Africa/Cairo',
            'default_language' => 'en',
            'hero_title' => 'Learn Egyptian Arabic',
            'hero_subtitle' => 'Private lessons with a native tutor.',
            'booking_instructions' => 'Choose an available appointment time.',
            'cancellation_policy' => 'Please cancel with notice.',
            'rescheduling_policy' => 'Rescheduling depends on availability.',
            'action' => 'publish',
        ], $overrides);
    }

    private function createBooking(): Booking
    {
        $sessionType = SessionType::query()->create([
            'title' => 'Private lesson',
            'slug' => 'private-lesson',
            'duration_minutes' => 60,
            'price' => 40,
            'currency' => 'USD',
            'active' => true,
        ]);
        $contact = Contact::query()->create([
            'name' => 'Student Contact',
            'email' => 'student-contact@example.test',
            'display_email' => 'student-contact@example.test',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $startUtc = now('UTC')->addDays(2)->startOfHour();
        $endUtc = $startUtc->addHour();
        $businessStart = $startUtc->copy()->setTimezone('Africa/Cairo');
        $customerStart = $startUtc->copy()->setTimezone('Europe/London');

        return Booking::query()->create([
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/London',
            'business_local_date_at_booking' => $businessStart->toDateString(),
            'business_local_start_time_at_booking' => $businessStart->format('H:i:s'),
            'business_local_end_time_at_booking' => $businessStart->addHour()->format('H:i:s'),
            'customer_local_date_at_booking' => $customerStart->toDateString(),
            'customer_local_start_time_at_booking' => $customerStart->format('H:i:s'),
            'customer_local_end_time_at_booking' => $customerStart->addHour()->format('H:i:s'),
            'business_utc_offset_at_booking' => $businessStart->format('P'),
            'customer_utc_offset_at_booking' => $customerStart->format('P'),
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => (string) Str::uuid(),
        ]);
    }
}
