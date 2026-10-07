<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Exceptions\VideoProviderUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsVideoSettings
{
    public function __construct(private AuditLogService $audits) {}

    public function connection(): VideoProviderConnection
    {
        $connection = VideoProviderConnection::query()->where('provider', 'bunny')->first();
        if (! $connection) {
            throw new VideoProviderUnavailable;
        }

        return $connection;
    }

    public function authorize(Administrator $actor): void
    {
        abort_unless(Administrator::query()->whereKey($actor->id)->where('role', 'super_admin')->whereNull('suspended_at')->exists(), 403);
    }

    /** @param array<string,mixed> $data */
    public function save(Administrator $actor, array $data, int $version): VideoProviderConnection
    {
        $this->authorize($actor);
        $values = Validator::make($data, [
            'library_id' => ['required', 'integer', 'min:1'],
            'cdn_hostname' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9-]+\\.b-cdn\\.net$/D'],
            'allowed_domains' => ['required', 'array', 'min:1', 'max:10'],
            'allowed_domains.*' => ['required', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', 'distinct'],
            'enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'min:16', 'max:1000'],
            'read_only_key' => ['nullable', 'string', 'min:16', 'max:1000'],
            'signing_key' => ['nullable', 'string', 'min:16', 'max:1000'],
            'account_key' => ['nullable', 'string', 'min:16', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $values, $version): VideoProviderConnection {
            $connection = VideoProviderConnection::query()->where('provider', 'bunny')->lockForUpdate()->first() ?? new VideoProviderConnection;
            abort_unless(($connection->exists ? $connection->lock_version : 0) === $version, 409, 'Video settings changed. Reload before saving.');
            $secrets = ['api_key', 'read_only_key', 'signing_key', 'account_key'];
            $safe = array_diff_key($values, array_fill_keys($secrets, true));
            $changingLibrary = $connection->exists && (int) $connection->library_id !== (int) $values['library_id'];
            if ($changingLibrary && $connection->assets()->exists()) {
                throw ValidationException::withMessages(['library_id' => 'An existing media library cannot be reassigned. Disable it and review its retained media first.']);
            }
            $connection->forceFill($safe + ['provider' => 'bunny', 'lock_version' => $version + 1, 'last_verified_at' => null]);
            foreach ($secrets as $key) {
                if (! empty($values[$key])) {
                    $connection->{$key} = $values[$key];
                }
            }
            if ($connection->enabled && (! $connection->api_key || ! $connection->read_only_key || ! $connection->signing_key || ! $connection->account_key)) {
                throw ValidationException::withMessages(['enabled' => 'Complete all four server-side credentials before enabling protected video.']);
            }
            $connection->save();
            $this->audits->log('lms_video_provider_settings_changed', VideoProviderConnection::class, $connection->id, null,
                ['library_id' => $connection->library_id, 'enabled' => $connection->enabled, 'version' => $connection->lock_version], $actor->id);

            return $connection;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function profile(Administrator $actor, ProtectionProfile $profile, array $data, int $version): ProtectionProfile
    {
        $this->authorize($actor);
        $values = Validator::make($data, [
            'device_limit' => ['nullable', 'integer', 'between:1,20'], 'stream_limit' => ['nullable', 'integer', 'between:1,10'],
            'token_seconds' => ['required', 'integer', 'between:30,600'], 'heartbeat_seconds' => ['required', 'integer', 'between:15,60'],
            'lease_seconds' => ['required', 'integer', 'between:45,900'], 'watermark' => ['required', 'boolean'],
            'drm_mode' => ['required', Rule::in(['none', 'optional', 'required'])], 'active' => ['required', 'boolean'],
        ])->validate();
        if ((int) $values['lease_seconds'] < (int) $values['token_seconds'] + (int) $values['heartbeat_seconds']) {
            throw ValidationException::withMessages(['lease_seconds' => 'Session expiry must cover the token lifetime plus one heartbeat interval.']);
        }
        if (in_array($profile->name, ['Private', 'Premium'], true) && (empty($values['device_limit']) || empty($values['stream_limit']) || ! $values['watermark'])) {
            throw ValidationException::withMessages(['watermark' => 'Private and Premium profiles require device/stream limits and a personalized watermark.']);
        }

        return DB::transaction(function () use ($actor, $profile, $values, $version): ProtectionProfile {
            $profile = ProtectionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($profile->lock_version === $version, 409, 'This protection profile changed. Reload before saving.');
            $profile->forceFill($values + ['lock_version' => $version + 1])->save();
            $this->audits->log('lms_video_profile_changed', ProtectionProfile::class, $profile->id, null,
                $profile->only(['name', 'device_limit', 'stream_limit', 'token_seconds', 'lease_seconds', 'heartbeat_seconds', 'watermark', 'drm_mode', 'active', 'lock_version']), $actor->id);

            return $profile;
        }, 3);
    }
}
