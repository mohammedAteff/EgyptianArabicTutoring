<?php

namespace Tests\Feature;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\DirectoryQuery;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Services\CashierReportService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentRecordsQuery;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FeatureExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_refunds_and_credits_are_separate_numeric_xlsx_cells_with_stable_references(): void
    {
        $student = Student::factory()->verified()->create(['first_name' => '=FORMULA', 'last_name' => 'Learner']);
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'QA package', 4, '100.00', '0.00', 'USD', null, 'expansion-package');
        $payment = $ledger->recordPayment($package, '100.00', 'expansion-payment', null);
        $refund = $ledger->refund($payment, '20.00', 'expansion-refund', null, 'Manual correction', 0);
        $credit = $ledger->adjustCredits($package, 2, 'Courtesy', 'expansion-credit', null);
        $package->load(['payments', 'refunds', 'ledgerEntries']);
        $ledger->adjustCredits($package, 1, 'Later courtesy', 'later-credit', null);
        $this->assertSame(7, $ledger->summary($package)['remaining_credits']);
        $this->assertSame(6, $ledger->summary($package, true)['remaining_credits']);
        $service = app(CashierReportService::class);
        $rows = iterator_to_array($service->rows(['student_id' => $student->id]));
        $this->assertCount(4, $rows);
        $this->assertSame(-20.0, $rows[1][5]);
        $this->assertSame('REF-'.$refund->id, $rows[1][9]);
        $this->assertSame('PAY-'.$payment->id, $rows[1][10]);
        $this->assertSame('CRD-'.$credit->id, $rows[3][9]);
        $this->assertSame('', $rows[2][5]);
        $this->assertSame('', $rows[2][6]);
        $this->assertSame(2, $rows[3][7]);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $response = $this->actingAs($admin, 'web')->get(route('admin.billing.export', ['student_id' => $student->id, 'format' => 'xlsx']))->assertOk()->baseResponse;
        $file = $response->getFile()->getPathname();
        try {
            $sheet = IOFactory::load($file)->getActiveSheet();
            $this->assertSame('n', $sheet->getCell('F3')->getDataType());
            $this->assertSame(-20.0, $sheet->getCell('F3')->getValue());
            $this->assertSame('n', $sheet->getCell('H4')->getDataType());
            $this->assertSame(1, $sheet->getCell('H4')->getValue());
            $this->assertSame(2, $sheet->getCell('H5')->getValue());
            $this->assertSame('s', $sheet->getCell('B2')->getDataType());
            $this->assertStringStartsWith("'=FORMULA", $sheet->getCell('B2')->getValue());
        } finally {
            unlink($file);
        }
    }

    public function test_filtered_cashier_screen_and_download_share_business_day_boundaries(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Boundary package', 3, '90.00', '0.00', 'USD', null, 'boundary-package');
        $this->travelTo(CarbonImmutable::parse('2026-10-02 21:00:00', 'UTC'));
        $first = $ledger->recordPayment($package, '30.00', 'boundary-first', null);
        $this->travelTo(CarbonImmutable::parse('2026-10-03 21:00:00', 'UTC'));
        $second = $ledger->recordPayment($package, '30.00', 'boundary-second', null);
        $this->travelBack();
        $filters = ['student_id' => $student->id, 'date_from' => '2026-10-03', 'date_to' => '2026-10-03', 'transaction_type' => 'payment'];
        $this->actingAs($admin, 'web')->get(route('admin.billing.cashier', $filters))->assertOk()->assertSee('PAY-'.$first->id)->assertDontSee('PAY-'.$second->id);
        $response = $this->get(route('admin.billing.export', $filters))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('2026-10-03 00:00', $csv);
        $this->assertStringContainsString('PAY-'.$first->id, $csv);
        $this->assertStringNotContainsString('PAY-'.$second->id, $csv);
        $this->assertStringContainsString('Africa/Cairo', $csv);
        $this->get(route('admin.billing.export', ['transaction_type' => '<script>']))->assertSessionHasErrors('transaction_type');
    }

    public function test_roster_excludes_resource_only_and_merged_records_and_export_matches_filters(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create(['first_name' => 'Tutoring', 'last_name' => 'Only', 'name_normalized' => 'tutoring only']);
        $resource = Student::factory()->verified()->create(['first_name' => 'Resource', 'last_name' => 'Only']);
        app(StudentLedgerService::class)->createPackage($student, 'Roster package', 2, '20.00', '0.00', 'USD', null, 'roster-package');
        $unsettled = Student::factory()->verified()->create();
        app(StudentLedgerService::class)->createPackage($unsettled, 'Foundation Coaching Track', 8, '280.00', '0.00', 'USD', null, 'unsettled-roster', null, 'foundation_track');
        $this->assertSame([$student->id], app(StudentRecordsQuery::class)->query(['credits' => 'available'])->pluck('id')->all());
        $unsettled->delete();
        $this->assertSame([$student->id], app(StudentRecordsQuery::class)->query([])->pluck('id')->all());
        $this->actingAs($admin, 'web')->get(route('admin.students.index'))->assertOk()->assertSee('Total Students')->assertViewHas('totalStudents', 1)->assertSee('Tutoring Only')->assertDontSee('Resource Only');
        $csv = $this->get(route('admin.students.export', ['q' => 'Tutoring']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Tutoring Only', $csv);
        $this->assertStringNotContainsString($resource->email, $csv);
        $this->assertStringNotContainsString('Date of birth', $csv);
        $this->assertStringNotContainsString('Internal notes', $csv);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']), 'web')->get(route('admin.students.export'))->assertForbidden();
    }

    public function test_directory_collapses_verified_aliases_and_searches_secondary_email(): void
    {
        $student = Student::factory()->verified()->create(['email' => 'primary@example.org', 'email_normalized' => 'primary@example.org']);
        app(StudentLedgerService::class)->createPackage($student, 'Directory package', 1, '10.00', '0.00', 'USD', null, 'directory-package');
        StudentEmail::factory()->create(['student_id' => $student->id, 'email_normalized' => 'alias@example.org', 'verified_at' => now()]);
        foreach (['primary@example.org', 'alias@example.org'] as $email) {
            Contact::create(['name' => 'Old alias', 'email' => $email, 'last_seen_at' => now()]);
        }
        Contact::create(['name' => 'Other Lead', 'email' => 'lead@example.org', 'last_seen_at' => now()]);
        $directory = app(DirectoryQuery::class);
        $rows = $directory->query([])->get();
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->where('student_id', $student->id)->count());
        $this->assertSame((string) $student->id, $directory->query(['search' => 'alias@example.org'])->sole()->student_id);
        $this->assertSame('Other Lead', $directory->query(['population' => 'leads'])->sole()->name);
    }

    public function test_credit_tables_show_same_extended_expiry_and_clipboard_controls_are_present(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Expiry package', 2, '20.00', '0.00', 'USD', '2026-12-15', 'expiry-package');
        $ledger->adjustCredits($package, 1, 'Courtesy extension', 'expiry-credit', $admin->id);
        $this->actingAs($admin, 'web')->post(route('admin.students.packages.validity', [$student, $package]), ['previous_expiration_date' => '2026-12-15', 'expiration_date' => '2027-01-15', 'reason' => 'Travel'])->assertSessionHasNoErrors();
        foreach ([route('admin.students.show', $student), route('admin.billing.cashier', ['student_id' => $student->id])] as $url) {
            $this->get($url)->assertOk()->assertSee('2027-01-15')->assertSee('Expiry package');
        }
        $this->get(route('admin.students.show', $student))->assertSee('data-copy-student', false)->assertSee('data-copy-field="date_of_birth"', false);
    }
}
