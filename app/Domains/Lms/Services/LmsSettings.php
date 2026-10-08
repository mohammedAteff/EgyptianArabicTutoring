<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\ProtectionProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LmsSettings
{
    public const KEY = 'lms.options';

    public const DEFAULTS = ['video_threshold' => 95, 'default_catalog_profile_id' => null,
        'inactivity_days' => 14, 'stalled_days' => 14, 'quiz_failures' => 3, 'expiry_days' => 5,
        'low_progress_percent' => 30, 'private_unopened_days' => 7,
        'expiry_notifications' => true, 'drip_notifications' => true];

    /** @var array{version:int,values:array<string,mixed>}|null */
    private ?array $snapshot = null;

    public function __construct(private AuditLogService $audits) {}

    /** @return array{version:int,values:array<string,mixed>} */
    public function document(): array
    {
        if ($this->snapshot === null) {
            $stored = Setting::get(self::KEY, []);
            $this->snapshot = ['version' => (int) ($stored['version'] ?? 0),
                'values' => array_replace(self::DEFAULTS, array_intersect_key($stored['values'] ?? [], self::DEFAULTS))];
        }

        return $this->snapshot;
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        return $this->document()['values'];
    }

    /** Security fallback is reread at authorization boundaries, including after provider verification. */
    public function catalogProfileId(): ?int
    {
        $stored = Setting::get(self::KEY, []);
        $id = $stored['values']['default_catalog_profile_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed> */
    public function save(Administrator $actor, array $input, int $version): array
    {
        Gate::forUser($actor)->authorize('manageSettings', Course::class);
        $values = Validator::make($input, ['video_threshold' => ['required', 'integer', 'between:1,100'],
            'default_catalog_profile_id' => ['nullable', 'integer', 'min:1'],
            'inactivity_days' => ['required', 'integer', 'between:1,365'], 'stalled_days' => ['required', 'integer', 'between:1,365'],
            'quiz_failures' => ['required', 'integer', 'between:1,20'], 'expiry_days' => ['required', 'integer', 'between:1,30'],
            'low_progress_percent' => ['required', 'integer', 'between:1,99'], 'private_unopened_days' => ['required', 'integer', 'between:1,365'],
            'expiry_notifications' => ['required', 'boolean'], 'drip_notifications' => ['required', 'boolean']])->validate();
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (! array_key_exists($key, $values)) {
                $values[$key] = null;
            } elseif (in_array($key, ['expiry_notifications', 'drip_notifications'], true)) {
                $values[$key] = (bool) $values[$key];
            } elseif ($values[$key] !== null) {
                $values[$key] = (int) $values[$key];
            }
        }
        $result = DB::transaction(function () use ($actor, $values, $version): array {
            $actor = Administrator::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('manageSettings', Course::class);
            if ($values['default_catalog_profile_id'] !== null) {
                $profile = ProtectionProfile::query()->lockForUpdate()->find($values['default_catalog_profile_id']);
                if (! $profile || ! $profile->active || ! $profile->secure_playback || $profile->downloads_allowed
                    || $profile->device_limit === null || $profile->stream_limit === null || ! $profile->watermark) {
                    throw ValidationException::withMessages(['default_catalog_profile_id' => 'Choose an active protected profile with a watermark and device/stream limits.']);
                }
            }
            $row = Setting::query()->where('key', self::KEY)->lockForUpdate()->first();
            $old = $row ? json_decode($row->value, true, 512, JSON_THROW_ON_ERROR) : [];
            abort_unless((int) ($old['version'] ?? 0) === $version, 409, 'LMS settings changed. Reload before saving.');
            $document = ['version' => $version + 1, 'values' => $values];
            $row ??= new Setting;
            $row->forceFill(['key' => self::KEY, 'value' => json_encode($document, JSON_THROW_ON_ERROR), 'group' => 'lms', 'is_public' => false])->save();
            $this->audits->log('lms_settings_changed', Setting::class, $row->id, $old['values'] ?? null,
                ['version' => $version + 1] + $values, $actor->id);

            return $document;
        }, 3);
        $this->snapshot = $result;

        return $result;
    }
}
