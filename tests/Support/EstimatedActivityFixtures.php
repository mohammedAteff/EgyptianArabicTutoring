<?php

namespace Tests\Support;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Reporting\Services\EstimatedActivityDefinition;
use App\Domains\Reporting\Services\EstimatedActivitySynthetic;
use App\Domains\Reporting\Services\StudentInsightsContext;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait EstimatedActivityFixtures
{
    private string $activityStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityStorage = storage_path('framework/testing/activity-'.Str::uuid());
        File::makeDirectory($this->activityStorage, 0700, true);
        foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private'] as $directory) {
            File::makeDirectory($this->activityStorage.'/'.$directory, 0700, true);
        }
        app()->useStoragePath($this->activityStorage);
        config(['estimated_activity.mode' => 'off', 'estimated_activity.run_id' => null]);
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->activityStorage);
        parent::tearDown();
    }

    private function activityRun(Student|Administrator ...$principals): string
    {
        $synthetic = app(EstimatedActivitySynthetic::class);
        $run = $synthetic->beginRun(CarbonImmutable::now('UTC')->addHour());
        foreach ($principals as $principal) {
            $synthetic->grant($run, $principal, CarbonImmutable::now('UTC')->addMinutes(50));
        }

        return $run;
    }

    private function activityStudent(Student $student): static
    {
        $this->actingAs($student, 'student');

        return $this->activitySession([
            'student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(),
        ]);

    }

    private function activityAdmin(Administrator $administrator): static
    {
        $this->actingAs($administrator, 'web');

        return $this->activitySession([
            'admin.session_fingerprint' => app(AdministratorTwoFactorService::class)->fingerprint($administrator),
        ]);

    }

    private function activitySession(array $data): static
    {
        $this->withSession($data);
        session()->save();

        return $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    }

    private function activityLesson(Student $student, string $kind = 'rich_text'): LessonBlock
    {
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'activity-'.Str::uuid());

        return LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => $kind, 'status' => 'ready', 'payload' => ['html' => '<p>Private fixture learning text.</p>']]);
    }

    private function activityAnchor(LessonBlock $block, ?string $stream = null): array
    {
        return $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => $stream ?? (string) Str::uuid()])
            ->assertOk()->assertJsonPath('seconds', 0)->json();
    }

    /** Compact synthetic accounting evidence; ingestion bounds are exercised separately. */
    private function activityInterval(Student|Administrator $principal, string $start, string $end, string $category = 'lms', int $record = 1, ?string $stream = null): int
    {
        $grant = DB::table('estimated_activity_grants')->where('principal_type', $principal instanceof Student ? 'student' : 'admin')->where('principal_id', $principal->id)->firstOrFail();

        return DB::table('estimated_activity_intervals')->insertGetId([
            'run_id' => $grant->run_id, 'grant_id' => $grant->id, 'stream_id' => $stream ?? (string) Str::uuid(),
            'student_id' => $principal instanceof Student ? $principal->id : null,
            'administrator_id' => $principal instanceof Administrator ? $principal->id : null,
            'workspace_id' => $principal instanceof Administrator ? app(TutorWorkWorkspace::class)->ready()->id : null,
            'definition' => EstimatedActivityDefinition::VERSION, 'generation' => 1, 'sequence' => 1,
            'kind' => $principal instanceof Student ? 'student_lesson' : 'admin_course', 'record_id' => $record, 'category' => $category,
            'started_at' => $start, 'ended_at' => $end,
        ]);
    }

    private function activityPeriod(?Student $student = null, array $filters = []): StudentInsightsContext
    {
        return StudentInsightsContext::fromRequest(request()->duplicate($filters + ['range' => 'today', 'timezone' => 'UTC']), $student, true);
    }
}
