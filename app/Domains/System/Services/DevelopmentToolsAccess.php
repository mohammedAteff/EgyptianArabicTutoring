<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\System\Models\DevelopmentDataOperation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DevelopmentToolsAccess
{
    public function __construct(private AdministratorTwoFactorService $twoFactor) {}

    public function authorize(Administrator $actor): Administrator
    {
        abort_unless(config('development_tools.enabled'), 404);
        $current = Administrator::query()->whereKey($actor->id)->where('role', 'super_admin')->whereNull('suspended_at')->first();
        abort_unless($current !== null, 403);

        return $current;
    }

    public function binding(Administrator $actor, string $sessionId): string
    {
        return hash_hmac('sha256', $actor->id.'|'.$sessionId, (string) config('app.key'));
    }

    public function fingerprint(Administrator $actor): string
    {
        return $this->twoFactor->fingerprint($actor);
    }

    public function confirmOperation(Administrator $actor, DevelopmentDataOperation $operation, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code, #[\SensitiveParameter] ?string $recoveryCode): void
    {
        $current = $this->authorize($actor);
        if ($operation->administrator_id !== $current->id || $operation->status !== 'pending' || $operation->expires_at->lte(now('UTC'))) {
            throw ValidationException::withMessages(['operation_token' => 'The pending operation does not belong to this account or has expired.']);
        }
        if ($password === '' || ! Hash::check($password, $current->password)) {
            throw ValidationException::withMessages(['password' => 'Your current password is incorrect.']);
        }
        if ($current->requiresTwoFactor()) {
            $this->twoFactor->authenticate($current->id, $code, $recoveryCode, $this->fingerprint($current));
        }
        $operation->update(['status' => 'running']);
        $this->stamp($operation);
    }

    private function stamp(DevelopmentDataOperation $operation): void
    {
        $scope = $operation->scope;
        $scope['confirmed_signature'] = $this->signature($operation);
        $operation->update(['scope' => $scope]);
    }

    public function assertConfirmed(Administrator $actor, ?DevelopmentDataOperation $operation, string $type, string $domain): void
    {
        $current = $this->authorize($actor);
        $stored = $operation === null ? null : DevelopmentDataOperation::query()->whereKey($operation->id)->lockForUpdate()->first();
        if ($stored === null || $stored->status !== 'running' || $stored->administrator_id !== $current->id
            || $stored->type !== $type || $stored->domain !== $domain
            || ! hash_equals($stored->security_fingerprint, $this->fingerprint($current))
            || ! is_string($stored->scope['confirmed_signature'] ?? null)
            || ! hash_equals($stored->scope['confirmed_signature'], $this->signature($stored))) {
            throw ValidationException::withMessages(['operation' => 'This service requires the confirmed, locked operation. No data was changed.']);
        }
    }

    private function signature(DevelopmentDataOperation $operation): string
    {
        $scope = $operation->scope;
        unset($scope['confirmed_signature']);

        return hash_hmac('sha256', json_encode([$operation->id, $operation->token_hash, $operation->session_binding,
            $operation->security_fingerprint, $operation->type, $operation->domain, $scope, $operation->preview['fingerprint']], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
