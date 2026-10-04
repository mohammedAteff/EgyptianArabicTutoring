<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StudentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_package_terms_and_credit_balance_are_derived_from_append_only_ledger(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Eight lessons', 8, '240.00', '20.00', 'USD', null, 'package-1', entitlementCode: 'one_hour');

        $this->assertSame('220.00', $package->final_price);
        $this->assertSame(8, $ledger->summary($package)['remaining_credits']);
        $this->assertSame($package->id, $ledger->createPackage($student, 'Eight lessons', 8, '240.00', '20.00', 'USD', null, 'package-1', entitlementCode: 'one_hour')->id);
        $this->assertDatabaseCount('student_packages', 1);
        $this->assertDatabaseCount('session_ledger_entries', 1);
        $this->expectException(\RuntimeException::class);
        $package->update(['final_price' => '1.00']);
    }

    public function test_fifo_package_selection_obeys_cairo_end_of_expiration_date(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 20:59:59 UTC');
        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $earlier = $ledger->createPackage($student, 'Expires today', 1, '30.00', '0.00', 'USD', '2026-10-01', 'package-expiring', entitlementCode: 'one_hour');
        $later = $ledger->createPackage($student, 'Later', 1, '30.00', '0.00', 'USD', '2026-11-01', 'package-later', entitlementCode: 'one_hour');

        DB::transaction(function () use ($ledger, $student, $earlier): void {
            $this->assertSame($earlier->id, $ledger->selectAndLockEligiblePackageForBooking($student->id, $this->oneHourPackageLesson())?->id);
        });

        CarbonImmutable::setTestNow('2026-10-01 21:00:00 UTC');
        DB::transaction(function () use ($ledger, $student, $later): void {
            $this->assertSame($later->id, $ledger->selectAndLockEligiblePackageForBooking($student->id, $this->oneHourPackageLesson())?->id);
        });
        $this->assertSame(0, $ledger->summary($earlier)['remaining_credits']);
    }

    public function test_ledger_balance_lookup_uses_a_package_id_index_seek(): void
    {
        $student = Student::factory()->verified()->create();
        $targetPackageId = null;

        for ($index = 0; $index < 30; $index++) {
            $packageId = DB::table('student_packages')->insertGetId([
                'student_id' => $student->id,
                'package_name' => 'Index plan '.$index,
                'original_price' => '30.00',
                'discount_amount' => '0.00',
                'final_price' => '30.00',
                'currency' => 'USD',
                'total_sessions_allocated' => 1,
                'expiration_date' => null,
                'status' => 'active',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            DB::table('session_ledger_entries')->insert([
                'student_id' => $student->id,
                'student_package_id' => $packageId,
                'idempotency_key' => 'index-plan-grant-'.$index,
                'entry_type' => 'package_grant',
                'credit_change' => 1,
                'description' => 'Index plan grant',
                'created_at' => now('UTC'),
            ]);

            if ($index === 0) {
                $targetPackageId = $packageId;
            }
        }

        $plan = DB::select(
            'EXPLAIN SELECT credit_change FROM session_ledger_entries WHERE student_package_id = ? FOR UPDATE',
            [$targetPackageId],
        )[0];
        $packageIndex = DB::table('information_schema.statistics')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'session_ledger_entries')
            ->where('COLUMN_NAME', 'student_package_id')
            ->where('SEQ_IN_INDEX', 1)
            ->value('INDEX_NAME');

        $this->assertNotNull($packageIndex);
        $this->assertSame($packageIndex, $plan->key);
        $this->assertContains(strtolower((string) $plan->type), ['ref', 'range', 'eq_ref']);
    }

    public function test_refunds_obey_per_payment_and_package_ceiling_without_credit_mutation(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Eight lessons', 8, '240.00', '0.00', 'USD', null, 'package-refund', entitlementCode: 'one_hour');
        $first = $ledger->recordPayment($package, '100.00', 'payment-1', null);
        $second = $ledger->recordPayment($package, '100.00', 'payment-2', null);
        $refund = $ledger->refund($first, '75.00', 'refund-1', null);
        $this->assertSame($refund->id, $ledger->refund($first, '75.00', 'refund-1', null)->id);
        $ledger->refund($second, '100.00', 'refund-2', null);
        $this->assertSame('175.00', $ledger->summary($package)['gross_refunded']);
        $this->assertSame('25.00', $ledger->summary($package)['net_paid']);
        $this->assertSame(8, $ledger->summary($package)['remaining_credits']);
        $this->assertSame(2, PaymentRefund::count());
        $this->assertSame(1, SessionLedgerEntry::count());

        $this->expectException(InvalidArgumentException::class);
        $ledger->refund($first, '25.01', 'refund-over-ceiling', null);
    }
}
