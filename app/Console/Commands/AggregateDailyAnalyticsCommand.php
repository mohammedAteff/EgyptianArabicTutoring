<?php

namespace App\Console\Commands;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\FunnelProgressionService;
use App\Domains\Analytics\Services\SocialAnalyticsRollup;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AggregateDailyAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate-daily
                            {--date= : The target date in YYYY-MM-DD format (defaults to yesterday in the business timezone)}
                            {--prune : Whether to execute raw event and export file retention cleanup}
                            {--rebuild-funnel : Rebuild visitor_funnel_progressions from retained events}';

    protected $description = 'Pre-aggregate raw analytics events into daily_metrics in the configured business timezone and manage retention';

    public function handle(): int
    {
        $dateInput = $this->option('date');
        $cairoTz = app(TimezoneService::class)->getBusinessTimezone();

        $targetDate = $dateInput
            ? CarbonImmutable::parse($dateInput, $cairoTz)->toDateString()
            : CarbonImmutable::now($cairoTz)->subDay()->toDateString();

        // Exact Cairo day half-open interval [cairoStart, cairoNext) converted to UTC for database querying
        $cairoStart = CarbonImmutable::parse($targetDate, $cairoTz)->startOfDay();
        $cairoNext = $cairoStart->addDay();
        $startUtc = $cairoStart->setTimezone('UTC');
        $endUtc = $cairoNext->setTimezone('UTC');

        $this->info("Aggregating daily metrics for {$targetDate} ({$cairoTz})...");

        $baseQuery = AnalyticsEvent::query()
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->where('is_bot', false)
            // Do not trust only the event flag: a malformed/replayed event
            // linked to a bot visitor or bot session is still bot activity.
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('visitors')
                    ->whereColumn('visitors.visitor_token', 'analytics_events.visitor_token')
                    ->where('visitors.is_bot', true);
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('visitor_sessions as bot_sessions')
                    ->leftJoin('visitors as bot_visitors', 'bot_visitors.id', '=', 'bot_sessions.visitor_id')
                    ->where(function ($nested): void {
                        $nested->whereColumn('bot_sessions.session_token', 'analytics_events.session_token')
                            ->orWhereColumn('bot_sessions.session_id', 'analytics_events.session_token');
                    })
                    ->where(function ($nested): void {
                        $nested->where('bot_sessions.is_bot', true)
                            ->orWhere('bot_visitors.is_bot', true);
                    });
            });

        // 1. Overall counts by event_name
        $eventCounts = (clone $baseQuery)
            ->select('event_name', DB::raw('count(*) as count'))
            ->groupBy('event_name')
            ->pluck('count', 'event_name');

        $sessionTokens = app(ReportService::class)->nonBotSessionQuery()->with('visitor')->where('started_at', '>=', $startUtc)->where('started_at', '<', $endUtc)->get()->map(fn ($session) => $session->visitor?->visitor_token);
        $uniqueVisitors = (clone $baseQuery)->pluck('visitor_token')->merge($sessionTokens)->filter()->unique()->count();

        // 3. Unique Sessions strictly attributed by session started_at in Cairo day (EDITS V1 §15)
        $uniqueSessions = VisitorSession::query()
            ->where('is_bot', false)
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            })
            ->where('started_at', '>=', $startUtc)
            ->where('started_at', '<', $endUtc)
            ->count();

        // 4. Bookings Created on calendar date D in Cairo regardless of later status (EDITS V1 §13)
        $bookingsCreated = DB::table('bookings')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('visitors')
                    ->whereColumn('visitors.visitor_token', 'bookings.visitor_token')
                    ->where('visitors.is_bot', true);
            })
            ->count();

        // 5. Social Link Clicks
        $socialClicks = (clone $baseQuery)
            ->whereIn('event_name', ['social_link_clicked', 'whatsapp_clicked', 'telegram_clicked'])
            ->count();

        // 6. Resource Downloads
        $downloads = (clone $baseQuery)
            ->where('event_name', 'resource_downloaded')
            ->count();

        // 6b. Bounced sessions within Cairo day (V4 Phase 1 §3)
        $bouncedSessionsCount = VisitorSession::query()
            ->where('is_bot', false)
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            })
            ->where('started_at', '>=', $startUtc)
            ->where('started_at', '<', $endUtc)
            ->whereRaw('TIMESTAMPDIFF(SECOND, started_at, last_activity_at) < 10')
            ->whereRaw('(SELECT COUNT(*) FROM analytics_events WHERE (analytics_events.session_token = visitor_sessions.session_token OR analytics_events.session_token = visitor_sessions.session_id) AND analytics_events.event_name = "page_view" AND analytics_events.is_bot = 0) = 1')
            ->whereRaw('(SELECT COUNT(*) FROM analytics_events WHERE (analytics_events.session_token = visitor_sessions.session_token OR analytics_events.session_token = visitor_sessions.session_id) AND analytics_events.event_name IN ('.implode(',', array_fill(0, count(AnalyticsService::CONVERSION_EVENTS), '?')).') AND analytics_events.is_bot = 0) = 0', AnalyticsService::CONVERSION_EVENTS)
            ->count();

        // 7. Page views by Page
        $pageViewsByPage = (clone $baseQuery)
            ->where('event_name', 'page_view')
            ->whereNotNull('page')
            ->select('page', DB::raw('count(*) as count'))
            ->groupBy('page')
            ->orderByDesc('count')
            ->take(50)
            ->get();

        // 8. Visitors by Source
        $visitorsBySource = (clone $baseQuery)
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(distinct visitor_token) as count'))
            ->groupBy('utm_source')
            ->get();

        // 9. Sessions by Source
        $sessionsBySource = VisitorSession::query()
            ->where('is_bot', false)
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            })
            ->where('started_at', '>=', $startUtc)
            ->where('started_at', '<', $endUtc)
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(*) as count'))
            ->groupBy('utm_source')
            ->get();

        // 10. Page Views by Source
        $pageViewsBySource = (clone $baseQuery)
            ->where('event_name', 'page_view')
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(*) as count'))
            ->groupBy('utm_source')
            ->get();

        // 11. Section Dwell Seconds and Views by Section ID (V4 Phase 1 §4 & Phase 2 §2)
        $sectionDwells = (clone $baseQuery)
            ->where('event_name', 'section_dwell')
            ->get(['metadata']);
        $dwellBySection = [];
        foreach ($sectionDwells as $evt) {
            $secId = is_array($evt->metadata) ? ($evt->metadata['section_id'] ?? null) : null;
            $dwell = is_array($evt->metadata) ? (int) ($evt->metadata['dwell_seconds'] ?? 0) : 0;
            if ($secId && $dwell > 0) {
                $dwellBySection[$secId] = ($dwellBySection[$secId] ?? 0) + $dwell;
            }
        }

        $sectionViews = (clone $baseQuery)
            ->where('event_name', 'section_view')
            ->get(['metadata']);
        $viewsBySection = [];
        foreach ($sectionViews as $evt) {
            $secId = is_array($evt->metadata) ? ($evt->metadata['section_id'] ?? null) : null;
            if ($secId) {
                $viewsBySection[$secId] = ($viewsBySection[$secId] ?? 0) + 1;
            }
        }

        // Atomic rebuild: purge existing date metrics first to eliminate stale dimension rows
        DB::transaction(function () use (
            $targetDate,
            $eventCounts,
            $uniqueVisitors,
            $uniqueSessions,
            $bookingsCreated,
            $socialClicks,
            $downloads,
            $pageViewsByPage,
            $visitorsBySource,
            $sessionsBySource,
            $pageViewsBySource,
            $bouncedSessionsCount,
            $dwellBySection,
            $viewsBySection
        ) {
            DailyMetric::where('reporting_timezone', app(TimezoneService::class)->getBusinessTimezone())->where('metric_date', $targetDate)->delete();

            foreach ($eventCounts as $eventName => $count) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => $eventName,
                    'dimension_key' => '',
                    'dimension_value' => '',
                    'count' => $count,
                ]);
            }

            DailyMetric::create([
                'metric_date' => $targetDate,
                'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'metric_name' => 'unique_visitors',
                'dimension_key' => '',
                'dimension_value' => '',
                'count' => $uniqueVisitors,
            ]);

            DailyMetric::create([
                'metric_date' => $targetDate,
                'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'metric_name' => 'sessions',
                'dimension_key' => '',
                'dimension_value' => '',
                'count' => $uniqueSessions,
            ]);

            DailyMetric::create([
                'metric_date' => $targetDate,
                'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'metric_name' => 'bounced_sessions',
                'dimension_key' => '',
                'dimension_value' => '',
                'count' => $bouncedSessionsCount,
            ]);

            DailyMetric::create([
                'metric_date' => $targetDate,
                'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'metric_name' => 'bookings_created',
                'dimension_key' => '',
                'dimension_value' => '',
                'count' => $bookingsCreated,
            ]);

            DailyMetric::create([
                'metric_date' => $targetDate,
                'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'metric_name' => 'social_clicks',
                'dimension_key' => '',
                'dimension_value' => '',
                'count' => $socialClicks,
            ]);

            DailyMetric::create([
                'metric_date' => $targetDate,
                'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                'metric_name' => 'resource_downloads',
                'dimension_key' => '',
                'dimension_value' => '',
                'count' => $downloads,
            ]);

            foreach ($pageViewsByPage as $row) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => 'page_views',
                    'dimension_key' => 'page',
                    'dimension_value' => substr($row->page, 0, 128),
                    'count' => $row->count,
                ]);
            }

            foreach ($visitorsBySource as $row) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => 'visitors_by_source',
                    'dimension_key' => 'source',
                    'dimension_value' => substr($row->utm_source, 0, 128),
                    'count' => $row->count,
                ]);
            }

            foreach ($sessionsBySource as $row) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => 'sessions_by_source',
                    'dimension_key' => 'source',
                    'dimension_value' => substr($row->utm_source, 0, 128),
                    'count' => $row->count,
                ]);
            }

            foreach ($pageViewsBySource as $row) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => 'page_views_by_source',
                    'dimension_key' => 'source',
                    'dimension_value' => substr($row->utm_source, 0, 128),
                    'count' => $row->count,
                ]);
            }

            foreach ($dwellBySection as $secId => $dwellSecs) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => 'section_dwell_seconds',
                    'dimension_key' => 'section',
                    'dimension_value' => substr($secId, 0, 128),
                    'count' => $dwellSecs,
                ]);
            }

            foreach ($viewsBySection as $secId => $viewsCount) {
                DailyMetric::create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'metric_name' => 'section_views',
                    'dimension_key' => 'section',
                    'dimension_value' => substr($secId, 0, 128),
                    'count' => $viewsCount,
                ]);
            }
        });

        $this->info("Aggregation completed for {$targetDate}.");

        // Aggregate daily country metrics (Section 4). A failed country
        // aggregation must fail the parent job as well; otherwise the
        // scheduler would record a successful analytics run while the
        // required audience rollup was missing.
        if ($this->call('analytics:aggregate-daily-country', ['--date' => $targetDate]) !== self::SUCCESS) {
            $this->error("Country aggregation failed for {$targetDate}.");

            return self::FAILURE;
        }

        app(SocialAnalyticsRollup::class)->aggregateDay(CarbonImmutable::parse($targetDate, app(TimezoneService::class)->getBusinessTimezone()));

        // Optional Rebuilding of visitor_funnel_progressions (Section 14)
        if ($this->option('rebuild-funnel')) {
            $this->info('Rebuilding visitor_funnel_progressions...');
            $funnelService = app(FunnelProgressionService::class);
            // Crawlers and link previews are never valid funnel cohort members.
            $visitors = Visitor::query()->where('is_bot', false)->get();
            $rebuiltCount = 0;
            $reconciledAt = now();
            $retentionDays = (int) Setting::get('analytics_retention_days', 180);
            $pruneCutoff = CarbonImmutable::now()->subDays($retentionDays);

            $preservedCount = 0;
            DB::transaction(function () use ($visitors, $funnelService, $reconciledAt, $pruneCutoff, &$rebuiltCount, &$preservedCount) {
                foreach ($visitors as $visitor) {
                    $funnelService->rebuildVisitorFunnel($visitor, $reconciledAt);
                    $rebuiltCount++;
                    if ($visitor->first_seen_at && CarbonImmutable::parse($visitor->first_seen_at)->addDays(30)->lt($pruneCutoff)) {
                        $preservedCount++;
                    }
                }
            });

            $authoritativeBookingsCount = DB::table('bookings')->where('status', '!=', 'cancelled')->count();

            $funnelService->recordReconciliationAudit([
                'audit_type' => 'rebuild',
                'cutover_at' => $reconciledAt,
                'rebuilt_visitors_count' => $rebuiltCount,
                'preserved_historical_count' => $preservedCount,
                'authoritative_bookings_count' => $authoritativeBookingsCount,
                'non_comparable_before' => $pruneCutoff,
                'notes' => "Rebuild executed via aggregate-daily command for {$rebuiltCount} visitors.",
            ]);

            $this->info("Rebuilt funnel progressions for {$rebuiltCount} visitors (reconciled at: {$reconciledAt}).");
        }

        // Retention Pruning (EDITS V1 §9, §13-15: whole Cairo calendar days, verified durable rollups)
        if ($this->option('prune') || ! $dateInput) {
            $retentionDays = (int) Setting::get('analytics_retention_days', 180);
            $pruneCutoffDateCairo = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->subDays($retentionDays)->startOfDay();
            $pruneCutoffUtc = $pruneCutoffDateCairo->setTimezone('UTC');

            $oldestEvent = AnalyticsEvent::where('created_at', '<', $pruneCutoffUtc)->min('created_at');
            $oldestSession = VisitorSession::where('started_at', '<', $pruneCutoffUtc)->orWhere('created_at', '<', $pruneCutoffUtc)->min('started_at')
                ?: VisitorSession::where('created_at', '<', $pruneCutoffUtc)->min('created_at');

            $totalDeletedEvents = 0;
            $totalDeletedSessions = 0;

            if ($oldestEvent || $oldestSession) {
                $oldestTimestamp = min(array_filter([$oldestEvent, $oldestSession]));
                $currDay = CarbonImmutable::parse($oldestTimestamp)->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
                $latestPrunableDay = $pruneCutoffDateCairo->subDay()->startOfDay();

                while ($currDay->lte($latestPrunableDay)) {
                    $dayStr = $currDay->toDateString();
                    $dayStartUtc = $currDay->setTimezone('UTC');
                    $dayEndUtc = $currDay->addDay()->setTimezone('UTC');

                    // Confirm durable rollup exists for this whole Cairo day before deleting raw data
                    $hasRollup = DailyMetric::where('reporting_timezone', app(TimezoneService::class)->getBusinessTimezone())->where('metric_date', $dayStr)
                        ->where('metric_name', 'unique_visitors')
                        ->exists();
                    $hasCountryRollup = DailyCountryMetric::where('reporting_timezone', app(TimezoneService::class)->getBusinessTimezone())->where('metric_date', $dayStr)->exists();

                    if (! $hasRollup || ! $hasCountryRollup) {
                        $this->warn("Skipping retention pruning for business date {$dayStr}: durable daily or country rollup is missing.");
                        $currDay = $currDay->addDay();

                        continue;
                    }

                    app(SocialAnalyticsRollup::class)->aggregateDay($currDay);
                    $deletedEvents = AnalyticsEvent::where('created_at', '>=', $dayStartUtc)
                        ->where('created_at', '<', $dayEndUtc)
                        ->delete();
                    $totalDeletedEvents += $deletedEvents;

                    $deletedSessions = VisitorSession::where(function ($q) use ($dayStartUtc, $dayEndUtc) {
                        $q->where('started_at', '>=', $dayStartUtc)->where('started_at', '<', $dayEndUtc)
                            ->orWhere(function ($sub) use ($dayStartUtc, $dayEndUtc) {
                                $sub->whereNull('started_at')
                                    ->where('created_at', '>=', $dayStartUtc)
                                    ->where('created_at', '<', $dayEndUtc);
                            });
                    })->delete();
                    $totalDeletedSessions += $deletedSessions;

                    $currDay = $currDay->addDay();
                }
            }

            $this->info("Pruned {$totalDeletedEvents} raw analytics events older than {$retentionDays} days across whole Cairo days.");
            $this->info("Pruned {$totalDeletedSessions} visitor sessions older than {$retentionDays} days across whole Cairo days.");

            // Notice: visitors and visitor_funnel_progressions are NOT deleted to preserve multi-year cohort reporting!

            $exportPath = storage_path('app/exports');
            if (File::isDirectory($exportPath)) {
                $deletedExports = 0;
                $files = File::files($exportPath);
                foreach ($files as $file) {
                    if (time() - $file->getMTime() > 86400) {
                        File::delete($file->getPathname());
                        $deletedExports++;
                    }
                }
                $this->info("Cleaned up {$deletedExports} temporary export files.");
            }

            // Bounded Telemetry & Auth Pruning (V4 Phase 1 §5)
            $cutoff90Days = CarbonImmutable::now('UTC')->subDays(90);
            $prunedRawEvents = 0;
            $ninetyDayCutoffCairo = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->subDays(90)->startOfDay();
            $oldestRemainingEvent = AnalyticsEvent::query()
                ->where('created_at', '<', $ninetyDayCutoffCairo->setTimezone('UTC'))
                ->min('created_at');
            if ($oldestRemainingEvent) {
                $pruneDay = CarbonImmutable::parse($oldestRemainingEvent, 'UTC')->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
                while ($pruneDay->lt($ninetyDayCutoffCairo)) {
                    $dayStartUtc = $pruneDay->setTimezone('UTC');
                    $dayEndUtc = $pruneDay->addDay()->setTimezone('UTC');
                    $hasRollup = DailyMetric::query()->where('reporting_timezone', app(TimezoneService::class)->getBusinessTimezone())
                        ->where('metric_date', $pruneDay->toDateString())
                        ->where('metric_name', 'unique_visitors')
                        ->exists();
                    $hasCountryRollup = DailyCountryMetric::query()->where('reporting_timezone', app(TimezoneService::class)->getBusinessTimezone())
                        ->where('metric_date', $pruneDay->toDateString())
                        ->exists();

                    if ($hasRollup && $hasCountryRollup) {
                        app(SocialAnalyticsRollup::class)->aggregateDay($pruneDay);
                        do {
                            $deletedChunk = DB::table('analytics_events')
                                ->where('created_at', '>=', $dayStartUtc)
                                ->where('created_at', '<', $dayEndUtc)
                                ->orderBy('id')
                                ->limit(1000)
                                ->delete();
                            $prunedRawEvents += $deletedChunk;
                        } while ($deletedChunk === 1000);
                    } else {
                        $this->warn("Skipping 90-day event pruning for business date {$pruneDay->toDateString()}: durable daily or country rollup is missing.");
                    }

                    $pruneDay = $pruneDay->addDay();
                }
            }

            $prunedTouches = 0;
            do {
                $deletedChunk = DB::table('marketing_touches')
                    ->where('created_at', '<', $cutoff90Days)
                    ->orderBy('id')
                    ->limit(1000)
                    ->delete();
                $prunedTouches += $deletedChunk;
            } while ($deletedChunk === 1000);

            $cutoffAuthCooldown = CarbonImmutable::now('UTC')->subDays(7);
            $cutoffAuth30Days = CarbonImmutable::now('UTC')->subDays(30);
            $prunedAuthAttempts = 0;
            do {
                $deletedChunk = DB::table('student_auth_attempts')
                    ->where(function ($query) use ($cutoffAuthCooldown, $cutoffAuth30Days): void {
                        $query->where('cooldown_until', '<', $cutoffAuthCooldown)
                            ->orWhere('created_at', '<', $cutoffAuth30Days);
                    })
                    ->limit(1000)
                    ->delete();
                $prunedAuthAttempts += $deletedChunk;
            } while ($deletedChunk === 1000);

            if ($prunedRawEvents > 0) {
                $this->info("Pruned {$prunedRawEvents} historical analytics events older than 90 days.");
            }
            if ($prunedTouches > 0) {
                $this->info("Pruned {$prunedTouches} historical marketing touches older than 90 days.");
            }
            if ($prunedAuthAttempts > 0) {
                $this->info("Pruned {$prunedAuthAttempts} stale student auth attempts.");
            }
        }

        return Command::SUCCESS;
    }
}
