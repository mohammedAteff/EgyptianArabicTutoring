<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Support\Collection;

class StudentLifetimeSummary
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, array{package: StudentPackage, summary: array<string, mixed>, status: string}>  $packageRows
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, Collection $bookings, Collection $packageRows): array
    {
        $completed = $bookings->where('status', 'completed');
        $packages = $packageRows->pluck('package');
        $money = [];
        foreach ($packageRows as $row) {
            $currency = strtoupper($row['package']->currency);
            $money[$currency] ??= ['paid' => '0.00', 'refunded' => '0.00'];
            $money[$currency]['paid'] = bcadd($money[$currency]['paid'], $row['summary']['gross_paid'], 2);
            $money[$currency]['refunded'] = bcadd($money[$currency]['refunded'], $row['summary']['gross_refunded'], 2);
        }
        ksort($money);

        return [
            'studentSince' => $student->created_at,
            'completedLessonsCount' => $completed->count(),
            'totalHours' => round($completed->sum(fn (Booking $booking): int => max(0, $booking->end_at_utc->timestamp - $booking->start_at_utc->timestamp)) / 3600, 1),
            'activePackagesCount' => $packages->filter(fn (StudentPackage $package): bool => $this->entitlements->eligible($package))->count(),
            'credits' => $this->entitlements->forPackages($packages),
            'money' => $money,
            'nextBooking' => $bookings->whereIn('status', ['confirmed', 'held', 'pending'])
                ->filter(fn (Booking $booking): bool => $booking->start_at_utc->isFuture())->sortBy('start_at_utc')->first(),
        ];
    }

    /** @param Collection<int, Booking> $bookings
     * @param  Collection<int, array<string, mixed>>  $packageRows
     * @return array<string, string>
     */
    public function copyBlocks(Student $student, Collection $bookings, Collection $packageRows, string $timezone): array
    {
        $summary = $this->forStudent($student, $bookings, $packageRows);
        $next = $summary['nextBooking'];
        $nextText = $next ? 'Next lesson: '.$next->start_at_utc->copy()->setTimezone($timezone)->format('Y-m-d H:i T').' · '.$next->sessionType?->title : 'No upcoming lesson.';
        $billing = [];
        foreach ($summary['money'] as $currency => $money) {
            $billing[] = $currency.' · Paid: '.$money['paid'].' · Refunded: '.$money['refunded'].' · Net: '.bcsub($money['paid'], $money['refunded'], 2);
        }
        $packages = $packageRows->map(fn (array $row): string => $row['package']->package_name.' · Purchase #'.$row['package']->id.' · '.$row['summary']['entitlement_status'].' · '.$row['summary']['balance_text'].' · Expiry: '.$row['package']->expiryLabel())->implode("\n");
        $meeting = $next && $next->meeting_url_snapshot ? 'Meeting: '.$next->meeting_provider_snapshot."\n".$next->meeting_url_snapshot : 'Meeting not yet assigned.';

        return ['Billing Summary' => $student->name."\n".(implode("\n", $billing) ?: 'No payment history.'), 'Upcoming Lesson' => $student->name."\n".$nextText,
            'Meeting Details' => $student->name."\n".$nextText."\n".$meeting, 'Package Summary' => $student->name."\n".($packages ?: 'No packages recorded.')];
    }
}
