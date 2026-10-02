<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Resources\Services\EmailQualityService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Models\StudentEmailVerification;
use App\Mail\StudentSecondaryEmailVerification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentEmailService
{
    public function trusted(Student $student, string $email): bool
    {
        return $email === $student->email_normalized || StudentEmail::query()->where('student_id', $student->id)->where('email_normalized', $email)->exists();
    }

    public function requestVerification(Student $student, string $email): void
    {
        $email = app(StudentIdentityService::class)->normalizeEmail($email) ?? '';
        app(EmailQualityService::class)->validate($email);
        $key = 'student-secondary-email:'.$student->id;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages(['email' => 'Please wait before requesting another verification email.']);
        }
        RateLimiter::hit($key, 3600);
        $this->assertAvailable($student, $email);
        if ($this->trusted($student, $email)) {
            throw ValidationException::withMessages(['email' => 'This email is already verified for your profile.']);
        }
        $token = Str::random(64);
        $challenge = DB::transaction(function () use ($student, $email, $token): StudentEmailVerification {
            Student::verified()->whereNull('suspended_at')->whereKey($student->id)->lockForUpdate()->firstOrFail();
            StudentEmailVerification::query()->where('student_id', $student->id)->where('email_normalized', $email)->whereNull('consumed_at')->update(['consumed_at' => now('UTC')]);

            return StudentEmailVerification::create(['student_id' => $student->id, 'email_normalized' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now('UTC')->addMinutes(15)]);
        }, 5);
        try {
            Mail::to($email)->send(new StudentSecondaryEmailVerification(route('student.profile.email.verify', ['token' => $token])));
        } catch (\Throwable $exception) {
            $challenge->update(['consumed_at' => now('UTC')]);
            throw ValidationException::withMessages(['email' => 'Verification email could not be sent. Please try again later.']);
        }
    }

    public function verify(Student $student, string $token): void
    {
        if (! preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            throw ValidationException::withMessages(['email' => 'This verification link is invalid or expired.']);
        }
        try {
            DB::transaction(function () use ($student, $token): void {
                /** Serialize ownership claims against edits to another student's primary email. */
                Student::withTrashed()->orderBy('id')->lockForUpdate()->get(['id']);
                Student::verified()->whereNull('suspended_at')->whereKey($student->id)->lockForUpdate()->firstOrFail();
                $challenge = StudentEmailVerification::query()->where('student_id', $student->id)->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                if (! $challenge || $challenge->consumed_at || $challenge->expires_at->lte(now('UTC'))) {
                    throw ValidationException::withMessages(['email' => 'This verification link is invalid or expired.']);
                }
                $this->assertAvailable($student, $challenge->email_normalized);
                StudentEmail::firstOrCreate(['email_normalized' => $challenge->email_normalized], ['student_id' => $student->id, 'verified_at' => now('UTC')]);
                $challenge->update(['consumed_at' => now('UTC')]);
                app(AuditLogService::class)->log('student_secondary_email_verified', Student::class, $student->id, null, ['email' => $challenge->email_normalized, 'student_id' => $student->id], null);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email is already associated with another profile.']);
        }
    }

    private function assertAvailable(Student $student, string $email): void
    {
        if (Student::withTrashed()->where('email_normalized', $email)->where('id', '!=', $student->id)->exists() || StudentEmail::query()->where('email_normalized', $email)->where('student_id', '!=', $student->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email cannot be added to this profile.']);
        }
    }
}
