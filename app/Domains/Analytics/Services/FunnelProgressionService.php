<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\AnalyticsReconciliationAudit;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorFunnelProgression;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FunnelProgressionService
{
    public const MATURITY_SECONDS = 30 * 86400; // 30 days in seconds

    /**
     * Ensure a visitor has an entry in visitor_funnel_progressions.
     */
    public function recordVisit(Visitor $visitor, ?CarbonInterface $timestamp = null): VisitorFunnelProgression
    {
        $firstSeen = $visitor->first_seen_at ?? ($timestamp ?? now());
        $cohortDate = CarbonImmutable::parse($firstSeen)->setTimezone('Africa/Cairo')->toDateString();

        return VisitorFunnelProgression::firstOrCreate(
            ['visitor_id' => $visitor->id],
            [
                'cohort_date' => $cohortDate,
                'visitor_at' => $firstSeen,
                'booking_cta_provenance' => 'none',
                'booking_started_provenance' => 'none',
                'slot_held_provenance' => 'none',
                'booking_completed_provenance' => 'none',
            ]
        );
    }

    /**
     * Record a stage milestone for a visitor, performing deterministic imputation for upstream milestones.
     */
    public function recordStage(
        Visitor $visitor,
        string $stage,
        ?CarbonInterface $eventTimestamp = null,
        bool $isObserved = true
    ): ?VisitorFunnelProgression {
        return DB::transaction(function () use ($visitor, $stage, $eventTimestamp, $isObserved) {
            $progression = $this->recordVisit($visitor, $eventTimestamp);
            $progression = VisitorFunnelProgression::where('id', $progression->id)->lockForUpdate()->first();

            $ts = $eventTimestamp ? CarbonImmutable::parse($eventTimestamp) : CarbonImmutable::now();
            $firstVisit = CarbonImmutable::parse($progression->visitor_at);
            $cutoff = $firstVisit->addSeconds(self::MATURITY_SECONDS);

            // Routine calls cannot mutate an already matured cohort (Section 6 & Section 9)
            // Once now() >= cutoff, updates can only occur via explicit audited reconciliation or rebuild.
            if (CarbonImmutable::now()->gte($cutoff)) {
                return $progression;
            }

            // Event must fall strictly within the half-open window: [firstVisit, firstVisit + 30 days)
            $isInsideWindow = $ts->gte($firstVisit) && $ts->lt($cutoff);
            if (! $isInsideWindow) {
                return $progression;
            }

            switch ($stage) {
                case 'booking_cta':
                    if ($progression->booking_cta_provenance !== 'observed') {
                        $progression->booking_cta_observed_at = $isObserved ? $ts : null;
                        $progression->booking_cta_qualified_at = $progression->booking_cta_qualified_at ?? $ts;
                        $progression->booking_cta_provenance = $isObserved ? 'observed' : 'imputed';
                    }
                    break;

                case 'booking_started':
                    if ($progression->booking_started_provenance !== 'observed') {
                        $progression->booking_started_observed_at = $isObserved ? $ts : null;
                        $progression->booking_started_qualified_at = $progression->booking_started_qualified_at ?? $ts;
                        $progression->booking_started_provenance = $isObserved ? 'observed' : 'imputed';
                    }
                    // Deterministically impute upstream CTA stage if missing
                    if ($progression->booking_cta_provenance === 'none') {
                        $progression->booking_cta_observed_at = null;
                        $progression->booking_cta_qualified_at = $ts;
                        $progression->booking_cta_provenance = 'imputed';
                    }
                    break;

                case 'slot_held':
                    if ($progression->slot_held_provenance !== 'observed') {
                        $progression->slot_held_observed_at = $isObserved ? $ts : null;
                        $progression->slot_held_qualified_at = $progression->slot_held_qualified_at ?? $ts;
                        $progression->slot_held_provenance = $isObserved ? 'observed' : 'imputed';
                    }
                    // Impute upstream booking_started if missing
                    if ($progression->booking_started_provenance === 'none') {
                        $progression->booking_started_observed_at = null;
                        $progression->booking_started_qualified_at = $ts;
                        $progression->booking_started_provenance = 'imputed';
                    }
                    // Impute upstream CTA if missing
                    if ($progression->booking_cta_provenance === 'none') {
                        $progression->booking_cta_observed_at = null;
                        $progression->booking_cta_qualified_at = $ts;
                        $progression->booking_cta_provenance = 'imputed';
                    }
                    break;

                case 'booking_completed':
                    if ($progression->booking_completed_provenance !== 'observed') {
                        $progression->booking_completed_observed_at = $isObserved ? $ts : null;
                        $progression->booking_completed_qualified_at = $progression->booking_completed_qualified_at ?? $ts;
                        $progression->booking_completed_provenance = $isObserved ? 'observed' : 'imputed';
                    }
                    // Impute slot_held if missing
                    if ($progression->slot_held_provenance === 'none') {
                        $progression->slot_held_observed_at = null;
                        $progression->slot_held_qualified_at = $ts;
                        $progression->slot_held_provenance = 'imputed';
                    }
                    // Impute booking_started if missing
                    if ($progression->booking_started_provenance === 'none') {
                        $progression->booking_started_observed_at = null;
                        $progression->booking_started_qualified_at = $ts;
                        $progression->booking_started_provenance = 'imputed';
                    }
                    // Impute CTA if missing
                    if ($progression->booking_cta_provenance === 'none') {
                        $progression->booking_cta_observed_at = null;
                        $progression->booking_cta_qualified_at = $ts;
                        $progression->booking_cta_provenance = 'imputed';
                    }
                    break;
            }

            $progression->save();

            return $progression;
        });
    }

    /**
     * Reconcile late-arriving event for an upstream imputed stage or qualify an in-window late event.
     */
    public function reconcileLateEvent(
        Visitor $visitor,
        string $stage,
        CarbonInterface $authoritativeTimestamp
    ): bool {
        return DB::transaction(function () use ($visitor, $stage, $authoritativeTimestamp) {
            $progression = VisitorFunnelProgression::where('visitor_id', $visitor->id)->lockForUpdate()->first();
            if (! $progression) {
                return false;
            }

            $eventTs = CarbonImmutable::parse($authoritativeTimestamp);
            $firstVisit = CarbonImmutable::parse($progression->visitor_at);
            $cutoff = $firstVisit->addSeconds(self::MATURITY_SECONDS);

            // Section 6 & 9: Event must fall strictly within half-open interval [t_first_seen, t_first_seen + 30 days)
            if ($eventTs->lt($firstVisit) || $eventTs->gte($cutoff)) {
                return false;
            }

            $observedColumn = "{$stage}_observed_at";
            $qualifiedColumn = "{$stage}_qualified_at";
            $provenanceColumn = "{$stage}_provenance";

            if ($progression->{$provenanceColumn} === 'imputed') {
                $progression->{$observedColumn} = $eventTs;
                $progression->{$provenanceColumn} = 'observed';
                $progression->reconciled_at = now();
                $progression->save();

                Log::info("FunnelProgressionService: reconciled late event for visitor {$visitor->id}, stage {$stage}");

                return true;
            }

            if ($progression->{$provenanceColumn} === 'none') {
                $progression->{$observedColumn} = $eventTs;
                $progression->{$qualifiedColumn} = $eventTs;
                $progression->{$provenanceColumn} = 'observed';

                // Impute missing upstream stages
                if ($stage === 'booking_completed' && $progression->slot_held_provenance === 'none') {
                    $progression->slot_held_qualified_at = $eventTs;
                    $progression->slot_held_provenance = 'imputed';
                }
                if (in_array($stage, ['booking_completed', 'slot_held'], true) && $progression->booking_started_provenance === 'none') {
                    $progression->booking_started_qualified_at = $eventTs;
                    $progression->booking_started_provenance = 'imputed';
                }
                if (in_array($stage, ['booking_completed', 'slot_held', 'booking_started'], true) && $progression->booking_cta_provenance === 'none') {
                    $progression->booking_cta_qualified_at = $eventTs;
                    $progression->booking_cta_provenance = 'imputed';
                }

                $progression->reconciled_at = now();
                $progression->save();

                Log::info("FunnelProgressionService: reconciled missing late event for visitor {$visitor->id}, stage {$stage}");

                return true;
            }

            return false;
        });
    }

    /**
     * Deterministically rebuild a visitor's funnel progression from retained raw events
     * and authoritative server records (BookingHold and Booking).
     */
    public function rebuildVisitorFunnel(Visitor $visitor, ?CarbonInterface $reconciledAt = null): VisitorFunnelProgression
    {
        return DB::transaction(function () use ($visitor, $reconciledAt) {
            $progression = $this->recordVisit($visitor);
            $progression = VisitorFunnelProgression::where('id', $progression->id)->lockForUpdate()->first();

            $firstVisit = CarbonImmutable::parse($progression->visitor_at);
            $cutoff = $firstVisit->addSeconds(self::MATURITY_SECONDS);

            $retentionDays = (int) Setting::get('analytics_retention_days', 180);
            $pruneCutoff = CarbonImmutable::now()->subDays($retentionDays);
            $eventsPrunedForWindow = $firstVisit->lt($pruneCutoff);

            // Preserve existing observed stages whenever their source interval is no longer
            // fully reconstructable from raw events (EDITS V1 §3–6, §9, §14).
            $savedObserved = [];
            if ($eventsPrunedForWindow) {
                foreach (['booking_cta', 'booking_started', 'slot_held', 'booking_completed'] as $stg) {
                    if ($progression->{$stg.'_provenance'} === 'observed') {
                        $savedObserved[$stg] = [
                            'observed_at' => $progression->{$stg.'_observed_at'},
                            'qualified_at' => $progression->{$stg.'_qualified_at'},
                        ];
                    }
                }
            }

            // 1. Reset all milestones to clean baseline (or saved observed if partially/fully pruned)
            $progression->booking_cta_observed_at = $savedObserved['booking_cta']['observed_at'] ?? null;
            $progression->booking_cta_qualified_at = $savedObserved['booking_cta']['qualified_at'] ?? null;
            $progression->booking_cta_provenance = isset($savedObserved['booking_cta']) ? 'observed' : 'none';

            $progression->booking_started_observed_at = $savedObserved['booking_started']['observed_at'] ?? null;
            $progression->booking_started_qualified_at = $savedObserved['booking_started']['qualified_at'] ?? null;
            $progression->booking_started_provenance = isset($savedObserved['booking_started']) ? 'observed' : 'none';

            $progression->slot_held_observed_at = $savedObserved['slot_held']['observed_at'] ?? null;
            $progression->slot_held_qualified_at = $savedObserved['slot_held']['qualified_at'] ?? null;
            $progression->slot_held_provenance = isset($savedObserved['slot_held']) ? 'observed' : 'none';

            $progression->booking_completed_observed_at = $savedObserved['booking_completed']['observed_at'] ?? null;
            $progression->booking_completed_qualified_at = $savedObserved['booking_completed']['qualified_at'] ?? null;
            $progression->booking_completed_provenance = isset($savedObserved['booking_completed']) ? 'observed' : 'none';

            // 2. Replay retained raw events within [firstVisit, cutoff)
            $events = AnalyticsEvent::where('visitor_token', $visitor->visitor_token)
                ->where('is_bot', false)
                ->where('created_at', '>=', $firstVisit)
                ->where('created_at', '<', $cutoff)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($events as $ev) {
                $evTs = CarbonImmutable::parse($ev->created_at);
                match ($ev->event_name) {
                    'booking_cta_clicked' => $this->applyStageRebuild($progression, 'booking_cta', $evTs, true),
                    'booking_started' => $this->applyStageRebuild($progression, 'booking_started', $evTs, true),
                    'booking_slot_held' => $this->applyStageRebuild($progression, 'slot_held', $evTs, true),
                    'booking_completed' => $this->applyStageRebuild($progression, 'booking_completed', $evTs, true),
                    default => null,
                };
            }

            // 3. Check authoritative server records: BookingHold
            $holds = BookingHold::where('visitor_token', $visitor->visitor_token)
                ->where('created_at', '>=', $firstVisit)
                ->where('created_at', '<', $cutoff)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($holds as $hold) {
                $holdTs = CarbonImmutable::parse($hold->created_at);
                $isObserved = $progression->slot_held_provenance === 'observed';
                $this->applyStageRebuild($progression, 'slot_held', $holdTs, $isObserved);
            }

            // 4. Check authoritative server records: Booking
            // Match bookings strictly by persisted visitor_token (never by slot coincidence).
            // Include all bookings created by this visitor, even if subsequently cancelled,
            // because booking creation represents a completed conversion stage (EDITS V1 §3-6, §11, §13-14).
            $bookings = Booking::where('visitor_token', $visitor->visitor_token)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($bookings as $booking) {
                $bookingCreated = CarbonImmutable::parse($booking->created_at);
                // Authoritative occurrence timestamp must fall strictly within [firstVisit, cutoff)
                if ($bookingCreated->gte($firstVisit) && $bookingCreated->lt($cutoff)) {
                    $isBookObserved = $progression->booking_completed_provenance === 'observed';
                    $this->applyStageRebuild($progression, 'booking_completed', $bookingCreated, $isBookObserved);
                }
            }

            $progression->reconciled_at = $reconciledAt ?? now();
            $progression->save();

            return $progression;
        });
    }

    /**
     * Record a durable reconciliation audit entry.
     */
    public function recordReconciliationAudit(array $data): AnalyticsReconciliationAudit
    {
        return AnalyticsReconciliationAudit::create([
            'audit_type' => $data['audit_type'] ?? 'rebuild',
            'cutover_at' => $data['cutover_at'] ?? now(),
            'rebuilt_visitors_count' => $data['rebuilt_visitors_count'] ?? 0,
            'preserved_historical_count' => $data['preserved_historical_count'] ?? 0,
            'authoritative_bookings_count' => $data['authoritative_bookings_count'] ?? 0,
            'non_comparable_before' => $data['non_comparable_before'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    protected function applyStageRebuild(VisitorFunnelProgression $prog, string $stage, CarbonImmutable $ts, bool $isObserved): void
    {
        switch ($stage) {
            case 'booking_cta':
                if ($prog->booking_cta_provenance !== 'observed') {
                    $prog->booking_cta_observed_at = $isObserved ? $ts : null;
                    $prog->booking_cta_qualified_at = $prog->booking_cta_qualified_at ?? $ts;
                    $prog->booking_cta_provenance = $isObserved ? 'observed' : 'imputed';
                }
                break;

            case 'booking_started':
                if ($prog->booking_started_provenance !== 'observed') {
                    $prog->booking_started_observed_at = $isObserved ? $ts : null;
                    $prog->booking_started_qualified_at = $prog->booking_started_qualified_at ?? $ts;
                    $prog->booking_started_provenance = $isObserved ? 'observed' : 'imputed';
                }
                if ($prog->booking_cta_provenance === 'none') {
                    $prog->booking_cta_qualified_at = $prog->booking_cta_qualified_at ?? $ts;
                    $prog->booking_cta_provenance = 'imputed';
                }
                break;

            case 'slot_held':
                if ($prog->slot_held_provenance !== 'observed') {
                    $prog->slot_held_observed_at = $isObserved ? $ts : null;
                    $prog->slot_held_qualified_at = $prog->slot_held_qualified_at ?? $ts;
                    $prog->slot_held_provenance = $isObserved ? 'observed' : 'imputed';
                }
                if ($prog->booking_started_provenance === 'none') {
                    $prog->booking_started_qualified_at = $prog->booking_started_qualified_at ?? $ts;
                    $prog->booking_started_provenance = 'imputed';
                }
                if ($prog->booking_cta_provenance === 'none') {
                    $prog->booking_cta_qualified_at = $prog->booking_cta_qualified_at ?? $ts;
                    $prog->booking_cta_provenance = 'imputed';
                }
                break;

            case 'booking_completed':
                if ($prog->booking_completed_provenance !== 'observed') {
                    $prog->booking_completed_observed_at = $isObserved ? $ts : null;
                    $prog->booking_completed_qualified_at = $prog->booking_completed_qualified_at ?? $ts;
                    $prog->booking_completed_provenance = $isObserved ? 'observed' : 'imputed';
                }
                if ($prog->slot_held_provenance === 'none') {
                    $prog->slot_held_qualified_at = $prog->slot_held_qualified_at ?? $ts;
                    $prog->slot_held_provenance = 'imputed';
                }
                if ($prog->booking_started_provenance === 'none') {
                    $prog->booking_started_qualified_at = $prog->booking_started_qualified_at ?? $ts;
                    $prog->booking_started_provenance = 'imputed';
                }
                if ($prog->booking_cta_provenance === 'none') {
                    $prog->booking_cta_qualified_at = $prog->booking_cta_qualified_at ?? $ts;
                    $prog->booking_cta_provenance = 'imputed';
                }
                break;
        }
    }

    /**
     * Compute cumulative cohort funnel for interval [T_start, T_end) in Africa/Cairo.
     */
    public function getCohortFunnel(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $tz = 'Africa/Cairo';

        // Canonical reporting interval: half-open [T_start, T_end) in Africa/Cairo
        // Explicitly convert Carbon instances to Africa/Cairo before day rounding
        $startCairo = ($startDate instanceof CarbonInterface)
            ? $startDate->copy()->setTimezone($tz)
            : CarbonImmutable::parse($startDate, $tz);

        $endCairo = ($endDate instanceof CarbonInterface)
            ? $endDate->copy()->setTimezone($tz)
            : CarbonImmutable::parse($endDate, $tz);

        $tStartCairo = $startCairo->startOfDay();
        $tEndCairo = $endCairo->startOfDay()->addDay();

        // Convert boundary timestamps to UTC for database comparison
        $tStartUtc = $tStartCairo->setTimezone('UTC');
        $tEndUtc = $tEndCairo->setTimezone('UTC');

        // Cohort qualification: visitor belongs to cohort if and only if t_first_seen >= T_start AND t_first_seen < T_end
        $cohortQuery = VisitorFunnelProgression::query()
            ->where('visitor_at', '>=', $tStartUtc)
            ->where('visitor_at', '<', $tEndUtc);

        $totalVisitors = (clone $cohortQuery)->count();

        // Cumulative counts based on qualification (upper boundary < t_first_seen + 30 days is enforced on write)
        $ctaQualified = (clone $cohortQuery)->whereIn('booking_cta_provenance', ['observed', 'imputed'])->count();
        $ctaObserved = (clone $cohortQuery)->where('booking_cta_provenance', 'observed')->count();
        $ctaImputed = (clone $cohortQuery)->where('booking_cta_provenance', 'imputed')->count();

        $startedQualified = (clone $cohortQuery)->whereIn('booking_started_provenance', ['observed', 'imputed'])->count();
        $heldQualified = (clone $cohortQuery)->whereIn('slot_held_provenance', ['observed', 'imputed'])->count();
        $completedQualified = (clone $cohortQuery)->whereIn('booking_completed_provenance', ['observed', 'imputed'])->count();

        // Enforce mathematical cumulative invariants: Completed <= Held <= Started <= CTA <= Visitors
        $heldQualified = min($heldQualified, $startedQualified);
        $completedQualified = min($completedQualified, $heldQualified);

        // Maturity calculation (Section 6)
        $now = CarbonImmutable::now();
        $allMatured = true;
        $latestReconciledAt = null;

        if ($totalVisitors > 0) {
            $immatureCount = (clone $cohortQuery)
                ->whereRaw('TIMESTAMPDIFF(SECOND, visitor_at, ?) < ?', [$now->toDateTimeString(), self::MATURITY_SECONDS])
                ->count();

            $allMatured = ($immatureCount === 0);
            $latestReconciledAt = (clone $cohortQuery)->max('reconciled_at');
        }

        // Terminal Abandonment mutually exclusive buckets (Section 8)
        $abandonment = $this->calculateTerminalAbandonment($cohortQuery, $totalVisitors);

        $ctaRate = $totalVisitors > 0 ? round(($ctaQualified / $totalVisitors) * 100, 1) : 0;
        $startRate = $ctaQualified > 0 ? round(($startedQualified / $ctaQualified) * 100, 1) : 0;
        $holdRate = $startedQualified > 0 ? round(($heldQualified / $startedQualified) * 100, 1) : 0;
        $completeRate = $heldQualified > 0 ? round(($completedQualified / $heldQualified) * 100, 1) : 0;
        $overallConversion = $totalVisitors > 0 ? round(($completedQualified / $totalVisitors) * 100, 2) : 0;

        $maturityLabel = $allMatured
            ? ($latestReconciledAt ? "Mature cohort — last reconciled: {$latestReconciledAt}" : 'Mature cohort')
            : 'Immature / In-Progress';

        return [
            // Flattened keys for direct view binding
            'visitors' => $totalVisitors,
            'cta_clicked' => $ctaQualified, // Backwards compatible alias
            'cta_qualified' => $ctaQualified, // Labeled: "Booking CTA Reached / Qualified"
            'cta_observed' => $ctaObserved,
            'cta_imputed' => $ctaImputed,
            'booking_started' => $startedQualified,
            'slot_held' => $heldQualified,
            'booking_completed' => $completedQualified,
            'cta_rate' => $ctaRate,
            'start_rate' => $startRate,
            'hold_rate' => $holdRate,
            'complete_rate' => $completeRate,
            'overall_conversion' => $overallConversion,
            'is_mature' => $allMatured,
            'maturity_label' => $maturityLabel,
            'abandonment' => $abandonment,
            'interval' => [
                'start' => $tStartCairo->toDateTimeString(),
                'end' => $tEndCairo->toDateTimeString(),
                'start_utc' => $tStartUtc->toDateTimeString(),
                'end_utc' => $tEndUtc->toDateTimeString(),
                'timezone' => $tz,
            ],
        ];
    }

    /**
     * Compute terminal abandonment mutually exclusive buckets (Section 8).
     */
    protected function calculateTerminalAbandonment($cohortQuery, int $totalVisitors): array
    {
        if ($totalVisitors === 0) {
            return [
                'pre_cta' => 0,
                'cta_abandoned' => 0,
                'started_abandoned' => 0,
                'held_abandoned' => 0,
                'completed' => 0,
                'total' => 0,
            ];
        }

        $completed = (clone $cohortQuery)->whereIn('booking_completed_provenance', ['observed', 'imputed'])->count();

        $heldAbandoned = (clone $cohortQuery)
            ->whereIn('slot_held_provenance', ['observed', 'imputed'])
            ->where('booking_completed_provenance', 'none')
            ->count();

        $startedAbandoned = (clone $cohortQuery)
            ->whereIn('booking_started_provenance', ['observed', 'imputed'])
            ->where('slot_held_provenance', 'none')
            ->where('booking_completed_provenance', 'none')
            ->count();

        $ctaAbandoned = (clone $cohortQuery)
            ->whereIn('booking_cta_provenance', ['observed', 'imputed'])
            ->where('booking_started_provenance', 'none')
            ->where('slot_held_provenance', 'none')
            ->where('booking_completed_provenance', 'none')
            ->count();

        $preCta = $totalVisitors - ($completed + $heldAbandoned + $startedAbandoned + $ctaAbandoned);

        return [
            'pre_cta' => max(0, $preCta),
            'cta_abandoned' => $ctaAbandoned,
            'started_abandoned' => $startedAbandoned,
            'held_abandoned' => $heldAbandoned,
            'completed' => $completed,
            'total' => $totalVisitors,
        ];
    }
}
