<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsStructureService;
use App\Domains\Students\Models\Student;
use App\Domains\System\Services\DevelopmentDataArchiveService;
use App\Domains\System\Services\DevelopmentDataGraph;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CourseStudioPublicationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Administrator,Course,string} */
    private function ready(): array
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'Arabic foundations', 'slug' => 'author-course', 'kind' => 'catalog']);
        $this->write($actor, $course, 'access', ['audience' => 'public']);
        $this->write($actor, $course, 'add_section', ['title' => 'Foundations', 'status' => 'published']);
        $section = $studio->draft($actor, $course)['sections'][0];
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Alphabet', 'slug' => 'alphabet', 'status' => 'published']);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];
        $this->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<p>Published original · الأصل</p>']);

        return [$actor, $course->fresh(), $lesson['key']];
    }

    private function write(Administrator $actor, Course $course, string $operation, array $data): Course
    {
        return app(CourseStudioService::class)->write($actor, $course, $operation, $data, $course->fresh()->lock_version);
    }

    public function test_publication_applies_only_explicit_selected_states_and_appends_owned_release(): void
    {
        [$actor,$course,$key] = $this->ready();
        $studio = app(CourseStudioService::class);
        $section = $studio->draft($actor, $course)['sections'][0];
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Hidden next lesson', 'slug' => 'hidden', 'status' => 'unpublished']);
        $course = $studio->publish($actor, $course, $course->fresh()->lock_version);
        $this->assertSame('published', $course->status);
        $this->assertNotNull($course->current_release_id);
        $this->assertDatabaseHas('lms_lessons', ['course_id' => $course->id, 'slug' => 'hidden', 'status' => 'unpublished']);
        $this->assertDatabaseHas('lms_course_releases', ['course_id' => $course->id, 'revision_number' => 1]);
        $release = $course->currentRelease;
        $this->assertSame(Course::class, $release->revision->revisable_type);
        $this->assertSame($course->id, $release->revision->revisable_id);
        $this->assertSame(hash('sha256', json_encode($release->revision->content, JSON_THROW_ON_ERROR)), $release->content_hash);
        $this->get(route('lms.courses.show', $course))->assertOk()->assertJsonCount(1, 'sections.0.lessons')->assertDontSee('Hidden next lesson');
    }

    public function test_saving_a_published_lesson_draft_does_not_leak_until_publishing_and_snapshots_are_retained(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $oldRelease = $course->currentRelease;
        $oldContent = $oldRelease->revision->getRawOriginal('content');
        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $block = $studio->draft($actor, $course)['sections'][0]['lessons'][0]['blocks'][0];
        $this->write($actor, $course, 'edit_block', ['key' => $block['key'], 'kind' => 'rich_text', 'html' => '<p>UNPUBLISHED NEW TEXT</p>']);
        $this->write($actor, $course, 'metadata', ['title' => 'UNPUBLISHED TITLE', 'slug' => 'new-course-url']);
        $this->get(route('lms.lessons.show', [$course, $lesson]))->assertOk()->assertSee('Published original')->assertDontSee('UNPUBLISHED NEW TEXT');
        $this->get(route('lms.courses.show', $course))->assertDontSee('UNPUBLISHED TITLE');
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.preview', $course))->assertSee('UNPUBLISHED NEW TEXT')->assertSee('UNPUBLISHED TITLE');
        $studio->publish($actor, $course, $course->fresh()->lock_version);
        $this->get(route('lms.lessons.show', [$course, $lesson]))->assertSee('UNPUBLISHED NEW TEXT')->assertDontSee('Published original');
        $this->assertSame($oldContent, $oldRelease->revision->fresh()->getRawOriginal('content'));
        $this->assertDatabaseCount('lms_course_releases', 2);
        $this->assertSame(2, $course->fresh()->currentRelease->revision_number);
    }

    public function test_publishing_empty_or_broken_content_returns_actionable_errors_without_exposing_draft(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $studio = app(CourseStudioService::class);
        $empty = $studio->create($actor, ['title' => 'Empty', 'slug' => 'empty', 'kind' => 'catalog']);
        try {
            $studio->publish($actor, $empty, 1);
            $this->fail('Empty course publication must fail.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        [$actor,$course,$key] = $this->ready();
        $resource = ResourceFactory::new()->create(['external_url' => null, 'file_path' => 'resources/missing.pdf']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'resource', 'resource_id' => $resource->id]);
        $this->actingAs($actor, 'web')->postJson(route('admin.lms.courses.lifecycle', $course), ['status' => 'published', 'version' => $course->fresh()->lock_version])->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertSame('draft', $course->fresh()->status);
        $this->assertDatabaseCount('lms_course_releases', 0);
        $this->get(route('lms.courses.show', $course))->assertNotFound();
    }

    public function test_unpublish_archive_and_restore_close_access_and_preserve_history(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $this->get(route('lms.courses.show', $course))->assertOk();
        $course = $studio->lifecycle($actor, $course, 'unpublished', $course->lock_version);
        $this->get(route('lms.courses.show', $course))->assertNotFound();
        $course = $studio->publish($actor, $course, $course->lock_version);
        $this->assertSame(hash('sha256', json_encode($course->currentRelease->revision->content, JSON_THROW_ON_ERROR)), $course->currentRelease->content_hash);
        $course = $studio->lifecycle($actor, $course, 'archived', $course->lock_version);
        $this->get(route('lms.courses.show', $course))->assertNotFound();
        $this->assertDatabaseCount('lms_course_releases', 2);
        $this->assertDatabaseCount('lms_lessons', 1);
        $course = $studio->lifecycle($actor, $course, 'draft', $course->lock_version);
        $this->get(route('lms.courses.show', $course))->assertNotFound();
        $this->assertSame('draft', $course->status);
    }

    public function test_duplicate_copies_ordered_eligible_content_as_new_draft_without_student_runtime_facts(): void
    {
        [$actor,$course,$key] = $this->ready();
        $studio = app(CourseStudioService::class);
        $resource = ResourceFactory::new()->create();
        $this->write($actor, $course, 'add_block', ['parent_key' => $key, 'kind' => 'resource', 'resource_id' => $resource->id]);
        $course = $studio->publish($actor, $course, $course->fresh()->lock_version);
        $student = Student::factory()->verified()->create();
        $ops = app(LmsAccessOperations::class);
        $ops->enroll($actor, $student, $course, [], 'source-enrollment');
        $ops->assign($actor, $student, $course, ['instructions' => 'PRIVATE LEARNING INSTRUCTIONS'], 'source-assignment');
        $before = [];
        foreach (['lms_enrollments', 'lms_access_grants', 'lms_learning_assignments', 'lms_access_events'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $copy = $studio->duplicate($actor, $course, $course->lock_version);
        $graph = $studio->draft($actor, $copy);
        $this->assertNotSame($course->id, $copy->id);
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->current_release_id);
        $this->assertCount(1, $graph['sections']);
        $this->assertCount(1, $graph['sections'][0]['lessons']);
        $this->assertCount(2, $graph['sections'][0]['lessons'][0]['blocks']);
        $this->assertNull($graph['sections'][0]['id']);
        $this->assertSame($resource->id, $graph['sections'][0]['lessons'][0]['blocks'][1]['resource_id']);
        $this->assertStringNotContainsString('PRIVATE LEARNING INSTRUCTIONS', json_encode($graph));
        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->get(route('lms.courses.show', $copy))->assertNotFound();
        $copy = $studio->publish($actor, $copy, $copy->lock_version);
        $this->assertNotSame($course->sections->first()->id, $copy->sections->first()->id);
        $this->assertNotSame($course->sections->first()->lessons->first()->id, $copy->sections->first()->lessons->first()->id);
        $this->assertDatabaseCount('resources', 1);
    }

    public function test_discarding_draft_restores_live_editor_without_changing_published_rows(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $this->write($actor, $course, 'metadata', ['title' => 'Discard me', 'slug' => 'discard-me']);
        $course = $studio->discard($actor, $course, $course->fresh()->lock_version);
        $this->assertSame('Arabic foundations', $studio->draft($actor, $course)['title']);
        $this->assertSame('published', $course->status);
        $this->assertDatabaseCount('lms_course_releases', 1);
        $this->get(route('lms.courses.show', $course))->assertJsonPath('course.title', 'Arabic foundations');
    }

    public function test_ready_authored_course_rejects_legacy_live_structure_writes(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Use Course Studio drafts');
        app(LmsStructureService::class)->addSection($actor, $course, 'Bypass');
    }

    public function test_external_live_structure_changes_do_not_silently_overwrite_a_saved_draft(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $this->write($actor, $course, 'metadata', ['title' => 'Saved draft', 'slug' => 'saved']);
        DB::table('lms_lessons')->where('course_id', $course->id)->update(['title' => 'Externally changed']);
        try {
            $studio->publish($actor, $course, $course->fresh()->lock_version);
            $this->fail('External changes must be reviewed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('lms_course_releases', 1);
        $this->assertDatabaseHas('lms_lessons', ['course_id' => $course->id, 'title' => 'Externally changed']);
    }

    public function test_schema_rejects_foreign_release_pointer_and_duplicate_lms_draft_without_changing_cms(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $foreign = Course::factory()->create();
        try {
            DB::table('lms_courses')->where('id', $foreign->id)->update(['current_release_id' => $course->current_release_id]);
            $this->fail('Foreign release must fail.');
        } catch (QueryException) {
            $this->assertNull($foreign->fresh()->current_release_id);
        }
        $this->write($actor, $course, 'metadata', ['title' => 'Draft', 'slug' => 'draft']);
        $this->expectException(QueryException::class);
        ContentRevision::query()->create(['revisable_type' => Course::class, 'revisable_id' => $course->id, 'revision_number' => 99, 'status' => 'draft', 'content' => []]);
    }

    public function test_published_release_model_cannot_overwrite_historical_hash(): void
    {
        [$actor,$course] = $this->ready();
        $course = app(CourseStudioService::class)->publish($actor, $course, $course->lock_version);
        $this->expectException(\RuntimeException::class);
        $course->currentRelease->forceFill(['content_hash' => str_repeat('0', 64)])->save();
    }

    public function test_republication_can_swap_existing_lesson_urls_without_changing_lesson_identity(): void
    {
        [$actor,$course] = $this->ready();
        $studio = app(CourseStudioService::class);
        $section = $studio->draft($actor, $course)['sections'][0];
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Greetings', 'slug' => 'greetings', 'status' => 'draft']);
        $course = $studio->publish($actor, $course, $course->fresh()->lock_version);
        [$alphabet,$greetings] = $studio->draft($actor, $course)['sections'][0]['lessons'];
        $this->write($actor, $course, 'edit_lesson', ['key' => $alphabet['key'], 'title' => $alphabet['title'], 'slug' => 'temporary', 'status' => 'published']);
        $this->write($actor, $course, 'edit_lesson', ['key' => $greetings['key'], 'title' => $greetings['title'], 'slug' => 'alphabet', 'status' => 'draft']);
        $this->write($actor, $course, 'edit_lesson', ['key' => $alphabet['key'], 'title' => $alphabet['title'], 'slug' => 'greetings', 'status' => 'published']);
        $studio->publish($actor, $course, $course->fresh()->lock_version);
        $this->assertDatabaseHas('lms_lessons', ['id' => $alphabet['id'], 'slug' => 'greetings', 'status' => 'published']);
        $this->assertDatabaseHas('lms_lessons', ['id' => $greetings['id'], 'slug' => 'alphabet', 'status' => 'draft']);
        $this->assertDatabaseCount('lms_lessons', 2);
    }

    public function test_resource_export_excludes_lms_revisions_and_import_refuses_an_injected_lms_revision(): void
    {
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false]);
        Storage::fake('local');
        [$actor,$course] = $this->ready();
        $actor->update(['role' => 'super_admin']);
        $graph = app(DevelopmentDataGraph::class);
        $plan = $graph->export(['resources.records', 'resources.revisions']);
        $this->assertEmpty($plan['rows']['content_revisions'] ?? []);
        $plan['rows']['content_revisions'] = [(array) DB::table('content_revisions')->where('revisable_type', Course::class)->firstOrFail()];
        $archive = app(DevelopmentDataArchiveService::class);
        $file = $archive->create($actor, $plan);
        try {
            $archive->read($actor, $file['path']);
            $this->fail('Resource imports must refuse LMS revisions.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('LMS course revisions', $exception->errors()['archive'][0]);
        }
        $this->assertSame(1, $course->revisions()->count());
    }

    public function test_legacy_reserved_content_cannot_be_published_as_working_lesson_content(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $structure = app(LmsStructureService::class);
        $course = Course::factory()->create();
        $section = $structure->addSection($actor,$course,'Legacy section');
        $lesson = $structure->addLesson($actor,$section,'Legacy lesson','legacy');
        $structure->setStatus($actor,$section,'published',$section->fresh()->lock_version);
        $structure->setStatus($actor,$lesson,'published',$lesson->fresh()->lock_version);
        $structure->addBlock($actor,$lesson,['kind' => 'bunny_video']);
        $this->actingAs($actor,'web')->postJson(route('admin.lms.courses.lifecycle',$course),['status' => 'published', 'version' => $course->fresh()->lock_version])->assertUnprocessable()->assertJsonValidationErrors('publication');
        $this->assertDatabaseCount('lms_course_releases',0);
    }
}
