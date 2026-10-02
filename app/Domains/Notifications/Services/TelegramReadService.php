<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormAssignmentService;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class TelegramReadService
{
    public function __construct(private StudentLedgerService $ledger, private AnalyticsService $analytics, private TelegramAutomationService $automation, private FormAssignmentService $assignments, private ReportService $reports) {}

    /** @return array<string, scalar|null> */
    public function booking(Booking $booking): array
    {
        $booking->loadMissing(['student', 'contact', 'sessionType']);
        $entry = SessionLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'consumed')->first();
        if (! $entry) {
            $entry = SessionLedgerEntry::where('booking_id', $booking->id)->where('credit_change', '<', 0)->first();
        }
        $package = $entry?->package;
        $data = ['_booking_start' => $booking->start_at_utc->timestamp, '_student_id' => $booking->student_id, '_session_type_id' => $booking->session_type_id, 'country' => $booking->detected_country_code, 'source' => $booking->source, 'booking_id' => $booking->id, 'student_name' => $booking->student->name ?? $booking->contact->name ?? 'Student', 'email' => $booking->student->email ?? $booking->contact->email ?? null, 'phone' => $booking->student->phone ?? $booking->contact->phone ?? null, 'session_title' => $booking->sessionType->title ?? 'Lesson', 'tutor_time' => $booking->business_start->format('Y-m-d H:i T'), 'student_time' => $booking->customer_start->format('Y-m-d H:i T'), 'admin_url' => route('admin.bookings.index'), 'meeting_url' => $booking->status === 'confirmed' ? (string) Setting::get('video_meeting_url', '') : 'Unavailable', 'booking_context' => $package ? 'Returning package student' : 'Diagnostic booking', 'remaining_credits' => $package ? $this->ledger->summary($package)['remaining_credits'] : 0, 'session_number' => $package ? (int) $package->ledgerEntries()->where('credit_change', '<', 0)->where('id', '<=', $entry->id)->count() : 1, 'total_sessions' => $package->total_sessions_allocated ?? 1];

        return $data;
    }

    /** @return array<string, scalar|null> */
    public function package(StudentPackage $package): array
    {
        $package->loadMissing('student');

        return ['_student_id' => $package->student_id, 'student_name' => $package->student->name, 'email' => $package->student->email, 'phone' => $package->student->phone, 'package_name' => $package->package_name, 'remaining_credits' => $this->ledger->summary($package)['remaining_credits'], 'expiry_date' => $package->expiration_date?->format('Y-m-d') ?? 'Never', 'admin_url' => route('admin.students.show', $package->student_id)];
    }

    public function scanPackage(StudentPackage $package): void
    {
        foreach (TelegramRule::where('enabled', true)->whereIn('trigger', ['low_credits', 'package_expiring'])->whereHas('bot', fn ($query) => $query->where('enabled', true))->get() as $rule) {
            $this->evaluatePackage($package, $rule, CarbonImmutable::now('UTC'));
        }
    }

    private function evaluatePackage(StudentPackage $package, TelegramRule $rule, CarbonImmutable $now): void
    {
        if ($package->status !== 'active') {
            return;
        }
        $data = $this->package($package);
        $remaining = (int) $data['remaining_credits'];
        $expiry = $package->expiration_date ? CarbonImmutable::parse($package->expiration_date->format('Y-m-d'), (string) Setting::get('business_timezone', 'Africa/Cairo'))->endOfDay()->setTimezone('UTC') : null;
        if ($expiry && $expiry->lt($now)) {
            return;
        }
        $matched = $rule->trigger === 'low_credits' ? $remaining <= $rule->threshold : ($remaining > 0 && $expiry && $expiry->lte($now->addDays($rule->threshold)));
        if ($matched) {
            $this->automation->emit($rule->trigger, 'package:'.$package->id.':'.$now->timestamp, $data, 'package:'.$package->id, $rule->id);
        }
    }

    public function tick(): void
    {
        $now = CarbonImmutable::now('UTC');
        foreach (TelegramRule::where('enabled', true)->whereHas('bot', fn ($q) => $q->where('enabled', true))->get() as $rule) {
            if (in_array($rule->trigger, ['scheduler_stale', 'queue_stale'], true)) {
                continue;
            }
            if (in_array($rule->trigger, ['low_credits', 'package_expiring'], true)) {
                StudentPackage::where('status', 'active')->chunkById(100, function ($packages) use ($rule, $now): void {
                    foreach ($packages as $package) {
                        $this->evaluatePackage($package, $rule, $now);
                    }
                });
            } elseif (in_array($rule->trigger, ['session_reminder', 'missing_form'], true)) {
                Booking::where('status', 'confirmed')->where('start_at_utc', '>', $now)->where('start_at_utc', '<=', $now->addMinutes($rule->minutes))->chunkById(100, function ($bookings) use ($rule): void {
                    foreach ($bookings as $booking) {
                        $data = $this->booking($booking);
                        $identity = 'booking:'.$booking->id.':'.$booking->start_at_utc->timestamp;
                        if ($rule->trigger === 'session_reminder') {
                            $this->automation->emit($rule->trigger, $identity, $data, null, $rule->id);
                        } elseif ($booking->student) {
                            foreach ($this->assignments->assignedTo($booking->student)->where('is_mandatory', true) as $form) {
                                if (! FormSubmission::where('student_id', $booking->student_id)->where('form_version_id', $form->published_version_id)->where('status', 'submitted')->exists()) {
                                    $this->automation->emit('missing_form', $identity.':form:'.$form->published_version_id, array_merge($data, ['form_title' => $form->title, '_form_version_id' => $form->published_version_id]), null, $rule->id);
                                }
                            }
                        }
                    }
                });
            } elseif ($rule->trigger === 'traffic_spike') {
                $count = $this->analytics->getActiveVisitorsCount($rule->window_minutes);
                if ($count >= $rule->threshold) {
                    $source = $this->analytics->getActiveVisitorsSummary($rule->window_minutes, 1)->first();
                    $this->automation->emit('traffic_spike', 'traffic:'.$now->timestamp, ['unique_visitors' => $count, 'window_minutes' => $rule->window_minutes, 'source' => $source['source'] ?? 'Unknown'], 'traffic', $rule->id);
                }
            } elseif ($rule->trigger === 'maintenance_duration') {
                $enabled = filter_var(Setting::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
                $since = Setting::get('telegram.maintenance_since');
                if ($enabled && is_string($since) && $now->gte(CarbonImmutable::parse($since)->addMinutes($rule->minutes))) {
                    $this->automation->emit('maintenance_duration', 'maintenance:'.$now->timestamp, ['enabled_at' => $since, 'duration_minutes' => (int) CarbonImmutable::parse($since)->diffInMinutes($now)], 'maintenance:'.$since, $rule->id);
                }
            } elseif (in_array($rule->trigger, ['business_digest', 'analytics_digest'], true)) {
                $local = $now->setTimezone((string) Setting::get('business_timezone', 'Africa/Cairo'));
                if ($rule->mode === 'scheduled' && $local->format('H:i') >= $rule->send_time) {
                    $this->automation->emit($rule->trigger, 'digest:'.$local->toDateString(), ['date' => $local->toDateString(), 'digest' => $rule->trigger === 'business_digest' ? $this->businessDigest($rule->sections ?? []) : $this->stats(1)], null, $rule->id);
                }
            }
        }
    }

    public function watchdog(): void
    {
        Setting::set('telegram.last_watchdog_run_at', now('UTC')->toIso8601String(), 'telegram');
        foreach (TelegramRule::where('enabled', true)->whereIn('trigger', ['scheduler_stale', 'queue_stale'])->get() as $rule) {
            if ($rule->trigger === 'queue_stale' && is_string(Cache::get('queue_worker_heartbeat_at'))) {
                Setting::set('telegram.last_queue_seen', Cache::get('queue_worker_heartbeat_at'), 'telegram');
            }
            $heartbeat = $rule->trigger === 'scheduler_stale' ? Setting::get('last_scheduler_run_at') : (Cache::get('queue_worker_heartbeat_at') ?? Setting::get('telegram.last_queue_seen'));
            if (! $heartbeat || CarbonImmutable::parse((string) $heartbeat)->lt(now('UTC')->subMinutes($rule->minutes))) {
                $this->automation->emit($rule->trigger, 'health:'.now('UTC')->timestamp, ['last_heartbeat' => is_string($heartbeat) ? $heartbeat : 'Never observed', 'threshold_minutes' => $rule->minutes], $rule->trigger, $rule->id);
            }
        }
    }

    /** @param list<string> $sections */
    public function businessDigest(array $sections): string
    {
        $today = CarbonImmutable::now((string) Setting::get('business_timezone', 'Africa/Cairo'))->startOfDay();
        $start = $today->setTimezone('UTC');
        $end = $today->addDay()->setTimezone('UTC');
        $lines = [];
        if (in_array('bookings', $sections, true)) {
            $lines[] = 'Bookings created today: '.Booking::whereBetween('created_at', [$start, $end->subMicrosecond()])->count();
            $lines[] = 'Sessions today: '.Booking::where('status', 'confirmed')->whereBetween('start_at_utc', [$start, $end->subMicrosecond()])->count();
            $lines[] = 'Sessions tomorrow: '.Booking::where('status', 'confirmed')->whereBetween('start_at_utc', [$end, $end->addDay()->subMicrosecond()])->count();
            foreach (['created', 'rescheduled', 'cancelled'] as $type) {
                $lines[] = ucfirst($type).' events today: '.BookingEvent::where('event_type', $type)->whereBetween('created_at', [$start, $end->subMicrosecond()])->count();
            }
        }
        if (in_array('students', $sections, true)) {
            $low = 0;
            $expiry = 0;
            foreach (StudentPackage::where('status', 'active')->cursor() as $package) {
                $remaining = $this->ledger->summary($package)['remaining_credits'];
                if ($remaining <= 1 && (! $package->expiration_date || $package->expiration_date->gte($today))) {
                    $low++;
                } if ($remaining > 0 && $package->expiration_date && $package->expiration_date->between($today, $today->addDays(14))) {
                    $expiry++;
                }
            } $lines[] = 'Low-credit packages (≤1): '.$low;
            $lines[] = 'Unused packages expiring within 14 days: '.$expiry;
        }
        if (in_array('resources', $sections, true)) {
            $lines[] = 'Resource leads today: '.ResourceRequest::whereBetween('created_at', [$start, $end->subMicrosecond()])->count();
            $lines[] = 'Downloads today: '.ResourceDownload::whereBetween('created_at', [$start, $end->subMicrosecond()])->count();
        }
        if (in_array('system', $sections, true)) {
            $lines[] = 'Backup status: '.(str_starts_with((string) Setting::get('last_backup_status', 'unknown'), 'success') ? 'success' : 'not successful / unknown');
            $lines[] = 'Maintenance: '.(filter_var(Setting::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off');
            $lines[] = 'Scheduler heartbeat: '.(string) Setting::get('last_scheduler_run_at', 'never');
        }
        if (in_array('analytics', $sections, true)) {
            $lines[] = $this->stats(1);
        }

        return implode("\n", $lines) ?: 'No digest sections selected.';
    }

    public function stats(int $days): string
    {
        $end = CarbonImmutable::now('Africa/Cairo')->endOfDay();
        $start = $end->startOfDay()->subDays($days - 1);
        $metrics = $this->analytics->reportingMetrics($start, $end)->where('dimension_key', '');
        $traffic = $this->reports->getTrafficReport($start, $end)['summary'];
        $lines = ['Analytics — '.$start->toDateString().' to '.$end->toDateString().' (Africa/Cairo)'];
        $lines[] = ($traffic['visitors_is_daily_sum'] ? 'Sum of daily unique visitors' : 'Unique visitors').': '.$traffic['visitors'];
        $lines[] = 'Sessions: '.$traffic['sessions'];
        $lines[] = 'Page views: '.$traffic['page_views'];
        foreach (['bounced_sessions', 'booking_completed', 'resource_requested', 'resource_downloaded'] as $metric) {
            $lines[] = ucwords(str_replace('_', ' ', $metric)).': '.$metrics->where('metric_name', $metric)->sum('count');
        }
        $social = $this->reports->getSocialReport($start->setTimezone('UTC'), $end->setTimezone('UTC'));
        $lines[] = 'Social clicks: '.$social['total_clicks'];
        foreach ($social['platform_totals'] as $platform => $clicks) {
            $lines[] = $platform.': '.$clicks;
        }
        $lines[] = 'Top content (retained page-view events):';
        $events = $this->reports->getEventsReport($start->setTimezone('UTC'), $end->setTimezone('UTC'), 'page_view')['rows'];
        foreach ($events->groupBy('page')->map(fn ($rows): int => (int) $rows->sum('event_count'))->sortDesc()->take(5) as $page => $views) {
            $lines[] = $page.': '.$views;
        }
        $lines[] = 'Traffic sources (dashboard acquisition report):';
        foreach ($this->analytics->getAcquisitionPerformance($start->setTimezone('UTC'), $end->setTimezone('UTC'))->take(5) as $source) {
            $lines[] = $source->source.' / '.$source->medium.' / '.$source->campaign.': '.$source->visitors;
        }
        $lines[] = 'Top countries (acquisition visitors):';
        foreach ($this->analytics->reportingCountries($start, $end)->sortByDesc('unique_visitors')->take(5) as $country) {
            $lines[] = $country->country_code.': '.$country->unique_visitors;
        }
        $lines[] = 'Historical periods before the analytics cutover may not be comparable.';

        return implode("\n", $lines);
    }

    public function sessions(bool $tomorrow, bool $personal): string
    {
        $date = CarbonImmutable::now((string) Setting::get('business_timezone', 'Africa/Cairo'))->startOfDay();
        if ($tomorrow) {
            $date = $date->addDay();
        }
        $bookings = Booking::where('status', 'confirmed')->where('start_at_utc', '>=', $date->setTimezone('UTC'))->where('start_at_utc', '<', $date->addDay()->setTimezone('UTC'))->orderBy('start_at_utc')->get();
        $lines = [$date->toDateString().' — '.$bookings->count().' confirmed sessions'];
        foreach ($bookings->take(50) as $booking) {
            $lines[] = $booking->business_start->format('H:i').' '.($personal ? $this->booking($booking)['student_name'] : 'Lesson #'.$booking->id);
        }
        if ($bookings->count() > 50) {
            $lines[] = 'Additional sessions: '.($bookings->count() - 50);
        }

        return implode("\n", $lines);
    }
}
