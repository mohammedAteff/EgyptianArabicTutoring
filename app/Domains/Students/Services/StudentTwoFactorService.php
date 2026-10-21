<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Administration\Services\SecurityMailTransport;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Mail\StudentSecurityVerification;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class StudentTwoFactorService
{
    public function __construct(private Google2FA $totp, private AuditLogService $audit) {}

    public function fingerprint(Student $student): string
    {
        return hash_hmac('sha256', implode('|', [$student->id, $student->two_factor_version, $student->email_normalized, $student->phone_normalized, $student->name_normalized, $student->date_of_birth?->toDateString()]), (string) config('app.key'));
    }

    public function beginEnrollment(int $id, string $sessionId): void
    {
        if (! app()->environment('testing') && ! SecurityMailTransport::canDeliver()) {
            throw ValidationException::withMessages(['security' => 'Email delivery must be configured before authenticator enrollment. Contact your tutor.']);
        }
        [$student, $state] = DB::transaction(function () use ($id, $sessionId): array {
            $student = $this->locked($id);
            if ($student->requiresTwoFactor() || ! filter_var($student->email_normalized, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['security' => 'An available primary email and disabled authenticator are required to start setup.']);
            }
            $student->forceFill(['two_factor_pending_secret' => $this->totp->generateSecretKey(), 'two_factor_pending_at' => now('UTC'),
                'two_factor_pending_session' => $this->binding($student, $sessionId)])->save();

            return [$student, $this->state($student)];
        });
        $url = URL::temporarySignedRoute('student.security.approve', now('UTC')->addMinutes(10), ['state' => $state]);
        try {
            Mail::to($student->email_normalized)->send(new StudentSecurityVerification($url));
        } catch (\Throwable) {
            Student::query()->whereKey($id)->where('two_factor_pending_session', $student->two_factor_pending_session)
                ->update(['two_factor_pending_secret' => null, 'two_factor_pending_at' => null, 'two_factor_pending_session' => null]);
            throw ValidationException::withMessages(['security' => 'The security email could not be delivered. Please try again later.']);
        }
        $this->audit->logStudent($id, 'student_two_factor_enrollment_requested', Student::class, $id);
    }

    /** @return array{secret: string, qr: string} */
    public function setup(int $id, string $sessionId, string $state): array
    {
        $student = $this->pending($this->lockedQuery($id)->firstOrFail(), $sessionId, $state);
        $secret = (string) $student->two_factor_pending_secret;
        $uri = $this->totp->getQRCodeUrl((string) config('business.site_name'), 'Student '.$student->id, $secret);

        return ['secret' => $secret, 'qr' => (new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd)))->writeString($uri)];
    }

    /** @return list<string> */
    public function confirm(int $id, string $sessionId, string $state, #[\SensitiveParameter] string $code): array
    {
        return DB::transaction(function () use ($id, $sessionId, $state, $code): array {
            $student = $this->pending($this->locked($id), $sessionId, $state);
            $step = $this->totp->verifyKeyNewer($student->two_factor_pending_secret, $code, 0, 1, intdiv(now('UTC')->timestamp, 30));
            if ($step === false) {
                throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
            }
            [$codes, $hashes] = $this->recoveryCodes();
            $student->forceFill(['two_factor_secret' => $student->two_factor_pending_secret, 'two_factor_confirmed_at' => now('UTC'),
                'two_factor_recovery_codes' => $hashes, 'two_factor_last_used_step' => $step, 'two_factor_version' => (string) Str::uuid(),
                ...$this->emptyPending()])->save();
            $this->audit->logStudent($id, 'student_two_factor_enabled', Student::class, $id);
            $this->notify($student, 'Authenticator enabled');

            return $codes;
        });
    }

    public function authenticate(int $id, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode, string $fingerprint): Student
    {
        return DB::transaction(function () use ($id, $code, $recoveryCode, $fingerprint): Student {
            $student = $this->locked($id);
            if (! hash_equals($this->fingerprint($student), $fingerprint) || ! $student->requiresTwoFactor()) {
                throw ValidationException::withMessages(['code' => 'Your sign-in state changed. Sign in again.']);
            }
            if ($recoveryCode && ! $code) {
                $hashes = $student->two_factor_recovery_codes ?? [];
                foreach ($hashes as $index => $hash) {
                    if (Hash::check($recoveryCode, $hash)) {
                        unset($hashes[$index]);
                        $student->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                        $this->audit->logStudent($id, 'student_two_factor_recovery_code_used', Student::class, $id);

                        return $student;
                    }
                }
            } elseif ($code && ! $recoveryCode && preg_match('/\A[0-9]{6}\z/', $code)) {
                $step = $this->totp->verifyKeyNewer($student->two_factor_secret, $code, $student->two_factor_last_used_step ?? 0, 1, intdiv(now('UTC')->timestamp, 30));
                if ($step !== false) {
                    $student->forceFill(['two_factor_last_used_step' => $step])->save();

                    return $student;
                }
            }
            throw ValidationException::withMessages(['code' => 'The code is invalid or has already been used.']);
        });
    }

    /** @return list<string> */
    public function change(int $id, string $action, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode, string $fingerprint): array
    {
        return DB::transaction(function () use ($id, $action, $code, $recoveryCode, $fingerprint): array {
            $student = $this->authenticate($id, $code, $recoveryCode, $fingerprint);
            if ($action === 'disable') {
                $this->clear($student);
                $codes = [];
            } else {
                [$codes, $hashes] = $this->recoveryCodes();
                $student->forceFill(['two_factor_recovery_codes' => $hashes, 'two_factor_version' => (string) Str::uuid(), ...$this->emptyPending()])->save();
            }
            $this->audit->logStudent($id, 'student_two_factor_'.$action, Student::class, $id);
            $this->notify($student, $action === 'disable' ? 'Authenticator disabled' : 'Recovery codes replaced');

            return $codes;
        });
    }

    public function resetByStaff(Administrator $actor, int $id, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode, string $reason): void
    {
        DB::transaction(function () use ($actor, $id, $password, $code, $recoveryCode, $reason): void {
            $actor = Administrator::query()->whereKey($actor->id)->whereIn('role', ['super_admin', 'admin'])->whereNull('suspended_at')->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($password, $actor->password) && $actor->requiresTwoFactor(), 403, 'Confirm your password and enable staff MFA before resetting student security.');
            $factors = app(AdministratorTwoFactorService::class);
            $factors->authenticate($actor->id, $code, $recoveryCode, $factors->fingerprint($actor));
            $student = $this->locked($id);
            $this->clear($student);
            $this->notify($student, 'Authenticator reset by staff');
            $this->audit->log('student_two_factor_staff_reset', Student::class, $id, newData: ['reason' => $reason], adminId: $actor->id);
        });
    }

    /** @return Builder<Student> */
    private function lockedQuery(int $id): Builder
    {
        return Student::verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')->whereKey($id);
    }

    private function locked(int $id): Student
    {
        return $this->lockedQuery($id)->lockForUpdate()->firstOrFail();
    }

    private function binding(Student $student, string $sessionId): string
    {
        return hash_hmac('sha256', $this->fingerprint($student).'|'.$sessionId, (string) config('app.key'));
    }

    private function state(Student $student): string
    {
        return hash_hmac('sha256', $student->two_factor_pending_session.'|'.$student->two_factor_pending_secret, (string) config('app.key'));
    }

    private function pending(Student $student, string $sessionId, string $state): Student
    {
        if ($student->requiresTwoFactor() || ! $student->two_factor_pending_secret || ! $student->two_factor_pending_at
            || $student->two_factor_pending_at->lte(now('UTC')->subMinutes(10))
            || ! hash_equals((string) $student->two_factor_pending_session, $this->binding($student, $sessionId))
            || ! hash_equals($this->state($student), $state)) {
            throw ValidationException::withMessages(['security' => 'Setup is invalid, expired, or belongs to another session. Restart setup.']);
        }

        return $student;
    }

    /** @return array{list<string>, list<string>} */
    private function recoveryCodes(): array
    {
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = Str::random(10).'-'.Str::random(10);
            $codes[] = $code;
            $hashes[] = Hash::make($code);
        }

        return [$codes, $hashes];
    }

    /** @return array<string, null> */
    private function emptyPending(): array
    {
        return ['two_factor_pending_secret' => null, 'two_factor_pending_at' => null, 'two_factor_pending_session' => null];
    }

    private function notify(Student $student, string $title): void
    {
        app(StudentNotificationService::class)->synchronize($student, [['key' => 'security:'.$student->two_factor_version, 'type' => 'account_security',
            'title' => $title, 'message' => 'Your account security settings changed. Contact your tutor if you did not request this change.', 'link' => route('student.security.show')]]);
    }

    private function clear(Student $student): void
    {
        $student->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null,
            'two_factor_last_used_step' => null, 'two_factor_version' => (string) Str::uuid(), ...$this->emptyPending()])->save();
    }
}
