<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\StaffBin;
use App\Domains\Administration\Models\StaffNotePreference;
use App\Domains\Administration\Models\StaffRecentView;
use App\Domains\Administration\Models\StaffSavedView;
use App\Domains\Administration\Models\StaffTask;
use App\Domains\Administration\Services\OperationsReadModel;
use App\Domains\Administration\Services\StaffRecentViewService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormVersion;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentOperationalAlert;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffOperationsProductivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function roles(): array
    {
        return [['super_admin'], ['admin'], ['assistant']];
    }

    #[DataProvider('roles')]
    public function test_shared_pins_follow_super_admin_authority_and_are_visible_to_all_staff(string $role): void
    {
        $actor = AdministratorFactory::new()->create(['role' => $role]);
        $owner = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $note = StaffBin::factory()->create(['author_id' => $owner->id]);
        $this->actingAs($actor, 'web');
        $response = $this->patch(route('admin.staff-bins.shared-pin', $note), ['pinned' => true, 'pinned_by' => $actor->id]);
        if ($role !== 'super_admin') {
            $response->assertForbidden();
            $this->assertFalse($note->fresh()->pinned);

            return;
        }
        $response->assertRedirect();
        $this->assertSame($actor->id, $note->fresh()->pinned_by);
        $this->assertNotNull($note->fresh()->pinned_at);
        $this->actingAs($owner, 'web')->get(route('admin.staff-bins.index', ['collection' => 'shared']))->assertSeeText($note->title)->assertSeeText('Pinned for all staff');
        $this->patch(route('admin.staff-bins.shared-pin', $note), ['pinned' => false])->assertRedirect();
        $this->assertNull($note->fresh()->pinned_by);
        $this->assertNull($note->fresh()->pinned_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_note_shared_pin_updated', 'entity_id' => $note->id]);
    }

    #[DataProvider('roles')]
    public function test_personal_pins_and_favorites_are_independent_unique_and_owner_scoped(string $role): void
    {
        $actor = AdministratorFactory::new()->create(['role' => $role]);
        $other = AdministratorFactory::new()->create();
        $note = StaffBin::factory()->create(['title' => 'Isolated personal note']);
        $this->actingAs($actor, 'web');
        foreach (['pinned', 'favorite', 'pinned'] as $flag) {
            $this->patch(route('admin.staff-bins.personal', $note), ['flag' => $flag, 'enabled' => true, 'administrator_id' => $other->id])->assertRedirect();
        }
        $preference = StaffNotePreference::query()->sole();
        $this->assertSame($actor->id, $preference->administrator_id);
        $this->assertTrue($preference->pinned);
        $this->assertTrue($preference->favorite);
        $this->assertFalse($note->fresh()->pinned);
        foreach (['mine', 'favorites'] as $collection) {
            $this->get(route('admin.staff-bins.index', ['collection' => $collection]))->assertOk()->assertSeeText($note->title);
            $this->actingAs($other, 'web')->get(route('admin.staff-bins.index', ['collection' => $collection]))->assertOk()->assertDontSeeText($note->title);
            $this->actingAs($actor, 'web');
        }
        $this->patch(route('admin.staff-bins.personal', $note), ['flag' => 'pinned', 'enabled' => false])->assertRedirect();
        $this->assertTrue($preference->fresh()->favorite);
        $this->assertFalse($preference->fresh()->pinned);
        $this->get(route('admin.staff-bins.index', ['collection' => 'shared']))->assertDontSeeText($note->title);
    }

    public function test_note_filters_compose_and_pinned_notes_sort_prominently_without_leaking_other_personal_flags(): void
    {
        $actor = AdministratorFactory::new()->create();
        $own = StaffBin::factory()->create(['author_id' => $actor->id, 'title' => 'Alpha searchable', 'pinned' => true, 'pinned_by' => $actor->id, 'pinned_at' => now()]);
        $other = StaffBin::factory()->create(['title' => 'Beta searchable']);
        StaffNotePreference::factory()->create(['administrator_id' => $actor->id, 'staff_bin_id' => $own->id, 'favorite' => true]);
        $this->actingAs($actor, 'web')->get(route('admin.staff-bins.index', ['q' => 'searchable', 'owner' => 'mine', 'collection' => 'favorites']))->assertSeeText($own->title)->assertDontSeeText($other->title);
        $this->get(route('admin.staff-bins.index'))->assertSeeInOrder([$own->title, $other->title]);
        $this->get(route('admin.staff-bins.index', ['sort' => 'id; drop table staff_bins']))->assertSessionHasErrors('sort');
        $this->patch(route('admin.staff-bins.personal', $own), ['flag' => 'administrator_id', 'enabled' => true])->assertSessionHasErrors('flag');
        $own->delete();
        $this->patch(route('admin.staff-bins.personal', $own), ['flag' => 'pinned', 'enabled' => true])->assertNotFound();
    }

    public function test_alert_lifecycle_is_distinct_private_to_staff_and_parent_bound(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $this->actingAs($actor, 'web')->post(route('admin.student-alerts.store', $student), ['title' => 'Payment scheduling alert', 'body' => '<script>alert(1)</script>', 'student_id' => $other->id])->assertRedirect();
        $alert = StudentOperationalAlert::query()->sole();
        $this->assertSame($student->id, $alert->student_id);
        $this->assertDatabaseCount('student_bins', 0);
        $this->assertDatabaseCount('staff_bins', 0);
        $this->get(route('admin.students.show', $student))->assertSeeText($alert->title)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->patch(route('admin.student-alerts.update', [$other, $alert]), ['status' => 'resolved'])->assertNotFound();
        foreach (['resolved', 'archived', 'active'] as $status) {
            $this->patch(route('admin.student-alerts.update', [$student, $alert]), ['status' => $status])->assertRedirect();
            $this->assertSame($status, $alert->fresh()->status);
            $this->assertSame($status === 'resolved', $alert->fresh()->resolved_at !== null);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_operational_alert_updated', 'entity_id' => $alert->id]);
        $this->actingAs($actor, 'web')->post(route('admin.logout'));
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()])->get(route('student.dashboard'))->assertOk()->assertDontSeeText($alert->title);
    }

    public function test_assistant_reads_alerts_without_management_or_billing_actions(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'assistant']);
        $alert = StudentOperationalAlert::factory()->create(['title' => 'Scheduling preference']);
        $this->actingAs($actor, 'web')->get(route('admin.students.show', $alert->student_id))->assertOk()->assertSeeText($alert->title)->assertDontSeeText('Pin operational alert')->assertDontSeeText('Open Cashier')->assertSeeText('Create staff task');
        $this->post(route('admin.student-alerts.store', $alert->student_id), ['title' => 'Denied', 'body' => 'Denied'])->assertForbidden();
        $this->patch(route('admin.student-alerts.update', [$alert->student_id, $alert->id]), ['status' => 'resolved'])->assertForbidden();
    }

    public function test_admin_creates_assigns_and_edits_tasks_with_validated_references_and_completion_lifecycle(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $assignee = AdministratorFactory::new()->create(['role' => 'assistant']);
        $student = Student::factory()->verified()->create();
        $payload = ['title' => '<script>Task</script>', 'description' => '<img src=x onerror=alert(1)>', 'student_id' => $student->id, 'assignee_id' => $assignee->id, 'due_date' => '2026-10-05', 'status' => 'open', 'priority' => 'high', 'created_by' => $assignee->id];
        $this->actingAs($actor, 'web')->post(route('admin.tasks.store'), $payload)->assertRedirect();
        $task = StaffTask::query()->sole();
        $this->assertSame($actor->id, $task->created_by);
        $this->get(route('admin.tasks.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>Task</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->patch(route('admin.tasks.update', $task), [...$payload, 'status' => 'completed'])->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->patch(route('admin.tasks.update', $task), [...$payload, 'status' => 'in_progress'])->assertRedirect();
        $this->assertNull($task->fresh()->completed_at);
        $assignee->update(['suspended_at' => now()]);
        $this->post(route('admin.tasks.store'), $payload)->assertSessionHasErrors('assignee_id');
        $this->post(route('admin.tasks.store'), [...$payload, 'assignee_id' => $actor->id, 'student_id' => 999999])->assertSessionHasErrors('student_id');
        $student->update(['identity_status' => 'merged']);
        $this->post(route('admin.tasks.store'), [...$payload, 'assignee_id' => $actor->id])->assertSessionHasErrors('student_id');
    }

    public function test_assistant_tasks_are_assignment_scoped_and_only_status_can_change(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'assistant']);
        $other = AdministratorFactory::new()->create(['role' => 'assistant']);
        $own = StaffTask::factory()->create(['assignee_id' => $actor->id, 'title' => 'My assigned work']);
        $foreign = StaffTask::factory()->create(['assignee_id' => $other->id, 'title' => 'Other staff private work']);
        $this->actingAs($actor, 'web')->get(route('admin.tasks.index', ['owner' => 'all']))->assertSeeText($own->title)->assertDontSeeText($foreign->title);
        $this->patch(route('admin.tasks.update', $foreign), ['status' => 'completed'])->assertForbidden();
        $this->patch(route('admin.tasks.update', $own), ['status' => 'completed', 'title' => 'Override', 'assignee_id' => $other->id])->assertRedirect();
        $this->assertSame('My assigned work', $own->fresh()->title);
        $this->assertSame($actor->id, $own->fresh()->assignee_id);
        $this->assertNotNull($own->fresh()->completed_at);
        $payload = ['title' => 'Self follow-up', 'assignee_id' => $other->id, 'status' => 'open', 'priority' => 'normal'];
        $this->post(route('admin.tasks.store'), $payload)->assertForbidden();
        $this->post(route('admin.tasks.store'), [...$payload, 'assignee_id' => $actor->id])->assertRedirect();
    }

    public static function savedSections(): array
    {
        return [['students', ['status' => 'verified', 'credits' => 'none'], 'admin.students.index'], ['staff_notes', ['owner' => 'mine', 'collection' => 'favorites'], 'admin.staff-bins.index'], ['tasks', ['priority' => 'high', 'due' => 'overdue'], 'admin.tasks.index']];
    }

    #[DataProvider('savedSections')]
    public function test_saved_views_apply_allowlisted_filters_and_cannot_be_accessed_across_users(string $section, array $filters, string $destination): void
    {
        $actor = AdministratorFactory::new()->create();
        $other = AdministratorFactory::new()->create();
        $this->actingAs($actor, 'web')->post(route('admin.saved-views.store'), ['section' => $section, 'name' => 'My view', 'filters' => $filters, 'administrator_id' => $other->id])->assertRedirect(route($destination, $filters));
        $view = StaffSavedView::query()->sole();
        $this->assertSame($actor->id, $view->administrator_id);
        $this->post(route('admin.saved-views.store'), ['section' => $section, 'name' => 'My view', 'filters' => $filters])->assertRedirect();
        $this->assertDatabaseCount('staff_saved_views', 1);
        $this->get(route('admin.saved-views.apply', $view->id))->assertRedirect(route($destination, $filters));
        $this->actingAs($other, 'web')->get(route('admin.saved-views.apply', $view->id))->assertNotFound();
        $this->delete(route('admin.saved-views.destroy', $view->id))->assertNotFound();
        $this->get(route($destination))->assertDontSeeText('My view');
        $this->actingAs($actor, 'web')->delete(route('admin.saved-views.destroy', $view->id))->assertRedirect();
        $this->assertDatabaseCount('staff_saved_views', 0);
    }

    public function test_saved_views_reject_unknown_nested_filters_unsafe_sections_invalid_values_and_bound_storage(): void
    {
        $actor = AdministratorFactory::new()->create();
        $this->actingAs($actor, 'web');
        $this->post(route('admin.saved-views.store'), ['section' => 'tasks', 'name' => 'Unsafe', 'filters' => ['sql' => 'drop table', 'status' => 'open']])->assertSessionHasErrors('filters');
        $this->post(route('admin.saved-views.store'), ['section' => 'tasks', 'name' => 'Nested', 'filters' => ['q' => ['secret' => 'payload']]])->assertSessionHasErrors('q');
        $this->post(route('admin.saved-views.store'), ['section' => 'tasks', 'name' => 'Invalid', 'filters' => ['status' => 'bogus']])->assertSessionHasErrors('status');
        $this->post(route('admin.saved-views.store'), ['section' => 'settings', 'name' => 'Denied'])->assertSessionHasErrors('section');
        $this->post(route('admin.saved-views.store'), ['section' => 'staff_notes', 'name' => '<script>view</script>'])->assertRedirect();
        $this->get(route('admin.staff-bins.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>view</script>', false);
        foreach (range(1, 20) as $number) {
            StaffSavedView::create(['administrator_id' => $actor->id, 'section' => 'tasks', 'name' => 'View '.$number, 'filters' => []]);
        }
        $this->post(route('admin.saved-views.store'), ['section' => 'tasks', 'name' => 'Overflow'])->assertSessionHasErrors('name');
    }

    public function test_recent_views_record_only_ids_after_authorized_reads_and_resolve_current_names_per_user(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'assistant']);
        $other = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        $this->actingAs($actor, 'web')->get(route('admin.students.show', $student))->assertOk();
        $this->get(route('admin.bookings.show', $booking))->assertOk();
        $this->get(route('admin.lessons.show', $booking))->assertForbidden();
        $this->get(route('admin.students.show', 999999))->assertNotFound();
        $this->assertDatabaseCount('staff_recent_views', 2);
        $this->get(route('admin.students.show', $student))->assertOk();
        $this->assertDatabaseCount('staff_recent_views', 2);
        $this->assertSame(['id', 'administrator_id', 'entity_type', 'entity_id', 'viewed_at'], array_keys(StaffRecentView::query()->first()->getAttributes()));
        $this->get(route('admin.operations.index'))->assertSeeText($student->name)->assertSeeText('Booking #'.$booking->id);
        $this->assertSame([], app(StaffRecentViewService::class)->forAdministrator($other));
        $student->delete();
        $this->assertCount(1, app(StaffRecentViewService::class)->forAdministrator($actor));
    }

    public function test_recent_views_are_bounded(): void
    {
        $actor = AdministratorFactory::new()->create();
        foreach (range(1, 35) as $id) {
            app(StaffRecentViewService::class)->record($actor, 'student', $id);
        }
        $this->assertSame(30, StaffRecentView::where('administrator_id', $actor->id)->count());
    }

    public function test_today_uses_business_day_boundaries_exact_counts_and_assistant_assignment_scope(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00 UTC'));
        $actor = AdministratorFactory::new()->create(['role' => 'assistant']);
        $student = Student::factory()->verified()->create();
        Booking::factory()->count(13)->create(['student_id' => $student->id, 'start_at_utc' => CarbonImmutable::parse('2026-10-04 22:00:00 UTC')]);
        Booking::factory()->create(['start_at_utc' => CarbonImmutable::parse('2026-10-04 20:00:00 UTC')]);
        Booking::factory()->create(['start_at_utc' => CarbonImmutable::parse('2026-10-06 07:00:00 UTC')]);
        Booking::factory()->create(['start_at_utc' => CarbonImmutable::parse('2026-10-06 07:00:00 UTC'), 'status' => 'cancelled']);
        StaffTask::factory()->create(['assignee_id' => $actor->id, 'due_date' => '2026-10-04']);
        StaffTask::factory()->create(['assignee_id' => $actor->id, 'due_date' => '2026-10-04', 'status' => 'completed']);
        StaffTask::factory()->create(['due_date' => '2026-10-04', 'title' => 'Foreign overdue secret']);
        StaffTask::factory()->create(['assignee_id' => $actor->id, 'student_id' => $student->id, 'due_date' => '2026-10-05']);
        StudentOperationalAlert::factory()->create(['student_id' => $student->id]);
        StudentOperationalAlert::factory()->create(['student_id' => $student->id, 'status' => 'resolved']);
        $data = app(OperationsReadModel::class)->forAdministrator($actor);
        $this->assertSame(13, $data['counts']['todayLessons']);
        $this->assertCount(12, $data['todayLessons']);
        $this->assertSame(1, $data['counts']['upcomingLessons']);
        $this->assertSame(1, $data['counts']['overdueTasks']);
        $this->assertSame(1, $data['counts']['followUps']);
        $this->assertSame(1, $data['counts']['activeAlerts']);
        $this->assertArrayNotHasKey('paymentFollowUps', $data);
        $this->actingAs($actor, 'web')->get(route('admin.operations.index'))->assertOk()->assertDontSeeText('Outstanding payment follow-ups')->assertDontSeeText('Foreign overdue secret')->assertDontSeeText('Cashier Hub');
    }

    public function test_today_financial_projection_uses_typed_balances_and_net_payments_excludes_expired_and_pools_only_same_type(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00 UTC'));
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $pooled = Student::factory()->verified()->create();
        $expired = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $short = $ledger->createPackage($student, 'One hour low', 1, '40', '0', 'USD', '2026-10-10', 'low', entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Two hour high', 5, '50', '0', 'USD', '2026-10-12', 'high', entitlementCode: 'two_hour');
        $ledger->createPackage($pooled, 'Small allocation', 1, '40', '0', 'USD', null, 'small', entitlementCode: 'one_hour');
        $ledger->createPackage($pooled, 'Large allocation', 3, '40', '0', 'USD', null, 'large', entitlementCode: 'one_hour');
        $ledger->createPackage($expired, 'Expired allocation', 1, '40', '0', 'USD', '2026-10-04', 'expired', entitlementCode: 'one_hour');
        $payment = PaymentRecord::factory()->create(['student_package_id' => $short->id, 'student_id' => $student->id, 'amount_paid' => '30.00']);
        PaymentRefund::query()->create(['student_id' => $student->id, 'student_package_id' => $short->id, 'payment_record_id' => $payment->id, 'amount_refunded' => '5.00', 'currency' => 'USD', 'refunded_at' => now(), 'created_at' => now(), 'idempotency_key' => 'refund-projection']);
        $data = app(OperationsReadModel::class)->forAdministrator($actor);
        $this->assertSame([$student->id], $data['lowCreditStudents']->modelKeys());
        $this->assertSame(2, $data['counts']['expiringPackages']);
        $this->assertSame('15.00', $data['paymentBalances'][$short->id]);
        $this->assertSame(1, $data['studentCredits'][$student->id]['one_hour']['available']);
        $this->assertSame(5, $data['studentCredits'][$student->id]['two_hour']['available']);
        $this->actingAs($actor, 'web')->get(route('admin.operations.index'))->assertOk()->assertSeeText('USD 15.00');
    }

    public function test_today_recent_forms_include_submitted_records_without_answers_and_skip_drafts_and_old_completions(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $form = Form::create(['title' => 'Follow-up questionnaire', 'slug' => 'ops-form', 'created_by' => $actor->id]);
        $version = FormVersion::create(['form_id' => $form->id, 'version_number' => 1, 'status' => 'published']);
        FormSubmission::create(['form_version_id' => $version->id, 'student_id' => $student->id, 'status' => 'submitted', 'submitted_at' => now()]);
        FormSubmission::create(['form_version_id' => $version->id, 'student_id' => Student::factory()->verified()->create()->id, 'status' => 'draft']);
        FormSubmission::create(['form_version_id' => $version->id, 'student_id' => Student::factory()->verified()->create()->id, 'status' => 'submitted', 'submitted_at' => now()->subDays(8)]);
        $data = app(OperationsReadModel::class)->forAdministrator($actor);
        $this->assertSame(1, $data['counts']['recentForms']);
        $this->assertFalse($data['recentForms']->first()->relationLoaded('answers'));
    }

    public function test_merge_preserves_operations_and_privacy_removes_student_details_from_tasks_alerts_and_history(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $task = StaffTask::factory()->create(['student_id' => $secondary->id, 'title' => $secondary->email, 'description' => $secondary->phone]);
        $alert = StudentOperationalAlert::factory()->create(['student_id' => $secondary->id, 'body' => $secondary->email]);
        app(StaffRecentViewService::class)->record($actor, 'student', $primary->id);
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $actor->id);
        $this->assertSame($primary->id, $task->fresh()->student_id);
        $this->assertSame($primary->id, $alert->fresh()->student_id);
        app(StudentPrivacyService::class)->anonymize($primary->id, $actor->id);
        $this->assertNull($task->fresh()->student_id);
        $this->assertNull($task->fresh()->description);
        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertSame('[redacted]', $alert->fresh()->body);
        $this->assertSame('archived', $alert->fresh()->status);
        $this->assertDatabaseCount('staff_recent_views', 0);
    }

    public function test_guest_and_suspended_staff_cannot_use_productivity_endpoints(): void
    {
        $this->get(route('admin.operations.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.tasks.store'))->assertRedirect(route('admin.login'));
        $actor = AdministratorFactory::new()->create(['suspended_at' => now()]);
        $this->actingAs($actor, 'web')->get(route('admin.operations.index'))->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('staff_recent_views', 0);
    }

    public function test_assistant_login_has_an_authorized_landing_and_launcher_hides_privileged_destinations(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'assistant', 'password' => 'SyntheticTestPassword123!']);
        $this->withSession(['url.intended' => route('admin.dashboard')])->post(route('admin.login.submit'), ['email' => $actor->email, 'password' => 'SyntheticTestPassword123!'])->assertRedirect(route('admin.operations.index'));
        $this->get(route('admin.operations.index'))->assertOk()->assertSeeText('Quick actions')->assertSeeText('Find student')->assertDontSee('data-quick-destination class="block rounded-lg p-3 text-sm font-semibold hover:bg-amber-50" href="'.route('admin.billing.cashier').'"', false);
    }

    public function test_contextual_booking_prefills_the_existing_manual_flow_and_preserves_permissions(): void
    {
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'America/New_York']);
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($actor, 'web')->get(route('admin.bookings.create', ['student_id' => $student->id]))->assertOk()->assertSee('value="'.$student->email.'"', false)->assertSee('value="America/New_York"', false)->assertViewHas('selectedStudent', fn (Student $selected): bool => $selected->is($student));
        $this->get(route('admin.bookings.create', ['student_id' => 999999]))->assertNotFound();
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web')->get(route('admin.bookings.create', ['student_id' => $student->id]))->assertForbidden();
    }

    public function test_operations_queries_stay_bounded_as_lesson_and_payment_rows_grow(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $start = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->startOfDay()->addHours(10)->utc();
        Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => $start]);
        $ledger = app(StudentLedgerService::class);
        $ledger->createPackage($student, 'Operational query fixture', 6, '40', '0', 'USD', null, 'query-one', entitlementCode: 'one_hour');
        $operations = app(OperationsReadModel::class);
        $operations->forAdministrator($actor);
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $operations->forAdministrator($actor);
            $smallCount = count(DB::getQueryLog());
            DB::disableQueryLog();
            Booking::factory()->count(11)->create(['student_id' => $student->id, 'start_at_utc' => $start]);
            foreach (range(2, 12) as $number) {
                $ledger->createPackage($student, 'Operational query fixture '.$number, 6, '40', '0', 'USD', null, 'query-'.$number, entitlementCode: 'one_hour');
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $data = $operations->forAdministrator($actor);
            $this->assertCount(12, $data['todayLessons']);
            $this->assertCount(12, $data['paymentFollowUps']);
            $this->assertSame($smallCount, count(DB::getQueryLog()));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
