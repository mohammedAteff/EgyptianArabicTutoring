<?php

namespace App\Domains\Lms\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VideoDeviceService
{
    public const COOKIE = 'lms_browser_device';

    public function __construct(private TeachingRecordService $students, private AuditLogService $audits) {}

    /** Caller holds the canonical Student lock. Browser identity supplements login. */
    public function current(Request $request, Student $student, ?int $limit, bool $create = true): AuthorizedDevice
    {
        $token = $request->cookie(self::COOKIE);
        $device = is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)
            ? AuthorizedDevice::query()->where('token_hash', hash('sha256', $token))->where('student_id', $student->id)->lockForUpdate()->first() : null;
        if ($device) {
            abort_unless($device->status === 'authorized', 403, 'This browser was revoked. Manage your authorized browsers to register it again.');
            abort_if($limit !== null && AuthorizedDevice::query()->where('student_id', $student->id)->where('status', 'authorized')->count() > $limit, 409, 'Remove an authorized browser before playing this video.');
            $device->forceFill(['last_seen_at' => now('UTC')])->save();

            return $device;
        }
        abort_unless($create, 404);
        abort_if($limit !== null && AuthorizedDevice::query()->where('student_id', $student->id)->where('status', 'authorized')->count() >= $limit, 409, 'Your authorized browser limit has been reached.');
        $token = bin2hex(random_bytes(32));
        $device = new AuthorizedDevice;
        $device->forceFill(['student_id' => $student->id, 'token_hash' => hash('sha256', $token), 'label' => 'My browser', 'status' => 'authorized', 'last_seen_at' => now('UTC')])->save();
        Cookie::queue(Cookie::make(self::COOKIE, $token, 60 * 24 * 365, config('session.path', '/'), config('session.domain'), app()->isProduction() || $request->isSecure(), true, false, 'lax'));
        $this->audits->logStudent($student->id, 'lms_video_browser_authorized', AuthorizedDevice::class, $device->id);

        return $device;
    }

    public function register(Request $request, Student $student): void
    {
        DB::transaction(function () use ($request, $student): void {
            $student = $this->students->lockStudent($student->id);
            $copy = clone $request;
            $token = $request->cookie(self::COOKIE);
            if (is_string($token) && AuthorizedDevice::query()->where('student_id', $student->id)->where('token_hash', hash('sha256', $token))->where('status', 'revoked')->exists()) {
                $copy->cookies->remove(self::COOKIE);
            }
            $profile = ProtectionProfile::query()->where('name', 'Private')->where('active', true)->lockForUpdate()->firstOrFail();
            $this->current($copy, $student, $profile->device_limit);
        }, 3);
    }

    public function rename(Student $student, int $id, string $label): void
    {
        $label = Validator::make(['label' => $label], ['label' => ['required', 'string', 'max:80']])->validate()['label'];
        DB::transaction(function () use ($student, $id, $label): void {
            $student = $this->students->lockStudent($student->id);
            AuthorizedDevice::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($id)->forceFill(['label' => $label])->save();
        }, 3);
    }

    public function revoke(Student $student, int $id): void
    {
        DB::transaction(function () use ($student, $id): void {
            $student = $this->students->lockStudent($student->id);
            AuthorizedDevice::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($id)->forceFill(['status' => 'revoked', 'revoked_at' => now('UTC')])->save();
            PlaybackLease::query()->where('student_id', $student->id)->where('device_id', $id)->where('status', 'active')->update(['status' => 'revoked']);
            $this->audits->log('lms_video_browser_revoked', AuthorizedDevice::class, $id, null, ['student_id' => $student->id]);
        }, 3);
    }
}
