<?php

namespace App\Console\Commands;

use App\Domains\Analytics\Models\AnalyticsReconciliationAudit;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillDailyCountryMetricsCommand extends Command
{
    protected $signature = 'analytics:backfill-daily-country
                            {--from= : First business calendar date (YYYY-MM-DD)}
                            {--to= : Last business calendar date (YYYY-MM-DD)}';

    protected $description = 'Rebuild auditable daily country rollups for an explicit business date range';

    public function handle(): int
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $fromInput = (string) $this->option('from');
        $toInput = (string) ($this->option('to') ?: $fromInput);

        if ($fromInput === '') {
            $this->error('Provide an explicit --from date; historical country analytics must not be guessed.');

            return self::INVALID;
        }

        try {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $fromInput, $timezone)->startOfDay();
            $to = CarbonImmutable::createFromFormat('Y-m-d', $toInput, $timezone)->startOfDay();
        } catch (\Throwable) {
            $this->error("Dates must use YYYY-MM-DD in {$timezone}.");

            return self::INVALID;
        }

        if ($to->lessThan($from)) {
            $this->error('The --to date must not be earlier than --from.');

            return self::INVALID;
        }

        $days = $from->diffInDays($to) + 1;
        if ($days > 3660) {
            $this->error('Backfill is limited to 10 years per invocation.');

            return self::INVALID;
        }

        $cursor = $from;
        while ($cursor->lessThanOrEqualTo($to)) {
            $date = $cursor->toDateString();
            if ($this->call('analytics:aggregate-daily-country', ['--date' => $date]) !== self::SUCCESS) {
                $this->error("Country aggregation failed for {$date}; later dates were not attempted.");

                return self::FAILURE;
            }
            $cursor = $cursor->addDay();
        }

        AnalyticsReconciliationAudit::create([
            'audit_type' => 'daily_country_backfill',
            'cutover_at' => now('UTC'),
            'preserved_historical_count' => $days,
            'notes' => "Rebuilt daily_country_metrics for {$from->toDateString()} through {$to->toDateString()} in {$timezone}. Unresolved or unavailable historical country context is represented as ZZ.",
        ]);

        $this->info("Country backfill completed for {$days} business calendar day(s).");

        return self::SUCCESS;
    }
}
