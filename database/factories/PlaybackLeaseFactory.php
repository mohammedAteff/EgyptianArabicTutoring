<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlaybackLease> */
class PlaybackLeaseFactory extends Factory
{
    protected $model = PlaybackLease::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'device_id' => fn (array $values) => AuthorizedDeviceFactory::new()->create(['student_id' => $values['student_id']])->id, 'lesson_id' => Lesson::factory(), 'course_id' => fn (array $values) => Lesson::query()->findOrFail($values['lesson_id'])->course_id, 'block_id' => fn (array $values) => LessonBlock::factory()->create(['lesson_id' => $values['lesson_id']])->id, 'video_asset_id' => VideoAssetFactory::new(), 'profile_id' => ProtectionProfileFactory::new(), 'profile_version' => 1, 'session_hash' => hash('sha256', 'synthetic-session'), 'lease_token_hash' => hash('sha256', Str::random(64)), 'request_key' => Str::uuid(), 'session_code' => 'ABCDEF123456', 'authorized_until' => now('UTC')->addMinutes(2), 'expires_at' => now('UTC')->addSeconds(150), 'last_heartbeat_at' => now('UTC'), 'status' => 'active'];
    }
}
