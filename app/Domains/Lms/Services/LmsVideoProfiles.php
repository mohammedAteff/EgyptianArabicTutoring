<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Exceptions\VideoProviderUnavailable;

class LmsVideoProfiles
{
    public function effective(Course $course, ?Lesson $lesson = null): ProtectionProfile
    {
        $id = $lesson !== null ? $lesson->protection_profile_id : null;
        $id ??= $course->protection_profile_id;
        $profile = $id ? ProtectionProfile::query()->find($id) : ProtectionProfile::query()->where('name', $course->kind === 'private' ? 'Private' : 'Member')->first();
        if (! $profile || ! $profile->active || (in_array($profile->name, ['Private', 'Premium'], true) && ($profile->device_limit === null || $profile->stream_limit === null || ! $profile->watermark))) {
            throw new VideoProviderUnavailable;
        }

        return $profile;
    }
}
