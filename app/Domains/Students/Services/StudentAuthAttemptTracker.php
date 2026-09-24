<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;

class StudentAuthAttemptTracker
{
    /**
     * @param  array<string, string>  $fingerprints
     * @param  Closure(): ?Student  $verify
     * @return array{student: ?Student, cooling_down: bool}
     */
    public function attempt(array $fingerprints, Closure $verify): array
    {
        $keys = array_values(array_unique($fingerprints));
        sort($keys, SORT_STRING);

        if ($keys === []) {
            return ['student' => null, 'cooling_down' => false];
        }

        return DB::transaction(function () use ($keys, $verify): array {
            $now = now('UTC');
            foreach ($keys as $key) {
                DB::table('student_auth_attempts')->insertOrIgnore([
                    'fingerprint' => $key,
                    'consecutive_failures' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $states = DB::table('student_auth_attempts')
                ->whereIn('fingerprint', $keys)
                ->orderBy('fingerprint')
                ->lockForUpdate()
                ->get();

            if ($states->contains(fn ($state): bool => $state->cooldown_until !== null && $state->cooldown_until > $now->toDateTimeString())) {
                return ['student' => null, 'cooling_down' => true];
            }

            $student = $verify();
            if ($student) {
                DB::table('student_auth_attempts')->whereIn('fingerprint', $keys)->update([
                    'consecutive_failures' => 0,
                    'cooldown_until' => null,
                    'updated_at' => $now,
                ]);

                return ['student' => $student, 'cooling_down' => false];
            }

            foreach ($states as $state) {
                $failures = (int) $state->consecutive_failures + 1;
                DB::table('student_auth_attempts')->where('fingerprint', $state->fingerprint)->update([
                    'consecutive_failures' => $failures,
                    'cooldown_until' => $failures >= 3 ? $now->copy()->addMinutes(15) : null,
                    'updated_at' => $now,
                ]);
            }

            return ['student' => null, 'cooling_down' => false];
        }, 3);
    }
}
