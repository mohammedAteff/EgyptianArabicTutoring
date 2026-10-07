<?php

namespace App\Domains\Lms\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentSessionContext;
use App\Domains\Students\Services\TeachingRecordService;
use App\Exceptions\VideoProviderUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProtectedPlaybackService
{
    public function __construct(private StudentSessionContext $sessions, private TeachingRecordService $students,
        private LmsAccessService $access, private LmsVideoProfiles $profiles, private VideoDeviceService $devices,
        private VideoDeliveryProvider $provider, private LmsVideoService $videos, private AuditLogService $audits) {}

    /** @return array<string,mixed> */
    public function authorize(Request $request, Course $course, Lesson $lesson, LessonBlock $block, ?int $leaseId = null): array
    {
        $student = $this->sessions->current($request);
        abort_unless($student !== null, 404);
        abort_if(app()->isProduction() && ! $request->isSecure(), 503);
        $values = Validator::make($request->only(['request_key', 'lease_token']), [
            'request_key' => ['required', 'uuid'], 'lease_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();
        [$course, $lesson, $block, $asset] = $this->target($student, $course->id, $lesson->id, $block->id);
        $profile = $this->profiles->effective($course, $lesson);
        if ($profile->drm_mode === 'required' || ! $profile->secure_playback || $profile->downloads_allowed) {
            throw new VideoProviderUnavailable;
        }
        $connection = $asset->connection;
        if (! $connection || (int) $connection->library_id !== (int) $asset->library_id) {
            throw new VideoProviderUnavailable;
        }
        $this->provider->verifyProtection($connection, strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)));
        $asset = $this->videos->reconcile($asset);
        abort_unless($asset->status === 'ready', 503, 'This video is still processing or unavailable.');
        $connectionVersion = $connection->lock_version;
        $profileVersion = $profile->lock_version;

        return DB::transaction(function () use ($request, $student, $course, $lesson, $block, $asset, $connection, $connectionVersion, $profile, $profileVersion, $values, $leaseId): array {
            $student = $this->students->lockStudent($student->id);
            abort_unless($this->sessions->current($request)?->id === $student->id, 404);
            [$course, $lesson, $block, $currentAsset] = $this->target($student, $course->id, $lesson->id, $block->id, true);
            $currentProfile = $this->profiles->effective($course, $lesson);
            $currentProfile = ProtectionProfile::query()->lockForUpdate()->findOrFail($currentProfile->id);
            $currentConnection = VideoProviderConnection::query()->lockForUpdate()->findOrFail($connection->id);
            abort_unless($currentAsset->id === $asset->id && $currentAsset->provider_video_id === $asset->provider_video_id
                && $currentProfile->id === $profile->id && $currentProfile->lock_version === $profileVersion
                && $currentConnection->enabled && $currentConnection->lock_version === $connectionVersion, 409, 'Video protection changed. Reload this lesson.');
            $device = $this->devices->current($request, $student, $profile->device_limit, $leaseId === null);
            $lease = $leaseId ? PlaybackLease::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($leaseId)
                : PlaybackLease::query()->where('student_id', $student->id)->where('request_key', $values['request_key'])->lockForUpdate()->first();
            $now = CarbonImmutable::now('UTC');
            if ($lease) {
                $this->proof($request, $lease, $values['lease_token']);
                abort_unless($lease->request_key === $values['request_key'] && $lease->device_id === $device->id
                    && $lease->course_id === $course->id && $lease->lesson_id === $lesson->id && $lease->block_id === $block->id
                    && $lease->video_asset_id === $asset->id && $lease->profile_id === $profile->id && $lease->profile_version === $profileVersion
                    && $lease->status === 'active' && $lease->expires_at->gt($now), 409, 'This playback session ended. Start playback again.');
            }
            $occupied = PlaybackLease::query()->whereIn('student_id', Student::withTrashed()->whereKey($student->id)->orWhere('merged_into_student_id', $student->id)->select('id'))->when($lease, fn ($query) => $query->where('id', '!=', $lease->id))
                ->where(fn ($query) => $query->where('authorized_until', '>', $now)->orWhere(fn ($query) => $query->where('status', 'active')->where('expires_at', '>', $now)))->count();
            abort_if($profile->stream_limit !== null && $occupied >= $profile->stream_limit, 409, 'Your active video limit has been reached. Close another player and wait for its short authorization to expire.');
            $ends = $this->access->accessEndings($student, collect([$course]), $lesson)[$course->id];
            $authorizationEnds = $now->addSeconds($profile->token_seconds)->min(CarbonImmutable::parse($request->session()->get('student_auth_expires_at'), 'UTC'));
            if ($ends) {
                $authorizationEnds = $authorizationEnds->min($ends);
            }
            abort_unless($authorizationEnds->timestamp > $now->timestamp, 404);
            $url = $this->provider->playbackUrl($currentConnection, $asset->provider_video_id, $authorizationEnds->timestamp);
            $lease ??= new PlaybackLease;
            $newLease = ! $lease->exists;
            $lease->forceFill(['student_id' => $student->id, 'device_id' => $device->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'block_id' => $block->id,
                'video_asset_id' => $asset->id, 'profile_id' => $profile->id, 'profile_version' => $profileVersion,
                'session_hash' => $this->sessionHash($request), 'lease_token_hash' => hash('sha256', $values['lease_token']), 'request_key' => $values['request_key'],
                'status' => 'active', 'authorized_until' => $authorizationEnds, 'expires_at' => $now->addSeconds($profile->lease_seconds), 'last_heartbeat_at' => $now,
                'session_code' => $lease->session_code ?? strtoupper(bin2hex(random_bytes(4)))])->save();
            if ($newLease) {
                $this->audits->logStudent($student->id, 'lms_video_playback_started', PlaybackLease::class, $lease->id, null, ['course_id' => $course->id, 'profile_id' => $profile->id]);
            }

            return ['url' => $url, 'lease_id' => $lease->id, 'expires_at' => $authorizationEnds->toIso8601String(), 'heartbeat_seconds' => $profile->heartbeat_seconds,
                'renew_url' => route('student.video.renew', [$course, $lesson, $block, $lease]), 'close_url' => route('student.video.close', $lease),
                'watermark' => $profile->watermark ? 'Learner '.strtoupper(substr(hash_hmac('sha256', (string) $student->id, config('app.key')), 0, 10)).' · '.$lease->session_code : null,
                'drm' => 'not_configured'];
        }, 3);
    }

    public function close(Request $request, int $id): void
    {
        $student = $this->sessions->current($request);
        abort_unless($student !== null, 404);
        DB::transaction(function () use ($request, $student, $id): void {
            $student = $this->students->lockStudent($student->id);
            $lease = PlaybackLease::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($id);
            $this->proof($request, $lease, (string) $request->input('lease_token'));
            $device = $this->devices->current($request, $student, null, false);
            abort_unless($device->id === $lease->device_id, 404);
            $lease->forceFill(['status' => 'closed'])->save();
            $this->audits->logStudent($student->id, 'lms_video_playback_closed', PlaybackLease::class, $lease->id);
        }, 3);
    }

    public function logout(Request $request): void
    {
        $student = $this->sessions->current($request);
        if ($student) {
            DB::transaction(function () use ($request, $student): void {
                $this->students->lockStudent($student->id);
                PlaybackLease::query()->where('student_id', $student->id)->where('session_hash', $this->sessionHash($request))->where('status', 'active')->update(['status' => 'revoked']);
            }, 3);
        }
    }

    /** @return array{Course,Lesson,LessonBlock,VideoAsset} */
    private function target(Student $student, int $courseId, int $lessonId, int $blockId, bool $lock = false): array
    {
        $course = Course::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($courseId);
        $lesson = Lesson::query()->where('course_id', $courseId)->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($lessonId);
        $block = LessonBlock::query()->where('lesson_id', $lessonId)->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($blockId);
        abort_unless($this->access->canAccess($student, $block) && $block->kind === 'video' && $block->video_asset_id !== null, 404);
        $asset = VideoAsset::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($block->video_asset_id);
        abort_unless($asset->provider === 'bunny' && $asset->provider_video_id !== null && $asset->usableFor($course), 404);

        return [$course, $lesson, $block, $asset];
    }

    private function proof(Request $request, PlaybackLease $lease, string $token): void
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token) && hash_equals($lease->lease_token_hash, hash('sha256', $token)) && hash_equals($lease->session_hash, $this->sessionHash($request)), 404);
    }

    private function sessionHash(Request $request): string
    {
        return hash_hmac('sha256', $request->session()->getId(), config('app.key'));
    }
}
