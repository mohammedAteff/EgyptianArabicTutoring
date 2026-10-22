<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Services\LmsTeachingTimeline;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class StudentTimelineService
{
    public function __construct(
        private LmsTeachingTimeline $teachingTimeline,
    ) {}

    /**
     * @return Collection<int, array{key: string, category: string, at: CarbonImmutable, label: string, detail: string, badge_class: string}>
     */
    public function timeline(Administrator $actor, Student $student, ?string $filter = null): Collection
    {
        Gate::forUser($actor)->authorize('manageTeaching', $student);

        $events = collect();

        $add = function (string $key, string $category, mixed $at, string $label, string $detail, string $badgeClass = 'bg-slate-100 text-slate-700') use ($events): void {
            if ($at !== null) {
                $events->put($key, [
                    'key' => $key,
                    'category' => $category,
                    'at' => CarbonImmutable::parse($at, 'UTC'),
                    'label' => $label,
                    'detail' => $detail,
                    'badge_class' => $badgeClass,
                ]);
            }
        };

        // 1. Learning & Teaching Events
        foreach ($this->teachingTimeline->staff($actor, $student) as $item) {
            $add($item['key'], 'learning', $item['at'], $item['label'], $item['detail'], 'bg-indigo-50 text-indigo-700 border border-indigo-200');
        }

        // 2. Billing & Financial Ledger Events (Only if actor has access)
        if ($actor->isAdmin() || $actor->isSuperAdmin()) {
            $ledgerEntries = SessionLedgerEntry::query()
                ->where('student_id', $student->id)
                ->with('package:id,package_name')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            foreach ($ledgerEntries as $entry) {
                $pkgName = $entry->package->package_name ?? 'Package';
                $badge = match ($entry->getAttribute('entry_type')) {
                    'package_grant' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'courtesy_adjustment', 'cancellation_restore', 'transfer_in' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'expiration_forfeit', 'transfer_out' => 'bg-rose-50 text-rose-700 border border-rose-200',
                    default => 'bg-slate-50 text-slate-700 border border-slate-200',
                };

                $label = ucwords(str_replace('_', ' ', $entry->entry_type));
                $units = $entry->credit_change > 0 ? "+{$entry->credit_change}" : (string) $entry->credit_change;
                $memo = $entry->description ? " · {$entry->description}" : '';

                $add(
                    "ledger:{$entry->id}",
                    'billing',
                    $entry->created_at,
                    "Financial: {$label} ({$units})",
                    "{$pkgName}{$memo}",
                    $badge
                );
            }

            // Payments
            $payments = PaymentRecord::query()
                ->where('student_id', $student->id)
                ->orderByDesc('id')
                ->limit(30)
                ->get();

            foreach ($payments as $pay) {
                $cur = strtoupper($pay->currency ?? 'USD');
                $amt = $pay->amount_paid;
                $add(
                    "payment:{$pay->id}",
                    'billing',
                    $pay->paid_at,
                    "Payment Recorded ({$cur} {$amt})",
                    "Method: {$pay->payment_method} · Ref: #{$pay->id}",
                    'bg-emerald-100 text-emerald-800 border border-emerald-300'
                );
            }

            // Refunds
            $refunds = PaymentRefund::query()
                ->where('student_id', $student->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get();

            foreach ($refunds as $ref) {
                $amt = $ref->amount_refunded;
                $cur = strtoupper($ref->currency ?? 'USD');
                $reason = $ref->reason ? " · Reason: {$ref->reason}" : '';
                $add(
                    "refund:{$ref->id}",
                    'billing',
                    $ref->refunded_at,
                    "Payment Refund ({$cur} {$amt})",
                    "Payment: #{$ref->payment_record_id}{$reason}",
                    'bg-rose-100 text-rose-800 border border-rose-300'
                );
            }
        }

        // 3. Bookings & Calendar Lifecycle Events
        $bookings = Booking::query()
            ->where('student_id', $student->id)
            ->with(['sessionType:id,title', 'events' => fn ($query) => $query->whereIn('event_type', ['rescheduled', 'completed', 'marked_no_show', 'restored', 'confirmed'])->orderBy('id')])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        foreach ($bookings as $b) {
            $sessName = $b->sessionType->title ?? 'Tutoring session';
            $add(
                "booking:{$b->id}:created",
                'bookings',
                $b->created_at,
                "Session Booked (#{$b->id})",
                "{$sessName} · Status: {$b->status}",
                'bg-blue-50 text-blue-700 border border-blue-200'
            );

            foreach ($b->events as $event) {
                $label = match ($event->event_type) {
                    'rescheduled' => 'Session Rescheduled',
                    'completed' => 'Session Completed',
                    'marked_no_show' => 'Session Missed',
                    'restored' => 'Session Restored',
                    default => 'Session Confirmed',
                };
                $add("booking-event:{$event->id}", 'bookings', $event->created_at, "{$label} (#{$b->id})", $sessName);
            }

            if ($b->cancelled_at) {
                $add(
                    "booking:{$b->id}:cancelled",
                    'bookings',
                    $b->cancelled_at,
                    "Session Cancelled (#{$b->id})",
                    $b->cancellation_reason ?: 'Cancelled',
                    'bg-rose-50 text-rose-700 border border-rose-200'
                );
            }
        }

        // 4. Educational & Staff Notes
        $notes = StudentBin::query()
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        foreach ($notes as $note) {
            $preview = mb_substr(strip_tags($note->body ?? ''), 0, 80);
            $add(
                "note:{$note->id}",
                'notes',
                $note->created_at,
                'Educational Note Added',
                $note->title ? "{$note->title}: {$preview}" : $preview,
                'bg-amber-50 text-amber-900 border border-amber-300'
            );
        }

        // Filter if requested
        $filtered = match ($filter) {
            'learning' => $events->where('category', 'learning'),
            'billing' => $events->where('category', 'billing'),
            'bookings' => $events->where('category', 'bookings'),
            'notes' => $events->where('category', 'notes'),
            default => $events,
        };

        return $filtered->sort(function (array $a, array $b): int {
            return $b['at']->getTimestamp() <=> $a['at']->getTimestamp() ?: strcmp($b['key'], $a['key']);
        })->take(100)->values();
    }
}
