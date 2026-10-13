<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningMilestone;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\StudentProgressReport;
use App\Domains\Students\Models\StudentSkillAssessment;
use App\Domains\Students\Models\TeachingTag;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;

class StudentProgressReportService
{
    /** @return array<string, mixed> */
    public function data(StudentProgressReport $report, bool $studentView = false): array
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $from = CarbonImmutable::parse($report->date_from->toDateString(), $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($report->date_to->toDateString(), $timezone)->startOfDay()->addDay()->utc();
        $lessons = Booking::query()->where('student_id', $report->student_id)->where('status', 'completed')->where('start_at_utc', '>=', $from)->where('start_at_utc', '<', $to)->with('sessionType:id,title')->orderBy('start_at_utc')->get();
        $homework = Homework::query()->where('student_id', $report->student_id)->whereBetween('assigned_date', [$report->date_from->toDateString(), $report->date_to->toDateString()])->when($studentView, fn ($query) => $query->where('student_visible', true))->get();
        $assessments = StudentSkillAssessment::query()->where('student_id', $report->student_id)->where('assessed_at', '>=', $from)->where('assessed_at', '<', $to)->when($studentView, fn ($query) => $query->where('student_visible', true))->with('skill')->orderBy('assessed_at')->get();
        $milestones = LearningMilestone::query()->whereHas('plan', fn ($query) => $query->where('student_id', $report->student_id)->when($studentView, fn ($plans) => $plans->where('student_visible', true)))->where('completed_at', '>=', $from)->where('completed_at', '<', $to)->get();
        $resources = ResourceAssignment::query()->where('student_id', $report->student_id)->where('created_at', '>=', $from)->where('created_at', '<', $to)->when($studentView, fn ($query) => $query->where('student_visible', true))->whereHas('resource', fn ($query) => $query->published()->when($studentView, fn ($resources) => $resources->where('student_discoverable', true)))->with('resource:id,title')->get();
        $topics = TeachingTag::query()->where('student_id', $report->student_id)->whereIn('booking_id', $lessons->modelKeys())->when($studentView, fn ($query) => $query->where('student_visible', true))->pluck('label')->unique();

        return ['report' => $report, 'lessons' => $lessons, 'hours' => round($lessons->sum(fn (Booking $booking): int => max(0, $booking->end_at_utc->timestamp - $booking->start_at_utc->timestamp)) / 3600, 1),
            'homeworkCount' => $homework->count(), 'homeworkCompleted' => $homework->whereIn('status', ['reviewed', 'completed'])->count(),
            'assessments' => $assessments, 'milestones' => $milestones, 'resources' => $resources, 'topics' => $topics, 'timezone' => $timezone];
    }
}
