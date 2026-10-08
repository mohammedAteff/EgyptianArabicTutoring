<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\StudentLearningStateService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentLearningTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(Student $student): static
    {
        return $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
    }

    private function lesson(?Student $owner = null): Lesson
    {
        $course = Course::factory()->published()->create($owner ? ['kind' => 'private', 'owner_student_id' => $owner->id, 'title' => 'OWNER ONLY COURSE'] : ['title' => 'العربية · Arabic foundations']);
        $section = Section::factory()->published()->create(['course_id' => $course->id, 'title' => 'Getting started']);

        return Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'أهلاً · Greetings']);
    }

    private function grant(Student $student, Course|Lesson $target, array $data = []): AccessGrant
    {
        return app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $student, $target, $data, 'learning-'.Str::uuid());
    }

    private function video(Lesson $lesson, string $url = 'https://example.test/lesson.mp4'): LessonBlock
    {
        return LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'external_video', 'status' => 'ready', 'payload' => ['url' => $url]]);
    }

    public function test_hub_uses_canonical_shell_and_deduplicates_independent_entitlements_without_progress(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        $this->grant($student, $lesson);
        $response = $this->signIn($student)->get(route('student.learning.index'));
        $response->assertOk()->assertViewHas('cards', fn ($cards) => $cards->count() === 1)->assertSee('My Learning')->assertSee('Continue Learning')->assertSee('Completed')->assertSee('Tutoring work')->assertSee(route('student.teaching.index'), false)->assertSee('0%')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('lms_lesson_progress', 0);
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('student_notifications', 0);
    }

    public function test_private_assignment_is_owner_only_in_hub_course_lesson_and_item_routes(): void
    {
        $a = Student::factory()->verified()->create();
        $b = Student::factory()->verified()->create();
        $lesson = $this->lesson($a);
        $assignment = app(LmsAccessOperations::class)->assign(AdministratorFactory::new()->create(), $a, $lesson, ['instructions' => 'OWNER ONLY INSTRUCTIONS'], 'private-learning');
        $this->signIn($a)->get(route('student.learning.index'))->assertSee('OWNER ONLY COURSE')->assertSee('OWNER ONLY INSTRUCTIONS');
        $this->get(route('student.learning.assignments.show', $assignment))->assertOk()->assertSee('OWNER ONLY INSTRUCTIONS');
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk();
        $this->signIn($b)->get(route('student.learning.index', ['student_id' => $a->id]))->assertOk()->assertDontSee('OWNER ONLY COURSE')->assertDontSee('OWNER ONLY INSTRUCTIONS')->assertViewHas('cards', fn ($cards) => $cards->isEmpty());
        $this->get(route('student.learning.courses.show', $lesson->course))->assertNotFound()->assertDontSee('OWNER ONLY COURSE')->assertDontSee('OWNER ONLY INSTRUCTIONS');
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertNotFound()->assertDontSee('أهلاً');
        $this->get(route('student.learning.assignments.show', $assignment))->assertNotFound()->assertDontSee('OWNER ONLY INSTRUCTIONS');
        $this->assertSame(0, LearningVisit::query()->where('student_id', $b->id)->count());
    }

    public function test_scoped_grant_filters_curriculum_and_previous_next_without_sibling_leaks(): void
    {
        $student = Student::factory()->verified()->create();
        $first = $this->lesson();
        $hidden = Lesson::factory()->published()->create(['course_id' => $first->course_id, 'section_id' => $first->section_id, 'title' => 'RESTRICTED SIBLING', 'sort_order' => 1]);
        $last = Lesson::factory()->published()->create(['course_id' => $first->course_id, 'section_id' => $first->section_id, 'title' => 'Available last lesson', 'sort_order' => 2]);
        $this->grant($student, $first);
        $this->grant($student, $last);
        $this->signIn($student)->get(route('student.learning.courses.show', $first->course))->assertOk()->assertDontSee('RESTRICTED SIBLING')->assertViewHas('lessonList', fn ($items) => $items->pluck('id')->all() === [$first->id, $last->id]);
        $this->get(route('student.learning.lessons.show', [$first->course, $first]))->assertOk()->assertDontSee('RESTRICTED SIBLING')->assertViewHas('previousLesson', null)->assertViewHas('nextLesson', fn ($item) => $item->id === $last->id);
        $this->get(route('student.learning.lessons.show', [$first->course, $hidden]))->assertNotFound()->assertDontSee('RESTRICTED SIBLING');
        $this->get(route('student.learning.lessons.show', [$first->course, $last]))->assertViewHas('previousLesson', fn ($item) => $item->id === $first->id)->assertViewHas('nextLesson', null);
    }

    public function test_last_view_keeps_last_lesson_on_overview_and_filters_later_revocation(): void
    {
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $grant = $this->grant($student, $lesson->course);
        $this->signIn($student)->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk();
        $this->travel(1)->minutes();
        $this->get(route('student.learning.courses.show', $lesson->course))->assertOk();
        $this->assertDatabaseCount('lms_learning_visits', 1);
        $this->assertDatabaseHas('lms_learning_visits', ['student_id' => $student->id, 'course_id' => $lesson->course_id, 'lesson_id' => $lesson->id, 'accessed_at' => now('UTC')->format('Y-m-d H:i:s')]);
        $this->get(route('student.learning.index'))->assertViewHas('continueLearning', fn ($items) => $items->first()['url'] === route('student.learning.lessons.show', [$lesson->course, $lesson]));
        app(LmsAccessOperations::class)->change(AdministratorFactory::new()->create(), $grant, 'revoke', [], 1, 'revoke-view');
        $this->get(route('student.learning.index'))->assertViewHas('continueLearning', fn ($items) => $items->isEmpty())->assertDontSee($lesson->title);
        $this->get(route('student.learning.courses.show', $lesson->course))->assertNotFound()->assertSee('Your access has been withdrawn.');
    }

    public function test_continue_falls_back_to_course_when_last_lesson_is_unpublished(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        $this->signIn($student)->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk();
        $lesson->forceFill(['status' => 'unpublished'])->save();
        $this->get(route('student.learning.index'))->assertViewHas('continueLearning', fn ($items) => $items->first()['lesson'] === null && $items->first()['url'] === route('student.learning.courses.show', $lesson->course));
    }

    public static function unavailableStates(): iterable
    {
        yield 'future' => ['future', 'Your access starts'];
        yield 'expired' => ['expired', 'Your access expired'];
        yield 'revoked' => ['revoked', 'Your access has been withdrawn.'];
        yield 'draft' => ['draft', 'not currently published'];
        yield 'unpublished' => ['unpublished', 'not currently published'];
        yield 'archived' => ['archived', 'no longer available'];
    }

    #[DataProvider('unavailableStates')]
    public function test_unavailable_owned_course_has_clear_state_but_no_content(string $state, string $copy): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 7)->setTime(12, 0));
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $data = match ($state) {
            'future' => ['starts_at' => '2026-10-08T12:00:00Z'],
            'expired' => ['access_mode' => 'fixed', 'starts_at' => '2026-10-06T10:00:00Z', 'expires_at' => '2026-10-07T11:00:00Z'],
            default => [],
        };
        $grant = $this->grant($student, $lesson->course, $data);
        if ($state === 'revoked') {
            app(LmsAccessOperations::class)->change(AdministratorFactory::new()->create(), $grant, 'revoke', [], 1, 'state-revoke');
        } elseif (in_array($state, ['draft', 'unpublished', 'archived'], true)) {
            $lesson->course->forceFill(['status' => $state])->save();
        }
        $this->signIn($student)->get(route('student.learning.index'))->assertViewHas('cards', fn ($cards) => $cards->isEmpty())->assertDontSee($lesson->course->title);
        $this->get(route('student.learning.courses.show', $lesson->course))->assertNotFound()->assertSee($copy)->assertDontSee($lesson->course->title)->assertDontSee($lesson->title);
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertNotFound()->assertDontSee($lesson->title);
        $this->postJson(route('student.learning.notes.store', [$lesson->course, $lesson]), ['body' => 'Denied'])->assertNotFound();
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('lms_lesson_notes', 0);
    }

    public function test_expiry_uses_union_of_sources_and_canonical_student_timezone(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 7)->setTime(12, 0));
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course, ['access_mode' => 'fixed', 'starts_at' => '2026-10-07T10:00:00Z', 'expires_at' => '2026-10-08T12:00:00Z']);
        $this->grant($student, $lesson->course, ['access_mode' => 'relative', 'relative_days' => 2]);
        $this->signIn($student)->get(route('student.learning.index'))->assertSee('9 Oct 2026, 15:00 EEST');
        $this->grant($student, $lesson->course);
        $this->get(route('student.learning.index'))->assertSee('no scheduled end')->assertDontSee('9 Oct 2026, 15:00');
    }

    public function test_native_request_id_tampering_cannot_change_student_or_nested_scope(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $one = $this->lesson();
        $two = $this->lesson();
        $this->grant($student, $one->course);
        $this->grant($student, $two->course);
        $this->signIn($student)->get(route('student.learning.lessons.show', [$one->course, $two]))->assertNotFound();
        $this->postJson(route('student.learning.notes.store', [$one->course, $two]), ['body' => 'Injected', 'student_id' => $other->id])->assertNotFound();
        $this->post(route('student.learning.notes.store', [$one->course, $one]), ['body' => 'Owned body', 'student_id' => $other->id, 'lesson_id' => $two->id, 'lock_version' => 200])->assertRedirect();
        $this->assertDatabaseHas('lms_lesson_notes', ['student_id' => $student->id, 'lesson_id' => $one->id, 'lock_version' => 1]);
        $this->assertSame(0, LessonNote::query()->where('student_id', $other->id)->count());
    }

    public function test_lesson_expiry_does_not_borrow_a_later_sibling_grant(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 7)->setTime(12, 0));
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $sibling = Lesson::factory()->published()->create(['course_id' => $lesson->course_id, 'section_id' => $lesson->section_id, 'sort_order' => 1]);
        $this->grant($student, $lesson, ['access_mode' => 'relative', 'relative_days' => 1]);
        $this->grant($student, $sibling, ['access_mode' => 'relative', 'relative_days' => 3]);
        $this->signIn($student)->get(route('student.learning.index'))->assertSee('10 Oct 2026, 15:00 EEST');
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertViewHas('accessEndsAt', fn ($instant) => $instant->format('Y-m-d H:i:s') === '2026-10-08 12:00:00')->assertSee('8 Oct 2026, 15:00 EEST')->assertDontSee('10 Oct 2026, 15:00');
    }

    public function test_private_notes_create_edit_delete_escape_and_reject_foreign_ids_and_stale_edits(): void
    {
        $a = Student::factory()->verified()->create();
        $b = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($a, $lesson->course);
        $this->grant($b, $lesson->course);
        $url = route('student.learning.notes.store', [$lesson->course, $lesson]);
        $this->signIn($a)->post($url, ['body' => '<script>PRIVATE NOTE</script> ملاحظتي'])->assertRedirect();
        $note = LessonNote::query()->firstOrFail();
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertSee('&lt;script&gt;PRIVATE NOTE&lt;/script&gt;', false)->assertDontSee('<script>PRIVATE NOTE</script>', false);
        $this->signIn($b)->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertDontSee('PRIVATE NOTE');
        $this->get(route('student.learning.notes.index', ['student_id' => $a->id]))->assertDontSee('PRIVATE NOTE');
        $this->patchJson(route('student.learning.notes.update', [$lesson->course, $lesson, $note]), ['body' => 'Stolen', 'version' => 1])->assertNotFound();
        $this->deleteJson(route('student.learning.notes.destroy', $note), ['version' => 1])->assertNotFound();
        $this->signIn($a)->patch(route('student.learning.notes.update', [$lesson->course, $lesson, $note]), ['body' => 'Updated private text', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('lms_lesson_notes', ['id' => $note->id, 'body' => 'Updated private text', 'lock_version' => 2]);
        $this->patchJson(route('student.learning.notes.update', [$lesson->course, $lesson, $note]), ['body' => 'Stale', 'version' => 1])->assertConflict();
        $this->deleteJson(route('student.learning.notes.destroy', $note), ['version' => 1])->assertConflict();
        $this->delete(route('student.learning.notes.destroy', $note), ['version' => 2])->assertRedirect(route('student.learning.notes.index'));
        $this->assertDatabaseMissing('lms_lesson_notes', ['id' => $note->id]);
    }

    public function test_private_note_read_and_deletion_remain_available_after_expiration(): void
    {
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course, ['access_mode' => 'relative', 'relative_days' => 1]);
        $note = app(StudentLearningStateService::class)->saveNote($student, $lesson->course, $lesson, 'My retained private note');
        $this->travel(1)->days();
        $this->signIn($student)->get(route('student.learning.notes.index'))->assertOk()->assertSee('My retained private note')->assertDontSee($lesson->title)->assertDontSee($lesson->course->title);
        $this->patchJson(route('student.learning.notes.update', [$lesson->course, $lesson, $note]), ['body' => 'Expired edit', 'version' => 1])->assertNotFound();
        $this->delete(route('student.learning.notes.destroy', $note), ['version' => 1])->assertRedirect();
        $this->assertDatabaseMissing('lms_lesson_notes', ['id' => $note->id]);
    }

    public function test_bookmarks_use_real_direct_video_timestamps_are_idempotent_and_private(): void
    {
        $a = Student::factory()->verified()->create();
        $b = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($a, $lesson->course);
        $this->grant($b, $lesson->course);
        $video = $this->video($lesson);
        $url = route('student.learning.bookmarks.store', [$lesson->course, $lesson]);
        $this->signIn($a)->post($url, ['block_id' => $video->id, 'seconds' => 83.456, 'label' => '<script>PRIVATE BOOKMARK</script>', 'student_id' => $b->id])->assertRedirect();
        $this->post($url, ['block_id' => $video->id, 'seconds' => 83.456, 'label' => '<script>PRIVATE BOOKMARK</script>'])->assertRedirect();
        $this->assertDatabaseCount('lms_lesson_bookmarks', 1);
        $bookmark = LessonBookmark::query()->firstOrFail();
        $this->assertSame(83456, $bookmark->position_milliseconds);
        $this->assertSame($a->id, $bookmark->student_id);
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertSee('1:23')->assertSee('&lt;script&gt;PRIVATE BOOKMARK&lt;/script&gt;', false)->assertDontSee('<script>PRIVATE BOOKMARK</script>', false);
        $this->signIn($b)->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertDontSee('PRIVATE BOOKMARK')->assertViewHas('bookmarks', fn ($bookmarks) => $bookmarks->isEmpty());
        $this->deleteJson(route('student.learning.bookmarks.destroy', [$lesson->course, $lesson, $bookmark]))->assertNotFound();
        $this->signIn($a)->delete(route('student.learning.bookmarks.destroy', [$lesson->course, $lesson, $bookmark]))->assertRedirect();
        $this->assertDatabaseMissing('lms_lesson_bookmarks', ['id' => $bookmark->id]);
    }

    public function test_bookmarks_reject_foreign_block_nested_ids_and_unsupported_provider(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $foreign = $this->lesson();
        $this->grant($student, $lesson->course);
        $foreignBlock = $this->video($foreign);
        $youtube = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'youtube_video', 'status' => 'ready', 'payload' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']]);
        $this->signIn($student)->postJson(route('student.learning.bookmarks.store', [$lesson->course, $lesson]), ['block_id' => $foreignBlock->id, 'seconds' => 10])->assertNotFound();
        $this->postJson(route('student.learning.bookmarks.store', [$lesson->course, $lesson]), ['block_id' => $youtube->id, 'seconds' => 10])->assertUnprocessable()->assertJsonValidationErrors('block_id')->assertSee('Timestamp bookmarks are available for direct videos.');
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertSee('www.youtube-nocookie.com/embed/dQw4w9WgXcQ')->assertDontSee('data-lms-save-bookmark', false);
        $this->assertDatabaseCount('lms_lesson_bookmarks', 0);
    }

    public static function invalidPersonalInput(): iterable
    {
        yield 'empty note' => ['notes', ['body' => ''], 'body', 'The body field is required.'];
        yield 'long note' => ['notes', ['body' => str_repeat('a', 10001)], 'body', 'The body field must not be greater than 10000 characters.'];
        yield 'missing timestamp' => ['bookmarks', ['block_id' => 1], 'seconds', 'The seconds field is required.'];
        yield 'negative timestamp' => ['bookmarks', ['block_id' => 1, 'seconds' => -1], 'seconds', 'The seconds field must be between 0 and 86400.'];
        yield 'long timestamp' => ['bookmarks', ['block_id' => 1, 'seconds' => 86401], 'seconds', 'The seconds field must be between 0 and 86400.'];
        yield 'string timestamp' => ['bookmarks', ['block_id' => 1, 'seconds' => 'now'], 'seconds', 'The seconds field must be a number.'];
        yield 'long label' => ['bookmarks', ['block_id' => 1, 'seconds' => 0, 'label' => str_repeat('b', 501)], 'label', 'The label field must not be greater than 500 characters.'];
    }

    #[DataProvider('invalidPersonalInput')]
    public function test_personal_input_validation_is_visible_and_writes_nothing(string $kind, array $values, string $field, string $message): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        $this->signIn($student)->postJson(route('student.learning.'.$kind.'.store', [$lesson->course, $lesson]), $values)->assertUnprocessable()->assertJsonValidationErrors($field)->assertSee($message);
        $this->assertDatabaseCount('lms_lesson_notes', 0);
        $this->assertDatabaseCount('lms_lesson_bookmarks', 0);
    }

    public function test_mixed_content_is_sanitized_and_bunny_placeholder_never_exposes_url(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>أهلاً Hello</p><script>INJECTED</script><iframe src="https://evil.test"></iframe>']]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'external_link', 'status' => 'ready', 'payload' => ['url' => 'javascript:alert(1)']]);
        $this->video($lesson, 'https://vimeo.com/123456');
        $this->video($lesson);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'bunny_video', 'status' => 'placeholder', 'payload' => null]);
        $this->signIn($student)->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk()->assertSee('أهلاً Hello')->assertSee('dir="auto"', false)->assertSee('player.vimeo.com/video/123456')->assertSee('data-lms-video', false)->assertSee('Protected video playback is not available yet')->assertSee('This content is currently unavailable.')->assertDontSee('PERMANENT-UNSAFE')->assertDontSee('javascript:alert')->assertDontSee('<script>INJECTED', false)->assertDontSee('evil.test');
    }

    public function test_current_published_resources_and_private_course_files_require_nested_access(): void
    {
        Storage::fake('local');
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $a = Student::factory()->verified()->create();
        $b = Student::factory()->verified()->create();
        $lesson = $this->lesson($a);
        $this->grant($a, $lesson);
        $resource = ResourceFactory::new()->create(['status' => 'published', 'external_url' => null, 'published_at' => now('UTC')->subMinute(), 'file_path' => 'resources/PRIVATE.pdf']);
        Storage::disk('local')->put('resources/PRIVATE.pdf', '%PDF-1.4 PRIVATE');
        $resourceBlock = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'resource', 'status' => 'ready', 'resource_id' => $resource->id]);
        $course = $lesson->course;
        $asset = app(CourseStudioService::class)->upload($actor, $course, UploadedFile::fake()->image('private-picture.png', 20, 20), 'image', $course->lock_version);
        $imageBlock = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'image', 'status' => 'ready', 'asset_id' => $asset->id, 'payload' => ['alt' => 'Arabic picture']]);
        $this->signIn($a)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk()->assertSee('Arabic picture')->assertDontSee($asset->path)->assertDontSee('resources/PRIVATE.pdf');
        $this->get(route('student.learning.materials.open', [$course, $lesson, $imageBlock]))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('student.learning.materials.open', [$course, $lesson, $resourceBlock]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->signIn($b)->get(route('student.learning.materials.open', [$course, $lesson, $imageBlock]))->assertNotFound();
        $this->get(route('student.learning.materials.open', [$course, $lesson, $resourceBlock]))->assertNotFound();
        $this->get(route('student.lms.resources.open', [$course, $lesson, $resourceBlock]))->assertNotFound();
        $this->signIn($a)->get(route('student.learning.materials.open', [$this->lesson()->course, $lesson, $imageBlock]))->assertNotFound();
        $resource->update(['status' => 'unpublished']);
        $this->get(route('student.learning.materials.open', [$course, $lesson, $resourceBlock]))->assertNotFound();
        Storage::disk('local')->delete($asset->path);
        $this->get(route('student.learning.materials.open', [$course, $lesson, $imageBlock]))->assertNotFound();
    }

    public function test_guests_staff_and_expired_canonical_sessions_cannot_use_lms_html_or_writes(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        foreach ([route('student.learning.index'), route('student.learning.notes.index'), route('student.learning.courses.show', $lesson->course), route('student.learning.lessons.show', [$lesson->course, $lesson])] as $url) {
            $this->get($url)->assertRedirect(route('student.login'));
        }
        $this->post(route('student.learning.notes.store', [$lesson->course, $lesson]), ['body' => 'Guest'])->assertRedirect(route('student.login'));
        $this->actingAs(AdministratorFactory::new()->create(), 'web')->get(route('student.learning.notes.index'))->assertRedirect(route('student.login'));
        $this->signIn($student)->withSession(['student_auth_expires_at' => now('UTC')->subSecond()->toIso8601String()])->get(route('student.learning.index'))->assertRedirect(route('student.login'));
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('lms_lesson_notes', 0);
    }

    public function test_canonical_merge_keeps_latest_visit_and_reassigns_private_notes_and_bookmarks(): void
    {
        $this->freezeTime();
        $actor = AdministratorFactory::new()->create();
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($primary, $lesson->course);
        $this->grant($secondary, $lesson->course);
        $video = $this->video($lesson);
        $state = app(StudentLearningStateService::class);
        $state->visit($primary, $lesson->course);
        $this->travel(1)->minutes();
        $state->visit($secondary, $lesson->course, $lesson);
        $note = $state->saveNote($secondary, $lesson->course, $lesson, 'Merged private note');
        $bookmark = $state->bookmark($secondary, $lesson->course, $lesson, $video->id, 20, 'Merged bookmark');
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $actor->id);
        $this->assertDatabaseCount('lms_learning_visits', 1);
        $this->assertDatabaseHas('lms_learning_visits', ['student_id' => $primary->id, 'lesson_id' => $lesson->id, 'accessed_at' => now('UTC')->format('Y-m-d H:i:s')]);
        $this->assertSame($primary->id, $note->fresh()->student_id);
        $this->assertSame($primary->id, $bookmark->fresh()->student_id);
        $this->assertSame(2, $note->fresh()->lock_version);
        $this->signIn($secondary)->get(route('student.learning.notes.index'))->assertRedirect(route('student.login'));
    }

    public function test_privacy_erases_personal_data_without_erasing_shared_catalog_content(): void
    {
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        $this->grant($other, $lesson->course);
        $video = $this->video($lesson);
        $state = app(StudentLearningStateService::class);
        $state->visit($student, $lesson->course, $lesson);
        $state->saveNote($student, $lesson->course, $lesson, 'Erase my body');
        $state->bookmark($student, $lesson->course, $lesson, $video->id, 10, 'Erase my label');
        $otherNote = $state->saveNote($other, $lesson->course, $lesson, 'Keep other owner');
        app(StudentPrivacyService::class)->anonymize($student->id, $actor->id);
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('lms_lesson_bookmarks', 0);
        $this->assertDatabaseMissing('lms_lesson_notes', ['student_id' => $student->id]);
        $this->assertDatabaseHas('lms_lesson_notes', ['id' => $otherNote->id, 'body' => 'Keep other owner']);
        $this->assertSame('published', $lesson->fresh()->status);
    }

    public function test_learning_visits_keep_existing_notification_deduplication_and_read_state(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed']);
        $this->signIn($student)->get(route('student.learning.index'))->assertOk();
        $notification = StudentNotification::query()->where('student_id', $student->id)->firstOrFail();
        $this->patch(route('student.notifications.update', $notification))->assertRedirect();
        $readAt = $notification->fresh()->read_at->format('Y-m-d H:i:s');
        $this->get(route('student.learning.courses.show', $lesson->course))->assertOk();
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk();
        $this->get(route('student.learning.index'))->assertOk();
        $this->assertSame(1, StudentNotification::query()->where('student_id', $student->id)->count());
        $this->assertSame($readAt, $notification->fresh()->read_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, DB::table('analytics_events')->where('event_name', 'like', 'lms_%')->count());
    }

    public function test_pdf_asset_uses_private_delivery_and_expired_content_refuses_files_and_bookmarks(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson($student);
        $this->grant($student, $lesson, ['access_mode' => 'relative', 'relative_days' => 1]);
        $course = $lesson->course;
        $asset = app(CourseStudioService::class)->upload(AdministratorFactory::new()->create(['role' => 'admin']), $course, UploadedFile::fake()->createWithContent('practice.pdf', "%PDF-1.4\nPrivate lesson"), 'file', $course->lock_version);
        $file = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'file', 'status' => 'ready', 'asset_id' => $asset->id, 'payload' => ['label' => 'Private PDF']]);
        $video = $this->video($lesson);
        $this->signIn($student)->get(route('student.learning.materials.open', [$course, $lesson, $file]))->assertOk()->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('Cache-Control', 'no-store, private');
        $this->travel(1)->days();
        $this->signIn($student)->get(route('student.learning.materials.open', [$course, $lesson, $file]))->assertNotFound();
        $this->postJson(route('student.learning.bookmarks.store', [$course, $lesson]), ['block_id' => $video->id, 'seconds' => 10])->assertNotFound();
        $this->assertDatabaseCount('lms_lesson_bookmarks', 0);
    }

    public static function hiddenAncestors(): iterable
    {
        foreach (['section', 'lesson'] as $target) {
            foreach (['draft', 'unpublished', 'archived'] as $status) {
                yield $target.'-'.$status => [$target, $status];
            }
        }
    }

    #[DataProvider('hiddenAncestors')]
    public function test_player_refuses_hidden_ancestors_and_keeps_them_out_of_curriculum(string $target, string $status): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $this->grant($student, $lesson->course);
        ($target === 'lesson' ? $lesson : $lesson->section)->forceFill(['status' => $status])->save();
        $this->signIn($student)->get(route('student.learning.courses.show', $lesson->course))->assertOk()->assertDontSee($lesson->title);
        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertNotFound()->assertDontSee($lesson->title);
        $this->assertDatabaseHas('lms_learning_visits', ['student_id' => $student->id, 'lesson_id' => null]);
    }

    public function test_real_published_student_views_render_for_mixed_direction_ui_review(): void
    {
        $student = Student::factory()->verified()->create();
        $lesson = $this->lesson();
        $lesson->forceFill(['title' => 'أهلاً وسهلاً · Your first conversation'])->save();
        $this->grant($student, $lesson->course, ['access_mode' => 'relative', 'relative_days' => 30]);
        Lesson::factory()->published()->create(['course_id' => $lesson->course_id, 'section_id' => $lesson->section_id, 'title' => 'Introduce yourself · عرّف بنفسك', 'sort_order' => 1]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<h2>One conversation at a time</h2><p>Build a confident foundation in Egyptian Arabic. Listen, practise out loud, and keep the phrases you want to remember.</p><h3>أهلاً! إزيّك؟</h3><p>Welcome! How are you? Try greeting someone you know.</p>']]);
        $video = $this->video($lesson);
        $state = app(StudentLearningStateService::class);
        $state->saveNote($student, $lesson->course, $lesson, "إزيّك؟ · How are you?\nPractise the greeting before my next session.");
        $state->bookmark($student, $lesson->course, $lesson, $video->id, 83, 'The greeting · التحية');
        $private = $this->lesson($student);
        $private->course->forceFill(['title' => 'Conversation practice · تدريب المحادثة'])->save();
        app(LmsAccessOperations::class)->assign(AdministratorFactory::new()->create(), $student, $private, ['instructions' => 'Try this short conversation and bring your questions to our next lesson.'], 'review-private');
        $this->signIn($student);
        $viewer = $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk()->assertSee('One conversation at a time')->assertSee('The greeting');
        $overview = $this->get(route('student.learning.courses.show', $lesson->course))->assertOk()->assertSee('2 available lessons');
        $hub = $this->get(route('student.learning.index'))->assertOk()->assertSee('Conversation practice')->assertSee('Your first conversation');
        $directory = getenv('STUDENT_LEARNING_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            file_put_contents($directory.'/student-learning-hub.html', $hub->getContent());
            file_put_contents($directory.'/student-learning-course.html', $overview->getContent());
            file_put_contents($directory.'/student-learning-lesson.html', $viewer->getContent());
        }
    }
}
