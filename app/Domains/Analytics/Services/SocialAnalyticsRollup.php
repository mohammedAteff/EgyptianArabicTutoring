<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SocialAnalyticsRollup
{
    public const EVENTS = ['whatsapp_clicked', 'telegram_clicked', 'social_link_clicked', 'outbound_link_clicked'];

    /** @return array<string, string> */
    private function expressions(): array
    {
        return [
            'platform' => "LOWER(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.platform')), ''), CASE event_name WHEN 'whatsapp_clicked' THEN 'whatsapp' WHEN 'telegram_clicked' THEN 'telegram' WHEN 'social_link_clicked' THEN 'social channel' ELSE 'outbound link' END))",
            'placement' => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.placement')), ''), 'unknown')",
            'language' => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.language')), ''), 'unknown')",
            'context' => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.context')), ''), 'unknown')",
            'country' => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.detected_country_code')), ''), 'ZZ')",
            'page' => "COALESCE(NULLIF(page, ''), '/')", 'source' => "COALESCE(utm_source, '')",
            'medium' => "COALESCE(utm_medium, '')", 'campaign' => "COALESCE(utm_campaign, '')",
        ];
    }

    /** @return Builder<AnalyticsEvent> */
    private function query(CarbonInterface $start, CarbonInterface $end, array $filters = []): Builder
    {
        $query = app(ReportService::class)->nonBotEventQuery()->whereBetween('created_at', [$start, $end])->whereIn('event_name', self::EVENTS);
        foreach ($this->expressions() as $dimension => $expression) {
            $filterName = $dimension === 'page' ? 'page_url' : $dimension;
            if (isset($filters[$filterName]) && $filters[$filterName] !== '') {
                $query->whereRaw('BINARY '.$expression.' = BINARY ?', [$filters[$filterName]]);
            }
        }

        return $query;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rawRows(CarbonInterface $start, CarbonInterface $end, array $filters = []): Collection
    {
        $expressions = $this->expressions();
        $date = app(ReportService::class)->getBusinessDateExpression('created_at', $start, $end);
        $query = $this->query($start, $end, $filters)->select('event_name', 'visitor_token')->selectRaw($date.' as report_date');
        foreach ($expressions as $dimension => $expression) {
            $query->selectRaw($expression.' as '.$dimension);
        }
        $columns = array_merge(['event_name', 'report_date'], array_keys($expressions));

        $records = DB::query()->fromSub($query, 'social_events')->select($columns)
            ->selectRaw("COUNT(*) as clicks, COUNT(DISTINCT NULLIF(visitor_token, '')) as unique_visitors")
            ->groupBy($columns)->get();
        /** @var array<int, array<string, mixed>> $results */
        $results = [];
        foreach ($records as $row) {
            /** @var array<string, mixed> $result */
            $result = (array) $row;
            $result['date'] = $result['report_date'];
            unset($result['report_date']);
            $result['clicks'] = (int) $result['clicks'];
            $result['unique_visitors'] = (int) $result['unique_visitors'];

            $results[] = $result;
        }

        return new Collection($results);
    }

    /** Persist one complete business day before either raw-event pruning path. */
    public function aggregateDay(CarbonImmutable $day): void
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $day = $day->setTimezone($timezone)->startOfDay();
        $scope = fn () => DB::table('daily_social_metrics')->where('reporting_timezone', $timezone)->where('metric_date', $day->toDateString());
        $start = $day->utc();
        $end = $day->addDay()->utc()->subMicrosecond();
        $rows = $this->rawRows($start, $end);
        $total = (int) $rows->sum('clicks');
        $previous = $scope()->where('dimension_hash', '')->first();
        if ($previous && $total < (int) $previous->clicks) {
            return;
        }
        DB::transaction(function () use ($scope, $day, $timezone, $rows, $total, $start, $end): void {
            $scope()->delete();
            $base = ['reporting_timezone' => $timezone, 'metric_date' => $day->toDateString(), 'created_at' => now(), 'updated_at' => now()];
            foreach ($rows as $row) {
                $dimensions = array_diff_key($row, array_flip(['date', 'clicks', 'unique_visitors']));
                $json = json_encode($dimensions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                DB::table('daily_social_metrics')->insert(array_merge($base, ['dimension_hash' => hash('sha256', $json), 'dimensions' => $json, 'clicks' => $row['clicks'], 'unique_visitors' => $row['unique_visitors']]));
            }
            DB::table('daily_social_metrics')->insert(array_merge($base, ['dimension_hash' => '', 'dimensions' => null, 'clicks' => $total, 'unique_visitors' => $this->query($start, $end)->where('visitor_token', '!=', '')->distinct()->count('visitor_token')]));
        });
    }

    /** @return array<string, mixed> */
    public function report(CarbonInterface $start, CarbonInterface $end, array $filters = []): array
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $day = CarbonImmutable::instance($start)->setTimezone($timezone)->startOfDay();
        $last = CarbonImmutable::instance($end)->setTimezone($timezone)->startOfDay();
        $rawByDay = $this->rawRows($start, $end, $filters)->groupBy('date');
        $storedByDay = DB::table('daily_social_metrics')->where('reporting_timezone', $timezone)
            ->whereBetween('metric_date', [$day->toDateString(), $last->toDateString()])->get()->groupBy('metric_date');
        $rawTotalsByDay = collect();
        if ($storedByDay->isNotEmpty()) {
            $dateExpression = app(ReportService::class)->getBusinessDateExpression('created_at', $start, $end);
            $dates = $this->query($day->utc(), $last->addDay()->utc()->subMicrosecond())->selectRaw($dateExpression.' as report_date');
            $rawTotalsByDay = DB::query()->fromSub($dates, 'social_dates')->select('report_date')->selectRaw('COUNT(*) as event_count')->groupBy('report_date')->pluck('event_count', 'report_date');
        }
        $rows = collect();
        $usesDurableHistory = false;
        foreach ($rawByDay as $date => $rawRows) {
            $rows = $rows->concat($rawRows);
        }
        foreach ($storedByDay as $date => $storedRows) {
            $marker = $storedRows->firstWhere('dimension_hash', '');
            if (! $marker) {
                continue;
            }
            $rawCount = (int) ($rawTotalsByDay[$date] ?? 0);
            if ($rawCount >= (int) $marker->clicks) {
                continue;
            }
            $usesDurableHistory = true;
            $rows = $rows->filter(fn (array $row): bool => $row['date'] !== $date)->values();
            foreach ($storedRows->where('dimension_hash', '!=', '') as $stored) {
                $row = json_decode($stored->dimensions, true, 32, JSON_THROW_ON_ERROR);
                $matches = true;
                foreach ($this->expressions() as $dimension => $unused) {
                    $filterName = $dimension === 'page' ? 'page_url' : $dimension;
                    if (isset($filters[$filterName]) && $filters[$filterName] !== '' && $row[$dimension] !== $filters[$filterName]) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $rows->push(array_merge($row, ['date' => $date, 'clicks' => (int) $stored->clicks, 'unique_visitors' => (int) $stored->unique_visitors]));
                }
            }
        }
        $rows = $rows->sortBy([['date', 'desc'], ['platform', 'asc'], ['placement', 'asc']])->values()->map(function (array $row): array {
            $row['platform'] = match ($row['platform']) {
                'whatsapp' => 'WhatsApp', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', default => ucwords($row['platform']),
            };

            return $row;
        });
        $social = $rows->where('event_name', '!=', 'outbound_link_clicked');

        return [
            'rows' => $rows, 'total_clicks' => (int) $social->sum('clicks'),
            'outbound_clicks' => (int) $rows->where('event_name', 'outbound_link_clicked')->sum('clicks'),
            'unique_visitors' => $usesDurableHistory ? null : $this->query($start, $end, $filters)->where('event_name', '!=', 'outbound_link_clicked')->where('visitor_token', '!=', '')->distinct()->count('visitor_token'),
            'uses_durable_history' => $usesDurableHistory,
            'platform_totals' => $rows->groupBy('platform')->map(fn ($group): int => (int) $group->sum('clicks')),
            'whatsapp_clicks' => (int) $rows->where('platform', 'WhatsApp')->sum('clicks'),
            'telegram_clicks' => (int) $rows->where('platform', 'Telegram')->sum('clicks'),
        ];
    }
}
