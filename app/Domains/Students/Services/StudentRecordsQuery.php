<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentRecordsQuery
{
    public function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['verified', 'legacy_unverified', 'suspended'])],
            'package' => ['nullable', 'string', 'max:160'],
            'credits' => ['nullable', Rule::in(['available', 'none'])],
            'expiry_from' => ['nullable', 'date_format:Y-m-d'], 'expiry_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expiry_from'],
            'timezone' => ['nullable', 'timezone'],
            'session_status' => ['nullable', Rule::in(['confirmed', 'completed', 'cancelled', 'no_show'])],
            'joined_from' => ['nullable', 'date_format:Y-m-d'], 'joined_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_from'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);
    }

    /** @return Builder<Student> */
    public function query(array $filters): Builder
    {
        $query = Student::tutoringRoster()->withCount('bookings')->with(['packages.ledgerEntries', 'packages.payments', 'packages.refunds']);
        if (! empty($filters['q'])) {
            $identity = app(StudentIdentityService::class);
            $raw = trim($filters['q']);
            $normalizedPhone = null;
            if (str_starts_with($raw, '+')) {
                try {
                    $normalizedPhone = $identity->normalizePhone($raw);
                } catch (\InvalidArgumentException) {
                    $normalizedPhone = null;
                }
            }
            $terms = ['name_normalized' => $identity->normalizeName($raw), 'email_normalized' => $identity->normalizeEmail($raw), 'phone_normalized' => $normalizedPhone];
            $query->where(function (Builder $students) use ($terms, $raw): void {
                foreach ($terms as $column => $term) {
                    if ($term !== null && $term !== '') {
                        $students->orWhere($column, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%');
                    }
                }
                $students->orWhereHas('verifiedEmails', fn (Builder $emails) => $emails->whereNotNull('verified_at')->where('email_normalized', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($raw)).'%'));
            });
        }
        if (! empty($filters['status'])) {
            $filters['status'] === 'suspended' ? $query->whereNotNull('suspended_at') : $query->where('identity_status', $filters['status'])->whereNull('suspended_at');
        }
        if (! empty($filters['timezone'])) {
            $query->where('preferred_timezone', $filters['timezone']);
        }
        if (! empty($filters['session_status'])) {
            $query->whereHas('bookings', fn (Builder $bookings) => $bookings->where('status', $filters['session_status']));
        }
        foreach (['joined_from' => '>=', 'joined_to' => '<'] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $boundary = CarbonImmutable::parse($filters[$filter], app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
                $query->where('created_at', $operator, ($filter === 'joined_to' ? $boundary->addDay() : $boundary)->utc());
            }
        }
        if (! empty($filters['package']) || ! empty($filters['expiry_from']) || ! empty($filters['expiry_to'])) {
            $query->whereHas('packages', function (Builder $packages) use ($filters): void {
                if (! empty($filters['package'])) {
                    $packages->where('package_name', $filters['package']);
                }
                foreach (['expiry_from' => '>=', 'expiry_to' => '<='] as $filter => $operator) {
                    if (! empty($filters[$filter])) {
                        $packages->whereDate('expiration_date', $operator, $filters[$filter]);
                    }
                }
            });
        }
        if (! empty($filters['credits'])) {
            $available = fn (Builder $packages) => $packages->where('status', 'active')->where(function (Builder $packages): void {
                $packages->where(function (Builder $unlimited): void {
                    $unlimited->whereNull('expiration_date');
                    foreach (StudentLedgerService::PRESETS as $preset) {
                        $unlimited->where(fn (Builder $custom) => $custom->where('package_name', '!=', $preset['name'])->orWhere('total_sessions_allocated', '!=', $preset['sessions'])->orWhere('currency', '!=', 'USD'));
                    }
                })->orWhereDate('expiration_date', '>=', now(app(TimezoneService::class)->getBusinessTimezone())->toDateString());
            })->whereRaw('(select coalesce(sum(credit_change),0) from session_ledger_entries where student_package_id = student_packages.id) > 0');
            $filters['credits'] === 'available' ? $query->whereHas('packages', $available) : $query->whereDoesntHave('packages', $available);
        }

        return $query->orderBy('name_normalized')->orderBy('id');
    }
}
