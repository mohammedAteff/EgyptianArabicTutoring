<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffNoteTemplate;
use App\Domains\Administration\Services\OperationalReasonCatalog;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Models\ReasonCode;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\TeachingTag;
use App\Domains\Students\Services\StudentLedgerService;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultStaffWorkflowExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_returns_payment_references_only_to_authorized_staff(): void
    {
        $payment = PaymentRecord::factory()->create(['transaction_reference' => 'PRIVATE-PAYMENT-NEEDLE']);
        $resource = ResourceFactory::new()->create(['title' => 'PRIVATE-PAYMENT-NEEDLE resource']);
        $this->actingAs(Administrator::factory()->create(), 'web')->getJson(route('admin.search', ['q' => 'PRIVATE-PAYMENT-NEEDLE']))->assertOk()->assertJsonFragment(['kind' => 'payment'])->assertJsonFragment(['kind' => 'resource']);
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']), 'web')->getJson(route('admin.search', ['q' => 'PRIVATE-PAYMENT-NEEDLE']))->assertOk()->assertJsonMissing(['kind' => 'payment'])->assertJsonMissing(['kind' => 'resource'])->assertDontSee('PRIVATE-PAYMENT-NEEDLE');
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.search', ['q' => 'PAY-'.$payment->id]))->assertOk()->assertSee('PAY-'.$payment->id);
    }

    public function test_palette_student_actions_are_authorized_and_clipboard_values_are_escaped(): void
    {
        $student = Student::factory()->verified()->create(['first_name' => '<script>Needle</script>', 'name_normalized' => 'needle student']);
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.search', ['q' => 'needle']))->assertOk()->assertSee('Record Payment')->assertDontSee('<script>Needle</script>', false)->assertSee('&lt;script&gt;Needle&lt;/script&gt;', false);
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']), 'web')->getJson(route('admin.search', ['q' => 'needle']))->assertOk()->assertJsonFragment(['label' => 'Add Note'])->assertJsonMissing(['label' => 'Record Payment'])->assertJsonMissing(['label' => 'Adjust Credits']);
    }

    public function test_pinned_roster_is_per_staff_and_back_link_retains_filters_and_page(): void
    {
        $actor = Administrator::factory()->create();
        $a = Student::factory()->verified()->create(['first_name' => 'Pinned', 'name_normalized' => 'pinned student']);
        $b = Student::factory()->verified()->create(['first_name' => 'Other', 'name_normalized' => 'other student']);
        foreach ([$a, $b] as $student) {
            Booking::factory()->create(['student_id' => $student->id]);
        }
        $this->actingAs($actor, 'web')->post(route('admin.students.pin', $a))->assertSessionHasNoErrors();
        $this->get(route('admin.students.index', ['pinned' => 1, 'q' => 'pinned', 'page' => 1]))->assertOk()->assertViewHas('students', fn ($rows) => $rows->pluck('id')->all() === [$a->id]);
        $this->get(route('admin.students.show', $a))->assertOk()->assertViewHas('studentListUrl', fn (string $url): bool => str_contains($url, 'pinned=1') && str_contains($url, 'q=pinned') && str_contains($url, 'page=1'));
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.students.index', ['pinned' => 1]))->assertOk()->assertViewHas('students', fn ($rows) => $rows->isEmpty());
    }

    public function test_safe_bulk_resource_and_tag_actions_are_atomic_and_do_not_create_duplicate_assignments(): void
    {
        $students = Student::factory()->verified()->count(2)->create();
        $resource = ResourceFactory::new()->create();
        $this->actingAs(Administrator::factory()->create(), 'web');
        $data = ['student_ids' => $students->modelKeys(), 'action' => 'resource', 'resource_id' => $resource->id, 'student_visible' => true];
        foreach ([1, 2] as $attempt) {
            $this->post(route('admin.students.bulk-teaching'), $data)->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('resource_assignments', 2);
        $this->post(route('admin.students.bulk-teaching'), ['student_ids' => $students->modelKeys(), 'action' => 'tag', 'label' => 'Revision', 'student_visible' => false])->assertSessionHasNoErrors();
        $this->assertSame(2, TeachingTag::query()->where('label', 'revision')->count());
        $students->last()->update(['suspended_at' => now('UTC')]);
        $this->post(route('admin.students.bulk-teaching'), ['student_ids' => $students->modelKeys(), 'action' => 'tag', 'label' => 'Failed batch', 'student_visible' => false])->assertNotFound();
        $this->assertSame(0, TeachingTag::query()->where('label', 'failed batch')->count());
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']), 'web')->postJson(route('admin.students.bulk-teaching'), $data)->assertForbidden();
    }

    public function test_templates_and_snippets_are_available_in_their_staff_workflows(): void
    {
        $student = Student::factory()->verified()->create();
        StaffNoteTemplate::create(['title' => 'Homework template', 'category' => 'homework', 'body' => 'Repeat the vocabulary aloud']);
        StaffNoteTemplate::create(['title' => 'Renewal snippet', 'category' => 'snippet', 'body' => 'Please contact me about your next package.']);
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.staff-note-templates.library'))->assertOk()->assertSee('Copy Snippet')->assertSee('Renewal snippet');
        $this->get(route('admin.students.teaching', $student))->assertOk()->assertSee('Homework template')->assertSee('data-staff-template-picker', false);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])
            ->get(route('student.dashboard'))->assertOk()->assertDontSee('Renewal snippet')->assertDontSee('Homework template');
    }

    public function test_summary_copy_blocks_keep_currencies_separate_and_are_hidden_from_assistants(): void
    {
        $student = Student::factory()->verified()->create();
        PaymentRecord::factory()->create(['student_package_id' => app(StudentLedgerService::class)->createPackage($student, 'Summary package', 2, '80.00', '0.00', 'USD', null, Str::uuid()->toString(), entitlementCode: 'one_hour')->id]);
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.students.show', $student))->assertOk()->assertSee('Copy Billing Summary')->assertSee('Copy Upcoming Lesson')->assertViewHas('copyBlocks', fn (array $blocks): bool => str_contains($blocks['Billing Summary'], 'USD'));
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']), 'web')->get(route('admin.students.show', $student))->assertOk()->assertViewHas('copyBlocks', [])->assertDontSee('Copy Billing Summary');
    }

    public function test_sensitive_actions_validate_reason_category_and_persist_code_in_immutable_audit(): void
    {
        $student = Student::factory()->verified()->create();
        $package = app(StudentLedgerService::class)->createPackage($student, 'Reason package', 2, '80.00', '0.00', 'USD', null, Str::uuid()->toString(), entitlementCode: 'one_hour');
        $allocation = $package->entitlements()->sole();
        $this->actingAs(Administrator::factory()->create(), 'web');
        $payload = ['allocation_id' => $allocation->id, 'credit_change' => 1, 'description' => 'Service recovery text', 'credit_idempotency_key' => Str::uuid()->toString(), 'reason_code' => 'duplicate_payment'];
        $this->postJson(route('admin.students.credits.adjust', [$student, $package]), $payload)->assertUnprocessable()->assertJsonValidationErrors('reason_code');
        $this->post(route('admin.students.credits.adjust', [$student, $package]), array_replace($payload, ['reason_code' => 'service_recovery']))->assertSessionHasNoErrors();
        $audit = AuditLog::query()->where('action', 'student_credit_adjusted')->sole();
        $this->assertSame('service_recovery', $audit->new_data['reason_code']);
        $this->assertSame('Service recovery text', $audit->new_data['description']);
    }

    public function test_disabling_a_reason_does_not_reactivate_it_via_defaults(): void
    {
        $reason = ReasonCode::query()->create(['category' => 'courtesy_credit', 'code' => 'service_recovery', 'label' => 'Service recovery', 'is_active' => false]);
        $this->assertArrayNotHasKey('service_recovery', OperationalReasonCatalog::forCategory('courtesy_credit'));
        $this->assertArrayHasKey('promotion', OperationalReasonCatalog::forCategory('courtesy_credit'));
        $this->actingAs(Administrator::factory()->create(['role' => 'super_admin']), 'web')->get(route('admin.reason-codes.index'))->assertOk()->assertSee('Operational Reason Categories');
        $this->actingAs(Administrator::factory()->create(['role' => 'admin']), 'web')->patchJson(route('admin.reason-codes.update', $reason), ['label' => 'Changed', 'is_active' => true, 'sort_order' => 0])->assertForbidden();
    }
}
