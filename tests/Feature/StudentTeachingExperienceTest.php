<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormVersion;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningMilestone;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\LessonFeedback;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentErrorLog;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\TeachingTag;
use App\Domains\Students\Models\TutorPreparation;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Students\Services\TeachingRecordService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentTeachingExperienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function staffRoles(): array
    {
        return ['super admin' => ['super_admin', true], 'admin' => ['admin', true], 'assistant' => ['assistant', false]];
    }

    #[DataProvider('staffRoles')]
    public function test_teaching_permission_matches_lesson_workspace_roles(string $role, bool $allowed): void
    {
        $staff = AdministratorFactory::new()->create(['role' => $role]);
        $student = Student::factory()->verified()->create();

        $this->assertSame($allowed, Gate::forUser($staff)->allows('manageTeaching', $student));
    }

    public function test_assistant_cannot_read_or_write_teaching_but_retains_existing_educational_notes_permission(): void
    {
        $student = Student::factory()->verified()->create();
        $note = StudentBin::factory()->create(['student_id' => $student->id]);
        $staff = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($staff, 'web');

        $this->get(route('admin.students.teaching', $student))->assertForbidden();
        $this->post(route('admin.students.teaching.store', [$student, 'preparation']), ['body' => 'Private'])->assertForbidden();
        $this->assertTrue(Gate::forUser($staff)->allows('view', $note));
        $this->assertDatabaseCount('tutor_preparations', 0);
    }

    public function test_suspended_staff_and_merged_students_cannot_use_teaching_workspace(): void
    {
        $student = Student::factory()->verified()->create();
        $staff = AdministratorFactory::new()->create(['suspended_at' => now()]);
        $this->assertFalse(Gate::forUser($staff)->allows('manageTeaching', $student));
        $student->delete();
        $staff->suspended_at = null;
        $this->assertFalse(Gate::forUser($staff)->allows('manageTeaching', $student));
    }

    public static function teachingKinds(): array
    {
        return [
            'homework' => ['homework', ['title' => 'Practice vowels', 'instructions' => 'Read aloud', 'assigned_date' => '2026-10-05', 'due_date' => '2026-10-12', 'status' => 'assigned', 'student_visible' => 1], 'homeworks'],
            'plan' => ['plan', ['title' => 'Conversation', 'goals' => 'Order food', 'focus_areas' => 'Questions', 'current_level' => 'Beginner', 'start_date' => '2026-10-05', 'status' => 'active', 'student_visible' => 1], 'learning_plans'],
            'preparation' => ['preparation', ['body' => 'Prepare vowel cards'], 'tutor_preparations'],
            'resource' => ['resource', ['instructions' => 'Review these words', 'student_visible' => 1], 'resource_assignments'],
            'error' => ['error', ['category' => 'grammar', 'mistake' => 'Verb agreement', 'correction' => 'Match the subject', 'status' => 'practising', 'student_visible' => 1], 'student_error_logs'],
            'tag' => ['tag', ['label' => 'Exam Prep', 'student_visible' => 1], 'teaching_tags'],
        ];
    }

    #[DataProvider('teachingKinds')]
    public function test_staff_save_teaching_records_with_student_and_optional_lesson(string $kind, array $data, string $table): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $student->id]);
        $staff = AdministratorFactory::new()->create();
        if ($kind === 'resource') {
            $data['resource_id'] = ResourceFactory::new()->create()->id;
        }

        $this->actingAs($staff, 'web')->post(route('admin.students.teaching.store', [$student, $kind]), $data + ['booking_id' => $lesson->id, 'student_id' => 99999])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas($table, ['student_id' => $student->id, 'created_by' => $staff->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_teaching_'.$kind.'_saved', 'entity_id' => $student->id]);
        if ($kind !== 'plan') {
            $this->assertDatabaseHas($table, ['booking_id' => $lesson->id]);
        }
    }

    public function test_staff_cannot_attach_a_foreign_lesson_or_edit_a_foreign_record(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $other->id]);
        $homework = Homework::factory()->create(['student_id' => $other->id, 'title' => 'Original title']);
        $staff = AdministratorFactory::new()->create();
        $data = self::teachingKinds()['homework'][1];
        $this->actingAs($staff, 'web');

        $this->post(route('admin.students.teaching.store', [$student, 'homework']), $data + ['booking_id' => $lesson->id])->assertNotFound();
        $this->post(route('admin.students.teaching.store', [$student, 'homework']), $data + ['record_id' => $homework->id])->assertNotFound();
        $this->assertSame('Original title', $homework->fresh()->title);
        $this->assertDatabaseCount('homeworks', 1);
    }

    public function test_homework_cannot_reference_a_material_from_another_lesson(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $student->id]);
        $otherLesson = Booking::factory()->create(['student_id' => $student->id]);
        $material = LessonMaterial::factory()->create(['booking_id' => $otherLesson->id]);
        $this->actingAs(AdministratorFactory::new()->create(), 'web');

        $this->post(route('admin.students.teaching.store', [$student, 'homework']), self::teachingKinds()['homework'][1] + ['booking_id' => $lesson->id, 'lesson_material_id' => $material->id])->assertNotFound();
        $this->assertDatabaseCount('homeworks', 0);
    }

    public static function invalidHomework(): array
    {
        return ['empty title' => ['title', ''], 'invalid date' => ['assigned_date', 'not-a-date'], 'earlier due date' => ['due_date', '2026-10-01'],
            'unknown status' => ['status', 'published'], 'unsafe link' => ['url', 'javascript:alert(1)'], 'invalid visibility' => ['student_visible', 'yes']];
    }

    #[DataProvider('invalidHomework')]
    public function test_invalid_homework_never_writes(string $field, mixed $value): void
    {
        $student = Student::factory()->verified()->create();
        $data = self::teachingKinds()['homework'][1];
        $data[$field] = $value;

        $this->actingAs(AdministratorFactory::new()->create(), 'web')->post(route('admin.students.teaching.store', [$student, 'homework']), $data)
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('homeworks', 0);
    }

    public function test_student_sees_only_shared_owned_teaching_and_authoritative_progress(): void
    {
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'completed', 'title' => 'Shared homework']);
        Homework::factory()->create(['student_id' => $student->id, 'title' => 'Secret homework']);
        Homework::factory()->create(['student_id' => $other->id, 'student_visible' => true, 'title' => 'Foreign homework']);
        $plan = LearningPlan::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        LearningMilestone::factory()->create(['learning_plan_id' => $plan->id, 'status' => 'completed']);
        LearningMilestone::factory()->create(['learning_plan_id' => $plan->id]);
        $hiddenPlan = LearningPlan::factory()->create(['student_id' => $student->id, 'title' => 'Secret plan']);
        LearningMilestone::factory()->create(['learning_plan_id' => $hiddenPlan->id, 'status' => 'completed']);
        StudentErrorLog::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'improved']);
        StudentBin::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed']);
        TutorPreparation::factory()->create(['student_id' => $student->id, 'body' => 'Never expose preparation']);
        TeachingTag::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'label' => 'revision']);
        $this->portal($student);

        $response = $this->get(route('student.teaching.index'));
        $response->assertSeeText('Shared homework')->assertSeeText('revision')->assertDontSeeText('Secret homework')->assertDontSeeText('Foreign homework')
            ->assertDontSeeText('Secret plan')->assertDontSeeText('Never expose preparation')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['completed_lessons' => 1, 'milestones_completed' => 1, 'milestones_total' => 2, 'shared_notes' => 1, 'homework_completed' => 1, 'homework_total' => 1, 'errors_improved' => 1, 'errors_total' => 1], $response->viewData('progress'));
    }

    public function test_student_submits_homework_without_changing_staff_fields(): void
    {
        $student = Student::factory()->verified()->create();
        $homework = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'feedback' => 'Original feedback']);
        $this->portal($student);

        $this->patch(route('student.homework.update', $homework->id), ['status' => 'submitted', 'student_response' => 'My practice response', 'feedback' => 'Forged', 'student_id' => 999])->assertRedirect(route('student.teaching.index'));

        $this->assertDatabaseHas('homeworks', ['id' => $homework->id, 'student_id' => $student->id, 'status' => 'submitted', 'student_response' => 'My practice response', 'feedback' => 'Original feedback']);
    }

    public function test_completed_hidden_and_foreign_homework_cannot_be_updated(): void
    {
        $student = Student::factory()->verified()->create();
        $completed = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'completed']);
        $hidden = Homework::factory()->create(['student_id' => $student->id]);
        $foreign = Homework::factory()->create(['student_visible' => true]);
        $this->portal($student);

        $this->patch(route('student.homework.update', $completed->id), ['status' => 'in_progress'])->assertStatus(409);
        $this->patch(route('student.homework.update', $hidden->id), ['status' => 'submitted'])->assertNotFound();
        $this->patch(route('student.homework.update', $foreign->id), ['status' => 'submitted'])->assertNotFound();
        $this->patch(route('student.homework.update', $completed->id), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('assigned', $hidden->fresh()->status);
    }

    public function test_learning_milestone_completion_is_editable_and_foreign_plans_are_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00 UTC'));
        $student = Student::factory()->verified()->create();
        $plan = LearningPlan::factory()->create(['student_id' => $student->id]);
        $foreign = LearningPlan::factory()->create();
        $this->actingAs(AdministratorFactory::new()->create(), 'web');
        $route = route('admin.students.teaching.store', [$student, 'milestone']);

        $this->post($route, ['learning_plan_id' => $plan->id, 'title' => 'Introduce yourself', 'status' => 'completed'])->assertSessionHasNoErrors();
        $milestone = $plan->milestones()->sole();
        $this->assertSame('2026-10-05 12:00:00', $milestone->completed_at->toDateTimeString());
        $this->post($route, ['learning_plan_id' => $plan->id, 'record_id' => $milestone->id, 'title' => 'Introduce yourself', 'status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertNull($milestone->fresh()->completed_at);
        $this->post($route, ['learning_plan_id' => $foreign->id, 'title' => 'Wrong plan', 'status' => 'pending'])->assertNotFound();
        $this->assertDatabaseCount('learning_milestones', 1);
    }

    public function test_existing_resource_files_are_reused_without_public_gate_or_download_tracking(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resources/synthetic.pdf', '%PDF-1.4 synthetic');
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create(['external_url' => null, 'file_path' => 'resources/synthetic.pdf']);
        $assignment = ResourceAssignment::factory()->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        $this->portal($student);

        $this->get(route('student.resources.open', $assignment->id))->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(['resources/synthetic.pdf'], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('resource_requests', 0);
        $this->assertDatabaseCount('resource_downloads', 0);
    }

    public function test_resource_access_rechecks_owner_visibility_publication_and_safe_url(): void
    {
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create();
        $assignment = ResourceAssignment::factory()->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        $foreign = ResourceAssignment::factory()->create(['resource_id' => $resource->id, 'student_visible' => true]);
        $this->portal($student);

        $this->get(route('student.resources.open', $foreign->id))->assertNotFound();
        $assignment->update(['student_visible' => false]);
        $this->get(route('student.resources.open', $assignment->id))->assertNotFound();
        $assignment->update(['student_visible' => true]);
        $resource->update(['status' => 'draft']);
        $this->get(route('student.resources.open', $assignment->id))->assertNotFound();
        $resource->update(['status' => 'published', 'external_url' => 'javascript:alert(1)']);
        $this->get(route('student.resources.open', $assignment->id))->assertNotFound();
    }

    public function test_resource_review_drives_next_action_and_reset_on_reassignment(): void
    {
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create(['title' => 'Food vocabulary']);
        $assignment = ResourceAssignment::factory()->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        $this->portal($student);
        $this->get(route('student.dashboard'))->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Review your assigned resource');

        $this->patch(route('student.resources.update', $assignment->id))->assertRedirect();
        $this->assertNotNull($assignment->fresh()->reviewed_at);
        $second = ResourceFactory::new()->create();
        $actor = AdministratorFactory::new()->create();
        $this->actingAs($actor, 'web')->post(route('admin.students.teaching.store', [$student, 'resource']), ['record_id' => $assignment->id, 'resource_id' => $second->id, 'student_visible' => true])->assertSessionHasNoErrors();
        $this->assertNull($assignment->fresh()->reviewed_at);
    }

    public function test_homework_links_and_material_references_keep_their_existing_access_boundaries(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed']);
        $material = LessonMaterial::factory()->create(['booking_id' => $lesson->id, 'student_visible' => false, 'title' => 'Hidden lesson material']);
        $resource = ResourceFactory::new()->create(['title' => 'Shared practice resource']);
        $homework = Homework::factory()->create(['student_id' => $student->id, 'booking_id' => $lesson->id, 'student_visible' => true, 'lesson_material_id' => $material->id, 'resource_id' => $resource->id, 'url' => 'https://example.test/practice']);
        $this->portal($student);

        $this->get(route('student.teaching.index'))->assertDontSeeText('Hidden lesson material')->assertSeeText('Shared practice resource');
        $this->get(route('student.homework.resource', $homework->id))->assertRedirect('https://example.test/practice');
        $this->get(route('student.homework.link', $homework->id))->assertRedirect('https://example.test/practice');
        $this->get(route('student.lessons.materials.open', [$lesson->id, $material->id]))->assertNotFound();
        $homework->update(['student_visible' => false]);
        $this->get(route('student.homework.link', $homework->id))->assertNotFound();
    }

    public function test_tags_normalize_and_deduplicate_with_separate_student_and_lesson_contexts(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $student->id]);
        $actor = AdministratorFactory::new()->create();
        $service = app(TeachingRecordService::class);

        $first = $service->save($student, 'tag', ['label' => ' Exam Prep ', 'student_visible' => true], $actor->id);
        $same = $service->save($student, 'tag', ['label' => 'exam prep', 'student_visible' => true], $actor->id);
        $service->save($student, 'tag', ['label' => 'exam prep', 'student_visible' => true, 'booking_id' => $lesson->id], $actor->id);

        $this->assertSame($first->id, $same->id);
        $this->assertDatabaseCount('teaching_tags', 2);
    }

    public function test_next_action_prioritizes_questionnaire_then_homework(): void
    {
        $student = Student::factory()->verified()->create();
        $form = Form::query()->create(['title' => 'Learning profile', 'slug' => 'profile', 'status' => 'published', 'is_mandatory' => true, 'created_by' => AdministratorFactory::new()->create()->id]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1, 'status' => 'published', 'published_at' => now()->subMinute()]);
        $form->update(['published_version_id' => $version->id]);
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        $this->portal($student);

        $this->get(route('student.dashboard'))->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Finish your questionnaire' && $action['detail'] === 'Learning profile');
        $form->update(['status' => 'archived']);
        $this->get(route('student.dashboard'))->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Continue your homework');
    }

    public function test_booking_next_action_requires_one_compatible_allocation_not_pooled_or_legacy_units(): void
    {
        $student = Student::factory()->verified()->create();
        $type = SessionType::query()->create(['title' => 'Pair lesson', 'slug' => 'pair', 'duration_minutes' => 60, 'price' => 40, 'active' => true, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 2]);
        $ledger = app(StudentLedgerService::class);
        $ledger->createPackage($student, 'First', 1, '40', '0', 'USD', null, 'first', entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Second', 1, '40', '0', 'USD', null, 'second', entitlementCode: 'one_hour');
        $this->portal($student);
        $this->get(route('student.dashboard'))->assertViewHas('canBookWithPackage', false)->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Review your learning progress');

        $type->update(['required_entitlement_units' => 1]);
        $this->get(route('student.dashboard'))->assertViewHas('canBookWithPackage', true)->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Book your next session');
    }

    public function test_notifications_are_owner_scoped_persistent_and_do_not_repeat_after_read(): void
    {
        $student = Student::factory()->verified()->create();
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        $foreign = StudentNotification::factory()->create();
        $this->portal($student);
        $this->get(route('student.dashboard'));
        $item = StudentNotification::where('student_id', $student->id)->sole();

        $this->patch(route('student.notifications.update', $item->id))->assertRedirect();
        $this->get(route('student.notifications.index'))->assertSeeText('Homework assigned')->assertDontSeeText($foreign->message);
        $this->patch(route('student.notifications.update', $foreign->id))->assertNotFound();

        $this->assertNotNull($item->fresh()->read_at);
        $this->assertSame(1, StudentNotification::where('student_id', $student->id)->count());
        $this->get(route('student.dashboard'))->assertViewHas('unreadNotifications', 0);
    }

    public function test_notifications_include_only_shared_materials_and_publish_new_lesson_events_once(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed']);
        $other = Booking::factory()->create(['status' => 'completed']);
        LessonMaterial::factory()->create(['booking_id' => $lesson->id, 'student_visible' => true]);
        LessonMaterial::factory()->create(['booking_id' => $lesson->id, 'student_visible' => false]);
        LessonMaterial::factory()->create(['booking_id' => $lesson->id, 'student_visible' => false, 'withdrawn_at' => now(), 'url' => null]);
        LessonMaterial::factory()->create(['booking_id' => $other->id, 'student_visible' => true]);
        ResourceAssignment::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        $form = Form::query()->create(['title' => 'Requested profile', 'slug' => 'requested-profile', 'status' => 'published', 'created_by' => AdministratorFactory::new()->create()->id]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1, 'status' => 'published', 'published_at' => now()->subMinute()]);
        $form->update(['published_version_id' => $version->id]);
        $this->portal($student);

        $this->get(route('student.notifications.index'))->assertOk()->assertSeeText('Lesson material published')->assertSeeText('Resource assigned')->assertSeeText('Questionnaire available');
        $this->get(route('student.notifications.index'))->assertOk();
        $counts = StudentNotification::where('student_id', $student->id)->get()->countBy('type')->all();
        $this->assertEquals(['lesson' => 1, 'material' => 1, 'resource' => 1, 'form' => 1], $counts);

        $lesson->update(['status' => 'cancelled']);
        $this->get(route('student.notifications.index'))->assertSeeText('Booking Canceled');
        $this->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(2, StudentNotification::where('student_id', $student->id)->where('type', 'lesson')->count());
        $this->assertSame(1, StudentNotification::where('student_id', $student->id)->where('type', 'material')->count());
    }

    public function test_typed_notices_identify_purchase_type_and_expiry_without_generic_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00 UTC'));
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $one = $ledger->createPackage($student, 'Short practice', 1, '40', '0', 'USD', '2026-10-10', 'one', entitlementCode: 'one_hour');
        $two = $ledger->createPackage($student, 'Long practice', 5, '40', '0', 'USD', '2026-10-12', 'two', entitlementCode: 'two_hour');
        $this->portal($student);

        $response = $this->get(route('student.dashboard'));
        $notices = $response->viewData('entitlementNotices');
        $this->assertCount(2, $notices);
        $this->assertStringContainsString('Purchase #'.$one->id, $notices[0]);
        $this->assertStringContainsString('2026-10-10', $notices[0]);
        $this->assertStringContainsString('Short practice', $notices[0]);
        $this->assertStringContainsString('Long practice', $notices[1]);
        $this->assertSame(2, StudentNotification::where('student_id', $student->id)->where('type', 'entitlement')->count());
    }

    public function test_private_feedback_accepts_only_owned_completed_lessons_and_upserts_once(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed']);
        $foreign = Booking::factory()->create(['status' => 'completed']);
        $future = Booking::factory()->create(['student_id' => $student->id]);
        $this->portal($student);

        $this->post(route('student.lessons.feedback', $lesson->id), ['rating' => 4, 'comment' => 'Private comment'])->assertRedirect();
        $this->post(route('student.lessons.feedback', $lesson->id), ['rating' => 5, 'comment' => 'Updated private comment'])->assertRedirect();
        $this->post(route('student.lessons.feedback', $foreign->id), ['rating' => 4])->assertNotFound();
        $this->post(route('student.lessons.feedback', $future->id), ['rating' => 4])->assertNotFound();
        $this->post(route('student.lessons.feedback', $lesson->id), ['rating' => 6])->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('lesson_feedback', 1);
        $this->assertDatabaseHas('lesson_feedback', ['booking_id' => $lesson->id, 'student_id' => $student->id, 'rating' => 5, 'comment' => 'Updated private comment']);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_teaching_text_is_escaped_in_student_and_staff_contexts(): void
    {
        $student = Student::factory()->verified()->create();
        $dangerous = '<script>alert("teaching")</script>';
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'title' => $dangerous, 'instructions' => $dangerous, 'feedback' => $dangerous, 'student_response' => $dangerous]);
        LearningPlan::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'goals' => $dangerous, 'notes' => $dangerous]);
        StudentErrorLog::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'mistake' => $dangerous, 'correction' => $dangerous, 'notes' => $dangerous]);
        TutorPreparation::factory()->create(['student_id' => $student->id, 'body' => $dangerous]);
        $this->portal($student);

        $this->get(route('student.teaching.index'))->assertSee($dangerous)->assertDontSee($dangerous, false);
        $this->actingAs(AdministratorFactory::new()->create(), 'web')->get(route('admin.students.teaching', $student))->assertSee($dangerous)->assertDontSee($dangerous, false);
    }

    public function test_merge_preserves_and_reassigns_all_teaching_history_and_privacy_redacts_content(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $lesson = Booking::factory()->create(['student_id' => $secondary->id, 'status' => 'completed']);
        $homework = Homework::factory()->create(['student_id' => $secondary->id, 'booking_id' => $lesson->id, 'student_visible' => true, 'student_response' => 'Private response', 'url' => 'https://example.test/private']);
        $plan = LearningPlan::factory()->create(['student_id' => $secondary->id, 'student_visible' => true]);
        $milestone = LearningMilestone::factory()->create(['learning_plan_id' => $plan->id]);
        $preparation = TutorPreparation::factory()->create(['student_id' => $secondary->id, 'booking_id' => $lesson->id]);
        $resource = ResourceFactory::new()->create();
        $assignment = ResourceAssignment::factory()->create(['student_id' => $secondary->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        $error = StudentErrorLog::factory()->create(['student_id' => $secondary->id, 'student_visible' => true]);
        $tag = TeachingTag::factory()->create(['student_id' => $secondary->id, 'student_visible' => true]);
        $feedback = LessonFeedback::factory()->create(['student_id' => $secondary->id, 'booking_id' => $lesson->id]);
        StudentNotification::factory()->create(['student_id' => $secondary->id]);
        $createdAt = $homework->created_at->toDateTimeString();

        app(StudentMergeService::class)->merge($primary->id, $secondary->id);
        foreach ([$homework, $plan, $preparation, $assignment, $error, $tag, $feedback] as $record) {
            $this->assertSame($primary->id, $record->fresh()->student_id);
        }
        $this->assertSame($createdAt, $homework->fresh()->created_at->toDateTimeString());
        $this->assertSame($plan->id, $milestone->fresh()->learning_plan_id);
        $this->assertSame($primary->id, StudentNotification::sole()->student_id);

        app(StudentPrivacyService::class)->anonymize($primary->id);
        $this->assertDatabaseHas('homeworks', ['id' => $homework->id, 'instructions' => '[redacted]', 'student_response' => null, 'url' => null, 'student_visible' => false]);
        $this->assertSame('[redacted]', $preparation->fresh()->body);
        $this->assertSame('Redacted milestone', $milestone->fresh()->title);
        $this->assertNull($feedback->fresh()->comment);
        $this->assertNull($feedback->fresh()->rating);
        $this->assertDatabaseCount('student_notifications', 0);
        $this->assertModelExists($lesson);
        $this->assertModelExists($resource);
    }

    public function test_portal_batches_teaching_relationships_instead_of_querying_per_item(): void
    {
        $student = Student::factory()->verified()->create();
        $plan = LearningPlan::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        LearningMilestone::factory()->count(8)->create(['learning_plan_id' => $plan->id]);
        $resource = ResourceFactory::new()->create();
        Homework::factory()->count(8)->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        ResourceAssignment::factory()->count(8)->create(['student_id' => $student->id, 'resource_id' => $resource->id, 'student_visible' => true]);
        $this->portal($student);
        DB::enableQueryLog();
        try {
            $this->get(route('student.teaching.index'))->assertSeeText($resource->title);
            $queries = collect(DB::getQueryLog());
            $this->assertCount(2, $queries->filter(fn (array $query): bool => preg_match('/^select (?:(?!\\bfrom\\b).)*\\bfrom `resources`/i', $query['query']) === 1));
            $this->assertCount(1, $queries->filter(fn (array $query): bool => str_starts_with($query['query'], 'select') && str_contains($query['query'], 'from `learning_milestones`')));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_guest_and_suspended_student_cannot_access_learning_or_submit_feedback(): void
    {
        $this->get(route('student.teaching.index'))->assertRedirect(route('student.login'));
        $student = Student::factory()->verified()->create(['suspended_at' => now()]);
        $this->portal($student);
        $this->get(route('student.teaching.index'))->assertRedirect(route('student.login'));
    }

    private function portal(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }
}
