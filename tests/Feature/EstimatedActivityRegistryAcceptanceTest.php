<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityRegistryAcceptanceTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    public static function contexts(): array
    {
        return [
            'lesson text' => ['student_lesson', 'rich_text', 'lms', 'activity-lesson-text-'],
            'lesson image' => ['student_lesson', 'image', 'lms', 'activity-lesson-text-'],
            'quiz' => ['student_assessment', 'quiz', 'lms', 'activity-assessment'],
            'assignment' => ['student_assessment', 'assignment', 'lms', 'activity-assessment'],
            'new note' => ['student_note_draft', 'rich_text', 'lms', 'activity-note-new'],
            'saved note' => ['student_note', 'rich_text', 'lms', 'activity-note-'],
            'homework' => ['student_homework', 'rich_text', 'homework', 'activity-homework-study-'],
            'course creation' => ['admin_course_create', 'rich_text', 'learning_materials', 'activity-course-create'],
            'course editing' => ['admin_course', 'rich_text', 'learning_materials', 'activity-course-editor'],
            'course review' => ['admin_lms_review', 'rich_text', 'homework_review', 'activity-review-content'],
            'lesson materials' => ['admin_lesson_materials', 'rich_text', 'lesson_preparation', 'activity-lesson-materials'],
            'feedback' => ['admin_feedback', 'rich_text', 'feedback', 'activity-feedback'],
            'booking schedule' => ['admin_booking', 'rich_text', 'booking_admin', 'activity-booking-schedule'],
            'new booking schedule' => ['admin_booking_create', 'rich_text', 'booking_admin', 'activity-booking-create-schedule'],
            'teaching homework' => ['admin_teaching_homework', 'rich_text', 'homework_review', 'teaching-homework'],
            'teaching materials' => ['admin_teaching_materials', 'rich_text', 'lesson_preparation', 'teaching-resource'],
            'teaching plan' => ['admin_teaching_plan', 'rich_text', 'general_admin', 'teaching-plan'],
            'teaching practice' => ['admin_teaching_practice', 'rich_text', 'general_admin', 'teaching-error'],
        ];
    }

    #[DataProvider('contexts')]
    public function test_each_root_credits_a_bounded_window_without_primary_writes(string $kind, string $blockKind, string $category, string $root): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        $student = Student::factory()->verified()->create();
        $actor = str_starts_with($kind, 'student_') ? $student : Administrator::factory()->create();
        $block = $this->activityLesson($student);
        if ($blockKind === 'quiz') {
            $block->forceFill(['kind' => 'quiz', 'payload' => app(LmsLearningDefinition::class)->assessment('quiz', [
                'title' => 'Synthetic quiz', 'passing_score' => 80, 'attempt_limit' => 2, 'review_policy' => 'after_grading',
                'questions' => [['type' => 'choice', 'prompt' => 'Choose', 'points' => 1, 'options' => ['Hello', 'Bye'], 'answer' => 0]],
            ])])->save();
        } elseif ($blockKind === 'assignment') {
            $block->forceFill(['kind' => 'assignment', 'payload' => app(LmsLearningDefinition::class)->assessment('assignment', [
                'title' => 'Synthetic assignment', 'instructions' => 'Write a greeting.', 'types' => ['text'],
            ])])->save();
        } elseif ($blockKind === 'image') {
            Storage::fake('local');
            $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
            $asset = LmsAsset::factory()->create(['course_id' => $block->lesson->course_id, 'byte_size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]);
            Storage::disk('local')->put($asset->path, $bytes);
            $block->forceFill(['kind' => 'image', 'asset_id' => $asset->id, 'payload' => ['alt' => 'Synthetic image']])->save();
        }
        $lesson = $block->lesson;
        $course = $lesson->course;
        $record = $block->id;
        $url = route('student.learning.lessons.show', [$course, $lesson]);
        if ($kind === 'student_assessment') {
            $url = route('student.evidence.show', [$course, $lesson, $block]);
        } elseif ($kind === 'student_note_draft') {
            $record = $lesson->id;
        } elseif ($kind === 'student_note') {
            $record = LessonNote::factory()->create(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'body' => 'PRIVATE_AUTHORED_CONTENT'])->id;
        } elseif ($kind === 'student_homework') {
            $record = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true])->id;
            $url = route('student.teaching.index');
        } elseif (in_array($kind, ['admin_course', 'admin_course_create', 'admin_lms_review'], true)) {
            $record = $kind === 'admin_course_create' ? 0 : $course->id;
            $url = match ($kind) {
                'admin_course_create' => route('admin.lms.courses.create'),
                'admin_course' => route('admin.lms.courses.edit', $course),
                default => route('admin.lms.reviews.index', $course),
            };
        } elseif ($kind === 'admin_feedback') {
            $record = FeedbackConversation::factory()->create(['student_id' => $student->id])->id;
            $url = route('admin.feedback.show', $record);
        } elseif (str_starts_with($kind, 'admin_teaching_')) {
            $record = $student->id;
            $url = route('admin.students.teaching', $student);
        } elseif (in_array($kind, ['admin_booking', 'admin_booking_create', 'admin_lesson_materials'], true)) {
            $record = $kind === 'admin_booking_create' ? 0 : Booking::factory()->create(['student_id' => $student->id])->id;
            $url = match ($kind) {
                'admin_booking_create' => route('admin.bookings.create'),
                'admin_booking' => route('admin.bookings.show', $record),
                default => route('admin.lessons.show', $record),
            };
        }
        $this->activityRun($actor);
        $domain = $actor instanceof Student ? 'student' : 'admin';
        $actor instanceof Student ? $this->activityStudent($actor) : $this->activityAdmin($actor);
        $this->get($url)->assertOk()->assertSee('data-estimated-activity')->assertSee($root);
        $before = [];
        foreach (['bookings', 'homework_submissions', 'lms_quiz_attempts', 'lms_assignment_submissions', 'lms_lesson_progress', 'student_notifications'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $anchor = $this->postJson(route($domain.'.estimated-activity.anchor'), ['kind' => $kind, 'record' => $record, 'stream' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('seconds', 0)->json();
        $this->travel(15)->seconds();

        $this->postJson(route($domain.'.estimated-activity.checkpoint'), ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000])
            ->assertOk()->assertJsonPath('seconds', 15);

        $this->assertDatabaseHas('estimated_activity_intervals', ['kind' => $kind, 'record_id' => $record, 'category' => $category]);
        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->postJson(route($domain.'.estimated-activity.anchor'), ['kind' => $kind, 'record' => 999999, 'stream' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('estimated_activity_intervals', 1);
        $this->assertStringNotContainsString('PRIVATE_AUTHORED_CONTENT', json_encode(DB::table('estimated_activity_intervals')->get(), JSON_THROW_ON_ERROR));
        file_put_contents($this->acceptanceOutputPath('registry-'.$kind.'-'.$blockKind.'.json'), json_encode([
            'kind' => $kind, 'block_kind' => $blockKind, 'category' => $category, 'root' => $root, 'route' => $url,
            'record' => $record, 'anchor_seconds' => 0, 'accepted_seconds' => 15, 'primary_counts' => $before, 'invalid_record_status' => 404,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
