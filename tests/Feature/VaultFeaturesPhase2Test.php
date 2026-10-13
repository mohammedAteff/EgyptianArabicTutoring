<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffNoteTemplate;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentGuardian;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use App\Domains\Students\Services\StudentCompletenessService;
use App\Domains\Students\Services\StudentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultFeaturesPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Administrator::factory()->create([
            'role' => 'super_admin',
        ]);
    }

    public function test_admin_can_manage_student_guardians(): void
    {
        $student = Student::factory()->verified()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.students.guardians.store', $student), [
                'name' => 'John Doe Sr.',
                'relationship' => 'Father',
                'email' => 'father@example.com',
                'phone' => '+15551234567',
                'is_primary' => '1',
                'is_billing_contact' => '1',
                'notes' => 'Primary tuition sponsor',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'name' => 'John Doe Sr.',
            'relationship' => 'Father',
            'email' => 'father@example.com',
            'is_primary' => true,
            'is_billing_contact' => true,
        ]);

        $guardian = StudentGuardian::query()->where('student_id', $student->id)->firstOrFail();

        $deleteResponse = $this->actingAs($this->admin, 'web')
            ->delete(route('admin.students.guardians.destroy', [$student, $guardian]));

        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('student_guardians', [
            'id' => $guardian->id,
        ]);
    }

    public function test_admin_cannot_pause_package_with_future_scheduled_sessions(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->createPackageForStudent($student, 5);

        $entitlementType = EntitlementType::query()->where('code', 'one_hour')->firstOrFail();

        $sessionType = SessionType::query()->create([
            'title' => 'One Hour Coaching',
            'slug' => 'one-hour-coaching-'.fake()->unique()->slug(),
            'duration_minutes' => 60,
            'price' => '40.00',
            'currency' => 'USD',
            'active' => true,
            'funding_mode' => 'package',
            'required_entitlement_type_id' => $entitlementType->id,
            'required_entitlement_units' => 1,
        ]);

        $booking = Booking::factory()->create([
            'student_id' => $student->id,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => now('UTC')->addDays(3),
            'end_at_utc' => now('UTC')->addDays(3)->addHour(),
            'status' => 'confirmed',
        ]);

        DB::transaction(function () use ($booking) {
            app(StudentLedgerService::class)->consumeForBooking($booking, 'pause-debit-'.$booking->id);
        });

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.packages.pause', $package), [
                'reason' => 'Student asked for pause',
            ]);

        $response->assertSessionHasErrors('package');
        $this->assertNull($package->fresh()->paused_at);
    }

    public function test_admin_can_pause_and_resume_package_without_future_bookings(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->createPackageForStudent($student, 5);
        $originalExpiry = $package->expires_at;

        // Pause package
        $pauseResponse = $this->actingAs($this->admin, 'web')
            ->post(route('admin.packages.pause', $package), [
                'reason' => 'Travel vacation',
            ]);

        $pauseResponse->assertSessionHasNoErrors();
        $this->assertNotNull($package->fresh()->paused_at);
        $this->assertEquals('Travel vacation', $package->fresh()->pause_reason);

        // Simulate 7 days passed
        $package->fresh()->update(['paused_at' => now('UTC')->subDays(7)]);

        // Resume package
        $resumeResponse = $this->actingAs($this->admin, 'web')
            ->post(route('admin.packages.resume', $package));

        $resumeResponse->assertSessionHasNoErrors();
        $refreshed = $package->fresh();
        $this->assertNull($refreshed->paused_at);
        $this->assertNull($refreshed->pause_reason);
        // Expiry was extended
        $this->assertTrue($refreshed->expires_at->greaterThan($originalExpiry));
    }

    public function test_admin_can_save_package_business_notes(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->createPackageForStudent($student, 5);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.packages.notes', $package), [
                'admin_notes' => 'Special 10% discount authorized by Abdallah.',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Special 10% discount authorized by Abdallah.', $package->fresh()->admin_notes);
    }

    public function test_admin_can_transfer_credits_between_packages(): void
    {
        $student = Student::factory()->verified()->create();
        $sourcePackage = $this->createPackageForStudent($student, 5);
        $targetPackage = $this->createPackageForStudent($student, 0);

        $sourceAlloc = $sourcePackage->entitlements()->first();
        $targetAlloc = $targetPackage->entitlements()->first();

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.packages.transfer', $sourcePackage), [
                'target_package_id' => $targetPackage->id,
                'credits' => 2,
                'source_allocation_id' => $sourceAlloc->id,
                'target_allocation_id' => $targetAlloc->id,
                'description' => 'Transfer unused hours to new package',
                'idempotency_key' => (string) Str::uuid(),
            ]);

        $response->assertSessionHasNoErrors();

        // Check source debited
        $this->assertDatabaseHas('session_ledger_entries', [
            'student_package_id' => $sourcePackage->id,
            'entry_type' => 'transfer_out',
            'credit_change' => -2,
        ]);

        // Check target credited
        $this->assertDatabaseHas('session_ledger_entries', [
            'student_package_id' => $targetPackage->id,
            'entry_type' => 'transfer_in',
            'credit_change' => 2,
        ]);
    }

    public function test_staff_can_toggle_pinned_student(): void
    {
        $student = Student::factory()->verified()->create();

        // Pin student
        $pinResponse = $this->actingAs($this->admin, 'web')
            ->post(route('admin.students.pin', $student));

        $pinResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff_pinned_students', [
            'administrator_id' => $this->admin->id,
            'student_id' => $student->id,
        ]);

        // Unpin student
        $unpinResponse = $this->actingAs($this->admin, 'web')
            ->post(route('admin.students.pin', $student));

        $unpinResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('staff_pinned_students', [
            'administrator_id' => $this->admin->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_staff_can_view_and_create_note_templates(): void
    {
        StaffNoteTemplate::create([
            'title' => 'Test Assessment Template',
            'category' => 'teaching',
            'body' => 'Test assessment body',
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson(route('admin.staff-note-templates.index'));

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Test Assessment Template']);

        $createResponse = $this->actingAs($this->admin, 'web')
            ->post(route('admin.staff-note-templates.store'), [
                'title' => 'New Homework Template',
                'category' => 'teaching',
                'body' => 'Homework completion feedback',
            ]);

        $createResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff_note_templates', [
            'title' => 'New Homework Template',
        ]);
    }

    public function test_staff_can_create_bulk_tasks(): void
    {
        $studentA = Student::factory()->verified()->create();
        $studentB = Student::factory()->verified()->create();

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.students.bulk-task'), [
                'student_ids' => [$studentA->id, $studentB->id],
                'title' => 'Bulk Term Review',
                'description' => 'Review upcoming term placement',
                'due_date' => now()->addDays(5)->toDateString(),
                'priority' => 'high',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff_tasks', [
            'student_id' => $studentA->id,
            'title' => 'Bulk Term Review',
            'priority' => 'high',
        ]);
        $this->assertDatabaseHas('staff_tasks', [
            'student_id' => $studentB->id,
            'title' => 'Bulk Term Review',
            'priority' => 'high',
        ]);
    }

    public function test_tutor_daily_summary_command_runs_successfully(): void
    {
        $this->artisan('tutor:daily-summary')
            ->assertSuccessful();
    }

    public function test_student_profile_completeness_service(): void
    {
        $student = Student::factory()->verified()->create([
            'phone' => '+15551234567',
            'preferred_timezone' => 'Africa/Cairo',
            'date_of_birth' => '2000-01-01',
        ]);

        $service = app(StudentCompletenessService::class);
        $result = $service->assess($student);

        $this->assertGreaterThan(50, $result['percentage']);
        $this->assertContains('Basic Identity', $result['completed']);
        $this->assertContains('Phone Number', $result['completed']);
        $this->assertContains('Timezone', $result['completed']);
        $this->assertContains('Date of Birth', $result['completed']);
    }

    private function createPackageForStudent(Student $student, int $credits): StudentPackage
    {
        $package = StudentPackage::create([
            'student_id' => $student->id,
            'package_name' => '10-Session Track',
            'original_price' => '250.00',
            'discount_amount' => '0.00',
            'final_price' => '250.00',
            'currency' => 'USD',
            'status' => 'active',
            'identity_state' => 'mapped',
            'total_sessions_allocated' => $credits,
            'expires_at' => now('UTC')->addDays(60),
            'validity_days' => 60,
        ]);

        $entitlementType = EntitlementType::query()->where('code', 'one_hour')->firstOrFail();

        $alloc = StudentPackageEntitlement::create([
            'student_id' => $student->id,
            'student_package_id' => $package->id,
            'entitlement_type_id' => $entitlementType->id,
            'granted_quantity' => $credits,
        ]);

        if ($credits > 0) {
            SessionLedgerEntry::create([
                'student_id' => $student->id,
                'student_package_id' => $package->id,
                'student_package_entitlement_id' => $alloc->id,
                'entitlement_type_id' => $entitlementType->id,
                'entry_type' => 'package_grant',
                'credit_change' => $credits,
                'description' => 'Package grant',
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => now('UTC'),
            ]);
        }

        return $package;
    }
}
