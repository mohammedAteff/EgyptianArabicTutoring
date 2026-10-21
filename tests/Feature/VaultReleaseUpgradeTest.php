<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use App\Domains\Students\Services\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VaultReleaseUpgradeTest extends TestCase
{
    use DatabaseTruncation;

    public function test_upgrade_from_the_deployed_schema_preserves_identity_booking_and_financial_facts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00:00', 'UTC'));
        // The deployed 094b2c4 schema precedes this first release migration.
        $baseline = collect(File::files(database_path('migrations')))
            ->filter(fn ($file): bool => strcmp($file->getFilename(), '2026_10_08_180250_add_public_visibility_and_faq_topics.php') < 0)
            ->map(fn ($file): string => $file->getPathname())->all();
        $this->assertCount(81, $baseline);
        $this->artisan('migrate:fresh', ['--path' => $baseline, '--realpath' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('student_packages', 'expires_at'));

        $student = Student::factory()->verified()->create();
        $package = StudentPackage::withoutEvents(fn () => StudentPackage::factory()->create(['student_id' => $student->id, 'identity_state' => 'custom', 'expiration_date' => '2026-11-08', 'validity_days' => null, 'total_sessions_allocated' => 3]));
        $allocation = StudentPackageEntitlement::query()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'granted_quantity' => 3]);
        SessionLedgerEntry::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'student_package_entitlement_id' => $allocation->id, 'entitlement_type_id' => $allocation->entitlement_type_id, 'credit_change' => 3]);
        $payment = PaymentRecord::factory()->create(['student_package_id' => $package->id, 'student_id' => $student->id, 'amount_paid' => '240.00']);
        PaymentRefund::factory()->create(['student_package_id' => $package->id, 'student_id' => $student->id, 'payment_record_id' => $payment->id, 'amount_refunded' => '10.00']);
        Booking::factory()->create(['student_id' => $student->id]);

        $columns = [];
        $before = [];
        foreach (['students', 'bookings', 'student_packages', 'student_package_entitlements', 'session_ledger_entries', 'payment_records', 'payment_refunds'] as $table) {
            $columns[$table] = Schema::getColumnListing($table);
            $before[$table] = DB::table($table)->orderBy('id')->get($columns[$table])->map(fn ($record): array => (array) $record)->all();
        }
        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
        foreach ($before as $table => $records) {
            $this->assertSame($records, DB::table($table)->orderBy('id')->get($columns[$table])->map(fn ($record): array => (array) $record)->all(), 'Existing facts changed in '.$table);
        }
        $this->assertTrue(Schema::hasColumn('student_packages', 'expires_at'));
        $this->assertSame('2026-11-08', $package->fresh()->expiration_date->toDateString());
        $this->assertTrue(app(EntitlementService::class)->eligible($package->fresh()));
        $this->assertSame(3, app(EntitlementService::class)->projection($package->fresh())[0]['available']);
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.students.show', $student))->assertOk()->assertSee('240.00');
        $this->get(route('admin.students.teaching', $student))->assertOk()->assertSee('Learning Skills');
    }
}
