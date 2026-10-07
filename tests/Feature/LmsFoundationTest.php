<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsStructureService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LmsFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): Administrator
    {
        return AdministratorFactory::new()->create();
    }

    private function lesson(): Lesson
    {
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);

        return Lesson::factory()->published()->create(['section_id' => $section->id, 'course_id' => $course->id]);
    }

    public function test_structure_operations_use_one_course_section_lesson_and_ordered_block_domain(): void
    {
        $actor = $this->actor();
        $structure = app(LmsStructureService::class);
        $course = $structure->createCourse($actor, ['title' => 'Foundations', 'slug' => 'foundations', 'kind' => 'catalog', 'status' => 'published']);
        $section = $structure->addSection($actor, $course, 'Basics', 2);
        $lesson = $structure->addLesson($actor, $section, 'Alphabet', 'alphabet');
        $structure->addBlock($actor, $lesson, ['kind' => 'bunny_video', 'sort_order' => 3]);
        $text = $structure->addBlock($actor, $lesson, ['kind' => 'rich_text', 'sort_order' => 1, 'payload' => ['html' => '<p>Hello</p><script>alert(1)</script>']]);
        $this->assertSame('draft', $course->fresh()->status);
        $this->assertSame($course->id, $lesson->course_id);
        $this->assertSame(['rich_text', 'bunny_video'], $lesson->blocks->pluck('kind')->all());
        $this->assertStringNotContainsString('<script', $text->payload['html']);
        $this->assertSame(0, DB::table('student_packages')->count());
        $this->assertSame(0, DB::table('session_ledger_entries')->count());
        $this->assertDatabaseCount('student_notifications', 0);
    }

    /** @return iterable<string, array{string,string}> */
    public static function hiddenStates(): iterable
    {
        foreach (['course', 'section', 'lesson'] as $target) {
            foreach (['draft', 'unpublished', 'archived'] as $state) {
                yield $target.'-'.$state => [$target, $state];
            }
        }
    }

    #[DataProvider('hiddenStates')]
    public function test_every_ancestor_publication_state_is_enforced(string $target, string $status): void
    {
        $lesson = $this->lesson();
        $actor = $this->actor();
        app(LmsStructureService::class)->setRule($actor, $lesson->course, ['audience' => 'public']);
        $model = match ($target) {
            'course' => $lesson->course, 'section' => $lesson->section, 'lesson' => $lesson
        };
        app(LmsStructureService::class)->setStatus($actor, $model, $status, 1);
        $this->get(route('lms.lessons.show', [$lesson->course_id, $lesson]))->assertNotFound();
        $this->assertFalse(app(LmsAccessService::class)->canAccess(null, $lesson));
    }

    public function test_public_course_returns_only_published_entitled_children_and_ready_content(): void
    {
        $lesson = $this->lesson();
        $actor = $this->actor();
        $structure = app(LmsStructureService::class);
        $structure->setRule($actor, $lesson->course, ['audience' => 'public']);
        $draft = Lesson::factory()->create(['section_id' => $lesson->section_id, 'course_id' => $lesson->course_id, 'title' => 'Hidden draft']);
        $structure->addBlock($actor, $lesson, ['kind' => 'quiz']);
        $structure->addBlock($actor, $lesson, ['kind' => 'external_link', 'payload' => ['url' => 'https://example.test/practice']]);
        $this->get(route('lms.courses.show', $lesson->course_id))->assertOk()->assertJsonCount(1, 'sections.0.lessons')->assertDontSee($draft->title);
        $this->get(route('lms.lessons.show', [$lesson->course_id, $lesson]))->assertOk()->assertJsonCount(1, 'blocks')->assertJsonPath('blocks.0.kind', 'external_link')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    /** @return array<string,array{string}> */
    public static function studentAudiences(): array
    {
        return ['member' => ['member'], 'all' => ['all_students']];
    }

    #[DataProvider('studentAudiences')]
    public function test_student_audiences_require_current_canonical_student_session(string $audience): void
    {
        $lesson = $this->lesson();
        app(LmsStructureService::class)->setRule($this->actor(), $lesson->course, ['audience' => $audience]);
        $url = route('lms.lessons.show', [$lesson->course_id, $lesson]);
        $this->get($url)->assertNotFound();
        $student = Student::factory()->verified()->create();
        $this->actingAs($student, 'student')->get($url)->assertNotFound();
        $this->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])->get($url)->assertOk();
        $this->withSession(['student_auth_expires_at' => now('UTC')->toIso8601String()])->get($url)->assertNotFound();
        $other = Student::factory()->verified()->create();
        $this->withSession(['student_id' => $other->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])->get($url)->assertNotFound();
    }

    public function test_staff_session_does_not_count_as_member_and_unsafe_student_is_denied(): void
    {
        $lesson = $this->lesson();
        app(LmsStructureService::class)->setRule($this->actor(), $lesson->course, ['audience' => 'member']);
        $this->actingAs($this->actor(), 'web')->get(route('lms.courses.show', $lesson->course_id))->assertNotFound();
        foreach ([['identity_status' => 'legacy_unverified'], ['suspended_at' => now()], ['merged_into_student_id' => Student::factory()->verified()->create()->id]] as $values) {
            $student = Student::factory()->verified()->create($values);
            $this->assertFalse(app(LmsAccessService::class)->canAccess($student, $lesson));
        }
    }

    public function test_private_ownership_and_publication_constraints_are_enforced_by_mariadb(): void
    {
        $this->expectException(QueryException::class);
        DB::table('lms_courses')->insert(['title' => 'Invalid', 'slug' => 'invalid-owner', 'kind' => 'private']);
    }

    public function test_cross_course_section_is_rejected_by_composite_foreign_key(): void
    {
        $section = Section::factory()->create();
        $course = Course::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('lms_lessons')->insert(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'Bad', 'slug' => 'bad']);
    }

    public function test_unimplemented_content_cannot_be_marked_ready_at_database_boundary(): void
    {
        $lesson = $this->lesson();
        $this->expectException(QueryException::class);
        DB::table('lms_lesson_blocks')->insert(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => '{}']);
    }

    public function test_private_course_cannot_gain_a_broad_rule(): void
    {
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->create(['kind' => 'private', 'owner_student_id' => $student->id]);
        $this->expectException(ValidationException::class);
        app(LmsStructureService::class)->setRule($this->actor(), $course, ['audience' => 'public']);
    }

    public function test_unsafe_link_and_undeclared_payload_are_rejected(): void
    {
        $structure = app(LmsStructureService::class);
        $actor = $this->actor();
        $lesson = $this->lesson();
        foreach ([['url' => 'javascript:alert(1)'], ['url' => 'https://example.test', 'token' => 'secret']] as $payload) {
            try {
                $structure->addBlock($actor, $lesson, ['kind' => 'external_link', 'payload' => $payload]);
                $this->fail('Unsafe content must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('lms_lesson_blocks', 0);
            }
        }
    }

    public function test_students_assistants_and_suspended_admins_cannot_manage_domain(): void
    {
        $course = Course::factory()->create();
        foreach ([$this->actor()->forceFill(['role' => 'assistant']), $this->actor()->forceFill(['suspended_at' => now()])] as $actor) {
            $actor->save();
            $this->assertFalse(Gate::forUser($actor)->allows('manage', $course));
            $response = $this->actingAs($actor, 'web')->postJson(route('admin.lms.access.issue', [Student::factory()->verified()->create(), $course]), ['operation' => 'grant', 'operation_key' => 'deny']);
            if ($actor->suspended_at) {
                $response->assertRedirect(route('admin.login'));
            } else {
                $response->assertForbidden();
            }
            $this->assertDatabaseCount('lms_access_grants', 0);
        }
        $this->assertFalse(Gate::forUser(Student::factory()->verified()->create())->allows('manage', $course));
    }

    public function test_nested_course_lesson_and_resource_ids_fail_closed(): void
    {
        $lesson = $this->lesson();
        $other = $this->lesson();
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        app(LmsStructureService::class)->setRule($actor, $lesson->course, ['audience' => 'all_students']);
        $resource = ResourceFactory::new()->create();
        $block = app(LmsStructureService::class)->addBlock($actor, $lesson, ['kind' => 'resource', 'resource_id' => $resource->id]);
        $this->portal($student);
        $this->get(route('student.lms.lessons.show', [$other->course_id, $lesson]))->assertNotFound();
        $this->get(route('student.lms.resources.open', [$lesson->course_id, $other, $block]))->assertNotFound();
    }

    public function test_resources_reuse_private_delivery_and_current_resource_publication(): void
    {
        Storage::fake('local');
        $lesson = $this->lesson();
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $resource = ResourceFactory::new()->create(['external_url' => null, 'file_path' => 'resources/test.pdf']);
        Storage::disk('local')->put('resources/test.pdf', '%PDF-private');
        $structure = app(LmsStructureService::class);
        $structure->setRule($actor, $lesson->course, ['audience' => 'all_students']);
        $block = $structure->addBlock($actor, $lesson, ['kind' => 'resource', 'resource_id' => $resource->id]);
        $this->portal($student);
        $url = route('student.lms.resources.open', [$lesson->course_id, $lesson, $block]);
        $this->get($url)->assertOk()->assertDownload()->assertHeader('Cache-Control', 'no-store, private');
        $resource->update(['status' => 'draft']);
        $this->get($url)->assertNotFound();
    }

    public function test_public_guest_resource_link_keeps_existing_resource_gate(): void
    {
        $lesson = $this->lesson();
        $actor = $this->actor();
        $structure = app(LmsStructureService::class);
        $resource = ResourceFactory::new()->create();
        $structure->setRule($actor, $lesson->course, ['audience' => 'public']);
        $structure->addBlock($actor, $lesson, ['kind' => 'resource', 'resource_id' => $resource->id]);
        $this->get(route('lms.lessons.show', [$lesson->course_id, $lesson]))->assertJsonPath('blocks.0.url', route('resources.show', $resource->slug))->assertDontSee('file_path');
    }

    private function portal(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
    }

    public function test_outline_and_content_queries_do_not_grow_per_learning_item(): void
    {
        $lesson = $this->lesson();
        $actor = $this->actor();
        $structure = app(LmsStructureService::class);
        $structure->setRule($actor, $lesson->course, ['audience' => 'public']);
        DB::enableQueryLog();
        try {
            app(LmsAccessService::class)->outline(null, $lesson->course);
            $small = count(DB::getQueryLog());
            DB::flushQueryLog();
            foreach (range(1, 6) as $index) {
                $section = Section::factory()->published()->create(['course_id' => $lesson->course_id]);
                Lesson::factory()->published()->count(3)->create(['section_id' => $section->id, 'course_id' => $lesson->course_id]);
            }
            DB::flushQueryLog();
            $outline = app(LmsAccessService::class)->outline(null, $lesson->course);
            $this->assertCount(7, $outline['sections']);
            $this->assertSame($small, count(DB::getQueryLog()));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_populated_lms_history_refuses_destructive_migration_rollback(): void
    {
        $course = Course::factory()->create();
        $migration = require database_path('migrations/2026_10_07_152414_create_lms_foundation_tables.php');
        try {
            $migration->down();
            $this->fail('Populated history must require reviewed recovery.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('destructive rollback refused', $exception->getMessage());
        }
        $this->assertModelExists($course);
        $this->assertDatabaseCount('lms_courses', 1);
    }

    public function test_student_b_cannot_read_student_a_private_course_lesson_or_resource(): void
    {
        Storage::fake('local');
        $owner = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $actor = $this->actor();
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $owner->id]);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        $resource = ResourceFactory::new()->create(['external_url' => null, 'file_path' => 'resources/owned.pdf']);
        Storage::disk('local')->put('resources/owned.pdf', '%PDF-owned-private-content');
        $block = app(LmsStructureService::class)->addBlock($actor, $lesson, ['kind' => 'resource', 'resource_id' => $resource->id]);
        app(LmsAccessOperations::class)->assign($actor, $owner, $lesson, [], 'owner-lesson');
        $urls = [route('student.lms.courses.show', $course), route('student.lms.lessons.show', [$course, $lesson]), route('student.lms.resources.open', [$course, $lesson, $block])];
        $this->portal($owner);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
        $this->portal($other);
        foreach ($urls as $url) {
            $this->get($url)->assertNotFound()->assertDontSee('%PDF-owned-private-content');
        }
    }
}
