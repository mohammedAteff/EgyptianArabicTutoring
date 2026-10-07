<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilterAwareExportsTest extends TestCase
{
    use RefreshDatabase;

    public static function datasets(): array
    {
        return array_map(fn (string $type): array => [$type], ['cashier', 'reconciliation', 'students', 'contacts', 'forms', 'overview', 'countries', 'sections', 'traffic', 'bookings', 'resources', 'social', 'events', 'campaigns', 'maintenance']);
    }

    #[DataProvider('datasets')]
    public function test_every_dataset_has_one_filter_scope_for_screen_csv_xlsx_and_clear(string $type): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'web');
        $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00', 'Africa/Cairo'));
        [$screenRoute, $exportRoute, $fixed, $filters, $included, $excluded, $viewKey] = $this->arrange($type, $admin);
        $scope = array_merge($fixed, $filters);
        $screen = $this->get(route($screenRoute, $scope))->assertOk()->assertSeeText('Filters')->assertSeeText('Apply Filters')->assertSeeText('Clear Filters');
        $clear = $this->get(route($screenRoute, $fixed))->assertOk();
        $count = function ($response) use ($viewKey, $type): int {
            $value = $response->viewData($viewKey);
            if ($type === 'overview') {
                return $value['visitors'];
            }
            if (in_array($type, ['traffic', 'bookings', 'resources', 'social', 'events', 'campaigns'], true)) {
                $value = $value['rows'];
            }
            if ($value instanceof LengthAwarePaginator) {
                return $value->total();
            }

            return count($value);
        };
        $this->assertGreaterThan($count($screen), $count($clear), $type.' clear scope');
        foreach (['csv', 'xlsx'] as $format) {
            $download = $this->get(route($exportRoute, array_merge($scope, ['format' => $format, 'page' => 999])))->assertOk();
            $download->assertHeader('Cache-Control', 'no-store, private');
            $data = $this->contents($download, $format);
            $this->assertStringContainsString($included, $data, $type.' '.$format);
            $this->assertStringNotContainsString($excluded, $data, $type.' '.$format);
            $this->assertStringNotContainsString('private_staff_notes', $data);
            $this->assertStringNotContainsString('Date of birth', $data);
            $this->assertStringNotContainsString('203.0.113.8', $data);
        }
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $denied = $this->actingAs($assistant, 'web')->get(route($exportRoute, $scope));
        $type === 'forms' ? $denied->assertOk() : $denied->assertForbidden();
        auth('web')->logout();
        $this->get(route($exportRoute, $scope))->assertRedirect();
    }

    private function contents(TestResponse $response, string $format): string
    {
        if ($format === 'csv') {
            return $response->streamedContent();
        }
        $file = $response->baseResponse->getFile()->getPathname();
        $workbook = IOFactory::load($file);
        try {
            return json_encode($workbook->getActiveSheet()->toArray(), JSON_THROW_ON_ERROR);
        } finally {
            $workbook->disconnectWorksheets();
            unlink($file);
        }
    }

    private function arrange(string $type, Administrator $admin): array
    {
        $days = [CarbonImmutable::parse('2026-10-02 12:00:00', 'Africa/Cairo')->utc(), CarbonImmutable::parse('2026-10-03 12:00:00', 'Africa/Cairo')->utc()];
        $names = ['Needle', 'Other'];
        $students = [];
        foreach ($names as $name) {
            $students[] = Student::factory()->verified()->create(['first_name' => $name, 'last_name' => 'Learner', 'name_normalized' => strtolower($name).' learner', 'email' => strtolower($name).'@example.test']);
        }
        if (in_array($type, ['cashier', 'reconciliation', 'students'], true)) {
            foreach ($students as $student) {
                $package = app(StudentLedgerService::class)->createPackage($student, $student->first_name.' package', 2, '50.00', '0.00', 'USD', null, Str::uuid()->toString(), entitlementCode: 'one_hour');
                if ($type === 'reconciliation') {
                    DB::table('student_packages')->where('id', $package->id)->update(['total_sessions_allocated' => 3]);
                } elseif ($type === 'cashier') {
                    app(StudentLedgerService::class)->recordPayment($package, '10.00', Str::uuid()->toString(), null);
                }
            }

            return match ($type) {
                'cashier' => ['admin.billing.cashier', 'admin.billing.export', [], ['student_id' => $students[0]->id], 'Needle', 'Other', 'transactionRows'],
                'reconciliation' => ['admin.billing.reconcile', 'admin.billing.reconcile.export', [], ['student_id' => $students[0]->id], 'Needle', 'Other', 'rows'],
                default => ['admin.students.index', 'admin.students.export', [], ['q' => 'Needle'], 'Needle', 'Other', 'students'],
            };
        }
        if ($type === 'contacts') {
            foreach ($names as $name) {
                Contact::create(['name' => $name.' contact', 'email' => strtolower($name).'@contact.test', 'last_seen_at' => now()]);
            }

            return ['admin.contacts.index', 'admin.contacts.export', [], ['search' => 'Needle'], 'Needle', 'Other', 'contacts'];
        }
        if ($type === 'forms') {
            $builder = app(FormBuilderService::class);
            $form = $builder->create(['title' => 'Export QA', 'slug' => 'export-qa'], [['question_key' => 'answer', 'label' => 'Answer', 'question_type' => 'short_text', 'assistant_visible' => true]], $admin);
            $form = $builder->publish($form->id, $form->active_version_id, $form->lock_version);
            $question = $form->activeVersion->questions()->firstOrFail();
            foreach ($students as $i => $student) {
                $submission = FormSubmission::create(['form_version_id' => $form->published_version_id, 'student_id' => $student->id, 'status' => $i === 0 ? 'submitted' : 'draft', 'submitted_at' => $i === 0 ? $days[$i] : null, 'created_at' => $days[$i]]);
                $submission->answers()->create(['form_question_id' => $question->id, 'value_text' => $names[$i]]);
            }

            return ['admin.forms.submissions', 'admin.forms.export', ['form' => $form->id], ['status' => 'submitted'], 'Needle', 'Other', 'submissions'];
        }
        if ($type === 'resources') {
            $category = ResourceCategory::create(['name' => 'QA', 'slug' => 'qa', 'active' => true]);
            $resources = [];
            foreach ($names as $name) {
                $resources[] = Resource::create(['title' => $name.' resource', 'slug' => strtolower($name).'-resource', 'file_type' => 'pdf', 'status' => 'published', 'category_id' => $category->id]);
            }

            return ['admin.reports.index', 'admin.reports.export', ['type' => 'resources'], ['resource_id' => $resources[0]->id], 'Needle', 'Other', 'reportData'];
        }
        if ($type === 'bookings') {
            $session = SessionType::create(['title' => 'QA lesson', 'slug' => 'qa', 'duration_minutes' => 60, 'price' => '25.00', 'currency' => 'USD', 'active' => true]);
            foreach ($names as $i => $name) {
                $contact = Contact::create(['name' => $name, 'email' => strtolower($name).'@booking.test']);
                Booking::forceCreate(['contact_id' => $contact->id, 'session_type_id' => $session->id, 'status' => $i === 0 ? 'confirmed' : 'cancelled', 'confirmation_token' => Str::random(64), 'idempotency_key' => Str::uuid()->toString(), 'created_at' => $days[$i]] + app(TimezoneService::class)->createBookingSnapshot($days[$i], $days[$i]->addHour(), 'Africa/Cairo', 'Africa/Cairo'));
            }

            return ['admin.reports.index', 'admin.reports.export', ['type' => 'bookings'], ['status' => 'confirmed'], 'Needle', 'Other', 'reportData'];
        }
        if ($type === 'maintenance') {
            foreach ($names as $i => $name) {
                DB::table('maintenance_visits')->insert(['visitor_id' => $name, 'ip_address' => null, 'country_code' => $i === 0 ? 'EG' : 'DE', 'url' => '/'.$name, 'created_at' => $days[$i], 'is_bounced' => true]);
            }

            return ['admin.analytics.maintenance', 'admin.analytics.maintenance.export', [], ['country' => 'EG'], 'Needle', 'Other', 'maintenanceRows'];
        }
        foreach ($names as $i => $name) {
            $token = Str::uuid()->toString();
            Visitor::create(['visitor_token' => $token, 'is_bot' => false, 'detected_country_code' => $i === 0 ? 'EG' : 'DE', 'first_seen_at' => $days[$i], 'last_seen_at' => $days[$i]]);
            AnalyticsEvent::create(['visitor_token' => $token, 'event_name' => match ($type) {
                'social' => 'whatsapp_clicked','sections' => 'section_view',default => 'page_view'
            }, 'page' => '/'.$name, 'utm_source' => $name, 'utm_campaign' => $name, 'utm_content' => $name, 'is_bot' => false, 'metadata' => ['platform' => 'whatsapp', 'placement' => $i === 0 ? 'footer_social' : 'floating_cta', 'detected_country_code' => $i === 0 ? 'EG' : 'DE', 'section_id' => $name.'Section', 'page_template' => 'landing'], 'created_at' => $days[$i]]);
        }

        return match ($type) {
            'overview' => ['admin.analytics', 'admin.analytics.overview.export', [], ['start_date' => '2026-10-02', 'end_date' => '2026-10-02'], '2026-10-02', '2026-10-03', 'traffic'],
            'countries' => ['admin.analytics.countries', 'admin.analytics.countries.export', [], ['search' => 'Egypt', 'sort' => 'country_name', 'dir' => 'asc'], 'Egypt', 'Germany', 'countries'],
            'sections' => ['admin.analytics.sections', 'admin.analytics.sections.export', [], ['section' => 'Needle'], 'Needle', 'Other', 'sections'],
            'social' => ['admin.reports.index', 'admin.reports.export', ['type' => 'social'], ['placement' => 'footer_social'], 'Needle', 'Other', 'reportData'],
            'events' => ['admin.reports.index', 'admin.reports.export', ['type' => 'events'], ['page_url' => '/Needle'], 'Needle', 'Other', 'reportData'],
            'campaigns' => ['admin.reports.index', 'admin.reports.export', ['type' => 'campaigns'], ['campaign' => 'Needle', 'source' => 'Needle', 'content' => 'Needle'], 'Needle', 'Other', 'reportData'],
            default => ['admin.reports.index', 'admin.reports.export', ['type' => 'traffic'], ['source' => 'Needle'], 'Needle', 'Other', 'reportData'],
        };
    }

    public function test_xlsx_bounds_and_shared_writer_keep_strings_and_trusted_numbers_safe(): void
    {
        $service = app(ExportService::class);
        $response = $service->exportXlsx('qa.xlsx', [' =Header'], [[" \t=CMD", '00123', '+201022222222', '-REFERENCE', -12.5, 4]]);
        $file = $response->getFile()->getPathname();
        $workbook = IOFactory::load($file);
        try {
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('s', $sheet->getCell('A1')->getDataType());
            $this->assertSame("' =Header", $sheet->getCell('A1')->getValue());
            $this->assertSame('00123', $sheet->getCell('B2')->getValue());
            $this->assertSame('s', $sheet->getCell('C2')->getDataType());
            $this->assertSame(-12.5, $sheet->getCell('E2')->getValue());
            $this->assertSame('n', $sheet->getCell('F2')->getDataType());
        } finally {
            $workbook->disconnectWorksheets();
            unlink($file);
        }
        $rows = function (): \Generator {
            for ($i = 0; $i <= ExportService::XLSX_MAX_ROWS; $i++) {
                yield [$i];
            }
        };
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Export CSV');
        $service->exportXlsx('too-large.xlsx', ['Number'], $rows());
    }
}
