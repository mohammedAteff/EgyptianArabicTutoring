<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use App\Domains\Timezone\Services\TimezoneService;
use App\Rules\SafeLessonUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(private TimezoneService $timezones) {}

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return Setting::query()->where('group', 'announcement')->pluck('value', 'key')
            ->mapWithKeys(fn (?string $value, string $key): array => [substr($key, strlen('announcement.')) => json_decode($value ?? '', true) ?? $value])
            ->all();
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        foreach (['start_at', 'end_at'] as $field) {
            if (! empty($values[$field])) {
                try {
                    $values[$field] = $this->timezones->resolveLocalWallTime($values[$field], $this->timezones->getBusinessTimezone(), 'reject')->toIso8601String();
                } catch (DstGapException|DstFoldAmbiguityException) {
                    throw ValidationException::withMessages([$field => 'Choose an unambiguous time that exists in the Business Timezone.']);
                }
            }
        }
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                Setting::set('announcement.'.$key, $value ?? '', 'announcement', true);
            }
        });
    }

    /** @return array{message: string, severity: string, dismissible: bool, cta_label: string, cta_url: ?string, version: string}|null */
    public function visible(string $audience): ?array
    {
        $settings = $this->settings();
        if (! filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ($audience === 'student' && ($settings['audience'] ?? 'public') !== 'public_student')) {
            return null;
        }
        $now = CarbonImmutable::now($this->timezones->getBusinessTimezone());
        foreach (['start_at', 'end_at'] as $field) {
            if (! empty($settings[$field])) {
                try {
                    $instant = CarbonImmutable::parse($settings[$field]);
                } catch (\Throwable) {
                    return null;
                }
                if (($field === 'start_at' && $now->lessThan($instant)) || ($field === 'end_at' && $now->greaterThanOrEqualTo($instant))) {
                    return null;
                }
            }
        }
        $locale = app()->getLocale();
        $message = (string) (($settings['message_'.$locale] ?? '') ?: ($settings['message_en'] ?? ''));
        if (trim($message) === '') {
            return null;
        }
        $url = (string) ($settings['cta_url'] ?? '');

        return [
            'message' => $message,
            'severity' => in_array($settings['severity'] ?? '', ['information', 'warning', 'urgent'], true) ? $settings['severity'] : 'information',
            'dismissible' => filter_var($settings['dismissible'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'cta_label' => (string) (($settings['cta_label_'.$locale] ?? '') ?: ($settings['cta_label_en'] ?? '')),
            'cta_url' => SafeLessonUrl::isSafe($url) ? $url : null,
            'version' => hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR)),
        ];
    }
}
