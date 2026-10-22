<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Services\FormCompletionReadModel;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneDisplayService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramReadService
{
    public function __construct(private StudentLedgerService $ledger, private AnalyticsService $analytics, private TelegramAutomationService $automation, private ReportService $reports) {}

    /** @return array<string, scalar|null> */
    public function booking(Booking $booking): array
    {
        $booking->loadMissing(['student', 'contact', 'sessionType']);
        $booking->loadMissing('consumedEntry.package');
        $entry = $booking->consumedEntry;
        $package = $entry?->package;
        $package?->loadMissing(['payments', 'refunds', 'ledgerEntries', 'entitlements.type']);
        $data = ['_booking_start' => $booking->start_at_utc->timestamp, '_student_id' => $booking->student_id, '_session_type_id' => $booking->session_type_id, 'country' => $booking->detected_country_code, 'source' => $booking->source, 'booking_id' => $booking->id, 'student_name' => $booking->student->name ?? $booking->contact->name ?? 'Student', 'email' => $booking->student->email ?? $booking->contact->email ?? null, 'phone' => $booking->student->phone ?? $booking->contact->phone ?? null, 'session_title' => $booking->sessionType->title ?? 'Lesson', 'tutor_time' => $booking->business_start->format('D j M Y, H:i T'), 'student_time' => $booking->start_at_utc->copy()->setTimezone($booking->student?->preferred_timezone ?: $booking->customer_timezone)->format('D j M Y, g:i A T'), 'admin_url' => route('admin.bookings.index'), 'meeting_url' => app(MeetingLinkService::class)->notificationUrl($booking, 0) ?? 'Unavailable', 'booking_context' => $package ? 'Returning package student' : 'Diagnostic booking', 'remaining_credits' => $package ? $this->ledger->summary($package, true)['balance_text'] : 'Direct booking', 'session_number' => $package ? (int) $package->ledgerEntries->where('credit_change', '<', 0)->where('id', '<=', $entry->id)->count() : 1, 'total_sessions' => $package->total_sessions_allocated ?? 1];

        return $data;
    }

    /** @return array<string, scalar|null> */
    public function package(StudentPackage $package): array
    {
        $package->loadMissing(['student', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type']);

        return ['_student_id' => $package->student_id, 'student_name' => $package->student->name, 'email' => $package->student->email, 'phone' => $package->student->phone, 'package_name' => $package->package_name, 'remaining_credits' => $this->ledger->summary($package, true)['balance_text'], 'offering_key' => $package->offering_key ?? 'legacy_unclassified', 'purchase_id' => $package->id, 'expiry_date' => app(TelegramReportFormatter::class)->expiry($package), 'admin_url' => route('admin.students.show', $package->student_id)];
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
        $service = app(EntitlementService::class);
        if (! $service->eligible($package)) {
            return;
        }
        $package->loadMissing(['student.packages.entitlements.type', 'student.packages.ledgerEntries']);
        if ($rule->trigger === 'low_credits' && ! $service->allCreditsLow($package->student->packages, $rule->threshold)) {
            return;
        }
        $expiry = $package->expiryDeadline();
        foreach ($service->projection($package) as $allocation) {
            if ($allocation['id'] === null) {
                continue;
            }
            $remaining = $allocation['available'];
            $matched = $rule->trigger === 'low_credits' ? $remaining <= $rule->threshold : ($remaining > 0 && $expiry && $expiry->lte($now->addDays($rule->threshold)));
            if ($matched) {
                $data = array_merge($this->package($package), ['remaining_credits' => $allocation['label'].': '.$remaining, 'entitlement_code' => $allocation['code'], 'entitlement_label' => $allocation['label'], 'allocation_id' => $allocation['id'], 'available_units' => $remaining]);
                $scope = 'package:'.$package->id.':allocation:'.$allocation['id'];
                $this->automation->emit($rule->trigger, $scope.':'.$now->timestamp, $data, $scope, $rule->id);
            }
        }
    }

    public function tick(): void
    {
        $now = CarbonImmutable::now('UTC');
        $rules = TelegramRule::where('enabled', true)->whereHas('bot', fn ($q) => $q->where('enabled', true))->get();
        $packageRules = $rules->whereIn('trigger', ['low_credits', 'package_expiring']);
        if ($packageRules->isNotEmpty()) {
            StudentPackage::where('status', 'active')->with(['student.packages.entitlements.type', 'student.packages.ledgerEntries', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type'])
                ->chunkById(100, function ($packages) use ($packageRules, $now): void {
                    foreach ($packages as $package) {
                        foreach ($packageRules as $rule) {
                            $this->evaluatePackage($package, $rule, $now);
                        }
                    }
                });
        }
        $bookingRules = $rules->whereIn('trigger', ['session_reminder', 'missing_form']);
        if ($bookingRules->isNotEmpty()) {
            Booking::where('status', 'confirmed')->where('start_at_utc', '>', $now)->where('start_at_utc', '<=', $now->addMinutes((int) $bookingRules->max('minutes')))
                ->with(['student', 'contact', 'sessionType', 'consumedEntry.package.payments', 'consumedEntry.package.refunds', 'consumedEntry.package.entitlements.type', 'consumedEntry.package.ledgerEntries'])
                ->chunkById(100, function ($bookings) use ($bookingRules, $now): void {
                    $forms = $bookingRules->contains('trigger', 'missing_form')
                        ? app(FormCompletionReadModel::class)->forStudents($bookings->pluck('student_id')->filter()->unique()->values()->all()) : collect();
                    foreach ($bookings as $booking) {
                        $facts = $this->booking($booking);
                        foreach ($bookingRules as $rule) {
                            if ($booking->start_at_utc->gt($now->addMinutes($rule->minutes))) {
                                continue;
                            }
                            $data = $facts;
                            $data['meeting_url'] = app(MeetingLinkService::class)->notificationUrl($booking, $rule->minutes) ?? 'Unavailable';
                            $data['meeting_provider'] = $booking->meeting_provider_snapshot ?? 'Unassigned';
                            $identity = 'booking:'.$booking->id.':'.$booking->start_at_utc->timestamp;
                            if ($rule->trigger === 'session_reminder') {
                                $this->automation->emit($rule->trigger, $identity, $data, null, $rule->id);
                            } elseif ($booking->student) {
                                foreach ($forms->get($booking->student_id, collect()) as $form) {
                                    if ($form['mandatory'] && ! $form['has_submitted']) {
                                        $versionId = $form['form_version_id'];
                                        $this->automation->emit('missing_form', $identity.':form:'.$versionId, array_merge($data, ['form_title' => $form['title'], '_form_version_id' => $versionId]), null, $rule->id);
                                    }
                                }
                            }
                        }
                    }
                });
        }
        foreach ($rules as $rule) {
            if (in_array($rule->trigger, ['scheduler_stale', 'queue_stale', 'low_credits', 'package_expiring', 'session_reminder', 'missing_form'], true)) {
                continue;
            }
            if (in_array($rule->trigger, TelegramFollowUpService::TRIGGERS, true)) {
                app(TelegramFollowUpService::class)->scan($rule);
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
            } elseif (in_array($rule->trigger, TelegramRuleCatalog::DIGESTS, true)) {
                $local = $now->setTimezone((string) Setting::get('business_timezone', app(TimezoneService::class)->getBusinessTimezone()));
                if ($rule->trigger === 'weekly_summary' && $local->dayOfWeekIso !== 1) {
                    continue;
                }
                if ($rule->mode === 'scheduled' && $local->format('H:i') >= $rule->send_time) {
                    $this->automation->emit($rule->trigger, 'digest:'.$local->toDateString(), ['date' => $local->toDateString(), 'digest' => in_array($rule->trigger, ['business_digest', 'analytics_digest'], true) ? $this->digest($rule->trigger, $rule->sections ?? [], false) : ''], null, $rule->id);
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
        $today = CarbonImmutable::now((string) Setting::get('business_timezone', app(TimezoneService::class)->getBusinessTimezone()))->startOfDay();
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
            $low = app(TelegramBusinessReadService::class)->lowCreditStudents()->count();
            $expiry = 0;
            $entitlements = app(EntitlementService::class);
            $expiryLimit = CarbonImmutable::now('UTC')->addDays(14);
            foreach (StudentPackage::where('status', 'active')->with(['entitlements.type', 'ledgerEntries'])->lazyById(100) as $package) {
                if (! $entitlements->eligible($package)) {
                    continue;
                }
                $deadline = $package->expiryDeadline();
                foreach ($entitlements->projection($package) as $allocation) {
                    if ($allocation['id'] === null) {
                        continue;
                    }
                    $remaining = $allocation['available'];
                    if ($remaining > 0 && $deadline && $deadline->lessThanOrEqualTo($expiryLimit)) {
                        $expiry++;
                    }
                }
            }
            $lines[] = 'Students with low credits (all types ≤1): '.$low;
            $lines[] = 'Unused entitlement allocations expiring within 14 days: '.$expiry;
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

    /** @param list<string> $sections */
    public function digest(string $trigger, array $sections, bool $personal): string
    {
        $business = app(TelegramBusinessReadService::class);

        return match ($trigger) {
            'business_digest' => $this->businessDigest($sections),
            'analytics_digest' => $this->stats(1, view: 'overview')."\n\nMore details: /stats today",
            'morning_briefing' => $business->briefing('morning', $personal),
            'daily_wrapup' => $business->briefing('wrap', $personal),
            'weekly_summary' => $business->weekly($personal),
            default => throw new \InvalidArgumentException('Unknown digest.'),
        };
    }

    public function stats(int $days, ?CarbonImmutable $from = null, ?CarbonImmutable $through = null, string $view = 'full'): string
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $end = ($through ?? CarbonImmutable::now($timezone)->endOfDay())->setTimezone($timezone);
        $start = $from?->setTimezone($timezone) ?? $end->startOfDay()->subDays($days - 1);
        $metrics = $this->analytics->reportingMetrics($start, $end)->where('dimension_key', '');
        $traffic = $this->reports->getTrafficReport($start, $end)['summary'];
        $heading = ['<b>Analytics</b>', app(TelegramReportFormatter::class)->period($start, $end)];
        $sections = [];
        $lines = ['', '<b>Traffic</b>'];
        $lines[] = ($traffic['visitors_is_daily_sum'] ? 'Sum of daily unique visitors' : 'Unique visitors').': '.$traffic['visitors'];
        $lines[] = 'Sessions: '.$traffic['sessions'];
        $lines[] = 'Page views: '.$traffic['page_views'];
        $lines[] = 'Bounced sessions: '.$metrics->where('metric_name', 'bounced_sessions')->sum('count');
        $sections['traffic'] = $lines;
        $lines = ['', '<b>Actions</b>'];
        foreach (['booking_completed' => 'Bookings completed', 'resource_requested' => 'Resources requested', 'resource_downloaded' => 'Resources downloaded'] as $metric => $label) {
            $lines[] = $label.': '.$metrics->where('metric_name', $metric)->sum('count');
        }
        $social = $this->reports->getSocialReport($start->setTimezone('UTC'), $end->setTimezone('UTC'));
        $lines[] = 'Social clicks: '.$social['total_clicks'];
        $platforms = [];
        foreach ($social['platform_totals'] as $platform => $clicks) {
            $platforms[] = e(Str::limit($platform, 60)).': '.$clicks;
        }
        if ($platforms !== []) {
            $lines[] = implode(' · ', $platforms);
        }
        $sections['actions'] = $lines;
        $lines = ['', '<b>Top pages · views</b>'];
        $events = $this->reports->getEventsReport($start->setTimezone('UTC'), $end->setTimezone('UTC'), 'page_view')['rows'];
        foreach ($events->groupBy('page')->map(fn ($rows): int => (int) $rows->sum('event_count'))->sortDesc()->take(5) as $page => $views) {
            $lines[] = $this->pageLink((string) $page).' — '.$views;
        }
        if ($events->isEmpty()) {
            $lines[] = 'No page views.';
        }
        $sections['pages'] = $lines;
        $lines = ['', '<b>Sources · visitors</b>'];
        foreach ($this->analytics->getAcquisitionPerformance($start->setTimezone('UTC'), $end->setTimezone('UTC'))->take(5) as $source) {
            $name = strtolower((string) $source->source) === 'direct' ? 'Direct traffic' : (string) $source->source;
            $details = collect([$source->medium, $source->campaign])->filter(fn ($value): bool => $value !== null && ! in_array(strtolower(trim((string) $value)), ['', 'none', '(none)', 'null', '(not set)'], true));
            $lines[] = e(Str::limit($name.($details->isNotEmpty() ? ' · '.$details->implode(' · ') : ''), 160)).' — '.$source->visitors;
        }
        $sections['sources'] = $lines;
        $lines = ['', '<b>Top countries · visitors</b>'];
        $display = app(TimezoneDisplayService::class);
        $countries = $this->analytics->reportingCountries($start, $end)->filter(function ($country) use ($display): bool {
            $code = strtoupper((string) $country->country_code);

            return preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['ZZ', 'XX'], true) && $display->resolveCountryName($code) !== $code;
        })->sortByDesc('unique_visitors')->take(5);
        foreach ($countries as $country) {
            $code = strtoupper($country->country_code);
            $lines[] = e(app(TelegramReportFormatter::class)->country($code)).' — '.$country->unique_visitors;
        }
        if ($countries->isEmpty()) {
            $lines[] = 'No country data.';
        }
        $sections['countries'] = $lines;
        $lines = ['', '<b>🎮 Games · recorded activity</b>'];
        $lines[] = 'Opens: '.$metrics->where('metric_name', 'game_opened')->sum('count');
        $lines[] = 'Starts: '.$metrics->where('metric_name', 'game_started')->sum('count');
        $lines[] = 'Completions: '.$metrics->where('metric_name', 'game_completed')->sum('count');
        $sections['games'] = $lines;
        $lines = [];
        if ($days > 1 && in_array($view, ['full', 'changes'], true)) {
            $previousStart = $start->subDays($days);
            $previousEnd = $end->min(CarbonImmutable::now($timezone))->subDays($days);
            $previous = $this->reports->getTrafficReport($previousStart, $previousEnd)['summary'];
            $previousMetrics = $this->analytics->reportingMetrics($previousStart, $previousEnd)->where('dimension_key', '');
            $lines[] = '';
            $lines[] = '<b>Change vs previous period</b>';
            if ($traffic['visitors_is_daily_sum'] === $previous['visitors_is_daily_sum']) {
                $lines[] = app(TelegramReportFormatter::class)->comparison('Visitors', $traffic['visitors'], $previous['visitors']);
            }
            $lines[] = app(TelegramReportFormatter::class)->comparison('Sessions', $traffic['sessions'], $previous['sessions']);
            $lines[] = app(TelegramReportFormatter::class)->comparison('Page views', $traffic['page_views'], $previous['page_views']);
            foreach (['booking_completed' => 'Booking events', 'resource_requested' => 'Resource requests', 'game_started' => 'Game starts'] as $metric => $label) {
                $lines[] = app(TelegramReportFormatter::class)->comparison($label, $metrics->where('metric_name', $metric)->sum('count'), $previousMetrics->where('metric_name', $metric)->sum('count'));
            }
            $lines[] = 'Today is compared through the same local time. Visitor counts use a matching basis.';
        }
        $sections['changes'] = $lines ?: ['', 'Choose a period longer than one day to compare.'];
        $selected = match ($view) {
            'overview' => ['traffic', 'actions', 'countries', 'games'],
            'pages' => ['pages'],
            'changes' => ['changes'],
            default => array_keys($sections),
        };

        return implode("\n", array_merge($heading, ...array_map(fn (string $section): array => $sections[$section], $selected)));
    }

    private function pageLink(string $page): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $basePath = rtrim(parse_url($base, PHP_URL_PATH) ?: '', '/');
        $root = $basePath !== '' ? substr($base, 0, -strlen($basePath)) : $base;
        $url = str_starts_with($page, '/') && ! str_starts_with($page, '//')
            ? ($basePath !== '' && ($page === $basePath || str_starts_with($page, $basePath.'/')) ? $root.$page : $base.$page)
            : $page;
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20]/', $url)) {
            return e(Str::limit($page, 80));
        }
        $path = $parts['path'] ?? '/';
        $local = strtolower($parts['host']) === strtolower((string) parse_url($base, PHP_URL_HOST));
        if ($local && $basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath.'/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        $path = trim($path, '/');
        $label = match ($path) {
            '', '_arabictutor-landing' => 'Home',
            'booking' => 'Booking', 'pricing' => 'Pricing', 'resources' => 'Resources', 'games' => 'Games', 'about' => 'About', 'faq' => 'FAQ', 'blogs' => 'Blogs',
            default => Str::headline(rawurldecode($path)),
        };
        if ($local && $path === '_arabictutor-landing') {
            $url = $base.'/';
        }
        if (! $local) {
            $label = $parts['host'].' / '.$label;
        }

        $url = str_replace(['"', '<', '>'], ['%22', '%3C', '%3E'], $url);

        return '<a href="'.e($url).'">'.e(Str::limit($label, 80)).'</a>';
    }

    public function sessions(bool $tomorrow, bool $personal): string
    {
        $date = CarbonImmutable::now((string) Setting::get('business_timezone', app(TimezoneService::class)->getBusinessTimezone()))->startOfDay();
        if ($tomorrow) {
            $date = $date->addDay();
        }
        $bookings = Booking::where('status', 'confirmed')->where('start_at_utc', '>=', $date->setTimezone('UTC'))->where('start_at_utc', '<', $date->addDay()->setTimezone('UTC'))->when($personal, fn ($query) => $query->with(['student', 'contact', 'sessionType', 'consumedEntry.package.payments', 'consumedEntry.package.refunds', 'consumedEntry.package.entitlements.type', 'consumedEntry.package.ledgerEntries']))->orderBy('start_at_utc')->get();
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
