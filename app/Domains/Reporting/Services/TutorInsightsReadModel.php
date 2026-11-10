<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\FeedbackConversation;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TutorInsightsReadModel
{
    /** Single-provider boundary. Completion actors never determine teaching attribution. */
    public const PROVIDER_BASIS = 'Abdallah is the sole teaching provider; qualifying instructional appointments are service-wide teaching records, independent of the completing administrator. Multi-tutor attribution requires explicit future source data.';

    /** Identifier => label, unit, authoritative source/formula. */
    public const DEFINITIONS = [
        'appointments_completed' => ['Completed appointments', 'appointments', 'Distinct retained bookings currently completed; scheduled-start attribution.'],
        'recorded_teaching_seconds' => ['Recorded teaching duration', 'seconds', 'Sum of positive stored UTC end-minus-start intervals on completed bookings; full interval attributed to start.'],
        'identified_students_taught' => ['Identified students taught', 'students', 'Distinct canonical retained student IDs on completed bookings; unlinked appointments excluded.'],
        'teaching_days' => ['Teaching days', 'local dates', 'Distinct reporting-timezone scheduled-start dates with completed appointments.'],
        'linked_completed_appointments' => ['Linked completed appointments', 'appointments', 'Completed bookings with canonical retained student ownership.'],
        'unlinked_completed_appointments' => ['Unlinked completed appointments', 'appointments', 'Completed bookings whose student_id is genuinely null; no inferred identity.'],
        'sessions_per_identified_student' => ['Sessions per identified student', 'appointments per student', 'Linked completed appointments / distinct identified students taught; no denominator means not applicable.'],
        'diagnostic_type_completed' => ['Completed diagnostic-type appointments', 'appointments', 'Completed bookings with canonical diagnostic-session type; purpose is not proven; included in overall completed total.'],
        'cancellation_actions' => ['Recorded cancellation actions', 'policy-decision records', 'BookingPolicyDecision: action=cancellation; action created_at, regardless of scheduled date; mirrored events excluded.'],
        'reschedule_actions' => ['Recorded reschedule actions', 'reschedule records', 'SessionReschedule: distinct record ID; action created_at; mirrored events excluded.'],
        'lms_final_review_records' => ['LMS submissions with a retained final-review date', 'submission records', 'Non-erased assignment submissions currently approved or needs_revision; explicit latest reviewed_at; private ownership consistent.'],
        'feedback_conversations_received' => ['Incoming feedback conversations', 'conversations', 'Currently available canonical conversations; creation date; no subjects or bodies.'],
        'feedback_administrator_messages' => ['Administrator feedback messages', 'message events', 'Canonical message events with administrator actor type; event date; clarification message counted once.'],
        'feedback_resolution_transitions' => ['Feedback resolution transitions', 'status events', 'Canonical actual status transitions entering resolved; repeated resolution after reopening counts again.'],
    ];

    public function __construct(private StudentInsightsReadModel $students) {}

    /** One scalar booking projection; joins cannot multiply booking IDs. Invalid non-null owners stay excluded. */
    private function bookingPopulation(TutorInsightsContext $context): Builder
    {
        return DB::table('bookings as b')->whereNull('b.deleted_at')
            ->where(fn (Builder $owners): Builder => $owners->whereNull('b.student_id')->orWhereIn('b.student_id', $this->students->population()->select('id')))
            ->when($context->period->studentId, fn (Builder $query): Builder => $query->where('b.student_id', $context->period->studentId));
    }

    public function appointments(TutorInsightsContext $context, bool $upcoming = false): Builder
    {
        $query = $this->bookingPopulation($context)->leftJoin('session_types as t', 't.id', '=', 'b.session_type_id')
            ->when($context->sessionTypeId, fn (Builder $query): Builder => $query->where('b.session_type_id', $context->sessionTypeId));
        if ($upcoming) {
            $query->where('b.status', 'confirmed')->where('b.start_at_utc', '>=', $context->period->generatedAt)->where('b.start_at_utc', '<', $context->upcomingUntil());
            if (! $context->upcomingApplies()) {
                $query->whereRaw('1=0');
            }
        } else {
            $query->where('b.start_at_utc', '>=', $context->period->from)->where('b.start_at_utc', '<', $context->period->effectiveUntil())
                ->when($context->outcome, fn (Builder $query): Builder => $query->where('b.status', $context->outcome));
        }

        return DB::query()->fromSub($query->selectRaw("b.id as record_id, b.student_id, b.session_type_id, COALESCE(t.title, 'Unknown type') as session_type, t.slug as type_slug, b.status, b.start_at_utc as occurred_at, b.start_at_utc as interval_start, b.end_at_utc as interval_end, CASE WHEN b.end_at_utc > b.start_at_utc THEN TIMESTAMPDIFF(SECOND, b.start_at_utc, b.end_at_utc) ELSE NULL END as duration_seconds"), 'r');
    }

    /** PHP IANA transitions produce bounded SQL expressions; no database timezone tables are required. */
    private function localTime(TutorInsightsContext $context, bool $upcoming = false): string
    {
        $from = $upcoming ? $context->period->generatedAt : $context->period->from;
        $until = $upcoming ? $context->upcomingUntil() : $context->period->until;
        $cases = [];
        foreach (array_reverse((new DateTimeZone($context->period->timezone))->getTransitions($from->timestamp, $until->timestamp) ?: []) as $transition) {
            $at = CarbonImmutable::createFromTimestampUTC($transition['ts'])->format('Y-m-d H:i:s');
            $offset = (int) $transition['offset'];
            $cases[] = "WHEN occurred_at >= '{$at}' THEN DATE_ADD(occurred_at, INTERVAL {$offset} SECOND)";
        }
        $offset = $from->setTimezone($context->period->timezone)->getOffset();
        if ($cases === []) {
            return "DATE_ADD(occurred_at, INTERVAL {$offset} SECOND)";
        }

        return 'CASE '.implode(' ', $cases)." ELSE DATE_ADD(occurred_at, INTERVAL {$offset} SECOND) END";
    }

    private function teachingAggregate(Builder $query, TutorInsightsContext $context): Builder
    {
        $date = 'DATE('.$this->localTime($context).')';

        return $query->where('status', 'completed')->selectRaw("COUNT(*) as appointments_completed, SUM(duration_seconds) as recorded_teaching_seconds, COUNT(DISTINCT student_id) as identified_students_taught, COUNT(DISTINCT {$date}) as teaching_days, COUNT(student_id) as linked_completed_appointments, COUNT(CASE WHEN student_id IS NULL THEN 1 END) as unlinked_completed_appointments, COUNT(CASE WHEN duration_seconds IS NULL THEN 1 END) as invalid_durations, COUNT(CASE WHEN type_slug='diagnostic-session' THEN 1 END) as diagnostic_type_completed");
    }

    private function teachingValues(array $row, bool $applies = true): array
    {
        $metrics = [];
        foreach (array_slice(self::DEFINITIONS, 0, 8, true) as $id => [$label, $unit, $source]) {
            $value = (int) ($row[$id] ?? 0);
            $state = $applies ? 'recorded' : 'not_applicable';
            $invalid = $id === 'recorded_teaching_seconds' ? (int) ($row['invalid_durations'] ?? 0) : 0;
            if ($invalid > 0 && $applies) {
                $state = isset($row[$id]) ? 'partial' : 'unavailable';
                $value = isset($row[$id]) ? (int) $row[$id] : null;
            }
            if ($id === 'sessions_per_identified_student') {
                $denominator = (int) ($row['identified_students_taught'] ?? 0);
                $value = $denominator > 0 ? (int) $row['linked_completed_appointments'] / $denominator : null;
                if ($value === null) {
                    $state = 'not_applicable';
                }
            }
            $metrics[$id] = compact('id', 'label', 'unit', 'source', 'state', 'invalid') + ['value' => $applies ? $value : null, 'denominator' => $id === 'sessions_per_identified_student' ? (int) ($row['identified_students_taught'] ?? 0) : null];
        }

        return $metrics;
    }

    public function metrics(TutorInsightsContext $context): array
    {
        if ($context->view === 'teaching') {
            return $this->teachingValues((array) $this->teachingAggregate($this->appointments($context), $context)->first(), $context->teachingApplies());
        }
        $counts = $this->operationalRecords($context)->selectRaw('metric_id, COUNT(*) as total')->groupBy('metric_id')->pluck('total', 'metric_id');
        $metrics = [];
        foreach (array_slice(self::DEFINITIONS, 8, null, true) as $id => [$label, $unit, $source]) {
            if ($context->activity !== 'all' && ! in_array($id, self::activityMetrics($context->activity), true)) {
                continue;
            }
            $metrics[$id] = ['id' => $id, 'label' => $label, 'unit' => $unit, 'source' => $source, 'state' => 'recorded', 'invalid' => 0, 'denominator' => null, 'value' => (int) ($counts[$id] ?? 0)];
        }

        return $metrics;
    }

    public static function activityMetrics(string $kind): array
    {
        return match ($kind) {
            'cancellations' => ['cancellation_actions'], 'reschedules' => ['reschedule_actions'], 'lms_reviews' => ['lms_final_review_records'],
            default => ['feedback_conversations_received', 'feedback_administrator_messages', 'feedback_resolution_transitions'],
        };
    }

    /** Canonical action sources only; never audit payloads, notifications, subjects or message bodies. */
    public function operationalRecords(TutorInsightsContext $context): Builder
    {
        $queries = [];
        foreach (['cancellations' => ['booking_policy_decisions', 'cancellation_actions'], 'reschedules' => ['session_reschedules', 'reschedule_actions']] as $kind => [$table, $metric]) {
            $query = $this->bookingPopulation($context)->join($table.' as e', 'e.booking_id', '=', 'b.id');
            if ($kind === 'cancellations') {
                $query->where('e.action', 'cancellation');
            }
            $queries[] = $query->selectRaw("'{$kind}' as source_kind, e.id as record_id, e.created_at as occurred_at, b.student_id, b.id as booking_id, NULL as submission_id, NULL as conversation_id, e.actor_type, '{$metric}' as metric_id, '{$kind}' as classification");
        }
        $queries[] = DB::table('lms_assignment_submissions as e')->whereIn('e.student_id', $this->students->population($context->period->studentId)->select('id'))
            ->join('lms_lesson_blocks as block', 'block.id', '=', 'e.block_id')->where('block.kind', 'assignment')
            ->join('lms_lessons as lesson', 'lesson.id', '=', 'block.lesson_id')
            ->join('lms_sections as section', fn ($join) => $join->on('section.id', '=', 'lesson.section_id')->on('section.course_id', '=', 'lesson.course_id'))
            ->join('lms_courses as course', 'course.id', '=', 'lesson.course_id')
            ->where(fn (Builder $owner): Builder => $owner->where(fn (Builder $catalog): Builder => $catalog->where('course.kind', 'catalog')->whereNull('course.owner_student_id'))
                ->orWhere(fn (Builder $private): Builder => $private->where('course.kind', 'private')->whereColumn('course.owner_student_id', 'e.student_id')))
            ->whereIn('e.status', ['approved', 'needs_revision'])->whereColumn('e.reviewed_at', '>=', 'e.created_at')
            ->selectRaw("'lms_reviews' as source_kind, e.id as record_id, e.reviewed_at as occurred_at, e.student_id, NULL as booking_id, e.id as submission_id, NULL as conversation_id, 'administrator' as actor_type, 'lms_final_review_records' as metric_id, e.status as classification");
        $conversations = FeedbackConversation::query()->available()->when($context->period->studentId, fn ($query) => $query->where('student_id', $context->period->studentId))->select('id');
        $queries[] = DB::table('feedback_conversations as c')->whereIn('c.id', $conversations)
            ->selectRaw("'feedback' as source_kind, c.id as record_id, c.created_at as occurred_at, c.student_id, NULL as booking_id, NULL as submission_id, c.id as conversation_id, 'student' as actor_type, 'feedback_conversations_received' as metric_id, 'received' as classification");
        $queries[] = DB::table('feedback_events as e')->join('feedback_conversations as c', 'c.id', '=', 'e.conversation_id')->whereIn('c.id', $conversations)
            ->whereColumn('e.created_at', '>=', 'c.created_at')
            ->where(fn (Builder $events): Builder => $events->where(fn (Builder $messages): Builder => $messages->where('e.kind', 'message')->where('e.actor_type', 'administrator'))
                ->orWhere(fn (Builder $status): Builder => $status->where('e.kind', 'status')->where('e.to_status', 'resolved')->whereNotNull('e.from_status')->where('e.from_status', '<>', 'resolved')))
            ->selectRaw("'feedback' as source_kind, e.id as record_id, e.created_at as occurred_at, c.student_id, NULL as booking_id, NULL as submission_id, c.id as conversation_id, e.actor_type, CASE WHEN e.kind='message' THEN 'feedback_administrator_messages' ELSE 'feedback_resolution_transitions' END as metric_id, CASE WHEN e.kind='message' THEN 'message' ELSE 'resolved' END as classification");
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'r')->where('occurred_at', '>=', $context->period->from)->where('occurred_at', '<', $context->period->effectiveUntil())
            ->when($context->activity !== 'all', fn (Builder $query): Builder => $query->where('source_kind', $context->activity));
    }

    public function contributions(TutorInsightsContext $context, string $dataset): Builder
    {
        $upcoming = $dataset === 'upcoming';
        $query = $dataset === 'operations' ? $this->operationalRecords($context) : $this->appointments($context, $upcoming);
        $time = $this->localTime($context, $upcoming);
        $query->select('r.*')->selectRaw("DATE({$time}) as local_date")->orderBy('occurred_at');
        if ($dataset === 'operations') {
            $query->orderBy('source_kind')->orderBy('metric_id');
        }

        return $query->orderBy('record_id');
    }

    public function outcomes(TutorInsightsContext $context): array
    {
        $counts = $this->appointments($context)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($value): int => (int) $value)->all();

        return array_replace(array_fill_keys(array_keys(TutorInsightsContext::OUTCOMES), 0), $counts);
    }

    public function types(TutorInsightsContext $context): array
    {
        return $this->appointments($context)->selectRaw('session_type_id, session_type, COUNT(*) as appointments, COUNT(CASE WHEN status=\'completed\' THEN 1 END) as completed')->groupBy('session_type_id', 'session_type')->orderBy('session_type_id')->get()->all();
    }

    public function trends(TutorInsightsContext $context): array
    {
        $date = 'DATE('.$this->localTime($context).')';
        $bucket = $context->grouping === 'weekly' ? "DATE_SUB({$date}, INTERVAL WEEKDAY({$date}) DAY)" : "DATE_FORMAT({$date}, '%Y-%m-01')";
        $rows = $this->teachingAggregate($this->appointments($context), $context)->selectRaw("{$bucket} as bucket")->groupBy('bucket')->get()->keyBy('bucket');
        $start = CarbonImmutable::parse($context->period->startDate, $context->period->timezone);
        $end = $context->period->effectiveUntil()->setTimezone($context->period->timezone);
        $buckets = [];
        for ($day = $context->grouping === 'weekly' ? $start->startOfWeek() : $start->startOfMonth(); $day->lt($end); $day = $context->grouping === 'weekly' ? $day->addWeek() : $day->addMonth()) {
            $until = $context->grouping === 'weekly' ? $day->addWeek() : $day->addMonth();
            $buckets[] = ['date' => $day->toDateString(), 'partial' => $day->lt($start) || $until->gt($end), 'metrics' => $this->teachingValues((array) ($rows[$day->toDateString()] ?? []), $context->teachingApplies())];
        }

        return $buckets;
    }

    public function distributions(TutorInsightsContext $context): array
    {
        $time = $this->localTime($context);
        $groups = ['weekday' => "WEEKDAY({$time})", 'time_band' => "FLOOR(HOUR({$time}) / 6)"];
        $result = [];
        foreach ($groups as $kind => $expression) {
            $rows = $this->teachingAggregate($this->appointments($context), $context)->selectRaw("{$expression} as bucket")->groupBy('bucket')->get()->keyBy('bucket');
            foreach ($kind === 'weekday' ? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] : ['Night · 00:00–05:59', 'Morning · 06:00–11:59', 'Afternoon · 12:00–17:59', 'Evening · 18:00–23:59'] as $index => $label) {
                $result[$kind][] = ['label' => $label, 'metrics' => $this->teachingValues((array) ($rows[$index] ?? []), $context->teachingApplies())];
            }
        }

        return $result;
    }

    public function studentSummaries(TutorInsightsContext $context): LengthAwarePaginator
    {
        return $this->teachingAggregate($this->appointments($context), $context)->whereNotNull('student_id')->addSelect('student_id')->groupBy('student_id')->orderBy('student_id')->paginate(20, ['*'], 'students_page')->withQueryString();
    }

    public function sessionTypes(): array
    {
        return SessionType::query()->orderBy('id')->pluck('title', 'id')->all();
    }

    public static function display(array $metric): string
    {
        if (in_array($metric['state'], ['unavailable', 'not_applicable'], true)) {
            return $metric['state'] === 'unavailable' ? 'Unavailable' : 'Not applicable';
        }
        $value = $metric['value'];
        if ($metric['unit'] === 'seconds') {
            return sprintf('%d:%02d:%02d', intdiv((int) $value, 3600), intdiv((int) $value % 3600, 60), (int) $value % 60);
        }

        return number_format((float) $value, $metric['unit'] === 'appointments per student' ? 2 : 0, '.', ',');
    }
}
