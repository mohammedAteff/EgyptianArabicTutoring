<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TutorWorkInput
{
    public const CATEGORIES = ['teaching' => 'Teaching', 'diagnostic' => 'Diagnostic consultation', 'lesson_preparation' => 'Lesson preparation', 'learning_materials' => 'Workbook & learning material creation', 'homework_review' => 'Homework review', 'student_communication' => 'Student communication', 'feedback' => 'Feedback handling', 'booking_admin' => 'Booking & scheduling', 'general_admin' => 'General administration', 'other' => 'Other work'];

    public function normalize(array $input, string $action): array
    {
        $rules = ['operation_id' => ['required', 'uuid'], 'version' => ['required_unless:action,start,manual', 'integer', 'min:1'], 'action' => ['required', Rule::in(['start', 'manual', 'pause', 'resume', 'stop', 'correct', 'confirm', 'exclude'])], 'administrator_id' => ['prohibited'], 'owner_id' => ['prohibited'], 'student_id' => ['prohibited'], 'elapsed_seconds' => ['prohibited'], 'duration_seconds' => ['prohibited'], 'client_timestamp' => ['prohibited'], 'reason' => ['nullable', 'string', 'max:500']];
        $effect = in_array($action, ['start', 'manual', 'correct'], true);
        if ($effect) {
            $rules += ['category' => ['required', Rule::in(array_keys(self::CATEGORIES))], 'description' => ['nullable', 'string', 'max:1000'], 'timezone' => ['required', 'timezone:all']];
        }
        if (in_array($action, ['manual', 'correct'], true)) {
            $rules += ['placement' => ['required', Rule::in(['placed', 'unplaced'])], 'work_date' => ['required_if:placement,unplaced', 'prohibited_if:placement,placed', 'date_format:Y-m-d'], 'hours' => ['required_if:placement,unplaced', 'prohibited_if:placement,placed', 'integer', 'between:0,1000000'], 'minutes' => ['required_if:placement,unplaced', 'prohibited_if:placement,placed', 'integer', 'between:0,59'], 'intervals' => ['required_if:placement,placed', 'prohibited_if:placement,unplaced', 'array', 'min:1', 'max:200'], 'intervals.*' => ['array:start,end,start_fold,end_fold'], 'intervals.*.start' => ['required', 'date_format:Y-m-d\TH:i:s'], 'intervals.*.end' => ['required', 'date_format:Y-m-d\TH:i:s'], 'intervals.*.start_fold' => ['nullable', Rule::in(['reject', 'first', 'second'])], 'intervals.*.end_fold' => ['nullable', Rule::in(['reject', 'first', 'second'])]];
        }
        $data = Validator::make(array_replace($input, ['action' => $action]), $rules, [
            'intervals.*.start.date_format' => 'Choose a complete local start date and time without a timezone offset.',
            'intervals.*.end.date_format' => 'Choose a complete local end date and time without a timezone offset.',
            'work_date.date_format' => 'Choose a valid work date.',
            'operation_id.uuid' => 'The request identity is invalid. Refresh this interface before trying again.',
        ], [
            'intervals.*.start' => 'local start date and time', 'intervals.*.end' => 'local end date and time',
            'intervals.*.start_fold' => 'start occurrence', 'intervals.*.end_fold' => 'end occurrence',
            'operation_id' => 'request identity', 'work_date' => 'work date', 'unplaced_seconds' => 'declared duration',
            'category' => 'work category', 'timezone' => 'entry timezone', 'reason' => 'private reason', 'description' => 'private description',
        ])->validate();
        $result = ['operation_id' => strtolower($data['operation_id']), 'action' => $action, 'version' => isset($data['version']) ? (int) $data['version'] : null, 'reason' => trim($data['reason'] ?? '')];
        if (! $effect) {
            return $result;
        }
        $result += ['category' => $data['category'], 'description' => trim($data['description'] ?? ''), 'timezone' => $data['timezone']];
        if ($action === 'start') {
            return $result;
        }
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $result['placement'] = $data['placement'];
        $result['intervals'] = [];
        $result['unplaced_seconds'] = null;
        if ($data['placement'] === 'unplaced') {
            if ($data['work_date'] < '1000-01-01') {
                throw ValidationException::withMessages(['work_date' => 'The database supports work dates from year 1000 onward.']);
            }
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['work_date'], $data['timezone']);
            $duration = (int) $data['hours'] * 3600 + (int) $data['minutes'] * 60;
            if ($data['work_date'] > $now->setTimezone($data['timezone'])->toDateString() || $date->toDateString() !== $data['work_date']) {
                throw ValidationException::withMessages(['work_date' => 'Choose an existing local date that is not in the future.']);
            }
            if ($duration <= 0 || $duration > $date->addDay()->timestamp - $date->timestamp) {
                throw ValidationException::withMessages(['hours' => 'Duration must be positive and fit within that local calendar date. Use a precise interval for work spanning dates.']);
            }
            $result['work_date'] = $data['work_date'];
            $result['unplaced_seconds'] = $duration;
        } else {
            foreach ($data['intervals'] as $index => $interval) {
                $points = [];
                foreach (['start', 'end'] as $point) {
                    if (substr($interval[$point], 0, 10) < '1000-01-01') {
                        throw ValidationException::withMessages(["intervals.$index.$point" => 'The database supports dates from year 1000 onward.']);
                    }
                    try {
                        $points[$point] = app(TimezoneService::class)->resolveLocalWallTime(str_replace('T', ' ', $interval[$point]), $data['timezone'], $interval[$point.'_fold'] ?? 'reject')->startOfSecond();
                    } catch (DstGapException) {
                        throw ValidationException::withMessages(["intervals.$index.$point" => 'This local time does not exist because the clock changes. Choose another time.']);
                    } catch (DstFoldAmbiguityException) {
                        throw ValidationException::withMessages(["intervals.$index.$point" => 'This local time occurs twice. Choose the earlier or later occurrence.']);
                    }
                }
                if ($points['end']->lte($points['start']) || $points['end']->gt($now) || $points['start']->gt($now)) {
                    throw ValidationException::withMessages(["intervals.$index.end" => 'End must be after start, and both times must be in the past.']);
                }
                $result['intervals'][] = ['started_at' => $points['start']->toDateTimeString(), 'ended_at' => $points['end']->toDateTimeString()];
            }
            usort($result['intervals'], fn (array $a, array $b): int => strcmp($a['started_at'], $b['started_at']));
            foreach ($result['intervals'] as $index => $interval) {
                if ($index > 0 && $interval['started_at'] < $result['intervals'][$index - 1]['ended_at']) {
                    throw ValidationException::withMessages(['intervals' => 'Intervals within one entry must not overlap.']);
                }
            }
            $result['work_date'] = CarbonImmutable::parse($result['intervals'][0]['started_at'], 'UTC')->setTimezone($data['timezone'])->toDateString();
        }

        return $result;
    }
}
