<?php

namespace Tests\Feature;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Students\Models\Student;
use App\Domains\System\Services\DevelopmentDataArchiveService;
use App\Domains\System\Services\DevelopmentDataCatalog;
use App\Domains\System\Services\DevelopmentDataFiles;
use App\Domains\System\Services\DevelopmentDataGraph;
use App\Domains\System\Services\DevelopmentDataReconciler;
use App\Domains\System\Services\DevelopmentToolsService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentLearningResetTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['entitlement_types', 'meeting_providers'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false, 'session.driver' => 'database']);
        Storage::fake('local');
    }

    public function test_confirmed_reset_removes_learning_history_and_private_courses_but_preserves_shared_content(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin', 'password' => Hash::make('LearningResetPass!')]);
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create();
        $shared = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $shared->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $shared->id, 'section_id' => $section->id]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'assignment', 'status' => 'ready', 'payload' => ['title' => 'Practice', 'instructions' => 'Upload a response', 'types' => ['file']]]);
        app(LmsAccessOperations::class)->assign($actor, $student, $shared, [], 'reset-learning');
        $submission = app(LmsAssignmentService::class)->submit($student, $shared, $lesson, $block, ['kind' => 'file', 'request_key' => (string) Str::uuid()], UploadedFile::fake()->createWithContent('response.pdf', "%PDF-1.4\nStudent response"));
        $sharedAsset = app(LessonMaterialService::class)->storeCourseAsset($shared, $actor, UploadedFile::fake()->createWithContent('shared.pdf', "%PDF-1.4\nShared content"), 'file');
        $studio = app(CourseStudioService::class);
        $private = $studio->create($actor, ['title' => 'Private practice', 'slug' => 'private-practice', 'kind' => 'private', 'owner_student_id' => $student->id]);
        $studio->write($actor, $private, 'add_section', ['title' => 'Practice', 'status' => 'published'], $private->fresh()->lock_version);
        $sectionKey = $studio->draft($actor, $private)['sections'][0]['key'];
        $studio->write($actor, $private, 'add_lesson', ['parent_key' => $sectionKey, 'title' => 'Conversation', 'slug' => 'conversation', 'status' => 'published'], $private->fresh()->lock_version);
        $lessonKey = $studio->draft($actor, $private)['sections'][0]['lessons'][0]['key'];
        $studio->write($actor, $private, 'add_block', ['parent_key' => $lessonKey, 'kind' => 'rich_text', 'html' => '<p>Private practice</p>'], $private->fresh()->lock_version);
        $private = $studio->publish($actor, $private, $private->fresh()->lock_version);
        $privateAsset = app(LessonMaterialService::class)->storeCourseAsset($private, $actor, UploadedFile::fake()->createWithContent('private.pdf', "%PDF-1.4\nPrivate content"), 'file');
        $tools = app(DevelopmentToolsService::class);
        $preview = $tools->preview($actor, 'learning-reset', 'reset', 'students', ['include_all_bookings' => true, 'export_before' => true]);
        $this->assertModelExists($student);
        $this->assertModelExists($private);
        Storage::disk('local')->assertExists($submission->path);
        $summary = $tools->confirm($actor, 'learning-reset', $preview['token'], 'LearningResetPass!', null, null, 'DELETE ALL STUDENTS');
        $this->assertSame('completed', $summary['numbering_status']);
        $archive = app(DevelopmentDataArchiveService::class)->read($actor, $preview['operation']->fresh()->archive_path);
        $this->assertArrayNotHasKey('lms_courses', $archive['rows']);
        $this->assertNotContains($submission->path, array_column($archive['manifest']['file_inventory'], 'path'));
        $this->assertDatabaseCount('students', 0);
        foreach (['lms_enrollments', 'lms_access_grants', 'lms_access_events', 'lms_learning_assignments', 'lms_assignment_submissions'] as $table) {
            $this->assertDatabaseCount($table, 0);
            $this->assertSame('Restarted: next record #1', $summary['numbering_results'][$table]);
        }
        $this->assertModelMissing($private);
        $this->assertDatabaseMissing('content_revisions', ['revisable_type' => Course::class, 'revisable_id' => $private->id]);
        $this->assertModelExists($shared);
        $this->assertModelExists($lesson);
        $this->assertModelExists($block);
        $this->assertModelExists($resource);
        $this->assertModelExists($actor);
        Storage::disk('local')->assertMissing($submission->path);
        Storage::disk('local')->assertMissing($privateAsset->path);
        Storage::disk('local')->assertExists($sharedAsset->path);
        $this->assertSame(0, app(DevelopmentDataReconciler::class)->verify(true)['orphan_count']);
        $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        $next = Student::factory()->verified()->create();
        $this->assertSame(1, $next->id);
        $this->assertSame($summary, $tools->confirm($actor, 'learning-reset', $preview['token'], '', null, null, ''));
        $this->assertModelExists($next);
    }

    public function test_private_asset_referenced_by_shared_lesson_blocks_the_entire_preview(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $student = Student::factory()->verified()->create();
        $private = Course::factory()->create(['kind' => 'private', 'owner_student_id' => $student->id]);
        $asset = app(LessonMaterialService::class)->storeCourseAsset($private, $actor, UploadedFile::fake()->createWithContent('private.pdf', "%PDF-1.4\nPrivate"), 'file');
        $shared = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $shared->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $shared->id, 'section_id' => $section->id]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'file', 'status' => 'ready', 'asset_id' => $asset->id, 'payload' => ['label' => 'Shared reference']]);
        try {
            app(DevelopmentDataGraph::class)->reset('students');
            $this->fail('Cross-course private file references require review.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('shared lesson', $exception->errors()['scope'][0]);
        }
        $this->assertModelExists($student);
        $this->assertModelExists($block);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_learning_reset_does_not_expand_portable_archives_or_accept_unsafe_paths(): void
    {
        $this->assertNotContains('lms_authorized_devices', app(DevelopmentDataCatalog::class)->tables());
        $this->assertNotContains('lms_courses', app(DevelopmentDataCatalog::class)->tables());
        $this->assertNotContains('lms_video_providers', app(DevelopmentDataCatalog::class)->resetTables());
        $this->expectException(ValidationException::class);
        app(DevelopmentDataFiles::class)->privatePath('lms-submissions/../../.env');
    }
}
