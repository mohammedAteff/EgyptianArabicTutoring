<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseStudioTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): Administrator
    {
        return AdministratorFactory::new()->create(['role' => 'admin']);
    }

    private function course(Administrator $actor): Course
    {
        return app(CourseStudioService::class)->create($actor, ['title' => 'Arabic foundations', 'slug' => 'arabic-foundations', 'kind' => 'catalog']);
    }

    private function write(Administrator $actor, Course $course, string $operation, array $data): Course
    {
        return app(CourseStudioService::class)->write($actor, $course, $operation, $data, $course->fresh()->lock_version);
    }

    public function test_admin_can_create_edit_and_render_a_safe_draft_course(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor, 'web');
        $this->get(route('admin.lms.courses.create'))->assertOk()->assertSee('Create a course');
        $this->post(route('admin.lms.courses.store'), ['title' => 'Arabic foundations', 'slug' => 'arabic-foundations', 'kind' => 'catalog'])->assertRedirect();
        $course = Course::query()->firstOrFail();
        $this->assertSame('draft', $course->status);
        $this->post(route('admin.lms.courses.update', $course), ['operation' => 'metadata', 'version' => 1, 'title' => 'العربية · Arabic', 'slug' => 'arabic-new'])->assertRedirect();
        $this->get(route('admin.lms.courses.edit', $course))->assertOk()->assertSee('العربية · Arabic')->assertSee('Author preview')->assertSee('Course attachments');
        $this->assertSame('Arabic foundations', $course->fresh()->title);
        $this->get(route('lms.courses.show', $course))->assertNotFound();
    }

    public function test_create_validation_and_private_owner_are_server_enforced(): void
    {
        $this->actingAs($this->actor(), 'web');
        $this->postJson(route('admin.lms.courses.store'), ['title' => '', 'slug' => 'Bad Slug', 'kind' => 'catalog'])->assertUnprocessable()->assertJsonValidationErrors(['title', 'slug']);
        $this->postJson(route('admin.lms.courses.store'), ['title' => 'Private', 'slug' => 'private', 'kind' => 'private'])->assertNotFound();
        $owner = Student::factory()->verified()->create();
        $this->post(route('admin.lms.courses.store'), ['title' => 'Private', 'slug' => 'private', 'kind' => 'private', 'owner_student_id' => $owner->id])->assertRedirect();
        $this->assertDatabaseHas('lms_courses', ['slug' => 'private', 'kind' => 'private', 'owner_student_id' => $owner->id, 'status' => 'draft']);
    }

    public function test_assistant_and_student_cannot_author_or_preview_and_navigation_is_role_aware(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web')->get(route('admin.lms.courses.index'))->assertForbidden();
        $this->get(route('admin.lms.courses.preview', $course))->assertForbidden();
        $this->postJson(route('admin.lms.courses.update', $course), ['operation' => 'metadata', 'version' => 1, 'title' => 'Injected', 'slug' => 'injected'])->assertForbidden();
        $this->get(route('admin.operations.index'))->assertOk()->assertDontSee(route('admin.lms.courses.index'), false);
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.index'))->assertOk()->assertSee(route('admin.lms.courses.index'), false);
        auth('web')->logout();
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])->get(route('admin.lms.courses.preview', $course))->assertRedirect(route('admin.login'));
    }

    public function test_sections_lessons_and_blocks_edit_reorder_move_and_archive_in_the_scoped_draft(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $studio = app(CourseStudioService::class);
        $this->write($actor, $course, 'add_section', ['title' => 'First', 'status' => 'published']);
        $this->write($actor, $course, 'add_section', ['title' => 'Second', 'status' => 'draft']);
        $graph = $studio->draft($actor, $course);
        [$first,$second] = $graph['sections'];
        $this->write($actor, $course, 'edit_section', ['key' => $first['key'], 'title' => 'First renamed', 'status' => 'published']);
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $first['key'], 'title' => 'Alphabet', 'slug' => 'alphabet', 'status' => 'published']);
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $first['key'], 'title' => 'Greetings', 'slug' => 'greetings', 'status' => 'draft']);
        $graph = $studio->draft($actor, $course);
        [$one,$two] = $graph['sections'][0]['lessons'];
        $this->write($actor, $course, 'reorder_lesson', ['key' => $two['key'], 'direction' => 'up']);
        $this->write($actor, $course, 'edit_lesson', ['key' => $one['key'], 'title' => 'الحروف · Alphabet', 'slug' => 'alphabet', 'status' => 'published']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $one['key'], 'kind' => 'rich_text', 'html' => '<p>أهلاً Hello</p>']);
        $this->write($actor, $course, 'add_block', ['parent_key' => $one['key'], 'kind' => 'external_link', 'url' => 'https://example.test/practice']);
        $graph = $studio->draft($actor, $course);
        $blocks = $graph['sections'][0]['lessons'][1]['blocks'];
        $this->write($actor, $course, 'reorder_block', ['key' => $blocks[1]['key'], 'direction' => 'up']);
        $this->write($actor, $course, 'edit_block', ['key' => $blocks[0]['key'], 'kind' => 'rich_text', 'html' => '<p>Updated مرحباً</p>']);
        $this->write($actor, $course, 'remove_block', ['key' => $blocks[1]['key']]);
        $this->write($actor, $course, 'move_lesson', ['key' => $one['key'], 'destination' => $second['key']]);
        $this->write($actor, $course, 'reorder_section', ['key' => $second['key'], 'direction' => 'up']);
        $graph = $studio->draft($actor, $course);
        $this->assertSame('Second', $graph['sections'][0]['title']);
        $this->assertSame('الحروف · Alphabet', $graph['sections'][0]['lessons'][0]['title']);
        $this->assertSame('withdrawn', $graph['sections'][0]['lessons'][0]['blocks'][0]['status']);
        $this->assertStringContainsString('Updated', $graph['sections'][0]['lessons'][0]['blocks'][1]['payload']['html']);
        $this->assertSame('Greetings', $graph['sections'][1]['lessons'][0]['title']);
        $this->write($actor, $course, 'edit_section', ['key' => $first['key'], 'title' => 'First renamed', 'status' => 'archived']);
        $this->assertSame('archived', $studio->draft($actor, $course)['sections'][1]['status']);
        $this->assertDatabaseCount('lms_sections', 0);
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.edit', $course))->assertOk()->assertSee('الحروف · Alphabet');
    }

    public function test_cross_course_node_keys_are_rejected_without_changes(): void
    {
        $actor = $this->actor();
        $one = $this->course($actor);
        $two = app(CourseStudioService::class)->create($actor, ['title' => 'Foreign', 'slug' => 'foreign', 'kind' => 'catalog']);
        $this->write($actor, $two, 'add_section', ['title' => 'Foreign section']);
        $foreign = app(CourseStudioService::class)->draft($actor, $two)['sections'][0];
        $this->actingAs($actor, 'web')->postJson(route('admin.lms.courses.update', $one), ['operation' => 'edit_section', 'version' => 1, 'key' => $foreign['key'], 'title' => 'Tampered', 'status' => 'published'])->assertNotFound();
        $this->assertSame('Foreign section', app(CourseStudioService::class)->draft($actor, $two)['sections'][0]['title']);
        $this->assertSame(1, $one->fresh()->lock_version);
    }

    public function test_list_search_status_archive_and_access_filters_use_real_course_data(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $this->write($actor, $course, 'metadata', ['title' => 'Draft search title', 'slug' => 'new-title']);
        $archived = Course::factory()->create(['status' => 'archived', 'title' => 'Archived unique title']);
        $this->actingAs($actor, 'web');
        $this->get(route('admin.lms.courses.index', ['search' => 'Draft search']))->assertOk()->assertSee('Draft search title');
        $this->get(route('admin.lms.courses.index'))->assertDontSee('Archived unique title');
        $this->get(route('admin.lms.courses.index', ['view' => 'archived']))->assertSee('Archived unique title')->assertDontSee('Draft search title');
        $this->get(route('admin.lms.courses.index', ['status' => 'published']))->assertDontSee('Draft search title');
        $this->get(route('admin.lms.courses.index', ['audience' => 'selected_students']))->assertSee('Draft search title');
    }

    public function test_stale_http_edit_returns_conflict_and_preserves_latest_draft(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $this->write($actor, $course, 'metadata', ['title' => 'Latest draft', 'slug' => 'latest']);
        $this->actingAs($actor, 'web')->postJson(route('admin.lms.courses.update', $course), ['operation' => 'metadata', 'version' => 1, 'title' => 'Stale draft', 'slug' => 'stale'])->assertStatus(409);
        $this->assertSame('Latest draft', app(CourseStudioService::class)->draft($actor, $course)['title']);
        $this->assertSame(1, $course->revisions()->where('status', 'draft')->count());
    }

    public function test_preview_is_read_only_sanitized_and_never_creates_learner_access(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $studio = app(CourseStudioService::class);
        $this->write($actor, $course, 'add_section', ['title' => 'أهلاً Section', 'status' => 'draft']);
        $section = $studio->draft($actor, $course)['sections'][0];
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'مرحبا Lesson', 'slug' => 'hello', 'status' => 'draft']);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];
        $this->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<p>مرحبا Hello <strong>reader</strong></p><script>alert(1)</script><a href="javascript:alert(1)">bad</a><iframe src="https://evil.test"></iframe>']);
        $before = DB::table('content_revisions')->where('revisable_type', Course::class)->count();
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.preview', $course))->assertOk()->assertSee('مرحبا Hello', false)->assertDontSee('alert(1)', false)->assertDontSee('https://evil.test', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, DB::table('content_revisions')->where('revisable_type', Course::class)->count());
        foreach (['lms_enrollments', 'lms_access_grants', 'lms_learning_assignments', 'student_notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->get(route('lms.courses.show', $course))->assertNotFound();
    }

    public function test_cross_course_lesson_and_content_keys_are_rejected(): void
    {
        $actor = $this->actor();
        $one = $this->course($actor);
        $studio = app(CourseStudioService::class);
        $two = $studio->create($actor, ['title' => 'Other course', 'slug' => 'other-course', 'kind' => 'catalog']);
        $this->write($actor, $two, 'add_section', ['title' => 'Other section']);
        $section = $studio->draft($actor, $two)['sections'][0];
        $this->write($actor, $two, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Other lesson', 'slug' => 'other-lesson']);
        $lesson = $studio->draft($actor, $two)['sections'][0]['lessons'][0];
        $this->write($actor, $two, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<p>Other content</p>']);
        $block = $studio->draft($actor, $two)['sections'][0]['lessons'][0]['blocks'][0];
        $this->actingAs($actor, 'web');
        foreach ([
            ['operation' => 'edit_lesson', 'key' => $lesson['key'], 'title' => 'Tampered', 'slug' => 'tampered', 'status' => 'published'],
            ['operation' => 'reorder_lesson', 'key' => $lesson['key'], 'direction' => 'up'],
            ['operation' => 'add_block', 'parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<p>Tampered</p>'],
            ['operation' => 'edit_block', 'key' => $block['key'], 'kind' => 'rich_text', 'html' => '<p>Tampered</p>'],
            ['operation' => 'remove_block', 'key' => $block['key']],
            ['operation' => 'reorder_block', 'key' => $block['key'], 'direction' => 'down'],
        ] as $payload) {
            $this->postJson(route('admin.lms.courses.update', $one), $payload + ['version' => 1])->assertNotFound();
        }
        $this->assertSame('Other lesson', $studio->draft($actor, $two)['sections'][0]['lessons'][0]['title']);
        $this->assertSame(1, $one->fresh()->lock_version);
    }

    public function test_access_form_normalizes_explicit_utc_dates_without_creating_grants(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $this->actingAs($actor, 'web')->post(route('admin.lms.courses.update', $course), ['operation' => 'access', 'version' => 1, 'audience' => 'all_students', 'access_mode' => 'fixed', 'starts_at' => '2026-10-08T10:00', 'expires_at' => '2026-10-09T10:00:00'])->assertRedirect()->assertSessionHasNoErrors();
        $graph = app(CourseStudioService::class)->draft($actor, $course);
        $this->assertSame('2026-10-08T10:00:00+00:00', $graph['access']['starts_at']);
        $this->assertSame('2026-10-09T10:00:00+00:00', $graph['access']['expires_at']);
        $this->assertDatabaseCount('lms_access_grants', 0);
    }

    public function test_validation_redirect_retains_access_mode_and_selected_section_state(): void
    {
        $actor = $this->actor();
        $course = $this->course($actor);
        $this->write($actor, $course, 'add_section', ['title' => 'Existing section', 'status' => 'draft']);
        $section = app(CourseStudioService::class)->draft($actor, $course)['sections'][0];
        $edit = route('admin.lms.courses.edit', $course);
        $this->actingAs($actor, 'web')->from($edit)->post(route('admin.lms.courses.update', $course), ['operation' => 'edit_section', 'key' => $section['key'], 'version' => $course->fresh()->lock_version, 'title' => '', 'status' => 'unpublished'])->assertRedirect($edit)->assertSessionHasErrors('title');
        $response = $this->get($edit)->assertOk();
        $this->assertMatchesRegularExpression('/id="section-0-status"[^>]*>.*?value="unpublished"\s+selected/s', $response->getContent());
        $this->from($edit)->post(route('admin.lms.courses.update', $course), ['operation' => 'access', 'version' => $course->fresh()->lock_version, 'audience' => 'all_students', 'access_mode' => 'relative', 'relative_days' => '0'])->assertRedirect($edit)->assertSessionHasErrors('relative_days');
        $response = $this->get($edit)->assertOk();
        $this->assertMatchesRegularExpression('/id="studio-audience"[^>]*>.*?value="all_students"\s+selected/s', $response->getContent());
        $this->assertMatchesRegularExpression('/id="studio-mode"[^>]*>.*?value="relative"\s+selected/s', $response->getContent());
        $this->assertSame('draft', app(CourseStudioService::class)->draft($actor, $course)['sections'][0]['status']);
    }

    public function test_editor_and_preview_render_complete_course_hierarchy(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $course = $this->course($actor);
        $studio = app(CourseStudioService::class);
        $this->write($actor, $course, 'add_section', ['title' => '01 · Foundations / الأساسيات', 'status' => 'published']);
        $this->write($actor, $course, 'add_section', ['title' => '02 · Everyday Arabic / العربية اليومية', 'status' => 'draft']);
        $graph = $studio->draft($actor, $course);
        $this->write($actor, $course, 'add_lesson', ['parent_key' => $graph['sections'][0]['key'], 'title' => 'The alphabet · الحروف', 'slug' => 'alphabet', 'status' => 'published']);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];
        $this->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<h2>أهلاً وسهلاً · Welcome</h2><p>Build a confident foundation in Egyptian Arabic, one lesson at a time.</p>']);
        $asset = $studio->upload($actor, $course, UploadedFile::fake()->image('alphabet-reference.png', 200, 120), 'image', $course->fresh()->lock_version);
        $this->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'image', 'asset_id' => $asset->id, 'alt' => 'Alphabet reference · مرجع الحروف']);
        $this->actingAs($actor, 'web');
        $edit = $this->get(route('admin.lms.courses.edit', $course))->assertOk()->assertSeeText('Sections & lessons')->assertSee('courseRichTextEditor()', false)->assertSee('Add lesson content')->assertSee('Move lesson to section');
        $preview = $this->get(route('admin.lms.courses.preview', $course))->assertOk()->assertSee('أهلاً وسهلاً')->assertSee('Alphabet reference');
        $output = getenv('COURSE_STUDIO_UI_REVIEW_DIR');
        if (is_string($output) && is_dir($output)) {
            file_put_contents($output.'/course-studio-editor.html', $edit->getContent());
            $html = str_replace(route('admin.lms.courses.assets', [$course, $asset]), 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($asset->path)), $preview->getContent());
            file_put_contents($output.'/course-studio-preview.html',$html);
            file_put_contents($output.'/course-studio-list.html',$this->get(route('admin.lms.courses.index'))->assertOk()->getContent());
        }
    }
}
