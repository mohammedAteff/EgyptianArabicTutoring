<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Students\Models\Student;
use App\Exceptions\VideoProviderUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LmsPreviewService
{
    public const SESSION_KEY = 'lms_preview';

    public function __construct(private StudentLearningService $learning, private LmsAccessService $access,
        private LmsContentService $content, private LmsQuizService $quizzes, private LmsLearningDefinition $definitions,
        private LmsVideoProfiles $profiles, private VideoDeliveryProvider $provider, private LmsVideoService $videos,
        private AuditLogService $audits) {}

    public function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);
        Gate::forUser($actor)->authorize('preview', Course::class);

        return $actor;
    }

    public function begin(Request $request, Course $course, ?int $studentId, string $requestKey): string
    {
        $actor = $this->actor($request);
        Validator::make(['request_key' => $requestKey], ['request_key' => ['required', 'uuid']])->validate();
        $course = Course::query()->findOrFail($course->id);
        abort_unless($course->status === 'published' && $course->published_at?->lte(now('UTC')), 422, 'Publish learning before opening learner preview. Draft author preview remains available in Course Studio.');
        $student = $studentId ? Student::verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')->findOrFail($studentId) : null;
        if ($student) {
            Gate::forUser($actor)->authorize('manageTeaching', $student);
        }
        abort_if($student && $course->kind === 'private' && (int) $course->owner_student_id !== $student->id, 404);
        $old = $request->session()->get(self::SESSION_KEY);
        if (is_array($old) && ((int) ($old['actor_id'] ?? 0) !== $actor->id || ! hash_equals((string) ($old['session_hash'] ?? ''), $this->sessionHash($request)))) {
            $request->session()->forget(self::SESSION_KEY);
            $old = null;
        }
        if (is_array($old) && $old['request_key'] === $requestKey) {
            abort_unless((int) $old['actor_id'] === $actor->id && (int) $old['course_id'] === $course->id
                && ($old['student_id'] ?? null) === $student?->id && $old['session_hash'] === $this->sessionHash($request), 409);
            if (CarbonImmutable::parse($old['expires_at'])->isFuture()) {
                return $old['id'];
            }
        }
        if (is_array($old)) {
            $this->end($request, $old['id'], 'replaced');
        }
        $context = ['id' => (string) Str::uuid(), 'request_key' => $requestKey, 'actor_id' => $actor->id, 'course_id' => $course->id,
            'student_id' => $student?->id, 'label' => $student ? mb_substr($student->name, 0, 160) : 'a generic learner',
            'session_hash' => $this->sessionHash($request), 'expires_at' => now('UTC')->addMinutes(15)->toIso8601String(), 'tokens' => []];
        $this->audits->log('lms_preview_started', Course::class, $course->id, null,
            ['student_id' => $student?->id, 'course_id' => $course->id, 'preview_hash' => hash('sha256', $context['id'])], $actor->id);
        $request->session()->put(self::SESSION_KEY, $context);

        return $context['id'];
    }

    /** @return array<string,mixed> */
    public function context(Request $request, string $id): array
    {
        $actor = $this->actor($request);
        $context = $request->session()->get(self::SESSION_KEY);
        abort_unless(is_array($context) && ($context['id'] ?? null) === $id && (int) ($context['actor_id'] ?? 0) === $actor->id
            && hash_equals((string) ($context['session_hash'] ?? ''), $this->sessionHash($request))
            && CarbonImmutable::parse($context['expires_at'])->isFuture(), 404, 'This preview ended. Start a new preview.');

        return $context;
    }

    public function end(Request $request, string $id, string $reason = 'exit'): void
    {
        $actor = $this->actor($request);
        $context = $request->session()->get(self::SESSION_KEY);
        if (! is_array($context)) {
            return;
        }
        abort_unless($context['id'] === $id, 404);
        if ((int) $context['actor_id'] !== $actor->id || ! hash_equals($context['session_hash'], $this->sessionHash($request))) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }
        $this->audits->log('lms_preview_exited', Course::class, (int) $context['course_id'], null,
            ['student_id' => $context['student_id'], 'course_id' => $context['course_id'], 'preview_hash' => hash('sha256', $id), 'reason_code' => $reason], $actor->id);
        $request->session()->forget(self::SESSION_KEY);
    }

    /** @return array<string,mixed> */
    public function data(Request $request, string $id, ?int $lessonId = null): array
    {
        $context = $this->context($request, $id);
        $course = Course::query()->with(['sections.lessons.blocks.videoAsset.course', 'sections.lessons.blocks.asset.course', 'sections.lessons.blocks.resource'])
            ->where('status', 'published')->where('published_at', '<=', now('UTC'))->findOrFail($context['course_id']);
        $student = $context['student_id'] ? Student::verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')->findOrFail($context['student_id']) : null;
        abort_if($student && $course->kind === 'private' && (int) $course->owner_student_id !== $student->id, 404);
        if ($student) {
            Gate::forUser($this->actor($request))->authorize('manageTeaching', $student);
            $projection = $this->learning->course($student, $course);
            $lessons = $projection ? $projection['lessonList'] : collect();
            $course = $projection['course'] ?? $course;
        } else {
            $projection = null;
            $lessons = $course->sections->where('status', 'published')->filter(fn ($section) => $section->published_at?->lte(now('UTC')))
                ->flatMap(fn ($section) => $section->lessons->where('status', 'published')->filter(fn (Lesson $lesson) => $lesson->published_at?->lte(now('UTC'))))->values();
        }
        $lesson = $lessonId ? $lessons->firstWhere('id', $lessonId) : null;
        abort_if($lessonId && ! $lesson, 404);
        $allowed = $student ? $projection !== null : true;
        $reason = $allowed ? null : 'This Student has no current access to this course.';
        if ($student && $lesson && ! $projection['progress']['lessons']->get($lesson->id)['allowed']) {
            $allowed = false;
            $reason = $projection['progress']['lessons']->get($lesson->id)['reason'];
        }
        $blocks = collect();
        if ($allowed && $lesson) {
            $lesson->loadMissing(['blocks.videoAsset.course', 'blocks.asset.course', 'blocks.resource']);
            $blocks = $lesson->blocks->where('status', 'ready')->map(fn (LessonBlock $block): array => $this->block($course, $lesson, $block, $id));
        }

        return ['title' => 'Learner preview', 'preview' => $context, 'previewStudent' => $student, 'course' => $course, 'lessons' => $lessons, 'lesson' => $lesson,
            'allowed' => $allowed, 'reason' => $reason, 'progress' => $projection['progress'] ?? null, 'blocks' => $blocks];
    }

    /** @return array<string,mixed> */
    private function block(Course $course, Lesson $lesson, LessonBlock $block, string $id): array
    {
        try {
            if ($block->kind === 'quiz') {
                $attempt = new QuizAttempt;
                $attempt->forceFill(['definition' => $this->definitions->assessment('quiz', $block->payload), 'status' => 'started', 'answers' => [], 'number' => 0]);

                return ['id' => $block->id, 'kind' => 'quiz', 'quiz' => $this->quizzes->project($attempt)];
            }
            if ($block->kind === 'assignment') {
                return ['id' => $block->id, 'kind' => 'assignment', 'definition' => $this->definitions->assessment('assignment', $block->payload)];
            }
            if ($block->kind === 'video') {
                $video = $block->videoAsset;
                if (! $video || ! $video->usableFor($course)) {
                    return ['kind' => 'unavailable'];
                }
                if ($video->provider === 'bunny') {
                    return ['id' => $block->id, 'kind' => 'video', 'label' => $video->label,
                        'authorizationUrl' => route('admin.lms.preview.video', [$id, $lesson->id, $block->id])];
                }

                return ['id' => $block->id, 'kind' => 'external_link', 'payload' => ['url' => $video->external_url]];
            }
            $data = $this->content->normalize($course, $this->content->input($block->only(['kind', 'resource_id', 'asset_id', 'payload'])),
                $lesson->blocks->pluck('asset_id')->filter()->map(fn ($value) => (int) $value)->all());

            return ['id' => $block->id, 'url' => route('admin.lms.preview.material', [$id, $lesson->id, $block->id])] + $data;
        } catch (ValidationException) {
            return ['kind' => 'unavailable'];
        }
    }

    /** @return array{Course,Lesson,LessonBlock} */
    public function target(Request $request, string $id, int $lessonId, int $blockId): array
    {
        $data = $this->data($request, $id, $lessonId);
        abort_unless($data['allowed'], 404);
        $block = $data['lesson']->blocks->firstWhere('id', $blockId);
        $rendered = $data['blocks']->firstWhere('id', $blockId);
        abort_unless($block && $rendered && $rendered['kind'] !== 'unavailable', 404);
        if ($data['previewStudent']) {
            abort_unless($this->access->canAccess($data['previewStudent'], $block), 404);
        }

        return [$data['course'], $data['lesson'], $block];
    }

    /** @return array<string,mixed> */
    public function authorizeVideo(Request $request, string $id, int $lessonId, int $blockId, bool $renew = false): array
    {
        $actor = $this->actor($request);
        $context = $this->context($request, $id);
        abort_if(app()->isProduction() && ! $request->isSecure(), 503);
        $values = Validator::make($request->only(['request_key', 'lease_token']), ['request_key' => ['required', 'uuid'], 'lease_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']])->validate();
        [$course,$lesson,$block] = $this->target($request, $id, $lessonId, $blockId);
        $asset = $block->videoAsset;
        abort_unless($block->kind === 'video' && $asset?->provider === 'bunny' && $asset->usableFor($course), 404);
        $profile = $this->profiles->effective($course, $lesson);
        if ($profile->drm_mode === 'required' || ! $profile->secure_playback || $profile->downloads_allowed) {
            throw new VideoProviderUnavailable;
        }
        $connection = $asset->connection;
        if (! $connection || (int) $connection->library_id !== (int) $asset->library_id) {
            throw new VideoProviderUnavailable;
        }
        $old = $context['tokens'][(string) $blockId] ?? null;
        if ($renew) {
            abort_unless(is_array($old) && $old['request_key'] === $values['request_key'] && hash_equals($old['proof_hash'], hash('sha256', $values['lease_token']))
                && CarbonImmutable::parse($old['expires_at'])->isFuture() && ! $old['closed']
                && $old['profile_id'] === $profile->id && $old['profile_version'] === $profile->lock_version
                && $old['connection_version'] === $connection->lock_version && $old['asset_id'] === $asset->id, 409, 'Preview video policy or session changed. Restart preview playback.');
        }
        $this->provider->verifyProtection($connection, strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)));
        $asset = $this->videos->reconcile($asset);
        abort_unless($asset->status === 'ready' && $asset->usableFor($course), 503);
        [$url,$ends] = DB::transaction(function () use ($request, $id, $lessonId, $blockId, $context, $asset, $profile, $connection): array {
            $subject = $context['student_id'] ? Student::query()->lockForUpdate()->findOrFail($context['student_id']) : null;
            Course::query()->lockForUpdate()->findOrFail($context['course_id']);
            $this->context($request, $id);
            [$currentCourse,$currentLesson,$currentBlock] = $this->target($request, $id, $lessonId, $blockId);
            $currentProfile = $this->profiles->effective($currentCourse, $currentLesson);
            $currentProfile = ProtectionProfile::query()->lockForUpdate()->findOrFail($currentProfile->id);
            $currentConnection = VideoProviderConnection::query()->lockForUpdate()->findOrFail($connection->id);
            $currentAsset = VideoAsset::query()->lockForUpdate()->findOrFail($asset->id);
            abort_unless($currentBlock->video_asset_id === $asset->id && $currentAsset->provider_video_id === $asset->provider_video_id
                && $currentAsset->provider_connection_id === $currentConnection->id && (int) $currentAsset->library_id === (int) $currentConnection->library_id
                && $currentAsset->usableFor($currentCourse) && $currentProfile->id === $profile->id && $currentProfile->lock_version === $profile->lock_version
                && $currentProfile->secure_playback && ! $currentProfile->downloads_allowed && $currentProfile->drm_mode !== 'required'
                && $currentConnection->enabled && $currentConnection->lock_version === $connection->lock_version, 409);
            $ends = CarbonImmutable::now('UTC')->addSeconds(min(120, $profile->token_seconds))->min(CarbonImmutable::parse($context['expires_at']));
            if ($subject) {
                $subjectEnds = $this->access->accessEndings($subject, collect([$currentCourse]), $currentLesson)[$currentCourse->id];
                if ($subjectEnds) {
                    $ends = $ends->min($subjectEnds);
                }
            }
            abort_unless($ends->timestamp > now('UTC')->timestamp, 404);

            return [$this->provider->playbackUrl($currentConnection, $currentAsset->provider_video_id, $ends->timestamp), $ends];
        }, 3);
        $context['tokens'] = array_filter($context['tokens'], fn (array $token): bool => CarbonImmutable::parse($token['expires_at'])->isFuture());
        abort_if(count($context['tokens']) >= 16 && ! isset($context['tokens'][(string) $blockId]), 429);
        $context['tokens'][(string) $blockId] = ['request_key' => $values['request_key'], 'proof_hash' => hash('sha256', $values['lease_token']),
            'expires_at' => $ends->toIso8601String(), 'profile_id' => $profile->id, 'profile_version' => $profile->lock_version,
            'connection_version' => $connection->lock_version, 'asset_id' => $asset->id, 'closed' => false];
        $request->session()->put(self::SESSION_KEY, $context);

        return ['url' => $url, 'lease_id' => 0, 'expires_at' => $ends->toIso8601String(), 'heartbeat_seconds' => $profile->heartbeat_seconds,
            'renew_url' => route('admin.lms.preview.video.renew', [$id, $lessonId, $blockId]), 'close_url' => route('admin.lms.preview.video.close', [$id, $blockId]),
            'watermark' => 'STAFF PREVIEW · '.strtoupper(substr(hash('sha256', $id), 0, 8)), 'drm' => 'not_configured'];
    }

    public function closeVideo(Request $request, string $id, int $blockId): void
    {
        $context = $this->context($request, $id);
        $token = $context['tokens'][(string) $blockId] ?? null;
        abort_unless(is_array($token) && hash_equals($token['proof_hash'], hash('sha256', (string) $request->input('lease_token'))), 404);
        $context['tokens'][(string) $blockId]['closed'] = true;
        $request->session()->put(self::SESSION_KEY, $context);
    }

    private function sessionHash(Request $request): string
    {
        return hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));
    }
}
