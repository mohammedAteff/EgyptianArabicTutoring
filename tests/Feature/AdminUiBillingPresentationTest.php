<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentPackagePresentation;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUiBillingPresentationTest extends TestCase
{
    use RefreshDatabase;

    public static function filterScreens(): array
    {
        return [
            'cashier' => ['admin.billing.cashier', ['q' => 'Absent', 'date_from' => '2020-01-01', 'date_to' => '2020-01-02', 'transaction_type' => 'refund', 'payment_method' => 'Absent', 'package_status' => 'cancelled', 'sort' => 'oldest', 'page' => 2], 'students'],
            'roster' => ['admin.students.index', ['q' => 'Absent', 'status' => 'suspended', 'package' => 'Absent', 'credits' => 'none', 'expiry_from' => '2020-01-01', 'expiry_to' => '2020-01-02', 'timezone' => 'Europe/Berlin', 'session_status' => 'cancelled', 'joined_from' => '2020-01-01', 'joined_to' => '2020-01-02', 'sort' => 'oldest', 'page' => 2], 'students'],
            'contacts' => ['admin.contacts.index', ['search' => 'Absent', 'population' => 'leads', 'booking_status' => 'cancelled', 'activity_from' => '2020-01-01', 'activity_to' => '2020-01-02', 'sort' => 'oldest', 'page' => 2], 'contacts'],
            'notes' => ['admin.staff-bins.index', ['q' => 'Absent', 'owner' => 'mine', 'sort' => 'oldest', 'page' => 2], 'bins'],
        ];
    }

    #[DataProvider('filterScreens')]
    public function test_clear_filters_restores_default_scope(string $route, array $filters, string $results): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $package = $this->package($student, 'First package');
        $this->package(Student::factory()->verified()->create(), 'Second package');
        Contact::create(['name' => 'First contact', 'email' => 'first@example.test', 'last_seen_at' => now()]);
        Contact::create(['name' => 'Second contact', 'email' => 'second@example.test', 'last_seen_at' => now()]);
        StaffBin::factory()->create(['author_id' => $admin->id, 'title' => 'Own note']);
        StaffBin::factory()->create(['title' => 'Other staff note']);
        if ($route === 'admin.billing.cashier') {
            $filters += ['student_id' => $student->id, 'package_id' => $package->id];
        }
        $filtered = $this->actingAs($admin, 'web')->get(route($route, $filters))->assertOk()
            ->assertSee('Apply Filters')->assertSee('Clear Filters')->assertDontSee('Clear / all dates')->assertDontSee('Apply filters');
        $clear = $this->xpath($filtered->getContent())->query('//a[normalize-space(.)="Clear Filters"]')->item(0);
        $this->assertSame(route($route), $clear->getAttribute('href'));
        $this->assertStringContainsString('border-slate-300', $clear->getAttribute('class'));
        $reset = $this->get($clear->getAttribute('href'))->assertOk()->assertViewHas('filters', []);
        $this->assertSame(1, $reset->viewData($results)->currentPage());
        $this->assertSame($results === 'contacts' ? 4 : 2, $reset->viewData($results)->total());
        if ($results === 'bins') {
            $reset->assertSee('Own note')->assertSee('Other staff note');
        }
    }

    public function test_fixed_reset_scope_and_shared_copy_controls(): void
    {
        $filters = Blade::render('<x-report-filters :filters="$filters" :fields="$fields" :fixed="$fixed" :action="$action" />', [
            'filters' => ['q' => 'Transient', 'page' => 4], 'fields' => ['q' => ['Search', 'search']], 'fixed' => ['student' => 31], 'action' => '/scoped-notes',
        ]);
        $this->assertSame('/scoped-notes?student=31', $this->xpath($filters)->query('//a[normalize-space(.)="Clear Filters"]')->item(0)->getAttribute('href'));
        foreach (['field="email" label="Copy Email"', ':all="true" label="Copy Student Details"', ':bin="true" label="Copy note"'] as $props) {
            $xpath = $this->xpath(Blade::render('<x-copy-button '.$props.' />'));
            $button = $xpath->query('//button')->item(0);
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertNotEmpty($button->getAttribute('aria-label'));
            $this->assertStringContainsString('min-h-11 min-w-11', $button->getAttribute('class'));
            $this->assertSame(1, $xpath->query('//svg/rect')->length);
            $this->assertSame(1, $xpath->query('//span[@data-copy-status and @aria-live="polite"]')->length);
        }
    }

    public static function billingTabs(): array
    {
        return array_map(fn (string $tab): array => [$tab], ['overview', 'payments', 'credits', 'expiration', 'history']);
    }

    #[DataProvider('billingTabs')]
    public function test_one_panel_targets_selected_package(string $tab): void
    {
        $student = Student::factory()->verified()->create();
        $first = $this->package($student, 'Independent first');
        $second = $this->package($student, 'Independent second');
        PaymentMethod::factory()->create();
        $response = $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')
            ->get(route('admin.students.show', ['student' => $student->id, 'package_id' => $first->id, 'billing_tab' => $tab]))->assertOk()
            ->assertSee('Independent first')->assertSee('Independent second');
        $xpath = $this->xpath($response->getContent());
        $panels = $xpath->query('//*[@data-package-panel]');
        $this->assertSame(1, $panels->length);
        $this->assertSame((string) $first->id, $panels->item(0)->getAttribute('data-package-panel'));
        $this->assertSame(route('admin.billing.cashier', ['student_id' => $student->id]), $xpath->query('//a[normalize-space(.)="Open Full Cashier View →"]')->item(0)->getAttribute('href'));
        foreach (['payments' => 'admin.students.payments.store', 'credits' => 'admin.students.credits.adjust', 'expiration' => 'admin.students.packages.validity'] as $section => $action) {
            $this->assertSame($section === $tab ? 1 : 0, $xpath->query('//form[@action="'.route($action, [$student->id, $first->id]).'"]')->length);
            $this->assertSame(0, $xpath->query('//form[@action="'.route($action, [$student->id, $second->id]).'"]')->length);
        }
        $this->assertSame(8, $xpath->query('//div[@data-copy-section]/button[@data-copy-field]')->length);
    }

    public function test_empty_single_default_and_foreign_package_selection(): void
    {
        $student = Student::factory()->verified()->create();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']), 'web');
        $this->get(route('admin.students.show', $student->id))->assertOk()->assertViewHas('selectedFinancial', null)->assertSee('No packages yet');
        $package = $this->package($student, 'Only package');
        $this->get(route('admin.students.show', $student->id))->assertOk()->assertViewHas('selectedFinancial', fn ($item) => $item['package']->id === $package->id);
        $foreign = $this->package(Student::factory()->verified()->create(), 'Foreign purchase');
        $this->get(route('admin.students.show', ['student' => $student->id, 'package_id' => $foreign->id]))->assertNotFound();
        $this->get(route('admin.students.show', ['student' => $student->id, 'billing_tab' => '<script>']))->assertSessionHasErrors('billing_tab');
    }

    public function test_student_record_package_form_has_one_unambiguous_funding_type(): void
    {
        $student = Student::factory()->verified()->create();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web');
        $response = $this->get(route('admin.students.show', $student->id))->assertOk();
        $action = route('admin.students.packages.store', $student->id);
        $xpath = $this->xpath($response->getContent());
        $selectors = $xpath->query('//form[@action="'.$action.'"]//select[@name="entitlement_code"]');
        $this->assertSame(1, $selectors->length);
        $this->assertSame(1, $xpath->query('//form[@action="'.$action.'"]//select[@name="entitlement_code"]/option[@value="one_hour"]')->length);
        $this->assertSame(1, $xpath->query('//form[@action="'.$action.'"]//select[@name="entitlement_code"]/option[@value="two_hour"]')->length);
    }

    public static function historyTypes(): array
    {
        return array_map(fn (string $type): array => [$type], ['all', 'payments', 'refunds', 'credits', 'expiry']);
    }

    #[DataProvider('historyTypes')]
    public function test_history_is_selected_and_filtered(string $type): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $package = $this->package($student, 'Selected purchase');
        $other = $this->package($student, 'Other purchase');
        $ledger = app(StudentLedgerService::class);
        $payment = $ledger->recordPayment($package, '30.00', (string) Str::uuid(), $admin->id, 'Cash', 'SELECTED-REF', 'Selected private note');
        $refund = $ledger->refund($payment, '5.00', (string) Str::uuid(), $admin->id, 'Selected refund reason', 0);
        $credit = $ledger->adjustCredits($package, 1, 'Selected credit reason', (string) Str::uuid(), $admin->id, allocationId: $package->entitlements()->value('id'));
        $ledger->recordPayment($other, '50.00', (string) Str::uuid(), $admin->id, 'Cash', 'FOREIGN-REF', 'Foreign private note');
        app(AuditLogService::class)->log('package_validity_extended', StudentPackage::class, $package->id, ['expiration_date' => '2027-01-01'], ['expiration_date' => '2027-02-01', 'reason' => 'Selected extension'], $admin->id);
        $audit = AuditLog::query()->where('action', 'package_validity_extended')->sole();
        $response = $this->actingAs($admin, 'web')->get(route('admin.students.show', ['student' => $student->id, 'package_id' => $package->id, 'billing_tab' => 'history', 'history_type' => $type]))->assertOk()->assertDontSee('FOREIGN-REF')->assertDontSee('Foreign private note');
        $history = $response->viewData('packageHistory');
        $this->assertSame($type === 'all' ? 5 : ($type === 'credits' ? 2 : 1), $history->count());
        if ($type !== 'all') {
            $this->assertSame([$type], $history->pluck('type')->unique()->values()->all());
        } else {
            foreach (['PAY-'.$payment->id, 'REF-'.$refund->id, 'CRD-'.$credit->id, 'AUD-'.$audit->id] as $reference) {
                $response->assertSee($reference);
            }
            $response->assertSee('Selected private note')->assertSee('Selected credit reason')->assertSee('Selected extension');
        }
    }

    public static function financialActions(): array
    {
        return [['payments'], ['credits'], ['expiration'], ['refunds']];
    }

    #[DataProvider('financialActions')]
    public function test_rendered_action_changes_only_selected_package(string $action): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $selected = $this->package($student, 'Selected');
        $other = $this->package($student, 'Untouched');
        $method = PaymentMethod::factory()->create();
        $ledger = app(StudentLedgerService::class);
        $payment = $ledger->recordPayment($selected, '30.00', (string) Str::uuid(), $admin->id, $method->name);
        $tab = $action === 'refunds' ? 'payments' : $action;
        $url = route('admin.students.show', ['student' => $student->id, 'package_id' => $selected->id, 'billing_tab' => $tab]);
        $this->actingAs($admin, 'web');
        $html = $this->get($url)->assertOk()->getContent();
        $routes = ['payments' => 'admin.students.payments.store', 'credits' => 'admin.students.credits.adjust', 'expiration' => 'admin.students.packages.validity', 'refunds' => 'admin.students.refunds.store'];
        $target = route($routes[$action], [$student->id, $action === 'refunds' ? $payment->id : $selected->id]);
        $form = $this->xpath($html)->query('//form[@action="'.$target.'"]')->item(0);
        $this->assertNotNull($form);
        $data = match ($action) {
            'payments' => ['amount_paid' => '7.00', 'payment_method' => (string) $method->id, 'transaction_reference' => 'MANUAL-7', 'notes' => 'Private note'],
            'credits' => ['allocation_id' => $selected->entitlements()->value('id'), 'credit_change' => -1, 'description' => 'Selected correction'],
            'expiration' => ['expiration_date' => '2027-02-01', 'reason' => 'Selected extension'],
            'refunds' => ['amount_refunded' => '5.00', 'reason' => 'Selected refund'],
        };
        foreach ($form->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('type') === 'hidden' && $input->getAttribute('name') !== '_token') {
                $data[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }
        $this->from($url)->post($target, $data)->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('0.00', $ledger->summary($other)['gross_paid']);
        $this->assertSame(3, $ledger->summary($other)['remaining_credits']);
        $this->assertSame('2027-01-01', $other->fresh()->expiration_date->toDateString());
        match ($action) {
            'payments' => $this->assertDatabaseHas('payment_records', ['student_package_id' => $selected->id, 'amount_paid' => '7.00', 'transaction_reference' => 'MANUAL-7', 'notes' => 'Private note']),
            'credits' => $this->assertDatabaseHas('session_ledger_entries', ['student_package_id' => $selected->id, 'credit_change' => -1, 'description' => 'Selected correction']),
            'expiration' => $this->assertSame('2027-02-01', $selected->fresh()->expiration_date->toDateString()),
            'refunds' => $this->assertDatabaseHas('payment_refunds', ['student_package_id' => $selected->id, 'payment_record_id' => $payment->id, 'amount_refunded' => '5.00']),
        };
        if ($action !== 'expiration') {
            $this->post($target, $data)->assertStatus(409);
        }
    }

    #[DataProvider('financialActions')]
    public function test_foreign_financial_actions_are_rejected(string $action): void
    {
        $student = Student::factory()->verified()->create();
        $foreign = $this->package(Student::factory()->verified()->create(), 'Foreign');
        $method = PaymentMethod::factory()->create();
        $payment = app(StudentLedgerService::class)->recordPayment($foreign, '30.00', (string) Str::uuid(), null);
        $routes = ['payments' => 'admin.students.payments.store', 'credits' => 'admin.students.credits.adjust', 'expiration' => 'admin.students.packages.validity', 'refunds' => 'admin.students.refunds.store'];
        $data = ['amount_paid' => '5.00', 'payment_method' => $method->name, 'payment_idempotency_key' => (string) Str::uuid(), 'credit_change' => 1, 'description' => 'No', 'credit_idempotency_key' => (string) Str::uuid(), 'expiration_date' => '2027-02-01', 'previous_expiration_date' => '2027-01-01', 'reason' => 'No', 'amount_refunded' => '5.00', 'refund_idempotency_key' => (string) Str::uuid()];
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']), 'web')->post(route($routes[$action], [$student->id, $action === 'refunds' ? $payment->id : $foreign->id]), $data)->assertNotFound();
        $this->assertSame('30.00', app(StudentLedgerService::class)->summary($foreign)['gross_paid']);
        $this->assertDatabaseMissing('payment_refunds', ['payment_record_id' => $payment->id]);
    }

    public function test_assistant_cannot_view_finances_or_write(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->package($student, 'Hidden financial package');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']), 'web');
        $this->get(route('admin.students.show', ['student' => $student->id, 'package_id' => $package->id, 'billing_tab' => 'payments']))->assertOk()->assertDontSee('Hidden financial package')->assertDontSee('data-package-panel', false)->assertDontSee('Open Full Cashier View')->assertDontSee('data-copy-student', false);
        foreach (['admin.students.payments.store', 'admin.students.credits.adjust', 'admin.students.packages.validity'] as $route) {
            $this->post(route($route, [$student->id, $package->id]), [])->assertForbidden();
        }
        $this->get(route('admin.billing.cashier', ['student_id' => $student->id]))->assertForbidden();
    }

    public function test_summary_query_count_does_not_grow_with_packages(): void
    {
        $student = Student::factory()->verified()->create();
        $first = $this->package($student, 'First');
        $presentation = app(StudentPackagePresentation::class);
        DB::enableQueryLog();
        $presentation->forStudent($student->id, $first->id, 'all');
        $singleCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 5) as $number) {
            $this->package($student, 'Independent '.$number);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $presentation->forStudent($student->id, $first->id, 'all');
        $multipleCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(6, $result['packages']);
        $this->assertSame($singleCount, $multipleCount);
        $this->assertSame(8, $multipleCount);
    }

    private function package(Student $student, string $name): StudentPackage
    {
        return app(StudentLedgerService::class)->createPackage($student, $name, 3, '60.00', '0.00', 'USD', '2027-01-01', (string) Str::uuid(), entitlementCode: 'one_hour');
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
