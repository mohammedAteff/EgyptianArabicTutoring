<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AdministratorTwoFactorService
{
    public function __construct(private Google2FA $totp, private AuditLogService $audit) {}

    public function fingerprint(Administrator $administrator): string
    {
        return hash_hmac('sha256', implode('|', [$administrator->id, $administrator->role, $administrator->password, $administrator->two_factor_version]), (string) config('app.key'));
    }

    /** @return array{secret: string, qr: string} */
    public function beginEnrollment(int $administratorId, #[\SensitiveParameter] string $password, string $sessionId, bool $reset = false, #[\SensitiveParameter] ?string $code = null, #[\SensitiveParameter] ?string $recoveryCode = null): array
    {
        return DB::transaction(function () use ($administratorId, $password, $sessionId, $reset, $code, $recoveryCode): array {
            $administrator = $this->lockedAdministrator($administratorId);
            $this->confirmPassword($administrator, $password);
            if ($administrator->requiresTwoFactor()) {
                if (! $reset) {
                    throw ValidationException::withMessages(['code' => 'Two-factor authentication is already enabled.']);
                }
                $this->verifyLocked($administrator, $code, $recoveryCode);
            } elseif ($reset) {
                throw ValidationException::withMessages(['code' => 'Enable two-factor authentication before replacing your authenticator.']);
            }
            $secret = $this->totp->generateSecretKey();
            $administrator->forceFill([
                'two_factor_pending_secret' => $secret,
                'two_factor_pending_at' => now('UTC'),
                'two_factor_pending_session' => $this->sessionBinding($administrator, $sessionId),
            ])->save();
            $this->event($administrator, $reset ? 'two_factor_reset_started' : 'two_factor_enrollment_started');
            $uri = $this->totp->getQRCodeUrl((string) config('business.site_name', 'Arabic with Abdallah'), $administrator->email, $secret);
            $qr = (new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd)))->writeString($uri);

            return ['secret' => $secret, 'qr' => $qr];
        });
    }

    /** @return list<string> */
    public function confirmEnrollment(int $administratorId, #[\SensitiveParameter] string $code, string $sessionId): array
    {
        return DB::transaction(function () use ($administratorId, $code, $sessionId): array {
            $administrator = $this->lockedAdministrator($administratorId);
            if (! $administrator->two_factor_pending_secret || ! $administrator->two_factor_pending_at
                || $administrator->two_factor_pending_at->lte(now('UTC')->subMinutes(10))
                || ! hash_equals((string) $administrator->two_factor_pending_session, $this->sessionBinding($administrator, $sessionId))) {
                throw ValidationException::withMessages(['code' => 'Setup has expired or belongs to another session. Start setup again with your password.']);
            }
            $step = $this->totp->verifyKeyNewer($administrator->two_factor_pending_secret, $code, 0, 1, intdiv(now('UTC')->timestamp, 30));
            if ($step === false) {
                throw ValidationException::withMessages(['code' => 'The authenticator code is invalid. Two-factor setup has not been confirmed.']);
            }
            [$codes, $hashes] = $this->newRecoveryCodes();
            $wasEnabled = $administrator->requiresTwoFactor();
            $administrator->forceFill([
                'two_factor_secret' => $administrator->two_factor_pending_secret,
                'two_factor_confirmed_at' => now('UTC'),
                'two_factor_recovery_codes' => $hashes,
                'two_factor_last_used_step' => $step,
                'two_factor_version' => (string) Str::uuid(),
                'remember_token' => Str::random(60),
                ...$this->emptyPendingState(),
            ])->save();
            $this->event($administrator, $wasEnabled ? 'two_factor_authenticator_replaced' : 'two_factor_enabled');

            return $codes;
        });
    }

    public function authenticate(int $administratorId, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode, ?string $expectedFingerprint = null): Administrator
    {
        return DB::transaction(function () use ($administratorId, $code, $recoveryCode, $expectedFingerprint): Administrator {
            $administrator = $this->lockedAdministrator($administratorId);
            if ($expectedFingerprint !== null && ! hash_equals($this->fingerprint($administrator), $expectedFingerprint)) {
                throw ValidationException::withMessages(['code' => 'The sign-in state changed. Sign in with your password again.']);
            }
            $this->verifyLocked($administrator, $code, $recoveryCode);

            return $administrator;
        });
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(int $administratorId, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode): array
    {
        return DB::transaction(function () use ($administratorId, $password, $code, $recoveryCode): array {
            $administrator = $this->lockedAdministrator($administratorId);
            $this->confirmPassword($administrator, $password);
            $this->verifyLocked($administrator, $code, $recoveryCode);
            [$codes, $hashes] = $this->newRecoveryCodes();
            $administrator->forceFill(['two_factor_recovery_codes' => $hashes, 'two_factor_version' => (string) Str::uuid(), 'remember_token' => Str::random(60)])->save();
            $this->event($administrator, 'two_factor_recovery_codes_regenerated');

            return $codes;
        });
    }

    public function disable(int $administratorId, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode): void
    {
        DB::transaction(function () use ($administratorId, $password, $code, $recoveryCode): void {
            $administrator = $this->lockedAdministrator($administratorId);
            $this->confirmPassword($administrator, $password);
            $this->verifyLocked($administrator, $code, $recoveryCode);
            $this->clearSecurityState($administrator);
            $this->event($administrator, 'two_factor_disabled');
        });
    }

    public function emergencyRecover(int $administratorId, string $operator, string $reason): void
    {
        DB::transaction(function () use ($administratorId, $operator, $reason): void {
            $administrator = Administrator::query()->whereKey($administratorId)->where('role', 'super_admin')->lockForUpdate()->firstOrFail();
            $this->clearSecurityState($administrator);
            $this->audit->log('two_factor_emergency_recovery', Administrator::class, $administrator->id, newData: ['operator' => $operator, 'reason' => $reason], adminId: $administrator->id);
            DB::table('sessions')->where('user_id', $administrator->id)->delete();
        });
    }

    private function lockedAdministrator(int $administratorId): Administrator
    {
        $administrator = Administrator::query()->whereKey($administratorId)->where('role', 'super_admin')->whereNull('suspended_at')->lockForUpdate()->first();
        if (! $administrator) {
            throw ValidationException::withMessages(['code' => 'This account is unavailable. Sign in again.']);
        }

        return $administrator;
    }

    private function confirmPassword(Administrator $administrator, #[\SensitiveParameter] string $password): void
    {
        if (! Hash::check($password, $administrator->password)) {
            throw ValidationException::withMessages(['password' => 'Your current password is incorrect.']);
        }
    }

    private function verifyLocked(Administrator $administrator, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode): void
    {
        if (! $administrator->requiresTwoFactor() || ! $administrator->two_factor_secret || ! $administrator->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['code' => 'Two-factor authentication is not configured.']);
        }
        if ($recoveryCode !== null && $recoveryCode !== '' && ($code === null || $code === '')) {
            $hashes = $administrator->two_factor_recovery_codes ?? [];
            foreach ($hashes as $index => $hash) {
                if (Hash::check($recoveryCode, $hash)) {
                    unset($hashes[$index]);
                    $administrator->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                    $this->event($administrator, 'two_factor_recovery_code_used');

                    return;
                }
            }
        } elseif ($code !== null && preg_match('/\A[0-9]{6}\z/', $code) && ($recoveryCode === null || $recoveryCode === '')) {
            $step = $this->totp->verifyKeyNewer($administrator->two_factor_secret, $code, $administrator->two_factor_last_used_step ?? 0, 1, intdiv(now('UTC')->timestamp, 30));
            if ($step !== false) {
                $administrator->forceFill(['two_factor_last_used_step' => $step])->save();

                return;
            }
        }
        throw ValidationException::withMessages(['code' => 'The code is invalid or has already been used. Wait for a new authenticator code, or use an unused recovery code.']);
    }

    /** @return array{list<string>, list<string>} */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        $hashes = [];
        for ($index = 0; $index < 10; $index++) {
            $code = Str::random(10).'-'.Str::random(10);
            $codes[] = $code;
            $hashes[] = Hash::make($code);
        }

        return [$codes, $hashes];
    }

    private function clearSecurityState(Administrator $administrator): void
    {
        $administrator->forceFill([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null, 'two_factor_last_used_step' => null,
            'two_factor_version' => (string) Str::uuid(), 'remember_token' => Str::random(60),
            ...$this->emptyPendingState(),
        ])->save();
    }

    /** @return array{two_factor_pending_secret: null, two_factor_pending_at: null, two_factor_pending_session: null} */
    private function emptyPendingState(): array
    {
        return ['two_factor_pending_secret' => null, 'two_factor_pending_at' => null, 'two_factor_pending_session' => null];
    }

    private function sessionBinding(Administrator $administrator, string $sessionId): string
    {
        return hash_hmac('sha256', $this->fingerprint($administrator).'|'.$sessionId, (string) config('app.key'));
    }

    private function event(Administrator $administrator, string $action): void
    {
        $this->audit->log($action, Administrator::class, $administrator->id, adminId: $administrator->id);
    }
}
