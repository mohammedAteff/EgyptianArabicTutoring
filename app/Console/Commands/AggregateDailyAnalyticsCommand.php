<?php

namespace App\Console\Commands;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyMetric;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AggregateDailyAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate-daily {--date= : The target date in YYYY-MM-DD format (defaults to yesterday)} {--prune : Whether to execute raw event and export file retention cleanup}';

    protected $description = 'Pre-aggregate raw analytics events into daily_metrics and enforce retention policies';

    public function handle(): int
    {
        $dateInput = $this->option('date');
        $targetDate = $dateInput
            ? CarbonImmutable::parse($dateInput)->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        $startOfDay = CarbonImmutable::parse($targetDate)->startOfDay();
        $endOfDay = CarbonImmutable::parse($targetDate)->endOfDay();

        $this->info("Aggregating daily metrics for {$targetDate}...");

        $baseQuery = AnalyticsEvent::query()
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->where('is_bot', false);

        // 1. Overall counts by event_name
        $eventCounts = (clone $baseQuery)
            ->select('event_name', DB::raw('count(*) as count'))
            ->groupBy('event_name')
            ->pluck('count', 'event_name');

        foreach ($eventCounts as $eventName => $count) {
            DailyMetric::updateOrCreate(
                [
                    'metric_date' => $targetDate,
                    'metric_name' => $eventName,
                    'dimension_key' => null,
                    'dimension_value' => null,
                ],
                ['count' => $count]
            );
        }

        // 2. Unique Visitors
        $uniqueVisitors = (clone $baseQuery)
            ->whereNotNull('visitor_token')
            ->distinct('visitor_token')
            ->count('visitor_token');

        DailyMetric::updateOrCreate(
            [
                'metric_date' => $targetDate,
                'metric_name' => 'unique_visitors',
                'dimension_key' => null,
                'dimension_value' => null,
            ],
            ['count' => $uniqueVisitors]
        );

        // 3. Unique Sessions
        $uniqueSessions = (clone $baseQuery)
            ->whereNotNull('session_token')
            ->distinct('session_token')
            ->count('session_token');

        DailyMetric::updateOrCreate(
            [
                'metric_date' => $targetDate,
                'metric_name' => 'sessions',
                'dimension_key' => null,
                'dimension_value' => null,
            ],
            ['count' => $uniqueSessions]
        );

        // 4. Page views by Page
        $pageViewsByPage = (clone $baseQuery)
            ->where('event_name', 'page_view')
            ->whereNotNull('page')
            ->select('page', DB::raw('count(*) as count'))
            ->groupBy('page')
            ->orderByDesc('count')
            ->take(50)
            ->get();

        foreach ($pageViewsByPage as $row) {
            DailyMetric::updateOrCreate(
                [
                    'metric_date' => $targetDate,
                    'metric_name' => 'page_views',
                    'dimension_key' => 'page',
                    'dimension_value' => substr($row->page, 0, 128),
                ],
                ['count' => $row->count]
            );
        }

        // 5. Visitors by Source
        $visitorsBySource = (clone $baseQuery)
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(distinct visitor_token) as count'))
            ->groupBy('utm_source')
            ->get();

        foreach ($visitorsBySource as $row) {
            DailyMetric::updateOrCreate(
                [
                    'metric_date' => $targetDate,
                    'metric_name' => 'visitors_by_source',
                    'dimension_key' => 'source',
                    'dimension_value' => substr($row->utm_source, 0, 128),
                ],
                ['count' => $row->count]
            );
        }

        $this->info("Aggregation completed for {$targetDate}.");

        // Retention Pruning
        if ($this->option('prune') || ! $dateInput) {
            $retentionDays = (int) Setting::get('analytics_retention_days', 180);
            $pruneCutoff = CarbonImmutable::now()->subDays($retentionDays);

            $deletedEvents = AnalyticsEvent::where('created_at', '<', $pruneCutoff)->delete();
            $this->info("Pruned {$deletedEvents} raw analytics events older than {$retentionDays} days.");

            $deletedSessions = VisitorSession::where('created_at', '<', $pruneCutoff)->delete();
            $this->info("Pruned {$deletedSessions} visitor sessions older than {$retentionDays} days.");

            $deletedVisitors = Visitor::where('last_seen_at', '<', $pruneCutoff)
                ->whereDoesntHave('contacts')
                ->delete();
            $this->info("Pruned {$deletedVisitors} inactive uncontacted visitors older than {$retentionDays} days.");

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
        }

        return Command::SUCCESS;
    }
}
