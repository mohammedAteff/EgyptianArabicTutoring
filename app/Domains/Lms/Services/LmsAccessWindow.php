<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AccessRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsAccessWindow
{
    /** @param array<string, mixed> $data
     * @return array{access_mode: string, starts_at: ?string, expires_at: ?string, relative_days: ?int}
     */
    public function normalize(array $data, bool $rule = false): array
    {
        $mode = $data['access_mode'] ?? 'permanent';
        $values = Validator::make($data, [
            'access_mode' => ['sometimes', Rule::in(['permanent', 'fixed', 'relative'])],
            'starts_at' => [$mode === 'fixed' ? 'required' : 'nullable', 'string'],
            'expires_at' => [$mode === 'fixed' ? 'required' : 'nullable', 'string'],
            'relative_days' => [$mode === 'relative' ? 'required' : 'nullable', 'integer', 'min:1', 'max:36500'],
        ])->validate();
        $start = $this->instant($values['starts_at'] ?? null);
        $end = $this->instant($values['expires_at'] ?? null);
        $days = isset($values['relative_days']) ? (int) $values['relative_days'] : null;
        if (($mode === 'permanent' && ($end !== null || $days !== null))
            || ($mode === 'fixed' && ($days !== null || $end <= $start))
            || ($mode === 'relative' && $end !== null)) {
            throw ValidationException::withMessages(['access_mode' => 'The access window has incompatible or unordered terms.']);
        }

        return ['access_mode' => $mode, 'starts_at' => $start, 'expires_at' => $end, 'relative_days' => $days];
    }

    /** @param array{access_mode: string, starts_at: ?string, expires_at: ?string, relative_days: ?int} $window
     * @return array{access_mode: string, starts_at: ?string, expires_at: ?string, relative_days: ?int}
     */
    public function capture(array $window, bool $rule = false): array
    {
        if (! $rule) {
            $window['starts_at'] ??= CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
            if ($window['access_mode'] === 'relative') {
                $window['expires_at'] = CarbonImmutable::parse($window['starts_at'], 'UTC')->addSeconds($window['relative_days'] * 86400)->format('Y-m-d H:i:s');
            }
        }

        return $window;
    }

    public function active(AccessGrant|AccessRule $fact, ?CarbonImmutable $anchor = null): bool
    {
        $now = CarbonImmutable::now('UTC');
        if ($fact instanceof AccessGrant && $fact->status !== 'active') {
            return false;
        }
        $start = $fact->starts_at ? CarbonImmutable::instance($fact->starts_at)->utc() : null;
        $end = $fact->expires_at ? CarbonImmutable::instance($fact->expires_at)->utc() : null;
        if ($fact instanceof AccessRule && $fact->access_mode === 'relative') {
            if ($anchor === null || ! $fact->relative_days) {
                return false;
            }
            $start = $start && $start->gt($anchor) ? $start : $anchor;
            $end = $start->addSeconds($fact->relative_days * 86400);
        }

        return ($start === null || $now->gte($start)) && ($end === null || $now->lt($end));
    }

    public function instant(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:Z|[+-]\\d{2}:\\d{2})$/D', $value)) {
            throw ValidationException::withMessages(['starts_at' => 'Use an ISO timestamp with an explicit timezone offset.']);
        }
        try {
            $instant = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) {
                throw new \InvalidArgumentException;
            }

            return CarbonImmutable::instance($instant)->utc()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['starts_at' => 'Use a valid calendar timestamp.']);
        }
    }
}
