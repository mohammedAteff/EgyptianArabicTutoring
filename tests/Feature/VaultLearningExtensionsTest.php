<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Resources\Models\ResourceCollection;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\HomeworkSubmission;
use App\Domains\Students\Models\LearningSkill;
use App\Domains\Students\Models\ResourceCollectionAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentBookmark;
use App\Domains\Students\Models\StudentPortalLogin;
use App\Domains\Students\Models\StudentProgressReport;
use App\Domains\Students\Models\StudentSkillAssessment;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\System\Services\DevelopmentDataGraph;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultLearningExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Student $student): static
    {
        return $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'student_display_timezone' => 'UTC']);
    }

    public function test_homework_retains_legacy_and_resubmission_history_with_replay_protection(): void
    {
        $student = Student::factory()->verified()->create();
        $item = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'submitted', 'student_response' => 'Original response', 'instructions' => 'Tutor instructions']);
        $key = Str::uuid()->toString();
        $this->signIn($student);
        $payload = ['status' => 'submitted', 'student_response' => 'Revised response', 'submission_key' => $key, 'instructions' => 'Attempt to replace tutor text'];
        $this->patch(route('student.homework.update', $item), $payload)->assertSessionHasNoErrors();
        $this->patch(route('student.homework.update', $item), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['Revised response', 'Original response'], $item->submissions()->pluck('body')->all());
        $this->assertSame('Tutor instructions', $item->fresh()->instructions);
        $this->patchJson(route('student.homework.update', $item), array_replace($payload, ['student_response' => 'Changed replay']))->assertUnprocessable();
        $this->get(route('student.teaching.index'))->assertOk()->assertSee('Submission History')->assertSee('Original response')->assertSee('Revised response');
        $other = Student::factory()->verified()->create();
        $this->signIn($other)->patch(route('student.homework.update', $item), $payload)->assertNotFound();
    }

    public function test_saving_a_resubmission_draft_preserves_the_previous_legacy_submission(): void
    {
        $student = Student::factory()->verified()->create();
        $item = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'submitted', 'student_response' => 'Legacy submitted answer']);
        $this->signIn($student)->patch(route('student.homework.update', $item), ['status' => 'in_progress', 'student_response' => 'Draft revision'])->assertSessionHasNoErrors();
        $this->patch(route('student.homework.update', $item), ['status' => 'submitted', 'student_response' => 'Final revision', 'submission_key' => Str::uuid()->toString()])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Legacy submitted answer', 'Final revision'], $item->submissions()->pluck('body')->all());
    }

    public function test_custom_skill_assessment_is_private_by_default_and_cannot_link_another_students_booking(): void
    {
        $student = Student::factory()->verified()->create();
        $otherBooking = Booking::factory()->create();
        $skill = LearningSkill::factory()->create(['label' => 'Conversational confidence']);
        $this->actingAs(Administrator::factory()->create(), 'web');
        $payload = ['learning_skill_id' => $skill->id, 'state' => 'developing', 'notes' => 'Private tutor observation', 'student_visible' => false];
        $this->post(route('admin.students.skills.store', $student), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.students.skills.store', $student), $payload + ['booking_id' => $otherBooking->id])->assertNotFound();
        $this->assertDatabaseCount('student_skill_assessments', 1);
        $this->post(route('admin.students.skills.store', $student), array_replace($payload, ['student_visible' => true, 'notes' => 'Shared observation']))->assertSessionHasNoErrors();
        $this->signIn($student)->get(route('student.teaching.index'))->assertOk()->assertSee('Shared observation')->assertDontSee('Private tutor observation');
    }

    public function test_assistant_cannot_create_skills_collections_or_reports(): void
    {
        $student = Student::factory()->verified()->create();
        $this->actingAs(Administrator::factory()->create(['role' => 'assistant']), 'web');
        $this->postJson(route('admin.learning-skills.store'), ['label' => 'Grammar'])->assertForbidden();
        $this->get(route('admin.resource-collections.index'))->assertForbidden();
        $this->postJson(route('admin.students.progress-reports.store', $student), [])->assertForbidden();
    }

    public function test_collections_preserve_order_assign_once_and_hide_unpublished_content(): void
    {
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id]);
        $a = ResourceFactory::new()->create(['title' => 'First sequence resource']);
        $b = ResourceFactory::new()->create(['title' => 'Second sequence resource']);
        $this->actingAs(Administrator::factory()->create(), 'web')->post(route('admin.resource-collections.store'), [
            'title' => 'Beginner starter path', 'slug' => 'beginner-path', 'description' => 'Work through these in order.', 'is_published' => true, 'resource_ids' => [$b->id, $a->id],
        ])->assertSessionHasNoErrors();
        $collection = ResourceCollection::query()->sole();
        $this->assertSame([$b->id, $a->id], $collection->resources->modelKeys());
        $this->get(route('admin.resource-collections.index'))->assertOk()->assertSee('Beginner starter path');
        foreach ([1, 2] as $attempt) {
            $this->post(route('admin.resource-collections.assign', $collection), ['student_id' => $student->id])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('resource_collection_assignments', 1);
        $this->assertDatabaseCount('resource_assignments', 2);
        $this->signIn($student)->get(route('student.teaching.index'))->assertOk()->assertSee('Beginner starter path')->assertSeeInOrder(['Second sequence resource', 'First sequence resource']);
        $a->update(['status' => 'draft']);
        $this->get(route('student.teaching.index'))->assertDontSee('First sequence resource');
        $collection->update(['is_published' => false]);
        $this->get(route('student.teaching.index'))->assertDontSee('Beginner starter path');
    }

    public function test_bookmarks_are_idempotent_owned_and_respect_current_visibility(): void
    {
        $student = Student::factory()->verified()->create();
        $note = StudentBin::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'title' => 'Saved learning note']);
        $private = StudentBin::factory()->create(['student_id' => $student->id, 'student_visible' => false]);
        $other = StudentBin::factory()->create(['student_visible' => true]);
        $this->signIn($student);
        foreach ([1, 2] as $attempt) {
            $this->post(route('student.bookmarks.store'), ['kind' => 'note', 'target_id' => $note->id])->assertSessionHasNoErrors();
        }
        foreach ([$private, $other] as $inaccessible) {
            $this->post(route('student.bookmarks.store'), ['kind' => 'note', 'target_id' => $inaccessible->id])->assertNotFound();
        }
        $this->assertDatabaseCount('student_bookmarks', 1);
        $this->get(route('student.bookmarks.index'))->assertOk()->assertSee('Saved learning note');
        $note->update(['student_visible' => false]);
        $this->get(route('student.bookmarks.index'))->assertDontSee('Saved learning note');
        $bookmark = StudentBookmark::query()->sole();
        $this->signIn(Student::factory()->verified()->create())->delete(route('student.bookmarks.destroy', $bookmark))->assertNotFound();
        $this->signIn($student)->delete(route('student.bookmarks.destroy', $bookmark))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('student_bookmarks', 0);
    }

    public function test_report_uses_period_lesson_instants_and_excludes_private_assessments_for_student(): void
    {
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'start_at_utc' => '2026-09-15 09:00:00', 'end_at_utc' => '2026-09-15 10:30:00']);
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'start_at_utc' => '2026-08-15 09:00:00', 'end_at_utc' => '2026-08-15 10:30:00']);
        StudentSkillAssessment::factory()->create(['student_id' => $student->id, 'assessed_at' => '2026-09-15 12:00:00', 'notes' => 'Private assessment', 'student_visible' => false]);
        $this->actingAs(Administrator::factory()->create(), 'web')->post(route('admin.students.progress-reports.store', $student), [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'summary' => 'Tutor-written summary', 'next_goals' => 'Practise past tense', 'student_visible' => true,
        ])->assertSessionHasNoErrors();
        $report = StudentProgressReport::query()->sole();
        $this->get(route('admin.students.progress-reports.show', [$student, $report]))->assertOk()->assertSee('Private assessment');
        $this->signIn($student)->get(route('student.progress-reports.show', $report))->assertOk()->assertViewHas('hours', 1.5)->assertViewHas('lessons', fn ($items) => $items->count() === 1)->assertSee('Tutor-written summary')->assertDontSee('Private assessment');
        $this->signIn(Student::factory()->verified()->create())->get(route('student.progress-reports.show', $report))->assertNotFound();
        $report->update(['student_visible' => false]);
        $this->signIn($student)->get(route('student.progress-reports.show', $report))->assertNotFound();
    }

    public function test_new_teaching_data_is_redacted_by_the_existing_privacy_flow(): void
    {
        $student = Student::factory()->verified()->create();
        $item = Homework::factory()->create(['student_id' => $student->id]);
        HomeworkSubmission::factory()->create(['homework_id' => $item->id, 'body' => 'Sensitive homework']);
        $report = StudentProgressReport::factory()->create(['student_id' => $student->id, 'summary' => 'Sensitive report', 'student_visible' => true]);
        StudentBookmark::factory()->create(['student_id' => $student->id]);
        app(StudentPrivacyService::class)->anonymize($student->id);
        $this->assertDatabaseMissing('homework_submissions', ['body' => 'Sensitive homework']);
        $this->assertSame('[redacted]', $report->fresh()->summary);
        $this->assertDatabaseCount('student_bookmarks', 0);
    }

    public function test_merge_preserves_learning_history_and_deduplicates_collection_assignments(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $homework = Homework::factory()->create(['student_id' => $secondary->id, 'student_visible' => true]);
        $submission = HomeworkSubmission::factory()->create(['homework_id' => $homework->id, 'body' => 'Preserved response']);
        $assessment = StudentSkillAssessment::factory()->create(['student_id' => $secondary->id]);
        $report = StudentProgressReport::factory()->create(['student_id' => $secondary->id]);
        $login = StudentPortalLogin::factory()->create(['student_id' => $secondary->id]);
        $bookmark = StudentBookmark::factory()->create(['student_id' => $secondary->id, 'kind' => 'homework', 'target_id' => $homework->id]);
        $collection = ResourceCollection::factory()->create();
        foreach ([$primary, $secondary] as $student) {
            ResourceCollectionAssignment::factory()->create(['student_id' => $student->id, 'resource_collection_id' => $collection->id]);
        }
        app(StudentMergeService::class)->merge($primary->id, $secondary->id);
        foreach ([$homework, $assessment, $report, $login, $bookmark] as $record) {
            $this->assertSame($primary->id, $record->fresh()->student_id);
        }
        $this->assertSame('Preserved response', $submission->fresh()->body);
        $this->assertDatabaseCount('resource_collection_assignments', 1);
        $this->signIn($primary)->get(route('student.bookmarks.index'))->assertOk()->assertSee($homework->title);
    }

    public function test_development_student_graph_includes_learning_history_and_preserves_shared_definitions(): void
    {
        config(['session.driver' => 'database']);
        $student = Student::factory()->verified()->create();
        $homework = Homework::factory()->create(['student_id' => $student->id]);
        $submission = HomeworkSubmission::factory()->create(['homework_id' => $homework->id]);
        $assessment = StudentSkillAssessment::factory()->create(['student_id' => $student->id]);
        $report = StudentProgressReport::factory()->create(['student_id' => $student->id]);
        $bookmark = StudentBookmark::factory()->create(['student_id' => $student->id, 'kind' => 'homework', 'target_id' => $homework->id]);
        $login = StudentPortalLogin::factory()->create(['student_id' => $student->id]);
        $collection = ResourceCollection::factory()->create();
        $assignment = ResourceCollectionAssignment::factory()->create(['student_id' => $student->id, 'resource_collection_id' => $collection->id]);
        $graph = app(DevelopmentDataGraph::class);
        $reset = $graph->reset('students');
        foreach ([$submission, $assessment, $report, $bookmark, $login, $assignment] as $record) {
            $this->assertArrayHasKey($record->id, $reset['rows'][$record->getTable()]);
        }
        $this->assertArrayNotHasKey('learning_skills', $reset['rows']);
        $this->assertArrayNotHasKey('resource_collections', $reset['rows']);
        $export = $graph->export(['students.profile', 'students.teaching'], ['student_id' => $student->id]);
        $this->assertArrayHasKey($assessment->learning_skill_id, $export['rows']['learning_skills']);
        $this->assertArrayHasKey($collection->id, $export['rows']['resource_collections']);
        $this->assertArrayHasKey($bookmark->id, $export['rows']['student_bookmarks']);
    }
}
