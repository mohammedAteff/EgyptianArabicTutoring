<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected ContactService $contactService
    ) {}

    public function index(Request $request): View
    {
        $businessTz = $this->timezoneService->getBusinessTimezone();
        $cairoNow = CarbonImmutable::now($businessTz);
        $utcNow = CarbonImmutable::now('UTC');

        // 1. Next upcoming confirmed booking
        $nextBooking = Booking::query()
            ->with(['contact', 'sessionType'])
            ->where('status', 'confirmed')
            ->where('start_at_utc', '>=', $utcNow)
            ->orderBy('start_at_utc')
            ->first();

        // 2. Today's bookings in business timezone
        $cairoStartOfDayUtc = $cairoNow->startOfDay()->setTimezone('UTC');
        $cairoEndOfDayUtc = $cairoNow->endOfDay()->setTimezone('UTC');

        $todaysBookings = Booking::query()
            ->with(['contact', 'sessionType'])
            ->whereBetween('start_at_utc', [$cairoStartOfDayUtc, $cairoEndOfDayUtc])
            ->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->orderBy('start_at_utc')
            ->get();

        // 3. Needs attention
        $suspectedDuplicates = $this->contactService->findSuspectedDuplicates();
        $recentCancellations = Booking::query()
            ->with(['contact', 'sessionType'])
            ->where('status', 'cancelled')
            ->where('cancelled_at', '>=', $utcNow->subDays(7))
            ->orderByDesc('cancelled_at')
            ->take(5)
            ->get();

        // 4. KPI Period
        $period = $request->query('period', '30days');
        $periodStartUtc = match ($period) {
            'today' => $cairoStartOfDayUtc,
            '7days' => $utcNow->subDays(7),
            'yesterday' => $cairoNow->subDay()->startOfDay()->setTimezone('UTC'),
            default => $utcNow->subDays(30),
        };

        $kpis = [
            'period' => $period,
            'confirmed_bookings' => Booking::query()
                ->where('created_at', '>=', $periodStartUtc)
                ->where('status', 'confirmed')
                ->count(),
            'completed_bookings' => Booking::query()
                ->where('created_at', '>=', $periodStartUtc)
                ->where('status', 'completed')
                ->count(),
            'total_students' => Student::tutoringRoster()->count(),
            'resource_requests' => ResourceRequest::query()
                ->where('created_at', '>=', $periodStartUtc)
                ->count(),
            'resource_downloads' => ResourceDownload::query()
                ->where('created_at', '>=', $periodStartUtc)
                ->count(),
        ];

        // 5. Recent operational audit logs
        $recentAuditLogs = AuditLog::query()
            ->with('administrator')
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        // 6. Backup alerts
        $offsiteBackupStatus = Setting::get('last_offsite_backup_status');
        $offsiteBackupCategory = Setting::get('last_offsite_backup_category', 's3_replication_failed');

        return view('admin.dashboard', [
            'title' => 'Tutor Operations Console',
            'businessTz' => $businessTz,
            'cairoNow' => $cairoNow,
            'nextBooking' => $nextBooking,
            'todaysBookings' => $todaysBookings,
            'suspectedDuplicates' => $suspectedDuplicates,
            'recentCancellations' => $recentCancellations,
            'kpis' => $kpis,
            'recentAuditLogs' => $recentAuditLogs,
            'offsiteBackupStatus' => $offsiteBackupStatus,
            'offsiteBackupCategory' => $offsiteBackupCategory,
        ]);
    }
}
