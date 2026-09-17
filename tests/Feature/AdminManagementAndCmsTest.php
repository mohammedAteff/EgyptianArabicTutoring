<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Notifications\AdminResetPasswordNotification;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use App\Domains\CMS\Models\Media;
use App\Domains\CMS\Models\Page;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminManagementAndCmsTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $superAdmin;

    protected Administrator $regularAdmin;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Administrator::create([
            'name' => 'Super Tutor',
            'email' => 'super@boltlanding.test',
            'password' => Hash::make('SuperSecret123!'),
            'role' => 'super_admin',
        ]);

        $this->regularAdmin = Administrator::create([
            'name' => 'Regular Tutor',
            'email' => 'regular@boltlanding.test',
            'password' => Hash::make('RegularSecret123!'),
            'role' => 'admin',
        ]);

        $this->sessionType = SessionType::create([
            'title' => 'Standard Arabic Lesson',
            'slug' => 'standard-arabic',
            'duration_minutes' => 60,
            'price' => 45.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'session_duration_minutes' => 60,
                'buffer_minutes' => 0,
                'min_notice_hours' => 0,
                'max_horizon_days' => 60,
                'enabled' => true,
            ]);
        }
    }

    public function test_admin_create_artisan_command(): void
    {
        $this->artisan('admin:create cli@boltlanding.test --name="CLI Admin" --role=admin --password="Password123!"')
            ->assertSuccessful();

        $this->assertDatabaseHas('administrators', [
            'email' => 'cli@boltlanding.test',
            'role' => 'admin',
        ]);
    }

    public function test_administrator_crud_lifecycle_and_role_protection(): void
    {
        // 1. Regular admin denied
        $this->actingAs($this->regularAdmin, 'web')
            ->get(route('admin.administrators.index'))
            ->assertForbidden();

        // 2. Super admin lists admins
        $this->actingAs($this->superAdmin, 'web')
            ->get(route('admin.administrators.index'))
            ->assertOk()
            ->assertSee('Super Tutor');

        // 3. Super admin creates new admin
        $createRes = $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.administrators.store'), [
                'name' => 'New Staff',
                'email' => 'staff@boltlanding.test',
                'role' => 'admin',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ]);

        $createRes->assertRedirect(route('admin.administrators.index'));
        $this->assertDatabaseHas('administrators', ['email' => 'staff@boltlanding.test']);

        $newStaff = Administrator::where('email', 'staff@boltlanding.test')->firstOrFail();

        // 4. Update staff
        $updateRes = $this->actingAs($this->superAdmin, 'web')
            ->put(route('admin.administrators.update', $newStaff->id), [
                'name' => 'New Staff Updated',
                'email' => 'staff@boltlanding.test',
                'role' => 'admin',
            ]);

        $updateRes->assertRedirect(route('admin.administrators.index'));
        $this->assertEquals('New Staff Updated', $newStaff->fresh()->name);

        // 5. Prevent self-deletion
        $selfDeleteRes = $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.administrators.destroy', $this->superAdmin->id));

        $selfDeleteRes->assertRedirect();
        $this->assertDatabaseHas('administrators', ['id' => $this->superAdmin->id]);

        // 6. Delete staff
        $deleteRes = $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.administrators.destroy', $newStaff->id));

        $deleteRes->assertRedirect();
        $this->assertSoftDeleted('administrators', ['id' => $newStaff->id]);
    }

    public function test_admin_password_reset_flow(): void
    {
        Notification::fake();

        // 1. Request link
        $res = $this->post(route('admin.password.email'), [
            'email' => 'super@boltlanding.test',
        ]);
        $res->assertRedirect();

        $token = null;
        Notification::assertSentTo($this->superAdmin, AdminResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return ! empty($token);
        });

        $this->assertNotNull($token);

        // Hashed token stored in database, NOT plaintext
        $tokenRecord = DB::table('password_reset_tokens')->where('email', 'super@boltlanding.test')->first();
        $this->assertNotNull($tokenRecord);
        $this->assertNotEquals($token, $tokenRecord->token);

        // 2. Reset with the real token delivered via notification
        $resetRes = $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'super@boltlanding.test',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $resetRes->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('BrandNewPassword123!', $this->superAdmin->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'super@boltlanding.test']);
    }

    public function test_page_cms_and_revisions_workflow(): void
    {
        // 1. Create page
        $res = $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.pages.store'), [
                'title' => 'Curriculum Overview',
                'slug' => 'curriculum-overview',
                'content' => 'Initial curriculum body content.',
                'status' => 'published',
            ]);

        $res->assertRedirect(route('admin.pages.index'));
        $page = Page::where('slug', 'curriculum-overview')->firstOrFail();
        $this->assertEquals(1, $page->revisions()->count());

        // 2. Update page -> creates revision #2
        $updateRes = $this->actingAs($this->superAdmin, 'web')
            ->put(route('admin.pages.update', $page->id), [
                'title' => 'Curriculum Overview v2',
                'slug' => 'curriculum-overview',
                'content' => 'Updated curriculum content for second revision.',
                'status' => 'published',
            ]);

        $updateRes->assertRedirect(route('admin.pages.index'));
        $this->assertEquals(2, $page->revisions()->count());

        // 3. Restore revision #1
        $revision1 = $page->revisions()->where('revision_number', 1)->firstOrFail();
        $restoreRes = $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.pages.revisions.restore', ['page' => $page->id, 'revision' => $revision1->id]));

        $restoreRes->assertRedirect();
        $this->assertEquals('Initial curriculum body content.', $page->fresh()->content);
    }

    public function test_media_upload_and_deletion(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('cairo-street.jpg', 600, 400);

        $uploadRes = $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.media.store'), [
                'file' => $file,
                'alt_text' => 'Cairo Street',
            ]);

        $uploadRes->assertRedirect();
        $media = Media::where('filename', 'cairo-street.jpg')->firstOrFail();
        Storage::disk('public')->assertExists($media->path);

        // Delete media
        $deleteRes = $this->actingAs($this->superAdmin, 'web')
            ->delete(route('admin.media.destroy', $media->id));

        $deleteRes->assertRedirect();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_customer_self_service_reschedule_flow(): void
    {
        $startUtc = CarbonImmutable::now('UTC')->addDays(2)->setTime(10, 0);
        $endUtc = $startUtc->addMinutes(60);

        $bookingService = app(BookingService::class);
        $booking = $bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_timezone' => 'UTC',
            'business_timezone' => 'Africa/Cairo',
            'customer_name' => 'Rescheduling Student',
            'customer_email' => 'student-resched@boltlanding.test',
            'idempotency_key' => 'idemp-resched-1',
        ], isTrustedAdmin: true);

        // 1. Visit reschedule view
        $viewRes = $this->get(route('booking.reschedule', ['token' => $booking->confirmation_token]));
        $viewRes->assertOk();
        $viewRes->assertSee('Reschedule Lesson');

        // 2. Submit reschedule to new slot
        $newStartUtc = CarbonImmutable::now('UTC')->addDays(3)->setTime(12, 0);
        $rescheduleRes = $this->post(route('booking.reschedule.submit', ['token' => $booking->confirmation_token]), [
            'new_start_utc' => $newStartUtc->toDateTimeString(),
            'reason' => 'Schedule conflict',
        ]);

        $rescheduleRes->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
        $this->assertEquals($newStartUtc->toDateTimeString(), $booking->fresh()->start_at_utc->toDateTimeString());

        // 3. Cancelled booking cannot be rescheduled
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $cancelledRes = $this->get(route('booking.reschedule', ['token' => $booking->confirmation_token]));
        $cancelledRes->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
    }

    public function test_admin_manual_booking_creation(): void
    {
        $date = now('Africa/Cairo')->addDays(4)->format('Y-m-d');

        $res = $this->actingAs($this->superAdmin, 'web')
            ->post(route('admin.bookings.store'), [
                'session_type_id' => $this->sessionType->id,
                'student_name' => 'Walk-in Student',
                'student_email' => 'walkin@boltlanding.test',
                'student_phone' => '+201012345678',
                'date' => $date,
                'time' => '14:00',
                'customer_timezone' => 'Africa/Cairo',
                'notes' => 'Booked via phone consultation',
            ]);

        $res->assertRedirect();

        $this->assertDatabaseHas('contacts', ['email' => 'walkin@boltlanding.test']);
        $this->assertDatabaseHas('bookings', [
            'session_type_id' => $this->sessionType->id,
            'customer_timezone' => 'Africa/Cairo',
        ]);
    }
}
