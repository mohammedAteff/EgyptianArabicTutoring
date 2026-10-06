<?php

namespace App\Domains\System\Services;

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentSessionCleanupService
{
    /** @return array{count: int, fingerprint: string} */
    public function preview(): array
    {
        $keys = $this->keys();
        $scopes = [];
        foreach (DB::table('sessions')->orderBy('id')->cursor() as $session) {
            $data = array_intersect_key($this->decode($session->payload), array_flip($keys));
            if ($data !== []) {
                $scopes[$session->id] = $data;
            }
        }

        return ['count' => count($scopes), 'fingerprint' => hash('sha256', json_encode($scopes, JSON_THROW_ON_ERROR))];
    }

    public function clear(): int
    {
        $cleared = 0;
        $keys = $this->keys();
        foreach (DB::table('sessions')->orderBy('id')->lockForUpdate()->cursor() as $session) {
            $data = $this->decode($session->payload);
            $original = $data;
            foreach ($keys as $key) {
                unset($data[$key]);
            }
            if ($original === $data) {
                continue;
            }
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            if (config('session.encrypt')) {
                $encoded = Crypt::encryptString($encoded);
            }
            DB::table('sessions')->where('id', $session->id)->update(['payload' => base64_encode($encoded)]);
            $cleared++;
        }

        return $cleared;
    }

    /** @return list<string> */
    private function keys(): array
    {
        if (config('session.driver') !== 'database' || config('session.serialization') !== 'json') {
            throw ValidationException::withMessages(['sessions' => 'Student reset requires the installed database-backed JSON session serializer. No session was discarded.']);
        }
        $guard = Auth::guard('student');
        if (! $guard instanceof SessionGuard) {
            throw ValidationException::withMessages(['sessions' => 'Student reset requires the configured session authentication guard.']);
        }

        return ['student_id', 'student_authenticated_at', 'student_auth_expires_at', 'student_display_timezone', $guard->getName()];
    }

    /** @return array<string, mixed> */
    private function decode(string $stored): array
    {
        $payload = base64_decode($stored, true);
        if (! is_string($payload)) {
            throw ValidationException::withMessages(['sessions' => 'A stored session requires review before student reset.']);
        }
        try {
            if (config('session.encrypt')) {
                $payload = Crypt::decryptString($payload);
            }
            $data = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['sessions' => 'A stored session requires review before student reset.']);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages(['sessions' => 'A stored session requires review before student reset.']);
        }

        return $data;
    }
}
