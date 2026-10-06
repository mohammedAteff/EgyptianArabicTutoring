<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Services\OperationalReasonCatalog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\CMS\Models\Setting;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Services\StudentLedgerService;
use Illuminate\Support\Facades\Validator;

class BookingPolicyService
{
    /** @return array{version: int, cancellation_cutoff_hours: int, late_cancellation: string, staff_cancellation: string, no_show: string} */
    public function legacy(): array
    {
        return ['version' => 0, 'cancellation_cutoff_hours' => max(0, (int) Setting::get('booking_cancellation_cutoff_hours', 4)),
            'late_cancellation' => 'deny', 'staff_cancellation' => 'restore', 'no_show' => 'retain'];
    }

    /** @return array{version: int, cancellation_cutoff_hours: int, late_cancellation: string, staff_cancellation: string, no_show: string} */
    public function current(): array
    {
        $stored = Setting::get('booking_lifecycle_policy');
        $defaults = array_replace($this->legacy(), ['version' => 1]);
        if (! is_array($stored) || Validator::make($stored, $this->rules())->fails()) {
            return $defaults;
        }
        $policy = array_replace($defaults, array_intersect_key($stored, $defaults));
        $policy['cancellation_cutoff_hours'] = (int) $policy['cancellation_cutoff_hours'];
        $policy['version'] = 1;

        return $policy;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data, ?int $administratorId): void
    {
        $validated = Validator::make($data, $this->rules())->validate();
        app(DatabaseCapability::class)->transaction(function () use ($validated, $administratorId): void {
            Setting::query()->where('key', 'booking_lifecycle_policy')->lockForUpdate()->first();
            $previous = $this->current();
            $policy = array_replace($validated, ['version' => 1, 'cancellation_cutoff_hours' => (int) $validated['cancellation_cutoff_hours']]);
            Setting::set('booking_lifecycle_policy', $policy, 'booking');
            app(AuditLogService::class)->log('booking_policy_updated', Setting::class, null, $previous, $policy, $administratorId);
        }, 3);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'cancellation_cutoff_hours' => ['required', 'integer', 'between:0,168'],
            'late_cancellation' => ['required', 'in:deny,restore,retain'],
            'staff_cancellation' => ['required', 'in:restore,retain'],
            'no_show' => ['required', 'in:restore,retain'],
        ];
    }

    /** @return array<string, mixed> */
    public function forBooking(Booking $booking): array
    {
        return $booking->policy_snapshot ?? $this->legacy();
    }

    public function cancellationOutcome(Booking $booking, string $actor): string
    {
        $policy = $this->forBooking($booking);
        if ($actor !== 'customer') {
            return $policy['staff_cancellation'];
        }
        if ($booking->start_at_utc <= now('UTC')) {
            throw new BookingPolicyViolationException('Past appointments cannot be cancelled.');
        }
        $cutoff = (int) $policy['cancellation_cutoff_hours'];
        if ($booking->start_at_utc < now('UTC')->addHours($cutoff)) {
            if ($policy['late_cancellation'] === 'deny') {
                throw new BookingPolicyViolationException("Appointments cannot be cancelled within {$cutoff} hours of the scheduled start time.");
            }

            return $policy['late_cancellation'];
        }

        return 'restore';
    }

    public function record(Booking $booking, string $action, string $outcome, string $actor, ?int $actorId, ?string $reasonCode = null): void
    {
        OperationalReasonCatalog::validate($reasonCode);
        if ($outcome === 'restore') {
            app(StudentLedgerService::class)->restoreCancellation($booking, 'booking-cancellation-restore:'.$booking->id,
                $action === 'no_show' ? 'Original entitlement restored under no-show policy' : 'Original entitlement restored after cancellation');
        }
        BookingPolicyDecision::query()->create([
            'booking_id' => $booking->id, 'action' => $action, 'outcome' => $outcome,
            'policy_snapshot' => $this->forBooking($booking), 'reason_code' => $reasonCode,
            'actor_type' => $actor, 'actor_id' => $actorId, 'created_at' => now('UTC'),
        ]);
    }
}
